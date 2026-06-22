<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

if (!$user || $user['role'] !== 'agent') {
    die("Access denied");
}

$agent_id = (int)$user['id'];

/*
|--------------------------------------------------------------------------
| Seller Statistics
|--------------------------------------------------------------------------
*/
$statsQuery = $conn->prepare("
    SELECT
        COUNT(*) total_sellers
    FROM agent_referrals
    WHERE agent_id = ?
    AND referral_type = 'seller'
");

$statsQuery->bind_param("i", $agent_id);
$statsQuery->execute();

$stats = $statsQuery->get_result()->fetch_assoc();

/*
|--------------------------------------------------------------------------
| Fetch Sellers
|--------------------------------------------------------------------------
*/
$query = $conn->prepare("
    SELECT

        ar.id AS referral_id,
        ar.status AS referral_status,
        ar.reward_amount,

        u.id,
        u.full_name,
        u.email,
        u.phone,
        u.created_at,

        s.shop_name,
        s.shop_slug,
        s.status AS shop_status,
        s.verified,
        s.followers,
        s.views

    FROM agent_referrals ar

    INNER JOIN users u
        ON ar.referred_user_id = u.id

    LEFT JOIN shops s
        ON s.seller_id = u.id

    WHERE ar.agent_id = ?
    AND ar.referral_type = 'seller'

    ORDER BY ar.id DESC
");

$query->bind_param("i", $agent_id);
$query->execute();

$sellers = $query->get_result();

?>
<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>My Sellers | Karliakoo</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f5f7fa;
}

.card-stat{
    background:#fff;
    border-radius:12px;
    padding:20px;
}

</style>

</head>
<body>

<div class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<h2>My Sellers</h2>

<a href="dashboard.php" class="btn btn-outline-primary">
Dashboard
</a>

</div>

<div class="row mb-4">

<div class="col-md-3">

<div class="card-stat shadow-sm">

<h3>
<?= number_format($stats['total_sellers']) ?>
</h3>

<div>Total Referred Sellers</div>

</div>

</div>

</div>

<div class="card shadow-sm">

<div class="card-header">
Referred Sellers
</div>

<div class="card-body p-0">

<table class="table table-hover table-striped mb-0">

<thead>

<tr>

<th>#</th>
<th>Seller</th>
<th>Shop</th>
<th>Shop Status</th>
<th>Verified</th>
<th>Followers</th>
<th>Views</th>
<th>Reward</th>
<th>Joined</th>

</tr>

</thead>

<tbody>

<?php while($seller = $sellers->fetch_assoc()): ?>

<tr>

<td>
<?= $seller['id'] ?>
</td>

<td>

<strong>
<?= htmlspecialchars($seller['full_name']) ?>
</strong>

<br>

<small>
<?= htmlspecialchars($seller['email']) ?>
</small>

<?php if(!empty($seller['phone'])): ?>
<br>
<small>
<?= htmlspecialchars($seller['phone']) ?>
</small>
<?php endif; ?>

</td>

<td>

<?php if($seller['shop_name']): ?>

<strong>
<?= htmlspecialchars($seller['shop_name']) ?>
</strong>

<br>

<small>
<?= htmlspecialchars($seller['shop_slug']) ?>
</small>

<?php else: ?>

<span class="text-muted">
No shop created
</span>

<?php endif; ?>

</td>

<td>

<?php

$statusClass = match($seller['shop_status']) {
    'approved' => 'success',
    'pending' => 'warning',
    'rejected' => 'danger',
    default => 'secondary'
};

?>

<span class="badge bg-<?= $statusClass ?>">
<?= ucfirst($seller['shop_status'] ?? 'N/A') ?>
</span>

</td>

<td>

<?php if($seller['verified']): ?>

<span class="badge bg-primary">
Verified
</span>

<?php else: ?>

<span class="badge bg-secondary">
No
</span>

<?php endif; ?>

</td>

<td>
<?= number_format($seller['followers'] ?? 0) ?>
</td>

<td>
<?= number_format($seller['views'] ?? 0) ?>
</td>

<td>
TZS <?= number_format($seller['reward_amount'],2) ?>
</td>

<td>
<?= date('d M Y', strtotime($seller['created_at'])) ?>
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