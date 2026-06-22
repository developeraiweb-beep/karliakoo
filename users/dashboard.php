<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";

if(!isset($_SESSION['user_id']))
{
    header("Location: ../login.php");
    exit;
}

$userId =
(int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| USER INFO
|--------------------------------------------------------------------------
*/

$userStmt =
$conn->prepare("
    SELECT *
    FROM users
    WHERE id=?
    LIMIT 1
");

$userStmt->bind_param(
    "i",
    $userId
);

$userStmt->execute();

$user =
$userStmt
->get_result()
->fetch_assoc();

if(!$user)
{
    session_destroy();

    header("Location: ../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$orderCount = 0;
$wishlistCount = 0;
$cartCount = 0;
$addressCount = 0;

/* Orders */

$orderQuery =
$conn->prepare("
    SELECT COUNT(*) total
    FROM orders
    WHERE user_id=?
");

$orderQuery->bind_param(
    "i",
    $userId
);

$orderQuery->execute();

$orderCount =
(int)$orderQuery
->get_result()
->fetch_assoc()['total'];

/* Wishlist */

$wishlistQuery =
$conn->prepare("
    SELECT COUNT(*) total
    FROM wishlists
    WHERE user_id=?
");

$wishlistQuery->bind_param(
    "i",
    $userId
);

$wishlistQuery->execute();

$wishlistCount =
(int)$wishlistQuery
->get_result()
->fetch_assoc()['total'];

/* Cart */

$cartQuery =
$conn->prepare("
    SELECT COUNT(*) total
    FROM cart
    WHERE user_id=?
");

$cartQuery->bind_param(
    "i",
    $userId
);

$cartQuery->execute();

$cartCount =
(int)$cartQuery
->get_result()
->fetch_assoc()['total'];

/* Addresses */

$addressQuery =
$conn->prepare("
    SELECT COUNT(*) total
    FROM addresses
    WHERE user_id=?
");

$addressQuery->bind_param(
    "i",
    $userId
);

$addressQuery->execute();

$addressCount =
(int)$addressQuery
->get_result()
->fetch_assoc()['total'];

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

My Dashboard

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">

<style>

.stat-card{
border:none;
transition:.3s;
box-shadow:0 3px 15px rgba(0,0,0,.08);
}

.stat-card:hover{
transform:translateY(-5px);
}

</style>

</head>

<body>

<div class="container py-5">

<div class="row mb-4">

<div class="col-md-12">

<div class="card shadow-sm">

<div class="card-body">

<h3>

Welcome,

<?= htmlspecialchars(
$user['full_name']
?? $user['name']
?? 'Customer'
) ?>

</h3>

<p class="text-muted mb-0">

<?= htmlspecialchars(
$user['email']
) ?>

</p>

</div>

</div>

</div>

</div>

<div class="row">

<div class="col-lg-3 col-md-6 mb-4">

<div class="card stat-card h-100">

<div class="card-body text-center">

<i
class="bi bi-bag-check fs-1 text-primary"> </i>

<h2 class="mt-2">

<?= number_format($orderCount) ?>

</h2>

<p class="mb-0">

Orders

</p>

</div>

<div class="card-footer bg-white text-center">

<a
href="../my-orders.php"
class="btn btn-sm btn-outline-primary">

View Orders

</a>

</div>

</div>

</div>

<div class="col-lg-3 col-md-6 mb-4">

<div class="card stat-card h-100">

<div class="card-body text-center">

<i
class="bi bi-heart-fill fs-1 text-danger"> </i>

<h2 class="mt-2">

<?= number_format($wishlistCount) ?>

</h2>

<p class="mb-0">

Wishlist Items

</p>

</div>

<div class="card-footer bg-white text-center">

<a
href="../wishlist.php"
class="btn btn-sm btn-outline-danger">

My Wishlist

</a>

</div>

</div>

</div>

<div class="col-lg-3 col-md-6 mb-4">

<div class="card stat-card h-100">

<div class="card-body text-center">

<i
class="bi bi-cart-fill fs-1 text-success"> </i>

<h2 class="mt-2">

<?= number_format($cartCount) ?>

</h2>

<p class="mb-0">

Cart Items

</p>

</div>

<div class="card-footer bg-white text-center">

<a
href="../cart.php"
class="btn btn-sm btn-outline-success">

View Cart

</a>

</div>

</div>

</div>

<div class="col-lg-3 col-md-6 mb-4">

<div class="card stat-card h-100">

<div class="card-body text-center">

<i
class="bi bi-geo-alt-fill fs-1 text-warning"> </i>

<h2 class="mt-2">

<?= number_format($addressCount) ?>

</h2>

<p class="mb-0">

Saved Addresses

</p>

</div>

<div class="card-footer bg-white text-center">

<a
href="../addresses.php"
class="btn btn-sm btn-outline-warning">

Manage Addresses

</a>

</div>

</div>

</div>

</div>

<!-- QUICK ACTIONS -->

<div class="card shadow-sm mb-4">

<div class="card-header">

<h5 class="mb-0">

Quick Actions

</h5>

</div>

<div class="card-body">

<div class="row g-3">

<div class="col-md-3">

<a
href="../products.php"
class="btn btn-primary w-100">

<i class="bi bi-shop"></i>

Browse Products

</a>

</div>

<div class="col-md-3">

<a
href="../cart.php"
class="btn btn-success w-100">

<i class="bi bi-cart"></i>

Shopping Cart

</a>

</div>

<div class="col-md-3">

<a
href="../my-orders.php"
class="btn btn-info w-100">

<i class="bi bi-box"></i>

My Orders

</a>

</div>

<div class="col-md-3">

<a
href="../wishlist.php"
class="btn btn-danger w-100">

<i class="bi bi-heart"></i>

Wishlist

</a>

</div>

</div>

</div>

</div>

<div class="row">

<?php

/*
|--------------------------------------------------------------------------
| RECENT ORDERS
|--------------------------------------------------------------------------
*/

$recentOrdersStmt =
$conn->prepare("
    SELECT
        id,
        order_number,
        total_amount,
        payment_status,
        order_status,
        created_at
    FROM orders
    WHERE user_id=?
    ORDER BY id DESC
    LIMIT 5
");

$recentOrdersStmt->bind_param(
    "i",
    $userId
);

$recentOrdersStmt->execute();

$recentOrders =
$recentOrdersStmt->get_result();

?>

<div class="col-lg-8 mb-4">

<div class="card shadow-sm">

<div class="card-header d-flex justify-content-between align-items-center">

<h5 class="mb-0">

Recent Orders

</h5>

<a
href="../my-orders.php"
class="btn btn-sm btn-primary">

View All

</a>

</div>

<div class="card-body">

<?php if($recentOrders->num_rows > 0): ?>

<div class="table-responsive">

<table class="table table-hover align-middle">

<thead>

<tr>

<th>Order #</th>

<th>Date</th>

<th>Total</th>

<th>Payment</th>

<th>Status</th>

<th></th>

</tr>

</thead>

<tbody>

<?php while($order = $recentOrders->fetch_assoc()): ?>

<tr>

<td>

<?= htmlspecialchars(
$order['order_number']
?? ('ORD'.$order['id'])
) ?>

</td>

<td>

<?= date(
'd M Y',
strtotime($order['created_at'])
) ?>

</td>

<td>

TZS

<?= number_format(
(float)$order['total_amount']
) ?>

</td>

<td>

<?php

$paymentStatus =
strtolower(
$order['payment_status']
);

if($paymentStatus === 'paid')
{
echo '<span class="badge bg-success">Paid</span>';
}
elseif($paymentStatus === 'pending')
{
echo '<span class="badge bg-warning">Pending</span>';
}
else
{
echo '<span class="badge bg-secondary">'
. htmlspecialchars($order['payment_status'])
. '</span>';
}

?>

</td>

<td>

<?php

$status =
strtolower(
$order['order_status']
);

if($status === 'delivered')
{
echo '<span class="badge bg-success">Delivered</span>';
}
elseif($status === 'processing')
{
echo '<span class="badge bg-primary">Processing</span>';
}
elseif($status === 'cancelled')
{
echo '<span class="badge bg-danger">Cancelled</span>';
}
else
{
echo '<span class="badge bg-warning">'
. htmlspecialchars($order['order_status'])
. '</span>';
}

?>

</td>

<td>

<a
href="../order-details.php?id=<?= (int)$order['id'] ?>"
class="btn btn-sm btn-outline-primary">

View

</a>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

<?php else: ?>

<div class="text-center py-4">

<i
class="bi bi-bag-x fs-1 text-muted"> </i>

<p class="mt-3">

You have not placed any orders yet.

</p>

<a
href="../products.php"
class="btn btn-primary">

Start Shopping

</a>

</div>

<?php endif; ?>

</div>

</div>

</div>

<!-- ACCOUNT MANAGEMENT -->

<div class="col-lg-4 mb-4">

<div class="card shadow-sm">

<div class="card-header">

<h5 class="mb-0">

Account Management

</h5>

</div>

<div class="list-group list-group-flush">

<a
href="profile.php"
class="list-group-item list-group-item-action">

<i class="bi bi-person"></i>

My Profile

</a>

<a
href="../addresses.php"
class="list-group-item list-group-item-action">

<i class="bi bi-geo-alt"></i>

Addresses

</a>

<a
href="../wishlist.php"
class="list-group-item list-group-item-action">

<i class="bi bi-heart"></i>

Wishlist

</a>

<a
href="../cart.php"
class="list-group-item list-group-item-action">

<i class="bi bi-cart"></i>

Shopping Cart

</a>

<a
href="../my-orders.php"
class="list-group-item list-group-item-action">

<i class="bi bi-box-seam"></i>

My Orders

</a>

<a
href="../logout.php"
class="list-group-item list-group-item-action text-danger">

<i class="bi bi-box-arrow-right"></i>

Logout

</a>

</div>

</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
