<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['delivery','admin']);

$routeId = (int)($_GET['id'] ?? 0);

if($routeId <= 0)
{
    die("Invalid route.");
}

/*
|--------------------------------------------------------------------------
| UPDATE DELIVERY STATUS
|--------------------------------------------------------------------------
*/
if(
    isset($_POST['update_status'])
)
{
    $routeOrderId = (int)$_POST['route_order_id'];
    $status = trim($_POST['status']);

    $allowed = [
        'pending',
        'out_for_delivery',
        'delivered',
        'failed'
    ];

    if(in_array($status,$allowed))
    {
        $stmt = $conn->prepare("
            UPDATE route_orders
            SET

            delivery_status=?,

            delivered_at=
            CASE
                WHEN ?='delivered'
                THEN NOW()
                ELSE delivered_at
            END

            WHERE id=?
        ");

        $stmt->bind_param(
            "ssi",
            $status,
            $status,
            $routeOrderId
        );

        $stmt->execute();
    }

    header(
        "Location: route-details.php?id=".$routeId
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| ROUTE DETAILS
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT *

FROM delivery_routes

WHERE id=?

LIMIT 1
");

$stmt->bind_param(
    "i",
    $routeId
);

$stmt->execute();

$route =
$stmt
->get_result()
->fetch_assoc();

if(!$route)
{
    die("Route not found.");
}

/*
|--------------------------------------------------------------------------
| ROUTE ORDERS
|--------------------------------------------------------------------------
*/
$ordersStmt = $conn->prepare("
SELECT

ro.*,

o.order_number,
o.total_amount,

u.full_name,
u.phone,
u.email

FROM route_orders ro

LEFT JOIN orders o
ON o.id=ro.order_id

LEFT JOIN users u
ON u.id=o.user_id

WHERE ro.route_id=?

ORDER BY ro.stop_number ASC
");

$ordersStmt->bind_param(
    "i",
    $routeId
);

$ordersStmt->execute();

$routeOrders =
$ordersStmt
->get_result();

/*
|--------------------------------------------------------------------------
| ROUTE STATISTICS
|--------------------------------------------------------------------------
*/
$statsStmt = $conn->prepare("
SELECT

COUNT(*) total,

SUM(
CASE
WHEN delivery_status='delivered'
THEN 1
ELSE 0
END
) delivered,

SUM(
CASE
WHEN delivery_status='failed'
THEN 1
ELSE 0
END
) failed,

SUM(
CASE
WHEN delivery_status='pending'
THEN 1
ELSE 0
END
) pending

FROM route_orders

WHERE route_id=?
");

$statsStmt->bind_param(
    "i",
    $routeId
);

$statsStmt->execute();

$stats =
$statsStmt
->get_result()
->fetch_assoc();

$progress = 0;

if($stats['total'] > 0)
{
    $progress =
    round(
        (
            $stats['delivered']
            /
            $stats['total']
        ) * 100
    );
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Route Details

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
background:#f5f7fb;
}

.card-box{
background:#fff;
padding:20px;
border-radius:15px;
box-shadow:0 2px 10px rgba(0,0,0,.08);
margin-bottom:20px;
}

.metric{
font-size:26px;
font-weight:700;
}

.progress{
height:25px;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Route Details

</h2>

<!-- ROUTE INFO -->

<div class="card-box">

<div class="row">

<div class="col-md-4">

<strong>

Route Code

</strong>

<br>

<?= htmlspecialchars(
$route['route_code']
) ?>

</div>

<div class="col-md-4">

<strong>

Route Name

</strong>

<br>

<?= htmlspecialchars(
$route['route_name']
) ?>

</div>

<div class="col-md-4">

<strong>

Status

</strong>

<br>

<span class="badge bg-primary">

<?= ucfirst(
$route['status']
) ?>

</span>

</div>

</div>

</div>

<!-- STATS -->

<div class="row">

<div class="col-md-3">

<div class="card-box">

<div class="metric">

<?= $stats['total'] ?>

</div>

Stops

</div>

</div>

<div class="col-md-3">

<div class="card-box">

<div class="metric text-success">

<?= $stats['delivered'] ?>

</div>

Delivered

</div>

</div>

<div class="col-md-3">

<div class="card-box">

<div class="metric text-danger">

<?= $stats['failed'] ?>

</div>

Failed

</div>

</div>

<div class="col-md-3">

<div class="card-box">

<div class="metric">

<?= $progress ?>%

</div>

Progress

</div>

</div>

</div>

<!-- PROGRESS -->

<div class="card-box">

<h5>

Route Completion

</h5>

<div class="progress">

<div
class="progress-bar bg-success"
style="width:<?= $progress ?>%">

<?= $progress ?>%

</div>

</div>

</div>

<!-- ORDERS -->

<div class="card-box">

<h4>

Delivery Stops

</h4>

<div class="table-responsive">

<table class="table table-bordered align-middle">

<thead>

<tr>

<th>Stop</th>
<th>Order</th>
<th>Customer</th>
<th>Amount</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php while(
$order =
$routeOrders->fetch_assoc()
): ?>

<tr>

<td>

#<?= $order['stop_number'] ?>

</td>

<td>

<?= htmlspecialchars(
$order['order_number']
) ?>

</td>

<td>

<strong>

<?= htmlspecialchars(
$order['full_name']
)
?>

</strong>

<br>

<?= htmlspecialchars(
$order['phone']
)
?>

<br>

<?= htmlspecialchars(
$order['email']
)
?>

</td>

<td>

TZS

<?= number_format(
$order['total_amount'],
2
)
?>

</td>

<td>

<?php

$badge='secondary';

if(
$order['delivery_status']
=='pending'
)
$badge='warning';

if(
$order['delivery_status']
=='out_for_delivery'
)
$badge='primary';

if(
$order['delivery_status']
=='delivered'
)
$badge='success';

if(
$order['delivery_status']
=='failed'
)
$badge='danger';

?>

<span
class="badge bg-<?= $badge ?>">

<?= ucfirst(
str_replace(
'_',
' ',
$order['delivery_status']
)
)
?>

</span>

</td>

<td>

<form
method="POST"
class="d-flex gap-2">

<input
type="hidden"
name="route_order_id"
value="<?= $order['id'] ?>">

<select
name="status"
class="form-select form-select-sm">

<option value="pending">
Pending
</option>

<option value="out_for_delivery">
Out For Delivery
</option>

<option value="delivered">
Delivered
</option>

<option value="failed">
Failed
</option>

</select>

<button
type="submit"
name="update_status"
class="btn btn-sm btn-success">

Update

</button>

</form>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<!-- QUICK ACTIONS -->

<div class="card-box">

<a
href="routes.php"
class="btn btn-secondary">

Back To Routes

</a>

<a
href="orders.php"
class="btn btn-primary">

Orders

</a>

<a
href="tracking.php?route=<?= $routeId ?>"
class="btn btn-success">

Live Tracking

</a>

<a
href="delivery-proof.php?route=<?= $routeId ?>"
class="btn btn-warning">

Proof Of Delivery

</a>

</div>

</div>

</body>
</html>