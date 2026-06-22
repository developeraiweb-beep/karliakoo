<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$userId = (int)($_GET['id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));

$limit = 50;
$offset = ($page - 1) * $limit;

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/
$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = ["1=1"];
$params = [];
$types = '';

if($userId > 0)
{
    $where[] = "user_id=?";
    $params[] = $userId;
    $types .= "i";
}

if(!empty($status))
{
    $where[] = "login_status=?";
    $params[] = $status;
    $types .= "s";
}

if(!empty($search))
{
    $where[] = "(email LIKE ? OR ip_address LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $types .= "ss";
}

$whereSql = implode(" AND ", $where);

/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/
$countSql = "
SELECT COUNT(*) total
FROM login_history
WHERE {$whereSql}
";

$countStmt = $conn->prepare($countSql);

if(!empty($params))
{
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
| STATS
|--------------------------------------------------------------------------
*/
$stats = $conn->query("
SELECT

COUNT(*) total,

SUM(
CASE
WHEN login_status='success'
THEN 1
ELSE 0
END
) successful,

SUM(
CASE
WHEN login_status='failed'
THEN 1
ELSE 0
END
) failed

FROM login_history
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| RECORDS
|--------------------------------------------------------------------------
*/
$sql = "
SELECT
lh.*,
u.full_name

FROM login_history lh

LEFT JOIN users u
ON u.id=lh.user_id

WHERE {$whereSql}

ORDER BY lh.id DESC

LIMIT ?,?
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

$records =
$stmt->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>Login History</title>

<meta
name="viewport"
content="width=device-width,initial-scale=1">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
background:#f4f6f9;
}

.card-box{
background:#fff;
padding:20px;
border-radius:15px;
margin-bottom:20px;
box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.metric{
font-size:30px;
font-weight:700;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between mb-4">

<h2>

Login History

</h2>

<a
href="dashboard.php"
class="btn btn-secondary">

Dashboard

</a>

</div>

<!-- STATS -->

<div class="row g-3 mb-4">

<div class="col-md-4">

<div class="card-box">

<div class="metric">

<?= number_format($stats['total']) ?>

</div>

Total Logins

</div>

</div>

<div class="col-md-4">

<div class="card-box">

<div class="metric text-success">

<?= number_format($stats['successful']) ?>

</div>

Successful

</div>

</div>

<div class="col-md-4">

<div class="card-box">

<div class="metric text-danger">

<?= number_format($stats['failed']) ?>

</div>

Failed

</div>

</div>

</div>

<!-- FILTERS -->

<div class="card-box">

<form method="GET">

<input
type="hidden"
name="id"
value="<?= $userId ?>">

<div class="row">

<div class="col-md-4">

<input
type="text"
name="search"
value="<?= htmlspecialchars($search) ?>"
class="form-control"
placeholder="Email or IP">

</div>

<div class="col-md-3">

<select
name="status"
class="form-select">

<option value="">
All Status
</option>

<option value="success">
Successful
</option>

<option value="failed">
Failed
</option>

</select>

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

<!-- LOGIN TABLE -->

<div class="card-box">

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>User</th>
<th>Email</th>
<th>IP</th>
<th>Status</th>
<th>Browser</th>
<th>Device</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php while(
$row =
$records->fetch_assoc()
): ?>

<tr>

<td>

<?= htmlspecialchars(
$row['full_name'] ?? 'Unknown'
) ?>

</td>

<td>

<?= htmlspecialchars(
$row['email']
) ?>

</td>

<td>

<?= htmlspecialchars(
$row['ip_address']
) ?>

</td>

<td>

<?php if(
$row['login_status']
=='success'
): ?>

<span class="badge bg-success">

Success

</span>

<?php else: ?>

<span class="badge bg-danger">

Failed

</span>

<?php endif; ?>

</td>

<td>

<?= htmlspecialchars(
$row['browser'] ?? '-'
) ?>

</td>

<td>

<?= htmlspecialchars(
$row['device'] ?? '-'
) ?>

</td>

<td>

<?= date(
'd M Y H:i',
strtotime(
$row['created_at']
)
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<!-- PAGINATION -->

<nav>

<ul class="pagination">

<?php for(
$i=1;
$i<=$totalPages;
$i++
): ?>

<li class="page-item <?= $page==$i ? 'active' : '' ?>">

<a
class="page-link"
href="?page=<?= $i ?>&id=<?= $userId ?>">

<?= $i ?>

</a>

</li>

<?php endfor; ?>

</ul>

</nav>

</div>

</body>
</html>