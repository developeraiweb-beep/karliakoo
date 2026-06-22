<?php

require_once "../config/db.php";

/*
|--------------------------------------------------------------------------
| GET SHOP SLUG
|--------------------------------------------------------------------------
*/
$slug = $_GET['shop'] ?? '';

if (empty($slug)) {
    die("Shop not found.");
}

/*
|--------------------------------------------------------------------------
| FETCH SHOP
|--------------------------------------------------------------------------
*/
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
| INCREMENT VIEWS (simple anti-flood version optional upgrade later)
|--------------------------------------------------------------------------
*/
$updateViews = $conn->prepare("
    UPDATE shops
    SET views = views + 1
    WHERE id = ?
");

$updateViews->bind_param("i", $shop['id']);
$updateViews->execute();

/*
|--------------------------------------------------------------------------
| FETCH FEATURED PRODUCTS (LIMIT 8)
|--------------------------------------------------------------------------
*/
$productStmt = $conn->prepare("
    SELECT *
    FROM products
    WHERE shop_id = ?
    ORDER BY id DESC
    LIMIT 8
");

$productStmt->bind_param("i", $shop['id']);
$productStmt->execute();

$products = $productStmt->get_result();

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/
function badge($status)
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

<title><?= htmlspecialchars($shop['shop_name']) ?> | Karliakoo Shop</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f5f5f5;
}

.banner{
    height:220px;
    width:100%;
    object-fit:cover;
    border-radius:12px;
}

.shop-card{
    background:white;
    border-radius:12px;
    padding:20px;
}

.logo{
    width:80px;
    height:80px;
    object-fit:cover;
    border-radius:12px;
    margin-top:-40px;
    border:3px solid white;
    background:white;
}

.product-card{
    background:white;
    border-radius:12px;
    overflow:hidden;
    transition:.2s;
}

.product-card:hover{
    transform: translateY(-4px);
}

.product-img{
    height:160px;
    width:100%;
    object-fit:cover;
}

.small{
    font-size:13px;
    color:#777;
}

</style>

</head>

<body>

<div class="container py-4">

<!-- BANNER -->
<?php if(!empty($shop['banner'])): ?>
    <img src="../uploads/shops/<?= htmlspecialchars($shop['banner']) ?>"
         class="banner mb-3">
<?php else: ?>
    <div class="banner bg-secondary mb-3"></div>
<?php endif; ?>

<!-- SHOP INFO -->
<div class="shop-card shadow-sm">

<div class="d-flex align-items-center gap-3">

<!-- LOGO -->
<?php if(!empty($shop['logo'])): ?>
    <img src="../uploads/shops/<?= htmlspecialchars($shop['logo']) ?>"
         class="logo">
<?php else: ?>
    <div class="logo bg-light"></div>
<?php endif; ?>

<div>

<h3 class="mb-0">
    <?= htmlspecialchars($shop['shop_name']) ?>
</h3>

<div class="small">
    <?= htmlspecialchars($shop['city']) ?>,
    <?= htmlspecialchars($shop['region']) ?>
</div>

<div class="mt-1">

<span class="badge bg-<?= badge($shop['status']) ?>">
    <?= ucfirst($shop['status']) ?>
</span>

<?php if($shop['verified']): ?>
    <span class="badge bg-primary">Verified</span>
<?php endif; ?>

</div>

</div>

</div>

<p class="mt-3">
    <?= nl2br(htmlspecialchars($shop['description'])) ?>
</p>

<!-- STATS -->
<div class="row text-center mt-3">

<div class="col">
    <h6><?= number_format($shop['followers']) ?></h6>
    <small class="text-muted">Followers</small>
</div>

<div class="col">
    <h6><?= number_format($shop['views']) ?></h6>
    <small class="text-muted">Views</small>
</div>

<div class="col">
    <h6><?= $shop['verified'] ? "Yes" : "No" ?></h6>
    <small class="text-muted">Verified</small>
</div>

</div>

<!-- ACTIONS -->
<div class="mt-3 d-flex gap-2">

<!-- Follow button placeholder (future feature) -->
<button class="btn btn-primary btn-sm w-50">
    Follow Shop
</button>

<a href="shop-products.php?shop=<?= urlencode($shop['shop_slug']) ?>"
   class="btn btn-outline-primary btn-sm w-50">
    View All Products
</a>

</div>

</div>

<!-- PRODUCTS -->
<h5 class="mt-4 mb-3">Featured Products</h5>

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