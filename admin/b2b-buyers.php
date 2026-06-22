<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$search = trim($_GET['search'] ?? '');

/*
|--------------------------------------------------------------------------
| SUSPEND / ACTIVATE
|--------------------------------------------------------------------------
*/
if (
    isset($_GET['action']) &&
    isset($_GET['buyer'])
) {

    $buyer_id = (int)$_GET['buyer'];

    if ($_GET['action'] === 'suspend') {

        $stmt = $conn->prepare("
            UPDATE users
            SET status='suspended'
            WHERE id=?
        ");

        $stmt->bind_param(
            "i",
            $buyer_id
        );

        $stmt->execute();
    }

    if ($_GET['action'] === 'activate') {

        $stmt = $conn->prepare("
            UPDATE users
            SET status='active'
            WHERE id=?
        ");

        $stmt->bind_param(
            "i",
            $buyer_id
        );

        $stmt->execute();
    }

    header("Location:b2b-buyers.php");
    exit;
}

$where = "";

$params = [];
$types = "";

if (!empty($search)) {

    $where = "
    WHERE
    u.full_name LIKE ?
    OR u.email LIKE ?
    ";

    $like = "%{$search}%";

    $params[] = $like;
    $params[] = $like;

    $types = "ss";
}

/*
|--------------------------------------------------------------------------
| BUYERS
|--------------------------------------------------------------------------
*/
$sql = "

SELECT

u.id,
u.full_name,
u.email,
u.phone,
u.status,
u.created_at,

COUNT(DISTINCT o.id) total_orders,

COALESCE(
SUM(o.total_amount),
0
) total_spent,

COUNT(DISTINCT r.id) total_rfqs,

COUNT(DISTINCT d.id) disputes

FROM users u

LEFT JOIN b2b_orders o
ON o.buyer_id=u.id

LEFT JOIN rfq_requests r
ON r.buyer_id=u.id

LEFT JOIN b2b_disputes d
ON d.buyer_id=u.id

{$where}

GROUP BY u.id

ORDER BY total_spent DESC

";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$buyers = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/
$stats = $conn->query("
SELECT

COUNT(DISTINCT buyer_id)
total_buyers,

COUNT(*) total_orders,

SUM(total_amount)
revenue

FROM b2b_orders
")->fetch_assoc();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>
B2B Buyers
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

B2B Buyers Management

</h2>

<div class="row g-3 mb-4">

<div class="col-md-4">

<div class="stat-card shadow-sm">

<h4>

<?= number_format(
$stats['total_buyers']
) ?>

</h4>

<p>Total Buyers</p>

</div>

</div>

<div class="col-md-4">

<div class="stat-card shadow-sm">

<h4>

<?= number_format(
$stats['total_orders']
) ?>

</h4>

<p>Total Orders</p>

</div>

</div>

<div class="col-md-4">

<div class="stat-card shadow-sm">

<h4>

TZS

<?= number_format(
$stats['revenue'],
2
) ?>

</h4>

<p>Total Buyer Spend</p>

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
placeholder="Search buyer"
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

Wholesale Buyers

</div>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Buyer</th>
<th>Phone</th>
<th>Orders</th>
<th>RFQs</th>
<th>Disputes</th>
<th>Total Spent</th>
<th>Status</th>
<th>Actions</th>

</tr>

</thead>

<tbody>

<?php while($buyer = $buyers->fetch_assoc()): ?>

<tr>

<td>

<strong>

<?= htmlspecialchars(
$buyer['full_name']
) ?>

</strong>

<br>

<small>

<?= htmlspecialchars(
$buyer['email']
) ?>

</small>

</td>

<td>

<?= htmlspecialchars(
$buyer['phone']
) ?>

</td>

<td>

<?= number_format(
$buyer['total_orders']
) ?>

</td>

<td>

<?= number_format(
$buyer['total_rfqs']
) ?>

</td>

<td>

<?= number_format(
$buyer['disputes']
) ?>

</td>

<td>

TZS

<?= number_format(
$buyer['total_spent'],
2
) ?>

</td>

<td>

<?php if(
$buyer['status']==='active'
): ?>

<span class="badge bg-success">

Active

</span>

<?php else: ?>

<span class="badge bg-danger">

Suspended

</span>

<?php endif; ?>

</td>

<td>

<a
href="buyer-details.php?id=<?= $buyer['id'] ?>"
class="btn btn-sm btn-primary">

View

</a>

<?php if(
$buyer['status']==='active'
): ?>

<a
href="?action=suspend&buyer=<?= $buyer['id'] ?>"
class="btn btn-sm btn-danger">

Suspend

</a>

<?php else: ?>

<a
href="?action=activate&buyer=<?= $buyer['id'] ?>"
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

</div>

</body>
</html>