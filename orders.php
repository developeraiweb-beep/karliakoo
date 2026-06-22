<?php

require_once "config/db.php";
require_once "includes/auth.php";

requireLogin();

$user_id = (int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Cancel Order
|--------------------------------------------------------------------------
*/
if (
    isset($_GET['cancel']) &&
    is_numeric($_GET['cancel'])
) {

    $order_id = (int)$_GET['cancel'];

    $stmt = $conn->prepare("
        UPDATE orders
        SET order_status='cancelled'
        WHERE id=?
        AND user_id=?
        AND order_status='pending'
    ");

    $stmt->bind_param(
        "ii",
        $order_id,
        $user_id
    );

    $stmt->execute();

    header("Location: orders.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Orders
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT *
    FROM orders
    WHERE user_id=?
    ORDER BY id DESC
");

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$orders = $stmt->get_result();

function badgeColor($status)
{
    return match($status){

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

<meta charset="UTF-8">
<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>My Orders | Karliakoo</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f5f5;
}

.order-card{
    background:#fff;
    border-radius:12px;
    padding:20px;
    margin-bottom:20px;
}

</style>

</head>

<body>

<div class="container py-5">

<h2 class="mb-4">

<i class="fa fa-box"></i>
 My Orders

</h2>

<?php if($orders->num_rows > 0): ?>

<?php while($order = $orders->fetch_assoc()): ?>

<div class="order-card shadow-sm">

<div class="row align-items-center">

<div class="col-md-3">

<strong>
Order #
</strong>

<br>

<?= htmlspecialchars(
$order['order_number']
) ?>

</div>

<div class="col-md-2">

<strong>
Amount
</strong>

<br>

TZS
<?= number_format(
$order['total_amount']
) ?>

</div>

<div class="col-md-2">

<strong>
Payment
</strong>

<br>

<?php if(
$order['payment_status']
=== 'paid'
): ?>

<span class="badge bg-success">
Paid
</span>

<?php else: ?>

<span class="badge bg-warning">
Pending
</span>

<?php endif; ?>

</div>

<div class="col-md-2">

<strong>
Status
</strong>

<br>

<span class="badge bg-<?= badgeColor(
$order['order_status']
) ?>">

<?= ucfirst(
$order['order_status']
) ?>

</span>

</div>

<div class="col-md-3 text-md-end">

<a
href="order-details.php?id=<?= $order['id'] ?>"
class="btn btn-primary btn-sm">

View

</a>

<?php if(
$order['order_status']
=== 'pending'
): ?>

<a
href="?cancel=<?= $order['id'] ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Cancel this order?');">

Cancel

</a>

<?php endif; ?>

</div>

</div>

<hr>

<div class="small text-muted">

Placed:
<?= date(
"d M Y H:i",
strtotime(
$order['created_at']
)
) ?>

</div>

</div>

<?php endwhile; ?>

<?php else: ?>

<div class="alert alert-info">

You have not placed any orders yet.

</div>

<a
href="products.php"
class="btn btn-primary">

Start Shopping

</a>

<?php endif; ?>

</div>

</body>
</html>