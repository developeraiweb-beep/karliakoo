<?php

declare(strict_types=1);

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

requireRole(['admin']);

$admin = currentUser();

$statusFilter =
trim($_GET['status'] ?? '');

$where = "";
$params = [];
$types = "";

if(
    in_array(
        $statusFilter,
        [
            'pending',
            'approved',
            'paid',
            'rejected'
        ]
    )
)
{
    $where =
    " WHERE w.status = ? ";

    $params[] =
    $statusFilter;

    $types .= "s";
}

/*
|--------------------------------------------------------------------------
| DASHBOARD STATS
|--------------------------------------------------------------------------
*/

$stats = [

    'pending' => 0,
    'approved' => 0,
    'paid' => 0,
    'rejected' => 0,
    'total_amount' => 0

];

$statsQuery = mysqli_query(
    $conn,
    "
    SELECT

        status,
        COUNT(*) total_requests,
        SUM(amount) total_amount

    FROM withdrawals

    GROUP BY status
    "
);

while(
    $row =
    mysqli_fetch_assoc(
        $statsQuery
    )
)
{
    $stats[
        $row['status']
    ] =
    (int)$row['total_requests'];

    $stats['total_amount'] +=
    (float)$row['total_amount'];
}

/*
|--------------------------------------------------------------------------
| LOAD WITHDRAWALS
|--------------------------------------------------------------------------
*/

$sql = "
SELECT

    w.*,

    u.full_name,
    u.email,
    u.phone

FROM withdrawals w

INNER JOIN users u
ON u.id = w.user_id

{$where}

ORDER BY w.id DESC
";

$stmt =
$conn->prepare($sql);

if(!empty($params))
{
    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$withdrawals =
$stmt
->get_result();

if (
    empty($_SESSION['csrf_token'])
)
{
    $_SESSION['csrf_token'] =
    bin2hex(
        random_bytes(32)
    );
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Withdrawal Management

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f7fb;
}

.card-stat{
    border:none;
    border-radius:15px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.stat-number{
    font-size:28px;
    font-weight:700;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>

<i class="fas fa-money-check-dollar"></i>

Withdrawal Management

</h2>

<p class="text-muted">

Manage seller withdrawal requests

</p>

</div>

<a
href="dashboard.php"
class="btn btn-secondary">

Dashboard

</a>

</div>

<div class="row mb-4">

<div class="col-md-3">

<div class="card card-stat">

<div class="card-body">

<h6>Pending</h6>

<div class="stat-number text-warning">

<?= number_format(
$stats['pending']
) ?>

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card card-stat">

<div class="card-body">

<h6>Approved</h6>

<div class="stat-number text-success">

<?= number_format(
$stats['approved']
) ?>

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card card-stat">

<div class="card-body">

<h6>Paid</h6>

<div class="stat-number text-primary">

<?= number_format(
$stats['paid']
) ?>

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card card-stat">

<div class="card-body">

<h6>Total Amount</h6>

<div class="stat-number text-dark">

<?= number_format(
$stats['total_amount'],
2
) ?>

</div>

</div>

</div>

</div>

</div>

<div class="card mb-4">

<div class="card-body">

<form method="GET">

<div class="row">

<div class="col-md-4">

<select
name="status"
class="form-select">

<option value="">

All Statuses

</option>

<option
value="pending"
<?= $statusFilter=='pending'?'selected':'' ?>>

Pending

</option>

<option
value="approved"
<?= $statusFilter=='approved'?'selected':'' ?>>

Approved

</option>

<option
value="paid"
<?= $statusFilter=='paid'?'selected':'' ?>>

Paid

</option>

<option
value="rejected"
<?= $statusFilter=='rejected'?'selected':'' ?>>

Rejected

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

</div>

<?php if(
isset($_GET['success'])
): ?>

<div class="alert alert-success">

<?= htmlspecialchars(
$_GET['success']
) ?>

</div>

<?php endif; ?>

<?php if(
isset($_GET['error'])
): ?>

<div class="alert alert-danger">

<?= htmlspecialchars(
$_GET['error']
) ?>

</div>

<?php endif; ?>

<div class="card">

<div class="card-header d-flex justify-content-between align-items-center">

<h5 class="mb-0">

Withdrawal Requests

</h5>

<span class="badge bg-dark">

<?= number_format(
$withdrawals->num_rows
) ?>

Records

</span>

</div>

<div class="card-body table-responsive">

<table class="table table-bordered table-hover align-middle">

<thead>

<tr>

<th>ID</th>

<th>Seller</th>

<th>Amount</th>

<th>Method</th>

<th>Account</th>

<th>Status</th>

<th>Requested</th>

<th>Reference</th>

<th>Actions</th>

</tr>

</thead>

<tbody>

<?php
if(
    $withdrawals->num_rows > 0
):
?>

<?php
while(
    $withdrawal =
    $withdrawals->fetch_assoc()
):
?>

<?php

$status =
$withdrawal['status'];

$badge =
match($status)
{
    'pending'  => 'warning',
    'approved' => 'success',
    'paid'     => 'primary',
    'rejected' => 'danger',
    default    => 'secondary'
};

?>

<tr>

<td>

#<?= (int)$withdrawal['id'] ?>

</td>

<td>

<strong>

<?= htmlspecialchars(
$withdrawal['full_name']
) ?>

</strong>

<br>

<small class="text-muted">

<?= htmlspecialchars(
$withdrawal['email']
) ?>

</small>

<br>

<small class="text-muted">

<?= htmlspecialchars(
$withdrawal['phone']
) ?>

</small>

</td>

<td>

<strong>

TZS

<?= number_format(
(float)$withdrawal['amount'],
2
) ?>

</strong>

</td>

<td>

<?= htmlspecialchars(
$withdrawal['method']
) ?>

</td>

<td>

<?= htmlspecialchars(
$withdrawal['account_name']
?? '-'
) ?>

<br>

<small>

<?= htmlspecialchars(
$withdrawal['account_number']
?? '-'
) ?>

</small>

</td>

<td>

<span
class="badge bg-<?= $badge ?>">

<?= ucfirst(
$status
) ?>

</span>

</td>

<td>

<?= date(
'd M Y H:i',
strtotime(
$withdrawal['requested_at']
)
) ?>

</td>

<td>

<?= htmlspecialchars(
$withdrawal['transaction_reference']
?? '-'
) ?>

</td>

<td>

<div class="btn-group">

<a
href="withdrawal-details.php?id=<?= (int)$withdrawal['id'] ?>"
class="btn btn-sm btn-info">

View

</a>

<?php if(
$status === 'pending'
): ?>

<form
method="POST"
action="withdrawal-action.php"
class="d-inline">

<input
type="hidden"
name="csrf_token"
value="<?= $_SESSION['csrf_token'] ?>">

<input
type="hidden"
name="withdrawal_id"
value="<?= (int)$withdrawal['id'] ?>">

<input
type="hidden"
name="action"
value="approve">

<button
type="submit"
class="btn btn-sm btn-success">

Approve

</button>

</form>

<form
method="POST"
action="withdrawal-action.php"
class="d-inline">

<input
type="hidden"
name="csrf_token"
value="<?= $_SESSION['csrf_token'] ?>">

<input
type="hidden"
name="withdrawal_id"
value="<?= (int)$withdrawal['id'] ?>">

<input
type="hidden"
name="action"
value="reject">

<button
type="submit"
class="btn btn-sm btn-danger">

Reject

</button>

</form>

<?php endif; ?>

<?php if(
$status === 'approved'
): ?>

<form
method="POST"
action="withdrawal-action.php"
class="d-inline">

<input
type="hidden"
name="csrf_token"
value="<?= $_SESSION['csrf_token'] ?>">

<input
type="hidden"
name="withdrawal_id"
value="<?= (int)$withdrawal['id'] ?>">

<input
type="hidden"
name="action"
value="mark_paid">

<button
type="submit"
class="btn btn-sm btn-primary">

Mark Paid

</button>

</form>

<?php endif; ?>

</div>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td
colspan="9"
class="text-center text-muted">

No withdrawal requests found.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

</div>

</body>

</html>