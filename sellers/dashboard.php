<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user_id = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| GET SHOP
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT *
    FROM shops
    WHERE seller_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$shop = $stmt->get_result()->fetch_assoc();

/*
|--------------------------------------------------------------------------
| IF NO SHOP EXISTS
|--------------------------------------------------------------------------
*/
if (!$shop) {
    header("Location: ../shops/create-shop.php");
    exit;
}

$shop_id = $shop['id'];

/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/

/* Products */
$pStmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM products
    WHERE shop_id = ?
");
$pStmt->bind_param("i", $shop_id);
$pStmt->execute();
$products = $pStmt->get_result()->fetch_assoc()['total'];

/* Orders */
$oStmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM order_items
    WHERE shop_id = ?
");
$oStmt->bind_param("i", $shop_id);
$oStmt->execute();
$orders = $oStmt->get_result()->fetch_assoc()['total'];

/* Revenue */
$rStmt = $conn->prepare("
    SELECT SUM(price * quantity) as revenue
    FROM order_items
    WHERE shop_id = ?
");
$rStmt->bind_param("i", $shop_id);
$rStmt->execute();
$revenue = $rStmt->get_result()->fetch_assoc()['revenue'] ?? 0;

/* Pending Orders */
$pendingStmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE oi.shop_id = ?
    AND order_status != 'delivered'
");
$pendingStmt->bind_param("i", $shop_id);
$pendingStmt->execute();
$pending = $pendingStmt->get_result()->fetch_assoc()['total'];

/* Followers + Views */
$followers = $shop['followers'] ?? 0;
$views = $shop['views'] ?? 0;

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Seller Dashboard | Karliakoo</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f5f5f5;
}

.card-box{
    background:white;
    border-radius:12px;
    padding:20px;
}

.stat-card{
    background:white;
    border-radius:12px;
    padding:20px;
    text-align:center;
    transition:.2s;
}

.stat-card:hover{
    transform: translateY(-4px);
}

.small{
    font-size:13px;
    color:#777;
}

</style>

</head>

<body>

<div class="container py-4">

<h2 class="mb-3">
    Welcome, <?= htmlspecialchars($shop['shop_name']) ?>
</h2>

<!-- SHOP STATUS -->
<div class="card-box mb-4 shadow-sm">

<div class="d-flex justify-content-between">

<div>

<h5>Shop Status</h5>

<span class="badge bg-warning">
    <?= ucfirst($shop['status']) ?>
</span>

<?php if($shop['verified']): ?>
    <span class="badge bg-primary">Verified</span>
<?php endif; ?>

</div>

<div class="text-end">

<div class="small">Followers</div>
<h5><?= number_format($followers) ?></h5>

</div>

</div>

</div>

<!-- STATS GRID -->
<div class="row">

<div class="col-md-3 mb-3">
    <div class="stat-card shadow-sm">
        <h4><?= $products ?></h4>
        <div class="small">Products</div>
    </div>
</div>

<div class="col-md-3 mb-3">
    <div class="stat-card shadow-sm">
        <h4><?= $orders ?></h4>
        <div class="small">Orders</div>
    </div>
</div>

<div class="col-md-3 mb-3">
    <div class="stat-card shadow-sm">
        <h4>TZS <?= number_format($revenue) ?></h4>
        <div class="small">Revenue</div>
    </div>
</div>

<div class="col-md-3 mb-3">
    <div class="stat-card shadow-sm">
        <h4><?= $pending ?></h4>
        <div class="small">Pending Orders</div>
    </div>
</div>

</div>

<!-- QUICK ACTIONS -->
<div class="card-box shadow-sm mt-4">

<h5>Quick Actions</h5>

<div class="row">

<div class="col-md-3 mb-2">
    <a href="products.php" class="btn btn-outline-primary w-100">
        Manage Products
    </a>
</div>

<div class="col-md-3 mb-2">
    <a href="orders.php" class="btn btn-outline-success w-100">
        View Orders
    </a>
</div>

<div class="col-md-3 mb-2">
    <a href="earnings.php" class="btn btn-outline-dark w-100">
        Earnings
    </a>
</div>

<div class="col-md-3 mb-2">
    <a href="withdrawals.php" class="btn btn-outline-dark w-100">
        Withdrawals
</a>
</div>

<div class="col-md-3 mb-2">
    <a href="../shops/shop-profile.php?shop=<?= urlencode($shop['shop_slug']) ?>"
       class="btn btn-outline-secondary w-100">
        View Shop
    </a>

    <div class="small mt-1">
        <a href="rfq-requests.php" class="btn btn-outline-info w-100">
            View RFQ Requests
        </a>

        <div class="small mt-1">
            <a href="../logout.php" class="btn btn-danger">
                Logout
</a>
    </div>
</div>

</div>

</div>

</div>

</body>
</html>