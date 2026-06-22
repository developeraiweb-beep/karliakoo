<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

$search = trim($_GET['search'] ?? '');
$category = (int)($_GET['category'] ?? 0);

$where = [
    "p.status='active'",
    "p.is_wholesale=1"
];

$params = [];
$types = '';

if (!empty($search)) {

    $where[] = "(p.product_name LIKE ? OR p.description LIKE ?)";

    $like = "%{$search}%";

    $params[] = $like;
    $params[] = $like;

    $types .= "ss";
}

if ($category > 0) {

    $where[] = "p.category_id=?";

    $params[] = $category;

    $types .= "i";
}

$whereSQL = implode(" AND ", $where);

$sql = "

SELECT

p.*,

s.shop_name,

c.category_name

FROM products p

LEFT JOIN shops s
ON s.id = p.shop_id

LEFT JOIN categories c
ON c.id = p.category_id

WHERE $whereSQL

ORDER BY p.id DESC

";

$stmt = $conn->prepare($sql);

if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$products = $stmt->get_result();

$categories = $conn->query("
    SELECT *
    FROM categories
    ORDER BY category_name ASC
");

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>
Wholesale Products
</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f6fa;
}

.product-card{
    background:#fff;
    border-radius:12px;
    overflow:hidden;
    height:100%;
}

.product-image{
    width:100%;
    height:220px;
    object-fit:cover;
}

</style>

</head>

<body>

<div class="container py-4">

<h2 class="mb-4">
B2B Wholesale Marketplace
</h2>

<form method="GET" class="mb-4">

<div class="row">

<div class="col-md-5">

<input
type="text"
name="search"
value="<?= htmlspecialchars($search) ?>"
class="form-control"
placeholder="Search wholesale products">

</div>

<div class="col-md-5">

<select
name="category"
class="form-select">

<option value="">
All Categories
</option>

<?php while($cat = $categories->fetch_assoc()): ?>

<option
value="<?= $cat['id'] ?>"
<?= $category==$cat['id'] ? 'selected' : '' ?>>

<?= htmlspecialchars($cat['category_name']) ?>

</option>

<?php endwhile; ?>

</select>

</div>

<div class="col-md-2">

<button
class="btn btn-primary w-100">

Search

</button>

</div>

</div>

</form>

<div class="row g-4">

<?php while($product = $products->fetch_assoc()): ?>

<div class="col-lg-3 col-md-4">

<div class="product-card shadow-sm">

<img
src="../uploads/products/<?= htmlspecialchars($product['image']) ?>"
class="product-image">

<div class="p-3">

<h6>

<?= htmlspecialchars($product['product_name']) ?>

</h6>

<p class="text-muted small">

<?= htmlspecialchars($product['shop_name']) ?>

</p>

<p>

Wholesale Price:

<strong>

TZS <?= number_format($product['wholesale_price'],2) ?>

</strong>

</p>

<p>

MOQ:

<strong>

<?= number_format($product['minimum_order_qty']) ?>

</strong>

units

</p>

<p>

Stock:

<strong>

<?= number_format($product['stock_quantity']) ?>

</strong>

</p>

<a
href="product.php?id=<?= $product['id'] ?>"
class="btn btn-primary btn-sm">

View Product

</a>

<a
href="request-quote.php?product_id=<?= $product['id'] ?>"
class="btn btn-success btn-sm">

Request Quote

</a>

</div>

</div>

</div>

<?php endwhile; ?>

</div>

</div>

</body>
</html>