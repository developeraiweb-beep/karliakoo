<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";

if (!isset($_SESSION['user_id']))
{
    header("Location: ../login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

$status = trim($_GET['status'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$where = "buyer_id=?";

$params = [$userId];
$types = "i";

if(!empty($status))
{
    $where .= " AND order_status=?";
    $params[] = $status;
    $types .= "s";
}

/*
|--------------------------------------------------------------------------
| ORDER STATS
|--------------------------------------------------------------------------
*/

$statuses = [
    'pending',
    'confirmed',
    'processing',
    'shipped',
    'delivered',
    'cancelled'
];

$stats = [];

foreach($statuses as $st)
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) total
        FROM b2b_orders
        WHERE buyer_id=?
        AND order_status=?
    ");

    $stmt->bind_param("is", $userId, $st);
    $stmt->execute();

    $stats[$st] =
    (int)$stmt->get_result()->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| COUNT ORDERS
|--------------------------------------------------------------------------
*/

$countStmt = $conn->prepare("
SELECT COUNT(*) total
FROM b2b_orders
WHERE {$where}
");

$countStmt->bind_param($types, ...$params);
$countStmt->execute();

$totalOrders =
(int)$countStmt->get_result()->fetch_assoc()['total'];

$totalPages = (int)ceil($totalOrders / $perPage);

/*
|--------------------------------------------------------------------------
| LOAD ORDERS
|--------------------------------------------------------------------------
*/

$sql = "
SELECT *
FROM b2b_orders
WHERE {$where}
ORDER BY id DESC
LIMIT {$offset}, {$perPage}
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

$orders = $stmt->get_result();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="utf-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>My Orders</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

</head>

<body>

<div class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>My Orders</h2>

<p class="text-muted">Track all your B2B purchases</p>

</div>

<a href="products.php" class="btn btn-primary">

Browse Products

</a>

</div>

<!-- STATS -->

<div class="row mb-4">

<?php foreach($stats as $key => $value): ?>

<div class="col-md">

<div class="card text-center">

<div class="card-body">

<h4><?= $value ?></h4>

<small><?= ucfirst($key) ?></small>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

<!-- FILTER -->

<form method="GET" class="row g-2 mb-4">

<div class="col-md-4">

<select name="status" class="form-select">

<option value="">All Orders</option>

<?php foreach($statuses as $st): ?>

<option value="<?= $st ?>" <?= $status==$st?'selected':'' ?>>

<?= ucfirst($st) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-2">

<button class="btn btn-primary w-100">Filter</button>

</div>

<div class="col-md-2">

<a href="orders.php" class="btn btn-secondary w-100">Reset</a>

</div>

</form>

<!-- ORDERS LIST -->

<div class="card shadow-sm">

<div class="card-header">

Order List

</div>

<div class="card-body p-0">

<?php if($orders->num_rows > 0): ?>

<div class="table-responsive">

<table class="table table-hover mb-0">

<thead>

<tr>

<th>Order #</th>
<th>Total</th>
<th>Payment</th>
<th>Status</th>
<th>Date</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php while($order = $orders->fetch_assoc()): ?>

<tr>

<td>

<strong><?= htmlspecialchars($order['order_number']) ?></strong>

</td>

<td>

TZS <?= number_format((float)$order['total_amount']) ?>

</td>

<td>

<span class="badge bg-<?= $order['payment_status']=='paid'?'success':'warning' ?>">

<?= ucfirst($order['payment_status']) ?>

</span>

</td>

<td>

<?php
$badge = 'secondary';

switch($order['order_status'])
{
    case 'pending': $badge='warning'; break;
    case 'confirmed': $badge='info'; break;
    case 'processing': $badge='primary'; break;
    case 'shipped': $badge='dark'; break;
    case 'delivered': $badge='success'; break;
    case 'cancelled': $badge='danger'; break;
}
?>

<span class="badge bg-<?= $badge ?>">

<?= ucfirst($order['order_status']) ?>

</span>

</td>

<td>

<?= date('d M Y', strtotime($order['created_at'])) ?>

</td>

<td>

<a href="order-details.php?id=<?= (int)$order['id'] ?>" class="btn btn-sm btn-outline-primary">

View

</a>

<?php if($order['payment_status'] === 'pending'): ?>

<a href="payment.php?order_id=<?= (int)$order['id'] ?>" class="btn btn-sm btn-success">

Pay

</a>

<?php endif; ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

<?php else: ?>

<div class="text-center py-5">

<i class="bi bi-cart-x fs-1 text-muted"></i>

<h5 class="mt-3">No Orders Found</h5>

<p class="text-muted">You have not placed any orders yet.</p>

</div>

<?php endif; ?>

</div>

</div>

<!-- PAGINATION -->

<?php if($totalPages > 1): ?>

<nav class="mt-4">

<ul class="pagination justify-content-center">

<?php for($i=1; $i<=$totalPages; $i++): ?>

<li class="page-item <?= $page==$i?'active':'' ?>">

<a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($status) ?>">

<?= $i ?>

</a>

</li>

<?php endfor; ?>

</ul>

</nav>

<?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
