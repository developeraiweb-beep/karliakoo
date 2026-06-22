<?php

declare(strict_types=1);

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

if(!$user)
{
    header("Location: ../login.php");
    exit;
}

$sellerId = (int)$user['id'];

$orderId =
(int)($_GET['id'] ?? 0);

if($orderId <= 0)
{
    die("Invalid order.");
}

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if(empty($_SESSION['csrf_token']))
{
    $_SESSION['csrf_token'] =
    bin2hex(
        random_bytes(32)
    );
}

$csrfToken =
$_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| VERIFY SHOP
|--------------------------------------------------------------------------
*/

$shopStmt = $conn->prepare("
    SELECT
        id,
        shop_name,
        status,
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

if(!$shop)
{
    die("Shop not found.");
}

if(
    $shop['status'] !== 'approved'
)
{
    die("Shop not approved.");
}

if(
    (int)$shop['suspended'] === 1
)
{
    die("Shop suspended.");
}

$shopId =
(int)$shop['id'];

/*
|--------------------------------------------------------------------------
| VERIFY ORDER BELONGS TO SHOP
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

if(
    $verifyStmt
    ->get_result()
    ->num_rows === 0
)
{
    die(
        "Access denied."
    );
}

/*
|--------------------------------------------------------------------------
| ORDER INFORMATION
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
        a.phone AS address_phone

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

if(!$order)
{
    die(
        "Order not found."
    );
}

/*
|--------------------------------------------------------------------------
| PAYMENT INFORMATION
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
| DELIVERY INFORMATION
|--------------------------------------------------------------------------
*/

$deliveryStmt = $conn->prepare("
    SELECT

        d.*,

        u.full_name rider_name,
        u.phone rider_phone

    FROM deliveries d

    LEFT JOIN users u
    ON u.id = d.rider_id

    WHERE d.order_id = ?

    LIMIT 1
");

$deliveryStmt->bind_param(
    "i",
    $orderId
);

$deliveryStmt->execute();

$delivery =
$deliveryStmt
->get_result()
->fetch_assoc();

/*
|--------------------------------------------------------------------------
| ORDER ITEMS FOR THIS SHOP ONLY
|--------------------------------------------------------------------------
*/

$itemStmt = $conn->prepare("
    SELECT

        oi.*,

        p.name,
        p.image,
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

$orderItems =
$itemStmt
->get_result();

/*
|--------------------------------------------------------------------------
| ORDER HISTORY
|--------------------------------------------------------------------------
*/

$historyStmt = $conn->prepare("
    SELECT

        h.*,

        u.full_name

    FROM order_status_history h

    LEFT JOIN users u
    ON u.id = h.changed_by

    WHERE h.order_id = ?

    ORDER BY h.id DESC
");

$historyStmt->bind_param(
    "i",
    $orderId
);

$historyStmt->execute();

$orderHistory =
$historyStmt
->get_result();


$orderTotal = 0;
$itemCount = 0;

$orderItems->data_seek(0);

while($row = $orderItems->fetch_assoc())
{
    $itemCount++;

    $orderTotal +=
    (
        (float)$row['price']
        *
        (int)$row['quantity']
    );
}

$orderItems->data_seek(0);

$statusColors = [

    'pending'    => 'warning',
    'processing' => 'info',
    'packed'     => 'primary',
    'shipped'    => 'secondary',
    'delivered'  => 'success',
    'cancelled'  => 'danger'
];

$paymentColors = [

    'pending'  => 'warning',
    'paid'     => 'success',
    'failed'   => 'danger',
    'refunded' => 'dark'
];

/*
|--------------------------------------------------------------------------
| STATUS UPDATE
|--------------------------------------------------------------------------
*/

$allowedTransitions = [

    'pending' => [
        'processing',
        'cancelled'
    ],

    'processing' => [
        'packed',
        'cancelled'
    ],

    'packed' => [
        'shipped'
    ],

    'shipped' => [
        'delivered'
    ],

    'delivered' => [],

    'cancelled' => []
];

$statusSuccess = '';
$statusError = '';

if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['update_status'])
)
{
    if(
        empty($_POST['csrf_token'])
        ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    )
    {
        $statusError =
        "Invalid security token.";
    }
    else
    {
        $newStatus =
        trim(
            $_POST['new_status']
        );

        $currentStatus =
        $order['order_status'];

        if(
            !in_array(
                $newStatus,
                $allowedTransitions[
                    $currentStatus
                ] ?? []
            )
        )
        {
            $statusError =
            "Invalid status transition.";
        }
        else
        {
            try
            {
                $conn->begin_transaction();

                $updateStmt =
                $conn->prepare("
                    UPDATE orders
                    SET order_status = ?
                    WHERE id = ?
                    LIMIT 1
                ");

                $updateStmt->bind_param(
                    "si",
                    $newStatus,
                    $orderId
                );

                $updateStmt->execute();

                $historyInsert =
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
                        ?, ?, ?, ?, ?
                    )
                ");

                $note =
                trim(
                    $_POST['notes']
                    ?? ''
                );

                $historyInsert->bind_param(
                    "iisss",
                    $orderId,
                    $sellerId,
                    $currentStatus,
                    $newStatus,
                    $note
                );

                $historyInsert->execute();

                $conn->commit();

                $statusSuccess =
                "Order updated successfully.";

                $order['order_status'] =
                $newStatus;
            }
            catch(Exception $e)
            {
                $conn->rollback();

                $statusError =
                $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

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
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f7fb;
}

.card-box{
    border:none;
    border-radius:16px;
    box-shadow:0 2px 12px rgba(0,0,0,.08);
}

.product-image{
    width:60px;
    height:60px;
    object-fit:cover;
    border-radius:8px;
}

.metric-card{
    border:none;
    border-radius:16px;
    text-align:center;
    box-shadow:0 2px 12px rgba(0,0,0,.08);
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>

Order

#<?= htmlspecialchars(
$order['order_number']
) ?>

</h2>

<p class="text-muted mb-0">

<?= date(
'd M Y H:i',
strtotime(
$order['created_at']
)
) ?>

</p>

</div>

<div>

<a
href="orders.php"
class="btn btn-secondary">

Back

</a>

</div>

</div>

<!-- KPI CARDS -->

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="card metric-card">

<div class="card-body">

<h4>

<?= number_format(
$itemCount
) ?>

</h4>

<small>

Products

</small>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card metric-card">

<div class="card-body">

<h4>

TZS

<?= number_format(
$orderTotal,
2
) ?>

</h4>

<small>

Seller Revenue

</small>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card metric-card">

<div class="card-body">

<h4>

<span
class="badge bg-<?=
$statusColors[
$order['order_status']
] ?? 'secondary'
?>">

<?= ucfirst(
$order['order_status']
) ?>

</span>

</h4>

<small>

Order Status

</small>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card metric-card">

<div class="card-body">

<h4>

<span
class="badge bg-<?=
$paymentColors[
$order['payment_status']
] ?? 'secondary'
?>">

<?= ucfirst(
$order['payment_status']
) ?>

</span>

</h4>

<small>

Payment

</small>

</div>

</div>

</div>

</div>

<div class="row">

<!-- CUSTOMER -->

<div class="col-lg-4 mb-4">

<div class="card card-box">

<div class="card-header">

Customer

</div>

<div class="card-body">

<p>

<strong>Name:</strong><br>

<?= htmlspecialchars(
$order['full_name']
) ?>

</p>

<p>

<strong>Email:</strong><br>

<?= htmlspecialchars(
$order['email']
) ?>

</p>

<p>

<strong>Phone:</strong><br>

<?= htmlspecialchars(
$order['phone']
) ?>

</p>

</div>

</div>

</div>

<!-- SHIPPING -->

<div class="col-lg-4 mb-4">

<div class="card card-box">

<div class="card-header">

Shipping Address

</div>

<div class="card-body">

<p>

<strong>Recipient:</strong><br>

<?= htmlspecialchars(
(string)$order['recipient_name']
) ?>

</p>

<p>

<strong>Phone:</strong><br>

<?= htmlspecialchars(
(string)$order['address_phone']
) ?>

</p>

<p>

<strong>Region:</strong><br>

<?= htmlspecialchars(
(string)$order['region']
) ?>

</p>

<p>

<strong>District:</strong><br>

<?= htmlspecialchars(
(string)$order['district']
) ?>

</p>

<p>

<strong>Ward:</strong><br>

<?= htmlspecialchars(
(string)$order['ward']
) ?>

</p>

<p>

<strong>Street:</strong><br>

<?= htmlspecialchars(
(string)$order['street']
) ?>

</p>

</div>

</div>

</div>

<!-- PAYMENT -->

<div class="col-lg-4 mb-4">

<div class="card card-box">

<div class="card-header">

Payment

</div>

<div class="card-body">

<?php if($payment): ?>

<p>

<strong>Transaction:</strong><br>

<?= htmlspecialchars(
$payment['transaction_id']
)
?: 'N/A' ?>

</p>

<p>

<strong>Method:</strong><br>

<?= htmlspecialchars(
$payment['payment_method']
)
?: 'N/A' ?>

</p>

<p>

<strong>Amount:</strong><br>

TZS

<?= number_format(
(float)$payment['amount'],
2
) ?>

</p>

<p>

<strong>Status:</strong><br>

<?= ucfirst(
$payment['payment_status']
) ?>

</p>

<?php else: ?>

<p class="text-muted">

No payment record.

</p>

<?php endif; ?>

</div>

</div>

</div>

</div>

<!-- DELIVERY -->

<div class="card card-box mb-4">

<div class="card-header">

Delivery Information

</div>

<div class="card-body">

<?php if($delivery): ?>

<div class="row">

<div class="col-md-3">

<strong>Tracking</strong><br>

<?= htmlspecialchars(
$delivery['tracking_number']
) ?>

</div>

<div class="col-md-3">

<strong>Status</strong><br>

<?= ucfirst(
$delivery['status']
) ?>

</div>

<div class="col-md-3">

<strong>Rider</strong><br>

<?= htmlspecialchars(
$delivery['rider_name']
) ?>

</div>

<div class="col-md-3">

<strong>Phone</strong><br>

<?= htmlspecialchars(
$delivery['rider_phone']
) ?>

</div>

</div>

<?php else: ?>

<div class="text-muted">

No delivery assigned yet.

</div>

<?php endif; ?>

</div>

</div>

<!-- PRODUCTS -->

<div class="card card-box">

<div class="card-header">

Products In This Order

</div>

<div class="card-body">

<div class="table-responsive">

<table
class="table table-bordered align-middle">

<thead>

<tr>

<th>Image</th>

<th>Product</th>

<th>SKU</th>

<th>Price</th>

<th>Qty</th>

<th>Total</th>

</tr>

</thead>

<tbody>

<?php while(
$item =
$orderItems->fetch_assoc()
): ?>

<tr>

<td>

<?php if(!empty($item['image'])): ?>

<img
src="<?= htmlspecialchars(
$item['image']
) ?>"
class="product-image">

<?php endif; ?>

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

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>


<div class="card card-box mb-4">

<div class="card-header">

Order Fulfillment

</div>

<div class="card-body">

<?php if($statusSuccess): ?>

<div class="alert alert-success">

<?= htmlspecialchars(
$statusSuccess
) ?>

</div>

<?php endif; ?>

<?php if($statusError): ?>

<div class="alert alert-danger">

<?= htmlspecialchars(
$statusError
) ?>

</div>

<?php endif; ?>

<?php

$currentStatus =
$order['order_status'];

$availableStatuses =
$allowedTransitions[
    $currentStatus
] ?? [];

?>

<?php if(
    !empty(
        $availableStatuses
    )
): ?>

<form method="POST">

<input
type="hidden"
name="csrf_token"
value="<?= htmlspecialchars(
$csrfToken
) ?>">

<div class="row">

<div class="col-md-4">

<select
name="new_status"
class="form-select"
required>

<option value="">
Select Status
</option>

<?php foreach(
$availableStatuses
as $status
): ?>

<option
value="<?= $status ?>">

<?= ucfirst(
$status
) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-5">

<input
type="text"
name="notes"
class="form-control"
placeholder="Status notes">

</div>

<div class="col-md-3">

<button
type="submit"
name="update_status"
class="btn btn-primary w-100">

Update Status

</button>

</div>

</div>

</form>

<?php else: ?>

<div class="alert alert-info">

No further actions available.

</div>

<?php endif; ?>

</div>

</div>

<div class="card card-box mb-4">

<div class="card-header">

Order Timeline

</div>

<div class="card-body">

<?php if(
$orderHistory->num_rows > 0
): ?>

<div class="timeline">

<?php while(
$history =
$orderHistory->fetch_assoc()
): ?>

<div
class="border-start border-3 ps-3 mb-4">

<h6>

<?= ucfirst(
$history['old_status']
) ?>

→

<?= ucfirst(
$history['new_status']
) ?>

</h6>

<div
class="text-muted small">

<?= htmlspecialchars(
$history['full_name']
?? 'System'
) ?>

•

<?= date(
'd M Y H:i',
strtotime(
$history['created_at']
)
) ?>

</div>

<?php if(
!empty(
$history['notes']
)
): ?>

<div
class="mt-2">

<?= nl2br(
htmlspecialchars(
$history['notes']
)
) ?>

</div>

<?php endif; ?>

</div>

<?php endwhile; ?>

</div>

<?php else: ?>

<div
class="text-muted">

No history records found.

</div>

<?php endif; ?>

</div>

</div>

<a
href="invoice.php?id=<?= $orderId ?>"
class="btn btn-success">

Print Invoice

</a>