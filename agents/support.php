<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

if (!$user || $user['role'] !== 'agent') {
    die("Access denied");
}

$agent_id = (int)$user['id'];

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| CREATE TICKET
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['create_ticket'])
) {

    $subject = trim($_POST['subject']);
    $priority = trim($_POST['priority']);
    $message = trim($_POST['message']);

    if (
        empty($subject) ||
        empty($message)
    ) {

        $error = "All fields are required.";

    } else {

        $ticketNumber =
            'SUP-' .
            strtoupper(
                substr(
                    md5(
                        uniqid()
                    ),
                    0,
                    8
                )
            );

        $conn->begin_transaction();

        try {

            $ticket = $conn->prepare("
                INSERT INTO support_tickets
                (
                    ticket_number,
                    user_id,
                    user_role,
                    subject,
                    priority
                )
                VALUES
                (
                    ?,?,
                    'agent',
                    ?,?
                )
            ");

            $ticket->bind_param(
                "siss",
                $ticketNumber,
                $agent_id,
                $subject,
                $priority
            );

            $ticket->execute();

            $ticketId =
                $conn->insert_id;

            $attachment = null;

            if (
                isset($_FILES['attachment']) &&
                $_FILES['attachment']['error'] === 0
            ) {

                $uploadDir =
                    "../uploads/support/";

                if (!is_dir($uploadDir)) {
                    mkdir(
                        $uploadDir,
                        0755,
                        true
                    );
                }

                $filename =
                    time() .
                    '_' .
                    basename(
                        $_FILES['attachment']['name']
                    );

                move_uploaded_file(
                    $_FILES['attachment']['tmp_name'],
                    $uploadDir . $filename
                );

                $attachment = $filename;
            }

            $msg = $conn->prepare("
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

            $msg->bind_param(
                "iiss",
                $ticketId,
                $agent_id,
                $message,
                $attachment
            );

            $msg->execute();

            $conn->commit();

            $success =
                "Support ticket created successfully.";

        } catch(Exception $e) {

            $conn->rollback();

            $error =
                "Failed to create ticket.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH TICKETS
|--------------------------------------------------------------------------
*/
$tickets = $conn->prepare("
    SELECT *
    FROM support_tickets
    WHERE user_id=?
    AND user_role='agent'
    ORDER BY id DESC
");

$tickets->bind_param(
    "i",
    $agent_id
);

$tickets->execute();

$ticketList =
    $tickets->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Support Center</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f5f5;
}

.card-box{
    background:white;
    padding:20px;
    border-radius:12px;
}

</style>

</head>

<body>

<div class="container py-4">

<h2 class="mb-4">
Support Center
</h2>

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

<div class="card-box shadow-sm mb-4">

<h5>Create Support Ticket</h5>

<form
method="POST"
enctype="multipart/form-data">

<input
type="hidden"
name="create_ticket"
value="1">

<div class="mb-3">

<label class="form-label">
Subject
</label>

<input
type="text"
name="subject"
class="form-control"
required>

</div>

<div class="mb-3">

<label class="form-label">
Priority
</label>

<select
name="priority"
class="form-select">

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

<div class="mb-3">

<label class="form-label">
Message
</label>

<textarea
name="message"
rows="5"
class="form-control"
required></textarea>

</div>

<div class="mb-3">

<label class="form-label">
Attachment
</label>

<input
type="file"
name="attachment"
class="form-control">

</div>

<button
class="btn btn-primary">

Submit Ticket

</button>

</form>

</div>

<div class="card shadow-sm">

<div class="card-header">
My Support Tickets
</div>

<div class="card-body p-0">

<table class="table table-hover mb-0">

<thead>

<tr>

<th>Ticket</th>
<th>Subject</th>
<th>Priority</th>
<th>Status</th>
<th>Date</th>
<th></th>

</tr>

</thead>

<tbody>

<?php while($ticket = $ticketList->fetch_assoc()): ?>

<tr>

<td>
<?= htmlspecialchars(
$ticket['ticket_number']
) ?>
</td>

<td>
<?= htmlspecialchars(
$ticket['subject']
) ?>
</td>

<td>

<span class="badge bg-warning">

<?= ucfirst(
$ticket['priority']
) ?>

</span>

</td>

<td>

<?php

$badge = match(
$ticket['status']
){

'open' => 'primary',

'in_progress' => 'warning',

'resolved' => 'success',

'closed' => 'secondary',

default => 'dark'

};

?>

<span class="badge bg-<?= $badge ?>">

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
href="ticket.php?id=<?= $ticket['id'] ?>"
class="btn btn-sm btn-outline-primary">

View

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