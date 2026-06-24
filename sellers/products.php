<?php

declare(strict_types=1);

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

if (!$user)
{
    header("Location: ../login.php");
    exit;
}

$sellerId = (int)$user['id'];

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error'] ?? '';

unset($_SESSION['success']);
unset($_SESSION['error']);

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

if (!$shop)
{
    die("Create a shop first.");
}

if (
    $shop['status'] !== 'approved'
)
{
    die("Shop awaiting approval.");
}

if (
    (int)$shop['suspended'] === 1
)
{
    die("Shop suspended.");
}

$shopId = (int)$shop['id'];

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search =
trim(
    $_GET['search'] ?? ''
);

$status =
trim(
    $_GET['status'] ?? ''
);

$page =
max(
    1,
    (int)($_GET['page'] ?? 1)
);

$perPage = 20;

$offset =
($page - 1) * $perPage;

/*
|--------------------------------------------------------------------------
| DASHBOARD STATS
|--------------------------------------------------------------------------
*/

$statsStmt = $conn->prepare("
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
            WHEN stock <= min_stock_level
            THEN 1
            ELSE 0
            END
        ) low_stock_products,

        SUM(sold_count) total_sales

    FROM products

    WHERE shop_id = ?
AND deleted_at IS NULL
");

$statsStmt->bind_param(
    "i",
    $shopId
);

$statsStmt->execute();

$stats =
$statsStmt
->get_result()
->fetch_assoc();

/*
|--------------------------------------------------------------------------
| COUNT QUERY
|--------------------------------------------------------------------------
*/

$countSql = "
SELECT COUNT(*) total
FROM products
WHERE shop_id = ?
AND deleted_at IS NULL
";

$params = [$shopId];
$types  = "i";

if (!empty($search))
{
    $countSql .= "
    AND (
        name LIKE ?
        OR sku LIKE ?
    )
    ";

    $searchLike =
    "%{$search}%";

    $params[] = $searchLike;
    $params[] = $searchLike;

    $types .= "ss";
}

if (!empty($status))
{
    $countSql .= "
    AND status = ?
    ";

    $params[] = $status;
    $types .= "s";
}

$countStmt =
$conn->prepare($countSql);

$countStmt->bind_param(
    $types,
    ...$params
);

$countStmt->execute();

$totalRows =
$countStmt
->get_result()
->fetch_assoc()['total'];

$totalPages =
max(
    1,
    ceil(
        $totalRows /
        $perPage
    )
);

/*
|--------------------------------------------------------------------------
| PRODUCTS QUERY
|--------------------------------------------------------------------------
*/

$sql = "
SELECT

    p.*,

    c.category_name

FROM products p

LEFT JOIN categories c
ON c.id = p.category_id

WHERE shop_id = ?
AND deleted_at IS NULL
";

$params = [$shopId];
$types  = "i";

if (!empty($search))
{
    $sql .= "
    AND (
        p.name LIKE ?
        OR p.sku LIKE ?
    )
    ";

    $searchLike =
    "%{$search}%";

    $params[] = $searchLike;
    $params[] = $searchLike;

    $types .= "ss";
}

if (!empty($status))
{
    $sql .= "
    AND p.status = ?
    ";

    $params[] = $status;
    $types .= "s";
}

$sql .= "
ORDER BY p.id DESC
LIMIT ?, ?
";

$params[] = $offset;
$params[] = $perPage;

$types .= "ii";

$stmt =
$conn->prepare($sql);

$stmt->bind_param(
    $types,
    ...$params
);

$stmt->execute();

$products =
$stmt
->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>My Products</title>

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

.stat-card{
    border:none;
    border-radius:15px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
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

<div class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>
My Products
</h2>

<p class="text-muted">
<?= htmlspecialchars($shop['shop_name']) ?>
</p>

</div>

<a
href="add-product.php"
class="btn btn-primary">

<i class="fas fa-plus"></i>
Add Product

</a>

</div>

<?php if($success): ?>

<div class="alert alert-success">
<?= htmlspecialchars($success) ?>
</div>

<?php endif; ?>

<?php if($error): ?>

<div class="alert alert-danger">
<?= htmlspecialchars($error) ?>
</div>

<?php endif; ?>

<div class="row mb-4">

<div class="col-md-3">

<div class="card stat-card">

<div class="card-body">

<h6>Total Products</h6>

<h3>
<?= number_format(
$stats['total_products'] ?? 0
) ?>
</h3>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card stat-card">

<div class="card-body">

<h6>Active Products</h6>

<h3>
<?= isset($product)
    ? (int)$product['stock']
    : 0 ?>
</h3>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card stat-card">

<div class="card-body">

<h6>Low Stock</h6>

<h3>
<?= number_format((int)$stats['low_stock_products']) ?>
</h3>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card stat-card">

<div class="card-body">

<h6>Total Sold</h6>

<h3>
<?= number_format((int)$stats['total_sales']) ?>
</h3>

</div>

</div>

</div>

</div>

<!-- FILTERS -->

<div class="card border-0 shadow-sm mb-4">

<div class="card-body">

<form method="GET">

<div class="row g-3">

<div class="col-md-5">

<input
type="text"
name="search"
class="form-control"
placeholder="Search product name or SKU..."
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
<?= $status === 'active' ? 'selected' : '' ?>>
Active
</option>

<option
value="inactive"
<?= $status === 'inactive' ? 'selected' : '' ?>>
Inactive
</option>

<option
value="out_of_stock"
<?= $status === 'out_of_stock' ? 'selected' : '' ?>>
Out of Stock
</option>

</select>

</div>

<div class="col-md-4">

<button
type="submit"
class="btn btn-primary">

<i class="fas fa-search"></i>
Filter

</button>

<a
href="products.php"
class="btn btn-secondary">

Reset

</a>

</div>

</div>

</form>

</div>

</div>

<!-- PRODUCTS TABLE -->

<div class="card border-0 shadow-sm">

<div class="card-header bg-white">

<h5 class="mb-0">

Product Inventory

</h5>

</div>

<div class="card-body p-0">

<div class="table-responsive">

<table class="table table-hover align-middle mb-0">

<thead class="table-light">

<tr>

<th>#</th>

<th>Image</th>

<th>Product</th>

<th>Category</th>

<th>Price</th>

<th>Stock</th>

<th>Status</th>

<th>Approval</th>

<th>Views</th>

<th>Sold</th>

<th>Actions</th>

</tr>

</thead>

<tbody>

<?php if($products->num_rows > 0): ?>

<?php while($product = $products->fetch_assoc()): ?>

<tr>

<td>

<?= (int)$product['id'] ?>

</td>

<td>

<?php if(!empty($product['image'])): ?>

<img
src="<?= htmlspecialchars($product['image']) ?>"
alt="<?= htmlspecialchars($product['name']) ?>"
class="img-fluid">

<?php else: ?>

<div
class="bg-light d-flex align-items-center justify-content-center"
style="
width:60px;
height:60px;
border-radius:8px;">

<i class="fas fa-image"></i>

</div>

<?php endif; ?>

</td>

<td>

<div>

<strong>

<?= htmlspecialchars(
$product['name']
) ?>

</strong>

</div>

<small class="text-muted">

SKU:
<?= htmlspecialchars(
$product['sku']
) ?>

</small>

</td>

<td>

<?= htmlspecialchars(
$product['category_name']
?? 'N/A'
) ?>

</td>

<td>

<?php if(
!empty($product['sale_price'])
): ?>

<div>

<strong>

TZS <?= number_format((float)$product['price'], 2) ?>



</strong>

</div>

<small
class="text-decoration-line-through
text-muted">

TZS
<?= number_format(
(float)$product['sale_price'],
2
) ?>

</small>

<?php else: ?>

TZS
<?= number_format(
(float)$product['price'],
2
) ?>

<?php endif; ?>

</td>

<td>

<?php

$lowStock =
$product['stock']
<=
$product['min_stock_level'];

?>

<span
class="
badge
<?= $lowStock
? 'bg-warning'
: 'bg-success'
?>
">

<?= number_format(
$product['stock']
) ?>

</span>

</td>

<td>

<?php

$statusClass = [
'active'=>'success',
'inactive'=>'secondary',
'out_of_stock'=>'danger'
];

?>

<span
class="badge bg-<?= $statusClass[$product['status']] ?? 'secondary' ?>">

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
class="badge bg-warning text-dark">

Pending

</span>

<?php endif; ?>

</td>

<td>

<?= number_format(
$product['views']
) ?>

</td>

<td>

<?= number_format(
$product['sold_count']
) ?>

</td>

<td>

<div
class="btn-group">

<a
href="product-details.php?id=<?= (int)$product['id'] ?>"
class="btn btn-sm btn-outline-primary">

<i class="fas fa-eye"></i>

</a>

<a
href="edit-product.php?id=<?= (int)$product['id'] ?>"
class="btn btn-sm btn-outline-warning">

<i class="fas fa-edit"></i>

</a>

<a
href="delete-product.php?id=<?= (int)$product['id'] ?>&token=<?= urlencode($_SESSION['csrf_token']) ?>"
class="btn btn-sm btn-outline-danger"
onclick="return confirm('Delete this product?')">

<i class="fas fa-trash"></i>

</a>

</div>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td
colspan="11"
class="text-center py-5">

<i
class="fas fa-box-open fa-3x text-muted mb-3">
</i>

<h5>

No Products Found

</h5>

<p class="text-muted">

Start by adding your first product.

</p>

<a
href="add-product.php"
class="btn btn-primary">

Add Product

</a>

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

</div>

<!-- PAGINATION -->

<?php if($totalPages > 1): ?>

<nav class="mt-4">

<ul class="pagination justify-content-center">

<?php for(
$i = 1;
$i <= $totalPages;
$i++
): ?>

<li
class="page-item <?= $i == $page ? 'active' : '' ?>">

<a
class="page-link"
href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">

<?= $i ?>

</a>

</li>

<?php endfor; ?>

</ul>

</nav>

<?php endif; ?>

</div>

</body>

</html>