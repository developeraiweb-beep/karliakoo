<?php

declare(strict_types=1);

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

if(!$user)
{
    header("Location: ../login.php");
    exit;
}

$sellerId = (int)$user['id'];

$success = "";
$error = "";

/*
|--------------------------------------------------------------------------
| PRODUCT ID
|--------------------------------------------------------------------------
*/

$productId =
(int)($_GET['id'] ?? 0);

if($productId <= 0)
{
    die("Invalid product.");
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if(empty($_SESSION['csrf_token']))
{
    $_SESSION['csrf_token'] =
    bin2hex(
        random_bytes(32)
    );
}

$csrfToken =
$_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| VERIFY SHOP
|--------------------------------------------------------------------------
*/

$shopStmt = $conn->prepare("
    SELECT
        id,
        shop_name,
        status,
        suspended
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

if(!$shop)
{
    die(
        "Shop not found."
    );
}

if(
    $shop['status'] !== 'approved'
)
{
    die(
        "Shop not approved."
    );
}

if(
    (int)$shop['suspended'] === 1
)
{
    die(
        "Shop suspended."
    );
}

$shopId =
(int)$shop['id'];

/*
|--------------------------------------------------------------------------
| LOAD PRODUCT
|--------------------------------------------------------------------------
*/

$productStmt = $conn->prepare("
    SELECT *
    FROM products
    WHERE id = ?
    AND shop_id = ?
    LIMIT 1
");

$productStmt->bind_param(
    "ii",
    $productId,
    $shopId
);

$productStmt->execute();

$product =
$productStmt
->get_result()
->fetch_assoc();

if(!$product)
{
    die(
        "Product not found."
    );
}

/*
|--------------------------------------------------------------------------
| LOAD CATEGORIES
|--------------------------------------------------------------------------
*/

$categories =
$conn->query("
    SELECT
        id,
        category_name
    FROM categories
    WHERE status='active'
    ORDER BY category_name ASC
");

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function createUniqueSlugForUpdate(
    mysqli $conn,
    string $name,
    int $productId
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

    while(true)
    {
        $stmt = $conn->prepare("
            SELECT id
            FROM products
            WHERE slug = ?
            AND id != ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "si",
            $slug,
            $productId
        );

        $stmt->execute();

        if(
            $stmt
            ->get_result()
            ->num_rows === 0
        )
        {
            return $slug;
        }

        $slug =
        $baseSlug .
        "-" .
        $counter;

        $counter++;
    }
}

/*
|--------------------------------------------------------------------------
| UPDATE PRODUCT
|--------------------------------------------------------------------------
*/

if(
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_product'])
)
{
    try
    {
        /*
        |--------------------------------------------------------------------------
        | CSRF
        |--------------------------------------------------------------------------
        */

        if(
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
        | COLLECT DATA
        |--------------------------------------------------------------------------
        */

        $categoryId =
        (int)($_POST['category_id'] ?? 0);

        $name =
        trim($_POST['name'] ?? '');

        $sku =
        trim($_POST['sku'] ?? '');

        $shortDescription =
        trim(
            $_POST['short_description']
            ?? ''
        );

        $description =
        trim(
            $_POST['description']
            ?? ''
        );

        $price =
        (float)(
            $_POST['price'] ?? 0
        );

        $salePrice =
        !empty($_POST['sale_price'])
        ? (float)$_POST['sale_price']
        : null;

        $stock =
        (int)(
            $_POST['stock'] ?? 0
        );

        $minStock =
        (int)(
            $_POST['min_stock_level']
            ?? 5
        );

        $weight =
        !empty($_POST['weight'])
        ? (float)$_POST['weight']
        : null;

        $status =
        $_POST['status']
        ?? 'active';

        $featured =
        isset($_POST['featured'])
        ? 1
        : 0;

        $isWholesale =
        isset($_POST['is_wholesale'])
        ? 1
        : 0;

        $minimumOrderQty =
        (int)(
            $_POST['minimum_order_qty']
            ?? 1
        );

        $wholesalePrice =
        (float)(
            $_POST['wholesale_price']
            ?? 0
        );

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if(empty($name))
        {
            throw new Exception(
                "Product name is required."
            );
        }

        if($categoryId <= 0)
        {
            throw new Exception(
                "Select a category."
            );
        }

        if($price <= 0)
        {
            throw new Exception(
                "Invalid price."
            );
        }

        if(
            $salePrice !== null &&
            $salePrice > $price
        )
        {
            throw new Exception(
                "Sale price cannot exceed normal price."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | UNIQUE SKU
        |--------------------------------------------------------------------------
        */

        $skuCheck =
        $conn->prepare("
            SELECT id
            FROM products
            WHERE sku = ?
            AND id != ?
            LIMIT 1
        ");

        $skuCheck->bind_param(
            "si",
            $sku,
            $productId
        );

        $skuCheck->execute();

        if(
            $skuCheck
            ->get_result()
            ->num_rows > 0
        )
        {
            throw new Exception(
                "SKU already exists."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | UNIQUE SLUG
        |--------------------------------------------------------------------------
        */

        $slug =
        createUniqueSlugForUpdate(
            $conn,
            $name,
            $productId
        );

        /*
        |--------------------------------------------------------------------------
        | IMAGE UPDATE
        |--------------------------------------------------------------------------
        */

        $image =
        $product['image'];

        if(
            isset($_FILES['image']) &&
            $_FILES['image']['error']
            !== UPLOAD_ERR_NO_FILE
        )
        {
            $allowed =
            [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ];

            $ext =
            strtolower(
                pathinfo(
                    $_FILES['image']['name'],
                    PATHINFO_EXTENSION
                )
            );

            if(
                !in_array(
                    $ext,
                    $allowed
                )
            )
            {
                throw new Exception(
                    "Invalid image format."
                );
            }

            if(
                $_FILES['image']['size']
                >
                5 * 1024 * 1024
            )
            {
                throw new Exception(
                    "Image exceeds 5MB."
                );
            }

            if(
                !getimagesize(
                    $_FILES['image']['tmp_name']
                )
            )
            {
                throw new Exception(
                    "Invalid image."
                );
            }

            $uploadDir =
            "../uploads/products/";

            if(
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
            "." .
            $ext;

            $target =
            $uploadDir .
            $fileName;

            if(
                move_uploaded_file(
                    $_FILES['image']['tmp_name'],
                    $target
                )
            )
            {
                if(
                    !empty($image) &&
                    file_exists($image)
                )
                {
                    @unlink($image);
                }

                $image = $target;
            }
            else
            {
                throw new Exception(
                    "Failed to upload image."
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | TRANSACTION
        |--------------------------------------------------------------------------
        */

        $conn->begin_transaction();

        $stmt = $conn->prepare("
            UPDATE products

            SET

                category_id = ?,
                name = ?,
                slug = ?,
                sku = ?,

                short_description = ?,
                description = ?,

                price = ?,
                sale_price = ?,

                stock = ?,
                min_stock_level = ?,

                image = ?,
                featured_image = ?,

                weight = ?,

                status = ?,

                featured = ?,

                is_wholesale = ?,
                minimum_order_qty = ?,
                wholesale_price = ?,

                updated_at = NOW()

            WHERE id = ?
            AND shop_id = ?
        ");

        $stmt->bind_param(
            "isssssddiissdsiiidii",

            $categoryId,
            $name,
            $slug,
            $sku,

            $shortDescription,
            $description,

            $price,
            $salePrice,

            $stock,
            $minStock,

            $image,
            $image,

            $weight,

            $status,

            $featured,

            $isWholesale,
            $minimumOrderQty,
            $wholesalePrice,

            $productId,
            $shopId
        );

        if(!$stmt->execute())
        {
            throw new Exception(
                $stmt->error
            );
        }

        $conn->commit();

        $success =
        "Product updated successfully.";

        /*
        |--------------------------------------------------------------------------
        | RELOAD PRODUCT
        |--------------------------------------------------------------------------
        */

        $reload =
        $conn->prepare("
            SELECT *
            FROM products
            WHERE id = ?
            LIMIT 1
        ");

        $reload->bind_param(
            "i",
            $productId
        );

        $reload->execute();

        $product =
        $reload
        ->get_result()
        ->fetch_assoc();
    }
    catch(Exception $e)
    {
        $conn->rollback();

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

<title>
Edit Product
</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f7fb;
}

.page-card{
    border:none;
    border-radius:15px;
    box-shadow:0 3px 12px rgba(0,0,0,.08);
}

.product-image{
    width:200px;
    height:200px;
    object-fit:cover;
    border-radius:12px;
    border:1px solid #ddd;
}

</style>

</head>

<body>

<div class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>
<i class="fas fa-edit"></i>
Edit Product
</h2>

<p class="text-muted mb-0">

<?= htmlspecialchars($product['name']) ?>

</p>

</div>

<a
href="products.php"
class="btn btn-outline-secondary">

<i class="fas fa-arrow-left"></i>
Back

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

<div class="card page-card">

<div class="card-body">

<form
method="POST"
enctype="multipart/form-data">

<input
type="hidden"
name="csrf_token"
value="<?= htmlspecialchars($csrfToken) ?>">

<div class="row">

<div class="col-md-8">

<div class="mb-3">

<label class="form-label">

Product Name

</label>

<input
type="text"
name="name"
class="form-control"
required
value="<?= htmlspecialchars($product['name']) ?>">

</div>

</div>

<div class="col-md-4">

<div class="mb-3">

<label class="form-label">

SKU

</label>

<input
type="text"
name="sku"
class="form-control"
required
value="<?= htmlspecialchars($product['sku']) ?>">

</div>

</div>

<div class="col-md-6">

<div class="mb-3">

<label class="form-label">

Category

</label>

<select
name="category_id"
class="form-select"
required>

<option value="">
Select Category
</option>

<?php while($category = $categories->fetch_assoc()): ?>

<option
value="<?= (int)$category['id'] ?>"
<?= $product['category_id'] == $category['id'] ? 'selected' : '' ?>>

<?= htmlspecialchars($category['category_name']) ?>

</option>

<?php endwhile; ?>

</select>

</div>

</div>

<div class="col-md-6">

<div class="mb-3">

<label class="form-label">

Status

</label>

<select
name="status"
class="form-select">

<option
value="active"
<?= $product['status'] === 'active' ? 'selected' : '' ?>>

Active

</option>

<option
value="inactive"
<?= $product['status'] === 'inactive' ? 'selected' : '' ?>>

Inactive

</option>

<option
value="out_of_stock"
<?= $product['status'] === 'out_of_stock' ? 'selected' : '' ?>>

Out Of Stock

</option>

</select>

</div>

</div>

<div class="col-md-4">

<div class="mb-3">

<label class="form-label">

Price

</label>

<input
type="number"
step="0.01"
name="price"
required
class="form-control"
value="<?= htmlspecialchars((string)$product['price']) ?>">

</div>

</div>

<div class="col-md-4">

<div class="mb-3">

<label class="form-label">

Sale Price

</label>

<input
type="number"
step="0.01"
name="sale_price"
class="form-control"
value="<?= htmlspecialchars((string)$product['sale_price']) ?>">

</div>

</div>

<div class="col-md-4">

<div class="mb-3">

<label class="form-label">

Weight (KG)

</label>

<input
type="number"
step="0.01"
name="weight"
class="form-control"
value="<?= htmlspecialchars((string)$product['weight']) ?>">

</div>

</div>

<div class="col-md-4">

<div class="mb-3">

<label class="form-label">

Stock

</label>

<input
type="number"
name="stock"
class="form-control"
value="<?= (int)$product['stock'] ?>">

</div>

</div>

<div class="col-md-4">

<div class="mb-3">

<label class="form-label">

Minimum Stock

</label>

<input
type="number"
name="min_stock_level"
class="form-control"
value="<?= (int)$product['min_stock_level'] ?>">

</div>

</div>

<div class="col-md-4">

<div class="mb-3">

<label class="form-label">

Minimum Order Qty

</label>

<input
type="number"
name="minimum_order_qty"
class="form-control"
value="<?= (int)$product['minimum_order_qty'] ?>">

</div>

</div>

<div class="col-md-6">

<div class="mb-3">

<label class="form-label">

Wholesale Price

</label>

<input
type="number"
step="0.01"
name="wholesale_price"
class="form-control"
value="<?= htmlspecialchars((string)$product['wholesale_price']) ?>">

</div>

</div>

<div class="col-md-6">

<div class="mb-3">

<label class="form-label">

Product Image

</label>

<input
type="file"
name="image"
class="form-control">

</div>

</div>

<div class="col-12 mb-3">

<?php if(!empty($product['image'])): ?>

<img
src="<?= htmlspecialchars($product['image']) ?>"
class="product-image">

<?php endif; ?>

</div>

<div class="col-12">

<div class="mb-3">

<label class="form-label">

Short Description

</label>

<textarea
name="short_description"
rows="3"
class="form-control"><?= htmlspecialchars($product['short_description']) ?></textarea>

</div>

</div>

<div class="col-12">

<div class="mb-3">

<label class="form-label">

Description

</label>

<textarea
name="description"
rows="8"
class="form-control"><?= htmlspecialchars($product['description']) ?></textarea>

</div>

</div>

<div class="col-md-6">

<div class="form-check mb-3">

<input
class="form-check-input"
type="checkbox"
name="featured"
id="featured"
<?= (int)$product['featured'] === 1 ? 'checked' : '' ?>>

<label
class="form-check-label"
for="featured">

Featured Product

</label>

</div>

</div>

<div class="col-md-6">

<div class="form-check mb-3">

<input
class="form-check-input"
type="checkbox"
name="is_wholesale"
id="is_wholesale"
<?= (int)$product['is_wholesale'] === 1 ? 'checked' : '' ?>>

<label
class="form-check-label"
for="is_wholesale">

Wholesale Product

</label>

</div>

</div>

<div class="col-12">

<button
type="submit"
name="update_product"
class="btn btn-primary btn-lg">

<i class="fas fa-save"></i>
Update Product

</button>

<a
href="products.php"
class="btn btn-secondary">

Cancel

</a>

</div>

</div>

</form>

</div>

</div>

</div>

</body>
</html>