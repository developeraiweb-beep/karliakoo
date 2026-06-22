<?php

require_once "../config/db.php";

/*
|--------------------------------------------------------------------------
| Get Shop (by slug or id fallback)
|--------------------------------------------------------------------------
*/
$slug = $_GET['shops'] ?? '';

if (empty($slug)) {
    die("Shop not found.");
}

$stmt = $conn->prepare("
    SELECT *
    FROM shops
    WHERE shop_slug = ?
    LIMIT 1
");

$stmt->bind_param("s", $slug);
$stmt->execute();

$shop = $stmt->get_result()->fetch_assoc();

if (!$shop) {
    die("Shop does not exist.");
}

/*
|--------------------------------------------------------------------------
| Pagination + Search
|--------------------------------------------------------------------------
*/
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

$q = trim($_GET['q'] ?? '');

$where = " WHERE shop_id = ? ";
$params = [$shop['id']];
$types = "i";

if (!empty($q)) {
    $where .= " AND name LIKE ? ";
    $params[] = "%$q%";
    $types .= "s";
}

/*
|--------------------------------------------------------------------------
| Count Products
|--------------------------------------------------------------------------
*/
$countStmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM products
    $where
");

$countStmt->bind_param($types, ...$params);
$countStmt->execute();

$total = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($total / $limit);

/*
|--------------------------------------------------------------------------
| Fetch Products
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT *
    FROM products
    $where
    ORDER BY id DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

$products = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| Helper: status badge
|--------------------------------------------------------------------------
*/
function statusBadge($status)
{
    return match($status) {
        'approved' => 'success',
        'pending' => 'warning',
        'rejected' => 'danger',
        default => 'secondary'
    };
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= htmlspecialchars($shop['shop_name']) ?> | Shop</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f5f5f5;
}

.shop-header{
    background:white;
    padding:20px;
    border-radius:12px;
}

.product-card{
    background:white;
    border-radius:12px;
    overflow:hidden;
    transition:.2s;
}

.product-card:hover{
    transform: translateY(-3px);
}

.product-img{
    height:180px;
    object-fit:cover;
    width:100%;
}

</style>

</head>

<body>

<div class="container py-4">

<!-- SHOP HEADER -->
<div class="shop-header mb-4">

<div class="d-flex align-items-center gap-3">

<?php if(!empty($shop['logo'])): ?>
    <img src="../uploads/shops/<?= htmlspecialchars($shop['logo']) ?>"
         style="width:70px;height:70px;border-radius:10px;object-fit:cover;">
<?php endif; ?>

<div>
    <h3 class="mb-0">
        <?= htmlspecialchars($shop['shop_name']) ?>
    </h3>

    <small class="text-muted">
        <?= htmlspecialchars($shop['city'] ?? '') ?>,
        <?= htmlspecialchars($shop['region'] ?? '') ?>
    </small>

    <div>
        <span class="badge bg-<?= statusBadge($shop['status']) ?>">
            <?= ucfirst($shop['status']) ?>
        </span>

        <?php if($shop['verified']): ?>
            <span class="badge bg-primary">Verified</span>
        <?php endif; ?>
    </div>

</div>

</div>

<p class="mt-3">
    <?= htmlspecialchars($shop['description'] ?? '') ?>
</p>

</div>

<!-- SEARCH -->
<form method="GET" class="mb-3">

<input type="hidden" name="shop" value="<?= htmlspecialchars($slug) ?>">

<div class="input-group">
    <input type="text"
           name="q"
           value="<?= htmlspecialchars($q) ?>"
           class="form-control"
           placeholder="Search products in this shop...">

    <button class="btn btn-primary">Search</button>
</div>

</form>

<!-- PRODUCTS -->
<div class="row">

<?php while($p = $products->fetch_assoc()): ?>

<div class="col-md-3 mb-4">

<div class="product-card shadow-sm">

<img src="../uploads/products/<?= htmlspecialchars($p['image']) ?>"
     class="product-img">

<div class="p-3">

<h6 class="mb-1">
    <?= htmlspecialchars($p['name']) ?>
</h6>

<p class="text-muted mb-1">
    TZS <?= number_format($p['price']) ?>
</p>

<a href="../product.php?id=<?= $p['id'] ?>"
   class="btn btn-sm btn-outline-primary w-100">
   View Product
</a>

</div>

</div>

</div>

<?php endwhile; ?>

</div>

<!-- PAGINATION -->
<?php if($totalPages > 1): ?>

<nav>
<ul class="pagination">

<?php for($i = 1; $i <= $totalPages; $i++): ?>

<li class="page-item <?= $i == $page ? 'active' : '' ?>">

<a class="page-link"
   href="?shop=<?= urlencode($slug) ?>&q=<?= urlencode($q) ?>&page=<?= $i ?>">
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