<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

if (!$user || $user['role'] !== 'agent') {
    die("Access denied.");
}

$agent_id = (int)$user['id'];

/*
|--------------------------------------------------------------------------
| Earnings Summary
|--------------------------------------------------------------------------
*/
$summary = $conn->prepare("
    SELECT

    COALESCE(SUM(commission_amount),0) total_earnings,

    COALESCE(SUM(
        CASE
        WHEN status='pending'
        THEN commission_amount
        ELSE 0
        END
    ),0) pending_earnings,

    COALESCE(SUM(
        CASE
        WHEN status='approved'
        THEN commission_amount
        ELSE 0
        END
    ),0) approved_earnings,

    COALESCE(SUM(
        CASE
        WHEN status='paid'
        THEN commission_amount
        ELSE 0
        END
    ),0) paid_earnings

    FROM agent_commissions

    WHERE agent_id=?
");

$summary->bind_param("i", $agent_id);
$summary->execute();

$stats = $summary->get_result()->fetch_assoc();

/*
|--------------------------------------------------------------------------
| Current Month Earnings
|--------------------------------------------------------------------------
*/
$monthStmt = $conn->prepare("
    SELECT

    COALESCE(SUM(commission_amount),0) monthly_total

    FROM agent_commissions

    WHERE agent_id=?
    AND MONTH(created_at)=MONTH(CURRENT_DATE())
    AND YEAR(created_at)=YEAR(CURRENT_DATE())
");

$monthStmt->bind_param("i", $agent_id);
$monthStmt->execute();

$monthData = $monthStmt->get_result()->fetch_assoc();

/*
|--------------------------------------------------------------------------
| Commission History
|--------------------------------------------------------------------------
*/
$history = $conn->prepare("
    SELECT *
    FROM agent_commissions
    WHERE agent_id=?
    ORDER BY id DESC
    LIMIT 100
");

$history->bind_param("i", $agent_id);
$history->execute();

$commissions = $history->get_result();

/*
|--------------------------------------------------------------------------
| Withdrawable Balance
|--------------------------------------------------------------------------
*/
$withdrawable = $stats['approved_earnings'];

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Agent Earnings</title>

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
    height:100%;
}

</style>

</head>

<body>

<div class="container py-4">

<h2 class="mb-4">
My Earnings
</h2>

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4>
TZS <?= number_format($stats['total_earnings'],2) ?>
</h4>

<div>Total Earnings</div>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4 class="text-warning">
TZS <?= number_format($stats['pending_earnings'],2) ?>
</h4>

<div>Pending</div>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4 class="text-primary">
TZS <?= number_format($stats['approved_earnings'],2) ?>
</h4>

<div>Approved</div>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h4 class="text-success">
TZS <?= number_format($stats['paid_earnings'],2) ?>
</h4>

<div>Paid</div>

</div>

</div>

</div>

<div class="row g-3 mb-4">

<div class="col-md-6">

<div class="stat-card shadow-sm">

<h4>
TZS <?= number_format($monthData['monthly_total'],2) ?>
</h4>

<div>This Month</div>

</div>

</div>

<div class="col-md-6">

<div class="stat-card shadow-sm">

<h4 class="text-success">
TZS <?= number_format($withdrawable,2) ?>
</h4>

<div>Available For Withdrawal</div>

</div>

</div>

</div>

<div class="card shadow-sm">

<div class="card-header d-flex justify-content-between">

<span>Commission History</span>

<a href="withdraw-request.php"
   class="btn btn-success btn-sm">

Request Withdrawal

</a>

</div>

<div class="card-body p-0">

<table class="table table-hover table-striped mb-0">

<thead>

<tr>

<th>ID</th>
<th>Type</th>
<th>Order</th>
<th>Amount</th>
<th>Status</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php while($row = $commissions->fetch_assoc()): ?>

<tr>

<td>
#<?= $row['id'] ?>
</td>

<td>
<?= ucfirst(str_replace('_',' ',$row['commission_type'])) ?>
</td>

<td>
<?= $row['order_id'] ? '#'.$row['order_id'] : '-' ?>
</td>

<td>
TZS <?= number_format($row['commission_amount'],2) ?>
</td>

<td>

<?php

$badge = match($row['status']) {

'pending' => 'warning',
'approved' => 'primary',
'paid' => 'success',
'rejected' => 'danger',

default => 'secondary'

};

?>

<span class="badge bg-<?= $badge ?>">
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

</div>

</body>
</html>