<?php

session_start();

require_once "config/db.php";

if (isset($_SESSION['user_id'])) {

    $role = $_SESSION['role'] ?? 'buyer';

    switch ($role) {
        case 'admin':
            header("Location: admin/dashboard.php");
            break;

        case 'seller':
            header("Location: seller/dashboard.php");
            break;

        case 'b2b':
            header("Location: b2b/dashboard.php");
            break;

        case 'agent':
            header("Location: agent/dashboard.php");
            break;

        case 'delivery':
            header("Location: delivery/dashboard.php");
            break;

        default:
            header("Location: users/dashboard.php");
            break;
    }

    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {

        $error = "Please enter your email/phone and password.";

    } else {

        $stmt = $conn->prepare("
            SELECT *
            FROM users
            WHERE email = ?
            OR phone = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "ss",
            $login,
            $login
        );

        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {

            $error = "Account not found.";

        } elseif ($user['status'] !== 'active') {

            $error = "Your account is currently unavailable.";

        } elseif (!password_verify($password, $user['password'])) {

            $error = "Incorrect password.";

        } else {

            session_regenerate_id(true);

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;

            $update = $conn->prepare("
                UPDATE users
                SET last_login = NOW()
                WHERE id = ?
            ");

            $update->bind_param(
                "i",
                $user['id']
            );

            $update->execute();

            switch ($user['role']) {

                case 'admin':
                    header("Location: admin/dashboard.php");
                    break;

                case 'seller':
                    header("Location: seller/dashboard.php");
                    break;

                case 'b2b':
                    header("Location: b2b/dashboard.php");
                    break;

                case 'agent':
                    header("Location: agent/dashboard.php");
                    break;

                case 'delivery':
                    header("Location: delivery/dashboard.php");
                    break;

                default:
                    header("Location: users/dashboard.php");
                    break;
            }

            exit;
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
content="width=device-width, initial-scale=1.0">

<title>Login | Karliakoo</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
rel="stylesheet">

<style>

body{
background:#f5f6fa;
min-height:100vh;
display:flex;
align-items:center;
justify-content:center;
}

.login-card{
width:100%;
max-width:450px;
background:#fff;
padding:35px;
border-radius:20px;
box-shadow:0 10px 30px rgba(0,0,0,.08);
}

.brand{
text-align:center;
margin-bottom:25px;
}

.brand h2{
font-weight:700;
margin-bottom:5px;
}

.brand p{
color:#777;
}

</style>

</head>

<body>

<div class="login-card">

<div class="brand">

<h2>Karliakoo</h2>

<p>Marketplace Login</p>

</div>

<?php if(!empty($error)): ?>

<div class="alert alert-danger">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>

<form method="POST">

<div class="mb-3">

<label class="form-label">

Email or Phone

</label>

<input
type="text"
name="login"
class="form-control"
required>

</div>

<div class="mb-3">

<label class="form-label">

Password

</label>

<input
type="password"
name="password"
class="form-control"
required>

</div>

<div class="d-grid">

<button
type="submit"
class="btn btn-primary">

<i class="fas fa-sign-in-alt"></i>
Login

</button>

</div>

</form>

<div class="text-center mt-3">

<a href="register.php">
Create Account
</a>

|

<a href="forgot-password.php">
Forgot Password?
</a>

</div>

</div>

</body>
</html>
