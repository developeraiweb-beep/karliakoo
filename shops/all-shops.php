<?php

require_once "../config/db.php";

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

/*
|--------------------------------------------------------------------------
| Search + Filters
|--------------------------------------------------------------------------
*/
$q = trim($_GET['q'] ?? '');

$where = " WHERE status = 'approved' ";
$params = [];
$types = "";

if (!empty($q)) {
    $where .= " AND (shop_name LIKE ? OR city LIKE ? OR region LIKE ?) ";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $types .= "sss";
}

/*
|--------------------------------------------------------------------------
| Count Shops
|--------------------------------------------------------------------------
*/
$countSql = "
    SELECT COUNT(*) as total
    FROM shops
    $where
";

$countStmt = $conn->prepare($countSql);

if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];

$totalPages = ceil($total / $limit);

/*
|--------------------------------------------------------------------------
| Fetch Shops
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT *
    FROM shops
    $where
    ORDER BY verified DESC, followers DESC, views DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$shops = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function badgeColor($status)
{
    return match ($status) {
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

<title>All Shops | Karliakoo</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f5f5f5;
}

.shop-card{
    background:white;
    border-radius:12px;
    transition:.2s;
    overflow:hidden;
}

.shop-card:hover{
    transform: translateY(-4px);
}

.shop-logo{
    width:60px;
    height:60px;
    object-fit:cover;
    border-radius:10px;
}

.banner{
    height:120px;
    object-fit:cover;
    width:100%;
}

.small-text{
    font-size:13px;
    color:#777;
}

</style>

</head>

<body>

<div class="container py-4">

<h2 class="mb-4">Explore Shops</h2>

<!-- SEARCH -->
<form method="GET" class="mb-4">

<div class="input-group">

<input type="text"
       name="q"
       value="<?= htmlspecialchars($q) ?>"
       class="form-control"
       placeholder="Search shops by name, city, or region">

<button class="btn btn-primary">
    Search
</button>

</div>

</form>

<!-- SHOP GRID -->
<div class="row">

<?php while($s = $shops->fetch_assoc()): ?>

<div class="col-md-3 mb-4">

<div class="shop-card shadow-sm">

<!-- BANNER -->
<?php if(!empty($s['banner'])): ?>
    <img src="../uploads/shops/<?= htmlspecialchars($s['banner']) ?>"
         class="banner">
<?php else: ?>
    <div class="banner bg-secondary"></div>
<?php endif; ?>

<div class="p-3">

<div class="d-flex align-items-center gap-2">

<!-- LOGO -->
<?php if(!empty($s['logo'])): ?>
    <img src="../uploads/shops/<?= htmlspecialchars($s['logo']) ?>"
         class="shop-logo">
<?php else: ?>
    <div class="shop-logo bg-light"></div>
<?php endif; ?>

<div>

<h6 class="mb-0">
    <?= htmlspecialchars($s['shop_name']) ?>
</h6>

<div class="small-text">
    <?= htmlspecialchars($s['city']) ?>,
    <?= htmlspecialchars($s['region']) ?>
</div>

</div>

</div>

<!-- BADGES -->
<div class="mt-2">

<span class="badge bg-<?= badgeColor($s['status']) ?>">
    <?= ucfirst($s['status']) ?>
</span>

<?php if($s['verified']): ?>
    <span class="badge bg-primary">Verified</span>
<?php endif; ?>

</div>

<!-- STATS -->
<div class="small-text mt-2">
    👥 <?= number_format($s['followers']) ?> followers <br>
    👁️ <?= number_format($s['views']) ?> views
</div>

<!-- OPEN SHOP -->
<a href="shop-products.php?shop=<?= urlencode($s['shop_slug']) ?>"
   class="btn btn-outline-primary btn-sm w-100 mt-3">
   Visit Shop
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
   href="?q=<?= urlencode($q) ?>&page=<?= $i ?>">
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