<?php

session_start();

require_once "config/db.php";

$error = "";
$success = "";

$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    die("Invalid password reset link.");
}

/*
|--------------------------------------------------------------------------
| VERIFY TOKEN
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM password_resets
    WHERE token = ?
    AND used = 0
    LIMIT 1
");

$stmt->bind_param(
    "s",
    $token
);

$stmt->execute();

$reset = $stmt
    ->get_result()
    ->fetch_assoc();

if (!$reset) {
    die("Invalid or expired reset token.");
}

if (strtotime($reset['expires_at']) < time()) {
    die("This reset link has expired.");
}

/*
|--------------------------------------------------------------------------
| PROCESS PASSWORD RESET
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password =
    $_POST['password'] ?? '';

    $confirmPassword =
    $_POST['confirm_password'] ?? '';

    if (
        empty($password) ||
        empty($confirmPassword)
    ) {

        $error =
        "Please fill all fields.";

    }
    elseif (strlen($password) < 8) {

        $error =
        "Password must be at least 8 characters.";

    }
    elseif ($password !== $confirmPassword) {

        $error =
        "Passwords do not match.";

    }
    else {

        $hashedPassword =
        password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        /*
        |--------------------------------------------------------------------------
        | UPDATE USER PASSWORD
        |--------------------------------------------------------------------------
        */

        $update = $conn->prepare("
            UPDATE users
            SET password = ?
            WHERE id = ?
        ");

        $update->bind_param(
            "si",
            $hashedPassword,
            $reset['user_id']
        );

        if ($update->execute()) {

            /*
            |--------------------------------------------------------------------------
            | MARK TOKEN USED
            |--------------------------------------------------------------------------
            */

            $used = $conn->prepare("
                UPDATE password_resets
                SET used = 1
                WHERE id = ?
            ");

            $used->bind_param(
                "i",
                $reset['id']
            );

            $used->execute();

            $success =
            "Password updated successfully.";

        } else {

            $error =
            "Unable to reset password.";
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

<title>Reset Password | Karliakoo</title>

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

.reset-card{
background:#fff;
width:100%;
max-width:500px;
padding:35px;
border-radius:18px;
box-shadow:0 10px 30px rgba(0,0,0,.08);
}

</style>

</head>

<body>

<div class="reset-card">

<h3 class="mb-3">

Reset Password

</h3>

<p class="text-muted">

Create a new password for your account.

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

<div class="mt-3">

<a
href="login.php"
class="btn btn-success">

Login Now

</a>

</div>

<?php else: ?>

<form method="POST">

<div class="mb-3">

<label class="form-label">

New Password

</label>

<input
type="password"
name="password"
class="form-control"
required>

</div>

<div class="mb-3">

<label class="form-label">

Confirm Password

</label>

<input
type="password"
name="confirm_password"
class="form-control"
required>

</div>

<button
type="submit"
class="btn btn-primary w-100">

Reset Password

</button>

</form>

<?php endif; ?>

<div class="text-center mt-3">

<a href="login.php">

Back To Login

</a>

</div>

</div>

</body>
</html>
