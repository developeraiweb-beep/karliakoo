<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

/*
|--------------------------------------------------------------------------
| REVENUE
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT
COALESCE(SUM(amount),0) total
FROM payments
WHERE payment_status='paid'
AND DATE(created_at) BETWEEN ? AND ?
");

$stmt->bind_param("ss",$from,$to);
$stmt->execute();

$totalRevenue =
$stmt
->get_result()
->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| REFUNDS
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT
COALESCE(SUM(amount),0) total
FROM payments
WHERE payment_status='refunded'
AND DATE(created_at) BETWEEN ? AND ?
");

$stmt->bind_param("ss",$from,$to);
$stmt->execute();

$totalRefunds =
$stmt
->get_result()
->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| COMMISSIONS
|--------------------------------------------------------------------------
*/
$totalCommissions = 0;

if(
$conn->query(
"SHOW TABLES LIKE 'commissions'"
)->num_rows
){

$stmt = $conn->prepare("
SELECT
COALESCE(SUM(amount),0) total
FROM commissions
WHERE status='paid'
AND DATE(created_at)
BETWEEN ? AND ?
");

$stmt->bind_param(
"ss",
$from,
$to
);

$stmt->execute();

$totalCommissions =
$stmt
->get_result()
->fetch_assoc()['total'];

}

/*
|--------------------------------------------------------------------------
| WITHDRAWALS
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT
COALESCE(SUM(amount),0) total
FROM withdrawals
WHERE status='paid'
AND DATE(processed_at)
BETWEEN ? AND ?
");

$stmt->bind_param(
"ss",
$from,
$to
);

$stmt->execute();

$totalWithdrawals =
$stmt
->get_result()
->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| EXPENSES
|--------------------------------------------------------------------------
*/
$totalExpenses = 0;

if(
$conn->query(
"SHOW TABLES LIKE 'expenses'"
)->num_rows
){

$stmt = $conn->prepare("
SELECT
COALESCE(SUM(amount),0) total
FROM expenses
WHERE expense_date
BETWEEN ? AND ?
");

$stmt->bind_param(
"ss",
$from,
$to
);

$stmt->execute();

$totalExpenses =
$stmt
->get_result()
->fetch_assoc()['total'];

}

/*
|--------------------------------------------------------------------------
| TAX ESTIMATE
|--------------------------------------------------------------------------
*/
$taxRate = 18;

$estimatedTax =
(
($totalRevenue - $totalRefunds)
*
$taxRate
) / 100;

/*
|--------------------------------------------------------------------------
| PROFIT
|--------------------------------------------------------------------------
*/
$grossProfit =
$totalRevenue
-
$totalRefunds;

$operatingProfit =
$grossProfit
-
$totalCommissions
-
$totalWithdrawals
-
$totalExpenses;

$netProfit =
$operatingProfit
-
$estimatedTax;

/*
|--------------------------------------------------------------------------
| MONTHLY CHART
|--------------------------------------------------------------------------
*/
$labels = [];
$data = [];

$stmt = $conn->prepare("
SELECT

DATE_FORMAT(
created_at,
'%Y-%m'
) month,

SUM(amount)
revenue

FROM payments

WHERE payment_status='paid'

GROUP BY month

ORDER BY month ASC

LIMIT 12
");

$stmt->execute();

$result =
$stmt->get_result();

while(
$row =
$result->fetch_assoc()
){

$labels[] =
$row['month'];

$data[] =
$row['revenue'];

}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>

Profit & Loss Report

</title>

<meta
name="viewport"
content="width=device-width,initial-scale=1">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

body{
background:#f4f6f9;
}

.card-box{
background:#fff;
padding:20px;
border-radius:15px;
box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.metric{
font-size:28px;
font-weight:700;
}

.profit{
color:green;
}

.loss{
color:red;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Profit & Loss Statement

</h2>

<div class="card-box mb-4">

<form method="GET">

<div class="row">

<div class="col-md-3">

<label>From</label>

<input
type="date"
name="from"
value="<?= $from ?>"
class="form-control">

</div>

<div class="col-md-3">

<label>To</label>

<input
type="date"
name="to"
value="<?= $to ?>"
class="form-control">

</div>

<div class="col-md-2">

<label>&nbsp;</label>

<button
class="btn btn-primary w-100">

Generate

</button>

</div>

</div>

</form>

</div>

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($totalRevenue,2) ?>
</div>
Revenue
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($totalRefunds,2) ?>
</div>
Refunds
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($grossProfit,2) ?>
</div>
Gross Profit
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($estimatedTax,2) ?>
</div>
Tax Estimate
</div>
</div>

</div>

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($totalCommissions,2) ?>
</div>
Commissions
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($totalWithdrawals,2) ?>
</div>
Withdrawals
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($totalExpenses,2) ?>
</div>
Expenses
</div>
</div>

<div class="col-md-3">
<div class="card-box">

<div class="metric <?= $netProfit >=0 ? 'profit':'loss' ?>">

TZS <?= number_format($netProfit,2) ?>

</div>

Net Profit

</div>
</div>

</div>

<div class="card-box mb-4">

<h4>

Profit Calculation

</h4>

<table class="table">

<tr>
<th>Total Revenue</th>
<td>TZS <?= number_format($totalRevenue,2) ?></td>
</tr>

<tr>
<th>Less Refunds</th>
<td>- TZS <?= number_format($totalRefunds,2) ?></td>
</tr>

<tr>
<th>Gross Profit</th>
<td>TZS <?= number_format($grossProfit,2) ?></td>
</tr>

<tr>
<th>Less Commissions</th>
<td>- TZS <?= number_format($totalCommissions,2) ?></td>
</tr>

<tr>
<th>Less Withdrawals</th>
<td>- TZS <?= number_format($totalWithdrawals,2) ?></td>
</tr>

<tr>
<th>Less Expenses</th>
<td>- TZS <?= number_format($totalExpenses,2) ?></td>
</tr>

<tr>
<th>Operating Profit</th>
<td>TZS <?= number_format($operatingProfit,2) ?></td>
</tr>

<tr>
<th>Less Tax</th>
<td>- TZS <?= number_format($estimatedTax,2) ?></td>
</tr>

<tr class="table-success">

<th>Net Profit</th>

<td>

<strong>

TZS <?= number_format($netProfit,2) ?>

</strong>

</td>

</tr>

</table>

</div>

<div class="card-box">

<h4>

Revenue Trend

</h4>

<canvas id="profitChart"></canvas>

</div>

</div>

<script>

new Chart(
document.getElementById(
'profitChart'
),
{
type:'line',

data:{
labels:
<?= json_encode($labels) ?>,

datasets:[{

label:'Revenue',

data:
<?= json_encode($data) ?>,

borderWidth:3,
tension:.4

}]
}
});

</script>

</body>
</html>