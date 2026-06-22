<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

/*
|--------------------------------------------------------------------------
| B2B KPIs
|--------------------------------------------------------------------------
*/

$buyers = 0;
$suppliers = 0;
$rfqs = 0;
$quotes = 0;
$orders = 0;
$disputes = 0;
$revenue = 0;
$commissions = 0;

/*
|--------------------------------------------------------------------------
| BUYERS
|--------------------------------------------------------------------------
*/
if($conn->query("SHOW TABLES LIKE 'b2b_buyers'")->num_rows)
{
    $buyers = $conn->query("
        SELECT COUNT(*) total
        FROM b2b_buyers
    ")->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| SUPPLIERS
|--------------------------------------------------------------------------
*/
if($conn->query("SHOW TABLES LIKE 'b2b_suppliers'")->num_rows)
{
    $suppliers = $conn->query("
        SELECT COUNT(*) total
        FROM b2b_suppliers
    ")->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| RFQS
|--------------------------------------------------------------------------
*/
if($conn->query("SHOW TABLES LIKE 'rfqs'")->num_rows)
{
    $rfqs = $conn->query("
        SELECT COUNT(*) total
        FROM rfqs
    ")->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| QUOTES
|--------------------------------------------------------------------------
*/
if($conn->query("SHOW TABLES LIKE 'quotes'")->num_rows)
{
    $quotes = $conn->query("
        SELECT COUNT(*) total
        FROM quotes
    ")->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| ORDERS
|--------------------------------------------------------------------------
*/
if($conn->query("SHOW TABLES LIKE 'b2b_orders'")->num_rows)
{
    $orders = $conn->query("
        SELECT COUNT(*) total
        FROM b2b_orders
    ")->fetch_assoc()['total'];

    $revenue = $conn->query("
        SELECT COALESCE(SUM(total_amount),0) total
        FROM b2b_orders
        WHERE payment_status='paid'
    ")->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| COMMISSIONS
|--------------------------------------------------------------------------
*/
if($conn->query("SHOW TABLES LIKE 'commissions'")->num_rows)
{
    $commissions = $conn->query("
        SELECT COALESCE(SUM(amount),0) total
        FROM commissions
        WHERE commission_type='b2b'
    ")->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| DISPUTES
|--------------------------------------------------------------------------
*/
if($conn->query("SHOW TABLES LIKE 'b2b_disputes'")->num_rows)
{
    $disputes = $conn->query("
        SELECT COUNT(*) total
        FROM b2b_disputes
        WHERE status!='resolved'
    ")->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| RECENT RFQS
|--------------------------------------------------------------------------
*/
$recentRfqs = [];

if($conn->query("SHOW TABLES LIKE 'rfqs'")->num_rows)
{
    $recentRfqs = $conn->query("
        SELECT *
        FROM rfqs
        ORDER BY id DESC
        LIMIT 10
    ");
}

/*
|--------------------------------------------------------------------------
| RECENT ORDERS
|--------------------------------------------------------------------------
*/
$recentOrders = [];

if($conn->query("SHOW TABLES LIKE 'b2b_orders'")->num_rows)
{
    $recentOrders = $conn->query("
        SELECT *
        FROM b2b_orders
        ORDER BY id DESC
        LIMIT 10
    ");
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>B2B Dashboard</title>

<meta name="viewport"
content="width=device-width,initial-scale=1">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
background:#f4f6f9;
}

.card-box{
background:#fff;
padding:20px;
border-radius:15px;
box-shadow:0 2px 10px rgba(0,0,0,.08);
height:100%;
}

.metric{
font-size:32px;
font-weight:700;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between mb-4">

<h2>B2B Marketplace Dashboard</h2>

<a
href="dashboard.php"
class="btn btn-secondary">

Main Dashboard

</a>

</div>

<!-- KPI ROW 1 -->

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card-box">
<div class="metric">
<?= number_format($buyers) ?>
</div>
B2B Buyers
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
<?= number_format($suppliers) ?>
</div>
Suppliers
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
<?= number_format($rfqs) ?>
</div>
RFQs
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
<?= number_format($quotes) ?>
</div>
Quotes
</div>
</div>

</div>

<!-- KPI ROW 2 -->

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card-box">
<div class="metric">
<?= number_format($orders) ?>
</div>
Bulk Orders
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric text-success">
TZS <?= number_format($revenue,0) ?>
</div>
Revenue
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric text-primary">
TZS <?= number_format($commissions,0) ?>
</div>
Commissions
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric text-danger">
<?= number_format($disputes) ?>
</div>
Open Disputes
</div>
</div>

</div>

<!-- QUICK LINKS -->

<div class="card-box mb-4">

<h5 class="mb-3">

B2B Management

</h5>

<div class="d-flex flex-wrap gap-2">

<a href="b2b-buyers.php" class="btn btn-primary">
Buyers
</a>

<a href="b2b-suppliers.php" class="btn btn-success">
Suppliers
</a>

<a href="b2b-rfqs.php" class="btn btn-warning">
RFQs
</a>

<a href="b2b-orders.php" class="btn btn-info">
Orders
</a>

<a href="b2b-disputes.php" class="btn btn-danger">
Disputes
</a>

<a href="b2b-revenue.php" class="btn btn-dark">
Revenue
</a>

<a href="b2b-commissions.php" class="btn btn-secondary">
Commissions
</a>

<a href="fraud-monitor.php" class="btn btn-danger">
    Fraud Monitor
</a>

<a href="b2b-overview.php" class="btn btn-dark">
    Overview
</a>

</div>

</div>

<!-- RECENT RFQS -->

<div class="card-box mb-4">

<h4>Recent RFQs</h4>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>
<th>ID</th>
<th>Title</th>
<th>Status</th>
<th>Date</th>
<th></th>
</tr>

</thead>

<tbody>

<?php if($recentRfqs): ?>
<?php while($rfq = $recentRfqs->fetch_assoc()): ?>

<tr>

<td>#<?= $rfq['id'] ?></td>

<td>
<?= htmlspecialchars(
$rfq['title'] ?? 'RFQ'
) ?>
</td>

<td>
<?= htmlspecialchars(
$rfq['status'] ?? '-'
) ?>
</td>

<td>
<?= date(
'd M Y',
strtotime($rfq['created_at'])
) ?>
</td>

<td>

<a
href="b2b-rfq-details.php?id=<?= $rfq['id'] ?>"
class="btn btn-sm btn-primary">

View

</a>

</td>

</tr>

<?php endwhile; ?>
<?php endif; ?>

</tbody>

</table>

</div>

</div>

<!-- RECENT ORDERS -->

<div class="card-box">

<h4>Recent Bulk Orders</h4>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>
<th>Order</th>
<th>Total</th>
<th>Status</th>
<th>Date</th>
<th></th>
</tr>

</thead>

<tbody>

<?php if($recentOrders): ?>
<?php while($order = $recentOrders->fetch_assoc()): ?>

<tr>

<td>

<?= htmlspecialchars(
$order['order_number']
?? '#'.$order['id']
) ?>

</td>

<td>

TZS

<?= number_format(
$order['total_amount'] ?? 0,
2
) ?>

</td>

<td>

<?= htmlspecialchars(
$order['order_status']
?? 'pending'
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

<td>

<a
href="b2b-order-details.php?id=<?= $order['id'] ?>"
class="btn btn-sm btn-primary">

View

</a>

</td>

</tr>

<?php endwhile; ?>
<?php endif; ?>

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>