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
(int)($_GET['id'] ?? 0);

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
| DELIVERY ADDRESS
|--------------------------------------------------------------------------
*/

$addressStmt =
$conn->prepare("
SELECT *
FROM addresses
WHERE id=?
LIMIT 1
");

$addressStmt->bind_param(
"i",
$order['address_id']
);

$addressStmt->execute();

$address =
$addressStmt
->get_result()
->fetch_assoc();

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

oi.*,

p.name,
p.slug,
p.image,
p.featured_image

FROM order_items oi

LEFT JOIN products p
ON p.id = oi.product_id

WHERE oi.order_id=?

ORDER BY oi.id ASC
");

$itemStmt->bind_param(
"i",
$orderId
);

$itemStmt->execute();

$orderItems =
$itemStmt->get_result();

/*
|--------------------------------------------------------------------------
| ORDER HISTORY
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

$orderHistory =
$historyStmt
->get_result();

function statusColor(
    string $status
): string
{
    return match($status)
    {
        'pending' => 'warning',
        'processing' => 'info',
        'packed' => 'secondary',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'danger',
        default => 'dark'
    };
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Order Details

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">

<style>

.product-image{
width:70px;
height:70px;
object-fit:cover;
border-radius:8px;
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

<div class="d-flex justify-content-between align-items-center mb-4">

<h2>

Order Details

</h2>

<a
href="my-orders.php"
class="btn btn-outline-secondary">

Back To Orders

</a>

</div>

<div class="row">

<div class="col-lg-8">

<div class="card mb-4">

<div class="card-header">

Order Information

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

Order Date

</strong>

<br>

<?= date(
'd M Y H:i',
strtotime(
$order['created_at']
)
) ?>

</div>

<div class="col-md-6">

<strong>

Payment Status

</strong>

<br>

<span
class="badge bg-<?= $order['payment_status'] === 'paid'
? 'success'
: 'warning' ?>">

<?= ucfirst(
$order['payment_status']
) ?>

</span>

</div>

<div class="col-md-6">

<strong>

Order Status

</strong>

<br>

<span
class="badge bg-<?= statusColor(
$order['order_status']
) ?>">

<?= ucfirst(
$order['order_status']
) ?>

</span>

</div>

</div>

</div>

</div>

<div class="card mb-4">

<div class="card-header">

Delivery Address

</div>

<div class="card-body">

<?php if($address): ?>

<strong>

<?= htmlspecialchars(
$address['recipient_name']
) ?>

</strong>

<br>

<?= htmlspecialchars(
$address['phone']
) ?>

<br>

<?= htmlspecialchars(
$address['street']
) ?>

<br>

<?= htmlspecialchars(
$address['ward']
) ?>,

<?= htmlspecialchars(
$address['district']
) ?>,

<?= htmlspecialchars(
$address['region']
) ?>

<?php else: ?>

<div class="alert alert-warning mb-0">

Address not available.

</div>

<?php endif; ?>

</div>

</div>

<div class="card mb-4">

<div class="card-header">

Payment Information

</div>

<div class="card-body">

<div class="row">

<div class="col-md-6 mb-3">

<strong>

Payment Method

</strong>

<br>

<?= htmlspecialchars(
$payment['payment_method']
?? 'N/A'
) ?>

</div>

<div class="col-md-6 mb-3">

<strong>

Transaction ID

</strong>

<br>

<?= htmlspecialchars(
$payment['transaction_id']
?? 'N/A'
) ?>

</div>

<div class="col-md-6">

<strong>

Amount Paid

</strong>

<br>

TZS

<?= number_format(
(float)$order['total_amount']
) ?>

</div>

<div class="col-md-6">

<strong>

Paid At

</strong>

<br>

<?= !empty($payment['paid_at'])
? date(
'd M Y H:i',
strtotime(
$payment['paid_at']
)
)
: 'N/A'; ?>

</div>

</div>

</div>

</div>

</div>

<div class="col-lg-4">

<div class="card mb-4">

<div class="card-header">

Order Totals

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

Shipping

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

<div class="card">

<div class="card-header">

Actions

</div>

<div class="card-body d-grid gap-2">

<a
href="invoice.php?order_id=<?= (int)$orderId ?>"
class="btn btn-outline-primary">

<i class="bi bi-file-earmark-pdf"></i>

Download Invoice

</a>

<a
href="track-order.php?order_id=<?= (int)$orderId ?>"
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

<!-- ORDER ITEMS -->

<div class="card mb-4">

<div class="card-header">

Ordered Products

</div>

<div class="card-body p-0">

<div class="table-responsive">

<table class="table table-hover mb-0">

<thead>

<tr>

<th>Product</th>

<th>Quantity</th>

<th>Price</th>

<th>Total</th>

</tr>

</thead>

<tbody>

<?php if($orderItems->num_rows > 0): ?>

<?php while($item = $orderItems->fetch_assoc()): ?>

<?php

$image =
!empty($item['featured_image'])
? $item['featured_image']
: $item['image'];

if(empty($image))
{
    $image =
    "assets/images/no-image.jpg";
}

?>

<tr>

<td>

<div
class="d-flex align-items-center">

<img
src="<?= htmlspecialchars($image) ?>"
alt=""
class="product-image me-3">

<div>

<strong>

<?= htmlspecialchars(
$item['name']
) ?>

</strong>

<br>

<small class="text-muted">

Product ID:
<?= (int)$item['product_id'] ?>

</small>

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

<?php else: ?>

<tr>

<td colspan="4">

<div class="alert alert-light m-3 mb-0">

No products found for this order.

</div>

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

</div>

<!-- ORDER TIMELINE -->

<div class="card">

<div class="card-header">

Order Timeline

</div>

<div class="card-body">

<?php if($orderHistory->num_rows > 0): ?>

<?php while(
$history =
$orderHistory->fetch_assoc()
): ?>

<div class="timeline-item">

<h6 class="mb-1">

<span
class="badge bg-<?= statusColor(
$history['new_status']
) ?>">

<?= ucfirst(
$history['new_status']
) ?>

</span>

</h6>

<?php if(
!empty($history['notes'])
): ?>

<p class="mb-1">

<?= nl2br(
htmlspecialchars(
$history['notes']
)
) ?>

</p>

<?php endif; ?>

<small class="text-muted">

<?= date(
'd M Y H:i',
strtotime(
$history['created_at']
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

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>