<?php

require_once "../config/db.php";

$product_id = (int)($_GET['id'] ?? 0);

if ($product_id <= 0) {
    die("Invalid product.");
}

/*
|--------------------------------------------------------------------------
| PRODUCT
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT

p.*,

s.id shop_id,
s.shop_name,
s.shop_slug,
s.logo,
s.verified,
s.followers,

c.category_name

FROM products p

LEFT JOIN shops s
ON s.id = p.shop_id

LEFT JOIN categories c
ON c.id = p.category_id

WHERE p.id=?
AND p.is_wholesale=1
LIMIT 1
");

$stmt->bind_param("i", $product_id);
$stmt->execute();

$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    die("Wholesale product not found.");
}

/*
|--------------------------------------------------------------------------
| PRICE TIERS
|--------------------------------------------------------------------------
*/
$tierStmt = $conn->prepare("
SELECT *
FROM wholesale_pricing
WHERE product_id=?
ORDER BY min_qty ASC
");

$tierStmt->bind_param("i", $product_id);
$tierStmt->execute();

$tiers = $tierStmt->get_result();

/*
|--------------------------------------------------------------------------
| SPECIFICATIONS
|--------------------------------------------------------------------------
*/
$specStmt = $conn->prepare("
SELECT *
FROM product_specifications
WHERE product_id=?
ORDER BY id ASC
");

$specStmt->bind_param("i", $product_id);
$specStmt->execute();

$specifications = $specStmt->get_result();

/*
|--------------------------------------------------------------------------
| SIMILAR PRODUCTS
|--------------------------------------------------------------------------
*/
$similar = $conn->prepare("
SELECT

id,
product_name,
image,
wholesale_price

FROM products

WHERE category_id=?
AND id != ?
AND is_wholesale=1

LIMIT 8
");

$similar->bind_param(
    "ii",
    $product['category_id'],
    $product_id
);

$similar->execute();

$relatedProducts =
    $similar->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>
<?= htmlspecialchars($product['product_name']) ?>
</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f6fa;
}

.product-image{
    width:100%;
    height:450px;
    object-fit:cover;
    border-radius:12px;
}

.related-image{
    width:100%;
    height:180px;
    object-fit:cover;
}

.card-box{
    background:#fff;
    border-radius:12px;
}

</style>

</head>

<body>

<div class="container py-4">

<div class="row">

<div class="col-lg-5">

<img
src="../uploads/products/<?= htmlspecialchars($product['image']) ?>"
class="product-image">

</div>

<div class="col-lg-7">

<h2>

<?= htmlspecialchars($product['product_name']) ?>

</h2>

<p class="text-muted">

Category:
<?= htmlspecialchars($product['category_name']) ?>

</p>

<hr>

<h3 class="text-success">

TZS <?= number_format($product['wholesale_price'],2) ?>

</h3>

<p>

Minimum Order Quantity:

<strong>

<?= number_format($product['minimum_order_qty']) ?>

units

</strong>

</p>

<p>

Available Stock:

<strong>

<?= number_format($product['stock_quantity']) ?>

units

</strong>

</p>

<p>

<?= nl2br(
htmlspecialchars(
$product['description']
)
) ?>

</p>

<div class="d-flex gap-2">

<a
href="request-quote.php?product_id=<?= $product['id'] ?>"
class="btn btn-success">

Request Quotation

</a>

<a
href="../shops/shop-profile.php?id=<?= $product['shop_id'] ?>"
class="btn btn-primary">

Supplier Profile

</a>

</div>

</div>

</div>

<hr class="my-5">

<div class="row">

<div class="col-lg-8">

<div class="card-box shadow-sm p-4 mb-4">

<h4>
Bulk Pricing
</h4>

<table class="table">

<thead>

<tr>

<th>Quantity</th>
<th>Price Per Unit</th>

</tr>

</thead>

<tbody>

<?php while($tier = $tiers->fetch_assoc()): ?>

<tr>

<td>

<?= number_format($tier['min_qty']) ?>

-

<?= number_format($tier['max_qty']) ?>

</td>

<td>

TZS <?= number_format($tier['price'],2) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

<div class="card-box shadow-sm p-4">

<h4>
Specifications
</h4>

<table class="table">

<tbody>

<?php while($spec = $specifications->fetch_assoc()): ?>

<tr>

<th width="30%">

<?= htmlspecialchars($spec['spec_name']) ?>

</th>

<td>

<?= htmlspecialchars($spec['spec_value']) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<div class="col-lg-4">

<div class="card-box shadow-sm p-4">

<h4>
Supplier Information
</h4>

<p>

<strong>

<?= htmlspecialchars($product['shop_name']) ?>

</strong>

<?php if($product['verified']): ?>
✅
<?php endif; ?>

</p>

<p>

Followers:

<?= number_format($product['followers']) ?>

</p>

<a
href="../shops/shop-profile.php?id=<?= $product['shop_id'] ?>"
class="btn btn-outline-primary w-100">

Visit Supplier Shop

</a>

</div>

</div>

</div>

<hr class="my-5">

<h4 class="mb-4">
Similar Wholesale Products
</h4>

<div class="row g-4">

<?php while($item = $relatedProducts->fetch_assoc()): ?>

<div class="col-md-3">

<div class="card shadow-sm">

<img
src="../uploads/products/<?= htmlspecialchars($item['image']) ?>"
class="related-image">

<div class="card-body">

<h6>

<?= htmlspecialchars($item['product_name']) ?>

</h6>

<p>

TZS <?= number_format($item['wholesale_price'],2) ?>

</p>

<a
href="product.php?id=<?= $item['id'] ?>"
class="btn btn-primary btn-sm">

View

</a>

</div>

</div>

</div>

<?php endwhile; ?>

</div>

</div>

</body>
</html>