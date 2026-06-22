<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$module = trim($_GET['module'] ?? '');
$action = trim($_GET['action'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = ["1=1"];
$params = [];
$types = '';

if(!empty($module))
{
    $where[] = "module=?";
    $params[] = $module;
    $types .= "s";
}

if(!empty($action))
{
    $where[] = "action=?";
    $params[] = $action;
    $types .= "s";
}

if(!empty($search))
{
    $where[] = "
    (
        action LIKE ?
        OR module LIKE ?
    )
    ";

    $params[] = "%{$search}%";
    $params[] = "%{$search}%";

    $types .= "ss";
}

$whereSql =
implode(
    " AND ",
    $where
);

/*
|--------------------------------------------------------------------------
| COUNT
|--------------------------------------------------------------------------
*/
$countStmt = $conn->prepare("
SELECT COUNT(*) total
FROM audit_logs
WHERE {$whereSql}
");

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
ceil($totalRows/$limit)
);

/*
|--------------------------------------------------------------------------
| LOGS
|--------------------------------------------------------------------------
*/
$sql = "
SELECT

a.*,

u.full_name

FROM audit_logs a

LEFT JOIN users u
ON u.id=a.user_id

WHERE {$whereSql}

ORDER BY a.id DESC

LIMIT ?,?
";

$stmt = $conn->prepare($sql);

$bindTypes =
$types . "ii";

$bindParams =
$params;

$bindParams[] =
$offset;

$bindParams[] =
$limit;

$stmt->bind_param(
    $bindTypes,
    ...$bindParams
);

$stmt->execute();

$logs =
$stmt->get_result();

/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/
$stats =
$conn->query("
SELECT

COUNT(*) total_logs,

COUNT(DISTINCT user_id)
active_admins

FROM audit_logs
")->fetch_assoc();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>
Audit Log
</title>

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
margin-bottom:20px;
border-radius:15px;
box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.metric{
font-size:30px;
font-weight:700;
}

.json-box{
max-height:150px;
overflow:auto;
font-size:12px;
background:#f8f9fa;
padding:10px;
border-radius:8px;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Audit Log

</h2>

<!-- STATS -->

<div class="row g-3 mb-4">

<div class="col-md-6">

<div class="card-box">

<div class="metric">

<?= number_format(
$stats['total_logs']
) ?>

</div>

Total Events

</div>

</div>

<div class="col-md-6">

<div class="card-box">

<div class="metric">

<?= number_format(
$stats['active_admins']
) ?>

</div>

Active Admins

</div>

</div>

</div>

<!-- FILTER -->

<div class="card-box">

<form method="GET">

<div class="row">

<div class="col-md-3">

<input
type="text"
name="search"
class="form-control"
placeholder="Search"
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-2">

<input
type="text"
name="module"
class="form-control"
placeholder="Module"
value="<?= htmlspecialchars($module) ?>">

</div>

<div class="col-md-2">

<input
type="text"
name="action"
class="form-control"
placeholder="Action"
value="<?= htmlspecialchars($action) ?>">

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

<div class="card-box">

<div class="table-responsive">

<table class="table table-bordered">

<thead>

<tr>

<th>ID</th>
<th>Admin</th>
<th>Module</th>
<th>Action</th>
<th>Record</th>
<th>Old Data</th>
<th>New Data</th>
<th>IP</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php while(
$log =
$logs->fetch_assoc()
): ?>

<tr>

<td>
#<?= $log['id'] ?>
</td>

<td>

<?= htmlspecialchars(
$log['full_name']
?? 'System'
) ?>

</td>

<td>

<?= htmlspecialchars(
$log['module']
) ?>

</td>

<td>

<?= htmlspecialchars(
$log['action']
) ?>

</td>

<td>

<?= $log['record_id'] ?>

</td>

<td>

<div class="json-box">

<pre><?= htmlspecialchars(
$log['old_data']
) ?></pre>

</div>

</td>

<td>

<div class="json-box">

<pre><?= htmlspecialchars(
$log['new_data']
) ?></pre>

</div>

</td>

<td>

<?= htmlspecialchars(
$log['ip_address']
) ?>

</td>

<td>

<?= date(
'd M Y H:i:s',
strtotime(
$log['created_at']
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

<li class="page-item <?= $page==$i?'active':'' ?>">

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