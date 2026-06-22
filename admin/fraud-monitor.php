<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

/*
|--------------------------------------------------------------------------
| HANDLE ALERT STATUS
|--------------------------------------------------------------------------
*/
if (
    isset($_GET['action']) &&
    isset($_GET['id'])
) {

    $id = (int)$_GET['id'];

    $allowed = [
        'investigating',
        'resolved',
        'dismissed'
    ];

    if (
        in_array(
            $_GET['action'],
            $allowed
        )
    ) {

        $status = $_GET['action'];

        $stmt = $conn->prepare("
            UPDATE fraud_alerts
            SET status=?
            WHERE id=?
        ");

        $stmt->bind_param(
            "si",
            $status,
            $id
        );

        $stmt->execute();
    }

    header(
        "Location: fraud-monitor.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| FRAUD SUMMARY
|--------------------------------------------------------------------------
*/
$summary = [

'total' => 0,
'open' => 0,
'investigating' => 0,
'critical' => 0

];

if (
$conn->query(
"SHOW TABLES LIKE 'fraud_alerts'"
)->num_rows
) {

    $summary = $conn->query("
        SELECT

        COUNT(*) total,

        SUM(status='open')
        open_alerts,

        SUM(
            status='investigating'
        ) investigating_alerts,

        SUM(
            severity='critical'
        ) critical_alerts

        FROM fraud_alerts
    ")->fetch_assoc();
}

/*
|--------------------------------------------------------------------------
| FRAUD ALERTS
|--------------------------------------------------------------------------
*/
$alerts = [];

if (
$conn->query(
"SHOW TABLES LIKE 'fraud_alerts'"
)->num_rows
) {

    $alerts = $conn->query("
        SELECT

        f.*,

        u.full_name,
        u.email

        FROM fraud_alerts f

        LEFT JOIN users u
        ON u.id=f.user_id

        ORDER BY

        risk_score DESC,
        f.id DESC

        LIMIT 200
    ");
}

/*
|--------------------------------------------------------------------------
| FAILED LOGIN IPS
|--------------------------------------------------------------------------
*/
$failedIps = [];

if (
$conn->query(
"SHOW TABLES LIKE 'login_attempts'"
)->num_rows
) {

    $failedIps = $conn->query("
        SELECT

        ip_address,

        COUNT(*) attempts

        FROM login_attempts

        WHERE success=0

        AND created_at >=
        DATE_SUB(
            NOW(),
            INTERVAL 7 DAY
        )

        GROUP BY ip_address

        HAVING attempts >= 5

        ORDER BY attempts DESC

        LIMIT 20
    ");
}

/*
|--------------------------------------------------------------------------
| LARGE ORDERS
|--------------------------------------------------------------------------
*/
$largeOrders = [];

$orderCheck =
$conn->query(
"SHOW TABLES LIKE 'b2b_orders'"
);

if ($orderCheck->num_rows) {

    $largeOrders = $conn->query("
        SELECT

        order_number,
        buyer_id,
        total_amount,
        created_at

        FROM b2b_orders

        WHERE total_amount >= 1000000

        ORDER BY total_amount DESC

        LIMIT 20
    ");
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

Fraud Monitor

</title>

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
border-radius:12px;
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

Fraud Detection Center

</h2>

<!-- KPIs -->

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="card-box shadow-sm">

<div class="metric">

<?= number_format(
$summary['total']
?? 0
) ?>

</div>

Fraud Alerts

</div>

</div>

<div class="col-md-3">

<div class="card-box shadow-sm">

<div class="metric">

<?= number_format(
$summary['open_alerts']
?? 0
) ?>

</div>

Open Cases

</div>

</div>

<div class="col-md-3">

<div class="card-box shadow-sm">

<div class="metric">

<?= number_format(
$summary['investigating_alerts']
?? 0
) ?>

</div>

Investigating

</div>

</div>

<div class="col-md-3">

<div class="card-box shadow-sm">

<div class="metric">

<?= number_format(
$summary['critical_alerts']
?? 0
) ?>

</div>

Critical

</div>

</div>

</div>

<!-- FRAUD ALERTS -->

<div class="card-box shadow-sm mb-4">

<h4>

Fraud Investigation Queue

</h4>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>ID</th>
<th>User</th>
<th>Category</th>
<th>Risk</th>
<th>Severity</th>
<th>Status</th>
<th>Actions</th>

</tr>

</thead>

<tbody>

<?php

if($alerts):

while(
$row =
$alerts->fetch_assoc()
):

?>

<tr>

<td>
<?= $row['id'] ?>
</td>

<td>

<?= htmlspecialchars(
$row['full_name']
?? 'Unknown'
) ?>

</td>

<td>

<?= htmlspecialchars(
$row['category']
) ?>

</td>

<td>

<?= $row['risk_score'] ?>/100

</td>

<td>

<?php

$color = match(
$row['severity']
){

'critical'=>'danger',
'high'=>'warning',
'medium'=>'info',
default=>'secondary'

};

?>

<span
class="badge bg-<?= $color ?>">

<?= ucfirst(
$row['severity']
) ?>

</span>

</td>

<td>

<?= ucfirst(
$row['status']
) ?>

</td>

<td>

<a
href="?action=investigating&id=<?= $row['id'] ?>"
class="btn btn-sm btn-warning">

Investigate

</a>

<a
href="?action=resolved&id=<?= $row['id'] ?>"
class="btn btn-sm btn-success">

Resolve

</a>

</td>

</tr>

<?php

endwhile;
endif;

?>

</tbody>

</table>

</div>

</div>

<!-- FAILED IPS -->

<div class="card-box shadow-sm mb-4">

<h4>

Suspicious Login Activity

</h4>

<table class="table table-hover">

<thead>

<tr>

<th>IP Address</th>
<th>Failed Attempts</th>

</tr>

</thead>

<tbody>

<?php

if($failedIps):

while(
$ip =
$failedIps->fetch_assoc()
):

?>

<tr>

<td>

<?= htmlspecialchars(
$ip['ip_address']
) ?>

</td>

<td>

<?= number_format(
$ip['attempts']
) ?>

</td>

</tr>

<?php

endwhile;
endif;

?>

</tbody>

</table>

</div>

<!-- LARGE ORDERS -->

<div class="card-box shadow-sm">

<h4>

Large Transaction Monitor

</h4>

<table class="table table-hover">

<thead>

<tr>

<th>Order</th>
<th>Buyer</th>
<th>Amount</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php

if($largeOrders):

while(
$order =
$largeOrders->fetch_assoc()
):

?>

<tr>

<td>

<?= htmlspecialchars(
$order['order_number']
) ?>

</td>

<td>

#<?= $order['buyer_id'] ?>

</td>

<td>

TZS

<?= number_format(
$order['total_amount'],
2
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

<?php

endwhile;
endif;

?>

</tbody>

</table>

</div>

</div>

</body>
</html>