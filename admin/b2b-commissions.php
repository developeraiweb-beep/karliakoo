<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

/*
|--------------------------------------------------------------------------
| MARK COMMISSION PAID
|--------------------------------------------------------------------------
*/
if (
    isset($_GET['action']) &&
    $_GET['action'] === 'paid' &&
    isset($_GET['id'])
) {

    $id = (int)$_GET['id'];

    $stmt = $conn->prepare("
        UPDATE b2b_commissions
        SET
            status='paid',
            paid_at=NOW()
        WHERE id=?
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location:b2b-commissions.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/
$where = ["1=1"];
$params = [];
$types = '';

if (!empty($status)) {

    $where[] = "c.status=?";

    $params[] = $status;
    $types .= "s";
}

if (!empty($search)) {

    $where[] = "(
        o.order_number LIKE ?
        OR s.shop_name LIKE ?
    )";

    $like = "%{$search}%";

    $params[] = $like;
    $params[] = $like;

    $types .= "ss";
}

$whereSQL = implode(" AND ", $where);

/*
|--------------------------------------------------------------------------
| COMMISSIONS
|--------------------------------------------------------------------------
*/
$sql = "

SELECT

c.*,

o.order_number,

s.shop_name

FROM b2b_commissions c

LEFT JOIN b2b_orders o
ON o.id=c.order_id

LEFT JOIN shops s
ON s.id=c.shop_id

WHERE {$whereSQL}

ORDER BY c.id DESC

";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$commissions = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/
$summary = $conn->query("
SELECT

COALESCE(
SUM(commission_amount),
0
) total_commission,

COALESCE(
SUM(
CASE
WHEN status='pending'
THEN commission_amount
ELSE 0
END
),
0
) pending_commission,

COALESCE(
SUM(
CASE
WHEN status='paid'
THEN commission_amount
ELSE 0
END
),
0
) paid_commission,

COALESCE(
SUM(
CASE
WHEN YEAR(created_at)=YEAR(CURDATE())
AND MONTH(created_at)=MONTH(CURDATE())
THEN commission_amount
ELSE 0
END
),
0
) monthly_commission

FROM b2b_commissions
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| TOP SUPPLIERS
|--------------------------------------------------------------------------
*/
$topSuppliers = $conn->query("
SELECT

s.shop_name,

COUNT(c.id) total_records,

SUM(c.commission_amount)
total_commission

FROM b2b_commissions c

INNER JOIN shops s
ON s.id=c.shop_id

GROUP BY c.shop_id

ORDER BY total_commission DESC

LIMIT 10
");

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>

B2B Commissions

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

B2B Commission Management

</h2>

<!-- SUMMARY -->

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

TZS
<?= number_format(
$summary['total_commission'],
2
) ?>

</h4>

<p>Total Commission</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

TZS
<?= number_format(
$summary['monthly_commission'],
2
) ?>

</h4>

<p>This Month</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

TZS
<?= number_format(
$summary['pending_commission'],
2
) ?>

</h4>

<p>Pending</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>

TZS
<?= number_format(
$summary['paid_commission'],
2
) ?>

</h4>

<p>Paid</p>

</div>

</div>

</div>

<!-- FILTER -->

<div class="card shadow-sm mb-4">

<div class="card-body">

<form method="GET">

<div class="row">

<div class="col-md-5">

<input
type="text"
name="search"
class="form-control"
placeholder="Order or Supplier"
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-4">

<select
name="status"
class="form-select">

<option value="">
All Statuses
</option>

<option value="pending">
Pending
</option>

<option value="paid">
Paid
</option>

<option value="cancelled">
Cancelled
</option>

</select>

</div>

<div class="col-md-3">

<button
class="btn btn-primary w-100">

Filter

</button>

</div>

</div>

</form>

</div>

</div>

<!-- COMMISSIONS -->

<div class="card shadow-sm mb-4">

<div class="card-header">

Commission Records

</div>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Order</th>
<th>Supplier</th>
<th>Rate</th>
<th>Order Value</th>
<th>Commission</th>
<th>Status</th>
<th>Date</th>
<th></th>

</tr>

</thead>

<tbody>

<?php while($row = $commissions->fetch_assoc()): ?>

<tr>

<td>

<?= htmlspecialchars(
$row['order_number']
) ?>

</td>

<td>

<?= htmlspecialchars(
$row['shop_name']
) ?>

</td>

<td>

<?= number_format(
$row['commission_rate'],
2
) ?>%

</td>

<td>

TZS

<?= number_format(
$row['order_amount'],
2
) ?>

</td>

<td>

TZS

<?= number_format(
$row['commission_amount'],
2
) ?>

</td>

<td>

<?php

$badge = match($row['status']) {

'pending' => 'warning',
'paid' => 'success',
'cancelled' => 'danger',
default => 'secondary'

};

?>

<span class="badge bg-<?= $badge ?>">

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

<?php if(
$row['status']==='pending'
): ?>

<a
href="?action=paid&id=<?= $row['id'] ?>"
class="btn btn-sm btn-success">

Mark Paid

</a>

<?php endif; ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<!-- TOP SUPPLIERS -->

<div class="card shadow-sm">

<div class="card-header">

Top Commission Contributors

</div>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Supplier</th>
<th>Records</th>
<th>Total Commission</th>

</tr>

</thead>

<tbody>

<?php while($supplier = $topSuppliers->fetch_assoc()): ?>

<tr>

<td>

<?= htmlspecialchars(
$supplier['shop_name']
) ?>

</td>

<td>

<?= number_format(
$supplier['total_records']
) ?>

</td>

<td>

TZS

<?= number_format(
$supplier['total_commission'],
2
) ?>

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