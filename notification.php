<?php

require_once "config/db.php";
require_once "includes/auth.php";

requireLogin();

$user_id = (int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Mark One As Read
|--------------------------------------------------------------------------
*/
if (
    isset($_GET['read']) &&
    is_numeric($_GET['read'])
) {

    $notification_id =
    (int)$_GET['read'];

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id = ?
        AND user_id = ?
    ");

    $stmt->bind_param(
        "ii",
        $notification_id,
        $user_id
    );

    $stmt->execute();

    header("Location: notification.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Mark All As Read
|--------------------------------------------------------------------------
*/
if(isset($_GET['mark_all'])){

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read=1
        WHERE user_id=?
    ");

    $stmt->bind_param(
        "i",
        $user_id
    );

    $stmt->execute();

    header("Location: notification.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Notifications
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT *
FROM notifications
WHERE user_id=?
ORDER BY id DESC
");

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$notifications =
$stmt->get_result();

/*
|--------------------------------------------------------------------------
| Count Unread
|--------------------------------------------------------------------------
*/
$countStmt = $conn->prepare("
SELECT COUNT(*) total
FROM notifications
WHERE user_id=?
AND is_read=0
");

$countStmt->bind_param(
    "i",
    $user_id
);

$countStmt->execute();

$unread =
$countStmt
->get_result()
->fetch_assoc()['total'];

function notificationColor($type)
{
    return match($type){

        'order' => 'primary',

        'payment' => 'success',

        'delivery' => 'info',

        'message' => 'warning',

        'product' => 'secondary',

        'shop' => 'dark',

        'promotion' => 'danger',

        default => 'light'
    };
}
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
Notifications | Karliakoo
</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
rel="stylesheet">

<style>

body{
background:#f5f5f5;
}

.notification-card{
background:white;
border-radius:12px;
padding:20px;
margin-bottom:15px;
transition:.3s;
}

.unread{
border-left:5px solid #0d6efd;
background:#eef5ff;
}

.time{
font-size:13px;
color:#777;
}

</style>

</head>

<body>

<div class="container py-5">

<div
class="d-flex justify-content-between align-items-center mb-4">

<h2>

<i class="fa fa-bell"></i>

Notifications

<?php if($unread > 0): ?>

<span
class="badge bg-danger">

<?= $unread ?>

</span>

<?php endif; ?>

</h2>

<a
href="?mark_all=1"
class="btn btn-primary">

Mark All Read

</a>

</div>

<?php if(
$notifications->num_rows > 0
): ?>

<?php while(
$row =
$notifications->fetch_assoc()
): ?>

<div
class="notification-card
<?= !$row['is_read']
? 'unread'
: '' ?>">

<div
class="d-flex justify-content-between">

<div>

<span
class="badge bg-<?=
notificationColor(
$row['type']
)
?>">

<?= ucfirst(
$row['type']
) ?>

</span>

<h5 class="mt-2">

<?= htmlspecialchars(
$row['title']
) ?>

</h5>

<p>

<?= nl2br(
htmlspecialchars(
$row['message']
))
?>

</p>

<div class="time">

<?= date(
"d M Y H:i",
strtotime(
$row['created_at']
)
) ?>

</div>

</div>

<div>

<?php if(
!$row['is_read']
): ?>

<a
href="?read=<?=
$row['id']
?>"
class="btn btn-sm btn-outline-primary">

Read

</a>

<?php endif; ?>

</div>

</div>

</div>

<?php endwhile; ?>

<?php else: ?>

<div class="alert alert-info">

No notifications found.

</div>

<?php endif; ?>

</div>

</body>
</html>