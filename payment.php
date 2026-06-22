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

$stmt =
$conn->prepare("
SELECT *
FROM orders
WHERE
id=?
AND
user_id=?
LIMIT 1
");

$stmt->bind_param(
"ii",
$orderId,
$userId
);

$stmt->execute();

$order =
$stmt
->get_result()
->fetch_assoc();

if(!$order)
{
    die("Order not found.");
}

/*
|--------------------------------------------------------------------------
| ALREADY PAID
|--------------------------------------------------------------------------
*/

if(
$order['payment_status']
=== 'paid'
)
{
    header(
    "Location: order-success.php?order_id=" .
    $orderId
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| ORDER ITEMS
|--------------------------------------------------------------------------
*/

$itemStmt =
$conn->prepare("
SELECT

oi.*,
p.name

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

/*
|--------------------------------------------------------------------------
| PAYMENT PROCESSING
|--------------------------------------------------------------------------
*/

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $paymentMethod =
    trim(
        $_POST['payment_method']
        ?? ''
    );

    if(empty($paymentMethod))
    {
        $error =
        "Select payment method.";
    }
    else
    {
        $transactionId =
        strtoupper(
            uniqid(
                "TXN"
            )
        );

        $conn->begin_transaction();

        try
        {
            /*
            ------------------------------------------
            SAVE PAYMENT
            ------------------------------------------
            */

            $paymentStmt =
            $conn->prepare("
            INSERT INTO payments
            (
                order_id,
                user_id,
                transaction_id,
                payment_method,
                amount,
                currency,
                payment_status,
                paid_at
            )
            VALUES
            (
                ?,?,?,?,?,?,
                'paid',
                NOW()
            )
            ");

            $currency =
            "TZS";

            $amount =
            (float)$order['total_amount'];

            $paymentStmt->bind_param(
            "iissds",
            $orderId,
            $userId,
            $transactionId,
            $paymentMethod,
            $amount,
            $currency
            );

            $paymentStmt->execute();

            /*
            ------------------------------------------
            UPDATE ORDER
            ------------------------------------------
            */

            $updateOrder =
            $conn->prepare("
            UPDATE orders
            SET

            payment_status='paid',
            order_status='processing'

            WHERE id=?
            ");

            $updateOrder->bind_param(
            "i",
            $orderId
            );

            $updateOrder->execute();

            /*
            ------------------------------------------
            ORDER HISTORY
            ------------------------------------------
            */

            $history =
            $conn->prepare("
            INSERT INTO
            order_status_history
            (
                order_id,
                changed_by,
                old_status,
                new_status,
                notes
            )
            VALUES
            (
                ?,
                ?,
                'pending',
                'processing',
                'Payment received'
            )
            ");

            $history->bind_param(
            "ii",
            $orderId,
            $userId
            );

            $history->execute();

            $conn->commit();

            header(
            "Location: order-success.php?order_id=" .
            $orderId
            );

            exit;
        }
        catch(Exception $e)
        {
            $conn->rollback();

            $error =
            "Payment failed.";
        }
    }
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

Payment

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">

</head>

<body>

<div class="container py-5">

<h2 class="mb-4">

Payment

</h2>

<?php if(!empty($error)): ?>

<div class="alert alert-danger">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>

<div class="row">

<div class="col-lg-8">

<div class="card mb-4">

<div class="card-header">

Order Items

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

<tr>

<td>

<?= htmlspecialchars(
$item['name']
) ?>

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

<div class="card">

<div class="card-header">

Choose Payment Method

</div>

<div class="card-body">

<form method="POST">

<div class="form-check mb-3">

<input
class="form-check-input"
type="radio"
name="payment_method"
value="M-Pesa"
id="mpesa"
required>

<label
class="form-check-label"
for="mpesa">

📱 M-Pesa

</label>

</div>

<div class="form-check mb-3">

<input
class="form-check-input"
type="radio"
name="payment_method"
value="Airtel Money"
id="airtel">

<label
class="form-check-label"
for="airtel">

📱 Airtel Money

</label>

</div>

<div class="form-check mb-3">

<input
class="form-check-input"
type="radio"
name="payment_method"
value="Mix by Yas"
id="yas">

<label
class="form-check-label"
for="yas">

📱 Mix by Yas

</label>

</div>

<div class="form-check mb-3">

<input
class="form-check-input"
type="radio"
name="payment_method"
value="Bank Transfer"
id="bank">

<label
class="form-check-label"
for="bank">

🏦 Bank Transfer

</label>

</div>

<div class="form-check mb-4">

<input
class="form-check-input"
type="radio"
name="payment_method"
value="Cash On Delivery"
id="cod">

<label
class="form-check-label"
for="cod">

🚚 Cash On Delivery

</label>

</div>

<div class="card-footer">

<button
type="submit"
class="btn btn-success w-100">

Pay Now

</button>

</form>

</div>

</div>

</div>

<!-- RIGHT SIDEBAR -->

<div class="col-lg-4">

<div class="card">

<div class="card-header">

Order Summary

</div>

<div class="card-body">

<div
class="d-flex justify-content-between mb-2">

<span>

Order Number

</span>

<strong>

<?= htmlspecialchars(
$order['order_number']
) ?>

</strong>

</div>

<hr>

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

<div class="card-footer">

<span
class="badge bg-warning">

Payment Status:

<?= ucfirst(
$order['payment_status']
) ?>

</span>

</div>

</div>

<div class="card mt-3">

<div class="card-body">

<h6>

Secure Checkout

</h6>

<p class="small text-muted mb-0">

Your payment details are securely processed and recorded.

</p>

</div>

</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>