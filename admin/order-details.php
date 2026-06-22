<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) {
    die("Invalid Order ID");
}

/*
|--------------------------------------------------------------------------
| ORDER
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT

o.*,

u.full_name buyer_name,
u.email buyer_email,
u.phone buyer_phone,

s.shop_name,
s.id shop_id

FROM orders o

LEFT JOIN users u
ON u.id=o.user_id

LEFT JOIN shops s
ON s.id=o.id

WHERE o.id=?

LIMIT 1
");

$stmt->bind_param("i", $orderId);
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
| UPDATE STATUS
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    if (isset($_POST['update_order'])) {

        $orderStatus =
            trim($_POST['order_status']);

        $paymentStatus =
            trim($_POST['payment_status']);

        $trackingNumber =
            trim($_POST['tracking_number']);

        $stmt = $conn->prepare("
            UPDATE orders

            SET

            order_status=?,
            payment_status=?,
            order_number=?,
            created_at=NOW()

            WHERE id=?
        ");

        $stmt->bind_param(
            "sssi",
            $orderStatus,
            $paymentStatus,
            $trackingNumber,
            $orderId
        );

        $stmt->execute();

        header(
            "Location: order-details.php?id={$orderId}&updated=1"
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN NOTE
    |--------------------------------------------------------------------------
    */
    if (isset($_POST['add_note'])) {

        $note = trim($_POST['note']);

        if (!empty($note)) {

            $adminId =
            $_SESSION['user_id'];

            $stmt = $conn->prepare("
                INSERT INTO order_notes(

                    order_id,
                    admin_id,
                    note

                ) VALUES(?,?,?)
            ");

            $stmt->bind_param(
                "iis",
                $orderId,
                $adminId,
                $note
            );

            $stmt->execute();
        }

        header(
            "Location: order-details.php?id={$orderId}"
        );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| ITEMS
|--------------------------------------------------------------------------
*/
$items = [];

if (
$conn->query(
"SHOW TABLES LIKE 'order_items'"
)->num_rows
) {

    $stmt = $conn->prepare("
        SELECT *

        FROM order_items

        WHERE order_id=?
    ");

    $stmt->bind_param(
        "i",
        $orderId
    );

    $stmt->execute();

    $items =
    $stmt
    ->get_result();
}

/*
|--------------------------------------------------------------------------
| NOTES
|--------------------------------------------------------------------------
*/
$notes = [];

if (
$conn->query(
"SHOW TABLES LIKE 'order_notes'"
)->num_rows
) {

    $stmt = $conn->prepare("
        SELECT

        n.*,

        u.full_name

        FROM order_notes n

        LEFT JOIN users u
        ON u.id=n.admin_id

        WHERE n.order_id=?

        ORDER BY n.id DESC
    ");

    $stmt->bind_param(
        "i",
        $orderId
    );

    $stmt->execute();

    $notes =
    $stmt
    ->get_result();
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>

Order Details

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
padding:20px;
border-radius:12px;
}

.metric{
font-size:26px;
font-weight:700;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<a
href="orders.php"
class="btn btn-secondary mb-3">

← Back to Orders

</a>

<?php if(isset($_GET['updated'])): ?>

<div class="alert alert-success">

Order updated successfully.

</div>

<?php endif; ?>

<div class="row">

<!-- LEFT -->

<div class="col-lg-4">

<div class="card-box shadow-sm mb-4">

<h4>

Order Information

</h4>

<hr>

<p>
<strong>Order #</strong><br>
<?= htmlspecialchars($order['order_number']) ?>
</p>

<p>
<strong>Total</strong><br>
TZS <?= number_format($order['total_amount'],2) ?>
</p>

<p>
<strong>Payment Method</strong><br>
<?= htmlspecialchars($order['payment_method']) ?>
</p>

<p>
<strong>Payment Status</strong><br>
<?= htmlspecialchars($order['payment_status']) ?>
</p>

<p>
<strong>Order Status</strong><br>
<?= htmlspecialchars($order['order_status']) ?>
</p>

<p>
<strong>Tracking Number</strong><br>
<?= htmlspecialchars($order['tracking_number'] ?? '-') ?>
</p>

<p>
<strong>Created</strong><br>
<?= date('d M Y H:i',strtotime($order['created_at'])) ?>
</p>

</div>

<div class="card-box shadow-sm mb-4">

<h4>

Buyer Information

</h4>

<hr>

<p>
<strong>Name:</strong><br>
<?= htmlspecialchars($order['buyer_name']) ?>
</p>

<p>
<strong>Email:</strong><br>
<?= htmlspecialchars($order['buyer_email']) ?>
</p>

<p>
<strong>Phone:</strong><br>
<?= htmlspecialchars($order['buyer_phone']) ?>
</p>

</div>

<div class="card-box shadow-sm">

<h4>

Shop

</h4>

<hr>

<p>

<?= htmlspecialchars(
$order['shop_name']
) ?>

</p>

<a
href="shop-details.php?id=<?= $order['shop_id'] ?>"
class="btn btn-outline-primary">

View Shop

</a>

</div>

</div>

<!-- RIGHT -->

<div class="col-lg-8">

<div class="card-box shadow-sm mb-4">

<h4>

Order Management

</h4>

<hr>

<form method="POST">

<input
type="hidden"
name="update_order"
value="1">

<div class="row">

<div class="col-md-4">

<label>

Order Status

</label>

<select
name="order_status"
class="form-select">

<?php
$statuses = [
'pending',
'processing',
'shipped',
'completed',
'cancelled'
];

foreach($statuses as $status):
?>

<option
value="<?= $status ?>"
<?= $order['order_status']==$status ? 'selected':'' ?>>

<?= ucfirst($status) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-4">

<label>

Payment Status

</label>

<select
name="payment_status"
class="form-select">

<?php
$payments = [
'pending',
'paid',
'failed',
'refunded'
];

foreach($payments as $payment):
?>

<option
value="<?= $payment ?>"
<?= $order['payment_status']==$payment ? 'selected':'' ?>>

<?= ucfirst($payment) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-4">

<label>

Tracking Number

</label>

<input
type="text"
name="tracking_number"
class="form-control"
value="<?= htmlspecialchars($order['order_number']) ?>">

</div>

</div>

<button
class="btn btn-primary mt-3">

Update Order

</button>

</form>

</div>

<!-- ITEMS -->

<div class="card-box shadow-sm mb-4">

<h4>

Ordered Items

</h4>

<hr>

<table class="table table-hover">

<thead>

<tr>

<th>Product</th>
<th>Price</th>
<th>Qty</th>
<th>Subtotal</th>

</tr>

</thead>

<tbody>

<?php if($items): ?>

<?php while($item = $items->fetch_assoc()): ?>

<tr>

<td>

<?= htmlspecialchars(
$item['product_id']
) ?>

</td>

<td>

TZS <?= number_format(
$item['price'],
2
) ?>

</td>

<td>

<?= $item['quantity'] ?>

</td>

<td>

TZS <?= number_format(
$item['price'],
2
) ?>

</td>

</tr>

<?php endwhile; ?>

<?php endif; ?>

</tbody>

</table>

</div>

<!-- SHIPPING -->

<div class="card-box shadow-sm mb-4">

<h4>

Shipping Address

</h4>

<hr>

<?= nl2br(
htmlspecialchars(
$order['shipping_address']
?? 'Not Provided'
)
) ?>

</div>

<!-- NOTES -->

<div class="card-box shadow-sm">

<h4>

Admin Notes

</h4>

<hr>

<form method="POST">

<input
type="hidden"
name="add_note"
value="1">

<textarea
name="note"
class="form-control mb-3"
rows="4"
required></textarea>

<button
class="btn btn-success">

Add Note

</button>

</form>

<hr>

<?php if($notes): ?>

<?php while($note = $notes->fetch_assoc()): ?>

<div class="border rounded p-3 mb-2">

<strong>

<?= htmlspecialchars(
$note['full_name']
) ?>

</strong>

<small class="text-muted">

<?= date(
'd M Y H:i',
strtotime(
$note['created_at']
)
) ?>

</small>

<hr>

<?= nl2br(
htmlspecialchars(
$note['note']
)
) ?>

</div>

<?php endwhile; ?>

<?php endif; ?>

</div>

</div>

</div>

</div>

</body>
</html>