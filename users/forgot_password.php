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
| HANDLE REQUEST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === "POST") {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid request.");
    }

    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = "Email is required.";
    } else {

        /*
        |--------------------------------------------------------------------------
        | Find user
        |--------------------------------------------------------------------------
        */
        $stmt = $conn->prepare("
            SELECT id, email
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        /*
        |--------------------------------------------------------------------------
        | Always show success (prevents email enumeration)
        |--------------------------------------------------------------------------
        */
        if (!$user) {
            $success = "If the email exists, a reset link has been sent.";
        } else {

            /*
            |--------------------------------------------------------------------------
            | Generate secure token
            |--------------------------------------------------------------------------
            */
            $token = bin2hex(random_bytes(32));
            $token_hash = password_hash($token, PASSWORD_BCRYPT);
            $expires_at = date("Y-m-d H:i:s", strtotime("+1 hour"));

            /*
            |--------------------------------------------------------------------------
            | Store token
            |--------------------------------------------------------------------------
            */
            $insert = $conn->prepare("
                INSERT INTO password_resets (
                    user_id,
                    token_hash,
                    expires_at
                )
                VALUES (?,?,?)
            ");

            $insert->bind_param(
                "iss",
                $user['id'],
                $token_hash,
                $expires_at
            );

            $insert->execute();

            /*
            |--------------------------------------------------------------------------
            | Reset Link (replace domain in production)
            |--------------------------------------------------------------------------
            */
            $resetLink = "http://localhost/users/reset_password.php?token=" . $token . "&email=" . urlencode($email);

            /*
            |--------------------------------------------------------------------------
            | TODO: Send Email (PHPMailer recommended)
            |--------------------------------------------------------------------------
            */
            // mail($email, "Password Reset", $resetLink);

            $success = "If the email exists, a reset link has been sent.";

            // For development only (REMOVE IN PRODUCTION)
            $success .= "<br><small>Reset Link: $resetLink</small>";
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Forgot Password</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background: linear-gradient(120deg, #6f42c1, #0d6efd);
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

<h3 class="mb-3 text-center">Forgot Password</h3>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<?php if($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<form method="POST">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="mb-3">
    <label>Email Address</label>
    <input type="email" name="email" class="form-control" required>
</div>

<button class="btn btn-primary w-100">
    Send Reset Link
</button>

</form>

<hr>

<p class="text-center">
Back to <a href="login.php">Login</a>
</p>

</div>

</body>
</html>