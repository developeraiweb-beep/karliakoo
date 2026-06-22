<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['b2b']);

$userId = (int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| OVERVIEW STATS
|--------------------------------------------------------------------------
*/
$stats = $conn->prepare("
SELECT

COUNT(*) total_contracts,

SUM(
CASE WHEN status='active'
THEN 1 ELSE 0 END
) active_contracts,

SUM(
CASE WHEN status='expired'
THEN 1 ELSE 0 END
) expired_contracts,

SUM(
CASE WHEN status='terminated'
THEN 1 ELSE 0 END
) terminated_contracts,

COALESCE(
SUM(contract_value),
0
) total_value,

COALESCE(
AVG(contract_value),
0
) avg_value

FROM b2b_contracts

WHERE buyer_id=?
");

$stats->bind_param("i",$userId);
$stats->execute();

$overview =
$stats
->get_result()
->fetch_assoc();

/*
|--------------------------------------------------------------------------
| EXPIRING IN 30 DAYS
|--------------------------------------------------------------------------
*/
$expiring = $conn->prepare("
SELECT COUNT(*) total

FROM b2b_contracts

WHERE buyer_id=?
AND status='active'

AND end_date BETWEEN
CURDATE()
AND DATE_ADD(
CURDATE(),
INTERVAL 30 DAY
)
");

$expiring->bind_param(
    "i",
    $userId
);

$expiring->execute();

$expiringSoon =
$expiring
->get_result()
->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| MONTHLY CONTRACT VALUES
|--------------------------------------------------------------------------
*/
$monthly = $conn->prepare("
SELECT

DATE_FORMAT(
created_at,
'%Y-%m'
) month,

COUNT(*) contracts,

SUM(contract_value) value_total

FROM b2b_contracts

WHERE buyer_id=?

GROUP BY month

ORDER BY month DESC

LIMIT 12
");

$monthly->bind_param(
    "i",
    $userId
);

$monthly->execute();

$monthlyStats =
$monthly->get_result();

/*
|--------------------------------------------------------------------------
| TOP SUPPLIERS
|--------------------------------------------------------------------------
*/
$topSuppliers = $conn->prepare("
SELECT

supplier_id,

COUNT(*) total_contracts,

SUM(contract_value)
total_value

FROM b2b_contracts

WHERE buyer_id=?
AND supplier_id IS NOT NULL

GROUP BY supplier_id

ORDER BY total_value DESC

LIMIT 10
");

$topSuppliers->bind_param(
    "i",
    $userId
);

$topSuppliers->execute();

$topSuppliers =
$topSuppliers->get_result();

/*
|--------------------------------------------------------------------------
| RECENT CONTRACTS
|--------------------------------------------------------------------------
*/
$contracts = $conn->prepare("
SELECT *

FROM b2b_contracts

WHERE buyer_id=?

ORDER BY id DESC

LIMIT 15
");

$contracts->bind_param(
    "i",
    $userId
);

$contracts->execute();

$contracts =
$contracts->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>Contract Analytics</title>

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

<style>

body{
background:#f5f7fb;
}

.card-box{
background:#fff;
border-radius:15px;
padding:20px;
margin-bottom:20px;
box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.metric{
font-size:30px;
font-weight:700;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Contract Analytics

</h2>

<!-- KPI CARDS -->

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card-box text-center">
<div class="metric">
<?= number_format($overview['total_contracts']) ?>
</div>
Total Contracts
</div>
</div>

<div class="col-md-3">
<div class="card-box text-center">
<div class="metric text-success">
<?= number_format($overview['active_contracts']) ?>
</div>
Active Contracts
</div>
</div>

<div class="col-md-3">
<div class="card-box text-center">
<div class="metric text-danger">
<?= number_format($overview['expired_contracts']) ?>
</div>
Expired Contracts
</div>
</div>

<div class="col-md-3">
<div class="card-box text-center">
<div class="metric text-warning">
<?= number_format($expiringSoon) ?>
</div>
Expiring Soon
</div>
</div>

</div>

<!-- VALUE ANALYTICS -->

<div class="row g-3 mb-4">

<div class="col-md-6">

<div class="card-box">

<h5>Total Portfolio Value</h5>

<h2 class="text-primary">

TZS
<?= number_format(
$overview['total_value'],
2
) ?>

</h2>

</div>

</div>

<div class="col-md-6">

<div class="card-box">

<h5>Average Contract Value</h5>

<h2 class="text-success">

TZS
<?= number_format(
$overview['avg_value'],
2
) ?>

</h2>

</div>

</div>

</div>

<!-- MONTHLY PERFORMANCE -->

<div class="card-box">

<h5>

Monthly Contract Trends

</h5>

<div class="table-responsive">

<table class="table table-striped">

<thead>

<tr>

<th>Month</th>
<th>Contracts</th>
<th>Total Value</th>

</tr>

</thead>

<tbody>

<?php while(
$row =
$monthlyStats->fetch_assoc()
): ?>

<tr>

<td>
<?= htmlspecialchars(
$row['month']
) ?>
</td>

<td>
<?= number_format(
$row['contracts']
) ?>
</td>

<td>
TZS
<?= number_format(
$row['value_total'],
2
) ?>
</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<!-- TOP SUPPLIERS -->

<div class="card-box">

<h5>

Top Suppliers

</h5>

<table class="table table-hover">

<thead>

<tr>

<th>Supplier ID</th>
<th>Contracts</th>
<th>Total Value</th>

</tr>

</thead>

<tbody>

<?php while(
$supplier =
$topSuppliers->fetch_assoc()
): ?>

<tr>

<td>

#<?= $supplier['supplier_id'] ?>

</td>

<td>

<?= number_format(
$supplier['total_contracts']
) ?>

</td>

<td>

TZS
<?= number_format(
$supplier['total_value'],
2
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

<!-- RECENT CONTRACTS -->

<div class="card-box">

<h5>

Recent Contracts

</h5>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Number</th>
<th>Title</th>
<th>Status</th>
<th>Value</th>
<th>End Date</th>
<th></th>

</tr>

</thead>

<tbody>

<?php while(
$contract =
$contracts->fetch_assoc()
): ?>

<tr>

<td>

<?= htmlspecialchars(
$contract['contract_number']
) ?>

</td>

<td>

<?= htmlspecialchars(
$contract['contract_title']
) ?>

</td>

<td>

<span class="badge bg-<?=
$contract['status']=='active'
?
'success'
:
(
$contract['status']=='expired'
?
'danger'
:
'secondary'
)
?>">

<?= ucfirst(
$contract['status']
) ?>

</span>

</td>

<td>

TZS
<?= number_format(
$contract['contract_value'],
2
) ?>

</td>

<td>

<?= $contract['end_date'] ?>

</td>

<td>

<a
href="contract-details.php?id=<?= $contract['id'] ?>"
class="btn btn-sm btn-primary">

View

</a>

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