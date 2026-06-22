<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

if (!$user || !in_array($user['role'], ['agent', 'admin'])) {
    die("Access denied.");
}

/*
|--------------------------------------------------------------------------
| LEADERBOARD
|--------------------------------------------------------------------------
*/
$sql = "
SELECT

u.id,
u.full_name,
u.profile_photo,

COUNT(DISTINCT ar.id) AS total_referrals,

COUNT(DISTINCT o.id) AS total_orders,

COALESCE(SUM(DISTINCT o.total_amount),0) AS total_sales,

COALESCE(SUM(ac.commission_amount),0) AS total_commissions

FROM users u

LEFT JOIN agent_referrals ar
ON ar.agent_id = u.id

LEFT JOIN orders o
ON o.user_id = ar.referred_user_id

LEFT JOIN agent_commissions ac
ON ac.agent_id = u.id

WHERE u.role='agent'

GROUP BY u.id

ORDER BY total_commissions DESC,
         total_sales DESC,
         total_referrals DESC

LIMIT 100
";

$result = $conn->query($sql);

/*
|--------------------------------------------------------------------------
| CURRENT USER RANK
|--------------------------------------------------------------------------
*/
$rank = 0;
$myRank = null;

$leaderboardData = [];

while ($row = $result->fetch_assoc()) {

    $rank++;

    $row['rank'] = $rank;

    if (
        isset($user['id']) &&
        $row['id'] == $user['id']
    ) {
        $myRank = $rank;
    }

    $leaderboardData[] = $row;
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>
Agent Leaderboard
</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f5f5;
}

.rank-1{
    background:#fff8dc;
}

.rank-2{
    background:#f2f2f2;
}

.rank-3{
    background:#f7e7ce;
}

.avatar{
    width:40px;
    height:40px;
    border-radius:50%;
    object-fit:cover;
}

</style>

</head>

<body>

<div class="container py-4">

<h2 class="mb-4">
🏆 Agent Leaderboard
</h2>

<?php if($myRank): ?>

<div class="alert alert-primary">

Your Current Rank:
<strong>
#<?= $myRank ?>
</strong>

</div>

<?php endif; ?>

<div class="card shadow-sm">

<div class="card-header">

Top Performing Agents

</div>

<div class="card-body p-0">

<table class="table table-hover table-striped mb-0">

<thead>

<tr>

<th>Rank</th>
<th>Agent</th>
<th>Referrals</th>
<th>Orders</th>
<th>Sales</th>
<th>Commissions</th>

</tr>

</thead>

<tbody>

<?php foreach($leaderboardData as $agent): ?>

<tr class="<?=
($agent['rank']==1 ? 'rank-1' :
($agent['rank']==2 ? 'rank-2' :
($agent['rank']==3 ? 'rank-3' : '')))
?>">

<td>

<?php if($agent['rank']==1): ?>
🥇
<?php elseif($agent['rank']==2): ?>
🥈
<?php elseif($agent['rank']==3): ?>
🥉
<?php else: ?>
#<?= $agent['rank'] ?>
<?php endif; ?>

</td>

<td>

<div class="d-flex align-items-center">

<?php if(!empty($agent['profile_photo'])): ?>

<img
src="../uploads/profiles/<?= htmlspecialchars($agent['profile_photo']) ?>"
class="avatar me-2">

<?php else: ?>

<img
src="https://via.placeholder.com/40"
class="avatar me-2">

<?php endif; ?>

<div>

<strong>
<?= htmlspecialchars($agent['full_name']) ?>
</strong>

</div>

</div>

</td>

<td>
<?= number_format($agent['total_referrals']) ?>
</td>

<td>
<?= number_format($agent['total_orders']) ?>
</td>

<td>
TZS <?= number_format($agent['total_sales'],2) ?>
</td>

<td>
TZS <?= number_format($agent['total_commissions'],2) ?>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>