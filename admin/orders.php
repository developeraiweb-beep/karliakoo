<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$payment = trim($_GET['payment'] ?? '');

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/
$where = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {

    $where[] = "o.order_number LIKE ?";

    $params[] = "%{$search}%";

    $types .= "s";
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

$whereSql = implode(
    " AND ",
    $where
);

/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/
$stats = $conn->query("
SELECT

COUNT(*) total_orders,

COALESCE(
SUM(total_amount),
0
) total_revenue,

SUM(order_status='pending')
pending_orders,

SUM(order_status='completed')
completed_orders,

SUM(payment_status='paid')
paid_orders

FROM orders
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| TOTAL RECORDS
|--------------------------------------------------------------------------
*/
$countSql = "
SELECT COUNT(*) total

FROM orders o

WHERE {$whereSql}
";

$countStmt = $conn->prepare($countSql);

if (!empty($params)) {

    $countStmt->bind_param(
        $types,
        ...$params
    );
}

$countStmt->execute();

$totalRows =
$countStmt
->get_result()
->fetch_assoc()['total'];

$totalPages =
max(
    1,
    ceil($totalRows / $limit)
);

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

s.shop_name

FROM orders o

LEFT JOIN users u
ON u.id=o.user_id

LEFT JOIN shops s
ON s.id=o.address_id

WHERE {$whereSql}

ORDER BY o.id DESC

LIMIT ?, ?

";

$stmt = $conn->prepare($sql);

$bindTypes = $types . "ii";

$bindParams = $params;
$bindParams[] = $offset;
$bindParams[] = $limit;

$stmt->bind_param(
    $bindTypes,
    ...$bindParams
);

$stmt->execute();

$orders =
$stmt
->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width,initial-scale=1">

<title>

Order Management

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
background:#f5f6fa;
}

.card-box{
background:#fff;
padding:20px;
border-radius:12px;
}

.metric{
font-size:24px;
font-weight:700;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Marketplace Orders

</h2>

<!-- KPI -->

<div class="row g-3 mb-4">

<div class="col-md-2">

<div class="card-box shadow-sm">

<div class="metric">

<?= number_format(
$stats['total_orders']
) ?>

</div>

Orders

</div>

</div>

<div class="col-md-3">

<div class="card-box shadow-sm">

<div class="metric">

TZS

<?= number_format(
$stats['total_revenue'],
2
) ?>

</div>

Revenue

</div>

</div>

<div class="col-md-2">

<div class="card-box shadow-sm">

<div class="metric">

<?= number_format(
$stats['pending_orders']
) ?>

</div>

Pending

</div>

</div>

<div class="col-md-2">

<div class="card-box shadow-sm">

<div class="metric">

<?= number_format(
$stats['completed_orders']
) ?>

</div>

Completed

</div>

</div>

<div class="col-md-2">

<div class="card-box shadow-sm">

<div class="metric">

<?= number_format(
$stats['paid_orders']
) ?>

</div>

Paid

</div>

</div>

</div>

<!-- FILTERS -->

<div class="card-box shadow-sm mb-4">

<form method="GET">

<div class="row">

<div class="col-md-4">

<input
type="text"
name="search"
class="form-control"
placeholder="Order Number"
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-3">

<select
name="status"
class="form-select">

<option value="">
All Status
</option>

<option value="pending">
Pending
</option>

<option value="processing">
Processing
</option>

<option value="shipped">
Shipped
</option>

<option value="completed">
Completed
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

<option value="paid">
Paid
</option>

<option value="unpaid">
Unpaid
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

<!-- ORDERS TABLE -->

<div class="card-box shadow-sm">

<div class="table-responsive">

<table class="table table-hover align-middle">

<thead>

<tr>

<th>#</th>
<th>Order No</th>
<th>Buyer</th>
<th>Shop</th>
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

<?= $order['id'] ?>

</td>

<td>

<strong>

<?= htmlspecialchars(
$order['order_number']
) ?>

</strong>

</td>

<td>

<?= htmlspecialchars(
$order['buyer_name']
?? 'Unknown'
) ?>

<br>

<small>

<?= htmlspecialchars(
$order['buyer_email']
?? ''
) ?>

</small>

</td>

<td>

<?= htmlspecialchars(
$order['shop_name']
?? 'N/A'
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

$paymentColor = match(
$order['payment_status']
){

'paid'=>'success',
'refunded'=>'warning',
default=>'danger'

};

?>

<span
class="badge bg-<?= $paymentColor ?>">

<?= ucfirst(
$order['payment_status']
) ?>

</span>

</td>

<td>

<?php

$statusColor = match(
$order['order_status']
){

'completed'=>'success',
'processing'=>'primary',
'shipped'=>'info',
'cancelled'=>'danger',
default=>'warning'

};

?>

<span
class="badge bg-<?= $statusColor ?>">

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

<!-- PAGINATION -->

<nav class="mt-4">

<ul class="pagination">

<?php for($i=1;$i<=$totalPages;$i++): ?>

<li
class="page-item <?= $page==$i ? 'active':'' ?>">

<a
class="page-link"
href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&payment=<?= urlencode($payment) ?>">

<?= $i ?>

</a>

</li>

<?php endfor; ?>

</ul>

</nav>

</div>

</body>
</html>