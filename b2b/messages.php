<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['b2b']);

$userId = (int)$_SESSION['user_id'];

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| START CONVERSATION
|--------------------------------------------------------------------------
*/
if(isset($_POST['start_conversation']))
{
    $receiverId = (int)$_POST['receiver_id'];
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if($receiverId > 0 && !empty($message))
    {
        $conn->begin_transaction();

        try {

            $stmt = $conn->prepare("
            INSERT INTO b2b_conversations(
                sender_id,
                receiver_id,
                subject,
                last_message_at
            )
            VALUES(
                ?,?,?,NOW()
            )
            ");

            $stmt->bind_param(
                "iis",
                $userId,
                $receiverId,
                $subject
            );

            $stmt->execute();

            $conversationId =
            $conn->insert_id;

            $msg = $conn->prepare("
            INSERT INTO b2b_messages(
                conversation_id,
                sender_id,
                message
            )
            VALUES(?,?,?)
            ");

            $msg->bind_param(
                "iis",
                $conversationId,
                $userId,
                $message
            );

            $msg->execute();

            $conn->commit();

            $success =
            "Conversation started successfully.";

        } catch(Exception $e){

            $conn->rollback();

            $error =
            "Failed to start conversation.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| CONVERSATIONS
|--------------------------------------------------------------------------
*/
$conversations =
$conn->prepare("
SELECT *

FROM b2b_conversations

WHERE sender_id=?
OR receiver_id=?

ORDER BY last_message_at DESC
");

$conversations->bind_param(
    "ii",
    $userId,
    $userId
);

$conversations->execute();

$conversations =
$conversations->get_result();

/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/
$stats =
$conn->prepare("
SELECT

COUNT(*) total_conversations

FROM b2b_conversations

WHERE sender_id=?
OR receiver_id=?
");

$stats->bind_param(
    "ii",
    $userId,
    $userId
);

$stats->execute();

$totalConversations =
$stats
->get_result()
->fetch_assoc()['total_conversations'];

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>Messages</title>

<meta
name="viewport"
content="width=device-width, initial-scale=1">

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
border-radius:15px;
margin-bottom:20px;
box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.metric{
font-size:32px;
font-weight:bold;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Messaging Center

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

<!-- STATS -->

<div class="row mb-4">

<div class="col-md-4">

<div class="card-box text-center">

<div class="metric">

<?= number_format(
$totalConversations
) ?>

</div>

Conversations

</div>

</div>

</div>

<!-- NEW MESSAGE -->

<div class="card-box">

<h5>

Start Conversation

</h5>

<form method="POST">

<div class="row g-3">

<div class="col-md-3">

<input
type="number"
name="receiver_id"
class="form-control"
placeholder="Recipient User ID"
required>

</div>

<div class="col-md-4">

<input
type="text"
name="subject"
class="form-control"
placeholder="Subject">

</div>

<div class="col-md-12">

<textarea
name="message"
rows="4"
class="form-control"
placeholder="Write message..."
required></textarea>

</div>

<div class="col-md-3">

<button
name="start_conversation"
class="btn btn-primary">

Send Message

</button>

</div>

</div>

</form>

</div>

<!-- CONVERSATIONS -->

<div class="card-box">

<h5>

My Conversations

</h5>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>ID</th>
<th>Subject</th>
<th>Status</th>
<th>Last Activity</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php while(
$row =
$conversations->fetch_assoc()
): ?>

<tr>

<td>

#<?= $row['id'] ?>

</td>

<td>

<?= htmlspecialchars(
$row['subject']
?: 'No Subject'
) ?>

</td>

<td>

<span class="badge bg-success">

<?= ucfirst(
$row['status']
) ?>

</span>

</td>

<td>

<?= date(
'd M Y H:i',
strtotime(
$row['last_message_at']
)
) ?>

</td>

<td>

<a
href="conversation-details.php?id=<?= $row['id'] ?>"
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