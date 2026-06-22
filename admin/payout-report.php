<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

/*
|--------------------------------------------------------------------------
| CSV EXPORT
|--------------------------------------------------------------------------
*/
if(isset($_GET['export']))
{
    header('Content-Type:text/csv');
    header('Content-Disposition:attachment; filename=payout-report.csv');

    $output = fopen('php://output','w');

    fputcsv($output,[
        'ID',
        'User',
        'Amount',
        'Method',
        'Status',
        'Date'
    ]);

    $stmt = $conn->prepare("
        SELECT

        w.id,
        u.full_name,
        w.amount,
        w.method,
        w.status,
        w.requested_at

        FROM withdrawals w

        LEFT JOIN users u
        ON u.id=w.user_id

        WHERE DATE(w.requested_at)
        BETWEEN ? AND ?
    ");

    $stmt->bind_param(
        "ss",
        $from,
        $to
    );

    $stmt->execute();

    $result =
    $stmt->get_result();

    while($row = $result->fetch_assoc())
    {
        fputcsv($output,$row);
    }

    fclose($output);
    exit;
}

/*
|--------------------------------------------------------------------------
| TOTAL PAID
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT

COALESCE(
SUM(amount),
0
) total

FROM withdrawals

WHERE status='paid'

AND DATE(requested_at)
BETWEEN ? AND ?
");

$stmt->bind_param(
    "ss",
    $from,
    $to
);

$stmt->execute();

$totalPaid =
$stmt
->get_result()
->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| PENDING PAYOUTS
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT

COALESCE(
SUM(amount),
0
) total

FROM withdrawals

WHERE status IN(
'pending',
'approved'
)

AND DATE(requested_at)
BETWEEN ? AND ?
");

$stmt->bind_param(
    "ss",
    $from,
    $to
);

$stmt->execute();

$pendingLiability =
$stmt
->get_result()
->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| COMMISSIONS PAID
|--------------------------------------------------------------------------
*/
$commissionPaid = 0;

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

$commissionPaid =
$stmt
->get_result()
->fetch_assoc()['total'];

}

/*
|--------------------------------------------------------------------------
| AGENT PAYOUTS
|--------------------------------------------------------------------------
*/
$agentPayouts = 0;

$stmt = $conn->prepare("
SELECT

COALESCE(
SUM(w.amount),
0
) total

FROM withdrawals w

INNER JOIN users u
ON u.id=w.user_id

WHERE u.role='agent'

AND w.status='paid'

AND DATE(w.requested_at)
BETWEEN ? AND ?
");

$stmt->bind_param(
    "ss",
    $from,
    $to
);

$stmt->execute();

$agentPayouts =
$stmt
->get_result()
->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| SELLER PAYOUTS
|--------------------------------------------------------------------------
*/
$sellerPayouts = 0;

$stmt = $conn->prepare("
SELECT

COALESCE(
SUM(w.amount),
0
) total

FROM withdrawals w

INNER JOIN users u
ON u.id=w.user_id

WHERE u.role='seller'

AND w.status='paid'

AND DATE(w.requested_at)
BETWEEN ? AND ?
");

$stmt->bind_param(
    "ss",
    $from,
    $to
);

$stmt->execute();

$sellerPayouts =
$stmt
->get_result()
->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| TOP PAYOUT RECIPIENTS
|--------------------------------------------------------------------------
*/
$topRecipients = [];

$stmt = $conn->prepare("
SELECT

u.full_name,
u.role,

COUNT(w.id)
requests,

SUM(w.amount)
amount

FROM withdrawals w

INNER JOIN users u
ON u.id=w.user_id

WHERE w.status='paid'

GROUP BY w.user_id

ORDER BY amount DESC

LIMIT 10
");

$stmt->execute();

$result =
$stmt->get_result();

while(
$row =
$result->fetch_assoc()
){
    $topRecipients[]=$row;
}

/*
|--------------------------------------------------------------------------
| PAYOUT TREND
|--------------------------------------------------------------------------
*/
$labels = [];
$data = [];

$stmt = $conn->prepare("
SELECT

DATE(requested_at)
report_date,

SUM(amount)
total

FROM withdrawals

WHERE status='paid'

AND DATE(requested_at)
BETWEEN ? AND ?

GROUP BY DATE(requested_at)

ORDER BY DATE(requested_at)
");

$stmt->bind_param(
    "ss",
    $from,
    $to
);

$stmt->execute();

$result =
$stmt->get_result();

while(
$row=
$result->fetch_assoc()
){

$labels[] =
$row['report_date'];

$data[] =
$row['total'];

}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>

Payout Report

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

Payout Report

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
TZS <?= number_format($totalPaid,2) ?>
</div>
Paid Payouts
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($pendingLiability,2) ?>
</div>
Pending Liability
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($sellerPayouts,2) ?>
</div>
Seller Payouts
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($agentPayouts,2) ?>
</div>
Agent Payouts
</div>
</div>

</div>

<div class="row g-3 mb-4">

<div class="col-md-4">
<div class="card-box">
<div class="metric">
TZS <?= number_format($commissionPaid,2) ?>
</div>
Commission Paid
</div>
</div>

<div class="col-md-4">
<div class="card-box">
<div class="metric">
<?= count($topRecipients) ?>
</div>
Top Earners
</div>
</div>

<div class="col-md-4">
<div class="card-box">
<div class="metric">
TZS <?= number_format($totalPaid + $commissionPaid,2) ?>
</div>
Total Cash Outflow
</div>
</div>

</div>

<div class="card-box mb-4">

<h4>

Payout Trend

</h4>

<canvas id="payoutChart"></canvas>

</div>

<div class="card-box">

<h4>

Top Payout Recipients

</h4>

<table class="table table-striped">

<thead>

<tr>

<th>Name</th>
<th>Role</th>
<th>Requests</th>
<th>Total Paid</th>

</tr>

</thead>

<tbody>

<?php foreach($topRecipients as $user): ?>

<tr>

<td>

<?= htmlspecialchars(
$user['full_name']
) ?>

</td>

<td>

<?= ucfirst(
$user['role']
) ?>

</td>

<td>

<?= number_format(
$user['requests']
) ?>

</td>

<td>

TZS

<?= number_format(
$user['amount'],
2
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
'payoutChart'
),
{
type:'bar',

data:{
labels:
<?= json_encode($labels) ?>,

datasets:[{
label:'Paid Payouts',

data:
<?= json_encode($data) ?>,

borderWidth:2
}]
}
});

</script>

</body>
</html>