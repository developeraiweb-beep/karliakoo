<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['b2b']);

$userId = (int)$_SESSION['user_id'];

$conversationId =
(int)($_GET['id'] ?? 0);

if($conversationId <= 0)
{
    header("Location: messages.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| VERIFY ACCESS
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT *
FROM b2b_conversations
WHERE id=?
AND (
    sender_id=?
    OR receiver_id=?
)
LIMIT 1
");

$stmt->bind_param(
    "iii",
    $conversationId,
    $userId,
    $userId
);

$stmt->execute();

$conversation =
$stmt->get_result()->fetch_assoc();

if(!$conversation)
{
    die("Unauthorized access.");
}

/*
|--------------------------------------------------------------------------
| SEND REPLY
|--------------------------------------------------------------------------
*/
if(isset($_POST['send_message']))
{
    $message =
    trim($_POST['message']);

    $attachment = null;

    if(
        !empty($_FILES['attachment']['name'])
    )
    {
        $uploadDir =
        "../uploads/messages/";

        if(!is_dir($uploadDir))
        {
            mkdir(
                $uploadDir,
                0755,
                true
            );
        }

        $fileName =
        time().'_'.
        basename(
            $_FILES['attachment']['name']
        );

        $target =
        $uploadDir.$fileName;

        if(
            move_uploaded_file(
                $_FILES['attachment']['tmp_name'],
                $target
            )
        )
        {
            $attachment =
            $target;
        }
    }

    if(!empty($message))
    {
        $insert =
        $conn->prepare("
        INSERT INTO b2b_messages(
            conversation_id,
            sender_id,
            message,
            attachment
        )
        VALUES(
            ?,?,?,?
        )
        ");

        $insert->bind_param(
            "iiss",
            $conversationId,
            $userId,
            $message,
            $attachment
        );

        $insert->execute();

        $update =
        $conn->prepare("
        UPDATE b2b_conversations
        SET last_message_at=NOW()
        WHERE id=?
        ");

        $update->bind_param(
            "i",
            $conversationId
        );

        $update->execute();

        header(
        "Location: conversation-details.php?id=".$conversationId
        );
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| MARK AS READ
|--------------------------------------------------------------------------
*/
$mark =
$conn->prepare("
UPDATE b2b_messages

SET
is_read=1,
read_at=NOW()

WHERE
conversation_id=?
AND sender_id!=?
AND is_read=0
");

$mark->bind_param(
    "ii",
    $conversationId,
    $userId
);

$mark->execute();

/*
|--------------------------------------------------------------------------
| LOAD MESSAGES
|--------------------------------------------------------------------------
*/
$messages =
$conn->prepare("
SELECT *
FROM b2b_messages
WHERE conversation_id=?
ORDER BY created_at ASC
");

$messages->bind_param(
    "i",
    $conversationId
);

$messages->execute();

$messages =
$messages->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>Conversation</title>

<meta
name="viewport"
content="width=device-width,initial-scale=1">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
background:#f4f6f9;
}

.chat-container{
background:white;
border-radius:15px;
padding:20px;
height:600px;
overflow-y:auto;
box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.message{
padding:12px;
border-radius:12px;
margin-bottom:12px;
max-width:75%;
}

.mine{
background:#0d6efd;
color:white;
margin-left:auto;
}

.other{
background:#e9ecef;
}

.meta{
font-size:12px;
opacity:.8;
margin-top:5px;
}

.reply-box{
background:white;
padding:20px;
margin-top:20px;
border-radius:15px;
box-shadow:0 2px 10px rgba(0,0,0,.08);
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between mb-3">

<h3>

<?= htmlspecialchars(
$conversation['subject']
?: 'Conversation'
) ?>

</h3>

<a
href="messages.php"
class="btn btn-secondary">

Back

</a>

</div>

<!-- CHAT -->

<div class="chat-container">

<?php while(
$msg =
$messages->fetch_assoc()
): ?>

<div class="message <?=

$msg['sender_id']==$userId
?
'mine'
:
'other'

?>">

<div>

<?= nl2br(
htmlspecialchars(
$msg['message']
)
) ?>

</div>

<?php if(
!empty($msg['attachment'])
): ?>

<div class="mt-2">

<a
href="<?= $msg['attachment'] ?>"
target="_blank"
class="btn btn-sm btn-light">

Attachment

</a>

</div>

<?php endif; ?>

<div class="meta">

<?= date(
'd M Y H:i',
strtotime(
$msg['created_at']
)
) ?>

<?php if(
$msg['sender_id']==$userId
): ?>

|

<?= $msg['is_read']
?
'Read'
:
'Delivered'
?>

<?php endif; ?>

</div>

</div>

<?php endwhile; ?>

</div>

<!-- REPLY -->

<div class="reply-box">

<form
method="POST"
enctype="multipart/form-data">

<div class="mb-3">

<textarea
name="message"
class="form-control"
rows="4"
placeholder="Type your message..."
required></textarea>

</div>

<div class="row">

<div class="col-md-4">

<input
type="file"
name="attachment"
class="form-control">

</div>

<div class="col-md-3">

<button
name="send_message"
class="btn btn-primary">

Send Message

</button>

</div>

</div>

</form>

</div>

</div>

</body>
</html>