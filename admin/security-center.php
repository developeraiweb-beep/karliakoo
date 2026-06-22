<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

/*
|--------------------------------------------------------------------------
| SECURITY STATS
|--------------------------------------------------------------------------
*/
$failedLogins = 0;
$successfulLogins = 0;

if ($conn->query("SHOW TABLES LIKE 'login_attempts'")->num_rows) {

    $loginStats = $conn->query("
        SELECT

        SUM(success=0) failed_logins,

        SUM(success=1) successful_logins

        FROM login_attempts

        WHERE DATE(created_at)=CURDATE()
    ")->fetch_assoc();

    $failedLogins =
        $loginStats['failed_logins'] ?? 0;

    $successfulLogins =
        $loginStats['successful_logins'] ?? 0;
}

/*
|--------------------------------------------------------------------------
| SUSPENDED USERS
|--------------------------------------------------------------------------
*/
$suspendedUsers = 0;

$userColumns = $conn->query("
SHOW COLUMNS FROM users LIKE 'status'
");

if ($userColumns->num_rows) {

    $suspendedUsers = $conn->query("
        SELECT COUNT(*) total
        FROM users
        WHERE status='suspended'
    ")->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| SECURITY ALERTS
|--------------------------------------------------------------------------
*/
$alerts = [];

if ($conn->query("
SHOW TABLES LIKE 'security_alerts'
")->num_rows) {

    $alerts = $conn->query("
        SELECT *
        FROM security_alerts
        ORDER BY id DESC
        LIMIT 20
    ");
}

/*
|--------------------------------------------------------------------------
| RECENT AUDIT EVENTS
|--------------------------------------------------------------------------
*/
$auditLogs = [];

if ($conn->query("
SHOW TABLES LIKE 'b2b_audit_logs'
")->num_rows) {

    $auditLogs = $conn->query("
        SELECT *

        FROM b2b_audit_logs

        ORDER BY id DESC

        LIMIT 30
    ");
}

/*
|--------------------------------------------------------------------------
| SUSPENDED ACCOUNTS
|--------------------------------------------------------------------------
*/
$suspendedAccounts = [];

if ($userColumns->num_rows) {

    $suspendedAccounts = $conn->query("
        SELECT

        id,
        full_name,
        email,
        role,
        created_at

        FROM users

        WHERE status='suspended'

        ORDER BY id DESC

        LIMIT 50
    ");
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>

Security Center

</title>

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
border-radius:12px;
}

.metric{
font-size:28px;
font-weight:700;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Security Center

</h2>

<!-- KPI -->

<div class="row g-3 mb-4">

<div class="col-md-4">

<div class="card-box shadow-sm">

<div class="metric">

<?= number_format(
$failedLogins
) ?>

</div>

Failed Logins Today

</div>

</div>

<div class="col-md-4">

<div class="card-box shadow-sm">

<div class="metric">

<?= number_format(
$successfulLogins
) ?>

</div>

Successful Logins Today

</div>

</div>

<div class="col-md-4">

<div class="card-box shadow-sm">

<div class="metric">

<?= number_format(
$suspendedUsers
) ?>

</div>

Suspended Accounts

</div>

</div>

</div>

<!-- SECURITY ALERTS -->

<div class="card-box shadow-sm mb-4">

<h4>

Security Alerts

</h4>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>ID</th>
<th>Severity</th>
<th>Title</th>
<th>Status</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php

if ($alerts) :

while($alert = $alerts->fetch_assoc()):

?>

<tr>

<td>
<?= $alert['id'] ?>
</td>

<td>

<?php

$badge = match($alert['severity']) {

'critical' => 'danger',
'high' => 'warning',
'medium' => 'info',
default => 'secondary'

};

?>

<span class="badge bg-<?= $badge ?>">

<?= ucfirst(
$alert['severity']
) ?>

</span>

</td>

<td>

<?= htmlspecialchars(
$alert['title']
) ?>

</td>

<td>

<?= ucfirst(
$alert['status']
) ?>

</td>

<td>

<?= date(
'd M Y H:i',
strtotime(
$alert['created_at']
)
) ?>

</td>

</tr>

<?php

endwhile;
endif;

?>

</tbody>

</table>

</div>

</div>

<!-- SUSPENDED USERS -->

<div class="card-box shadow-sm mb-4">

<h4>

Suspended Accounts

</h4>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>ID</th>
<th>Name</th>
<th>Email</th>
<th>Role</th>
<th>Created</th>

</tr>

</thead>

<tbody>

<?php

if ($suspendedAccounts):

while(
$user = $suspendedAccounts->fetch_assoc()
):

?>

<tr>

<td>
<?= $user['id'] ?>
</td>

<td>
<?= htmlspecialchars(
$user['full_name']
) ?>
</td>

<td>
<?= htmlspecialchars(
$user['email']
) ?>
</td>

<td>
<?= htmlspecialchars(
$user['role']
) ?>
</td>

<td>

<?= date(
'd M Y',
strtotime(
$user['created_at']
)
) ?>

</td>

</tr>

<?php

endwhile;
endif;

?>

</tbody>

</table>

</div>

</div>

<!-- AUDIT LOGS -->

<div class="card-box shadow-sm">

<h4>

Recent Security Activity

</h4>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>ID</th>
<th>Action</th>
<th>User</th>
<th>IP</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php

if ($auditLogs):

while(
$log = $auditLogs->fetch_assoc()
):

?>

<tr>

<td>

<?= $log['id'] ?>

</td>

<td>

<?= htmlspecialchars(
$log['action']
)
?>

</td>

<td>

<?= htmlspecialchars(
$log['user_id']
?? 'System'
)
?>

</td>

<td>

<?= htmlspecialchars(
$log['ip_address']
?? '-'
)
?>

</td>

<td>

<?= date(
'd M Y H:i',
strtotime(
$log['created_at']
)
) ?>

</td>

</tr>

<?php

endwhile;
endif;

?>

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>