<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

if (!$user || $user['role'] !== 'agent') {
    die("Access denied");
}

$agent_id = (int)$user['id'];

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? '');

$where = "";

$params = [$agent_id];
$types = "i";

if (!empty($search)) {

    $where .= "
        AND (
            o.order_number LIKE ?
            OR u.full_name LIKE ?
            OR u.email LIKE ?
        )
    ";

    $like = "%{$search}%";

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

    $types .= "sss";
}

/*
|--------------------------------------------------------------------------
| Total Orders
|--------------------------------------------------------------------------
*/
$countSql = "
SELECT COUNT(DISTINCT o.id) total

FROM orders o

INNER JOIN users u
ON o.user_id = u.id

INNER JOIN agent_referrals ar
ON ar.referred_user_id = u.id

WHERE ar.agent_id = ?
$where
";

$countStmt = $conn->prepare($countSql);

$countStmt->bind_param($types, ...$params);
$countStmt->execute();

$totalOrders = $countStmt
    ->get_result()
    ->fetch_assoc()['total'];

$totalPages = ceil($totalOrders / $limit);

/*
|--------------------------------------------------------------------------
| Orders
|--------------------------------------------------------------------------
*/
$sql = "
SELECT

    o.id,
    o.order_number,
    o.total_amount,
    o.status,
    o.created_at,

    u.full_name,
    u.email,

    ar.referral_type

FROM orders o

INNER JOIN users u
ON o.user_id = u.id

INNER JOIN agent_referrals ar
ON ar.referred_user_id = u.id

WHERE ar.agent_id = ?
$where

GROUP BY o.id

ORDER BY o.id DESC

LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($sql);

$stmt->bind_param($types, ...$params);

$stmt->execute();

$orders = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| Dashboard Totals
|--------------------------------------------------------------------------
*/
$summary = $conn->prepare("
SELECT

COUNT(DISTINCT o.id) total_orders,

COALESCE(SUM(o.total_amount),0) total_sales

FROM orders o

INNER JOIN agent_referrals ar
ON ar.referred_user_id = o.user_id

WHERE ar.agent_id = ?
");

$summary->bind_param("i", $agent_id);
$summary->execute();

$stats = $summary->get_result()->fetch_assoc();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Agent Orders</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">

<style>

body{
    background:#f5f5f5;
}

.stat-card{
    background:#fff;
    border-radius:12px;
    padding:20px;
}

</style>

</head>
<body>

<div class="container py-4">

<h2 class="mb-4">
Orders Performance
</h2>

<div class="row mb-4">

<div class="col-md-6">

<div class="stat-card shadow-sm">

<h4>
<?= number_format($stats['total_orders']) ?>
</h4>

<div>Total Orders</div>

</div>

</div>

<div class="col-md-6">

<div class="stat-card shadow-sm">

<h4>
TZS <?= number_format($stats['total_sales'],2) ?>
</h4>

<div>Total Sales</div>

</div>

</div>

</div>

<form method="GET" class="mb-3">

<div class="input-group">

<input type="text"
       name="search"
       value="<?= htmlspecialchars($search) ?>"
       class="form-control"
       placeholder="Order number, customer name or email">

<button class="btn btn-primary">
Search
</button>

</div>

</form>

<div class="card shadow-sm">

<div class="card-header">
Orders
</div>

<div class="card-body p-0">

<table class="table table-striped table-hover mb-0">

<thead>

<tr>

<th>#</th>
<th>Order</th>
<th>Customer</th>
<th>Type</th>
<th>Total</th>
<th>Status</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php while($row = $orders->fetch_assoc()): ?>

<tr>

<td>
<?= $row['id'] ?>
</td>

<td>
<?= htmlspecialchars($row['order_number']) ?>
</td>

<td>

<strong>
<?= htmlspecialchars($row['full_name']) ?>
</strong>

<br>

<small>
<?= htmlspecialchars($row['email']) ?>
</small>

</td>

<td>

<span class="badge bg-info">
<?= ucfirst($row['referral_type']) ?>
</span>

</td>

<td>

TZS
<?= number_format($row['total_amount'],2) ?>

</td>

<td>

<?php

$statusColor = match($row['status']) {

'pending' => 'warning',
'processing' => 'info',
'shipped' => 'primary',
'delivered' => 'success',
'cancelled' => 'danger',

default => 'secondary'

};

?>

<span class="badge bg-<?= $statusColor ?>">
<?= ucfirst($row['status']) ?>
</span>

</td>

<td>

<?= date(
'd M Y H:i',
strtotime($row['created_at'])
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<?php if($totalPages > 1): ?>

<nav class="mt-4">

<ul class="pagination">

<?php for($i=1;$i<=$totalPages;$i++): ?>

<li class="page-item <?= $page==$i?'active':'' ?>">

<a class="page-link"
   href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">

<?= $i ?>

</a>

</li>

<?php endfor; ?>

</ul>

</nav>

<?php endif; ?>

</div>

</body>
</html>