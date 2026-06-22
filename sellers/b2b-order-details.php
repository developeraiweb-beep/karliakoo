<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['seller']);

$user = currentUser();
$seller_id = (int)$user['id'];

$order_id = (int)($_GET['id'] ?? 0);

if ($order_id <= 0) {
    die("Invalid order.");
}

/*
|--------------------------------------------------------------------------
| ORDER
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT

o.*,

u.full_name,
u.email,
u.phone,

s.shop_name,

r.quote_number

FROM b2b_orders o

LEFT JOIN users u
ON u.id=o.buyer_id

LEFT JOIN shops s
ON s.id=o.shop_id

LEFT JOIN rfq_requests r
ON r.id=o.rfq_id

WHERE o.id=?
AND o.supplier_id=?

LIMIT 1
");

$stmt->bind_param(
    "ii",
    $order_id,
    $seller_id
);

$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Order not found.");
}

/*
|--------------------------------------------------------------------------
| ITEMS
|--------------------------------------------------------------------------
*/
$itemStmt = $conn->prepare("
SELECT *
FROM b2b_order_items
WHERE order_id=?
ORDER BY id ASC
");

$itemStmt->bind_param("i", $order_id);
$itemStmt->execute();

$items = $itemStmt->get_result();

/*
|--------------------------------------------------------------------------
| SHIPMENT
|--------------------------------------------------------------------------
*/
$shipStmt = $conn->prepare("
SELECT *
FROM b2b_shipments
WHERE order_id=?
LIMIT 1
");

$shipStmt->bind_param("i", $order_id);
$shipStmt->execute();

$shipment =
    $shipStmt
    ->get_result()
    ->fetch_assoc();

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| UPDATE ORDER
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $order_status = trim($_POST['order_status']);

    $tracking_number =
        trim($_POST['tracking_number']);

    $courier_name =
        trim($_POST['courier_name']);

    $notes =
        trim($_POST['notes']);

    $conn->begin_transaction();

    try {

        /*
        |--------------------------------------------------------------------------
        | UPDATE ORDER STATUS
        |--------------------------------------------------------------------------
        */
        $updateOrder = $conn->prepare("
            UPDATE b2b_orders
            SET
                order_status=?,
                notes=?,
                updated_at=NOW()
            WHERE id=?
        ");

        $updateOrder->bind_param(
            "ssi",
            $order_status,
            $notes,
            $order_id
        );

        $updateOrder->execute();

        /*
        |--------------------------------------------------------------------------
        | SHIPMENT
        |--------------------------------------------------------------------------
        */
        if ($shipment) {

            $updateShipment = $conn->prepare("
                UPDATE b2b_shipments
                SET
                    tracking_number=?,
                    courier_name=?,
                    shipment_status=?,
                    notes=?
                WHERE order_id=?
            ");

            $shipment_status =
                ($order_status === 'shipped'
                || $order_status === 'delivered')
                ? $order_status
                : 'pending';

            $updateShipment->bind_param(
                "ssssi",
                $tracking_number,
                $courier_name,
                $shipment_status,
                $notes,
                $order_id
            );

            $updateShipment->execute();

        } else {

            $shipment_status =
                ($order_status === 'shipped'
                || $order_status === 'delivered')
                ? $order_status
                : 'pending';

            $insertShipment = $conn->prepare("
                INSERT INTO b2b_shipments
                (
                    order_id,
                    tracking_number,
                    courier_name,
                    shipment_status,
                    notes
                )
                VALUES
                (?,?,?,?,?)
            ");

            $insertShipment->bind_param(
                "issss",
                $order_id,
                $tracking_number,
                $courier_name,
                $shipment_status,
                $notes
            );

            $insertShipment->execute();
        }

        $conn->commit();

        header(
            "Location:b2b-order-details.php?id=".$order_id."&updated=1"
        );
        exit;

    } catch(Exception $e) {

        $conn->rollback();

        $error =
            "Failed to update order.";
    }
}

if(isset($_GET['updated'])) {
    $success =
        "Order updated successfully.";
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>
Manage Wholesale Order
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
href="b2b-orders.php"
class="btn btn-secondary mb-3">

← Back

</a>

<?php if($success): ?>
<div class="alert alert-success">
<?= $success ?>
</div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger">
<?= $error ?>
</div>
<?php endif; ?>

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

<p>

RFQ:
<strong>

<?= htmlspecialchars(
$order['quote_number']
) ?>

</strong>

</p>

<p>

Buyer:
<strong>

<?= htmlspecialchars(
$order['full_name']
) ?>

</strong>

</p>

<p>

Email:
<?= htmlspecialchars(
$order['email']
) ?>

</p>

<p>

Phone:
<?= htmlspecialchars(
$order['phone']
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

<?php
$total = 0;

while($item = $items->fetch_assoc()):

$total += $item['total_price'];
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
$total,
2
) ?>

</th>

</tr>

</tfoot>

</table>

</div>

</div>

<div class="col-lg-4">

<div class="card-box shadow-sm p-4">

<h4>

Order Fulfillment

</h4>

<hr>

<form method="POST">

<div class="mb-3">

<label>Status</label>

<select
name="order_status"
class="form-select">

<?php

$statuses = [
'pending',
'confirmed',
'processing',
'shipped',
'delivered',
'cancelled'
];

foreach($statuses as $status):

?>

<option
value="<?= $status ?>"
<?= $order['order_status']===$status?'selected':'' ?>>

<?= ucfirst($status) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="mb-3">

<label>Courier</label>

<input
type="text"
name="courier_name"
class="form-control"
value="<?= htmlspecialchars($shipment['courier_name'] ?? '') ?>">

</div>

<div class="mb-3">

<label>Tracking Number</label>

<input
type="text"
name="tracking_number"
class="form-control"
value="<?= htmlspecialchars($shipment['tracking_number'] ?? '') ?>">

</div>

<div class="mb-3">

<label>Notes</label>

<textarea
name="notes"
rows="4"
class="form-control"><?= htmlspecialchars($shipment['notes'] ?? $order['notes'] ?? '') ?></textarea>

</div>

<button
class="btn btn-primary w-100">

Update Order

</button>

</form>

</div>

<div class="card-box shadow-sm p-4 mt-4">

<h5>
Payment Status
</h5>

<hr>

<span class="badge bg-<?=
match($order['payment_status']) {
'paid' => 'success',
'partial' => 'warning',
'pending' => 'secondary',
'refunded' => 'danger',
default => 'dark'
}
?>">

<?= ucfirst(
$order['payment_status']
) ?>

</span>

</div>

<div class="card-box shadow-sm p-4 mt-4">

<h5>
Delivery Address
</h5>

<hr>

<?= nl2br(
htmlspecialchars(
$order['delivery_address']
)
) ?>

</div>

</div>

</div>

</div>

</body>
</html>