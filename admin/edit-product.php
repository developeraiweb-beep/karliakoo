<?php

declare(strict_types=1);

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();
requireRole(['admin']);

$success = '';
$error = '';

$productId = (int)($_GET['id'] ?? 0);

if($productId <= 0)
{
    header("Location: products.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD PRODUCT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT p.*,
           c.category_name,
           s.shop_name
    FROM products p
    LEFT JOIN categories c
        ON c.id = p.category_id
    LEFT JOIN shops s
        ON s.id = p.shop_id
    WHERE p.id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $productId
);

$stmt->execute();

$product =
$stmt
->get_result()
->fetch_assoc();

if(!$product)
{
    $_SESSION['error'] =
    "Product not found.";

    header(
        "Location: products.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = [];

$catQuery =
mysqli_query(
    $conn,
    "SELECT id,
            category_name
     FROM categories
     ORDER BY category_name ASC"
);

while(
    $row =
    mysqli_fetch_assoc(
        $catQuery
    )
)
{
    $categories[] = $row;
}

if(
    $_SERVER['REQUEST_METHOD']
    === 'POST'
)
{
    $name =
    trim($_POST['name']);

    $categoryId =
    (int)$_POST['category_id'];

    $price =
    (float)$_POST['price'];

    $salePrice =
    !empty($_POST['sale_price'])
    ? (float)$_POST['sale_price']
    : null;

    $stock =
    (int)$_POST['stock'];

    $minStock =
    (int)$_POST['min_stock_level'];

    $status =
    $_POST['status'];

    $shortDescription =
    trim(
        $_POST['short_description']
    );

    $description =
    trim(
        $_POST['description']
    );

    $featured =
    isset($_POST['featured'])
    ? 1
    : 0;

    $approved =
    isset($_POST['approved'])
    ? 1
    : 0;

    if(empty($name))
    {
        $error =
        "Product name required.";
    }
    elseif($price <= 0)
    {
        $error =
        "Price must be greater than zero.";
    }
    else
    {
        /*
        |--------------------------------------------------------------------------
        | IMAGE UPLOAD
        |--------------------------------------------------------------------------
        */

        $image =
        $product['image'];

        if(
            isset($_FILES['image']) &&
            $_FILES['image']['error']
            === 0
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
                in_array(
                    $ext,
                    $allowed
                )
            )
            {
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
                    'product_'
                )
                . '.'
                . $ext;

                $target =
                $uploadDir
                . $fileName;

                if(
                    move_uploaded_file(
                        $_FILES['image']['tmp_name'],
                        $target
                    )
                )
                {
                    $image =
                    "uploads/products/"
                    . $fileName;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE PRODUCT
        |--------------------------------------------------------------------------
        */

        $update =
        $conn->prepare("
            UPDATE products
SET

category_id=?,
name=?,

slug=?,
sku=?,

short_description=?,
description=?,

price=?,
sale_price=?,

stock=?,
min_stock_level=?,

image=?,
featured_image=?,

weight=?,

status=?,

featured=?,
approved=?,

is_wholesale=?,
minimum_order_qty=?,
wholesale_price=?,

updated_at=NOW()

WHERE id=?
        ");

        $update->bind_param(

"isssssddiissdsiiiidi",

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
$approved,

$isWholesale,
$minimumOrderQty,

$wholesalePrice,

$productId
);
        

        if(
            $update->execute()
        )
        {
            $success =
            "Product updated successfully.";

            header(
                "Refresh:1"
            );
        }
        else
        {
            $error =
            $update->error;
        }
    }
}
/*
|--------------------------------------------------------------------------
| LOAD PRODUCT GALLERY
|--------------------------------------------------------------------------
*/

$galleryImages = [];

$galleryStmt = $conn->prepare("
    SELECT *
    FROM product_images
    WHERE product_id = ?
    ORDER BY id ASC
");

$galleryStmt->bind_param(
    "i",
    $productId
);

$galleryStmt->execute();

$galleryResult =
$galleryStmt->get_result();

while(
    $row =
    $galleryResult->fetch_assoc()
)
{
    $galleryImages[] = $row;
}
/*
|--------------------------------------------------------------------------
| LOAD SPECIFICATIONS
|--------------------------------------------------------------------------
*/

$specifications = [];

$specStmt = $conn->prepare("
    SELECT *
    FROM product_specifications
    WHERE product_id = ?
    ORDER BY id ASC
");

$specStmt->bind_param(
    "i",
    $productId
);

$specStmt->execute();

$specResult =
$specStmt->get_result();

while(
    $row =
    $specResult->fetch_assoc()
)
{
    $specifications[] = $row;
}
/*
|--------------------------------------------------------------------------
| LOAD VARIANTS
|--------------------------------------------------------------------------
*/

$variants = [];

$variantStmt = $conn->prepare("
    SELECT *
    FROM product_variants
    WHERE product_id = ?
    ORDER BY id ASC
");

$variantStmt->bind_param(
    "i",
    $productId
);

$variantStmt->execute();

$variantResult =
$variantStmt->get_result();

while(
    $row =
    $variantResult->fetch_assoc()
)
{
    $variants[] = $row;
}
$sku =
trim(
    $_POST['sku']
);

$slug =
trim(
    $_POST['slug']
);

$weight =
!empty($_POST['weight'])
? (float)$_POST['weight']
: null;

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
| ADD GALLERY IMAGES
|--------------------------------------------------------------------------
*/

if(isset($_POST['upload_gallery']))
{
    if(
        isset($_FILES['gallery'])
        &&
        !empty($_FILES['gallery']['name'][0])
    )
    {
        $uploadDir =
        "../uploads/products/gallery/";

        if(!is_dir($uploadDir))
        {
            mkdir(
                $uploadDir,
                0755,
                true
            );
        }

        foreach(
            $_FILES['gallery']['tmp_name']
            as $key => $tmpName
        )
        {
            if(
                $_FILES['gallery']['error'][$key]
                !== 0
            )
            {
                continue;
            }

            $ext =
            strtolower(
                pathinfo(
                    $_FILES['gallery']['name'][$key],
                    PATHINFO_EXTENSION
                )
            );

            if(
                !in_array(
                    $ext,
                    ['jpg','jpeg','png','webp']
                )
            )
            {
                continue;
            }

            $fileName =
            uniqid(
                'gallery_',
                true
            )
            . "."
            . $ext;

            $target =
            $uploadDir .
            $fileName;

            if(
                move_uploaded_file(
                    $tmpName,
                    $target
                )
            )
            {
                $path =
                "uploads/products/gallery/" .
                $fileName;

                $insert =
                $conn->prepare("
                    INSERT INTO product_images
                    (
                        product_id,
                        image
                    )
                    VALUES
                    (
                        ?,
                        ?
                    )
                ");

                $insert->bind_param(
                    "is",
                    $productId,
                    $path
                );

                $insert->execute();
            }
        }
    }

    header(
        "Location: edit-product.php?id=" .
        $productId
    );

    exit;
}
/*
|--------------------------------------------------------------------------
| ADD SPECIFICATION
|--------------------------------------------------------------------------
*/

if(isset($_POST['add_specification']))
{
    $specName =
    trim(
        $_POST['spec_name']
    );

    $specValue =
    trim(
        $_POST['spec_value']
    );

    if(
        !empty($specName)
        &&
        !empty($specValue)
    )
    {
        $stmt =
        $conn->prepare("
            INSERT INTO
            product_specifications
            (
                product_id,
                spec_name,
                spec_value
            )
            VALUES
            (
                ?,
                ?,
                ?
            )
        ");

        $stmt->bind_param(
            "iss",
            $productId,
            $specName,
            $specValue
        );

        $stmt->execute();
    }

    header(
        "Location: edit-product.php?id=" .
        $productId
    );

    exit;
}
/*
|--------------------------------------------------------------------------
| ADD VARIANT
|--------------------------------------------------------------------------
*/

if(isset($_POST['add_variant']))
{
    $variantName =
    trim(
        $_POST['variant_name']
    );

    $variantSku =
    trim(
        $_POST['variant_sku']
    );

    $variantPrice =
    (float)$_POST['variant_price'];

    $variantStock =
    (int)$_POST['variant_stock'];

    $stmt =
    $conn->prepare("
        INSERT INTO
        product_variants
        (
            product_id,
            variant_name,
            sku,
            price,
            stock
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");

    $stmt->bind_param(
        "issdi",
        $productId,
        $variantName,
        $variantSku,
        $variantPrice,
        $variantStock
    );

    $stmt->execute();

    header(
        "Location: edit-product.php?id=" .
        $productId
    );

    exit;
}
$audit =
$conn->prepare("
    INSERT INTO audit_logs
    (
        user_id,
        action,
        description
    )
    VALUES
    (
        ?,
        ?,
        ?
    )
");

$action =
"PRODUCT_UPDATED";

$description =
"Admin updated product #" .
$productId;

$audit->bind_param(
    "iss",
    $_SESSION['user_id'],
    $action,
    $description
);

$audit->execute();
$notification =
$conn->prepare("
    INSERT INTO notifications
    (
        user_id,
        title,
        message
    )
    VALUES
    (
        ?,
        ?,
        ?
    )
");

$title =
"Product Updated";

$message =
"Your product (" .
$name .
") was updated by administration.";

$notification->bind_param(
    "iss",
    $product['seller_id'],
    $title,
    $message
);

$notification->execute();
?>
<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<title>

Edit Product

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

</head>

<body>

<div class="container py-4">

<h2>

Edit Product

</h2>

<?php if($success): ?>

<div class="alert alert-success">

<?= $success ?>

</div>

<?php endif; ?>

<?php if($error): ?>

<div class="alert alert-danger">

<?= $error ?>

</div>

<?php endif; ?>

<form
method="POST"
enctype="multipart/form-data">

<div class="row">

<div class="col-md-6 mb-3">

<label>

Product Name

<div class="col-md-6 mb-3">

<label>

SKU

</label>

<input
type="text"
name="sku"
class="form-control"
value="<?= htmlspecialchars($product['sku']) ?>">

</div>
<div class="col-md-6 mb-3">

<label>

SEO Slug

</label>

<input
type="text"
name="slug"
class="form-control"
value="<?= htmlspecialchars($product['slug']) ?>">

</div>
<div class="col-md-4 mb-3">

<label>

Weight (KG)

</label>

<input
type="number"
step="0.01"
name="weight"
class="form-control"
value="<?= htmlspecialchars($product['weight']) ?>">

</div>
<div class="col-md-4 mb-3">

<label>

Minimum Stock Level

</label>

<input
type="number"
name="min_stock_level"
class="form-control"
value="<?= (int)$product['min_stock_level'] ?>">

</div>
<div class="col-md-4 mb-3">

<label>

Status

</label>

<select
name="status"
class="form-select">

<option
value="active"
<?= $product['status']=='active'
? 'selected'
: '' ?>>

Active

</option>

<option
value="inactive"
<?= $product['status']=='inactive'
? 'selected'
: '' ?>>

Inactive

</option>

<option
value="out_of_stock"
<?= $product['status']=='out_of_stock'
? 'selected'
: '' ?>>

Out Of Stock

</option>

</select>

</div>
<hr>

<h4>

Wholesale Settings

</h4>

<div class="col-md-4 mb-3">

<div class="form-check">

<input
type="checkbox"
name="is_wholesale"
class="form-check-input"
<?= $product['is_wholesale']
? 'checked'
: '' ?>>

<label
class="form-check-label">

Wholesale Enabled

</label>

</div>

</div>

<div class="col-md-4 mb-3">

<label>

Minimum Order Qty

</label>

<input
type="number"
name="minimum_order_qty"
class="form-control"
value="<?= (int)$product['minimum_order_qty'] ?>">

</div>

<div class="col-md-4 mb-3">

<label>

Wholesale Price

</label>

<input
type="number"
step="0.01"
name="wholesale_price"
class="form-control"
value="<?= $product['wholesale_price'] ?>">

</div>

</label>

<input
type="text"
name="name"
class="form-control"
required
value="<?= htmlspecialchars($product['name']) ?>">

</div>
<div class="col-md-6 mb-3">

<label>

Category

</label>

<select
name="category_id"
class="form-select">

<?php foreach($categories as $cat): ?>

<option
value="<?= $cat['id'] ?>"
<?= $cat['id']==$product['category_id']
? 'selected'
: '' ?>>

<?= htmlspecialchars(
$cat['category_name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>
<div class="col-md-12 mb-3">

<?php if(!empty($product['image'])): ?>

<img
src="../<?= htmlspecialchars($product['image']) ?>"
style="
max-width:250px;
border-radius:10px;">

<?php endif; ?>

</div>
<div class="col-md-12 mb-3">

<label>

Replace Image

</label>

<input
type="file"
name="image"
class="form-control">

</div>
<div class="col-md-4 mb-3">

<label>

Price

</label>

<input
type="number"
step="0.01"
name="price"
class="form-control"
value="<?= $product['price'] ?>">

</div>

<div class="col-md-4 mb-3">

<label>

Sale Price

</label>

<input
type="number"
step="0.01"
name="sale_price"
class="form-control"
value="<?= $product['sale_price'] ?>">

</div>

<div class="col-md-4 mb-3">

<label>

Stock

</label>

<input
type="number"
name="stock"
class="form-control"
value="<?= $product['stock'] ?>">

</div>
<div class="col-md-12 mb-3">

<label>

Short Description

</label>

<textarea
name="short_description"
class="form-control"><?= htmlspecialchars($product['short_description']) ?></textarea>

</div>

<div class="col-md-12 mb-3">

<label>

Description

</label>

<textarea
name="description"
rows="8"
class="form-control"><?= htmlspecialchars($product['description']) ?></textarea>

</div>

<div class="col-md-6 mb-3">

<div class="form-check">

<input
type="checkbox"
name="featured"
class="form-check-input"
<?= $product['featured']
? 'checked'
: '' ?>>

<label
class="form-check-label">

Featured Product

</label>

</div>

</div>

<div class="col-md-6 mb-3">

<div class="form-check">

<input
type="checkbox"
name="approved"
class="form-check-input"
<?= $product['approved']
? 'checked'
: '' ?>>

<label
class="form-check-label">

Approved Product

</label>

</div>

</div>
<div class="col-md-12">

<button
type="submit"
class="btn btn-primary">

Update Product

</button>

<a
href="product-details.php?id=<?= $productId ?>"
class="btn btn-secondary">

Back

</a>

</div>

</div>

</form>
<hr>

<h4>

Gallery Images

</h4>

<div class="row">

<?php foreach($galleryImages as $image): ?>

<div class="col-md-3 mb-3">

<img
src="../<?= htmlspecialchars($image['image']) ?>"
class="img-fluid rounded border">

<a
href="delete-product-image.php?id=<?= $image['id'] ?>&product_id=<?= $productId ?>"
class="btn btn-danger btn-sm mt-2"
onclick="return confirm('Delete image?')">

Delete

</a>
<hr>

<h5>

Upload Gallery Images

</h5>

<form
method="POST"
enctype="multipart/form-data">

<div class="row">

<div class="col-md-8">

<input
type="file"
name="gallery[]"
multiple
class="form-control">

</div>

<div class="col-md-4">

<button
type="submit"
name="upload_gallery"
class="btn btn-primary">

Upload Images

</button>

</div>

</div>

</form>

</div>

<?php endforeach; ?>

</div>
<hr>

<h4>

Specifications

</h4>

<table class="table table-bordered">

<thead>

<tr>

<th>ID</th>
<th>Attribute</th>
<th>Value</th>

</tr>

</thead>

<tbody>

<?php foreach($specifications as $spec): ?>

<tr>

<td>

<?= (int)$spec['id'] ?>

</td>

<td>

<?= htmlspecialchars(
$spec['spec_name']
?? ''
) ?>

</td>

<td>

<?= htmlspecialchars(
$spec['spec_value']
?? ''
) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>
<hr>
<hr>

<h5>

Add Specification

</h5>

<form method="POST">

<div class="row">

<div class="col-md-5">

<input
type="text"
name="spec_name"
placeholder="Specification Name"
class="form-control">

</div>

<div class="col-md-5">

<input
type="text"
name="spec_value"
placeholder="Specification Value"
class="form-control">

</div>

<div class="col-md-2">

<button
type="submit"
name="add_specification"
class="btn btn-success">

Add

</button>

</div>

</div>

</form>

<th>Action</th>
<td>

<a
href="delete-product-specification.php?id=<?= (int)$spec['id'] ?>&product_id=<?= $productId ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Delete specification?')">

Delete

</a>

</td>
<h4>

Product Variants

</h4>

<table class="table table-striped">

<thead>

<tr>

<th>ID</th>
<th>Name</th>
<th>SKU</th>
<th>Price</th>
<th>Stock</th>

</tr>

</thead>

<tbody>

<?php foreach($variants as $variant): ?>

<tr>

<td>

<?= (int)$variant['id'] ?>

</td>

<td>

<?= htmlspecialchars(
$variant['variant_name']
?? ''
) ?>

</td>

<td>

<?= htmlspecialchars(
$variant['sku']
?? ''
) ?>

</td>

<td>

<?= number_format(
(float)$variant['price'],
2
) ?>

</td>

<td>

<?= number_format(
(int)$variant['stock']
) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>
<hr>

<h5>

Add Variant

</h5>

<form method="POST">

<div class="row">

<div class="col-md-3">

<input
type="text"
name="variant_name"
class="form-control"
placeholder="Size / Color">

</div>

<div class="col-md-3">

<input
type="text"
name="variant_sku"
class="form-control"
placeholder="SKU">

</div>

<div class="col-md-2">

<input
type="number"
step="0.01"
name="variant_price"
class="form-control"
placeholder="Price">

</div>

<div class="col-md-2">

<input
type="number"
name="variant_stock"
class="form-control"
placeholder="Stock">

</div>

<div class="col-md-2">

<button
type="submit"
name="add_variant"
class="btn btn-success">

Add

</button>

</div>
<div class="col-md-12 mb-3">

<label>

Admin Notes

</label>

<textarea
name="admin_notes"
rows="4"
class="form-control"><?= htmlspecialchars(
$product['admin_notes']
?? ''
) ?></textarea>

</div>
</div>

</form>
</table>
</div>

</body>
</html>