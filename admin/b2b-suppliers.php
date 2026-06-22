<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$search = trim($_GET['search'] ?? '');

/*
|--------------------------------------------------------------------------
| SHOP ACTIONS
|--------------------------------------------------------------------------
*/
if (
    isset($_GET['action']) &&
    isset($_GET['shop'])
) {

    $shop_id = (int)$_GET['shop'];

    switch ($_GET['action']) {

        case 'approve':

            $stmt = $conn->prepare("
                UPDATE shops
                SET status='approved'
                WHERE id=?
            ");

            $stmt->bind_param(
                "i",
                $shop_id
            );

            $stmt->execute();

        break;

        case 'reject':

            $stmt = $conn->prepare("
                UPDATE shops
                SET status='rejected'
                WHERE id=?
            ");

            $stmt->bind_param(
                "i",
                $shop_id
            );

            $stmt->execute();

        break;

        case 'verify':

            $stmt = $conn->prepare("
                UPDATE shops
                SET verified=1
                WHERE id=?
            ");

            $stmt->bind_param(
                "i",
                $shop_id
            );

            $stmt->execute();

        break;

        case 'unverify':

            $stmt = $conn->prepare("
                UPDATE shops
                SET verified=0
                WHERE id=?
            ");

            $stmt->bind_param(
                "i",
                $shop_id
            );

            $stmt->execute();

        break;

        case 'suspend':

            $stmt = $conn->prepare("
                UPDATE shops
                SET suspended=1
                WHERE id=?
            ");

            $stmt->bind_param(
                "i",
                $shop_id
            );

            $stmt->execute();

        break;

        case 'activate':

            $stmt = $conn->prepare("
                UPDATE shops
                SET suspended=0
                WHERE id=?
            ");

            $stmt->bind_param(
                "i",
                $shop_id
            );

            $stmt->execute();

        break;
    }

    header("Location:b2b-suppliers.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/
$where = "";
$params = [];
$types = "";

if (!empty($search)) {

    $where = "
    WHERE
        s.shop_name LIKE ?
        OR s.city LIKE ?
        OR s.region LIKE ?
        OR u.full_name LIKE ?
    ";

    $like = "%{$search}%";

    $params = [
        $like,
        $like,
        $like,
        $like
    ];

    $types = "ssss";
}

/*
|--------------------------------------------------------------------------
| SUPPLIERS
|--------------------------------------------------------------------------
*/
$sql = "

SELECT

s.*,

u.full_name owner_name,
u.email owner_email,

COUNT(DISTINCT o.id) total_orders,

COALESCE(
SUM(o.total_amount),
0
) revenue,

COUNT(DISTINCT rfq.id)
total_rfqs

FROM shops s

LEFT JOIN users u
ON u.id=s.seller_id

LEFT JOIN b2b_orders o
ON o.shop_id=s.id

LEFT JOIN rfq_requests rfq
ON rfq.supplier_id=s.seller_id

{$where}

GROUP BY s.id

ORDER BY revenue DESC

";

$stmt = $conn->prepare($sql);

if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$shops = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| DASHBOARD STATS
|--------------------------------------------------------------------------
*/
$stats = $conn->query("
SELECT

COUNT(*) total_shops,

SUM(status='approved')
approved_shops,

SUM(status='pending')
pending_shops,

SUM(verified=1)
verified_shops

FROM shops
")->fetch_assoc();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>

B2B Suppliers

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
padding:20px;
border-radius:12px;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

B2B Suppliers Management

</h2>

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

<?= number_format(
$stats['total_shops']
) ?>

</h4>

<p>Total Suppliers</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

<?= number_format(
$stats['approved_shops']
) ?>

</h4>

<p>Approved</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

<?= number_format(
$stats['pending_shops']
) ?>

</h4>

<p>Pending</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

<?= number_format(
$stats['verified_shops']
) ?>

</h4>

<p>Verified</p>

</div>

</div>

</div>

<div class="card shadow-sm mb-4">

<div class="card-body">

<form method="GET">

<div class="row">

<div class="col-md-10">

<input
type="text"
name="search"
class="form-control"
placeholder="Search supplier"
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-2">

<button
class="btn btn-primary w-100">

Search

</button>

</div>

</div>

</form>

</div>

</div>

<div class="card shadow-sm">

<div class="card-header">

Marketplace Suppliers

</div>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Supplier</th>
<th>Owner</th>
<th>Orders</th>
<th>RFQs</th>
<th>Revenue</th>
<th>Status</th>
<th>Verified</th>
<th>Actions</th>

</tr>

</thead>

<tbody>

<?php while($shop = $shops->fetch_assoc()): ?>

<tr>

<td>

<strong>

<?= htmlspecialchars(
$shop['shop_name']
) ?>

</strong>

<br>

<small>

<?= htmlspecialchars(
$shop['city']
) ?>

</small>

</td>

<td>

<?= htmlspecialchars(
$shop['owner_name']
) ?>

<br>

<small>

<?= htmlspecialchars(
$shop['owner_email']
) ?>

</small>

</td>

<td>

<?= number_format(
$shop['total_orders']
) ?>

</td>

<td>

<?= number_format(
$shop['total_rfqs']
) ?>

</td>

<td>

TZS

<?= number_format(
$shop['revenue'],
2
) ?>

</td>

<td>

<?php

$statusColor = match($shop['status']) {

'approved' => 'success',
'pending' => 'warning',
'rejected' => 'danger',
default => 'secondary'

};

?>

<span class="badge bg-<?= $statusColor ?>">

<?= ucfirst(
$shop['status']
) ?>

</span>

</td>

<td>

<?php if($shop['verified']): ?>

<span class="badge bg-success">

Verified

</span>

<?php else: ?>

<span class="badge bg-secondary">
Not Verified
</span>

<?php endif; ?>

</td>

<td>

<a
href="supplier-details.php?id=<?= $shop['id'] ?>"
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