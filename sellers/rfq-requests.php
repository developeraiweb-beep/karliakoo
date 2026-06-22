<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user_id = (int) $_SESSION['user_id'];

$user = currentUser();
$seller_id = (int)$user['id'];

$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = [
    "r.supplier_id=?"
];

$params = [$seller_id];
$types = "i";

if (!empty($status)) {

    $where[] = "r.status=?";

    $params[] = $status;
    $types .= "s";
}

if (!empty($search)) {

    $where[] = "(
        r.quote_number LIKE ?
        OR p.product_name LIKE ?
        OR u.full_name LIKE ?
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
| RFQ LIST
|--------------------------------------------------------------------------
*/
$sql = "

SELECT

r.*,

p.name,
p.image,

u.full_name buyer_name,
u.email buyer_email,

resp.quoted_price

FROM rfq_requests r

LEFT JOIN products p
ON p.id = r.product_id

LEFT JOIN users u
ON u.id = r.buyer_id

LEFT JOIN rfq_responses resp
ON resp.rfq_id = r.id

WHERE {$whereSQL}

ORDER BY r.id DESC

";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    $types,
    ...$params
);

$stmt->execute();

$rfqs = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| DASHBOARD COUNTERS
|--------------------------------------------------------------------------
*/
$countQuery = $conn->prepare("
SELECT

COUNT(*) total_rfqs,

SUM(status='pending') pending_rfqs,

SUM(status='quoted') quoted_rfqs,

SUM(status='accepted') accepted_rfqs,

SUM(status='rejected') rejected_rfqs

FROM rfq_requests

WHERE supplier_id=?
");

$countQuery->bind_param("i", $seller_id);
$countQuery->execute();

$stats =
    $countQuery
    ->get_result()
    ->fetch_assoc();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>
RFQ Requests
</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f6fa;
}

.product-img{
    width:60px;
    height:60px;
    object-fit:cover;
    border-radius:8px;
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

RFQ Requests

</h2>

<div class="row g-3 mb-4">

<div class="col-md-2">

<div class="stat-card shadow-sm">

<h4>

<?= number_format(
$stats['total_rfqs']
) ?>

</h4>

<p>Total RFQs</p>

</div>

</div>

<div class="col-md-2">

<div class="stat-card shadow-sm">

<h4 class="text-warning">

<?= number_format(
$stats['pending_rfqs']
) ?>

</h4>

<p>Pending</p>

</div>

</div>

<div class="col-md-2">

<div class="stat-card shadow-sm">

<h4 class="text-primary">

<?= number_format(
$stats['quoted_rfqs']
) ?>

</h4>

<p>Quoted</p>

</div>

</div>

<div class="col-md-2">

<div class="stat-card shadow-sm">

<h4 class="text-success">

<?= number_format(
$stats['accepted_rfqs']
) ?>

</h4>

<p>Accepted</p>

</div>

</div>

<div class="col-md-2">

<div class="stat-card shadow-sm">

<h4 class="text-danger">

<?= number_format(
$stats['rejected_rfqs']
) ?>

</h4>

<p>Rejected</p>

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
placeholder="Search RFQ">

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

<option value="quoted">
Quoted
</option>

<option value="accepted">
Accepted
</option>

<option value="rejected">
Rejected
</option>

<option value="expired">
Expired
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

Incoming RFQs

</div>

<div class="table-responsive">

<table class="table table-hover mb-0">

<thead>

<tr>

<th>RFQ</th>
<th>Product</th>
<th>Buyer</th>
<th>Quantity</th>
<th>Target Price</th>
<th>Quoted Price</th>
<th>Status</th>
<th>Date</th>
<th></th>

</tr>

</thead>

<tbody>

<?php while($rfq = $rfqs->fetch_assoc()): ?>

<tr>

<td>

<strong>

<?= htmlspecialchars(
$rfq['quote_number']
) ?>

</strong>

</td>

<td>

<div class="d-flex align-items-center">

<img
src="../uploads/products/<?= htmlspecialchars($rfq['image']) ?>"
class="product-img me-2">

<div>

<?= htmlspecialchars(
$rfq['product_name']
) ?>

</div>

</div>

</td>

<td>

<?= htmlspecialchars(
$rfq['buyer_name']
) ?>

<br>

<small>

<?= htmlspecialchars(
$rfq['buyer_email']
) ?>

</small>

</td>

<td>

<?= number_format(
$rfq['quantity']
) ?>

</td>

<td>

<?php if($rfq['target_price']): ?>

TZS

<?= number_format(
$rfq['target_price'],
2
) ?>

<?php else: ?>

—

<?php endif; ?>

</td>

<td>

<?php if($rfq['quoted_price']): ?>

TZS

<?= number_format(
$rfq['quoted_price'],
2
) ?>

<?php else: ?>

<span class="text-muted">

Not Quoted

</span>

<?php endif; ?>

</td>

<td>

<?php

$badge = match($rfq['status']) {

'pending' => 'warning',

'quoted' => 'primary',

'accepted' => 'success',

'rejected' => 'danger',

'expired' => 'secondary',

default => 'dark'

};

?>

<span class="badge bg-<?= $badge ?>">

<?= ucfirst(
$rfq['status']
) ?>

</span>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$rfq['created_at']
)
) ?>

</td>

<td>

<a
href="rfq-details.php?id=<?= $rfq['id'] ?>"
class="btn btn-sm btn-primary">

Open

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