<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$where = ["1=1"];
$params = [];
$types = '';

if (!empty($search)) {

    $where[] = "(
        d.dispute_number LIKE ?
        OR d.subject LIKE ?
        OR buyer.full_name LIKE ?
        OR supplier.shop_name LIKE ?
    )";

    $like = "%{$search}%";

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

    $types .= "ssss";
}

if (!empty($status)) {

    $where[] = "d.status=?";

    $params[] = $status;
    $types .= "s";
}

$whereSQL = implode(" AND ", $where);

/*
|--------------------------------------------------------------------------
| DISPUTES
|--------------------------------------------------------------------------
*/
$sql = "

SELECT

d.*,

buyer.full_name buyer_name,

supplier.shop_name,

o.order_number

FROM b2b_disputes d

LEFT JOIN users buyer
ON buyer.id=d.buyer_id

LEFT JOIN shops supplier
ON supplier.seller_id=d.supplier_id

LEFT JOIN b2b_orders o
ON o.id=d.order_id

WHERE {$whereSQL}

ORDER BY d.id DESC

";

$stmt = $conn->prepare($sql);

if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$disputes = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/
$stats = $conn->query("
SELECT

COUNT(*) total_disputes,

SUM(status='open') open_disputes,

SUM(status='investigating') investigating_disputes,

SUM(status='resolved') resolved_disputes,

SUM(status='closed') closed_disputes

FROM b2b_disputes
")->fetch_assoc();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>
B2B Disputes
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

B2B Dispute Management

</h2>

<!-- STATS -->

<div class="row g-3 mb-4">

<div class="col-md-2">
<div class="stat-card shadow-sm">
<h5><?= number_format($stats['total_disputes']) ?></h5>
<p>Total</p>
</div>
</div>

<div class="col-md-2">
<div class="stat-card shadow-sm">
<h5><?= number_format($stats['open_disputes']) ?></h5>
<p>Open</p>
</div>
</div>

<div class="col-md-2">
<div class="stat-card shadow-sm">
<h5><?= number_format($stats['investigating_disputes']) ?></h5>
<p>Investigating</p>
</div>
</div>

<div class="col-md-2">
<div class="stat-card shadow-sm">
<h5><?= number_format($stats['resolved_disputes']) ?></h5>
<p>Resolved</p>
</div>
</div>

<div class="col-md-2">
<div class="stat-card shadow-sm">
<h5><?= number_format($stats['closed_disputes']) ?></h5>
<p>Closed</p>
</div>
</div>

</div>

<!-- FILTER -->

<div class="card shadow-sm mb-4">

<div class="card-body">

<form method="GET">

<div class="row">

<div class="col-md-5">

<input
type="text"
name="search"
class="form-control"
placeholder="Search dispute"
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-4">

<select
name="status"
class="form-select">

<option value="">
All Statuses</option>

<option value="open">Open</option>
<option value="investigating">Investigating</option>
<option value="resolved">Resolved</option>
<option value="closed">Closed</option>

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

<!-- TABLE -->

<div class="card shadow-sm">

<div class="card-header">

Disputes

</div>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Dispute</th>
<th>Order</th>
<th>Buyer</th>
<th>Supplier</th>
<th>Type</th>
<th>Status</th>
<th>Date</th>
<th></th>

</tr>

</thead>

<tbody>

<?php while($row = $disputes->fetch_assoc()): ?>

<tr>

<td>

<strong>

<?= htmlspecialchars(
$row['dispute_number']
) ?>

</strong>

<br>

<small>

<?= htmlspecialchars(
$row['subject']
) ?>

</small>

</td>

<td>

<?= htmlspecialchars(
$row['order_number']
) ?>

</td>

<td>

<?= htmlspecialchars(
$row['buyer_name']
) ?>

</td>

<td>

<?= htmlspecialchars(
$row['shop_name']
) ?>

</td>

<td>

<?= ucfirst(
$row['dispute_type']
) ?>

</td>

<td>

<?php

$badge = match($row['status']) {

'open' => 'danger',

'investigating' => 'warning',

'resolved' => 'success',

'closed' => 'secondary',

default => 'dark'

};

?>

<span class="badge bg-<?= $badge ?>">

<?= ucfirst(
$row['status']
) ?>

</span>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$row['created_at']
)
) ?>

</td>

<td>

<a
href="b2b-dispute-details.php?id=<?= $row['id'] ?>"
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