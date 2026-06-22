<?php

require_once "config/db.php";
require_once "includes/auth.php";

requireLogin();

$user_id = (int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Remove Product
|--------------------------------------------------------------------------
*/
if(isset($_GET['remove']))
{
    $product_id = (int)$_GET['remove'];

    $stmt = $conn->prepare("
        DELETE FROM compare_products
        WHERE user_id=?
        AND product_id=?
    ");

    $stmt->bind_param(
        "ii",
        $user_id,
        $product_id
    );

    $stmt->execute();

    header("Location: compare.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Clear Comparison
|--------------------------------------------------------------------------
*/
if(isset($_GET['clear']))
{
    $stmt = $conn->prepare("
        DELETE FROM compare_products
        WHERE user_id=?
    ");

    $stmt->bind_param(
        "i",
        $user_id
    );

    $stmt->execute();

    header("Location: compare.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT
    p.*,
    s.shop_name,
    c.category_name
FROM compare_products cp

INNER JOIN products p
ON cp.product_id = p.id

LEFT JOIN shops s
ON p.shop_id = s.id

LEFT JOIN categories c
ON p.category_id = c.id

WHERE cp.user_id=?

LIMIT 4
");

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$products =
$stmt->get_result();

$productList = [];

while($row = $products->fetch_assoc())
{
    $productList[] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
Compare Products
</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
rel="stylesheet">

<style>

body{
background:#f5f5f5;
}

.product-image{
width:180px;
height:180px;
object-fit:cover;
border-radius:10px;
}

.compare-table{
background:white;
}

</style>

</head>

<body>

<div class="container py-5">

<div
class="d-flex justify-content-between align-items-center mb-4">

<h2>

<i class="fa fa-scale-balanced"></i>

Compare Products

</h2>

<a
href="?clear=1"
class="btn btn-danger">

Clear All

</a>

</div>

<?php if(count($productList) > 1): ?>

<div class="table-responsive">

<table
class="table table-bordered compare-table">

<tr>

<th width="200">

Feature

</th>

<?php foreach($productList as $product): ?>

<th class="text-center">

<img
src="uploads/products/<?=
htmlspecialchars(
$product['image']
)
?>"
class="product-image">

<h5 class="mt-2">

<?= htmlspecialchars(
$product['name']
) ?>

</h5>

<a
href="?remove=<?=
$product['id']
?>"
class="btn btn-sm btn-danger">

Remove

</a>

</th>

<?php endforeach; ?>

</tr>

<tr>

<th>Price</th>

<?php foreach($productList as $product): ?>

<td>

TZS
<?= number_format(
$product['price']
) ?>

</td>

<?php endforeach; ?>

</tr>

<tr>

<th>Category</th>

<?php foreach($productList as $product): ?>

<td>

<?= htmlspecialchars(
$product['category_name']
) ?>

</td>

<?php endforeach; ?>

</tr>

<tr>

<th>Shop</th>

<?php foreach($productList as $product): ?>

<td>

<?= htmlspecialchars(
$product['shop_name']
) ?>

</td>

<?php endforeach; ?>

</tr>

<tr>

<th>Stock</th>

<?php foreach($productList as $product): ?>

<td>

<?= number_format(
$product['stock']
) ?>

</td>

<?php endforeach; ?>

</tr>

<tr>

<th>Views</th>

<?php foreach($productList as $product): ?>

<td>

<?= number_format(
$product['views']
) ?>

</td>

<?php endforeach; ?>

</tr>

<tr>

<th>Description</th>

<?php foreach($productList as $product): ?>

<td>

<?= nl2br(
htmlspecialchars(
substr(
$product['description'],
0,
250
)
)
) ?>

</td>

<?php endforeach; ?>

</tr>

<tr>

<th>Action</th>

<?php foreach($productList as $product): ?>

<td>

<a
href="product.php?id=<?=
$product['id']
?>"
class="btn btn-primary w-100">

View Product

</a>

</td>

<?php endforeach; ?>

</tr>

</table>

</div>

<?php elseif(count($productList) == 1): ?>

<div class="alert alert-warning">

Add at least 2 products to compare.

</div>

<?php else: ?>

<div class="alert alert-info">

No products added for comparison.

</div>

<a
href="products.php"
class="btn btn-primary">

Browse Products

</a>

<?php endif; ?>

</div>

</body>
</html>