<?php

require_once "../config/db.php";
session_start();

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";
$success = "";

/*
|--------------------------------------------------------------------------
| GET PARAMETERS
|--------------------------------------------------------------------------
*/
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (empty($token) || empty($email)) {
    die("Invalid reset link.");
}

/*
|--------------------------------------------------------------------------
| HANDLE RESET
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === "POST") {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid request.");
    }

    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (empty($password) || empty($confirm)) {
        $error = "All fields are required.";
    }
    elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    }
    elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    }
    else {

        /*
        |--------------------------------------------------------------------------
        | Find user
        |--------------------------------------------------------------------------
        */
        $stmt = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            $error = "Invalid reset request.";
        } else {

            /*
            |--------------------------------------------------------------------------
            | Validate reset token
            |--------------------------------------------------------------------------
            */
            $check = $conn->prepare("
                SELECT id, token_hash, expires_at, used
                FROM password_resets
                WHERE user_id = ?
                AND used = 0
                ORDER BY id DESC
                LIMIT 1
            ");

            $check->bind_param("i", $user['id']);
            $check->execute();

            $reset = $check->get_result()->fetch_assoc();

            if (!$reset) {
                $error = "Reset link invalid or expired.";
            }
            elseif (strtotime($reset['expires_at']) < time()) {
                $error = "Reset link has expired.";
            }
            elseif (!password_verify($token, $reset['token_hash'])) {
                $error = "Invalid reset token.";
            }
            else {

                /*
                |--------------------------------------------------------------------------
                | Update password
                |--------------------------------------------------------------------------
                */
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                $update = $conn->prepare("
                    UPDATE users
                    SET password = ?
                    WHERE id = ?
                ");

                $update->bind_param("si", $hashedPassword, $user['id']);
                $update->execute();

                /*
                |--------------------------------------------------------------------------
                | Mark token as used
                |--------------------------------------------------------------------------
                */
                $mark = $conn->prepare("
                    UPDATE password_resets
                    SET used = 1
                    WHERE id = ?
                ");

                $mark->bind_param("i", $reset['id']);
                $mark->execute();

                $success = "Password updated successfully. You can now login.";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Reset Password</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background: linear-gradient(120deg, #198754, #0d6efd);
    min-height: 100vh;
    display:flex;
    align-items:center;
    justify-content:center;
}

.card-box{
    background:white;
    padding:30px;
    border-radius:12px;
    width:100%;
    max-width:420px;
}

</style>

</head>

<body>

<div class="card-box shadow">

<h3 class="mb-3 text-center">Reset Password</h3>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<?php if($success): ?>
<div class="alert alert-success">
    <?= $success ?>
    <br><br>
    <a href="login.php" class="btn btn-success btn-sm">Login</a>
</div>
<?php endif; ?>

<?php if(!$success): ?>

<form method="POST">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="mb-3">
    <label>New Password</label>
    <input type="password" name="password" class="form-control" required>
</div>

<div class="mb-3">
    <label>Confirm Password</label>
    <input type="password" name="confirm_password" class="form-control" required>
</div>

<button class="btn btn-primary w-100">
    Update Password
</button>

</form>

<?php endif; ?>

</div>

</body>
</html>