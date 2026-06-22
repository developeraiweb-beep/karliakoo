<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

/*
|--------------------------------------------------------------------------
| REVENUE REPORT
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT

COUNT(*) total_orders,

COALESCE(
SUM(total_amount),
0
) total_revenue,

COALESCE(
AVG(total_amount),
0
) average_order,

COALESCE(
SUM(platform_commission),
0
) total_commission

FROM b2b_orders

WHERE DATE(created_at)
BETWEEN ? AND ?
");

$stmt->bind_param(
    "ss",
    $from,
    $to
);

$stmt->execute();

$revenue =
    $stmt
    ->get_result()
    ->fetch_assoc();

/*
|--------------------------------------------------------------------------
| BUYERS
|--------------------------------------------------------------------------
*/
$buyers = $conn->prepare("
SELECT COUNT(DISTINCT buyer_id) total
FROM b2b_orders
WHERE DATE(created_at)
BETWEEN ? AND ?
");

$buyers->bind_param(
    "ss",
    $from,
    $to
);

$buyers->execute();

$totalBuyers =
    $buyers
    ->get_result()
    ->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| SUPPLIERS
|--------------------------------------------------------------------------
*/
$suppliers = $conn->prepare("
SELECT COUNT(DISTINCT shop_id) total
FROM b2b_orders
WHERE DATE(created_at)
BETWEEN ? AND ?
");

$suppliers->bind_param(
    "ss",
    $from,
    $to
);

$suppliers->execute();

$totalSuppliers =
    $suppliers
    ->get_result()
    ->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| RFQs
|--------------------------------------------------------------------------
*/
$totalRfqs = 0;

$rfqCheck = $conn->query(
    "SHOW TABLES LIKE 'rfq_requests'"
);

if($rfqCheck->num_rows){

    $rfqStmt = $conn->prepare("
        SELECT COUNT(*) total
        FROM rfq_requests
        WHERE DATE(created_at)
        BETWEEN ? AND ?
    ");

    $rfqStmt->bind_param(
        "ss",
        $from,
        $to
    );

    $rfqStmt->execute();

    $totalRfqs =
        $rfqStmt
        ->get_result()
        ->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| DISPUTES
|--------------------------------------------------------------------------
*/
$totalDisputes = 0;

$disputeCheck = $conn->query(
    "SHOW TABLES LIKE 'b2b_disputes'"
);

if($disputeCheck->num_rows){

    $disputeStmt = $conn->prepare("
        SELECT COUNT(*) total
        FROM b2b_disputes
        WHERE DATE(created_at)
        BETWEEN ? AND ?
    ");

    $disputeStmt->bind_param(
        "ss",
        $from,
        $to
    );

    $disputeStmt->execute();

    $totalDisputes =
        $disputeStmt
        ->get_result()
        ->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| TOP SUPPLIERS
|--------------------------------------------------------------------------
*/
$topSuppliers = $conn->prepare("
SELECT

s.shop_name,

COUNT(o.id) orders_count,

SUM(o.total_amount) revenue

FROM b2b_orders o

INNER JOIN shops s
ON s.id=o.shop_id

WHERE DATE(o.created_at)
BETWEEN ? AND ?

GROUP BY s.id

ORDER BY revenue DESC

LIMIT 10
");

$topSuppliers->bind_param(
    "ss",
    $from,
    $to
);

$topSuppliers->execute();

$topSuppliersResult =
    $topSuppliers->get_result();

/*
|--------------------------------------------------------------------------
| TOP BUYERS
|--------------------------------------------------------------------------
*/
$topBuyers = $conn->prepare("
SELECT

u.full_name,

COUNT(o.id) orders_count,

SUM(o.total_amount) spend

FROM b2b_orders o

INNER JOIN users u
ON u.id=o.buyer_id

WHERE DATE(o.created_at)
BETWEEN ? AND ?

GROUP BY u.id

ORDER BY spend DESC

LIMIT 10
");

$topBuyers->bind_param(
    "ss",
    $from,
    $to
);

$topBuyers->execute();

$topBuyersResult =
    $topBuyers->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>
B2B Reports
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

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

B2B Executive Reports

</h2>

<form
method="GET"
class="card shadow-sm p-3 mb-4">

<div class="row">

<div class="col-md-4">

<label>From</label>

<input
type="date"
name="from"
class="form-control"
value="<?= $from ?>">

</div>

<div class="col-md-4">

<label>To</label>

<input
type="date"
name="to"
class="form-control"
value="<?= $to ?>">

</div>

<div class="col-md-4 d-flex align-items-end">

<button
class="btn btn-primary w-100">

Generate Report

</button>

</div>

</div>

</form>

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

<?= number_format(
$revenue['total_orders']
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
$revenue['total_revenue'],
2
) ?>

</h4>

<p>Revenue</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

<?= number_format(
$totalBuyers
) ?>

</h4>

<p>Active Buyers</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

<?= number_format(
$totalSuppliers
) ?>

</h4>

<p>Active Suppliers</p>

</div>

</div>

</div>

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

TZS

<?= number_format(
$revenue['average_order'],
2
) ?>

</h4>

<p>Average Order</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

TZS

<?= number_format(
$revenue['total_commission'],
2
) ?>

</h4>

<p>Commission Earned</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

<?= number_format(
$totalRfqs
) ?>

</h4>

<p>RFQs</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

<?= number_format(
$totalDisputes
) ?>

</h4>

<p>Disputes</p>

</div>

</div>

</div>

<!-- TOP SUPPLIERS -->

<div class="card shadow-sm mb-4">

<div class="card-header">

Top Suppliers

</div>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>
<th>Supplier</th>
<th>Orders</th>
<th>Revenue</th>
</tr>

</thead>

<tbody>

<?php while($row = $topSuppliersResult->fetch_assoc()): ?>

<tr>

<td>

<?= htmlspecialchars(
$row['shop_name']
) ?>

</td>

<td>

<?= number_format(
$row['orders_count']
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

<!-- TOP BUYERS -->

<div class="card shadow-sm">

<div class="card-header">

Top Buyers

</div>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>
<th>Buyer</th>
<th>Orders</th>
<th>Spend</th>
</tr>

</thead>

<tbody>

<?php while($row = $topBuyersResult->fetch_assoc()): ?>

<tr>

<td>

<?= htmlspecialchars(
$row['full_name']
) ?>

</td>

<td>

<?= number_format(
$row['orders_count']
) ?>

</td>

<td>

TZS

<?= number_format(
$row['spend'],
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