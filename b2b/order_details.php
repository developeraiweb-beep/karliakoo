<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

$buyer_id = (int)$user['id'];

$order_id = (int)($_GET['id'] ?? 0);

if ($order_id <= 0) {
    die("Invalid order.");
}

/*
|--------------------------------------------------------------------------
| ORDER DETAILS
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT

o.*,

s.shop_name,
s.shop_slug,
s.logo,
s.verified,

r.quote_number

FROM b2b_orders o

LEFT JOIN shops s
ON s.id = o.shop_id

LEFT JOIN rfq_requests r
ON r.id = o.rfq_id

WHERE o.id=?
AND o.buyer_id=?

LIMIT 1
");

$stmt->bind_param(
    "ii",
    $order_id,
    $buyer_id
);

$stmt->execute();

$order =
    $stmt
    ->get_result()
    ->fetch_assoc();

if (!$order) {
    die("Order not found.");
}

/*
|--------------------------------------------------------------------------
| ORDER ITEMS
|--------------------------------------------------------------------------
*/
$itemStmt = $conn->prepare("
SELECT *
FROM b2b_order_items
WHERE order_id=?
ORDER BY id ASC
");

$itemStmt->bind_param(
    "i",
    $order_id
);

$itemStmt->execute();

$items =
    $itemStmt->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>

Order #

<?= htmlspecialchars(
$order['order_number']
) ?>

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f6fa;
}

.card-box{
    background:#fff;
    border-radius:12px;
}

</style>

</head>

<body>

<div class="container py-4">

<a
href="bulk-orders.php"
class="btn btn-secondary mb-3">

← Back To Orders

</a>

<div class="row">

<div class="col-lg-8">

<div class="card-box shadow-sm p-4 mb-4">

<h3>

Order

<?= htmlspecialchars(
$order['order_number']
) ?>

</h3>

<hr>

<div class="row">

<div class="col-md-6">

<p>

RFQ Reference:

<strong>

<?= htmlspecialchars(
$order['quote_number']
) ?>

</strong>

</p>

<p>

Created:

<strong>

<?= date(
'd M Y H:i',
strtotime(
$order['created_at']
)
) ?>

</strong>

</p>

</div>

<div class="col-md-6">

<p>

Payment Status:

<?php

$paymentColor = match($order['payment_status']) {

'paid' => 'success',

'partial' => 'warning',

'pending' => 'secondary',

'refunded' => 'danger',

default => 'dark'

};

?>

<span class="badge bg-<?= $paymentColor ?>">

<?= ucfirst(
$order['payment_status']
) ?>

</span>

</p>

<p>

Order Status:

<?php

$statusColor = match($order['order_status']) {

'pending' => 'warning',

'confirmed' => 'primary',

'processing' => 'info',

'shipped' => 'dark',

'delivered' => 'success',

'cancelled' => 'danger',

default => 'secondary'

};

?>

<span class="badge bg-<?= $statusColor ?>">

<?= ucfirst(
$order['order_status']
) ?>

</span>

</p>

</div>

</div>

</div>

<div class="card-box shadow-sm p-4">

<h4>

Order Items

</h4>

<div class="table-responsive">

<table class="table">

<thead>

<tr>

<th>Product</th>
<th>Qty</th>
<th>Unit Price</th>
<th>Total</th>

</tr>

</thead>

<tbody>

<?php
$grandTotal = 0;
while($item = $items->fetch_assoc()):
$grandTotal += $item['total_price'];
?>

<tr>

<td>

<?= htmlspecialchars(
$item['product_name']
) ?>

</td>

<td>

<?= number_format(
$item['quantity']
) ?>

</td>

<td>

TZS

<?= number_format(
$item['unit_price'],
2
) ?>

</td>

<td>

TZS

<?= number_format(
$item['total_price'],
2
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

<tfoot>

<tr>

<th colspan="3">

Grand Total

</th>

<th>

TZS

<?= number_format(
$grandTotal,
2
) ?>

</th>

</tr>

</tfoot>

</table>

</div>

</div>

</div>

<div class="col-lg-4">

<div class="card-box shadow-sm p-4 mb-4">

<h4>

Supplier

</h4>

<hr>

<?php if(!empty($order['logo'])): ?>

<img
src="../uploads/shops/<?= htmlspecialchars($order['logo']) ?>"
width="80"
class="mb-3">

<?php endif; ?>

<p>

<strong>

<?= htmlspecialchars(
$order['shop_name']
) ?>

</strong>

<?php if($order['verified']): ?>

✅

<?php endif; ?>

</p>

<a
href="../shops/shop-profile.php?slug=<?= urlencode($order['shop_slug']) ?>"
class="btn btn-outline-primary w-100">

Visit Supplier

</a>

</div>

<div class="card-box shadow-sm p-4 mb-4">

<h4>

Delivery Address

</h4>

<hr>

<?= nl2br(
htmlspecialchars(
$order['delivery_address']
)
) ?>

</div>

<div class="card-box shadow-sm p-4">

<h4>

Actions

</h4>

<hr>

<a
href="invoice.php?id=<?= $order_id ?>"
class="btn btn-success w-100 mb-2">

Download Invoice

</a>

<a
href="../messages/chat.php?shop_id=<?= $order['shop_id'] ?>"
class="btn btn-primary w-100">

Contact Supplier

</a>

</div>

</div>

</div>

</div>

</body>
</html>