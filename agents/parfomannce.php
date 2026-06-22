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
| REFERRALS
|--------------------------------------------------------------------------
*/
$referralsStmt = $conn->prepare("
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

        SUM(
            CASE
            WHEN status='active'
            OR status='completed'
            THEN 1 ELSE 0
            END
        ) active_referrals

    FROM agent_referrals

    WHERE agent_id=?
");

$referralsStmt->bind_param("i", $agent_id);
$referralsStmt->execute();

$referrals = $referralsStmt
    ->get_result()
    ->fetch_assoc();

/*
|--------------------------------------------------------------------------
| ORDERS GENERATED
|--------------------------------------------------------------------------
*/
$ordersStmt = $conn->prepare("
    SELECT

    COUNT(DISTINCT o.id) total_orders,

    COALESCE(SUM(o.total_amount),0) total_sales

    FROM orders o

    INNER JOIN agent_referrals ar
        ON ar.referred_user_id=o.user_id

    WHERE ar.agent_id=?
");

$ordersStmt->bind_param("i", $agent_id);
$ordersStmt->execute();

$orderStats = $ordersStmt
    ->get_result()
    ->fetch_assoc();

/*
|--------------------------------------------------------------------------
| COMMISSIONS
|--------------------------------------------------------------------------
*/
$commissionStmt = $conn->prepare("
    SELECT

    COALESCE(SUM(commission_amount),0)
    total_commissions

    FROM agent_commissions

    WHERE agent_id=?
");

$commissionStmt->bind_param("i", $agent_id);
$commissionStmt->execute();

$commission = $commissionStmt
    ->get_result()
    ->fetch_assoc();

/*
|--------------------------------------------------------------------------
| MONTHLY PERFORMANCE
|--------------------------------------------------------------------------
*/
$monthlyStmt = $conn->prepare("
    SELECT

    COUNT(*) monthly_referrals

    FROM agent_referrals

    WHERE agent_id=?

    AND MONTH(created_at)=MONTH(CURRENT_DATE())

    AND YEAR(created_at)=YEAR(CURRENT_DATE())
");

$monthlyStmt->bind_param("i", $agent_id);
$monthlyStmt->execute();

$monthly = $monthlyStmt
    ->get_result()
    ->fetch_assoc();

/*
|--------------------------------------------------------------------------
| CONVERSION RATE
|--------------------------------------------------------------------------
*/
$totalReferrals =
    (int)$referrals['total_referrals'];

$activeReferrals =
    (int)$referrals['active_referrals'];

$conversionRate = 0;

if ($totalReferrals > 0) {

    $conversionRate =
        ($activeReferrals / $totalReferrals) * 100;
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
      content="width=device-width,initial-scale=1">

<title>
Agent Performance
</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
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
Performance Dashboard
</h2>

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h3>
<?= number_format(
    $referrals['total_referrals']
) ?>
</h3>

<div>Total Referrals</div>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h3>
<?= number_format(
    $referrals['active_referrals']
) ?>
</h3>

<div>Active Referrals</div>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h3>
<?= number_format(
    $referrals['seller_referrals']
) ?>
</h3>

<div>Seller Referrals</div>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h3>
<?= number_format(
    $referrals['customer_referrals']
) ?>
</h3>

<div>Customer Referrals</div>

</div>

</div>

</div>

<div class="row g-3 mb-4">

<div class="col-md-4">

<div class="stat-card shadow-sm">

<h3>
<?= number_format(
    $orderStats['total_orders']
) ?>
</h3>

<div>Orders Generated</div>

</div>

</div>

<div class="col-md-4">

<div class="stat-card shadow-sm">

<h3>
TZS
<?= number_format(
$orderStats['total_sales'],2
) ?>
</h3>

<div>Total Sales Generated</div>

</div>

</div>

<div class="col-md-4">

<div class="stat-card shadow-sm">

<h3>
TZS
<?= number_format(
$commission['total_commissions'],2
) ?>
</h3>

<div>Total Commissions</div>

</div>

</div>

</div>

<div class="row g-3">

<div class="col-md-6">

<div class="stat-card shadow-sm">

<h3>
<?= number_format(
$monthly['monthly_referrals']
) ?>
</h3>

<div>This Month Referrals</div>

</div>

</div>

<div class="col-md-6">

<div class="stat-card shadow-sm">

<h3>
<?= number_format(
$conversionRate,
2
) ?>%
</h3>

<div>Referral Conversion Rate</div>

</div>

</div>

</div>

<div class="card shadow-sm mt-4">

<div class="card-header">
Performance Summary
</div>

<div class="card-body">

<ul class="list-group">

<li class="list-group-item">
Total Referrals:
<strong>
<?= number_format(
$referrals['total_referrals']
) ?>
</strong>
</li>

<li class="list-group-item">
Orders Generated:
<strong>
<?= number_format(
$orderStats['total_orders']
) ?>
</strong>
</li>

<li class="list-group-item">
Sales Generated:
<strong>
TZS
<?= number_format(
$orderStats['total_sales'],2
) ?>
</strong>
</li>

<li class="list-group-item">
Commission Earned:
<strong>
TZS
<?= number_format(
$commission['total_commissions'],2
) ?>
</strong>
</li>

<li class="list-group-item">
Conversion Rate:
<strong>
<?= number_format(
$conversionRate,
2
) ?>%
</strong>
</li>

</ul>

</div>

</div>

</div>

</body>
</html>