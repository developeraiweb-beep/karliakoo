<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

/*
|--------------------------------------------------------------------------
| ORDERS
|--------------------------------------------------------------------------
*/
$orderStats = $conn->query("
SELECT

COUNT(*) total_orders,

COALESCE(
SUM(total_amount),
0
) total_revenue,

COALESCE(
AVG(total_amount),
0
) avg_order_value

FROM b2b_orders

WHERE DATE(created_at)
BETWEEN '{$conn->real_escape_string($from)}'
AND '{$conn->real_escape_string($to)}'
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| USERS
|--------------------------------------------------------------------------
*/
$userStats = $conn->query("
SELECT

COUNT(*) total_users,

SUM(role='buyer') buyers,

SUM(role='seller') sellers,

SUM(role='agent') agents

FROM users
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| SHOPS
|--------------------------------------------------------------------------
*/
$shopStats = $conn->query("
SELECT

COUNT(*) total_shops,

SUM(status='approved') approved,

SUM(verified=1) verified

FROM shops
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| PRODUCTS
|--------------------------------------------------------------------------
*/
$productStats = $conn->query("
SELECT

COUNT(*) total_products,

SUM(status='active') active_products

FROM products
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| RFQs
|--------------------------------------------------------------------------
*/
$rfqs = 0;

$checkRFQ =
$conn->query(
"SHOW TABLES LIKE 'rfq_requests'"
);

if($checkRFQ->num_rows){

    $rfqResult =
    $conn->query("
    SELECT COUNT(*) total
    FROM rfq_requests
    ");

    $rfqs =
    $rfqResult
    ->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| DISPUTES
|--------------------------------------------------------------------------
*/
$disputes = 0;

$checkDispute =
$conn->query(
"SHOW TABLES LIKE 'b2b_disputes'"
);

if($checkDispute->num_rows){

    $d =
    $conn->query("
    SELECT COUNT(*) total
    FROM b2b_disputes
    ");

    $disputes =
    $d->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| COMMISSIONS
|--------------------------------------------------------------------------
*/
$commissions = 0;

$checkCommission =
$conn->query(
"SHOW TABLES LIKE 'b2b_commissions'"
);

if($checkCommission->num_rows){

    $c =
    $conn->query("
    SELECT
    COALESCE(
    SUM(commission_amount),
    0
    ) total
    FROM b2b_commissions
    ");

    $commissions =
    $c->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| MONTHLY REVENUE
|--------------------------------------------------------------------------
*/
$monthlyRevenue =
$conn->query("
SELECT

DATE_FORMAT(
created_at,
'%Y-%m'
) month,

SUM(total_amount)
revenue

FROM b2b_orders

GROUP BY month

ORDER BY month DESC

LIMIT 12
");

/*
|--------------------------------------------------------------------------
| TOP SUPPLIERS
|--------------------------------------------------------------------------
*/
$topSuppliers =
$conn->query("
SELECT

s.shop_name,

COUNT(o.id)
orders_count,

SUM(o.total_amount)
revenue

FROM b2b_orders o

INNER JOIN shops s
ON s.id=o.shop_id

GROUP BY s.id

ORDER BY revenue DESC

LIMIT 10
");

/*
|--------------------------------------------------------------------------
| TOP BUYERS
|--------------------------------------------------------------------------
*/
$topBuyers =
$conn->query("
SELECT

u.full_name,

COUNT(o.id)
orders_count,

SUM(o.total_amount)
spend

FROM b2b_orders o

INNER JOIN users u
ON u.id=o.buyer_id

GROUP BY u.id

ORDER BY spend DESC

LIMIT 10
");

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>
System Analytics
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
border-radius:12px;
padding:20px;
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

System Analytics Dashboard

</h2>

<form
method="GET"
class="card-box shadow-sm mb-4">

<div class="row">

<div class="col-md-5">

<label>From</label>

<input
type="date"
name="from"
class="form-control"
value="<?= $from ?>">

</div>

<div class="col-md-5">

<label>To</label>

<input
type="date"
name="to"
class="form-control"
value="<?= $to ?>">

</div>

<div class="col-md-2 d-flex align-items-end">

<button
class="btn btn-primary w-100">

Filter

</button>

</div>

</div>

</form>

<!-- KPI ROW -->

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card-box shadow-sm">
<div class="metric">
<?= number_format($orderStats['total_orders']) ?>
</div>
Orders
</div>
</div>

<div class="col-md-3">
<div class="card-box shadow-sm">
<div class="metric">
TZS <?= number_format($orderStats['total_revenue'],2) ?>
</div>
Revenue
</div>
</div>

<div class="col-md-3">
<div class="card-box shadow-sm">
<div class="metric">
<?= number_format($userStats['total_users']) ?>
</div>
Users
</div>
</div>

<div class="col-md-3">
<div class="card-box shadow-sm">
<div class="metric">
<?= number_format($shopStats['total_shops']) ?>
</div>
Shops
</div>
</div>

</div>

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card-box shadow-sm">
<div class="metric">
<?= number_format($productStats['total_products']) ?>
</div>
Products
</div>
</div>

<div class="col-md-3">
<div class="card-box shadow-sm">
<div class="metric">
<?= number_format($rfqs) ?>
</div>
RFQs
</div>
</div>

<div class="col-md-3">
<div class="card-box shadow-sm">
<div class="metric">
<?= number_format($disputes) ?>
</div>
Disputes
</div>
</div>

<div class="col-md-3">
<div class="card-box shadow-sm">
<div class="metric">
TZS <?= number_format($commissions,2) ?>
</div>
Commissions
</div>
</div>

</div>

<!-- USER BREAKDOWN -->

<div class="card-box shadow-sm mb-4">

<h4>User Breakdown</h4>

<table class="table">

<tr>
<th>Buyers</th>
<td><?= number_format($userStats['buyers']) ?></td>
</tr>

<tr>
<th>Sellers</th>
<td><?= number_format($userStats['sellers']) ?></td>
</tr>

<tr>
<th>Agents</th>
<td><?= number_format($userStats['agents']) ?></td>
</tr>

</table>

</div>

<!-- MONTHLY REVENUE -->

<div class="card-box shadow-sm mb-4">

<h4>Monthly Revenue</h4>

<table class="table table-hover">

<thead>

<tr>
<th>Month</th>
<th>Revenue</th>
</tr>

</thead>

<tbody>

<?php while($month = $monthlyRevenue->fetch_assoc()): ?>

<tr>

<td>
<?= htmlspecialchars($month['month']) ?>
</td>

<td>
TZS <?= number_format($month['revenue'],2) ?>
</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

<!-- TOP SUPPLIERS -->

<div class="card-box shadow-sm mb-4">

<h4>Top Suppliers</h4>

<table class="table table-hover">

<thead>

<tr>
<th>Supplier</th>
<th>Orders</th>
<th>Revenue</th>
</tr>

</thead>

<tbody>

<?php while($row = $topSuppliers->fetch_assoc()): ?>

<tr>

<td>
<?= htmlspecialchars($row['shop_name']) ?>
</td>

<td>
<?= number_format($row['orders_count']) ?>
</td>

<td>
TZS <?= number_format($row['revenue'],2) ?>
</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

<!-- TOP BUYERS -->

<div class="card-box shadow-sm">

<h4>Top Buyers</h4>

<table class="table table-hover">

<thead>

<tr>
<th>Buyer</th>
<th>Orders</th>
<th>Total Spend</th>
</tr>

</thead>

<tbody>

<?php while($row = $topBuyers->fetch_assoc()): ?>

<tr>

<td>
<?= htmlspecialchars($row['full_name']) ?>
</td>

<td>
<?= number_format($row['orders_count']) ?>
</td>

<td>
TZS <?= number_format($row['spend'],2) ?>
</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</body>
</html>