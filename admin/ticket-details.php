<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$admin = currentUser();
$admin_id = (int)$admin['id'];

$ticket_id = (int)($_GET['id'] ?? 0);

if ($ticket_id <= 0) {
    die("Invalid ticket ID");
}

/*
|--------------------------------------------------------------------------
| FETCH TICKET
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        st.*,
        u.full_name,
        u.email,
        u.phone
    FROM support_tickets st
    LEFT JOIN users u
        ON u.id = st.user_id
    WHERE st.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $ticket_id);
$stmt->execute();

$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    die("Ticket not found");
}

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| ASSIGN TO ME
|--------------------------------------------------------------------------
*/
if (isset($_POST['assign_me'])) {

    $assign = $conn->prepare("
        UPDATE support_tickets
        SET assigned_admin = ?,
            status = IF(status='open','in_progress',status)
        WHERE id = ?
    ");

    $assign->bind_param(
        "ii",
        $admin_id,
        $ticket_id
    );

    if ($assign->execute()) {

        header(
            "Location: ticket-details.php?id=".$ticket_id
        );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| UPDATE STATUS
|--------------------------------------------------------------------------
*/
if (isset($_POST['update_status'])) {

    $status = trim($_POST['status']);

    $allowed = [
        'open',
        'in_progress',
        'resolved',
        'closed'
    ];

    if (in_array($status, $allowed)) {

        $update = $conn->prepare("
            UPDATE support_tickets
            SET status=?
            WHERE id=?
        ");

        $update->bind_param(
            "si",
            $status,
            $ticket_id
        );

        $update->execute();

        $success =
            "Ticket status updated.";
    }
}

/*
|--------------------------------------------------------------------------
| SEND REPLY
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['send_reply'])
) {

    $message =
        trim($_POST['message']);

    if (empty($message)) {

        $error =
            "Reply message required.";

    } else {

        $attachment = null;

        if (
            isset($_FILES['attachment'])
            &&
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

            if (
                in_array(
                    $mime,
                    $allowed
                )
            ) {

                $uploadDir =
                    "../uploads/support/";

                if (
                    !is_dir(
                        $uploadDir
                    )
                ) {

                    mkdir(
                        $uploadDir,
                        0755,
                        true
                    );
                }

                $ext =
                    pathinfo(
                        $_FILES['attachment']['name'],
                        PATHINFO_EXTENSION
                    );

                $filename =
                    uniqid(
                        'reply_'
                    ) .
                    '.' .
                    $ext;

                move_uploaded_file(
                    $_FILES['attachment']['tmp_name'],
                    $uploadDir.$filename
                );

                $attachment =
                    $filename;
            }
        }

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
                    ?,
                    ?,
                    'admin',
                    ?,
                    ?
                )
            ");

        $insert->bind_param(
            "iiss",
            $ticket_id,
            $admin_id,
            $message,
            $attachment
        );

        if ($insert->execute()) {

            $update =
                $conn->prepare("
                    UPDATE support_tickets
                    SET
                    updated_at=NOW(),
                    status='in_progress'
                    WHERE id=?
                ");

            $update->bind_param(
                "i",
                $ticket_id
            );

            $update->execute();

            $success =
                "Reply sent successfully.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/
$msg = $conn->prepare("
    SELECT *
    FROM support_messages
    WHERE ticket_id=?
    ORDER BY created_at ASC
");

$msg->bind_param(
    "i",
    $ticket_id
);

$msg->execute();

$messages =
    $msg->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
      content="width=device-width,initial-scale=1">

<title>
Ticket Details
</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f6fa;
}

.message{
    padding:15px;
    border-radius:10px;
    margin-bottom:15px;
}

.admin{
    background:#d1ecf1;
}

.user{
    background:#f8f9fa;
}

</style>

</head>

<body>

<div class="container py-4">

<a
href="support-tickets.php"
class="btn btn-secondary mb-3">

← Back

</a>

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

<div class="card-body">

<h4>
<?= htmlspecialchars(
$ticket['subject']
) ?>
</h4>

<p>

<strong>Ticket:</strong>
<?= htmlspecialchars(
$ticket['ticket_number']
) ?>

</p>

<p>

<strong>User:</strong>
<?= htmlspecialchars(
$ticket['full_name']
) ?>

(
<?= htmlspecialchars(
$ticket['email']
) ?>

)

</p>

<p>

<strong>Priority:</strong>
<?= ucfirst(
$ticket['priority']
) ?>

</p>

<p>

<strong>Status:</strong>
<?= ucfirst(
str_replace(
'_',
' ',
$ticket['status']
)
) ?>

</p>

<form method="POST" class="mb-3">

<button
name="assign_me"
class="btn btn-primary">

Assign To Me

</button>

</form>

<form method="POST">

<div class="row">

<div class="col-md-6">

<select
name="status"
class="form-select">

<option value="open">
Open
</option>

<option value="in_progress">
In Progress
</option>

<option value="resolved">
Resolved
</option>

<option value="closed">
Closed
</option>

</select>

</div>

<div class="col-md-6">

<button
name="update_status"
class="btn btn-success">

Update Status

</button>

</div>

</div>

</form>

</div>

</div>

<div class="card shadow-sm mb-4">

<div class="card-header">
Conversation
</div>

<div class="card-body">

<?php while($row = $messages->fetch_assoc()): ?>

<div class="message <?= $row['sender_role']=='admin' ? 'admin' : 'user' ?>">

<strong>

<?= ucfirst(
htmlspecialchars(
$row['sender_role']
)
) ?>

</strong>

<small class="text-muted">

<?= date(
'd M Y H:i',
strtotime(
$row['created_at']
)
) ?>

</small>

<hr>

<?= nl2br(
htmlspecialchars(
$row['message']
)
) ?>

<?php if(!empty($row['attachment'])): ?>

<div class="mt-2">

<a
href="../uploads/support/<?= urlencode($row['attachment']) ?>"
target="_blank"
class="btn btn-sm btn-outline-primary">

Attachment

</a>

</div>

<?php endif; ?>

</div>

<?php endwhile; ?>

</div>

</div>

<div class="card shadow-sm">

<div class="card-header">
Reply
</div>

<div class="card-body">

<form
method="POST"
enctype="multipart/form-data">

<input
type="hidden"
name="send_reply"
value="1">

<div class="mb-3">

<textarea
name="message"
class="form-control"
rows="5"
required></textarea>

</div>

<div class="mb-3">

<input
type="file"
name="attachment"
class="form-control">

</div>

<button
class="btn btn-primary">

Send Reply

</button>

</form>

</div>

</div>

</div>

</body>
</html>