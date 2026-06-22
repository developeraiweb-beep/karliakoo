<?php

declare(strict_types=1);

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();
requireRole(['admin']);

$admin = currentUser();

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$category = (int)($_GET['category'] ?? 0);

/*
|--------------------------------------------------------------------------
| PRODUCT STATISTICS
|--------------------------------------------------------------------------
*/

$stats = [
    'total_products' => 0,
    'active_products' => 0,
    'inactive_products' => 0,
    'out_of_stock' => 0,
    'pending_approval' => 0
];

$statsQuery = $conn->query("
    SELECT

        COUNT(*) total_products,

        SUM(
            CASE
                WHEN status='active'
                THEN 1
                ELSE 0
            END
        ) active_products,

        SUM(
            CASE
                WHEN status='inactive'
                THEN 1
                ELSE 0
            END
        ) inactive_products,

        SUM(
            CASE
                WHEN status='out_of_stock'
                THEN 1
                ELSE 0
            END
        ) out_of_stock,

        SUM(
            CASE
                WHEN approved=0
                THEN 1
                ELSE 0
            END
        ) pending_approval

    FROM products
");

if($statsQuery)
{
    $stats = $statsQuery->fetch_assoc();
}

/*
|--------------------------------------------------------------------------
| LOAD CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = [];

$catResult = $conn->query("
    SELECT
        id,
        category_name
    FROM categories
    ORDER BY category_name ASC
");

while($row = $catResult->fetch_assoc())
{
    $categories[] = $row;
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];
$types = '';

if(!empty($search))
{
    $where[] = "
        (
            p.name LIKE ?
            OR p.sku LIKE ?
            OR s.shop_name LIKE ?
        )
    ";

    $searchLike = "%{$search}%";

    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;

    $types .= "sss";
}

if(!empty($status))
{
    $where[] = "p.status = ?";

    $params[] = $status;

    $types .= "s";
}

if($category > 0)
{
    $where[] = "p.category_id = ?";

    $params[] = $category;

    $types .= "i";
}

$sqlWhere = '';

if(!empty($where))
{
    $sqlWhere =
    " WHERE " .
    implode(
        ' AND ',
        $where
    );
}

/*
|--------------------------------------------------------------------------
| LOAD PRODUCTS
|--------------------------------------------------------------------------
*/

$sql = "

SELECT

    p.*,

    c.category_name,

    s.shop_name,

    u.full_name seller_name

FROM products p

LEFT JOIN categories c
ON c.id = p.category_id

LEFT JOIN shops s
ON s.id = p.shop_id

LEFT JOIN users u
ON u.id = s.seller_id

{$sqlWhere}

ORDER BY p.id DESC

LIMIT 200

";

$stmt = $conn->prepare($sql);

if(!empty($params))
{
    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$products =
$stmt->get_result();

?>

<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Products Management

</title>

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

.card{
    border:none;
    border-radius:12px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.stat-box{
    text-align:center;
}

.stat-number{
    font-size:30px;
    font-weight:700;
}

.product-image{
    width:60px;
    height:60px;
    object-fit:cover;
    border-radius:8px;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>

<i class="fas fa-box"></i>

Products Management

</h2>

<p class="text-muted">

Marketplace Products Administration

</p>

</div>

<a
href="dashboard.php"
class="btn btn-secondary">

Dashboard

</a>

</div>
<div class="row mb-4">

<div class="col-md-2">

<div class="card">

<div class="card-body stat-box">

<div class="stat-number text-primary">

<?= number_format(
(int)$stats['total_products']
) ?>

</div>

<div>Total Products</div>

</div>

</div>

</div>

<div class="col-md-2">

<div class="card">

<div class="card-body stat-box">

<div class="stat-number text-success">

<?= number_format(
(int)$stats['active_products']
) ?>

</div>

<div>Active</div>

</div>

</div>

</div>

<div class="col-md-2">

<div class="card">

<div class="card-body stat-box">

<div class="stat-number text-danger">

<?= number_format(
(int)$stats['inactive_products']
) ?>

</div>

<div>Inactive</div>

</div>

</div>

</div>

<div class="col-md-2">

<div class="card">

<div class="card-body stat-box">

<div class="stat-number text-warning">

<?= number_format(
(int)$stats['out_of_stock']
) ?>

</div>

<div>Out Of Stock</div>

</div>

</div>

</div>

<div class="col-md-2">

<div class="card">

<div class="card-body stat-box">

<div class="stat-number text-dark">

<?= number_format(
(int)$stats['pending_approval']
) ?>

</div>

<div>Pending Approval</div>

</div>

</div>

</div>

</div>
<div class="card mb-4">

<div class="card-body">

<form method="GET">

<div class="row">

<div class="col-md-4">

<input
type="text"
name="search"
class="form-control"
placeholder="Search product, SKU or shop..."
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-3">

<select
name="status"
class="form-select">

<option value="">

All Statuses

</option>

<option
value="active"
<?= $status==='active'?'selected':'' ?>>

Active

</option>

<option
value="inactive"
<?= $status==='inactive'?'selected':'' ?>>

Inactive

</option>

<option
value="out_of_stock"
<?= $status==='out_of_stock'?'selected':'' ?>>

Out Of Stock

</option>

</select>

</div>

<div class="col-md-3">

<select
name="category"
class="form-select">

<option value="">

All Categories

</option>

<?php foreach($categories as $cat): ?>

<option
value="<?= $cat['id'] ?>"
<?= $category==$cat['id']?'selected':'' ?>>

<?= htmlspecialchars(
$cat['category_name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-2">

<button
class="btn btn-primary w-100">

Filter

</button>

</div>

</div>

</form>

</div>

</div>
<div class="card">

<div class="card-header">

Products List

</div>

<div class="card-body table-responsive">

<table class="table table-bordered table-hover align-middle">

<thead>

<tr>

<th>ID</th>
<th>Image</th>
<th>Product</th>
<th>Category</th>
<th>Shop</th>
<th>Price</th>
<th>Stock</th>
<th>Status</th>
<th>Approval</th>
<th>Actions</th>

</tr>

</thead>

<tbody>

<?php if($products->num_rows > 0): ?>

<?php while($product = $products->fetch_assoc()): ?>

<?php

$statusColor =
match($product['status'])
{
    'active'       => 'success',
    'inactive'     => 'secondary',
    'out_of_stock' => 'danger',
    default        => 'dark'
};

$stockClass = '';

if((int)$product['stock'] <= 0)
{
    $stockClass = 'text-danger fw-bold';
}
elseif((int)$product['stock'] <= (int)$product['min_stock_level'])
{
    $stockClass = 'text-warning fw-bold';
}

?>

<tr>

<td>

#<?= (int)$product['id'] ?>

</td>

<td>

<?php

$imagePath = '';

if(!empty($product['image']))
{
    $imagePath = $product['image'];

    $imagePath = str_replace(
        '\\',
        '/',
        $imagePath
    );

    $imagePath = str_replace(
        '../',
        '',
        $imagePath
    );
}

?>

<?php if(!empty($imagePath)): ?>

<img
src="../<?= htmlspecialchars($imagePath) ?>"
alt="<?= htmlspecialchars($product['name']) ?>"
class="product-image">

<?php else: ?>

<div
class="product-image border d-flex align-items-center justify-content-center bg-light">

<i class="fas fa-image text-muted"></i>

</div>

<?php endif; ?>

</td>

<td>

<strong>

<?= htmlspecialchars(
$product['name']
) ?>

</strong>

<br>

<small class="text-muted">

SKU:
<?= htmlspecialchars(
$product['sku']
?? '-'
) ?>

</small>

<br>

<?php if(
(int)$product['featured'] === 1
): ?>

<span class="badge bg-warning">

Featured

</span>

<?php endif; ?>

<?php if(
(int)$product['is_wholesale'] === 1
): ?>

<span class="badge bg-info">

Wholesale

</span>

<?php endif; ?>

</td>

<td>

<?= htmlspecialchars(
$product['category_name']
?? 'Uncategorized'
) ?>

</td>

<td>

<strong>

<?= htmlspecialchars(
$product['shop_name']
?? '-'
) ?>

</strong>

<br>

<small>

<?= htmlspecialchars(
$product['seller_name']
?? '-'
) ?>

</small>

</td>

<td>

<?php if(
!empty($product['sale_price']) &&
(float)$product['sale_price'] > 0
): ?>

<span class="text-decoration-line-through text-muted">

TZS
<?= number_format(
(float)$product['price'],
2
) ?>

</span>

<br>

<span class="text-success fw-bold">

TZS
<?= number_format(
(float)$product['sale_price'],
2
) ?>

</span>

<?php else: ?>

TZS
<?= number_format(
(float)$product['price'],
2
) ?>

<?php endif; ?>

</td>

<td class="<?= $stockClass ?>">

<?= number_format(
(int)$product['stock']
) ?>

</td>

<td>

<span
class="badge bg-<?= $statusColor ?>">

<?= ucfirst(
str_replace(
'_',
' ',
$product['status']
)
) ?>

</span>

</td>

<td>

<?php if(
(int)$product['approved'] === 1
): ?>

<span
class="badge bg-success">

Approved

</span>

<?php else: ?>

<span
class="badge bg-warning">

Pending

</span>

<?php endif; ?>

</td>

<td>

<div
class="btn-group btn-group-sm">

<a
href="product-details.php?id=<?= (int)$product['id'] ?>"
class="btn btn-primary">

<i class="fas fa-eye"></i>

</a>

<a
href="edit-product.php?id=<?= (int)$product['id'] ?>"
class="btn btn-success">

<i class="fas fa-edit"></i>

</a>
<?php if((int)$product['featured'] === 0): ?>

<a
href="product-action.php?action=feature&id=<?= (int)$product['id'] ?>"
class="btn btn-info"
onclick="return confirm('Feature this product?')">

<i class="fas fa-star"></i>

</a>

<?php else: ?>

<a
href="product-action.php?action=unfeature&id=<?= (int)$product['id'] ?>"
class="btn btn-dark"
onclick="return confirm('Remove featured status?')">

<i class="fas fa-star-half-alt"></i>

</a>

<?php endif; ?>

<?php if(
(int)$product['approved'] === 0
): ?>

<a
href="product-action.php?action=approve&id=<?= (int)$product['id'] ?>"
class="btn btn-warning"
onclick="return confirm('Approve this product?')">

<i class="fas fa-check"></i>

</a>

<?php endif; ?>

<?php if(
$product['status'] === 'active'
): ?>

<a
href="product-action.php?action=disable&id=<?= (int)$product['id'] ?>"
class="btn btn-secondary"
onclick="return confirm('Disable this product?')">

<i class="fas fa-ban"></i>

</a>

<?php else: ?>

<a
href="product-action.php?action=activate&id=<?= (int)$product['id'] ?>"
class="btn btn-info"
onclick="return confirm('Activate this product?')">

<i class="fas fa-play"></i>

</a>

<?php endif; ?>

<a
href="product-action.php?action=delete&id=<?= (int)$product['id'] ?>"
class="btn btn-danger"
onclick="return confirm('Delete this product permanently?')">

<i class="fas fa-trash"></i>

</a>

</div>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td
colspan="10"
class="text-center text-muted py-4">

No products found.

</td>

</tr>

<?php endif; ?>
</tbody>

</table>

</div>

</div>
<div class="card mt-4">

<div class="card-header">

Inventory Health Overview

</div>

<div class="card-body">

<div class="row">

<div class="col-md-4">

<div class="alert alert-success">

<i class="fas fa-check-circle"></i>

Approved and active products are visible to customers.

</div>

</div>

<div class="col-md-4">

<div class="alert alert-warning">

<i class="fas fa-box-open"></i>

Products near minimum stock should be replenished.

</div>

</div>

<div class="col-md-4">

<div class="alert alert-danger">

<i class="fas fa-exclamation-triangle"></i>

Out-of-stock products may affect marketplace conversion.

</div>

</div>

</div>

</div>

</div>
</div>

</body>

</html>