<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$method = trim($_GET['method'] ?? '');

/*
|--------------------------------------------------------------------------
| PAYMENT ACTIONS
|--------------------------------------------------------------------------
*/
if (
    isset($_GET['action']) &&
    isset($_GET['id'])
) {

    $paymentId = (int)$_GET['id'];

    if ($_GET['action'] === 'verify') {

        $stmt = $conn->prepare("
            UPDATE payments
            SET payment_status='paid',
                paid_at=NOW()
            WHERE id=?
        ");

        $stmt->bind_param(
            "i",
            $paymentId
        );

        $stmt->execute();
    }

    if ($_GET['action'] === 'refund') {

        $stmt = $conn->prepare("
            UPDATE payments
            SET payment_status='refunded'
            WHERE id=?
        ");

        $stmt->bind_param(
            "i",
            $paymentId
        );

        $stmt->execute();
    }

    header("Location: payments.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/
$where = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {

    $where[] = "
    (
        p.transaction_id LIKE ?
        OR o.order_number LIKE ?
        OR u.full_name LIKE ?
    )
    ";

    $like = "%{$search}%";

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

    $types .= "sss";
}

if (!empty($status)) {

    $where[] = "p.payment_status=?";

    $params[] = $status;

    $types .= "s";
}

if (!empty($method)) {

    $where[] = "p.payment_method=?";

    $params[] = $method;

    $types .= "s";
}

$whereSql = implode(
    " AND ",
    $where
);

/*
|--------------------------------------------------------------------------
| KPI
|--------------------------------------------------------------------------
*/
$stats = $conn->query("
SELECT

COUNT(*) total_payments,

COALESCE(
SUM(amount),
0
) total_volume,

SUM(payment_status='paid')
paid_count,

SUM(payment_status='failed')
failed_count,

SUM(payment_status='refunded')
refunded_count

FROM payments
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| TOTAL RECORDS
|--------------------------------------------------------------------------
*/
$countSql = "
SELECT COUNT(*) total

FROM payments p

LEFT JOIN orders o
ON o.id=p.order_id

LEFT JOIN users u
ON u.id=p.user_id

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
| PAYMENTS
|--------------------------------------------------------------------------
*/
$sql = "

SELECT

p.*,

o.order_number,

u.full_name

FROM payments p

LEFT JOIN orders o
ON o.id=p.order_id

LEFT JOIN users u
ON u.id=p.user_id

WHERE {$whereSql}

ORDER BY p.id DESC

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

$payments =
$stmt
->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>

Payment Management

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

Payment Management

</h2>

<!-- KPI -->

<div class="row g-3 mb-4">

<div class="col-md-2">
<div class="card-box">
<div class="metric">
<?= number_format($stats['total_payments']) ?>
</div>
Payments
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($stats['total_volume'],2) ?>
</div>
Volume
</div>
</div>

<div class="col-md-2">
<div class="card-box">
<div class="metric">
<?= number_format($stats['paid_count']) ?>
</div>
Paid
</div>
</div>

<div class="col-md-2">
<div class="card-box">
<div class="metric">
<?= number_format($stats['failed_count']) ?>
</div>
Failed
</div>
</div>

<div class="col-md-2">
<div class="card-box">
<div class="metric">
<?= number_format($stats['refunded_count']) ?>
</div>
Refunded
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
placeholder="Transaction / Order / User"
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-3">

<select
name="status"
class="form-select">

<option value="">All Status</option>
<option value="pending">Pending</option>
<option value="paid">Paid</option>
<option value="failed">Failed</option>
<option value="refunded">Refunded</option>

</select>

</div>

<div class="col-md-3">

<select
name="method"
class="form-select">

<option value="">All Methods</option>
<option value="M-Pesa">M-Pesa</option>
<option value="Airtel Money">Airtel Money</option>
<option value="Tigo Pesa">Tigo Pesa</option>
<option value="Bank">Bank</option>
<option value="Card">Card</option>

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

<!-- TABLE -->

<div class="card-box shadow-sm">

<div class="table-responsive">

<table class="table table-hover align-middle">

<thead>

<tr>

<th>ID</th>
<th>Transaction</th>
<th>Order</th>
<th>Customer</th>
<th>Method</th>
<th>Amount</th>
<th>Status</th>
<th>Date</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php while($payment = $payments->fetch_assoc()): ?>

<tr>

<td>
<?= $payment['id'] ?>
</td>

<td>

<?= htmlspecialchars(
$payment['transaction_id']
) ?>

</td>

<td>

<?= htmlspecialchars(
$payment['order_number']
) ?>

</td>

<td>

<?= htmlspecialchars(
$payment['full_name']
) ?>

</td>

<td>

<?= htmlspecialchars(
$payment['payment_method']
) ?>

</td>

<td>

TZS

<?= number_format(
$payment['amount'],
2
) ?>

</td>

<td>

<?php

$color = match(
$payment['payment_status']
){

'paid' => 'success',
'failed' => 'danger',
'refunded' => 'warning',
default => 'secondary'

};

?>

<span
class="badge bg-<?= $color ?>">

<?= ucfirst(
$payment['payment_status']
) ?>

</span>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$payment['created_at']
)
) ?>

</td>

<td>

<div class="btn-group">

<a
href="?action=verify&id=<?= $payment['id'] ?>"
class="btn btn-sm btn-success">

Verify

</a>

<a
href="?action=refund&id=<?= $payment['id'] ?>"
class="btn btn-sm btn-warning">

Refund

</a>

<a
href="payment-details.php?id=<?= $payment['id'] ?>"
class="btn btn-sm btn-primary">

View

</a>

</div>

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
href="?page=<?= $i ?>">

<?= $i ?>

</a>

</li>

<?php endfor; ?>

</ul>

</nav>

</div>

</body>
</html>