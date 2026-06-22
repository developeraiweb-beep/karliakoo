<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

/*
|--------------------------------------------------------------------------
| OVERVIEW STATS
|--------------------------------------------------------------------------
*/
$overviewQuery = $conn->query("
SELECT

COUNT(*) total_orders,

SUM(total_amount) total_revenue,

AVG(total_amount) average_order_value,

SUM(order_status='pending') pending_orders,

SUM(order_status='processing') processing_orders,

SUM(order_status='shipped') shipped_orders,

SUM(order_status='delivered') delivered_orders

FROM b2b_orders
");

$overview = $overviewQuery->fetch_assoc();

/*
|--------------------------------------------------------------------------
| RFQ STATS
|--------------------------------------------------------------------------
*/
$rfqQuery = $conn->query("
SELECT

COUNT(*) total_rfqs,

SUM(status='accepted') accepted_rfqs,

SUM(status='quoted') quoted_rfqs

FROM rfq_requests
");

$rfqStats = $rfqQuery->fetch_assoc();

$totalRfqs =
(int)$rfqStats['total_rfqs'];

$acceptedRfqs =
(int)$rfqStats['accepted_rfqs'];

$conversionRate =
$totalRfqs > 0
?
round(
($acceptedRfqs / $totalRfqs) * 100,
2
)
:
0;

/*
|--------------------------------------------------------------------------
| ACTIVE BUYERS
|--------------------------------------------------------------------------
*/
$buyersQuery = $conn->query("
SELECT
COUNT(DISTINCT buyer_id)
total_buyers
FROM b2b_orders
");

$buyers =
$buyersQuery
->fetch_assoc()['total_buyers'];

/*
|--------------------------------------------------------------------------
| ACTIVE SUPPLIERS
|--------------------------------------------------------------------------
*/
$suppliersQuery = $conn->query("
SELECT
COUNT(DISTINCT supplier_id)
total_suppliers
FROM b2b_orders
");

$suppliers =
$suppliersQuery
->fetch_assoc()['total_suppliers'];

/*
|--------------------------------------------------------------------------
| RECENT ORDERS
|--------------------------------------------------------------------------
*/
$recentOrders = $conn->query("
SELECT

o.*,

u.full_name buyer_name,

s.shop_name

FROM b2b_orders o

LEFT JOIN users u
ON u.id=o.buyer_id

LEFT JOIN shops s
ON s.id=o.shop_id

ORDER BY o.id DESC

LIMIT 10
");

/*
|--------------------------------------------------------------------------
| TOP SUPPLIERS
|--------------------------------------------------------------------------
*/
$topSuppliers = $conn->query("
SELECT

s.shop_name,

COUNT(o.id) orders_count,

SUM(o.total_amount) revenue

FROM b2b_orders o

INNER JOIN shops s
ON s.id=o.shop_id

GROUP BY o.shop_id

ORDER BY revenue DESC

LIMIT 10
");

/*
|--------------------------------------------------------------------------
| TOP BUYERS
|--------------------------------------------------------------------------
*/
$topBuyers = $conn->query("
SELECT

u.full_name,

u.email,

COUNT(o.id) total_orders,

SUM(o.total_amount) total_spent

FROM b2b_orders o

INNER JOIN users u
ON u.id=o.buyer_id

GROUP BY o.buyer_id

ORDER BY total_spent DESC

LIMIT 10
");

/*
|--------------------------------------------------------------------------
| MONTHLY REVENUE
|--------------------------------------------------------------------------
*/
$monthlyRevenue = $conn->query("
SELECT

DATE_FORMAT(
created_at,
'%Y-%m'
) revenue_month,

COUNT(*) total_orders,

SUM(total_amount) revenue

FROM b2b_orders

GROUP BY revenue_month

ORDER BY revenue_month DESC

LIMIT 12
");
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>
B2B Marketplace Overview
</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
background:#f5f6fa;
}

.stat-card{
background:#fff;
border-radius:12px;
padding:20px;
}

.section-card{
background:#fff;
border-radius:12px;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

B2B Marketplace Dashboard

</h2>

<!-- OVERVIEW -->

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

TZS

<?= number_format(
$overview['total_revenue'] ?? 0,
2
) ?>

</h4>

<p>Total Revenue</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

<?= number_format(
$overview['total_orders'] ?? 0
) ?>

</h4>

<p>Total Orders</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

<?= number_format(
$suppliers
) ?>

</h4>

<p>Active Suppliers</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

<?= number_format(
$buyers
) ?>

</h4>

<p>Active Buyers</p>

</div>

</div>

</div>

<!-- SECOND ROW -->

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

<?= $conversionRate ?>%

</h4>

<p>RFQ Conversion</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

<?= number_format(
$rfqStats['total_rfqs']
) ?>

</h4>

<p>Total RFQs</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

TZS

<?= number_format(
$overview['average_order_value'] ?? 0,
2
) ?>

</h4>

<p>Average Order</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

<?= number_format(
$overview['delivered_orders'] ?? 0
) ?>

</h4>

<p>Delivered Orders</p>

</div>

</div>

</div>

<!-- RECENT ORDERS -->

<div class="card section-card shadow-sm mb-4">

<div class="card-header">

Recent Wholesale Orders

</div>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Order</th>
<th>Buyer</th>
<th>Supplier</th>
<th>Amount</th>
<th>Status</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php while($order = $recentOrders->fetch_assoc()): ?>

<tr>

<td>

<?= htmlspecialchars(
$order['order_number']
) ?>

</td>

<td>

<?= htmlspecialchars(
$order['buyer_name']
) ?>

</td>

<td>

<?= htmlspecialchars(
$order['shop_name']
) ?>

</td>

<td>

TZS

<?= number_format(
$order['total_amount'],
2
) ?>

</td>

<td>

<?= ucfirst(
$order['order_status']
) ?>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$order['created_at']
)
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<!-- TOP SUPPLIERS -->

<div class="card section-card shadow-sm mb-4">

<div class="card-header">

Top Suppliers

</div>

<div class="table-responsive">

<table class="table">

<thead>

<tr>

<th>Supplier</th>
<th>Orders</th>
<th>Revenue</th>

</tr>

</thead>

<tbody>

<?php while($supplier = $topSuppliers->fetch_assoc()): ?>

<tr>

<td>

<?= htmlspecialchars(
$supplier['shop_name']
) ?>

</td>

<td>

<?= number_format(
$supplier['orders_count']
) ?>

</td>

<td>

TZS

<?= number_format(
$supplier['revenue'],
2
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<!-- TOP BUYERS -->

<div class="card section-card shadow-sm mb-4">

<div class="card-header">

Top Buyers

</div>

<div class="table-responsive">

<table class="table">

<thead>

<tr>

<th>Buyer</th>
<th>Email</th>
<th>Orders</th>
<th>Spent</th>

</tr>

</thead>

<tbody>

<?php while($buyer = $topBuyers->fetch_assoc()): ?>

<tr>

<td>

<?= htmlspecialchars(
$buyer['full_name']
) ?>

</td>

<td>

<?= htmlspecialchars(
$buyer['email']
) ?>

</td>

<td>

<?= number_format(
$buyer['total_orders']
) ?>

</td>

<td>

TZS

<?= number_format(
$buyer['total_spent'],
2
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<!-- MONTHLY REVENUE -->

<div class="card section-card shadow-sm">

<div class="card-header">

Monthly Revenue

</div>

<div class="table-responsive">

<table class="table">

<thead>

<tr>

<th>Month</th>
<th>Orders</th>
<th>Revenue</th>

</tr>

</thead>

<tbody>

<?php while($month = $monthlyRevenue->fetch_assoc()): ?>

<tr>

<td>

<?= htmlspecialchars(
$month['revenue_month']
) ?>

</td>

<td>

<?= number_format(
$month['total_orders']
) ?>

</td>

<td>

TZS

<?= number_format(
$month['revenue'],
2
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>