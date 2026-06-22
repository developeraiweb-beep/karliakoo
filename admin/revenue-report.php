<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

/*
|--------------------------------------------------------------------------
| EXPORT CSV
|--------------------------------------------------------------------------
*/
if(isset($_GET['export']))
{

    header('Content-Type:text/csv');
    header('Content-Disposition:attachment; filename=revenue-report.csv');

    $output = fopen('php://output','w');

    fputcsv($output,[
        'Date',
        'Revenue'
    ]);

    $csv = $conn->prepare("
        SELECT

        DATE(created_at) report_date,
        SUM(amount) revenue

        FROM payments

        WHERE payment_status='paid'
        AND DATE(created_at)
        BETWEEN ? AND ?

        GROUP BY DATE(created_at)

        ORDER BY DATE(created_at)
    ");

    $csv->bind_param(
        "ss",
        $from,
        $to
    );

    $csv->execute();

    $result =
    $csv->get_result();

    while($row = $result->fetch_assoc())
    {
        fputcsv($output,$row);
    }

    fclose($output);
    exit;
}

/*
|--------------------------------------------------------------------------
| REVENUE SUMMARY
|--------------------------------------------------------------------------
*/
$summary = $conn->prepare("
SELECT

COALESCE(
SUM(amount),
0
) total_revenue

FROM payments

WHERE payment_status='paid'
AND DATE(created_at)
BETWEEN ? AND ?
");

$summary->bind_param(
    "ss",
    $from,
    $to
);

$summary->execute();

$summary =
$summary
->get_result()
->fetch_assoc();

/*
|--------------------------------------------------------------------------
| REFUNDS
|--------------------------------------------------------------------------
*/
$refunds = $conn->prepare("
SELECT

COALESCE(
SUM(amount),
0
) total_refunds

FROM payments

WHERE payment_status='refunded'
AND DATE(created_at)
BETWEEN ? AND ?
");

$refunds->bind_param(
    "ss",
    $from,
    $to
);

$refunds->execute();

$refunds =
$refunds
->get_result()
->fetch_assoc();

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

    COALESCE(
    SUM(amount),
    0
    ) total

    FROM commissions

    WHERE status IN(
    'earned',
    'paid'
    )

    AND DATE(created_at)
    BETWEEN ? AND ?
    ");

    $stmt->bind_param(
        "ss",
        $from,
        $to
    );

    $stmt->execute();

    $commission =
    $stmt
    ->get_result()
    ->fetch_assoc();

    $totalCommissions =
    $commission['total'];
}

/*
|--------------------------------------------------------------------------
| TOP SELLERS
|--------------------------------------------------------------------------
*/
$topSellers = [];

if(
$conn->query(
"SHOW TABLES LIKE 'orders'"
)->num_rows
){

    $sellerQuery = $conn->query("
    SELECT

    u.full_name,

    COUNT(o.id)
    total_orders,

    SUM(o.total_amount)
    revenue

    FROM users u

    INNER JOIN orders o
    ON o.seller_id=u.id

    GROUP BY u.id

    ORDER BY revenue DESC

    LIMIT 10
    ");

    while($row=
    $sellerQuery->fetch_assoc())
    {
        $topSellers[]=$row;
    }
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

    $shopQuery = $conn->query("
    SELECT

    shop_name,

    views,
    followers

    FROM shops

    ORDER BY followers DESC

    LIMIT 10
    ");

    while($row=
    $shopQuery->fetch_assoc())
    {
        $topShops[]=$row;
    }
}

/*
|--------------------------------------------------------------------------
| DAILY REVENUE
|--------------------------------------------------------------------------
*/
$chartLabels = [];
$chartData = [];

$daily = $conn->prepare("
SELECT

DATE(created_at)
report_date,

SUM(amount)
revenue

FROM payments

WHERE payment_status='paid'

AND DATE(created_at)
BETWEEN ? AND ?

GROUP BY DATE(created_at)

ORDER BY DATE(created_at)
");

$daily->bind_param(
    "ss",
    $from,
    $to
);

$daily->execute();

$result =
$daily->get_result();

while($row=
$result->fetch_assoc())
{
    $chartLabels[] =
    $row['report_date'];

    $chartData[] =
    $row['revenue'];
}

$netProfit =
(
$summary['total_revenue']
-
$refunds['total_refunds']
-
$totalCommissions
);

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>
Revenue Report
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
box-shadow:0 2px 10px rgba(0,0,0,.08);
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

Revenue Report

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

<div class="col-md-2">

<label>&nbsp;</label>

<a
href="?from=<?= $from ?>&to=<?= $to ?>&export=1"
class="btn btn-success w-100">

Export CSV

</a>

</div>

</div>

</form>

</div>

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($summary['total_revenue'],2) ?>
</div>
Revenue
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($refunds['total_refunds'],2) ?>
</div>
Refunds
</div>
</div>

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
TZS <?= number_format($netProfit,2) ?>
</div>
Net Profit
</div>
</div>

</div>

<div class="card-box mb-4">

<h4>

Revenue Trend

</h4>

<canvas id="revenueChart"></canvas>

</div>

<div class="row">

<div class="col-lg-6">

<div class="card-box">

<h4>Top Sellers</h4>

<table class="table">

<thead>

<tr>
<th>Seller</th>
<th>Orders</th>
<th>Revenue</th>
</tr>

</thead>

<tbody>

<?php foreach($topSellers as $seller): ?>

<tr>

<td>
<?= htmlspecialchars($seller['full_name']) ?>
</td>

<td>
<?= number_format($seller['total_orders']) ?>
</td>

<td>
TZS <?= number_format($seller['revenue'],2) ?>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

<div class="col-lg-6">

<div class="card-box">

<h4>Top Shops</h4>

<table class="table">

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
<?= htmlspecialchars($shop['shop_name']) ?>
</td>

<td>
<?= number_format($shop['followers']) ?>
</td>

<td>
<?= number_format($shop['views']) ?>
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

new Chart(
document.getElementById(
'revenueChart'
),
{
type:'line',

data:{
labels:
<?= json_encode($chartLabels) ?>,

datasets:[{
label:'Revenue',

data:
<?= json_encode($chartData) ?>,

borderWidth:3,
tension:.4
}]
}
});

</script>

</body>
</html>