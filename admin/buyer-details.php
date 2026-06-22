<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$buyer_id = (int)($_GET['id'] ?? 0);

if ($buyer_id <= 0) {
    die("Invalid buyer.");
}

/*
|--------------------------------------------------------------------------
| ACTIVATE / SUSPEND
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $status = trim($_POST['status']);

    $stmt = $conn->prepare("
        UPDATE users
        SET status=?
        WHERE id=?
        LIMIT 1
    ");

    $stmt->bind_param(
        "si",
        $status,
        $buyer_id
    );

    $stmt->execute();

    header(
        "Location: buyer-details.php?id={$buyer_id}&updated=1"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| BUYER PROFILE
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT *

FROM users

WHERE id=?

LIMIT 1
");

$stmt->bind_param(
    "i",
    $buyer_id
);

$stmt->execute();

$buyer =
    $stmt
    ->get_result()
    ->fetch_assoc();

if (!$buyer) {
    die("Buyer not found.");
}

/*
|--------------------------------------------------------------------------
| BUYER STATS
|--------------------------------------------------------------------------
*/
$statsStmt = $conn->prepare("
SELECT

COUNT(*) total_orders,

COALESCE(
SUM(total_amount),
0
) total_spent,

COALESCE(
AVG(total_amount),
0
) average_order

FROM b2b_orders

WHERE buyer_id=?
");

$statsStmt->bind_param(
    "i",
    $buyer_id
);

$statsStmt->execute();

$stats =
    $statsStmt
    ->get_result()
    ->fetch_assoc();

/*
|--------------------------------------------------------------------------
| RFQ COUNT
|--------------------------------------------------------------------------
*/
$rfqCount = 0;

$rfqResult = $conn->query("
SHOW TABLES LIKE 'rfq_requests'
");

if ($rfqResult->num_rows > 0) {

    $rfqStmt = $conn->prepare("
        SELECT COUNT(*) total
        FROM rfq_requests
        WHERE buyer_id=?
    ");

    $rfqStmt->bind_param(
        "i",
        $buyer_id
    );

    $rfqStmt->execute();

    $rfqCount =
        $rfqStmt
        ->get_result()
        ->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| DISPUTE COUNT
|--------------------------------------------------------------------------
*/
$disputeCount = 0;

$disputeResult = $conn->query("
SHOW TABLES LIKE 'b2b_disputes'
");

if ($disputeResult->num_rows > 0) {

    $dStmt = $conn->prepare("
        SELECT COUNT(*) total
        FROM b2b_disputes
        WHERE buyer_id=?
    ");

    $dStmt->bind_param(
        "i",
        $buyer_id
    );

    $dStmt->execute();

    $disputeCount =
        $dStmt
        ->get_result()
        ->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| RECENT ORDERS
|--------------------------------------------------------------------------
*/
$ordersStmt = $conn->prepare("
SELECT

id,
order_number,
total_amount,
payment_status,
order_status,
created_at

FROM b2b_orders

WHERE buyer_id=?

ORDER BY id DESC

LIMIT 20
");

$ordersStmt->bind_param(
    "i",
    $buyer_id
);

$ordersStmt->execute();

$orders =
    $ordersStmt
    ->get_result();

$updated =
    isset($_GET['updated']);

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>

Buyer Details

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
border-radius:12px;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<a
href="b2b-buyers.php"
class="btn btn-secondary mb-3">

← Back

</a>

<?php if($updated): ?>

<div class="alert alert-success">

Buyer updated successfully.

</div>

<?php endif; ?>

<div class="row">

<!-- PROFILE -->

<div class="col-lg-4">

<div class="card-box shadow-sm p-4 mb-4">

<h4>

Buyer Profile

</h4>

<hr>

<p>

Name:

<strong>

<?= htmlspecialchars(
$buyer['full_name']
) ?>

</strong>

</p>

<p>

Email:

<?= htmlspecialchars(
$buyer['email']
) ?>

</p>

<p>

Phone:

<?= htmlspecialchars(
$buyer['phone'] ?? '-'
) ?>

</p>

<p>

Status:

<?php if(
($buyer['status'] ?? 'active')
==='active'
): ?>

<span class="badge bg-success">

Active

</span>

<?php else: ?>

<span class="badge bg-danger">

Suspended

</span>

<?php endif; ?>

</p>

<p>

Joined:

<?= date(
'd M Y',
strtotime(
$buyer['created_at']
)
) ?>

</p>

</div>

<div class="card-box shadow-sm p-4">

<h4>

Admin Controls

</h4>

<hr>

<form method="POST">

<select
name="status"
class="form-select mb-3">

<option
value="active"
<?= ($buyer['status'] ?? 'active') === 'active'
? 'selected'
: ''
?>>

Active

</option>

<option
value="suspended"
<?= ($buyer['status'] ?? 'active') === 'suspended'
? 'selected'
: ''
?>>

Suspended

</option>

</select>

<button
class="btn btn-primary w-100">

Update Status

</button>

</form>

</div>

</div>

<!-- ANALYTICS -->

<div class="col-lg-8">

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="card-box shadow-sm p-3">

<h4>

<?= number_format(
$stats['total_orders']
) ?>

</h4>

<small>

Orders

</small>

</div>

</div>

<div class="col-md-3">

<div class="card-box shadow-sm p-3">

<h4>

<?= number_format(
$rfqCount
) ?>

</h4>

<small>

RFQs

</small>

</div>

</div>

<div class="col-md-3">

<div class="card-box shadow-sm p-3">

<h4>

<?= number_format(
$disputeCount
) ?>

</h4>

<small>

Disputes

</small>

</div>

</div>

<div class="col-md-3">

<div class="card-box shadow-sm p-3">

<h4>

TZS

<?= number_format(
$stats['total_spent'],
2
) ?>

</h4>

<small>

Total Spend

</small>

</div>

</div>

</div>

<!-- ORDERS -->

<div class="card-box shadow-sm p-4">

<h4>

Recent Orders

</h4>

<hr>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Order</th>
<th>Amount</th>
<th>Payment</th>
<th>Status</th>
<th>Date</th>
<th></th>

</tr>

</thead>

<tbody>

<?php while(
$order = $orders->fetch_assoc()
): ?>

<tr>

<td>

<?= htmlspecialchars(
$order['order_number']
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

<?= ucfirst(
$order['payment_status']
) ?>

</td>

<td>

<?= ucfirst(
$order['order_status']
) ?>

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

</div>

</div>

</body>
</html>