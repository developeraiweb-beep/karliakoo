<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$status = trim($_GET['status'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];
$types = '';

if (!empty($status)) {
    $where[] = "t.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($priority)) {
    $where[] = "t.priority = ?";
    $params[] = $priority;
    $types .= "s";
}

if (!empty($search)) {
    $where[] = "(
        t.ticket_number LIKE ?
        OR t.subject LIKE ?
        OR u.full_name LIKE ?
        OR u.email LIKE ?
    )";

    $like = "%{$search}%";

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

    $types .= "ssss";
}

$sqlWhere = "";

if (!empty($where)) {
    $sqlWhere = "WHERE " . implode(" AND ", $where);
}

/*
|--------------------------------------------------------------------------
| Ticket Statistics
|--------------------------------------------------------------------------
*/
$stats = $conn->query("
    SELECT

    COUNT(*) total,

    SUM(status='open') open_count,

    SUM(status='in_progress') progress_count,

    SUM(status='resolved') resolved_count,

    SUM(status='closed') closed_count

    FROM support_tickets
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| Tickets
|--------------------------------------------------------------------------
*/
$sql = "
SELECT

t.*,

u.full_name,
u.email,

admin.full_name assigned_admin_name

FROM support_tickets t

LEFT JOIN users u
ON u.id = t.user_id

LEFT JOIN users admin
ON admin.id = t.assigned_admin

$sqlWhere

ORDER BY

FIELD(
t.status,
'open',
'in_progress',
'resolved',
'closed'
),

t.updated_at DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$tickets = $stmt->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
      content="width=device-width,initial-scale=1">

<title>
Support Tickets
</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">

<style>

body{
    background:#f5f6fa;
}

.stat-card{
    background:white;
    border-radius:12px;
    padding:20px;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">
Support Ticket Management
</h2>

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h3>
<?= number_format($stats['total']) ?>
</h3>

<div>Total Tickets</div>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h3 class="text-primary">
<?= number_format($stats['open_count']) ?>
</h3>

<div>Open</div>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h3 class="text-warning">
<?= number_format($stats['progress_count']) ?>
</h3>

<div>In Progress</div>

</div>

</div>

<div class="col-md-3">

<div class="stat-card shadow-sm">

<h3 class="text-success">
<?= number_format($stats['resolved_count']) ?>
</h3>

<div>Resolved</div>

</div>

</div>

</div>

<div class="card shadow-sm mb-4">

<div class="card-body">

<form method="GET">

<div class="row">

<div class="col-md-4">

<input
type="text"
name="search"
value="<?= htmlspecialchars($search) ?>"
class="form-control"
placeholder="Search ticket">

</div>

<div class="col-md-3">

<select
name="status"
class="form-select">

<option value="">
All Status
</option>

<option value="open"
<?= $status=='open'?'selected':'' ?>>
Open
</option>

<option value="in_progress"
<?= $status=='in_progress'?'selected':'' ?>>
In Progress
</option>

<option value="resolved"
<?= $status=='resolved'?'selected':'' ?>>
Resolved
</option>

<option value="closed"
<?= $status=='closed'?'selected':'' ?>>
Closed
</option>

</select>

</div>

<div class="col-md-3">

<select
name="priority"
class="form-select">

<option value="">
All Priorities
</option>

<option value="low">
Low
</option>

<option value="medium">
Medium
</option>

<option value="high">
High
</option>

<option value="urgent">
Urgent
</option>

</select>

</div>

<div class="col-md-2">

<button
class="btn btn-primary w-100">

Filter

</button>

</div>

</div>

</form>

</div>

</div>

<div class="card shadow-sm">

<div class="card-header">
Support Tickets
</div>

<div class="table-responsive">

<table class="table table-hover mb-0">

<thead>

<tr>

<th>Ticket</th>
<th>User</th>
<th>Role</th>
<th>Subject</th>
<th>Priority</th>
<th>Status</th>
<th>Assigned</th>
<th>Updated</th>
<th></th>

</tr>

</thead>

<tbody>

<?php while($ticket = $tickets->fetch_assoc()): ?>

<tr>

<td>

<strong>
<?= htmlspecialchars($ticket['ticket_number']) ?>
</strong>

</td>

<td>

<?= htmlspecialchars($ticket['full_name']) ?>

<br>

<small>
<?= htmlspecialchars($ticket['email']) ?>
</small>

</td>

<td>

<?= ucfirst($ticket['user_role']) ?>

</td>

<td>

<?= htmlspecialchars($ticket['subject']) ?>

</td>

<td>

<?php

$priorityColor = match($ticket['priority']) {

'low' => 'secondary',
'medium' => 'primary',
'high' => 'warning',
'urgent' => 'danger',

default => 'secondary'
};

?>

<span class="badge bg-<?= $priorityColor ?>">

<?= ucfirst($ticket['priority']) ?>

</span>

</td>

<td>

<?php

$statusColor = match($ticket['status']) {

'open' => 'primary',
'in_progress' => 'warning',
'resolved' => 'success',
'closed' => 'secondary',

default => 'dark'
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

<?= htmlspecialchars(
$ticket['assigned_admin_name']
?? 'Unassigned'
) ?>

</td>

<td>

<?= date(
'd M Y H:i',
strtotime(
$ticket['updated_at']
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

</body>
</html>