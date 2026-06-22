<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$status = trim($_GET['status'] ?? '');
$type = trim($_GET['type'] ?? '');
$search = trim($_GET['search'] ?? '');

/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/
if (
    isset($_GET['action']) &&
    isset($_GET['id'])
) {

    $commissionId = (int)$_GET['id'];

    if ($_GET['action'] == 'approve') {

        $stmt = $conn->prepare("
            UPDATE commissions
            SET status='earned'
            WHERE id=?
        ");

        $stmt->bind_param(
            "i",
            $commissionId
        );

        $stmt->execute();
    }

    if ($_GET['action'] == 'pay') {

        $stmt = $conn->prepare("
            UPDATE commissions
            SET

            status='paid',
            paid_at=NOW()

            WHERE id=?
        ");

        $stmt->bind_param(
            "i",
            $commissionId
        );

        $stmt->execute();
    }

    if ($_GET['action'] == 'cancel') {

        $stmt = $conn->prepare("
            UPDATE commissions
            SET status='cancelled'
            WHERE id=?
        ");

        $stmt->bind_param(
            "i",
            $commissionId
        );

        $stmt->execute();
    }

    header("Location: commissions.php");
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

if (!empty($status)) {

    $where[] = "c.status=?";

    $params[] = $status;

    $types .= "s";
}

if (!empty($type)) {

    $where[] = "c.commission_type=?";

    $params[] = $type;

    $types .= "s";
}

if (!empty($search)) {

    $where[] = "
    (
        u.full_name LIKE ?
        OR u.email LIKE ?
    )
    ";

    $like = "%{$search}%";

    $params[] = $like;
    $params[] = $like;

    $types .= "ss";
}

$whereSql = implode(
    " AND ",
    $where
);

/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/
$stats = $conn->query("
SELECT

COUNT(*) total_commissions,

COALESCE(
SUM(amount),
0
) total_amount,

COALESCE(
SUM(CASE
WHEN status='pending'
THEN amount
ELSE 0
END),
0
) pending_amount,

COALESCE(
SUM(CASE
WHEN status='earned'
THEN amount
ELSE 0
END),
0
) earned_amount,

COALESCE(
SUM(CASE
WHEN status='paid'
THEN amount
ELSE 0
END),
0
) paid_amount

FROM commissions
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/
$countSql = "
SELECT COUNT(*) total

FROM commissions c

LEFT JOIN users u
ON u.id=c.user_id

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
| COMMISSIONS
|--------------------------------------------------------------------------
*/
$sql = "
SELECT

c.*,

u.full_name,
u.email,
u.role,

o.order_number

FROM commissions c

LEFT JOIN users u
ON u.id=c.user_id

LEFT JOIN orders o
ON o.id=c.order_id

WHERE {$whereSql}

ORDER BY c.id DESC

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

$commissions =
$stmt
->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Commission Management

</title>

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
border-radius:12px;
box-shadow:0 2px 8px rgba(0,0,0,.08);
}

.metric{
font-size:26px;
font-weight:700;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Commission Management

</h2>

<!-- KPI -->

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($stats['total_amount'],2) ?>
</div>
Total Commissions
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($stats['pending_amount'],2) ?>
</div>
Pending
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($stats['earned_amount'],2) ?>
</div>
Earned
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="metric">
TZS <?= number_format($stats['paid_amount'],2) ?>
</div>
Paid
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
class="form-control"
placeholder="User Search"
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-3">

<select
name="type"
class="form-select">

<option value="">All Types</option>
<option value="seller">Seller</option>
<option value="agent">Agent</option>
<option value="referral">Referral</option>
<option value="b2b">B2B</option>

</select>

</div>

<div class="col-md-3">

<select
name="status"
class="form-select">

<option value="">All Status</option>
<option value="pending">Pending</option>
<option value="earned">Earned</option>
<option value="paid">Paid</option>
<option value="cancelled">Cancelled</option>

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

<!-- TABLE -->

<div class="card-box">

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>ID</th>
<th>User</th>
<th>Role</th>
<th>Order</th>
<th>Type</th>
<th>Amount</th>
<th>Status</th>
<th>Date</th>
<th>Actions</th>

</tr>

</thead>

<tbody>

<?php while($row = $commissions->fetch_assoc()): ?>

<tr>

<td>
#<?= $row['id'] ?>
</td>

<td>

<strong>

<?= htmlspecialchars(
$row['full_name']
) ?>

</strong>

<br>

<small>

<?= htmlspecialchars(
$row['email']
) ?>

</small>

</td>

<td>

<?= ucfirst(
$row['role']
) ?>

</td>

<td>

<?= htmlspecialchars(
$row['order_number']
?? '-'
) ?>

</td>

<td>

<?= ucfirst(
$row['commission_type']
) ?>

</td>

<td>

TZS

<?= number_format(
$row['amount'],
2
) ?>

</td>

<td>

<?php

$color = match(
$row['status']
){

'paid'      => 'success',
'earned'    => 'primary',
'cancelled' => 'danger',
default     => 'warning'

};

?>

<span
class="badge bg-<?= $color ?>">

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

<div class="btn-group">

<a
href="?action=approve&id=<?= $row['id'] ?>"
class="btn btn-sm btn-success">

Approve

</a>

<a
href="?action=pay&id=<?= $row['id'] ?>"
class="btn btn-sm btn-primary">

Pay

</a>

<a
href="?action=cancel&id=<?= $row['id'] ?>"
class="btn btn-sm btn-danger">

Cancel

</a>

</div>

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

<li
class="page-item <?= $page==$i ? 'active':'' ?>">

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