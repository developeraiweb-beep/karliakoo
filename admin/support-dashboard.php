<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

/*
|--------------------------------------------------------------------------
| OVERVIEW STATS
|--------------------------------------------------------------------------
*/
$stats = $conn->query("
    SELECT

    COUNT(*) total_tickets,

    SUM(status='open') open_tickets,

    SUM(status='in_progress') in_progress_tickets,

    SUM(status='resolved') resolved_tickets,

    SUM(status='closed') closed_tickets,

    SUM(priority='urgent') urgent_tickets,

    SUM(DATE(created_at)=CURDATE()) today_tickets

    FROM support_tickets
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| AVG RESPONSE TIME
|--------------------------------------------------------------------------
|
| Measures minutes between ticket creation
| and first admin response.
|
*/
$responseQuery = $conn->query("
SELECT

AVG(
TIMESTAMPDIFF(
MINUTE,
t.created_at,
m.first_reply
)
) avg_response_minutes

FROM support_tickets t

INNER JOIN (

    SELECT

    ticket_id,

    MIN(created_at) first_reply

    FROM support_messages

    WHERE sender_role='admin'

    GROUP BY ticket_id

) m

ON t.id = m.ticket_id
");

$responseData = $responseQuery->fetch_assoc();

$avgResponse =
    round(
        $responseData['avg_response_minutes'] ?? 0
    );

/*
|--------------------------------------------------------------------------
| RECENT TICKETS
|--------------------------------------------------------------------------
*/
$recent = $conn->query("
SELECT

t.*,
u.full_name

FROM support_tickets t

LEFT JOIN users u
ON u.id=t.user_id

ORDER BY t.id DESC

LIMIT 10
");

/*
|--------------------------------------------------------------------------
| TOP SUPPORT ADMINS
|--------------------------------------------------------------------------
*/
$topAdmins = $conn->query("
SELECT

u.full_name,

COUNT(sm.id) replies

FROM support_messages sm

INNER JOIN users u
ON u.id = sm.sender_id

WHERE sm.sender_role='admin'

GROUP BY sm.sender_id

ORDER BY replies DESC

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
Support Dashboard
</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
    background:#f4f6f9;
}

.card-stat{
    background:#fff;
    border-radius:12px;
    padding:20px;
    height:100%;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">
Support Dashboard
</h2>

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="card-stat shadow-sm">

<h3>
<?= number_format($stats['total_tickets']) ?>
</h3>

<div>Total Tickets</div>

</div>

</div>

<div class="col-md-3">

<div class="card-stat shadow-sm">

<h3 class="text-primary">
<?= number_format($stats['open_tickets']) ?>
</h3>

<div>Open Tickets</div>

</div>

</div>

<div class="col-md-3">

<div class="card-stat shadow-sm">

<h3 class="text-warning">
<?= number_format($stats['in_progress_tickets']) ?>
</h3>

<div>In Progress</div>

</div>

</div>

<div class="col-md-3">

<div class="card-stat shadow-sm">

<h3 class="text-success">
<?= number_format($stats['resolved_tickets']) ?>
</h3>

<div>Resolved</div>

</div>

</div>

</div>

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="card-stat shadow-sm">

<h3 class="text-danger">
<?= number_format($stats['urgent_tickets']) ?>
</h3>

<div>Urgent Tickets</div>

</div>

</div>

<div class="col-md-3">

<div class="card-stat shadow-sm">

<h3>
<?= number_format($stats['today_tickets']) ?>
</h3>

<div>Today's Tickets</div>

</div>

</div>

<div class="col-md-3">

<div class="card-stat shadow-sm">

<h3>
<?= number_format($avgResponse) ?>
 min
</h3>

<div>Avg Response Time</div>

</div>

</div>

<div class="col-md-3">

<div class="card-stat shadow-sm">

<h3>
<?= number_format(
$stats['closed_tickets']
) ?>
</h3>

<div>Closed Tickets</div>

</div>

</div>

</div>

<div class="row">

<div class="col-lg-8">

<div class="card shadow-sm">

<div class="card-header">
Recent Tickets
</div>

<div class="table-responsive">

<table class="table table-hover mb-0">

<thead>

<tr>

<th>Ticket</th>
<th>User</th>
<th>Priority</th>
<th>Status</th>
<th>Date</th>
<th></th>

</tr>

</thead>

<tbody>

<?php while($ticket = $recent->fetch_assoc()): ?>

<tr>

<td>

<?= htmlspecialchars(
$ticket['ticket_number']
) ?>

</td>

<td>

<?= htmlspecialchars(
$ticket['full_name']
)
?>

</td>

<td>

<?php

$priorityColor = match(
$ticket['priority']
){

'low'=>'secondary',
'medium'=>'primary',
'high'=>'warning',
'urgent'=>'danger',

default=>'secondary'

};

?>

<span class="badge bg-<?= $priorityColor ?>">

<?= ucfirst(
$ticket['priority']
) ?>

</span>

</td>

<td>

<?php

$statusColor = match(
$ticket['status']
){

'open'=>'primary',
'in_progress'=>'warning',
'resolved'=>'success',
'closed'=>'secondary',

default=>'dark'

};

?>

<span class="badge bg-<?= $statusColor ?>">

<?= ucfirst(
str_replace(
'_',
' ',
$ticket['status']
)
) ?>

</span>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$ticket['created_at']
)
) ?>

</td>

<td>

<a
href="ticket-details.php?id=<?= $ticket['id'] ?>"
class="btn btn-sm btn-primary">

Open

</a>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

<div class="col-lg-4">

<div class="card shadow-sm">

<div class="card-header">
Top Support Admins
</div>

<div class="card-body">

<table class="table">

<thead>

<tr>

<th>Admin</th>
<th>Replies</th>

</tr>

</thead>

<tbody>

<?php while($admin = $topAdmins->fetch_assoc()): ?>

<tr>

<td>

<?= htmlspecialchars(
$admin['full_name']
) ?>

</td>

<td>

<?= number_format(
$admin['replies']
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

</div>

</body>
</html>