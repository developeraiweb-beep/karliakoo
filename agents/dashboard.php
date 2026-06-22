<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['agent']);

$user = currentUser();
$agent_id = (int)$user['id'];

/*
|--------------------------------------------------------------------------
| DASHBOARD STATISTICS
|--------------------------------------------------------------------------
*/

/* Total Referrals */
$stmt = $conn->prepare("
    SELECT COUNT(*) total
    FROM agent_referrals
    WHERE agent_id=?
");
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$total_referrals = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

/* Active Referrals */
$stmt = $conn->prepare("
    SELECT COUNT(*)
    active
    FROM agent_referrals
    WHERE agent_id=?
    AND status='active'
");
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$active_referrals = $stmt->get_result()->fetch_assoc()['active'] ?? 0;

/* Orders */
$stmt = $conn->prepare("
    SELECT COUNT(*) total_orders
    FROM orders
    WHERE agent_id=?
");
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$total_orders = $stmt->get_result()->fetch_assoc()['total_orders'] ?? 0;

/* Sales */
$stmt = $conn->prepare("
    SELECT COALESCE(
        SUM(total_amount),
        0
    ) total_sales
    FROM orders
    WHERE agent_id=?
");
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$total_sales = $stmt->get_result()->fetch_assoc()['total_sales'] ?? 0;

/* Commission Earned */
$stmt = $conn->prepare("
    SELECT COALESCE(
        SUM(commission_amount),
        0
    ) earned
    FROM agent_commissions
    WHERE agent_id=?
    AND status='paid'
");
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$commission_paid = $stmt->get_result()->fetch_assoc()['earned'] ?? 0;

/* Pending Commission */
$stmt = $conn->prepare("
    SELECT COALESCE(
        SUM(commission_amount),
        0
    ) pending
    FROM agent_commissions
    WHERE agent_id=?
    AND status='pending'
");
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$commission_pending = $stmt->get_result()->fetch_assoc()['pending'] ?? 0;

/* Withdrawn */
$stmt = $conn->prepare("
    SELECT COALESCE(
        SUM(amount),
        0
    ) withdrawn
    FROM withdraw_requests
    WHERE agent_id=?
    AND status='approved'
");
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$withdrawn = $stmt->get_result()->fetch_assoc()['withdrawn'] ?? 0;

$available_balance =
    $commission_paid - $withdrawn;

/*
|--------------------------------------------------------------------------
| RECENT REFERRALS
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        u.full_name,
        u.email,
        ar.created_at
    FROM agent_referrals ar
    JOIN users u
        ON u.id = ar.referred_user_id
    WHERE ar.agent_id=?
    ORDER BY ar.id DESC
    LIMIT 10
");
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$recent_referrals =
    $stmt->get_result();

/*
|--------------------------------------------------------------------------
| RECENT ORDERS
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        id,
        order_number,
        total_amount,
        status,
        created_at
    FROM orders
    WHERE agent_id=?
    ORDER BY id DESC
    LIMIT 10
");
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$recent_orders =
    $stmt->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Agent Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f6fa;
}

.stat-card{
    background:#fff;
    border-radius:12px;
    padding:20px;
    height:100%;
}

.quick-link{
    text-decoration:none;
}

</style>

</head>
<body>

<div class="container-fluid py-4">

<h2 class="mb-4">
Welcome,
<?= htmlspecialchars($user['full_name']) ?>
</h2>

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="stat-card shadow-sm">
<h3><?= number_format($total_referrals) ?></h3>
<p>Total Referrals</p>
</div>
</div>

<div class="col-md-3">
<div class="stat-card shadow-sm">
<h3><?= number_format($active_referrals) ?></h3>
<p>Active Referrals</p>
</div>
</div>

<div class="col-md-3">
<div class="stat-card shadow-sm">
<h3><?= number_format($total_orders) ?></h3>
<p>Orders Generated</p>
</div>
</div>

<div class="col-md-3">
<div class="stat-card shadow-sm">
<h3>TZS <?= number_format($total_sales,2) ?></h3>
<p>Total Sales</p>
</div>
</div>

</div>

<div class="row g-3 mb-4">

<div class="col-md-4">
<div class="stat-card shadow-sm">
<h3 class="text-success">
TZS <?= number_format($commission_paid,2) ?>
</h3>
<p>Commission Earned</p>
</div>
</div>

<div class="col-md-4">
<div class="stat-card shadow-sm">
<h3 class="text-warning">
TZS <?= number_format($commission_pending,2) ?>
</h3>
<p>Pending Commission</p>
</div>
</div>

<div class="col-md-4">
<div class="stat-card shadow-sm">
<h3 class="text-primary">
TZS <?= number_format($available_balance,2) ?>
</h3>
<p>Available Balance</p>
</div>
</div>

</div>

<div class="card shadow-sm mb-4">

<div class="card-header">
Quick Actions
</div>

<div class="card-body">

<a href="referrals.php" class="btn btn-primary">
Referrals
</a>

<a href="orders.php" class="btn btn-success">
Orders
</a>

<a href="earnings.php" class="btn btn-warning">
Earnings
</a>

<a href="withdraw-request.php" class="btn btn-info">
Withdraw
</a>

<a href="leaderboard.php" class="btn btn-dark">
Leaderboard
</a>

<a href="support.php" class="btn btn-danger">
Support
</a>

</div>

</div>

<div class="row">

<div class="col-lg-6">

<div class="card shadow-sm">

<div class="card-header">
Recent Referrals
</div>

<div class="table-responsive">

<table class="table table-hover mb-0">

<thead>
<tr>
<th>Name</th>
<th>Email</th>
<th>Date</th>
</tr>
</thead>

<tbody>

<?php while($row = $recent_referrals->fetch_assoc()): ?>

<tr>

<td>
<?= htmlspecialchars($row['full_name']) ?>
</td>

<td>
<?= htmlspecialchars($row['email']) ?>
</td>

<td>
<?= date(
'd M Y',
strtotime($row['created_at'])
) ?>
</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

<div class="col-lg-6">

<div class="card shadow-sm">

<div class="card-header">
Recent Orders
</div>

<div class="table-responsive">

<table class="table table-hover mb-0">

<thead>

<tr>

<th>Order</th>
<th>Amount</th>
<th>Status</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php while($order = $recent_orders->fetch_assoc()): ?>

<tr>

<td>
<?= htmlspecialchars($order['order_number']) ?>
</td>

<td>
TZS <?= number_format($order['total_amount'],2) ?>
</td>

<td>
<?= ucfirst($order['status']) ?>
</td>

<td>
<?= date(
'd M Y',
strtotime($order['created_at'])
) ?>
</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

</div>

</body>
</html>