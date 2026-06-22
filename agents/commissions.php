<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

if (!$user || $user['role'] !== 'agent') {
    die("Access denied");
}

$agent_id = $user['id'];

/*
|--------------------------------------------------------------------------
| Commission Summary
|--------------------------------------------------------------------------
*/
$summary = $conn->prepare("
    SELECT
        COALESCE(SUM(commission_amount),0) total,
        COALESCE(SUM(
            CASE WHEN status='pending'
            THEN commission_amount ELSE 0 END
        ),0) pending_total,
        COALESCE(SUM(
            CASE WHEN status='paid'
            THEN commission_amount ELSE 0 END
        ),0) paid_total
    FROM agent_commissions
    WHERE agent_id=?
");

$summary->bind_param("i", $agent_id);
$summary->execute();

$stats = $summary->get_result()->fetch_assoc();

/*
|--------------------------------------------------------------------------
| Commission History
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT *
    FROM agent_commissions
    WHERE agent_id=?
    ORDER BY id DESC
");

$stmt->bind_param("i", $agent_id);
$stmt->execute();

$commissions = $stmt->get_result();

?>
<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>Agent Commissions</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f5f5f5;
}

.stat-card{
    background:white;
    border-radius:12px;
    padding:20px;
}

</style>

</head>
<body>

<div class="container py-4">

<h2 class="mb-4">Commission Dashboard</h2>

<div class="row mb-4">

<div class="col-md-4">
<div class="stat-card shadow-sm">
<h5>Total Earned</h5>
<h3>TZS <?= number_format($stats['total'],2) ?></h3>
</div>
</div>

<div class="col-md-4">
<div class="stat-card shadow-sm">
<h5>Pending</h5>
<h3 class="text-warning">
TZS <?= number_format($stats['pending_total'],2) ?>
</h3>
</div>
</div>

<div class="col-md-4">
<div class="stat-card shadow-sm">
<h5>Paid</h5>
<h3 class="text-success">
TZS <?= number_format($stats['paid_total'],2) ?>
</h3>
</div>
</div>

</div>

<div class="card shadow-sm">

<div class="card-header">
Commission History
</div>

<div class="card-body p-0">

<table class="table table-striped mb-0">

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

<td>#<?= $row['id'] ?></td>

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
    'paid' => 'success',
    'approved' => 'primary',
    'pending' => 'warning',
    'rejected' => 'danger',
    default => 'secondary'
};

?>

<span class="badge bg-<?= $badge ?>">
<?= ucfirst($row['status']) ?>
</span>

</td>

<td><?= $row['created_at'] ?></td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>