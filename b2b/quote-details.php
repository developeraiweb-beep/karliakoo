<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();
$buyer_id = (int)$user['id'];

$rfq_id = (int)($_GET['id'] ?? 0);

if ($rfq_id <= 0) {
    die("Invalid quotation.");
}

/*
|--------------------------------------------------------------------------
| RFQ DETAILS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
SELECT

r.*,

p.product_name,
p.image,
p.shop_id,

s.shop_name,

resp.quoted_price,
resp.delivery_days,
resp.payment_terms,
resp.notes

FROM rfq_requests r

LEFT JOIN products p
ON p.id = r.product_id

LEFT JOIN shops s
ON s.seller_id = r.supplier_id

LEFT JOIN rfq_responses resp
ON resp.rfq_id = r.id

WHERE r.id=?
AND r.buyer_id=?

LIMIT 1
");

$stmt->bind_param(
    "ii",
    $rfq_id,
    $buyer_id
);

$stmt->execute();

$quote = $stmt->get_result()->fetch_assoc();

if (!$quote) {
    die("Quotation not found.");
}

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| ACCEPT QUOTE
|--------------------------------------------------------------------------
*/
if (
    isset($_POST['accept'])
    &&
    $quote['status'] === 'quoted'
) {

    $conn->begin_transaction();

    try {

        $order_number =
            'B2B-' .
            strtoupper(
                substr(
                    md5(
                        uniqid()
                    ),
                    0,
                    12
                )
            );

        $quantity =
            (int)$quote['quantity'];

        $unit_price =
            (float)$quote['quoted_price'];

        $total_amount =
            $quantity * $unit_price;

        $order = $conn->prepare("
            INSERT INTO orders
            (
                order_number,
                user_id,
                shop_id,
                total_amount,
                status,
                created_at
            )
            VALUES
            (
                ?,?,?,?,
                'pending',
                NOW()
            )
        ");

        $order->bind_param(
            "siid",
            $order_number,
            $buyer_id,
            $quote['shop_id'],
            $total_amount
        );

        $order->execute();

        $order_id =
            $conn->insert_id;

        $update = $conn->prepare("
            UPDATE rfq_requests
            SET
                status='accepted',
                order_id=?,
                updated_at=NOW()
            WHERE id=?
        ");

        $update->bind_param(
            "ii",
            $order_id,
            $rfq_id
        );

        $update->execute();

        $conn->commit();

        header(
            "Location: ../orders/order-details.php?id=" .
            $order_id
        );

        exit;

    } catch(Exception $e) {

        $conn->rollback();

        $error =
            "Failed to accept quotation.";
    }
}

/*
|--------------------------------------------------------------------------
| REJECT QUOTE
|--------------------------------------------------------------------------
*/
if (
    isset($_POST['reject'])
    &&
    $quote['status'] === 'quoted'
) {

    $stmt = $conn->prepare("
        UPDATE rfq_requests
        SET
            status='rejected',
            updated_at=NOW()
        WHERE id=?
    ");

    $stmt->bind_param(
        "i",
        $rfq_id
    );

    $stmt->execute();

    header(
        "Location: quote-details.php?id=" .
        $rfq_id
    );

    exit;
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>
Quote Details
</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f6fa;
}

.quote-card{
    background:#fff;
    border-radius:12px;
}

.product-image{
    width:100%;
    height:350px;
    object-fit:cover;
    border-radius:12px;
}

</style>

</head>

<body>

<div class="container py-5">

<a
href="my-quotes.php"
class="btn btn-secondary mb-4">

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

<div class="col-lg-4">

<img
src="../uploads/products/<?= htmlspecialchars($quote['image']) ?>"
class="product-image">

</div>

<div class="col-lg-8">

<div class="card quote-card shadow-sm">

<div class="card-body">

<h3>

<?= htmlspecialchars(
$quote['product_name']
) ?>

</h3>

<hr>

<p>

Quote Number:

<strong>

<?= htmlspecialchars(
$quote['quote_number']
) ?>

</strong>

</p>

<p>

Supplier:

<strong>

<?= htmlspecialchars(
$quote['shop_name']
) ?>

</strong>

</p>

<p>

Requested Quantity:

<strong>

<?= number_format(
$quote['quantity']
) ?>

</strong>

</p>

<p>

Status:

<?php

$color = match($quote['status']) {

'pending' => 'warning',

'quoted' => 'primary',

'accepted' => 'success',

'rejected' => 'danger',

'expired' => 'secondary',

default => 'dark'
};

?>

<span class="badge bg-<?= $color ?>">

<?= ucfirst(
$quote['status']
) ?>

</span>

</p>

</div>

</div>

</div>

</div>

<div class="card quote-card shadow-sm mt-4">

<div class="card-header">

Supplier Quotation

</div>

<div class="card-body">

<?php if($quote['quoted_price']): ?>

<div class="row">

<div class="col-md-4">

<h4 class="text-success">

TZS

<?= number_format(
$quote['quoted_price'],
2
) ?>

</h4>

<div>Unit Price</div>

</div>

<div class="col-md-4">

<h4>

<?= number_format(
$quote['delivery_days']
) ?>

 Days

</h4>

<div>Delivery Time</div>

</div>

<div class="col-md-4">

<h4>

TZS

<?= number_format(
$quote['quoted_price']
*
$quote['quantity'],
2
) ?>

</h4>

<div>Total Amount</div>

</div>

</div>

<hr>

<h5>

Payment Terms

</h5>

<p>

<?= nl2br(
htmlspecialchars(
$quote['payment_terms']
)
) ?>

</p>

<h5>

Supplier Notes

</h5>

<p>

<?= nl2br(
htmlspecialchars(
$quote['notes']
)
) ?>

</p>

<?php else: ?>

<div class="alert alert-warning">

Supplier has not responded yet.

</div>

<?php endif; ?>

</div>

</div>

<?php if($quote['status'] === 'quoted'): ?>

<div class="card quote-card shadow-sm mt-4">

<div class="card-body">

<form method="POST">

<button
type="submit"
name="accept"
class="btn btn-success">

Accept Quotation

</button>

<button
type="submit"
name="reject"
class="btn btn-danger">

Reject Quotation

</button>

</form>

</div>

</div>

<?php endif; ?>

<?php if(
$quote['status'] === 'accepted'
&&
$quote['order_id']
): ?>

<div class="card quote-card shadow-sm mt-4">

<div class="card-body">

<a
href="../orders/order-details.php?id=<?= $quote['order_id'] ?>"
class="btn btn-primary">

View Generated Order

</a>

</div>

</div>

<?php endif; ?>

</div>

</body>
</html>