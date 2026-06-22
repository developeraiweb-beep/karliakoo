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
")->fetch_assoc()['total'];

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
")->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| COMMISSIONS
|--------------------------------------------------------------------------
*/
$commissions = 0;

if(
$conn->query(
"SHOW TABLES LIKE 'commissions'"
)->num_rows
){

$commissions =
$conn->query("
SELECT
COALESCE(SUM(amount),0) total
FROM commissions
WHERE status='paid'
")->fetch_assoc()['total'];

}

/*
|--------------------------------------------------------------------------
| WITHDRAWALS
|--------------------------------------------------------------------------
*/
$withdrawals =
$conn->query("
SELECT
COALESCE(SUM(amount),0) total
FROM withdrawals
WHERE status='paid'
")->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| EXPENSES
|--------------------------------------------------------------------------
*/
$expenses = 0;

if(
$conn->query(
"SHOW TABLES LIKE 'expenses'"
)->num_rows
){

$expenses =
$conn->query("
SELECT
COALESCE(SUM(amount),0) total
FROM expenses
")->fetch_assoc()['total'];

}

/*
|--------------------------------------------------------------------------
| TAX LIABILITY
|--------------------------------------------------------------------------
*/
$taxRate = 18;

$taxLiability =
(
($revenue - $refunds)
*
$taxRate
) / 100;

/*
|--------------------------------------------------------------------------
| PROFIT
|--------------------------------------------------------------------------
*/
$grossProfit =
$revenue - $refunds;

$operatingProfit =
$grossProfit
-
$commissions
-
$withdrawals
-
$expenses;

$netProfit =
$operatingProfit
-
$taxLiability;

/*
|--------------------------------------------------------------------------
| PENDING LIABILITIES
|--------------------------------------------------------------------------
*/
$pendingWithdrawals =
$conn->query("
SELECT
COALESCE(SUM(amount),0) total
FROM withdrawals
WHERE status IN(
'pending',
'approved'
)
")->fetch_assoc()['total'];

$pendingCommissions = 0;

if(
$conn->query(
"SHOW TABLES LIKE 'commissions'"
)->num_rows
){

$pendingCommissions =
$conn->query("
SELECT
COALESCE(SUM(amount),0) total
FROM commissions
WHERE status='pending'
")->fetch_assoc()['total'];

}

/*
|--------------------------------------------------------------------------
| BUSINESS HEALTH SCORE
|--------------------------------------------------------------------------
*/
$healthScore = 100;

if($refunds > ($revenue * 0.10))
{
    $healthScore -= 15;
}

if($expenses > ($revenue * 0.20))
{
    $healthScore -= 15;
}

if($pendingWithdrawals > ($revenue * 0.15))
{
    $healthScore -= 10;
}

if($netProfit < 0)
{
    $healthScore -= 30;
}

$healthScore =
max(
0,
$healthScore
);

/*
|--------------------------------------------------------------------------
| MONTHLY REVENUE TREND
|--------------------------------------------------------------------------
*/
$labels = [];
$data = [];

$result = $conn->query("
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

while(
$row=
$result->fetch_assoc()
){

$labels[] =
$row['month'];

$data[] =
$row['revenue'];

}

/*
|--------------------------------------------------------------------------
| TOP SHOPS
|--------------------------------------------------------------------------
*/
$topShops = [];

if(
$conn->query(
"SHOW TABLES LIKE 'shops'"
)->num_rows
){

$result =
$conn->query("
SELECT

shop_name,
followers,
views

FROM shops

ORDER BY followers DESC

LIMIT 10
");

while(
$row=
$result->fetch_assoc()
){
$topShops[]=$row;
}

}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width,initial-scale=1">

<title>

Accounting Dashboard

</title>

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

.health{
font-size:42px;
font-weight:700;
color:green;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Accounting Dashboard

</h2>

<!-- KPI ROW 1 -->

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($revenue,2) ?>
</div>
Revenue
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($refunds,2) ?>
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
TZS <?= number_format($netProfit,2) ?>
</div>
Net Profit
</div>
</div>

</div>

<!-- KPI ROW 2 -->

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($commissions,2) ?>
</div>
Commissions
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($withdrawals,2) ?>
</div>
Withdrawals
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($expenses,2) ?>
</div>
Expenses
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($taxLiability,2) ?>
</div>
Tax Liability
</div>
</div>

</div>

<!-- KPI ROW 3 -->

<div class="row g-3 mb-4">

<div class="col-md-4">

<div class="card-box text-center">

Business Health

<hr>

<div class="health">

<?= $healthScore ?>%

</div>

</div>

</div>

<div class="col-md-4">

<div class="card-box">

<div class="metric">

TZS <?= number_format($pendingWithdrawals,2) ?>

</div>

Pending Withdrawals

</div>

</div>

<div class="col-md-4">

<div class="card-box">

<div class="metric">

TZS <?= number_format($pendingCommissions,2) ?>

</div>

Pending Commissions

</div>

</div>

</div>

<!-- REVENUE CHART -->

<div class="card-box mb-4">

<h4>

Revenue Trend

</h4>

<canvas id="revenueChart"></canvas>

</div>

<!-- FINANCIAL SUMMARY -->

<div class="card-box mb-4">

<h4>

Financial Summary

</h4>

<table class="table">

<tr>
<th>Total Revenue</th>
<td>TZS <?= number_format($revenue,2) ?></td>
</tr>

<tr>
<th>Total Refunds</th>
<td>TZS <?= number_format($refunds,2) ?></td>
</tr>

<tr>
<th>Total Commissions</th>
<td>TZS <?= number_format($commissions,2) ?></td>
</tr>

<tr>
<th>Total Withdrawals</th>
<td>TZS <?= number_format($withdrawals,2) ?></td>
</tr>

<tr>
<th>Total Expenses</th>
<td>TZS <?= number_format($expenses,2) ?></td>
</tr>

<tr>
<th>Estimated Taxes</th>
<td>TZS <?= number_format($taxLiability,2) ?></td>
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

<!-- TOP SHOPS -->

<div class="card-box">

<h4>

Top Shops

</h4>

<table class="table table-striped">

<thead>

<tr>

<th>Shop</th>
<th>Followers</th>
<th>Views</th>

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
$shop['followers']
) ?>

</td>

<td>

<?= number_format(
$shop['views']
) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

<script>

new Chart(
document.getElementById(
'revenueChart'
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