<?php

declare(strict_types=1);

session_start();

require_once "config/db.php";

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

$userId =
(int)$_SESSION['user_id'];

$orderId =
(int)($_GET['order_id'] ?? 0);

if($orderId <= 0)
{
    die("Invalid order.");
}

/*
|--------------------------------------------------------------------------
| LOAD ORDER
|--------------------------------------------------------------------------
*/

$orderStmt =
$conn->prepare("
SELECT *
FROM orders
WHERE id=?
AND user_id=?
LIMIT 1
");

$orderStmt->bind_param(
"ii",
$orderId,
$userId
);

$orderStmt->execute();

$order =
$orderStmt
->get_result()
->fetch_assoc();

if(!$order)
{
    die("Order not found.");
}

/*
|--------------------------------------------------------------------------
| DELIVERY INFORMATION
|--------------------------------------------------------------------------
*/

$deliveryStmt =
$conn->prepare("
SELECT

d.*,

u.full_name

FROM deliveries d

LEFT JOIN users u
ON u.id = d.rider_id

WHERE d.order_id=?
LIMIT 1
");

$deliveryStmt->bind_param(
"i",
$orderId
);

$deliveryStmt->execute();

$delivery =
$deliveryStmt
->get_result()
->fetch_assoc();

/*
|--------------------------------------------------------------------------
| STATUS HISTORY
|--------------------------------------------------------------------------
*/

$historyStmt =
$conn->prepare("
SELECT *
FROM order_status_history
WHERE order_id=?
ORDER BY created_at ASC
");

$historyStmt->bind_param(
"i",
$orderId
);

$historyStmt->execute();

$history =
$historyStmt
->get_result();

/*
|--------------------------------------------------------------------------
| TRACKING STEPS
|--------------------------------------------------------------------------
*/

$steps = [
    'pending'     => 1,
    'processing'  => 2,
    'packed'      => 3,
    'shipped'     => 4,
    'delivered'   => 5
];

$currentStep =
$steps[
    strtolower(
        $order['order_status']
    )
] ?? 1;

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Track Order

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">

<style>

.tracker{
display:flex;
justify-content:space-between;
margin:40px 0;
position:relative;
}

.tracker::before{
content:'';
position:absolute;
top:22px;
left:0;
width:100%;
height:4px;
background:#dee2e6;
z-index:1;
}

.step{
position:relative;
z-index:2;
text-align:center;
flex:1;
}

.step-circle{
width:45px;
height:45px;
border-radius:50%;
background:#dee2e6;
display:flex;
align-items:center;
justify-content:center;
margin:auto;
font-weight:bold;
}

.step.active .step-circle{
background:#198754;
color:#fff;
}

.timeline-item{
border-left:3px solid #dee2e6;
padding-left:15px;
margin-bottom:15px;
}

</style>

</head>

<body>

<div class="container py-5">

<div class="d-flex justify-content-between mb-4">

<h2>

Track Order

</h2>

<a
href="order-details.php?id=<?= (int)$orderId ?>"
class="btn btn-outline-secondary">

Back

</a>

</div>

<div class="card mb-4">

<div class="card-body">

<h5 class="mb-3">

Order #

<?= htmlspecialchars(
$order['order_number']
) ?>

</h5>

<p class="mb-1">

<strong>Status:</strong>

<span class="badge bg-primary">

<?= ucfirst(
$order['order_status']
) ?>

</span>

</p>

<p class="mb-0">

<strong>Placed:</strong>

<?= date(
'd M Y H:i',
strtotime(
$order['created_at']
)
) ?>

</p>

</div>

</div>

<!-- TRACKING PROGRESS -->

<div class="card mb-4">

<div class="card-header">

Order Progress

</div>

<div class="card-body">

<div class="tracker">

<div class="step <?= $currentStep >= 1 ? 'active' : '' ?>">

<div class="step-circle">

1

</div>

<div class="mt-2">

Pending

</div>

</div>

<div class="step <?= $currentStep >= 2 ? 'active' : '' ?>">

<div class="step-circle">

2

</div>

<div class="mt-2">

Processing

</div>

</div>

<div class="step <?= $currentStep >= 3 ? 'active' : '' ?>">

<div class="step-circle">

3

</div>

<div class="mt-2">

Packed

</div>

</div>

<div class="step <?= $currentStep >= 4 ? 'active' : '' ?>">

<div class="step-circle">

4

</div>

<div class="mt-2">

Shipped

</div>

</div>

<div class="step <?= $currentStep >= 5 ? 'active' : '' ?>">

<div class="step-circle">

5

</div>

<div class="mt-2">

Delivered

</div>

</div>

</div>

</div>

</div>

<div class="row">

<div class="col-lg-6">

<div class="card mb-4">

<div class="card-header">

Tracking Information

</div>

<div class="card-body">

<p>

<strong>

Tracking Number

</strong>

<br>

<?= htmlspecialchars(
$delivery['tracking_number']
?? 'Not Assigned Yet'
) ?>

</p>

<p>

<strong>

Delivery Status

</strong>

<br>

<span class="badge bg-info">

<?= ucfirst(
$delivery['status']
?? 'pending'
) ?>

</span>

</p>

<p class="mb-0">

<strong>

Order Status

</strong>

<br>

<?= ucfirst(
$order['order_status']
) ?>

</p>

</div>

</div>

</div>

<div class="col-lg-6">

<div class="card mb-4">

<div class="card-header">

Assigned Rider

</div>

<div class="card-body">

<?php if($delivery): ?>

<h6>

<?= htmlspecialchars(
$delivery['full_name']
?? 'Unknown Rider'
) ?>

</h6>

<p class="text-muted mb-0">

Delivery Agent Assigned

</p>

<?php else: ?>

<div class="alert alert-warning mb-0">

No rider assigned yet.

</div>

<?php endif; ?>

</div>

</div>

</div>

</div>

<!-- TRACKING HISTORY -->

<div class="card mb-4">

<div class="card-header">

Tracking Timeline

</div>

<div class="card-body">

<?php if($history->num_rows > 0): ?>

<?php while(
$item =
$history->fetch_assoc()
): ?>

<div class="timeline-item">

<h6 class="mb-1">

<?= ucfirst(
$item['new_status']
) ?>

</h6>

<?php if(
!empty($item['notes'])
): ?>

<p class="mb-1">

<?= nl2br(
htmlspecialchars(
$item['notes']
)
) ?>

</p>

<?php endif; ?>

<small class="text-muted">

<?= date(
'd M Y H:i',
strtotime(
$item['created_at']
)
) ?>

</small>

</div>

<?php endwhile; ?>

<?php else: ?>

<div class="alert alert-light">

No tracking history available.

</div>

<?php endif; ?>

</div>

</div>

<!-- ORDER SUMMARY -->

<div class="card mb-4">

<div class="card-header">

Order Summary

</div>

<div class="card-body">

<div
class="d-flex justify-content-between mb-2">

<span>

Subtotal

</span>

<strong>

TZS

<?= number_format(
(float)$order['subtotal']
) ?>

</strong>

</div>

<div
class="d-flex justify-content-between mb-2">

<span>

Shipping Fee

</span>

<strong>

TZS

<?= number_format(
(float)$order['shipping_fee']
) ?>

</strong>

</div>

<hr>

<div
class="d-flex justify-content-between">

<h5>

Total

</h5>

<h5 class="text-success">

TZS

<?= number_format(
(float)$order['total_amount']
) ?>

</h5>

</div>

</div>

</div>

<!-- ACTION BUTTONS -->

<div class="row">

<div class="col-md-4 mb-2">

<a
href="invoice.php?order_id=<?= (int)$orderId ?>"
class="btn btn-outline-primary w-100">

<i class="bi bi-file-earmark-pdf"></i>

Invoice

</a>

</div>

<div class="col-md-4 mb-2">

<a
href="order-details.php?id=<?= (int)$orderId ?>"
class="btn btn-outline-success w-100">

<i class="bi bi-receipt"></i>

Order Details

</a>

</div>

<div class="col-md-4 mb-2">

<a
href="my-orders.php"
class="btn btn-dark w-100">

<i class="bi bi-box-seam"></i>

My Orders

</a>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>