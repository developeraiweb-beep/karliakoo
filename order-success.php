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
WHERE
id=?
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
| PAYMENT
|--------------------------------------------------------------------------
*/

$paymentStmt =
$conn->prepare("
SELECT *
FROM payments
WHERE order_id=?
ORDER BY id DESC
LIMIT 1
");

$paymentStmt->bind_param(
"i",
$orderId
);

$paymentStmt->execute();

$payment =
$paymentStmt
->get_result()
->fetch_assoc();

/*
|--------------------------------------------------------------------------
| ORDER ITEMS
|--------------------------------------------------------------------------
*/

$itemStmt =
$conn->prepare("
SELECT

oi.quantity,
oi.price,

p.name,
p.image,
p.featured_image

FROM order_items oi

LEFT JOIN products p
ON p.id = oi.product_id

WHERE oi.order_id=?
");

$itemStmt->bind_param(
"i",
$orderId
);

$itemStmt->execute();

$orderItems =
$itemStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Order Successful

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">

<style>

.success-icon{
font-size:80px;
color:#198754;
}

.product-image{
width:70px;
height:70px;
object-fit:cover;
border-radius:8px;
}

</style>

</head>

<body>

<div class="container py-5">

<div class="text-center mb-5">

<div class="success-icon">

<i class="bi bi-check-circle-fill"></i>

</div>

<h1 class="mt-3">

Order Successful

</h1>

<p class="lead text-muted">

Thank you for your purchase.

</p>

</div>

<div class="row">

<div class="col-lg-8">

<div class="card mb-4">

<div class="card-header">

Order Details

</div>

<div class="card-body">

<div class="row">

<div class="col-md-6 mb-3">

<strong>

Order Number

</strong>

<br>

<?= htmlspecialchars(
$order['order_number']
) ?>

</div>

<div class="col-md-6 mb-3">

<strong>

Order Status

</strong>

<br>

<span class="badge bg-primary">

<?= ucfirst(
$order['order_status']
) ?>

</span>

</div>

<div class="col-md-6">

<strong>

Payment Status

</strong>

<br>

<span class="badge bg-success">

<?= ucfirst(
$order['payment_status']
) ?>

</span>

</div>

<div class="col-md-6">

<strong>

Date

</strong>

<br>

<?= date(
'd M Y H:i',
strtotime(
$order['created_at']
)
) ?>

</div>

</div>

</div>

</div>

<div class="card">

<div class="card-header">

Ordered Items

</div>

<div class="card-body p-0">

<div class="table-responsive">

<table class="table mb-0">

<thead>

<tr>

<th>Product</th>

<th>Qty</th>

<th>Price</th>

<th>Total</th>

</tr>

</thead>

<tbody>

<?php while($item = $orderItems->fetch_assoc()): ?>

<?php

$image =
$item['featured_image']
?:
$item['image'];

if(empty($image))
{
$image =
"assets/images/no-image.jpg";
}

?>

<tr>

<td>

<div
class="d-flex align-items-center gap-3">

<img
src="<?= htmlspecialchars($image) ?>"
class="product-image">

<div>

<?= htmlspecialchars(
$item['name']
) ?>

</div>

</div>

</td>

<td>

<?= (int)$item['quantity'] ?>

</td>

<td>

TZS

<?= number_format(
(float)$item['price']
) ?>

</td>

<td>

TZS

<?= number_format(
(float)$item['price']
*
(int)$item['quantity']
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

<div class="col-lg-4">

<div class="card mb-3">

<div class="card-header">

Payment Information

</div>

<div class="card-body">

<p>

<strong>

Method:

</strong>

<br>

<?= htmlspecialchars(
$payment['payment_method']
?? 'N/A'
) ?>

</p>

<p>

<strong>

Transaction ID:

</strong>

<br>

<?= htmlspecialchars(
$payment['transaction_id']
?? 'N/A'
) ?>

</p>

<p>

<strong>

Amount Paid:

</strong>

<br>

TZS

<?= number_format(
(float)$order['total_amount']
) ?>

</p>

</div>

</div>

<div class="card">

<div class="card-header">

Quick Actions

</div>

<div class="card-body d-grid gap-2">

<a
href="invoice.php?order_id=<?= $orderId ?>"
class="btn btn-outline-primary">

<i class="bi bi-file-earmark-pdf"></i>

Download Invoice

</a>

<a
href="my-orders.php"
class="btn btn-outline-success">

<i class="bi bi-box"></i>

My Orders

</a>

<a
href="track-order.php?order_id=<?= $orderId ?>"
class="btn btn-outline-dark">

<i class="bi bi-truck"></i>

Track Order

</a>

<a
href="products.php"
class="btn btn-primary">

Continue Shopping

</a>

</div>

</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>