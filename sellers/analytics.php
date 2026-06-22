<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user_id = (int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Get Seller Shop
|--------------------------------------------------------------------------
*/
$shopStmt = $conn->prepare("
    SELECT *
    FROM shops
    WHERE shop_name=?
    LIMIT 1
");

$shopStmt->bind_param("i", $user_id);
$shopStmt->execute();

$shop = $shopStmt->get_result()->fetch_assoc();

if(!$shop){
    die("Shop not found.");
}

$shop_id = $shop['id'];

/*
|--------------------------------------------------------------------------
| Revenue Overview
|--------------------------------------------------------------------------
*/
$revenueStmt = $conn->prepare("
SELECT
    SUM(oi.price * oi.quantity) as total_revenue,
    SUM(oi.quantity) as total_items_sold,
    COUNT(DISTINCT oi.order_id) as total_orders
FROM order_items oi
WHERE oi.shop_id=?
");

$revenueStmt->bind_param("i", $shop_id);
$revenueStmt->execute();

$overview = $revenueStmt->get_result()->fetch_assoc();

/*
|--------------------------------------------------------------------------
| Monthly Revenue (Last 6 Months)
|--------------------------------------------------------------------------
*/
$monthly = $conn->prepare("
SELECT
    DATE_FORMAT(o.created_at, '%Y-%m') as month,
    SUM(oi.price * oi.quantity) as revenue
FROM order_items oi
JOIN orders o ON oi.order_id = o.id
WHERE oi.shop_id=?
GROUP BY month
ORDER BY month DESC
LIMIT 6
");

$monthly->bind_param("i", $shop_id);
$monthly->execute();

$monthlyData = $monthly->get_result();

/*
|--------------------------------------------------------------------------
| Top Products
|--------------------------------------------------------------------------
*/
$topProducts = $conn->prepare("
SELECT
    p.name,
    p.image,
    SUM(oi.quantity) as qty_sold,
    SUM(oi.price * oi.quantity) as revenue
FROM order_items oi
JOIN products p ON oi.product_id = p.id
WHERE oi.shop_id=?
GROUP BY oi.product_id
ORDER BY qty_sold DESC
LIMIT 5
");

$topProducts->bind_param("i", $shop_id);
$topProducts->execute();

$top = $topProducts->get_result();

/*
|--------------------------------------------------------------------------
| Order Status Breakdown
|--------------------------------------------------------------------------
*/
$statusStmt = $conn->prepare("
SELECT
    o.status,
    COUNT(DISTINCT o.id) as total
FROM orders o
JOIN order_items oi ON o.id = oi.order_id
WHERE oi.shop_id=?
GROUP BY o.status
");

$statusStmt->bind_param("i", $shop_id);
$statusStmt->execute();

$statusData = $statusStmt->get_result();

/*
|--------------------------------------------------------------------------
| Status Color
|--------------------------------------------------------------------------
*/
function badge($status){
    return match($status){
        'pending' => 'warning',
        'paid' => 'info',
        'processing' => 'primary',
        'shipped' => 'secondary',
        'delivered' => 'success',
        'cancelled' => 'danger',
        default => 'dark'
    };
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Seller Analytics</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

body{
    background:#f5f5f5;
}

.card-box{
    background:white;
    padding:20px;
    border-radius:12px;
}

.small-text{
    font-size:14px;
    color:#777;
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

<div class="container py-4">

<h2 class="mb-4">Seller Analytics</h2>

<!-- Overview -->
<div class="row mb-4">

<div class="col-md-4">
    <div class="card-box">
        <h4>TZS <?= number_format($overview['total_revenue'] ?? 0) ?></h4>
        <p>Total Revenue</p>
    </div>
</div>

<div class="col-md-4">
    <div class="card-box">
        <h4><?= number_format($overview['total_orders'] ?? 0) ?></h4>
        <p>Total Orders</p>
    </div>
</div>

<div class="col-md-4">
    <div class="card-box">
        <h4><?= number_format($overview['total_items_sold'] ?? 0) ?></h4>
        <p>Items Sold</p>
    </div>
</div>

</div>

<!-- Charts Row -->
<div class="row mb-4">

<!-- Revenue Chart -->
<div class="col-lg-8">
    <div class="card-box">
        <h5>Monthly Revenue</h5>
        <canvas id="revenueChart"></canvas>
    </div>
</div>

<!-- Status Chart -->
<div class="col-lg-4">
    <div class="card-box">
        <h5>Order Status</h5>
        <canvas id="statusChart"></canvas>
    </div>
</div>

</div>

<!-- Top Products -->
<div class="card-box mb-4">

<h5>Top Selling Products</h5>

<table class="table">

<thead>

<tr>
<th>Product</th>
<th>Qty Sold</th>
<th>Revenue</th>
</tr>

</thead>

<tbody>

<?php while($p = $top->fetch_assoc()): ?>

<tr>

<td class="d-flex align-items-center gap-2">

<img src="../uploads/products/<?= htmlspecialchars($p['image']) ?>" class="product-img">

<?= htmlspecialchars($p['name']) ?>

</td>

<td><?= number_format($p['qty_sold']) ?></td>

<td>TZS <?= number_format($p['revenue']) ?></td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<script>

/*
|--------------------------------------------------------------------------
| Monthly Revenue Chart
|--------------------------------------------------------------------------
*/
const revenueCtx =
document.getElementById('revenueChart');

new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: [
            <?php
            $months = [];
            $revenue = [];

            while($m = $monthlyData->fetch_assoc()){
                $months[] = "'".$m['month']."'";
                $revenue[] = $m['revenue'] ?? 0;
            }

            echo implode(',', array_reverse($months));
            ?>
        ],
        datasets: [{
            label: 'Revenue (TZS)',
            data: [
                <?= implode(',', array_reverse($revenue)) ?>
            ],
            borderWidth: 2,
            fill: true
        }]
    }
});

/*
|--------------------------------------------------------------------------
| Status Chart
|--------------------------------------------------------------------------
*/
const statusCtx =
document.getElementById('statusChart');

new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: [
            <?php
            $labels = [];
            $values = [];

            while($s = $statusData->fetch_assoc()){
                $labels[] = "'".ucfirst($s['status'])."'";
                $values[] = $s['total'];
            }

            echo implode(',', $labels);
            ?>
        ],
        datasets: [{
            data: [
                <?= implode(',', $values) ?>
            ]
        }]
    }
});

</script>

</body>
</html>