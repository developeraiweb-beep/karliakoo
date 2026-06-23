<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/
if (
    isset($_GET['action']) &&
    isset($_GET['id'])
) {

    $id = (int)$_GET['id'];

    switch ($_GET['action']) {

        case 'approve':

            $stmt = $conn->prepare("
                UPDATE products
                SET approved=1
                WHERE id=?
            ");

            $stmt->bind_param("i", $id);
            $stmt->execute();

        break;

        case 'reject':

            $stmt = $conn->prepare("
                UPDATE products
                SET approved=0
                WHERE id=?
            ");

            $stmt->bind_param("i", $id);
            $stmt->execute();

        break;

        case 'feature':

            $stmt = $conn->prepare("
                UPDATE products
                SET featured=1
                WHERE id=?
            ");

            $stmt->bind_param("i", $id);
            $stmt->execute();

        break;

        case 'unfeature':

            $stmt = $conn->prepare("
                UPDATE products
                SET featured=0
                WHERE id=?
            ");

            $stmt->bind_param("i", $id);
            $stmt->execute();

        break;

        case 'delete':

            $stmt = $conn->prepare("
                DELETE FROM products
                WHERE id=?
                LIMIT 1
            ");

            $stmt->bind_param("i", $id);
            $stmt->execute();

        break;
    }

    header("Location: products.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/
$where = ["1=1"];
$params = [];
$types = '';

if (!empty($search)) {

    $where[] = "
    (
        p.product_name LIKE ?
        OR s.shop_name LIKE ?
    )
    ";

    $like = "%{$search}%";

    $params[] = $like;
    $params[] = $like;

    $types .= "ss";
}

if ($status !== '') {

    if ($status === 'approved') {
        $where[] = "p.approved=1";
    }

    if ($status === 'pending') {
        $where[] = "p.approved=0";
    }

    if ($status === 'featured') {
        $where[] = "p.featured=1";
    }
}

$whereSql = implode(
    " AND ",
    $where
);

/*
|--------------------------------------------------------------------------
| TOTAL PRODUCTS
|--------------------------------------------------------------------------
*/
$countSql = "
SELECT COUNT(*) total

FROM products p

LEFT JOIN shops s
ON s.seller_id=p.id

WHERE {$whereSql}
";

$countStmt = $conn->prepare($countSql);

if (!empty($params)) {

    $countStmt->bind_param(
        $types,
        ...$params
    );
}

$countStmt->execute();

$totalRows =
$countStmt
->get_result()
->fetch_assoc()['total'];

$totalPages =
max(
    1,
    ceil($totalRows / $limit)
);

/*
|--------------------------------------------------------------------------
| PRODUCTS
|--------------------------------------------------------------------------
*/
$sql = "

SELECT

p.*,

s.shop_name

FROM products p

LEFT JOIN shops s
ON s.seller_id=p.id

WHERE {$whereSql}

ORDER BY p.id DESC

LIMIT ?, ?

";

$stmt = $conn->prepare($sql);

$bindTypes = $types . "ii";

$bindParams = $params;
$bindParams[] = $offset;
$bindParams[] = $limit;

$stmt->bind_param(
    $bindTypes,
    ...$bindParams
);

$stmt->execute();

$products =
$stmt
->get_result();

/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/
$stats = $conn->query("
SELECT

COUNT(*) total_products,

SUM(approved=1)
approved_products,

SUM(approved=0)
pending_products,

SUM(featured=1)
featured_products,

SUM(stock<=5)
low_stock

FROM products
")->fetch_assoc();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>

Products Management

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
background:#f5f6fa;
}

.card-box{
background:#fff;
padding:20px;
border-radius:12px;
}

.product-img{
width:50px;
height:50px;
object-fit:cover;
border-radius:8px;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Products Management

</h2>

<!-- KPI -->

<div class="row g-3 mb-4">

<div class="col-md-2">
<div class="card-box shadow-sm">
<h4><?= number_format($stats['total_products']) ?></h4>
<small>Total</small>
</div>
</div>

<div class="col-md-2">
<div class="card-box shadow-sm">
<h4><?= number_format($stats['approved_products']) ?></h4>
<small>Approved</small>
</div>
</div>

<div class="col-md-2">
<div class="card-box shadow-sm">
<h4><?= number_format($stats['pending_products']) ?></h4>
<small>Pending</small>
</div>
</div>

<div class="col-md-2">
<div class="card-box shadow-sm">
<h4><?= number_format($stats['featured_products']) ?></h4>
<small>Featured</small>
</div>
</div>

<div class="col-md-2">
<div class="card-box shadow-sm">
<h4><?= number_format($stats['low_stock']) ?></h4>
<small>Low Stock</small>
</div>
</div>

</div>

<!-- FILTERS -->

<div class="card-box shadow-sm mb-4">

<form method="GET">

<div class="row">

<div class="col-md-5">

<input
type="text"
name="search"
class="form-control"
placeholder="Search product"
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-3">

<select
name="status"
class="form-select">

<option value="">All Products</option>
<option value="approved">Approved</option>
<option value="pending">Pending</option>
<option value="featured">Featured</option>

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

</div>

<!-- PRODUCTS TABLE -->

<div class="card-box shadow-sm">

<div class="table-responsive">

<table class="table table-hover align-middle">

<thead>

<tr>

<th>ID</th>
<th>Product</th>
<th>Shop</th>
<th>Price</th>
<th>Stock</th>
<th>Status</th>
<th>Featured</th>
<th>Actions</th>

</tr>

</thead>

<tbody>

<?php while($product = $products->fetch_assoc()): ?>

<tr>

<td>

#<?= $product['id'] ?>

</td>

<td>

<?php if(!empty($product['image'])): ?>

<img
src="../uploads/products/<?= htmlspecialchars($product['image']) ?>"
class="product-img me-2">

<?php endif; ?>

<?= htmlspecialchars(
$product['name']
) ?>

</td>

<td>

<?= htmlspecialchars(
$product['shop_name']
?? 'Unknown Shop'
) ?>

</td>

<td>

TZS

<?= number_format(
$product['price'],
2
) ?>

</td>

<td>

<?= number_format(
$product['stock']
) ?>

</td>

<td>

<?php if($product['approved']): ?>

<span class="badge bg-success">

Approved

</span>

<?php else: ?>

<span class="badge bg-warning">

Pending

</span>

<?php endif; ?>

</td>

<td>

<?= $product['featured']
? '⭐'
: '-' ?>

</td>

<td>

<div class="btn-group">

<a
href="product-details.php?id=<?= $product['id'] ?>"
class="btn btn-sm btn-primary">

View

</a>

<a
href="?action=approve&id=<?= $product['id'] ?>"
class="btn btn-sm btn-success">

Approve

</a>

<a
href="?action=feature&id=<?= $product['id'] ?>"
class="btn btn-sm btn-warning">

Feature

</a>

<a
href="?action=delete&id=<?= $product['id'] ?>"
class="btn btn-sm btn-danger"
onclick="return confirm('Delete product?')">

Delete

</a>

</div>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<!-- PAGINATION -->

<nav class="mt-4">

<ul class="pagination">

<?php for($i=1;$i<=$totalPages;$i++): ?>

<li
class="page-item <?= $page==$i?'active':'' ?>">

<a
class="page-link"
href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">

<?= $i ?>

</a>

</li>

<?php endfor; ?>

</ul>

</nav>

</div>

</body>
</html>