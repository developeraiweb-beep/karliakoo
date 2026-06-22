<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['seller']);

$user = currentUser();
$seller_id = (int)$user['id'];

/*
|--------------------------------------------------------------------------
| OVERVIEW
|--------------------------------------------------------------------------
*/
$overview = $conn->prepare("
SELECT

COUNT(*) total_orders,

SUM(total_amount) total_revenue,

AVG(total_amount) avg_order_value,

SUM(order_status='pending') pending_orders,

SUM(order_status='processing') processing_orders,

SUM(order_status='shipped') shipped_orders,

SUM(order_status='delivered') delivered_orders

FROM b2b_orders

WHERE supplier_id=?
");

$overview->bind_param("i", $seller_id);
$overview->execute();

$overviewData =
    $overview->get_result()->fetch_assoc();

/*
|--------------------------------------------------------------------------
| RFQ STATS
|--------------------------------------------------------------------------
*/
$rfqStats = $conn->prepare("
SELECT

COUNT(*) total_rfqs,

SUM(status='accepted') accepted_rfqs,

SUM(status='quoted') quoted_rfqs

FROM rfq_requests

WHERE supplier_id=?
");

$rfqStats->bind_param(
    "i",
    $seller_id
);

$rfqStats->execute();

$rfq =
    $rfqStats
    ->get_result()
    ->fetch_assoc();

$totalRfqs =
    (int)$rfq['total_rfqs'];

$acceptedRfqs =
    (int)$rfq['accepted_rfqs'];

$conversionRate =
    $totalRfqs > 0
    ? round(
        ($acceptedRfqs / $totalRfqs) * 100,
        2
      )
    : 0;

/*
|--------------------------------------------------------------------------
| TOP PRODUCTS
|--------------------------------------------------------------------------
*/
$topProducts = $conn->prepare("
SELECT

oi.product_name,

SUM(oi.quantity) total_qty,

SUM(oi.total_price) revenue

FROM b2b_order_items oi

INNER JOIN b2b_orders o
ON o.id=oi.order_id

WHERE o.supplier_id=?

GROUP BY oi.product_id

ORDER BY revenue DESC

LIMIT 10
");

$topProducts->bind_param(
    "i",
    $seller_id
);

$topProducts->execute();

$products =
    $topProducts->get_result();

/*
|--------------------------------------------------------------------------
| TOP CUSTOMERS
|--------------------------------------------------------------------------
*/
$topCustomers = $conn->prepare("
SELECT

u.full_name,

u.email,

COUNT(o.id) total_orders,

SUM(o.total_amount) total_spent

FROM b2b_orders o

INNER JOIN users u
ON u.id=o.buyer_id

WHERE o.supplier_id=?

GROUP BY o.buyer_id

ORDER BY total_spent DESC

LIMIT 10
");

$topCustomers->bind_param(
    "i",
    $seller_id
);

$topCustomers->execute();

$customers =
    $topCustomers->get_result();

/*
|--------------------------------------------------------------------------
| MONTHLY SALES
|--------------------------------------------------------------------------
*/
$monthly = $conn->prepare("
SELECT

DATE_FORMAT(
created_at,
'%Y-%m'
) sales_month,

COUNT(*) orders,

SUM(total_amount) revenue

FROM b2b_orders

WHERE supplier_id=?

GROUP BY sales_month

ORDER BY sales_month DESC

LIMIT 12
");

$monthly->bind_param(
    "i",
    $seller_id
);

$monthly->execute();

$monthlySales =
    $monthly->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>
B2B Analytics
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

B2B Analytics

</h2>

<!-- OVERVIEW -->

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

TZS

<?= number_format(
$overviewData['total_revenue'] ?? 0,
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
$overviewData['total_orders'] ?? 0
) ?>

</h4>

<p>Total Orders</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

TZS

<?= number_format(
$overviewData['avg_order_value'] ?? 0,
2
) ?>

</h4>

<p>Average Order Value</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

<?= $conversionRate ?>%

</h4>

<p>RFQ Conversion Rate</p>

</div>

</div>

</div>

<!-- ORDER STATUS -->

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h5>

<?= $overviewData['pending_orders'] ?? 0 ?>

</h5>

<p>Pending</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h5>

<?= $overviewData['processing_orders'] ?? 0 ?>

</h5>

<p>Processing</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h5>

<?= $overviewData['shipped_orders'] ?? 0 ?>

</h5>

<p>Shipped</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h5>

<?= $overviewData['delivered_orders'] ?? 0 ?>

</h5>

<p>Delivered</p>

</div>

</div>

</div>

<!-- TOP PRODUCTS -->

<div class="card section-card shadow-sm mb-4">

<div class="card-header">

Top Wholesale Products

</div>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Product</th>
<th>Quantity Sold</th>
<th>Revenue</th>

</tr>

</thead>

<tbody>

<?php while($row = $products->fetch_assoc()): ?>

<tr>

<td>

<?= htmlspecialchars(
$row['product_name']
) ?>

</td>

<td>

<?= number_format(
$row['total_qty']
) ?>

</td>

<td>

TZS

<?= number_format(
$row['revenue'],
2
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<!-- TOP CUSTOMERS -->

<div class="card section-card shadow-sm mb-4">

<div class="card-header">

Top Buyers

</div>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Buyer</th>
<th>Email</th>
<th>Orders</th>
<th>Total Spent</th>

</tr>

</thead>

<tbody>

<?php while($buyer = $customers->fetch_assoc()): ?>

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

<!-- MONTHLY SALES -->

<div class="card section-card shadow-sm">

<div class="card-header">

Monthly Sales

</div>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Month</th>
<th>Orders</th>
<th>Revenue</th>

</tr>

</thead>

<tbody>

<?php while($month = $monthlySales->fetch_assoc()): ?>

<tr>

<td>

<?= htmlspecialchars(
$month['sales_month']
) ?>

</td>

<td>

<?= number_format(
$month['orders']
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