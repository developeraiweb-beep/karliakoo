<?php

session_start();

require_once "config/db.php";

$error = "";
$success = "";
$verificationLink = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {

        $error = "Please enter your email address.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Invalid email address.";

    } else {

        $stmt = $conn->prepare("
            SELECT id, full_name, email, email_verified
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "s",
            $email
        );

        $stmt->execute();

        $user = $stmt
            ->get_result()
            ->fetch_assoc();

        if (!$user) {

            $error =
            "No account found with that email address.";

        } elseif ((int)$user['email_verified'] === 1) {

            $error =
            "This email address is already verified.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | EXPIRE OLD TOKENS
            |--------------------------------------------------------------------------
            */

            $expireOld = $conn->prepare("
                UPDATE email_verifications
                SET verified = 1
                WHERE user_id = ?
            ");

            $expireOld->bind_param(
                "i",
                $user['id']
            );

            $expireOld->execute();

            /*
            |--------------------------------------------------------------------------
            | GENERATE NEW TOKEN
            |--------------------------------------------------------------------------
            */

            $token =
            bin2hex(
                random_bytes(32)
            );

            $expires =
            date(
                "Y-m-d H:i:s",
                strtotime("+24 hours")
            );

            $insert = $conn->prepare("
                INSERT INTO email_verifications
                (
                    user_id,
                    email,
                    token,
                    expires_at
                )
                VALUES
                (
                    ?, ?, ?, ?
                )
            ");

            $insert->bind_param(
                "isss",
                $user['id'],
                $user['email'],
                $token,
                $expires
            );

            if ($insert->execute()) {

                /*
                |--------------------------------------------------------------------------
                | DEVELOPMENT LINK
                |--------------------------------------------------------------------------
                */

                $verificationLink =
                "http://localhost/Karliakoo/verify-email.php?token="
                . urlencode($token);

                /*
                |--------------------------------------------------------------------------
                | PRODUCTION EMAIL
                |--------------------------------------------------------------------------
                | Replace this block with PHPMailer
                |--------------------------------------------------------------------------
                */

                $success =
                "A new verification link has been generated.";

            } else {

                $error =
                "Unable to generate verification link.";
            }
        }
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

<title>Resend Verification | Karliakoo</title>

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
width:100%;
max-width:550px;
background:#fff;
padding:35px;
border-radius:18px;
box-shadow:0 10px 30px rgba(0,0,0,.08);
}

</style>

</head>

<body>

<div class="card-box">

<h3 class="mb-3">

Resend Verification Email

</h3>

<p class="text-muted">

Enter your email address to receive a new verification link.

</p>

<?php if($error): ?>

<div class="alert alert-danger">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>

<?php if($success): ?>

<div class="alert alert-success">

<?= htmlspecialchars($success) ?>

</div>

<?php endif; ?>

<?php if(!empty($verificationLink)): ?>

<div class="alert alert-info">

<strong>Development Verification Link</strong>

<br><br>

<a
href="<?= htmlspecialchars($verificationLink) ?>">

<?= htmlspecialchars($verificationLink) ?>

</a>

</div>

<?php endif; ?>

<form method="POST">

<div class="mb-3">

<label class="form-label">

Email Address

</label>

<input
type="email"
name="email"
class="form-control"
required>

</div>

<button
type="submit"
class="btn btn-primary w-100">

Resend Verification

</button>

</form>

<div class="text-center mt-3">

<a href="login.php">

Back To Login

</a>

</div>

</div>

</body>
</html>
