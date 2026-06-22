<?php

declare(strict_types=1);

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

if (!$user)
{
    header("Location: ../login.php");
    exit;
}

$sellerId = (int)$user['id'];

$orderId =
(int)($_GET['id'] ?? 0);

if ($orderId <= 0)
{
    die("Invalid invoice request.");
}

/*
|--------------------------------------------------------------------------
| LOAD SHOP
|--------------------------------------------------------------------------
*/

$shopStmt = $conn->prepare("
    SELECT
        id,
        shop_name,
        logo,
        address,
        city,
        region,
        verified,
        suspended
    FROM shops
    WHERE seller_id = ?
    LIMIT 1
");

$shopStmt->bind_param(
    "i",
    $sellerId
);

$shopStmt->execute();

$shop =
$shopStmt
->get_result()
->fetch_assoc();

if (!$shop)
{
    die("Shop not found.");
}

if (
    (int)$shop['suspended'] === 1
)
{
    die("Shop suspended.");
}

$shopId =
(int)$shop['id'];

/*
|--------------------------------------------------------------------------
| VERIFY ORDER BELONGS TO SELLER SHOP
|--------------------------------------------------------------------------
*/

$verifyStmt = $conn->prepare("
    SELECT id
    FROM order_items
    WHERE order_id = ?
    AND shop_id = ?
    LIMIT 1
");

$verifyStmt->bind_param(
    "ii",
    $orderId,
    $shopId
);

$verifyStmt->execute();

if (
    $verifyStmt
    ->get_result()
    ->num_rows === 0
)
{
    die("Access denied.");
}

/*
|--------------------------------------------------------------------------
| ORDER + CUSTOMER + ADDRESS
|--------------------------------------------------------------------------
*/

$orderStmt = $conn->prepare("
    SELECT

        o.*,

        u.full_name,
        u.email,
        u.phone,

        a.recipient_name,
        a.region,
        a.district,
        a.ward,
        a.street,
        a.phone AS delivery_phone

    FROM orders o

    INNER JOIN users u
    ON u.id = o.user_id

    LEFT JOIN addresses a
    ON a.id = o.address_id

    WHERE o.id = ?

    LIMIT 1
");

$orderStmt->bind_param(
    "i",
    $orderId
);

$orderStmt->execute();

$order =
$orderStmt
->get_result()
->fetch_assoc();

if (!$order)
{
    die("Order not found.");
}

/*
|--------------------------------------------------------------------------
| PAYMENT
|--------------------------------------------------------------------------
*/

$paymentStmt = $conn->prepare("
    SELECT *
    FROM payments
    WHERE order_id = ?
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
| INVOICE ITEMS (SELLER ITEMS ONLY)
|--------------------------------------------------------------------------
*/

$itemStmt = $conn->prepare("
    SELECT

        oi.*,

        p.name,
        p.sku

    FROM order_items oi

    INNER JOIN products p
    ON p.id = oi.product_id

    WHERE oi.order_id = ?
    AND oi.shop_id = ?

    ORDER BY oi.id ASC
");

$itemStmt->bind_param(
    "ii",
    $orderId,
    $shopId
);

$itemStmt->execute();

$items =
$itemStmt
->get_result();

/*
|--------------------------------------------------------------------------
| TOTALS
|--------------------------------------------------------------------------
*/

$subtotal = 0;
$platformFee = 0;

$itemRows = [];

while(
    $row =
    $items->fetch_assoc()
)
{
    $itemRows[] = $row;

    $subtotal +=
    (
        (float)$row['price']
        *
        (int)$row['quantity']
    );

    $platformFee +=
    (
        (float)$row['platform_fee']
    );
}

$netAmount =
$subtotal -
$platformFee;

/*
|--------------------------------------------------------------------------
| INVOICE NUMBER
|--------------------------------------------------------------------------
*/

$invoiceNumber =
"INV-" .
str_pad(
    (string)$order['id'],
    8,
    "0",
    STR_PAD_LEFT
);

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Invoice <?= htmlspecialchars($invoiceNumber) ?>

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
    background:#f4f6f9;
}

.invoice-wrapper{
    max-width:1100px;
    margin:auto;
}

.invoice-card{
    background:#fff;
    border-radius:16px;
    box-shadow:0 2px 12px rgba(0,0,0,.08);
}

.print-only{
    display:none;
}

@media print{

    .no-print{
        display:none !important;
    }

    .invoice-card{
        box-shadow:none;
        border:none;
    }

    body{
        background:#fff;
    }

    .print-only{
        display:block;
    }
}

.logo{
    max-height:80px;
}

</style>

</head>

<body>

<div class="container py-4 invoice-wrapper">

<div class="mb-4 no-print">

<a
href="order-details.php?id=<?= (int)$orderId ?>"
class="btn btn-secondary">

Back

</a>

<button
onclick="window.print()"
class="btn btn-success">

Print Invoice

</button>

</div>

<div class="invoice-card p-4">

<!-- HEADER -->

<div class="row mb-4">

<div class="col-md-6">

<?php if(!empty($shop['logo'])): ?>

<img
src="<?= htmlspecialchars($shop['logo']) ?>"
class="logo mb-3">

<?php endif; ?>

<h3>

<?= htmlspecialchars(
$shop['shop_name']
) ?>

</h3>

<p class="mb-0">

<?= htmlspecialchars(
(string)$shop['address']
) ?>

</p>

<p class="mb-0">

<?= htmlspecialchars(
(string)$shop['city']
) ?>

<?= htmlspecialchars(
(string)$shop['region']
) ?>

</p>

</div>

<div class="col-md-6 text-md-end">

<h1>

INVOICE

</h1>

<p>

<strong>

Invoice #:

</strong>

<?= htmlspecialchars(
$invoiceNumber
) ?>

</p>

<p>

<strong>

Order #:

</strong>

<?= htmlspecialchars(
$order['order_number']
) ?>

</p>

<p>

<strong>

Date:

</strong>

<?= date(
'd M Y',
strtotime(
$order['created_at']
)
) ?>

</p>

</div>

</div>

<!-- CUSTOMER -->

<div class="row mb-4">

<div class="col-md-6">

<h5>

Customer Information

</h5>

<p>

<strong>

<?= htmlspecialchars(
$order['full_name']
) ?>

</strong>

</p>

<p>

<?= htmlspecialchars(
$order['email']
) ?>

</p>

<p>

<?= htmlspecialchars(
$order['phone']
) ?>

</p>

</div>

<div class="col-md-6">

<h5>

Shipping Address

</h5>

<p>

<strong>

<?= htmlspecialchars(
(string)$order['recipient_name']
) ?>

</strong>

</p>

<p>

<?= htmlspecialchars(
(string)$order['street']
) ?>

</p>

<p>

<?= htmlspecialchars(
(string)$order['ward']
) ?>

,
<?= htmlspecialchars(
(string)$order['district']
) ?>

</p>

<p>

<?= htmlspecialchars(
(string)$order['region']
) ?>

</p>

<p>

<?= htmlspecialchars(
(string)$order['delivery_phone']
) ?>

</p>

</div>

</div>

<!-- ITEMS -->

<div class="table-responsive mb-4">

<table
class="table table-bordered">

<thead>

<tr>

<th>#</th>

<th>Product</th>

<th>SKU</th>

<th>Price</th>

<th>Qty</th>

<th>Total</th>

</tr>

</thead>

<tbody>

<?php
$counter = 1;

foreach(
$itemRows
as $item
):
?>

<tr>

<td>

<?= $counter++ ?>

</td>

<td>

<?= htmlspecialchars(
$item['name']
) ?>

</td>

<td>

<?= htmlspecialchars(
$item['sku']
) ?>

</td>

<td>

TZS

<?= number_format(
(float)$item['price'],
2
) ?>

</td>

<td>

<?= number_format(
(int)$item['quantity']
) ?>

</td>

<td>

TZS

<?= number_format(
(
(float)$item['price']
*
(int)$item['quantity']
),
2
) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<!-- TOTALS -->

<div class="row">

<div class="col-md-6">

<h5>

Payment Details

</h5>

<?php if($payment): ?>

<p>

<strong>

Method:

</strong>

<?= htmlspecialchars(
(string)$payment['payment_method']
) ?>

</p>

<p>

<strong>

Transaction ID:

</strong>

<?= htmlspecialchars(
(string)$payment['transaction_id']
) ?>

</p>

<p>

<strong>

Payment Status:

</strong>

<?= ucfirst(
(string)$payment['payment_status']
) ?>

</p>

<?php else: ?>

<p>

No payment record found.

</p>

<?php endif; ?>

</div>

<div class="col-md-6">

<table
class="table">

<tr>

<th>

Subtotal

</th>

<td class="text-end">

TZS

<?= number_format(
$subtotal,
2
) ?>

</td>

</tr>

<tr>

<th>

Platform Fee

</th>

<td class="text-end">

TZS

<?= number_format(
$platformFee,
2
) ?>

</td>

</tr>

<tr>

<th>

Net Amount

</th>

<td class="text-end">

<strong>

TZS

<?= number_format(
$netAmount,
2
) ?>

</strong>

</td>

</tr>

</table>

</div>

<!-- PAYMENT SUMMARY -->

<hr class="my-4">

<div class="row">

<div class="col-md-4">

<div class="border rounded p-3 h-100">

<h6>

Order Information

</h6>

<table class="table table-sm mb-0">

<tr>
<td>Status</td>
<td>

<strong>

<?= ucfirst(
$order['order_status']
) ?>

</strong>

</td>
</tr>

<tr>
<td>Payment</td>
<td>

<?= ucfirst(
$order['payment_status']
) ?>

</td>
</tr>

<tr>
<td>Items</td>
<td>

<?= count(
$itemRows
) ?>

</td>
</tr>

</table>

</div>

</div>

<div class="col-md-4">

<div class="border rounded p-3 h-100">

<h6>

Verification

</h6>

<p class="mb-1">

Invoice Number

</p>

<strong>

<?= htmlspecialchars(
$invoiceNumber
) ?>

</strong>

<hr>

<p class="mb-1">

Order Number

</p>

<strong>

<?= htmlspecialchars(
$order['order_number']
) ?>

</strong>

</div>

</div>

<div class="col-md-4">

<div class="border rounded p-3 h-100 text-center">

<h6>

QR Verification

</h6>

<div
style="
width:120px;
height:120px;
margin:auto;
border:1px dashed #999;
display:flex;
align-items:center;
justify-content:center;
">

QR CODE

</div>

<small class="text-muted">

Invoice verification

</small>

</div>

</div>

</div>

<!-- SIGNATURES -->

<div class="row mt-5">

<div class="col-md-6 text-center">

<div
style="
border-top:1px solid #000;
width:250px;
margin:auto;
padding-top:8px;
">

Seller Signature

</div>

</div>

<div class="col-md-6 text-center">

<div
style="
border-top:1px solid #000;
width:250px;
margin:auto;
padding-top:8px;
">

Customer Signature

</div>

</div>

</div>

<!-- TERMS -->

<div class="mt-5">

<h6>

Terms & Conditions

</h6>

<ul>

<li>
This invoice is generated electronically and is valid without a physical signature.
</li>

<li>
Products remain subject to marketplace return and refund policies.
</li>

<li>
Payment confirmation is required before shipment unless otherwise agreed.
</li>

<li>
All disputes should be reported through the platform support team.
</li>

</ul>

</div>

<!-- FOOTER -->

<hr>

<div class="text-center text-muted">

<p class="mb-1">

Generated on

<?= date(
'd M Y H:i:s'
) ?>

</p>

<p class="mb-1">

Powered by Karliakoo Marketplace

</p>

<p>

This document serves as an official sales invoice.

</p>

</div>

</div>