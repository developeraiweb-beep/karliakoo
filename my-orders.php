<?php

declare(strict_types=1);

session_start();

require_once "config/db.php";

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

$search =
trim($_GET['search'] ?? '');

$status =
trim($_GET['status'] ?? '');

$where = "o.user_id=?";
$params = [$userId];
$types = "i";

if(!empty($search))
{
    $where .= "
    AND o.order_number LIKE ?
    ";

    $params[] =
    "%{$search}%";

    $types .= "s";
}

if(!empty($status))
{
    $where .= "
    AND o.order_status=?
    ";

    $params[] =
    $status;

    $types .= "s";
}

$sql = "
SELECT

o.*,

(
    SELECT COUNT(*)
    FROM order_items oi
    WHERE oi.order_id=o.id
) item_count,

(
    SELECT payment_method
    FROM payments p
    WHERE p.order_id=o.id
    ORDER BY p.id DESC
    LIMIT 1
) payment_method

FROM orders o

WHERE {$where}

ORDER BY o.id DESC
";

$stmt =
$conn->prepare($sql);

$stmt->bind_param(
$types,
...$params
);

$stmt->execute();

$orders =
$stmt->get_result();

function orderBadge(
    string $status
): string
{
    return match($status)
    {
        'pending' =>
        'warning',

        'processing' =>
        'info',

        'packed' =>
        'secondary',

        'shipped' =>
        'primary',

        'delivered' =>
        'success',

        'cancelled' =>
        'danger',

        default =>
        'dark'
    };
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

My Orders

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">

</head>

<body>

<div class="container py-5">

<div
class="d-flex justify-content-between align-items-center mb-4">

<h2>

<i class="bi bi-box-seam"></i>

My Orders

</h2>

<a
href="products.php"
class="btn btn-primary">

Continue Shopping

</a>

</div>

<form
method="GET"
class="row g-2 mb-4">

<div class="col-md-5">

<input
type="text"
name="search"
class="form-control"
placeholder="Search Order Number"
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-4">

<select
name="status"
class="form-select">

<option value="">

All Status

</option>

<option
value="pending"
<?= $status=='pending'?'selected':'' ?>>

Pending

</option>

<option
value="processing"
<?= $status=='processing'?'selected':'' ?>>

Processing

</option>

<option
value="packed"
<?= $status=='packed'?'selected':'' ?>>

Packed

</option>

<option
value="shipped"
<?= $status=='shipped'?'selected':'' ?>>

Shipped

</option>

<option
value="delivered"
<?= $status=='delivered'?'selected':'' ?>>

Delivered

</option>

<option
value="cancelled"
<?= $status=='cancelled'?'selected':'' ?>>

Cancelled

</option>

</select>

</div>

<div class="col-md-3">

<button
class="btn btn-dark w-100">

Filter

</button>

</div>

</form>

<div class="card">

<div class="card-body p-0">

<div class="table-responsive">

<table class="table table-hover mb-0">

<thead>

<tr>

<th>Order #</th>

<th>Date</th>

<th>Items</th>

<th>Total</th>

<th>Payment</th>

<th>Status</th>

<th>Actions</th>

</tr>

</thead>

<tbody>

<?php if($orders->num_rows > 0): ?>

<?php while(
$order =
$orders->fetch_assoc()
): ?>

<tr>

<td>

<strong>

<?= htmlspecialchars(
$order['order_number']
) ?>

</strong>

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

<?= (int)$order['item_count'] ?>

</td>

<td>

TZS

<?= number_format(
(float)$order['total_amount']
) ?>

</td>

<td>

<div>

<?= htmlspecialchars(
$order['payment_method']
?? 'N/A'
) ?>

</div>

<span
class="badge bg-<?= $order['payment_status']=='paid'
? 'success'
: 'warning' ?>">

<?= ucfirst(
$order['payment_status']
) ?>

</span>

</td>

<td>

<span
class="badge bg-<?= orderBadge(
$order['order_status']
) ?>">

<?= ucfirst(
$order['order_status']
) ?>

</span>

</td>

<td>

<div
class="btn-group btn-group-sm">

<a
href="order-details.php?id=<?= (int)$order['id'] ?>"
class="btn btn-outline-primary">

View

</a>

<a
href="invoice.php?order_id=<?= (int)$order['id'] ?>"
class="btn btn-outline-success">

Invoice

</a>

<a
href="track-order.php?order_id=<?= (int)$order['id'] ?>"
class="btn btn-outline-dark">

Track

</a>

</div>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td colspan="7">

<div class="alert alert-light m-3">

No orders found.

</div>

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>