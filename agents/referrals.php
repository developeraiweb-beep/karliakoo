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
| Referral Summary
|--------------------------------------------------------------------------
*/
$summary = $conn->prepare("
    SELECT
        COUNT(*) total_referrals,

        SUM(
            CASE
            WHEN referral_type='seller'
            THEN 1 ELSE 0
            END
        ) seller_referrals,

        SUM(
            CASE
            WHEN referral_type='customer'
            THEN 1 ELSE 0
            END
        ) customer_referrals,

        COALESCE(SUM(reward_amount),0) total_rewards

    FROM agent_referrals

    WHERE agent_id=?
");

$summary->bind_param("i", $agent_id);
$summary->execute();

$stats = $summary->get_result()->fetch_assoc();

/*
|--------------------------------------------------------------------------
| Agent Referral Code
|--------------------------------------------------------------------------
*/
$referralCode = "AGENT".$agent_id;

/*
|--------------------------------------------------------------------------
| Referral List
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        ar.*,
        u.full_name,
        u.email

    FROM agent_referrals ar

    LEFT JOIN users u
    ON ar.referred_user_id=u.id

    WHERE ar.agent_id=?

    ORDER BY ar.id DESC
");

$stmt->bind_param("i", $agent_id);
$stmt->execute();

$referrals = $stmt->get_result();

?>
<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>My Referrals</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f4f6f9;
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

<h2 class="mb-4">
My Referrals
</h2>

<div class="alert alert-primary">

<strong>Your Referral Code:</strong>

<?= htmlspecialchars($referralCode) ?>

<br>

<strong>Referral Link:</strong>

text
https://karliakoo.com/register.php?ref=<?= urlencode($referralCode) ?>

</div> <div class="row mb-4"> <div class="col-md-3"> <div class="stat-card shadow-sm"> <h4><?= number_format($stats['total_referrals']) ?></h4> <small>Total Referrals</small> </div> </div> <div class="col-md-3"> <div class="stat-card shadow-sm"> <h4><?= number_format($stats['seller_referrals']) ?></h4> <small>Seller Referrals</small> </div> </div> <div class="col-md-3"> <div class="stat-card shadow-sm"> <h4><?= number_format($stats['customer_referrals']) ?></h4> <small>Customer Referrals</small> </div> </div> <div class="col-md-3"> <div class="stat-card shadow-sm"> <h4>TZS <?= number_format($stats['total_rewards'],2) ?></h4> <small>Total Rewards</small> </div> </div> </div> <div class="card shadow-sm"> <div class="card-header"> Referral History </div> <div class="card-body p-0"> <table class="table table-striped mb-0"> <thead> <tr> <th>#</th> <th>Name</th> <th>Email</th> <th>Type</th> <th>Reward</th> <th>Status</th> <th>Date</th> </tr> </thead> <tbody> <?php while($row = $referrals->fetch_assoc()): ?> <tr> <td><?= $row['id'] ?></td> <td> <?= htmlspecialchars($row['full_name'] ?? 'Unknown') ?> </td> <td> <?= htmlspecialchars($row['email'] ?? '-') ?> </td> <td> <?= ucfirst($row['referral_type']) ?> </td> <td> TZS <?= number_format($row['reward_amount'],2) ?> </td> <td> <?php $badge = match($row['status']) { 'completed' => 'success', 'active' => 'primary', 'pending' => 'warning', default => 'secondary' }; ?> <span class="badge bg-<?= $badge ?>"> <?= ucfirst($row['status']) ?> </span> </td> <td> <?= $row['created_at'] ?> </td> </tr> <?php endwhile; ?> </tbody> </table> </div> </div> </div> </body> </html>