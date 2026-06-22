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
    die("Invalid invoice.");
}

/*
|--------------------------------------------------------------------------
| ORDER
|--------------------------------------------------------------------------
*/

$orderStmt =
$conn->prepare("
SELECT
o.*,
u.full_name,
u.email,
u.phone
FROM orders o

LEFT JOIN users u
ON u.id = o.user_id

WHERE
o.id=?
AND o.user_id=?
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
    die("Invoice not found.");
}

/*
|--------------------------------------------------------------------------
| ADDRESS
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

oi.quantity,
oi.price,

p.name,
p.image

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
$itemStmt
->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Invoice #<?= htmlspecialchars($order['order_number']) ?>

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
background:#f8f9fa;
}

.invoice-box{
background:#fff;
padding:40px;
border-radius:10px;
box-shadow:0 0 10px rgba(0,0,0,.08);
}

@media print{

.no-print{
display:none;
}

body{
background:#fff;
}

.invoice-box{
box-shadow:none;
padding:0;
}

}

</style>

</head>

<body>

<div class="container py-5">

<div class="invoice-box">

<!-- ACTION BUTTONS -->

<div class="d-flex justify-content-between mb-4 no-print">

<a
href="order-details.php?id=<?= (int)$orderId ?>"
class="btn btn-secondary">

Back

</a>

<button
onclick="window.print();"
class="btn btn-primary">

Print Invoice

</button>

</div>

<!-- COMPANY HEADER -->

<div class="row mb-5">

<div class="col-md-6">

<h2 class="mb-1">

Karliakoo Marketplace

</h2>

<p class="text-muted mb-0">

Online Shopping Platform

</p>

<p class="mb-0">

Dar es Salaam, Tanzania

</p>

</div>

<div class="col-md-6 text-md-end">

<h3>

INVOICE

</h3>

<p class="mb-1">

<strong>

Invoice No:

</strong>

<?= htmlspecialchars(
$order['order_number']
) ?>

</p>

<p class="mb-1">

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

<p class="mb-0">

<strong>

Status:

</strong>

<span
class="badge bg-success">

<?= ucfirst(
$order['payment_status']
) ?>

</span>

</p>

</div>

</div>

<!-- CUSTOMER INFORMATION -->

<div class="row mb-4">

<div class="col-md-6">

<h5>

Customer Information

</h5>

<hr>

<p class="mb-1">

<strong>

Name:

</strong>

<?= htmlspecialchars(
$order['full_name']
)
?>

</p>

<p class="mb-1">

<strong>

Email:

</strong>

<?= htmlspecialchars(
$order['email']
?? 'N/A'
)
?>

</p>

<p class="mb-1">

<strong>

Phone:

</strong>

<?= htmlspecialchars(
$order['phone']
)
?>

</p>

</div>

<div class="col-md-6">

<h5>

Delivery Address

</h5>

<hr>

<?php if($address): ?>

<p class="mb-1">

<?= htmlspecialchars(
$address['recipient_name']
) ?>

</p>

<p class="mb-1">

<?= htmlspecialchars(
$address['phone']
) ?>

</p>

<p class="mb-1">

<?= htmlspecialchars(
$address['street']
) ?>

</p>

<p class="mb-1">

<?= htmlspecialchars(
$address['ward']
) ?>,

<?= htmlspecialchars(
$address['district']
) ?>

</p>

<p class="mb-0">

<?= htmlspecialchars(
$address['region']
) ?>

</p>

<?php else: ?>

<p>

Address unavailable

</p>

<?php endif; ?>

</div>

</div>

<!-- PAYMENT DETAILS -->

<div class="card mb-4">

<div class="card-header">

Payment Details

</div>

<div class="card-body">

<div class="row">

<div class="col-md-4">

<strong>

Payment Method

</strong>

<br>

<?= htmlspecialchars(
$payment['payment_method']
?? 'N/A'
) ?>

</div>

<div class="col-md-4">

<strong>

Transaction ID

</strong>

<br>

<?= htmlspecialchars(
$payment['transaction_id']
?? 'N/A'
) ?>

</div>

<div class="col-md-4">

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

<!-- ORDER ITEMS -->

<div class="card mb-4">

<div class="card-header">

Invoice Items

</div>

<div class="card-body p-0">

<div class="table-responsive">

<table class="table table-bordered mb-0">

<thead class="table-light">

<tr>

<th>#</th>

<th>Product</th>

<th class="text-center">

Qty

</th>

<th class="text-end">

Unit Price

</th>

<th class="text-end">

Total

</th>

</tr>

</thead>

<tbody>

<?php

$counter = 1;

while(
$item =
$orderItems->fetch_assoc()
):

$itemTotal =
(float)$item['price']
*
(int)$item['quantity'];

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

<td class="text-center">

<?= (int)$item['quantity'] ?>

</td>

<td class="text-end">

TZS

<?= number_format(
(float)$item['price'],
2
) ?>

</td>

<td class="text-end">

TZS

<?= number_format(
$itemTotal,
2
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

<!-- TOTALS -->

<div class="row justify-content-end">

<div class="col-md-5">

<table class="table">

<tr>

<td>

Subtotal

</td>

<td class="text-end">

TZS

<?= number_format(
(float)$order['subtotal'],
2
) ?>

</td>

</tr>

<tr>

<td>

Shipping Fee

</td>

<td class="text-end">

TZS

<?= number_format(
(float)$order['shipping_fee'],
2
) ?>

</td>

</tr>

<tr class="table-success">

<th>

Grand Total

</th>

<th class="text-end">

TZS

<?= number_format(
(float)$order['total_amount'],
2
) ?>

</th>

</tr>

</table>

</div>

</div>

<!-- NOTES -->

<div class="mt-5">

<h5>

Notes

</h5>

<p class="text-muted">

Thank you for shopping with
Karliakoo Marketplace.

Please keep this invoice
for your records.

For support regarding
this order, contact
customer support and
quote your order number.

</p>

</div>

<hr>

<!-- FOOTER -->

<div class="text-center text-muted mt-4">

<p class="mb-1">

Karliakoo Marketplace

</p>

<p class="mb-0">

Generated on

<?= date(
'd M Y H:i:s'
) ?>

</p>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>