<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$role = trim($_GET['role'] ?? '');
$status = trim($_GET['status'] ?? '');

/*
|--------------------------------------------------------------------------
| ACCOUNT ACTIONS
|--------------------------------------------------------------------------
*/
if(isset($_GET['suspend']))
{
    $id = (int)$_GET['suspend'];

    if($id != $_SESSION['user_id'])
    {
        $stmt = $conn->prepare("
            UPDATE users
            SET status='suspended'
            WHERE id=?
        ");

        $stmt->bind_param("i",$id);
        $stmt->execute();
    }

    header("Location: users.php");
    exit;
}

if(isset($_GET['activate']))
{
    $id = (int)$_GET['activate'];

    $stmt = $conn->prepare("
        UPDATE users
        SET status='active'
        WHERE id=?
    ");

    $stmt->bind_param("i",$id);
    $stmt->execute();

    header("Location: users.php");
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

if(!empty($search))
{
    $where[] = "(full_name LIKE ? OR email LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $types .= "ss";
}

if(!empty($role))
{
    $where[] = "role=?";
    $params[] = $role;
    $types .= "s";
}

if(!empty($status))
{
    $where[] = "status=?";
    $params[] = $status;
    $types .= "s";
}

$whereSql = implode(" AND ",$where);

/*
|--------------------------------------------------------------------------
| TOTAL USERS
|--------------------------------------------------------------------------
*/
$countSql = "
SELECT COUNT(*) total
FROM users
WHERE {$whereSql}
";

$countStmt = $conn->prepare($countSql);

if(!empty($params))
{
    $countStmt->bind_param($types,...$params);
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
| USERS
|--------------------------------------------------------------------------
*/
$sql = "
SELECT *
FROM users

WHERE {$whereSql}

ORDER BY id DESC

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

$users = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| DASHBOARD COUNTS
|--------------------------------------------------------------------------
*/
$totalUsers = $conn->query("
SELECT COUNT(*) total
FROM users
")->fetch_assoc()['total'];

$activeUsers = $conn->query("
SELECT COUNT(*) total
FROM users
WHERE status='active'
")->fetch_assoc()['total'];

$suspendedUsers = $conn->query("
SELECT COUNT(*) total
FROM users
WHERE status='suspended'
")->fetch_assoc()['total'];

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>
User Management
</title>

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
background:#f5f7fb;
}

.card-box{
background:#fff;
padding:20px;
border-radius:15px;
box-shadow:0 2px 12px rgba(0,0,0,.08);
}

.metric{
font-size:30px;
font-weight:700;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

User Management

</h2>

<!-- STATS -->

<div class="row g-3 mb-4">

<div class="col-md-4">

<div class="card-box">

<div class="metric">

<?= number_format($totalUsers) ?>

</div>

Total Users

</div>

</div>

<div class="col-md-4">

<div class="card-box">

<div class="metric text-success">

<?= number_format($activeUsers) ?>

</div>

Active Users

</div>

</div>

<div class="col-md-4">

<div class="card-box">

<div class="metric text-danger">

<?= number_format($suspendedUsers) ?>

</div>

Suspended Users

</div>

</div>

</div>

<!-- FILTERS -->

<div class="card-box mb-4">

<form method="GET">

<div class="row">

<div class="col-md-4">

<input
type="text"
name="search"
value="<?= htmlspecialchars($search) ?>"
class="form-control"
placeholder="Search name or email">

</div>

<div class="col-md-2">

<select
name="role"
class="form-select">

<option value="">
All Roles
</option>

<option value="admin">Admin</option>
<option value="seller">Seller</option>
<option value="agent">Agent</option>
<option value="delivery">Delivery</option>
<option value="vendor">Vendor</option>
<option value="b2b">B2B</option>
<option value="user">User</option>

</select>

</div>

<div class="col-md-2">

<select
name="status"
class="form-select">

<option value="">
All Status
</option>

<option value="active">
Active
</option>

<option value="suspended">
Suspended
</option>

<option value="pending">
Pending
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

<!-- USERS TABLE -->

<div class="card-box">

<div class="table-responsive">

<table class="table table-hover align-middle">

<thead>

<tr>

<th>ID</th>
<th>Name</th>
<th>Email</th>
<th>Role</th>
<th>Status</th>
<th>Created</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php while($user = $users->fetch_assoc()): ?>

<tr>

<td>
#<?= $user['id'] ?>
</td>

<td>

<?= htmlspecialchars(
$user['full_name']
) ?>

</td>

<td>

<?= htmlspecialchars(
$user['email']
) ?>

</td>

<td>

<span class="badge bg-dark">

<?= ucfirst(
$user['role']
) ?>

</span>

</td>

<td>

<?php if($user['status']=='active'): ?>

<span class="badge bg-success">
Active
</span>

<?php elseif($user['status']=='suspended'): ?>

<span class="badge bg-danger">
Suspended
</span>

<?php else: ?>

<span class="badge bg-warning">
Pending
</span>

<?php endif; ?>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$user['created_at']
)
) ?>

</td>

<td>

<a
href="user-details.php?id=<?= $user['id'] ?>"
class="btn btn-sm btn-primary">

View

</a>

<?php if(
$user['status']=='active'
&&
$user['id'] != $_SESSION['user_id']
): ?>

<a
href="?suspend=<?= $user['id'] ?>"
class="btn btn-sm btn-danger"
onclick="return confirm('Suspend this account?')">

Suspend

</a>

<?php endif; ?>

<?php if(
$user['status']=='suspended'
): ?>

<a
href="?activate=<?= $user['id'] ?>"
class="btn btn-sm btn-success">

Activate

</a>

<?php endif; ?>

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

<?php for($i=1;$i<=$totalPages;$i++): ?>

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