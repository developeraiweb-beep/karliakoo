<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user_id = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| MARK AS READ (optional action)
|--------------------------------------------------------------------------
*/
if (isset($_GET['read'])) {

    $notif_id = (int) $_GET['read'];

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id = ?
        AND user_id = ?
    ");

    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();

    header("Location: notification.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| MARK ALL AS READ
|--------------------------------------------------------------------------
*/
if (isset($_GET['read_all'])) {

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = ?
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    header("Location: notification.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| FETCH NOTIFICATIONS
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT *
    FROM notifications
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 50
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$notifications = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| COUNT UNREAD
|--------------------------------------------------------------------------
*/
$countStmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM notifications
    WHERE user_id = ?
    AND is_read = 0
");

$countStmt->bind_param("i", $user_id);
$countStmt->execute();

$unread = $countStmt->get_result()->fetch_assoc()['total'];

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Notifications | Karliakoo</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f5f5f5;
}

.notif-card{
    background:white;
    border-radius:12px;
    padding:15px;
    margin-bottom:10px;
}

.unread{
    border-left:4px solid #0d6efd;
}

.small{
    font-size:13px;
    color:#777;
}

</style>

</head>

<body>

<div class="container py-4">

<h3 class="mb-3">
    Notifications
    <?php if($unread > 0): ?>
        <span class="badge bg-danger"><?= $unread ?></span>
    <?php endif; ?>
</h3>

<a href="?read_all=1" class="btn btn-sm btn-outline-primary mb-3">
    Mark all as read
</a>

<!-- LIST -->
<?php while($n = $notifications->fetch_assoc()): ?>

<div class="notif-card shadow-sm <?= $n['is_read'] ? '' : 'unread' ?>">

<div class="d-flex justify-content-between">

<div>

<strong><?= htmlspecialchars($n['title']) ?></strong>

<p class="mb-1">
    <?= htmlspecialchars($n['message']) ?>
</p>

<div class="small">
    <?= $n['created_at'] ?>
</div>

</div>

<div>

<?php if(!$n['is_read']): ?>
    <a href="?read=<?= $n['id'] ?>"
       class="btn btn-sm btn-primary">
       Mark read
    </a>
<?php endif; ?>

</div>

</div>

</div>

<?php endwhile; ?>

</div>

</body>
</html>