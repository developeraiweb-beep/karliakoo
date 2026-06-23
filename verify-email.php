<?php

session_start();

require_once "config/db.php";

$error = "";
$success = "";

$token = trim($_GET['token'] ?? '');

if (empty($token)) {

    $error = "Verification token is missing.";

} else {

    $stmt = $conn->prepare("
        SELECT *
        FROM email_verifications
        WHERE token = ?
        AND verified = 0
        LIMIT 1
    ");

    $stmt->bind_param(
        "s",
        $token
    );

    $stmt->execute();

    $verification =
    $stmt->get_result()->fetch_assoc();

    if (!$verification) {

        $error =
        "Invalid verification link.";

    } elseif (
        strtotime($verification['expires_at'])
        < time()
    ) {

        $error =
        "Verification link has expired.";

    } else {

        $updateUser = $conn->prepare("
            UPDATE users
            SET email_verified = 1
            WHERE id = ?
        ");

        $updateUser->bind_param(
            "i",
            $verification['user_id']
        );

        $updateUser->execute();

        $updateToken = $conn->prepare("
            UPDATE email_verifications
            SET verified = 1
            WHERE id = ?
        ");

        $updateToken->bind_param(
            "i",
            $verification['id']
        );

        $updateToken->execute();

        $success =
        "Your email has been verified successfully.";
    }
}

?>

<!DOCTYPE html>

<html lang="en">
<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>Email Verification | Karliakoo</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
background:#f5f6fa;
min-height:100vh;
display:flex;
justify-content:center;
align-items:center;
padding:20px;
}

.card-box{
max-width:550px;
width:100%;
background:#fff;
padding:35px;
border-radius:18px;
box-shadow:0 10px 30px rgba(0,0,0,.08);
text-align:center;
}

</style>

</head>

<body>

<div class="card-box">

<h2 class="mb-4">

Karliakoo

</h2>

<?php if($error): ?>

<div class="alert alert-danger">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>

<?php if($success): ?>

<div class="alert alert-success">

<?= htmlspecialchars($success) ?>

</div>

<a
href="login.php"
class="btn btn-success">

Login

</a>

<?php endif; ?>

</div>

</body>
</html>
