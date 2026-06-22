<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['delivery','admin']);

$userId = $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| START ROUTE
|--------------------------------------------------------------------------
*/
if(isset($_GET['start']))
{
    $routeId = (int)$_GET['start'];

    $stmt = $conn->prepare("
        UPDATE delivery_routes
        SET
        status='active',
        start_time=NOW()
        WHERE id=?
    ");

    $stmt->bind_param("i",$routeId);
    $stmt->execute();

    header("Location: routes.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| COMPLETE ROUTE
|--------------------------------------------------------------------------
*/
if(isset($_GET['complete']))
{
    $routeId = (int)$_GET['complete'];

    $stmt = $conn->prepare("
        UPDATE delivery_routes
        SET
        status='completed',
        completed_at=NOW()
        WHERE id=?
    ");

    $stmt->bind_param("i",$routeId);
    $stmt->execute();

    header("Location: routes.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| ROUTES
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT

r.*,

COUNT(ro.id) total_orders,

SUM(
CASE
WHEN ro.delivery_status='delivered'
THEN 1
ELSE 0
END
) delivered_count

FROM delivery_routes r

LEFT JOIN route_orders ro
ON ro.route_id=r.id

WHERE r.driver_id=?

GROUP BY r.id

ORDER BY r.id DESC
");

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$routes =
$stmt->get_result();

/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/
$stats = $conn->query("
SELECT

COUNT(*) total_routes,

SUM(
CASE
WHEN status='active'
THEN 1
ELSE 0
END
) active_routes,

SUM(
CASE
WHEN status='completed'
THEN 1
ELSE 0
END
) completed_routes

FROM delivery_routes
")->fetch_assoc();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width,initial-scale=1">

<title>

Delivery Routes

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

Delivery Routes

</h2>

<div class="row g-3 mb-4">

<div class="col-md-4">
<div class="card-box">
<div class="metric">
<?= number_format($stats['total_routes']) ?>
</div>
Total Routes
</div>
</div>

<div class="col-md-4">
<div class="card-box">
<div class="metric">
<?= number_format($stats['active_routes']) ?>
</div>
Active Routes
</div>
</div>

<div class="col-md-4">
<div class="card-box">
<div class="metric">
<?= number_format($stats['completed_routes']) ?>
</div>
Completed Routes
</div>
</div>

</div>

<div class="card-box">

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Route</th>
<th>Orders</th>
<th>Delivered</th>
<th>Status</th>
<th>Created</th>
<th>Actions</th>

</tr>

</thead>

<tbody>

<?php while($route = $routes->fetch_assoc()): ?>

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

<?= number_format(
$route['total_orders']
) ?>

</td>

<td>

<?= number_format(
$route['delivered_count']
) ?>

/

<?= number_format(
$route['total_orders']
) ?>

</td>

<td>

<?php

$badge='secondary';

if($route['status']=='planned')
$badge='warning';

if($route['status']=='active')
$badge='primary';

if($route['status']=='completed')
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

<?php if(
$route['status']=='planned'
): ?>

<a
href="?start=<?= $route['id'] ?>"
class="btn btn-sm btn-primary">

Start

</a>

<?php endif; ?>

<?php if(
$route['status']=='active'
): ?>

<a
href="?complete=<?= $route['id'] ?>"
class="btn btn-sm btn-success">

Complete

</a>

<?php endif; ?>

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