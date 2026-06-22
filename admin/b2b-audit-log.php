<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$page = max(
    1,
    (int)($_GET['page'] ?? 1)
);

$limit = 50;
$offset = ($page - 1) * $limit;

$action = trim($_GET['action'] ?? '');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');
$userId = (int)($_GET['user_id'] ?? 0);

$where = ["1=1"];
$params = [];
$types = '';

if ($userId > 0) {

    $where[] = "l.user_id=?";
    $params[] = $userId;
    $types .= "i";
}

if ($action !== '') {

    $where[] = "l.action=?";
    $params[] = $action;
    $types .= "s";
}

if ($from !== '') {

    $where[] = "DATE(l.created_at)>=?";
    $params[] = $from;
    $types .= "s";
}

if ($to !== '') {

    $where[] = "DATE(l.created_at)<=?";
    $params[] = $to;
    $types .= "s";
}

$whereSQL = implode(
    " AND ",
    $where
);

/*
|--------------------------------------------------------------------------
| TOTAL ROWS
|--------------------------------------------------------------------------
*/
$countSql = "
SELECT COUNT(*) total
FROM b2b_audit_logs l
WHERE {$whereSQL}
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
| LOGS
|--------------------------------------------------------------------------
*/
$sql = "
SELECT

l.*,

u.full_name

FROM b2b_audit_logs l

LEFT JOIN users u
ON u.id=l.user_id

WHERE {$whereSQL}

ORDER BY l.id DESC

LIMIT ?, ?
";

$stmt = $conn->prepare($sql);

$bindParams = $params;
$bindTypes = $types . "ii";

$bindParams[] = $offset;
$bindParams[] = $limit;

$stmt->bind_param(
    $bindTypes,
    ...$bindParams
);

$stmt->execute();

$logs =
    $stmt
    ->get_result();

/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/
$actions =
$conn->query("
SELECT DISTINCT action

FROM b2b_audit_logs

ORDER BY action ASC
");

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>

B2B Audit Logs

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

.small-text{
font-size:13px;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

B2B Audit Logs

</h2>

<div class="card-box shadow-sm p-4 mb-4">

<form method="GET">

<div class="row g-2">

<div class="col-md-3">

<input
type="number"
name="user_id"
class="form-control"
placeholder="User ID"
value="<?= $userId ?: '' ?>">

</div>

<div class="col-md-3">

<select
name="action"
class="form-select">

<option value="">
All Actions
</option>

<?php while($a = $actions->fetch_assoc()): ?>

<option
value="<?= htmlspecialchars($a['action']) ?>"
<?= $action === $a['action']
? 'selected'
: ''
?>>

<?= htmlspecialchars($a['action']) ?>

</option>

<?php endwhile; ?>

</select>

</div>

<div class="col-md-2">

<input
type="date"
name="from"
class="form-control"
value="<?= htmlspecialchars($from) ?>">

</div>

<div class="col-md-2">

<input
type="date"
name="to"
class="form-control"
value="<?= htmlspecialchars($to) ?>">

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

<div class="card-box shadow-sm p-4">

<div class="table-responsive">

<table class="table table-hover align-middle">

<thead>

<tr>

<th>#</th>
<th>User</th>
<th>Role</th>
<th>Action</th>
<th>Entity</th>
<th>Description</th>
<th>IP</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php while($log = $logs->fetch_assoc()): ?>

<tr>

<td>

<?= $log['id'] ?>

</td>

<td>

<?= htmlspecialchars(
$log['full_name']
?? 'System'
) ?>

</td>

<td>

<?= htmlspecialchars(
$log['role']
?? '-'
) ?>

</td>

<td>

<span class="badge bg-dark">

<?= htmlspecialchars(
$log['action']
) ?>

</span>

</td>

<td>

<?= htmlspecialchars(
$log['entity_type']
?? '-'
) ?>

<?php if(
!empty($log['entity_id'])
): ?>

#<?= $log['entity_id'] ?>

<?php endif; ?>

</td>

<td
class="small-text">

<?= htmlspecialchars(
$log['description']
?? '-'
) ?>

</td>

<td>

<?= htmlspecialchars(
$log['ip_address']
?? '-'
) ?>

</td>

<td>

<?= date(
'd M Y H:i',
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

<nav class="mt-4">

<ul class="pagination">

<?php for(
$i = 1;
$i <= $totalPages;
$i++
): ?>

<li
class="page-item <?= $i == $page ? 'active' : '' ?>">

<a
class="page-link"
href="?page=<?= $i ?>&action=<?= urlencode($action) ?>&user_id=<?= $userId ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>">

<?= $i ?>

</a>

</li>

<?php endfor; ?>

</ul>

</nav>

</div>

</body>
</html>