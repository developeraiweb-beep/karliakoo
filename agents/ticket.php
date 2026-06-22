<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

if (!$user || $user['role'] !== 'agent') {
    die("Access denied.");
}

$agent_id = (int)$user['id'];

$ticket_id = (int)($_GET['id'] ?? 0);

if ($ticket_id <= 0) {
    die("Invalid ticket.");
}

/*
|--------------------------------------------------------------------------
| GET TICKET
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT *
    FROM support_tickets
    WHERE id = ?
    AND user_id = ?
    AND user_role = 'agent'
    LIMIT 1
");

$stmt->bind_param("ii", $ticket_id, $agent_id);
$stmt->execute();

$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    die("Ticket not found.");
}

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| ADD REPLY
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['reply'])
) {

    if (
        in_array(
            $ticket['status'],
            ['resolved', 'closed']
        )
    ) {
        $error =
            "This ticket is already closed.";
    } else {

        $message =
            trim($_POST['message'] ?? '');

        if (empty($message)) {

            $error =
                "Message cannot be empty.";

        } else {

            $attachment = null;

            /*
            |--------------------------------------------------------------------------
            | FILE UPLOAD
            |--------------------------------------------------------------------------
            */
            if (
                isset($_FILES['attachment']) &&
                $_FILES['attachment']['error'] === 0
            ) {

                $allowed = [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'application/pdf'
                ];

                $mime =
                    mime_content_type(
                        $_FILES['attachment']['tmp_name']
                    );

                if (!in_array($mime, $allowed)) {

                    $error =
                        "Invalid attachment type.";

                } else {

                    $uploadDir =
                        "../uploads/support/";

                    if (!is_dir($uploadDir)) {
                        mkdir(
                            $uploadDir,
                            0755,
                            true
                        );
                    }

                    $extension =
                        pathinfo(
                            $_FILES['attachment']['name'],
                            PATHINFO_EXTENSION
                        );

                    $filename =
                        uniqid('ticket_') .
                        '.' .
                        $extension;

                    move_uploaded_file(
                        $_FILES['attachment']['tmp_name'],
                        $uploadDir . $filename
                    );

                    $attachment = $filename;
                }
            }

            if (!$error) {

                $insert =
                    $conn->prepare("
                        INSERT INTO support_messages
                        (
                            ticket_id,
                            sender_id,
                            sender_role,
                            message,
                            attachment
                        )
                        VALUES
                        (
                            ?,?,
                            'agent',
                            ?,?
                        )
                    ");

                $insert->bind_param(
                    "iiss",
                    $ticket_id,
                    $agent_id,
                    $message,
                    $attachment
                );

                if ($insert->execute()) {

                    $update =
                        $conn->prepare("
                            UPDATE support_tickets
                            SET updated_at = NOW()
                            WHERE id = ?
                        ");

                    $update->bind_param(
                        "i",
                        $ticket_id
                    );

                    $update->execute();

                    $success =
                        "Reply sent successfully.";
                } else {

                    $error =
                        "Failed to send reply.";
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| GET MESSAGES
|--------------------------------------------------------------------------
*/
$msgStmt = $conn->prepare("
    SELECT *
    FROM support_messages
    WHERE ticket_id = ?
    ORDER BY created_at ASC
");

$msgStmt->bind_param(
    "i",
    $ticket_id
);

$msgStmt->execute();

$messages =
    $msgStmt->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>
Ticket #<?= htmlspecialchars($ticket['ticket_number']) ?>
</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">

<style>

body{
    background:#f5f5f5;
}

.message-box{
    border-radius:10px;
    padding:15px;
    margin-bottom:15px;
}

.agent{
    background:#d1ecf1;
}

.admin{
    background:#f8f9fa;
}

</style>

</head>

<body>

<div class="container py-4">

<div class="mb-3">

<a href="support.php"
   class="btn btn-secondary btn-sm">

← Back to Tickets

</a>

</div>

<div class="card shadow-sm mb-4">

<div class="card-body">

<h4>
<?= htmlspecialchars($ticket['subject']) ?>
</h4>

<p>

Ticket:
<strong>
<?= htmlspecialchars($ticket['ticket_number']) ?>
</strong>

</p>

<p>

Priority:
<span class="badge bg-warning">

<?= ucfirst($ticket['priority']) ?>

</span>

</p>

<p>

Status:

<?php

$statusClass = match($ticket['status']) {

    'open' => 'primary',
    'in_progress' => 'warning',
    'resolved' => 'success',
    'closed' => 'secondary',

    default => 'dark'
};

?>

<span class="badge bg-<?= $statusClass ?>">

<?= ucfirst(
str_replace(
'_',
' ',
$ticket['status']
)
) ?>

</span>

</p>

</div>

</div>

<?php if($success): ?>

<div class="alert alert-success">
<?= $success ?>
</div>

<?php endif; ?>

<?php if($error): ?>

<div class="alert alert-danger">
<?= $error ?>
</div>

<?php endif; ?>

<div class="card shadow-sm mb-4">

<div class="card-header">

Conversation

</div>

<div class="card-body">

<?php while($msg = $messages->fetch_assoc()): ?>

<div class="message-box <?= $msg['sender_role'] === 'agent' ? 'agent' : 'admin' ?>">

<div class="mb-2">

<strong>

<?= ucfirst(
htmlspecialchars(
$msg['sender_role']
)
) ?>

</strong>

<small class="text-muted">

<?= date(
'd M Y H:i',
strtotime(
$msg['created_at']
)
) ?>

</small>

</div>

<div>

<?= nl2br(
htmlspecialchars(
$msg['message']
)
) ?>

</div>

<?php if(!empty($msg['attachment'])): ?>

<div class="mt-2">

<a
href="../uploads/support/<?= urlencode($msg['attachment']) ?>"
target="_blank"
class="btn btn-sm btn-outline-primary">

View Attachment

</a>

</div>

<?php endif; ?>

</div>

<?php endwhile; ?>

</div>

</div>

<?php if(
!in_array(
$ticket['status'],
['resolved','closed']
)
): ?>

<div class="card shadow-sm">

<div class="card-header">
Reply
</div>

<div class="card-body">

<form method="POST"
      enctype="multipart/form-data">

<input
type="hidden"
name="reply"
value="1">

<div class="mb-3">

<label class="form-label">
Message
</label>

<textarea
name="message"
class="form-control"
rows="5"
required></textarea>

</div>

<div class="mb-3">

<label class="form-label">
Attachment (Optional)
</label>

<input
type="file"
name="attachment"
class="form-control">

</div>

<button class="btn btn-primary">

Send Reply

</button>

</form>

</div>

</div>

<?php endif; ?>

</div>

</body>
</html>