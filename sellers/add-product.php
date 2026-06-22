<?php

declare(strict_types=1);

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

/*
|--------------------------------------------------------------------------
| SELLER AUTHORIZATION
|--------------------------------------------------------------------------
*/

$user = currentUser();

if (!$user)
{
    header("Location: ../login.php");
    exit;
}

$sellerId = (int)$user['id'];

/*
|--------------------------------------------------------------------------
| PAGE VARIABLES
|--------------------------------------------------------------------------
*/

$success = "";
$error = "";

$formData = [
    'name' => '',
    'sku' => '',
    'price' => '',
    'sale_price' => '',
    'stock' => 0,
    'min_stock_level' => 5,
    'weight' => '',
    'short_description' => '',
    'description' => '',
    'category_id' => '',
    'status' => 'active',
    'minimum_order_qty' => 1,
    'wholesale_price' => 0
];

/*
|--------------------------------------------------------------------------
| CSRF PROTECTION
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token']))
{
    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );
}

$csrfToken = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| VERIFY SHOP
|--------------------------------------------------------------------------
*/

$shopStmt = $conn->prepare("
    SELECT
        id,
        seller_id,
        shop_name,
        status,
        suspended,
        verified
    FROM shops
    WHERE seller_id = ?
    LIMIT 1
");

$shopStmt->bind_param(
    "i",
    $sellerId
);

$shopStmt->execute();

$shop =
$shopStmt
->get_result()
->fetch_assoc();

if (!$shop)
{
    die(
        "You must create a shop before adding products."
    );
}

if ($shop['status'] !== 'approved')
{
    die(
        "Your shop is awaiting approval by the administrator."
    );
}

if ((int)$shop['suspended'] === 1)
{
    die(
        "Your shop has been suspended. Contact support."
    );
}

$shopId = (int)$shop['id'];

/*
|--------------------------------------------------------------------------
| LOAD ACTIVE CATEGORIES
|--------------------------------------------------------------------------
*/

$categoriesStmt = $conn->prepare("
    SELECT
        id,
        category_name
    FROM categories
    WHERE status = 'active'
    ORDER BY category_name ASC
");

$categoriesStmt->execute();

$categories =
$categoriesStmt
->get_result();

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function cleanInput(string $value): string
{
    return trim(
        htmlspecialchars(
            $value,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}

function generateSku(): string
{
    return 'SKU-' .
        strtoupper(
            substr(
                md5(
                    uniqid(
                        '',
                        true
                    )
                ),
                0,
                10
            )
        );
}

function createUniqueSlug(
    mysqli $conn,
    string $name
): string
{
    $baseSlug = strtolower(
        trim(
            preg_replace(
                '/[^a-zA-Z0-9]+/',
                '-',
                $name
            ),
            '-'
        )
    );

    $slug = $baseSlug;
    $counter = 1;

    while (true)
    {
        $stmt = $conn->prepare("
            SELECT id
            FROM products
            WHERE slug = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "s",
            $slug
        );

        $stmt->execute();

        if (
            $stmt
            ->get_result()
            ->num_rows === 0
        )
        {
            return $slug;
        }

        $slug =
        $baseSlug .
        '-' .
        $counter;

        $counter++;
    }
}

function skuExists(
    mysqli $conn,
    string $sku
): bool
{
    $stmt = $conn->prepare("
        SELECT id
        FROM products
        WHERE sku = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "s",
        $sku
    );

    $stmt->execute();

    return
    $stmt
    ->get_result()
    ->num_rows > 0;
}

/*
|--------------------------------------------------------------------------
| HANDLE FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_product'])
)
{
    try
    {
        /*
        |--------------------------------------------------------------------------
        | CSRF VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals(
                $_SESSION['csrf_token'],
                $_POST['csrf_token']
            )
        )
        {
            throw new Exception(
                "Invalid security token."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | COLLECT INPUTS
        |--------------------------------------------------------------------------
        */

        $formData['name'] =
        cleanInput(
            $_POST['name'] ?? ''
        );

        $formData['sku'] =
        cleanInput(
            $_POST['sku'] ?? ''
        );

        $formData['short_description'] =
        trim(
            $_POST['short_description'] ?? ''
        );

        $formData['description'] =
        trim(
            $_POST['description'] ?? ''
        );

        $formData['category_id'] =
        (int)(
            $_POST['category_id'] ?? 0
        );

        $formData['price'] =
        (float)(
            $_POST['price'] ?? 0
        );

        $formData['sale_price'] =
        !empty($_POST['sale_price'])
        ? (float)$_POST['sale_price']
        : null;

        $formData['stock'] =
        (int)(
            $_POST['stock'] ?? 0
        );

        $formData['min_stock_level'] =
        (int)(
            $_POST['min_stock_level'] ?? 5
        );

        $formData['weight'] =
        !empty($_POST['weight'])
        ? (float)$_POST['weight']
        : null;

        $formData['status'] =
        $_POST['status'] ?? 'active';

        $featured =
        isset($_POST['featured'])
        ? 1
        : 0;

        $isWholesale =
        isset($_POST['is_wholesale'])
        ? 1
        : 0;

        $formData['minimum_order_qty'] =
        (int)(
            $_POST['minimum_order_qty'] ?? 1
        );

        $formData['wholesale_price'] =
        (float)(
            $_POST['wholesale_price'] ?? 0
        );

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if (empty($formData['name']))
        {
            throw new Exception(
                "Product name is required."
            );
        }

        if (
            strlen(
                $formData['name']
            ) < 3
        )
        {
            throw new Exception(
                "Product name is too short."
            );
        }

        if (
            $formData['category_id'] <= 0
        )
        {
            throw new Exception(
                "Select a category."
            );
        }

        if (
            $formData['price'] <= 0
        )
        {
            throw new Exception(
                "Price must be greater than zero."
            );
        }

        if (
            $formData['sale_price'] !== null &&
            $formData['sale_price'] >
            $formData['price']
        )
        {
            throw new Exception(
                "Sale price cannot exceed product price."
            );
        }

        if (
            $formData['stock'] < 0
        )
        {
            throw new Exception(
                "Stock cannot be negative."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SKU
        |--------------------------------------------------------------------------
        */

        $sku = $formData['sku'];

        if (empty($sku))
        {
            do
            {
                $sku = generateSku();
            }
            while (
                skuExists(
                    $conn,
                    $sku
                )
            );
        }
        else
        {
            if (
                skuExists(
                    $conn,
                    $sku
                )
            )
            {
                throw new Exception(
                    "SKU already exists."
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SLUG
        |--------------------------------------------------------------------------
        */

        $slug =
        createUniqueSlug(
            $conn,
            $formData['name']
        );

        /*
        |--------------------------------------------------------------------------
        | IMAGE UPLOAD
        |--------------------------------------------------------------------------
        */

        $image = null;

        if (
            isset($_FILES['image']) &&
            $_FILES['image']['error']
            !== UPLOAD_ERR_NO_FILE
        )
        {
            if (
                $_FILES['image']['error']
                !== UPLOAD_ERR_OK
            )
            {
                throw new Exception(
                    "Image upload failed."
                );
            }

            $allowedExtensions = [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ];

            $extension =
            strtolower(
                pathinfo(
                    $_FILES['image']['name'],
                    PATHINFO_EXTENSION
                )
            );

            if (
                !in_array(
                    $extension,
                    $allowedExtensions
                )
            )
            {
                throw new Exception(
                    "Only JPG, PNG and WEBP images are allowed."
                );
            }

            if (
                $_FILES['image']['size']
                > 5 * 1024 * 1024
            )
            {
                throw new Exception(
                    "Image cannot exceed 5MB."
                );
            }

            if (
                !getimagesize(
                    $_FILES['image']['tmp_name']
                )
            )
            {
                throw new Exception(
                    "Invalid image file."
                );
            }

            $uploadDir =
            "/uploads/products/";

            if (
                !is_dir(
                    $uploadDir
                )
            )
            {
                mkdir(
                    $uploadDir,
                    0755,
                    true
                );
            }

            $fileName =
            uniqid(
                'product_',
                true
            ) .
            '.' .
            $extension;

            $target =
            $uploadDir .
            $fileName;

            if (
                !move_uploaded_file(
                    $_FILES['image']['tmp_name'],
                    $target
                )
            )
            {
                throw new Exception(
                    "Failed to store image."
                );
            }

            $image = $target;
        }

        /*
        |--------------------------------------------------------------------------
        | DATABASE TRANSACTION
        |--------------------------------------------------------------------------
        */

        $conn->begin_transaction();

        $stmt = $conn->prepare("
            INSERT INTO products (

                shop_id,
                category_id,

                name,
                slug,
                sku,

                short_description,
                description,

                price,
                sale_price,

                stock,
                min_stock_level,

                image,
                featured_image,

                weight,

                status,

                featured,

                is_wholesale,
                minimum_order_qty,
                wholesale_price,

                approved

            )

            VALUES (

                ?, ?, ?, ?, ?,
                ?, ?,
                ?, ?,
                ?, ?,
                ?, ?,
                ?,
                ?,
                ?,
                ?, ?, ?,
                0

            )
        ");

        $stmt->bind_param(
            "iisssssddiissdsiiid",

            $shopId,
            $formData['category_id'],

            $formData['name'],
            $slug,
            $sku,

            $formData['short_description'],
            $formData['description'],

            $formData['price'],
            $formData['sale_price'],

            $formData['stock'],
            $formData['min_stock_level'],

            $image,
            $image,

            $formData['weight'],

            $formData['status'],

            $featured,

            $isWholesale,
            $formData['minimum_order_qty'],
            $formData['wholesale_price']
        );

        if (!$stmt->execute())
        {
            throw new Exception(
                $stmt->error
            );
        }

        $productId =
        $conn->insert_id;

        $conn->commit();

        $success =
        "Product submitted successfully and is awaiting approval.";

        $formData = [
            'name' => '',
            'sku' => '',
            'price' => '',
            'sale_price' => '',
            'stock' => 0,
            'min_stock_level' => 5,
            'weight' => '',
            'short_description' => '',
            'description' => '',
            'category_id' => '',
            'status' => 'active',
            'minimum_order_qty' => 1,
            'wholesale_price' => 0
        ];
    }
    catch(Exception $e)
    {
        if (
            $conn->errno
        )
        {
            $conn->rollback();
        }

        $error =
        $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>Add Product</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
rel="stylesheet">

<style>

body{
    background:#f4f6f9;
}

.page-card{
    background:#fff;
    border-radius:15px;
    padding:30px;
    box-shadow:0 3px 15px rgba(0,0,0,.08);
}

.required{
    color:red;
}

.preview-image{
    max-height:200px;
    border-radius:10px;
    display:none;
    margin-top:10px;
}

</style>

</head>

<body>

<div class="container py-4">

<div class="row justify-content-center">

<div class="col-lg-10">

<div class="page-card">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2 class="mb-1">
<i class="fas fa-box"></i>
Add Product
</h2>

<p class="text-muted mb-0">
Create a new product listing.
</p>

</div>

<a
href="products.php"
class="btn btn-outline-secondary">

<i class="fas fa-arrow-left"></i>
Back to Products

</a>

</div>

<?php if(!empty($success)): ?>

<div class="alert alert-success">

<?= htmlspecialchars($success) ?>

</div>

<?php endif; ?>

<?php if(!empty($error)): ?>

<div class="alert alert-danger">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>

<form
method="POST"
enctype="multipart/form-data">

<input
type="hidden"
name="csrf_token"
value="<?= htmlspecialchars($csrfToken) ?>">

<div class="row">

<!-- PRODUCT NAME -->

<div class="col-md-8 mb-3">

<label class="form-label">

Product Name
<span class="required">*</span>

</label>

<input
type="text"
name="name"
class="form-control"
required
value="<?= htmlspecialchars($formData['name']) ?>">

</div>

<!-- SKU -->

<div class="col-md-4 mb-3">

<label class="form-label">

SKU

</label>

<input
type="text"
name="sku"
class="form-control"
value="<?= htmlspecialchars($formData['sku']) ?>">

</div>

<!-- CATEGORY -->

<div class="col-md-6 mb-3">

<label class="form-label">

Category
<span class="required">*</span>

</label>

<select
name="category_id"
class="form-select"
required>

<option value="">
Select Category
</option>

<?php while($cat = $categories->fetch_assoc()): ?>

<option
value="<?= $cat['id'] ?>"
<?= ($formData['category_id']==$cat['id']) ? 'selected' : '' ?>>

<?= htmlspecialchars($cat['category_name']) ?>

</option>

<?php endwhile; ?>

</select>

</div>

<!-- STATUS -->

<div class="col-md-6 mb-3">

<label class="form-label">

Status

</label>

<select
name="status"
class="form-select">

<option value="active">
Active
</option>

<option value="inactive">
Inactive
</option>

<option value="out_of_stock">
Out of Stock
</option>

</select>

</div>

<!-- PRICE -->

<div class="col-md-4 mb-3">

<label class="form-label">

Price
<span class="required">*</span>

</label>

<input
type="number"
step="0.01"
min="0"
name="price"
class="form-control"
required
value="<?= htmlspecialchars((string)$formData['price']) ?>">

</div>

<!-- SALE PRICE -->

<div class="col-md-4 mb-3">

<label class="form-label">

Sale Price

</label>

<input
type="number"
step="0.01"
min="0"
name="sale_price"
class="form-control"
value="<?= htmlspecialchars((string)$formData['sale_price']) ?>">

</div>

<!-- STOCK -->

<div class="col-md-4 mb-3">

<label class="form-label">

Stock Quantity

</label>

<input
type="number"
min="0"
name="stock"
class="form-control"
value="<?= htmlspecialchars((string)$formData['stock']) ?>">

</div>

<!-- MIN STOCK -->

<div class="col-md-4 mb-3">

<label class="form-label">

Minimum Stock

</label>

<input
type="number"
min="0"
name="min_stock_level"
class="form-control"
value="<?= htmlspecialchars((string)$formData['min_stock_level']) ?>">

</div>

<!-- WEIGHT -->

<div class="col-md-4 mb-3">

<label class="form-label">

Weight (KG)

</label>

<input
type="number"
step="0.01"
min="0"
name="weight"
class="form-control"
value="<?= htmlspecialchars((string)$formData['weight']) ?>">

</div>

<!-- MOQ -->

<div class="col-md-4 mb-3">

<label class="form-label">

Minimum Order Qty

</label>

<input
type="number"
min="1"
name="minimum_order_qty"
class="form-control"
value="<?= htmlspecialchars((string)$formData['minimum_order_qty']) ?>">

</div>

<!-- WHOLESALE -->

<div class="col-md-6 mb-3">

<label class="form-label">

Wholesale Price

</label>

<input
type="number"
step="0.01"
min="0"
name="wholesale_price"
class="form-control"
value="<?= htmlspecialchars((string)$formData['wholesale_price']) ?>">

</div>

<!-- IMAGE -->

<div class="col-md-6 mb-3">

<label class="form-label">

Product Image

</label>

<input
type="file"
name="image"
class="form-control"
accept=".jpg,.jpeg,.png,.webp"
onchange="previewProductImage(event)">

<img
id="preview"
class="preview-image">

</div>

<!-- SHORT DESCRIPTION -->

<div class="col-12 mb-3">

<label class="form-label">

Short Description

</label>

<textarea
name="short_description"
class="form-control"
rows="3"><?= htmlspecialchars($formData['short_description']) ?></textarea>

</div>

<!-- DESCRIPTION -->

<div class="col-12 mb-3">

<label class="form-label">

Description

</label>

<textarea
name="description"
class="form-control"
rows="8"><?= htmlspecialchars($formData['description']) ?></textarea>

</div>

<!-- OPTIONS -->

<div class="col-md-6 mb-3">

<div class="form-check">

<input
class="form-check-input"
type="checkbox"
name="featured"
id="featured">

<label
class="form-check-label"
for="featured">

Featured Product

</label>

</div>

</div>

<div class="col-md-6 mb-3">

<div class="form-check">

<input
class="form-check-input"
type="checkbox"
name="is_wholesale"
id="wholesale">

<label
class="form-check-label"
for="wholesale">

Wholesale Product

</label>

</div>

</div>

<!-- SUBMIT -->

<div class="col-12 mt-3">

<button
type="submit"
name="save_product"
class="btn btn-primary btn-lg">

<i class="fas fa-save"></i>
Save Product

</button>

</div>

</div>

</form>

</div>

</div>

</div>

</div>

<script>

function previewProductImage(event)
{
    const preview =
    document.getElementById('preview');

    preview.src =
    URL.createObjectURL(
        event.target.files[0]
    );

    preview.style.display =
    "block";
}

</script>

</body>
</html>