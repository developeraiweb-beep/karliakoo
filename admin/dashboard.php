<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$user = currentUser();

/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalUsers = $conn->query("
    SELECT COUNT(*) total
    FROM users
")->fetch_assoc()['total'] ?? 0;

$totalSellers = $conn->query("
    SELECT COUNT(*) total
    FROM users
    WHERE role='seller'
")->fetch_assoc()['total'] ?? 0;

$totalAgents = $conn->query("
    SELECT COUNT(*) total
    FROM users
    WHERE role='agent'
")->fetch_assoc()['total'] ?? 0;

$totalOrders = $conn->query("
    SELECT COUNT(*) total
    FROM orders
")->fetch_assoc()['total'] ?? 0;

$pendingOrders = $conn->query("
    SELECT COUNT(*) total
    FROM orders
    WHERE order_status='pending'
")->fetch_assoc()['total'] ?? 0;

$totalRevenue = $conn->query("
    SELECT COALESCE(SUM(amount),0) total
    FROM payments
    WHERE payment_status='paid'
")->fetch_assoc()['total'] ?? 0;

$totalShops = 0;

if($conn->query("SHOW TABLES LIKE 'shops'")->num_rows)
{
    $totalShops = $conn->query("
        SELECT COUNT(*) total
        FROM shops
    ")->fetch_assoc()['total'];
}

$pendingWithdrawals = 0;

if($conn->query("SHOW TABLES LIKE 'withdrawals'")->num_rows)
{
    $pendingWithdrawals = $conn->query("
        SELECT COUNT(*) total
        FROM withdrawals
        WHERE status='pending'
    ")->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| RECENT ORDERS
|--------------------------------------------------------------------------
*/
$recentOrders = $conn->query("
    SELECT
        id,
        order_number,
        total_amount,
        created_at
    FROM orders
    ORDER BY id DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Admin Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f4f6f9;
}

.sidebar-card,
.stat-card{
    background:#fff;
    border-radius:15px;
    padding:20px;
    box-shadow:0 2px 12px rgba(0,0,0,.08);
}

.stat-number{
    font-size:30px;
    font-weight:700;
}

.card-title{
    color:#6c757d;
    font-size:14px;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2 class="mb-1">
Admin Dashboard
</h2>

<p class="text-muted mb-0">
Welcome back,
<strong><?= htmlspecialchars($user['full_name']) ?></strong>
</p>

</div>

<div>

<a href="orders.php" class="btn btn-primary">
Manage Orders
</a>

<a href="users.php" class="btn btn-dark">
Users
</a>

</div>

</div>

<!-- KPI CARDS -->

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="stat-card">
<div class="card-title">Total Users</div>
<div class="stat-number">
<?= number_format($totalUsers) ?>
</div>
</div>
</div>

<div class="col-md-3">
<div class="stat-card">
<div class="card-title">Sellers</div>
<div class="stat-number">
<?= number_format($totalSellers) ?>
</div>
</div>
</div>

<div class="col-md-3">
<div class="stat-card">
<div class="card-title">Agents</div>
<div class="stat-number">
<?= number_format($totalAgents) ?>
</div>
</div>
</div>

<div class="col-md-3">
<div class="stat-card">
<div class="card-title">Shops</div>
<div class="stat-number">
<?= number_format($totalShops) ?>
</div>
</div>
</div>

</div>

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="stat-card">
<div class="card-title">Total Orders</div>
<div class="stat-number">
<?= number_format($totalOrders) ?>
</div>
</div>
</div>

<div class="col-md-3">
<div class="stat-card">
<div class="card-title">Pending Orders</div>
<div class="stat-number text-warning">
<?= number_format($pendingOrders) ?>
</div>
</div>
</div>

<div class="col-md-3">
<div class="stat-card">
<div class="card-title">Revenue</div>
<div class="stat-number text-success">
TZS <?= number_format($totalRevenue,2) ?>
</div>
</div>
</div>

<div class="col-md-3">
<div class="stat-card">
<div class="card-title">Pending Withdrawals</div>
<div class="stat-number text-danger">
<?= number_format($pendingWithdrawals) ?>
</div>
</div>
</div>

</div>

<!-- QUICK ACTIONS -->

<div class="sidebar-card mb-4">

<h5 class="mb-3">
Quick Actions
</h5>

<div class="d-flex flex-wrap gap-2">

<a href="products.php" class="btn btn-outline-primary">
Products
</a>

<a href="shops.php" class="btn btn-outline-success">
Shops
</a>

<a href="orders.php" class="btn btn-outline-dark">
Orders
</a>

<a href="payments.php" class="btn btn-outline-warning">
Payments
</a>

<a href="withdrawals.php" class="btn btn-outline-danger">
Withdrawals
</a>

<a href="support-dashboard.php" class="btn btn-outline-info">
Support
</a>

<a href="accounting-dashboard.php" class="btn btn-outline-secondary">
Accounting
</a>

<a href="b2b-dashboard.php" class="btn btn-outline-dark">
B2B Marketplace
</a>

<a href="Finance-dashboard.php" class="btn btn-outline-primary">
    Finance
</a>

<a href="system-analytics.php" class="btn btn-outline-info">
    Analytics
</a>

<a href="seller-wallets.php" class="btn btn-outline-secondary">
    Seller Wallet
</a>

<a href="promotions.php" class="btn btn-outline-danger">
    promotions
</a>

</div>

</div>

<!-- RECENT ORDERS -->

<div class="sidebar-card">

<h5 class="mb-3">
Recent Orders
</h5>

<div class="table-responsive">

<table class="table table-hover">

<thead>
<tr>
<th>Order</th>
<th>Amount</th>
<th>Date</th>
<th></th>
</tr>
</thead>

<tbody>

<?php while($order = $recentOrders->fetch_assoc()): ?>

<tr>

<td>
<?= htmlspecialchars($order['order_number']) ?>
</td>

<td>
TZS <?= number_format($order['total_amount'],2) ?>
</td>

<td>
<?= date('d M Y', strtotime($order['created_at'])) ?>
</td>

<td>

<a
href="order-details.php?id=<?= $order['id'] ?>"
class="btn btn-sm btn-primary">

View

</a>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>