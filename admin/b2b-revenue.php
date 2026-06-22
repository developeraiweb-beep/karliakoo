<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

/*
|--------------------------------------------------------------------------
| REVENUE OVERVIEW
|--------------------------------------------------------------------------
*/
$overview = $conn->query("
SELECT

COALESCE(SUM(total_amount),0) total_revenue,

COALESCE(
SUM(
CASE
WHEN DATE(created_at)=CURDATE()
THEN total_amount
ELSE 0
END
),0
) today_revenue,

COALESCE(
SUM(
CASE
WHEN YEAR(created_at)=YEAR(CURDATE())
AND MONTH(created_at)=MONTH(CURDATE())
THEN total_amount
ELSE 0
END
),0
) monthly_revenue,

COALESCE(
SUM(
CASE
WHEN YEAR(created_at)=YEAR(CURDATE())
THEN total_amount
ELSE 0
END
),0
) yearly_revenue,

COALESCE(
SUM(
CASE
WHEN payment_status='paid'
THEN total_amount
ELSE 0
END
),0
) paid_revenue,

COALESCE(
SUM(
CASE
WHEN payment_status!='paid'
THEN total_amount
ELSE 0
END
),0
) pending_revenue,

COALESCE(
SUM(platform_commission),
0
) commission_revenue

FROM b2b_orders
")->fetch_assoc();

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
) month_name,

COUNT(*) total_orders,

SUM(total_amount) revenue,

SUM(platform_commission) commission

FROM b2b_orders

GROUP BY month_name

ORDER BY month_name DESC

LIMIT 12
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

SUM(o.total_amount) revenue,

SUM(o.platform_commission) commission

FROM b2b_orders o

INNER JOIN shops s
ON s.id=o.shop_id

GROUP BY o.shop_id

ORDER BY revenue DESC

LIMIT 15
");

/*
|--------------------------------------------------------------------------
| TOP ORDERS
|--------------------------------------------------------------------------
*/
$topOrders = $conn->query("
SELECT

o.order_number,

o.total_amount,

o.payment_status,

o.created_at,

u.full_name buyer_name,

s.shop_name

FROM b2b_orders o

LEFT JOIN users u
ON u.id=o.buyer_id

LEFT JOIN shops s
ON s.id=o.shop_id

ORDER BY o.total_amount DESC

LIMIT 20
");

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>
B2B Revenue Analytics
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
padding:20px;
border-radius:12px;
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

B2B Revenue Dashboard

</h2>

<!-- OVERVIEW -->

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="stat-card shadow-sm">
<h4>
TZS <?= number_format($overview['total_revenue'],2) ?>
</h4>
<p>Total Revenue</p>
</div>
</div>

<div class="col-md-3">
<div class="stat-card shadow-sm">
<h4>
TZS <?= number_format($overview['today_revenue'],2) ?>
</h4>
<p>Today's Revenue</p>
</div>
</div>

<div class="col-md-3">
<div class="stat-card shadow-sm">
<h4>
TZS <?= number_format($overview['monthly_revenue'],2) ?>
</h4>
<p>This Month</p>
</div>
</div>

<div class="col-md-3">
<div class="stat-card shadow-sm">
<h4>
TZS <?= number_format($overview['yearly_revenue'],2) ?>
</h4>
<p>This Year</p>
</div>
</div>

</div>

<div class="row g-3 mb-4">

<div class="col-md-4">
<div class="stat-card shadow-sm">
<h4>
TZS <?= number_format($overview['paid_revenue'],2) ?>
</h4>
<p>Paid Revenue</p>
</div>
</div>

<div class="col-md-4">
<div class="stat-card shadow-sm">
<h4>
TZS <?= number_format($overview['pending_revenue'],2) ?>
</h4>
<p>Pending Revenue</p>
</div>
</div>

<div class="col-md-4">
<div class="stat-card shadow-sm">
<h4>
TZS <?= number_format($overview['commission_revenue'],2) ?>
</h4>
<p>Platform Commission</p>
</div>
</div>

</div>

<!-- MONTHLY REVENUE -->

<div class="card section-card shadow-sm mb-4">

<div class="card-header">

Monthly Revenue Trends

</div>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Month</th>
<th>Orders</th>
<th>Revenue</th>
<th>Commission</th>

</tr>

</thead>

<tbody>

<?php while($month = $monthlyRevenue->fetch_assoc()): ?>

<tr>

<td>
<?= htmlspecialchars($month['month_name']) ?>
</td>

<td>
<?= number_format($month['total_orders']) ?>
</td>

<td>
TZS <?= number_format($month['revenue'],2) ?>
</td>

<td>
TZS <?= number_format($month['commission'],2) ?>
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

Top Revenue Suppliers

</div>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Supplier</th>
<th>Orders</th>
<th>Revenue</th>
<th>Commission</th>

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

<td>

TZS

<?= number_format(
$supplier['commission'],
2
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<!-- TOP ORDERS -->

<div class="card section-card shadow-sm">

<div class="card-header">

Largest Wholesale Orders

</div>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Order</th>
<th>Buyer</th>
<th>Supplier</th>
<th>Amount</th>
<th>Payment</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php while($order = $topOrders->fetch_assoc()): ?>

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

<?php

$badge = match($order['payment_status']) {

'paid' => 'success',
'partial' => 'warning',
'pending' => 'secondary',
'refunded' => 'danger',
default => 'dark'

};

?>

<span class="badge bg-<?= $badge ?>">

<?= ucfirst(
$order['payment_status']
) ?>

</span>

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

</div>

</body>
</html>