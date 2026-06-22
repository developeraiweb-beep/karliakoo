<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$userId = (int)($_GET['id'] ?? 0);

if($userId <= 0)
{
    header("Location: users.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/
$userStmt = $conn->prepare("
SELECT *
FROM users
WHERE id=?
LIMIT 1
");

$userStmt->bind_param("i", $userId);
$userStmt->execute();

$user =
$userStmt
->get_result()
->fetch_assoc();

if(!$user)
{
    die("User not found.");
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/
$page = max(1, (int)($_GET['page'] ?? 1));

$limit = 50;
$offset = ($page - 1) * $limit;

$type = trim($_GET['type'] ?? '');

$where = "user_id=?";
$params = [$userId];
$types = "i";

if(!empty($type))
{
    $where .= " AND activity_type=?";
    $params[] = $type;
    $types .= "s";
}

/*
|--------------------------------------------------------------------------
| COUNT
|--------------------------------------------------------------------------
*/
$countSql = "
SELECT COUNT(*) total
FROM user_activities
WHERE {$where}
";

$countStmt = $conn->prepare($countSql);
$countStmt->bind_param($types, ...$params);
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
| ACTIVITIES
|--------------------------------------------------------------------------
*/
$sql = "
SELECT *
FROM user_activities
WHERE {$where}
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

$activities =
$stmt->get_result();

/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/
$stats = $conn->prepare("
SELECT

COUNT(*) total,

SUM(
CASE
WHEN activity_type='login'
THEN 1
ELSE 0
END
) logins,

SUM(
CASE
WHEN activity_type='order'
THEN 1
ELSE 0
END
) orders,

SUM(
CASE
WHEN activity_type='payment'
THEN 1
ELSE 0
END
) payments

FROM user_activities

WHERE user_id=?
");

$stats->bind_param(
    "i",
    $userId
);

$stats->execute();

$stats =
$stats
->get_result()
->fetch_assoc();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>User Activity</title>

<meta
name="viewport"
content="width=device-width, initial-scale=1">

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
box-shadow:0 2px 12px rgba(0,0,0,.08);
margin-bottom:20px;
}

.metric{
font-size:28px;
font-weight:700;
}

.timeline{
border-left:3px solid #dee2e6;
padding-left:20px;
}

.timeline-item{
position:relative;
margin-bottom:20px;
}

.timeline-item::before{
content:'';
width:14px;
height:14px;
background:#0d6efd;
border-radius:50%;
position:absolute;
left:-28px;
top:4px;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between mb-4">

<div>

<h2>

User Activity

</h2>

<p class="text-muted">

<?= htmlspecialchars(
$user['full_name']
) ?>

</p>

</div>

<a
href="user-details.php?id=<?= $userId ?>"
class="btn btn-secondary">

Back

</a>

</div>

<!-- STATS -->

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="card-box">

<div class="metric">

<?= number_format($stats['total']) ?>

</div>

Activities

</div>

</div>

<div class="col-md-3">

<div class="card-box">

<div class="metric">

<?= number_format($stats['logins']) ?>

</div>

Logins

</div>

</div>

<div class="col-md-3">

<div class="card-box">

<div class="metric">

<?= number_format($stats['orders']) ?>

</div>

Orders

</div>

</div>

<div class="col-md-3">

<div class="card-box">

<div class="metric">

<?= number_format($stats['payments']) ?>

</div>

Payments

</div>

</div>

</div>

<!-- FILTER -->

<div class="card-box">

<form method="GET">

<input
type="hidden"
name="id"
value="<?= $userId ?>">

<div class="row">

<div class="col-md-4">

<select
name="type"
class="form-select">

<option value="">
All Activities
</option>

<option value="login">
Login
</option>

<option value="order">
Order
</option>

<option value="payment">
Payment
</option>

<option value="withdrawal">
Withdrawal
</option>

<option value="product">
Product
</option>

<option value="shop">
Shop
</option>

<option value="support">
Support
</option>

</select>

</div>

<div class="col-md-2">

<button
class="btn btn-primary">

Filter

</button>

</div>

</div>

</form>

</div>

<!-- TIMELINE -->

<div class="card-box">

<h4>

Activity Timeline

</h4>

<div class="timeline">

<?php while(
$activity =
$activities->fetch_assoc()
): ?>

<div class="timeline-item">

<h6>

<?= htmlspecialchars(
$activity['activity_title']
) ?>

</h6>

<p class="mb-1">

<?= htmlspecialchars(
$activity['description']
) ?>

</p>

<small class="text-muted">

<?= ucfirst(
$activity['activity_type']
) ?>

<?php if(
$activity['reference_type']
): ?>

|

<?= htmlspecialchars(
$activity['reference_type']
) ?>

#<?= $activity['reference_id'] ?>

<?php endif; ?>

|

<?= date(
'd M Y H:i',
strtotime(
$activity['created_at']
)
) ?>

</small>

</div>

<?php endwhile; ?>

</div>

</div>

<!-- PAGINATION -->

<nav>

<ul class="pagination">

<?php for($i=1;$i<=$totalPages;$i++): ?>

<li class="page-item <?= $page==$i?'active':'' ?>">

<a
class="page-link"
href="?id=<?= $userId ?>&page=<?= $i ?>">

<?= $i ?>

</a>

</li>

<?php endfor; ?>

</ul>

</nav>

</div>

</body>
</html>