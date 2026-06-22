<?php

require_once "../config/db.php";
require_once "../includes/auth.php";



/*
|--------------------------------------------------------------------------
| MARK SINGLE AS READ
|--------------------------------------------------------------------------
*/
if(isset($_GET['read']))
{
    $id = (int)$_GET['read'];

    $stmt = $conn->prepare("
    UPDATE b2b_notifications
    SET is_read=1
    WHERE id=?
    AND user_id=?
    ");

    $stmt->bind_param(
        "ii",
        $id,
        $userId
    );

    $stmt->execute();

    header("Location: notifications.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| MARK ALL READ
|--------------------------------------------------------------------------
*/
if(isset($_POST['mark_all']))
{
    $stmt = $conn->prepare("
    UPDATE b2b_notifications
    SET is_read=1
    WHERE user_id=?
    ");

    $stmt->bind_param(
        "i",
        $userId
    );

    $stmt->execute();

    $success =
    "All notifications marked as read.";
}

/*
|--------------------------------------------------------------------------
| NOTIFICATION COUNTS
|--------------------------------------------------------------------------
*/
$stats = $conn->prepare("
SELECT

COUNT(*) total,

SUM(
CASE WHEN is_read=0
THEN 1 ELSE 0 END
) unread

FROM b2b_notifications

WHERE user_id=?
");

$stats->bind_param(
    "i",
    $userId
);

$stats->execute();

$stats =
$stats
->get_result()
->fetch_assoc();

/*
|--------------------------------------------------------------------------
| LOAD NOTIFICATIONS
|--------------------------------------------------------------------------
*/
$notifications =
$conn->prepare("
SELECT *
FROM b2b_notifications
WHERE user_id=?
ORDER BY id DESC
LIMIT 100
");

$notifications->bind_param(
    "i",
    $userId
);

$notifications->execute();

$notifications =
$notifications
->get_result();

function notificationBadge($type)
{
    switch($type)
    {
        case 'message':
            return 'primary';

        case 'rfq':
            return 'info';

        case 'quote':
            return 'warning';

        case 'contract':
            return 'success';

        case 'order':
            return 'secondary';

        case 'payment':
            return 'dark';

        case 'compliance':
            return 'danger';

        case 'connection':
            return 'primary';

        default:
            return 'secondary';
    }
}
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>Notifications</title>

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
background:#f5f7fb;
}

.card-box{
background:#fff;
padding:20px;
border-radius:15px;
margin-bottom:20px;
box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.metric{
font-size:30px;
font-weight:bold;
}

.notification{
padding:15px;
border-bottom:1px solid #eee;
}

.unread{
background:#f8f9ff;
border-left:4px solid #0d6efd;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<h2>

Notification Center

</h2>

<form method="POST">

<button
name="mark_all"
class="btn btn-primary">

Mark All Read

</button>

</form>

</div>

<?php if($success): ?>

<div class="alert alert-success">

<?= $success ?>

</div>

<?php endif; ?>

<!-- STATS -->

<div class="row mb-4">

<div class="col-md-3">

<div class="card-box text-center">

<div class="metric">

<?= number_format(
$stats['total']
) ?>

</div>

Total Notifications

</div>

</div>

<div class="col-md-3">

<div class="card-box text-center">

<div class="metric text-danger">

<?= number_format(
$stats['unread']
) ?>

</div>

Unread

</div>

</div>

</div>

<!-- NOTIFICATIONS -->

<div class="card-box">

<h5>

Recent Notifications

</h5>

<?php if(
$notifications->num_rows == 0
): ?>

<div class="alert alert-info">

No notifications available.

</div>

<?php else: ?>

<?php while(
$row =
$notifications->fetch_assoc()
): ?>

<div class="notification <?=

$row['is_read']
?
''
:
'unread'

?>">

<div class="d-flex justify-content-between">

<div>

<span
class="badge bg-<?=
notificationBadge(
$row['notification_type']
)
?>">

<?= ucfirst(
$row['notification_type']
) ?>

</span>

<h6 class="mt-2">

<?= htmlspecialchars(
$row['title']
) ?>

</h6>

<p class="mb-1">

<?= htmlspecialchars(
$row['message']
) ?>

</p>

<small class="text-muted">

<?= date(
'd M Y H:i',
strtotime(
$row['created_at']
)
) ?>

</small>

</div>

<div>

<?php if(
!$row['is_read']
): ?>

<a
href="?read=<?= $row['id'] ?>"
class="btn btn-sm btn-outline-primary">

Mark Read

</a>

<?php endif; ?>

</div>

</div>

</div>

<?php endwhile; ?>

<?php endif; ?>

</div>

</div>

</body>
</html>