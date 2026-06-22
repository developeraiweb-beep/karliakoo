<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$payment = trim($_GET['payment'] ?? '');

$where = ["1=1"];
$params = [];
$types = '';

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

if (!empty($status)) {

    $where[] = "o.order_status=?";

    $params[] = $status;
    $types .= "s";
}

if (!empty($payment)) {

    $where[] = "o.payment_status=?";

    $params[] = $payment;
    $types .= "s";
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
ON u.id=o.buyer_id

LEFT JOIN shops s
ON s.id=o.shop_id

LEFT JOIN rfq_requests r
ON r.id=o.rfq_id

WHERE {$whereSQL}

ORDER BY o.id DESC

";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$orders = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| DASHBOARD STATS
|--------------------------------------------------------------------------
*/
$stats = $conn->query("
SELECT

COUNT(*) total_orders,

SUM(total_amount) total_revenue,

SUM(order_status='pending') pending_orders,

SUM(order_status='processing') processing_orders,

SUM(order_status='shipped') shipped_orders,

SUM(order_status='delivered') delivered_orders

FROM b2b_orders
")->fetch_assoc();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

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

B2B Orders Management

</h2>

<div class="row g-3 mb-4">

<div class="col-md-2">
<div class="stat-card shadow-sm">
<h5><?= number_format($stats['total_orders']) ?></h5>
<p>Total Orders</p>
</div>
</div>

<div class="col-md-2">
<div class="stat-card shadow-sm">
<h5><?= number_format($stats['pending_orders']) ?></h5>
<p>Pending</p>
</div>
</div>

<div class="col-md-2">
<div class="stat-card shadow-sm">
<h5><?= number_format($stats['processing_orders']) ?></h5>
<p>Processing</p>
</div>
</div>

<div class="col-md-2">
<div class="stat-card shadow-sm">
<h5><?= number_format($stats['shipped_orders']) ?></h5>
<p>Shipped</p>
</div>
</div>

<div class="col-md-2">
<div class="stat-card shadow-sm">
<h5><?= number_format($stats['delivered_orders']) ?></h5>
<p>Delivered</p>
</div>
</div>

<div class="col-md-2">
<div class="stat-card shadow-sm">
<h5>

TZS

<?= number_format(
$stats['total_revenue'],
2
) ?>

</h5>

<p>Revenue</p>

</div>
</div>

</div>

<div class="card shadow-sm mb-4">

<div class="card-body">

<form method="GET">

<div class="row">

<div class="col-md-4">

<input
type="text"
name="search"
class="form-control"
placeholder="Search order, buyer or supplier"
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-3">

<select
name="status"
class="form-select">

<option value="">
All Order Status
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

<select
name="payment"
class="form-select">

<option value="">
All Payments
</option>

<option value="pending">
Pending
</option>

<option value="partial">
Partial
</option>

<option value="paid">
Paid
</option>

<option value="refunded">
Refunded
</option>

</select>

</div>

<div class="col-md-2">

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
<th>Supplier</th>
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

<?= htmlspecialchars(
$order['order_number']
) ?>

</strong>

</td>

<td>

<?= htmlspecialchars(
$order['quote_number']
) ?>

</td>

<td>

<?= htmlspecialchars(
$order['buyer_name']
) ?>

<br>

<small>

<?= htmlspecialchars(
$order['buyer_email']
) ?>

</small>

</td>

<td>

<?= htmlspecialchars(
$order['shop_name']
) ?>

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

<?= ucfirst(
$order['payment_status']
) ?>

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

<?= ucfirst(
$order['order_status']
) ?>

</span>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$order['created_at']
)
) ?>

</td>

<td>

<a
href="b2b-order-details.php?id=<?= $order['id'] ?>"
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