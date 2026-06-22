<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['delivery','admin']);

$userId = $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| ACTIVE ROUTE
|--------------------------------------------------------------------------
*/
$activeRoute = $conn->prepare("
SELECT *
FROM delivery_routes
WHERE driver_id=?
AND status='active'
LIMIT 1
");

$activeRoute->bind_param(
    "i",
    $userId
);

$activeRoute->execute();

$activeRoute =
$activeRoute
->get_result()
->fetch_assoc();

/*
|--------------------------------------------------------------------------
| TOTAL DELIVERIES
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
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
WHEN delivery_status='pending'
THEN 1
ELSE 0
END
) pending

FROM route_orders ro

INNER JOIN delivery_routes dr
ON dr.id=ro.route_id

WHERE dr.driver_id=?
");

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$deliveryStats =
$stmt
->get_result()
->fetch_assoc();

/*
|--------------------------------------------------------------------------
| TODAY DELIVERIES
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT

COUNT(*) total

FROM route_orders ro

INNER JOIN delivery_routes dr
ON dr.id=ro.route_id

WHERE dr.driver_id=?

AND DATE(dr.created_at)=CURDATE()
");

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$todayDeliveries =
$stmt
->get_result()
->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| EARNINGS
|--------------------------------------------------------------------------
*/
$totalEarnings = 0;

if(
$conn->query(
"SHOW TABLES LIKE 'delivery_earnings'"
)->num_rows
){

$stmt = $conn->prepare("
SELECT

COALESCE(
SUM(amount),
0
) earnings

FROM delivery_earnings

WHERE driver_id=?
");

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$totalEarnings =
$stmt
->get_result()
->fetch_assoc()['earnings'];

}

/*
|--------------------------------------------------------------------------
| THIS MONTH EARNINGS
|--------------------------------------------------------------------------
*/
$monthlyEarnings = 0;

if(
$conn->query(
"SHOW TABLES LIKE 'delivery_earnings'"
)->num_rows
){

$stmt = $conn->prepare("
SELECT

COALESCE(
SUM(amount),
0
) earnings

FROM delivery_earnings

WHERE driver_id=?

AND MONTH(created_at)=MONTH(CURDATE())
AND YEAR(created_at)=YEAR(CURDATE())
");

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$monthlyEarnings =
$stmt
->get_result()
->fetch_assoc()['earnings'];

}

/*
|--------------------------------------------------------------------------
| COMPLETION RATE
|--------------------------------------------------------------------------
*/
$completionRate = 0;

if($deliveryStats['total'] > 0)
{
    $completionRate =
    round(
        (
            $deliveryStats['delivered']
            /
            $deliveryStats['total']
        ) * 100,
        1
    );
}

/*
|--------------------------------------------------------------------------
| RECENT ROUTES
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT *

FROM delivery_routes

WHERE driver_id=?

ORDER BY id DESC

LIMIT 10
");

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$routes =
$stmt->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width,initial-scale=1">

<title>
Delivery Dashboard
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
height:100%;
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

Delivery Dashboard

</h2>

<!-- ACTIVE ROUTE -->

<?php if($activeRoute): ?>

<div class="alert alert-primary">

<strong>

Active Route:

</strong>

<?= htmlspecialchars(
$activeRoute['route_code']
) ?>

-

<?= htmlspecialchars(
$activeRoute['route_name']
) ?>

</div>

<?php endif; ?>

<!-- KPI ROW -->

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="card-box">

<div class="metric">

<?= number_format(
$deliveryStats['total']
) ?>

</div>

Total Deliveries

</div>

</div>

<div class="col-md-3">

<div class="card-box">

<div class="metric">

<?= number_format(
$deliveryStats['delivered']
) ?>

</div>

Completed

</div>

</div>

<div class="col-md-3">

<div class="card-box">

<div class="metric">

<?= number_format(
$deliveryStats['pending']
) ?>

</div>

Pending

</div>

</div>

<div class="col-md-3">

<div class="card-box">

<div class="metric">

<?= $completionRate ?>%

</div>

Completion Rate

</div>

</div>

</div>

<!-- KPI ROW 2 -->

<div class="row g-3 mb-4">

<div class="col-md-4">

<div class="card-box">

<div class="metric">

<?= number_format(
$todayDeliveries
) ?>

</div>

Today's Deliveries

</div>

</div>

<div class="col-md-4">

<div class="card-box">

<div class="metric">

TZS

<?= number_format(
$monthlyEarnings,
2
) ?>

</div>

Monthly Earnings

</div>

</div>

<div class="col-md-4">

<div class="card-box">

<div class="metric">

TZS

<?= number_format(
$totalEarnings,
2
) ?>

</div>

Lifetime Earnings

</div>

</div>

</div>

<!-- QUICK ACTIONS -->

<div class="card-box mb-4">

<h5 class="mb-3">

Quick Actions

</h5>

<a
href="routes.php"
class="btn btn-primary">

Routes

</a>

<a
href="orders.php"
class="btn btn-success">

Orders

</a>

<a
href="tracking.php"
class="btn btn-warning">

Tracking

</a>

<a
href="earnings.php"
class="btn btn-info">

Earnings

</a>

</div>

<!-- RECENT ROUTES -->

<div class="card-box">

<h4>

Recent Routes

</h4>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Route</th>
<th>Status</th>
<th>Created</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php while(
$route =
$routes->fetch_assoc()
): ?>

<tr>

<td>

<strong>

<?= htmlspecialchars(
$route['route_code']
) ?>

</strong>

<br>

<?= htmlspecialchars(
$route['route_name']
) ?>

</td>

<td>

<?php

$badge='secondary';

if(
$route['status']=='planned'
)
$badge='warning';

if(
$route['status']=='active'
)
$badge='primary';

if(
$route['status']=='completed'
)
$badge='success';

?>

<span
class="badge bg-<?= $badge ?>">

<?= ucfirst(
$route['status']
) ?>

</span>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$route['created_at']
)
) ?>

</td>

<td>

<a
href="route-details.php?id=<?= $route['id'] ?>"
class="btn btn-sm btn-dark">

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