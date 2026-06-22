<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['seller']);

$user = currentUser();

$seller_id = (int)$user['id'];

$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = [
    "o.supplier_id=?"
];

$params = [$seller_id];
$types = "i";

if (!empty($status)) {

    $where[] = "o.order_status=?";

    $params[] = $status;
    $types .= "s";
}

if (!empty($search)) {

    $where[] = "(
        o.order_number LIKE ?
        OR u.full_name LIKE ?
        OR s.shop_name LIKE ?
    )";

    $like = "%{$search}%";

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

    $types .= "sss";
}

$whereSQL = implode(" AND ", $where);

/*
|--------------------------------------------------------------------------
| ORDERS
|--------------------------------------------------------------------------
*/
$sql = "

SELECT

o.*,

u.full_name buyer_name,
u.email buyer_email,

s.shop_name,

r.quote_number

FROM b2b_orders o

LEFT JOIN users u
ON u.id = o.buyer_id

LEFT JOIN shops s
ON s.id = o.shop_id

LEFT JOIN rfq_requests r
ON r.id = o.rfq_id

WHERE {$whereSQL}

ORDER BY o.id DESC

";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    $types,
    ...$params
);

$stmt->execute();

$orders = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/
$statsStmt = $conn->prepare("
SELECT

COUNT(*) total_orders,

SUM(order_status='pending') pending_orders,

SUM(order_status='confirmed') confirmed_orders,

SUM(order_status='processing') processing_orders,

SUM(order_status='shipped') shipped_orders,

SUM(order_status='delivered') delivered_orders

FROM b2b_orders

WHERE supplier_id=?
");

$statsStmt->bind_param(
    "i",
    $seller_id
);

$statsStmt->execute();

$stats =
    $statsStmt
    ->get_result()
    ->fetch_assoc();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>
B2B Orders
</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f6fa;
}

.stat-card{
    background:#fff;
    border-radius:12px;
    padding:20px;
}

</style>

</head>
<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Wholesale Orders

</h2>

<div class="row g-3 mb-4">

<div class="col-md-2">

<div class="stat-card shadow-sm">

<h4>

<?= number_format($stats['total_orders']) ?>

</h4>

<p>Total Orders</p>

</div>

</div>

<div class="col-md-2">

<div class="stat-card shadow-sm">

<h4 class="text-warning">

<?= number_format($stats['pending_orders']) ?>

</h4>

<p>Pending</p>

</div>

</div>

<div class="col-md-2">

<div class="stat-card shadow-sm">

<h4 class="text-primary">

<?= number_format($stats['confirmed_orders']) ?>

</h4>

<p>Confirmed</p>

</div>

</div>

<div class="col-md-2">

<div class="stat-card shadow-sm">

<h4 class="text-info">

<?= number_format($stats['processing_orders']) ?>

</h4>

<p>Processing</p>

</div>

</div>

<div class="col-md-2">

<div class="stat-card shadow-sm">

<h4 class="text-dark">

<?= number_format($stats['shipped_orders']) ?>

</h4>

<p>Shipped</p>

</div>

</div>

<div class="col-md-2">

<div class="stat-card shadow-sm">

<h4 class="text-success">

<?= number_format($stats['delivered_orders']) ?>

</h4>

<p>Delivered</p>

</div>

</div>

</div>

<div class="card shadow-sm mb-4">

<div class="card-body">

<form method="GET">

<div class="row">

<div class="col-md-5">

<input
type="text"
name="search"
value="<?= htmlspecialchars($search) ?>"
class="form-control"
placeholder="Search orders">

</div>

<div class="col-md-4">

<select
name="status"
class="form-select">

<option value="">
All Status
</option>

<option value="pending">
Pending
</option>

<option value="confirmed">
Confirmed
</option>

<option value="processing">
Processing
</option>

<option value="shipped">
Shipped
</option>

<option value="delivered">
Delivered
</option>

<option value="cancelled">
Cancelled
</option>

</select>

</div>

<div class="col-md-3">

<button
class="btn btn-primary w-100">

Filter

</button>

</div>

</div>

</form>

</div>

</div>

<div class="card shadow-sm">

<div class="card-header">

Wholesale Orders

</div>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Order</th>
<th>RFQ</th>
<th>Buyer</th>
<th>Amount</th>
<th>Payment</th>
<th>Status</th>
<th>Date</th>
<th></th>

</tr>

</thead>

<tbody>

<?php while($order = $orders->fetch_assoc()): ?>

<tr>

<td>

<strong>

<?= htmlspecialchars($order['order_number']) ?>

</strong>

</td>

<td>

<?= htmlspecialchars($order['quote_number']) ?>

</td>

<td>

<?= htmlspecialchars($order['buyer_name']) ?>

<br>

<small>

<?= htmlspecialchars($order['buyer_email']) ?>

</small>

</td>

<td>

TZS

<?= number_format(
$order['total_amount'],
2
) ?>

</td>

<td>

<?php

$paymentColor = match($order['payment_status']) {

'paid' => 'success',

'partial' => 'warning',

'pending' => 'secondary',

'refunded' => 'danger',

default => 'dark'

};

?>

<span class="badge bg-<?= $paymentColor ?>">

<?= ucfirst($order['payment_status']) ?>

</span>

</td>

<td>

<?php

$statusColor = match($order['order_status']) {

'pending' => 'warning',

'confirmed' => 'primary',

'processing' => 'info',

'shipped' => 'dark',

'delivered' => 'success',

'cancelled' => 'danger',

default => 'secondary'

};

?>

<span class="badge bg-<?= $statusColor ?>">

<?= ucfirst($order['order_status']) ?>

</span>

</td>

<td>

<?= date(
'd M Y',
strtotime($order['created_at'])
) ?>

</td>

<td>

<a
href="b2b-order-details.php?id=<?= $order['id'] ?>"
class="btn btn-sm btn-primary">

Manage

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