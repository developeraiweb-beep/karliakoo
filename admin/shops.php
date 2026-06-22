<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

/*
|--------------------------------------------------------------------------
| SHOP ACTIONS
|--------------------------------------------------------------------------
*/
if (
    isset($_GET['action']) &&
    isset($_GET['id'])
) {

    $shopId = (int)$_GET['id'];

    switch ($_GET['action']) {

        case 'approve':

            $stmt = $conn->prepare("
                UPDATE shops
                SET status='approved'
                WHERE id=?
            ");

            $stmt->bind_param("i", $shopId);
            $stmt->execute();

        break;

        case 'reject':

            $stmt = $conn->prepare("
                UPDATE shops
                SET status='rejected'
                WHERE id=?
            ");

            $stmt->bind_param("i", $shopId);
            $stmt->execute();

        break;

        case 'verify':

            $stmt = $conn->prepare("
                UPDATE shops
                SET verified=1
                WHERE id=?
            ");

            $stmt->bind_param("i", $shopId);
            $stmt->execute();

        break;

        case 'unverify':

            $stmt = $conn->prepare("
                UPDATE shops
                SET verified=0
                WHERE id=?
            ");

            $stmt->bind_param("i", $shopId);
            $stmt->execute();

        break;

        case 'suspend':

            $stmt = $conn->prepare("
                UPDATE shops
                SET suspended=1
                WHERE id=?
            ");

            $stmt->bind_param("i", $shopId);
            $stmt->execute();

        break;

        case 'activate':

            $stmt = $conn->prepare("
                UPDATE shops
                SET suspended=0
                WHERE id=?
            ");

            $stmt->bind_param("i", $shopId);
            $stmt->execute();

        break;
    }

    header("Location: shops.php");
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
            s.shop_name LIKE ?
            OR u.full_name LIKE ?
            OR u.email LIKE ?
            OR s.city LIKE ?
        )
    ";

    $like = "%{$search}%";

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

    $types .= "ssss";
}

if (!empty($status)) {

    $where[] = "s.status=?";
    $params[] = $status;
    $types .= "s";
}

$whereSql = implode(" AND ", $where);

/*
|--------------------------------------------------------------------------
| TOTAL RECORDS
|--------------------------------------------------------------------------
*/
$countSql = "
SELECT COUNT(*) total

FROM shops s

LEFT JOIN users u
ON u.id=s.seller_id

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
| SHOPS
|--------------------------------------------------------------------------
*/
$sql = "

SELECT

s.*,

u.full_name,
u.email

FROM shops s

LEFT JOIN users u
ON u.id=s.seller_id

WHERE {$whereSql}

ORDER BY s.id DESC

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

$shops = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/
$stats = $conn->query("
SELECT

COUNT(*) total,

SUM(status='approved') approved,

SUM(status='pending') pending,

SUM(status='rejected') rejected,

SUM(verified=1) verified

FROM shops
")->fetch_assoc();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>Shop Management</title>

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

.logo{
width:45px;
height:45px;
border-radius:50%;
object-fit:cover;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Shop Management

</h2>

<div class="row g-3 mb-4">

<div class="col-md-2">
<div class="card-box shadow-sm">
<h4><?= number_format($stats['total']) ?></h4>
<small>Total Shops</small>
</div>
</div>

<div class="col-md-2">
<div class="card-box shadow-sm">
<h4><?= number_format($stats['approved']) ?></h4>
<small>Approved</small>
</div>
</div>

<div class="col-md-2">
<div class="card-box shadow-sm">
<h4><?= number_format($stats['pending']) ?></h4>
<small>Pending</small>
</div>
</div>

<div class="col-md-2">
<div class="card-box shadow-sm">
<h4><?= number_format($stats['rejected']) ?></h4>
<small>Rejected</small>
</div>
</div>

<div class="col-md-2">
<div class="card-box shadow-sm">
<h4><?= number_format($stats['verified']) ?></h4>
<small>Verified</small>
</div>
</div>

</div>

<div class="card-box shadow-sm mb-4">

<form method="GET">

<div class="row">

<div class="col-md-5">

<input
type="text"
name="search"
class="form-control"
placeholder="Search shops"
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-3">

<select
name="status"
class="form-select">

<option value="">All Status</option>

<option value="approved">
Approved
</option>

<option value="pending">
Pending
</option>

<option value="rejected">
Rejected
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

<div class="card-box shadow-sm">

<div class="table-responsive">

<table class="table table-hover align-middle">

<thead>

<tr>

<th>ID</th>
<th>Shop</th>
<th>Owner</th>
<th>Status</th>
<th>Verified</th>
<th>Followers</th>
<th>Views</th>
<th>Created</th>
<th>Actions</th>

</tr>

</thead>

<tbody>

<?php while($shop = $shops->fetch_assoc()): ?>

<tr>

<td>

#<?= $shop['id'] ?>

</td>

<td>

<?php if(!empty($shop['logo'])): ?>

<img
src="../uploads/shops/<?= htmlspecialchars($shop['logo']) ?>"
class="logo me-2">

<?php endif; ?>

<strong>

<?= htmlspecialchars(
$shop['shop_name']
) ?>

</strong>

</td>

<td>

<?= htmlspecialchars(
$shop['full_name']
) ?>

<br>

<small>

<?= htmlspecialchars(
$shop['email']
) ?>

</small>

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

<?= $shop['verified']
? '✅'
: '❌' ?>

</td>

<td>

<?= number_format(
$shop['followers']
) ?>

</td>

<td>

<?= number_format(
$shop['views']
) ?>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$shop['created_at']
)
) ?>

</td>

<td>

<div class="btn-group">

<a
href="supplier-details.php?id=<?= $shop['id'] ?>"
class="btn btn-sm btn-primary">

View

</a>

<a
href="?action=approve&id=<?= $shop['id'] ?>"
class="btn btn-sm btn-success">

Approve

</a>

<a
href="?action=reject&id=<?= $shop['id'] ?>"
class="btn btn-sm btn-danger">

Reject

</a>

</div>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<nav class="mt-4">

<ul class="pagination">

<?php for($i=1;$i<=$totalPages;$i++): ?>

<li
class="page-item <?= $page==$i ? 'active':'' ?>">

<a
class="page-link"
href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">

<?= $i ?>

</a>

</li>

<?php endfor; ?>

</ul>

</nav>

</div>

</body>
</html>