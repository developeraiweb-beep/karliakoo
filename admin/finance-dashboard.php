<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

/*
|--------------------------------------------------------------------------
| REVENUE
|--------------------------------------------------------------------------
*/
$revenue = $conn->query("
SELECT
COALESCE(SUM(amount),0) total
FROM payments
WHERE payment_status='paid'
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| REFUNDS
|--------------------------------------------------------------------------
*/
$refunds = $conn->query("
SELECT
COALESCE(SUM(amount),0) total
FROM payments
WHERE payment_status='refunded'
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| WITHDRAWALS
|--------------------------------------------------------------------------
*/
$withdrawals = $conn->query("
SELECT
COALESCE(SUM(amount),0) total
FROM withdrawals
WHERE status='paid'
")->fetch_assoc();

$pendingWithdrawals = $conn->query("
SELECT
COALESCE(SUM(amount),0) total
FROM withdrawals
WHERE status='pending'
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| COMMISSIONS
|--------------------------------------------------------------------------
*/
$commission = 0;

if (
$conn->query(
"SHOW TABLES LIKE 'commissions'"
)->num_rows
) {

    $commissionData = $conn->query("
    SELECT
    COALESCE(SUM(amount),0) total
    FROM commissions
    WHERE status='earned'
    ")->fetch_assoc();

    $commission =
    $commissionData['total'];
}

/*
|--------------------------------------------------------------------------
| SELLERS
|--------------------------------------------------------------------------
*/
$totalSellers = $conn->query("
SELECT COUNT(*)
total
FROM users
WHERE role='seller'
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| AGENTS
|--------------------------------------------------------------------------
*/
$totalAgents = $conn->query("
SELECT COUNT(*)
total
FROM users
WHERE role='agent'
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| MONTHLY REVENUE
|--------------------------------------------------------------------------
*/
$monthlyRevenue = [];

$monthlyResult = $conn->query("
SELECT

DATE_FORMAT(
created_at,
'%Y-%m'
) month,

SUM(amount) revenue

FROM payments

WHERE payment_status='paid'

GROUP BY month

ORDER BY month ASC

LIMIT 12
");

while($row = $monthlyResult->fetch_assoc()) {

    $monthlyRevenue[] = $row;
}

/*
|--------------------------------------------------------------------------
| TOP SHOPS
|--------------------------------------------------------------------------
*/
$topShops = [];

if (
$conn->query(
"SHOW TABLES LIKE 'shops'"
)->num_rows
) {

    $shopQuery = $conn->query("
    SELECT

    s.shop_name,

    COUNT(o.id) total_orders,

    COALESCE(
    SUM(o.total_amount),
    0
    ) revenue

    FROM shops s

    LEFT JOIN orders o
    ON o.id=s.id

    GROUP BY s.id

    ORDER BY revenue DESC

    LIMIT 10
    ");

    while($row = $shopQuery->fetch_assoc()) {

        $topShops[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| TOP PRODUCTS
|--------------------------------------------------------------------------
*/
$topProducts = [];

if (
$conn->query(
"SHOW TABLES LIKE 'order_items'"
)->num_rows
) {

    $productQuery = $conn->query("
    SELECT

    product_id,

    SUM(quantity)
    sold,

    SUM(price)
    revenue

    FROM order_items

    GROUP BY product_id

    ORDER BY sold DESC

    LIMIT 10
    ");

    while($row = $productQuery->fetch_assoc()) {

        $topProducts[] = $row;
    }
}

$netProfit =
(
$revenue['total']
-
$refunds['total']
-
$withdrawals['total']
);

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Finance Dashboard

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

body{
background:#f5f7fb;
}

.card-box{
background:#fff;
padding:20px;
border-radius:15px;
box-shadow:0 2px 8px rgba(0,0,0,.08);
}

.metric{
font-size:28px;
font-weight:700;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Finance Dashboard

</h2>

<!-- KPI -->

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($revenue['total'],2) ?>
</div>
Total Revenue
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($commission,2) ?>
</div>
Commission
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($withdrawals['total'],2) ?>
</div>
Paid Withdrawals
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($netProfit,2) ?>
</div>
Net Profit
</div>
</div>

</div>

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($refunds['total'],2) ?>
</div>
Refunds
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($pendingWithdrawals['total'],2) ?>
</div>
Pending Withdrawals
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
<?= number_format($totalSellers['total']) ?>
</div>
Sellers
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
<?= number_format($totalAgents['total']) ?>
</div>
Agents
</div>
</div>

</div>

<!-- CHART -->

<div class="card-box mb-4">

<h4>

Monthly Revenue

</h4>

<canvas id="revenueChart"></canvas>

</div>

<div class="row">

<!-- TOP SHOPS -->

<div class="col-lg-6">

<div class="card-box">

<h4>

Top Shops

</h4>

<table class="table">

<thead>

<tr>

<th>Shop</th>
<th>Orders</th>
<th>Revenue</th>

</tr>

</thead>

<tbody>

<?php foreach($topShops as $shop): ?>

<tr>

<td>

<?= htmlspecialchars(
$shop['shop_name']
) ?>

</td>

<td>

<?= number_format(
$shop['total_orders']
) ?>

</td>

<td>

TZS
<?= number_format(
$shop['revenue'],
2
) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

<!-- TOP PRODUCTS -->

<div class="col-lg-6">

<div class="card-box">

<h4>

Top Products

</h4>

<table class="table">

<thead>

<tr>

<th>Product</th>
<th>Sold</th>
<th>Revenue</th>

</tr>

</thead>

<tbody>

<?php foreach($topProducts as $product): ?>

<tr>

<td>

<?= htmlspecialchars(
$product['product_id']
) ?>

</td>

<td>

<?= number_format(
$product['sold']
) ?>

</td>

<td>

TZS
<?= number_format(
$product['revenue'],
2
) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

<script>

const revenueChart =
document.getElementById(
'revenueChart'
);

new Chart(
revenueChart,
{
type:'line',

data:{
labels:[
<?php
foreach($monthlyRevenue as $month){
echo "'".$month['month']."',";
}
?>
],

datasets:[{
label:'Revenue',

data:[
<?php
foreach($monthlyRevenue as $month){
echo $month['revenue'].",";
}
?>
],

fill:false,
tension:.4

}]
}
}
);

</script>

</body>
</html>