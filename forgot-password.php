<?php

session_start();

require_once "config/db.php";

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {

        $error = "Please enter your email address.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Invalid email address.";

    } else {

        $stmt = $conn->prepare("
            SELECT id, full_name, email
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "s",
            $email
        );

        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {

            $error = "No account found with that email address.";

        } else {

            $token = bin2hex(random_bytes(32));

            $expires =
            date(
                "Y-m-d H:i:s",
                strtotime("+1 hour")
            );

            $insert = $conn->prepare("
                INSERT INTO password_resets
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

                $resetLink =
                "http://localhost/Karliakoo/reset-password.php?token=" .
                urlencode($token);

                $success =
                "Password reset link generated successfully.";

            } else {

                $error =
                "Unable to generate reset request.";
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

<title>Forgot Password | Karliakoo</title>

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
max-width:500px;
width:100%;
background:#fff;
padding:30px;
border-radius:16px;
box-shadow:0 10px 30px rgba(0,0,0,.08);
}

</style>

</head>

<body>

<div class="card-box">

<h3 class="mb-3">

Forgot Password

</h3>

<p class="text-muted">

Enter your email address to receive a password reset link.

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

<div class="alert alert-info">

<strong>Development Reset Link:</strong>

<br><br>

<a
href="<?= htmlspecialchars($resetLink) ?>">

<?= htmlspecialchars($resetLink) ?>

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

Generate Reset Link

</button>

</form>

<div class="text-center mt-3">

<a href="login.php">

Back to Login

</a>

</div>

</div>

</body>
</html>
