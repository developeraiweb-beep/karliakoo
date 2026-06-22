<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$order_id = (int)($_GET['id'] ?? 0);

if ($order_id <= 0) {
    die("Invalid order.");
}

/*
|--------------------------------------------------------------------------
| UPDATE ORDER
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $order_status =
        trim($_POST['order_status']);

    $payment_status =
        trim($_POST['payment_status']);

    $admin_notes =
        trim($_POST['admin_notes']);

    $stmt = $conn->prepare("
        UPDATE b2b_orders
        SET
            order_status=?,
            payment_status=?,
            admin_notes=?,
            updated_at=NOW()
        WHERE id=?
    ");

    $stmt->bind_param(
        "sssi",
        $order_status,
        $payment_status,
        $admin_notes,
        $order_id
    );

    $stmt->execute();

    header(
        "Location:b2b-order-details.php?id=".$order_id."&updated=1"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| ORDER DETAILS
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT

o.*,

u.full_name buyer_name,
u.email buyer_email,
u.phone buyer_phone,

s.shop_name,
s.shop_slug,
s.verified,

rfq.quote_number

FROM b2b_orders o

LEFT JOIN users u
ON u.id=o.buyer_id

LEFT JOIN shops s
ON s.id=o.shop_id

LEFT JOIN rfq_requests rfq
ON rfq.id=o.rfq_id

WHERE o.id=?

LIMIT 1
");

$stmt->bind_param("i", $order_id);
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
    $itemStmt
    ->get_result();

/*
|--------------------------------------------------------------------------
| SHIPMENT
|--------------------------------------------------------------------------
*/
$shipmentStmt = $conn->prepare("
SELECT *
FROM b2b_shipments
WHERE order_id=?
LIMIT 1
");

$shipmentStmt->bind_param(
    "i",
    $order_id
);

$shipmentStmt->execute();

$shipment =
    $shipmentStmt
    ->get_result()
    ->fetch_assoc();

$updated =
    isset($_GET['updated']);

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>

Order

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

<div class="container-fluid py-4">

<a
href="b2b-orders.php"
class="btn btn-secondary mb-3">

← Back

</a>

<?php if($updated): ?>

<div class="alert alert-success">

Order updated successfully.

</div>

<?php endif; ?>

<div class="row">

<!-- LEFT -->

<div class="col-lg-8">

<div class="card-box shadow-sm p-4 mb-4">

<h3>

<?= htmlspecialchars(
$order['order_number']
) ?>

</h3>

<hr>

<div class="row">

<div class="col-md-6">

<p>

RFQ:
<strong>

<?= htmlspecialchars(
$order['quote_number']
) ?>

</strong>

</p>

<p>

Created:

<?= date(
'd M Y H:i',
strtotime(
$order['created_at']
)
) ?>

</p>

</div>

<div class="col-md-6">

<p>

Total Amount:

<strong>

TZS

<?= number_format(
$order['total_amount'],
2
) ?>

</strong>

</p>

<p>

Supplier:

<strong>

<?= htmlspecialchars(
$order['shop_name']
) ?>

</strong>

</p>

</div>

</div>

</div>

<div class="card-box shadow-sm p-4 mb-4">

<h4>

Buyer Information

</h4>

<hr>

<p>

Name:
<strong>

<?= htmlspecialchars(
$order['buyer_name']
) ?>

</strong>

</p>

<p>

Email:

<?= htmlspecialchars(
$order['buyer_email']
) ?>

</p>

<p>

Phone:

<?= htmlspecialchars(
$order['buyer_phone']
) ?>

</p>

</div>

<div class="card-box shadow-sm p-4">

<h4>

Order Items

</h4>

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

<?php while($item = $items->fetch_assoc()): ?>

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

</table>

</div>

</div>

<!-- RIGHT -->

<div class="col-lg-4">

<div class="card-box shadow-sm p-4 mb-4">

<h4>

Shipment

</h4>

<hr>

<p>

Courier:

<strong>

<?= htmlspecialchars(
$shipment['courier_name']
?? 'N/A'
) ?>

</strong>

</p>

<p>

Tracking:

<strong>

<?= htmlspecialchars(
$shipment['tracking_number']
?? 'N/A'
) ?>

</strong>

</p>

<p>

Shipment Status:

<strong>

<?= ucfirst(
$shipment['shipment_status']
?? 'pending'
) ?>

</strong>

</p>

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

Admin Controls

</h4>

<hr>

<form method="POST">

<div class="mb-3">

<label>

Order Status

</label>

<select
name="order_status"
class="form-select">

<?php

$orderStatuses = [
'pending',
'confirmed',
'processing',
'shipped',
'delivered',
'cancelled'
];

foreach(
$orderStatuses as $status
):

?>

<option
value="<?= $status ?>"
<?= $order['order_status']===$status ? 'selected' : '' ?>>

<?= ucfirst($status) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="mb-3">

<label>

Payment Status

</label>

<select
name="payment_status"
class="form-select">

<?php

$payments = [
'pending',
'partial',
'paid',
'refunded'
];

foreach(
$payments as $payment
):

?>

<option
value="<?= $payment ?>"
<?= $order['payment_status']===$payment ? 'selected' : '' ?>>

<?= ucfirst($payment) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="mb-3">

<label>

Admin Notes

</label>

<textarea
name="admin_notes"
rows="5"
class="form-control"><?= htmlspecialchars(
$order['admin_notes']
?? ''
) ?></textarea>

</div>

<button
class="btn btn-primary w-100">

Update Order

</button>

</form>

</div>

</div>

</div>

</div>

</body>
</html>