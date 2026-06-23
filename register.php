<?php

session_start();

require_once "config/db.php";

if (isset($_SESSION['user_id'])) {
    header("Location: users/dashboard.php");
    exit;
}

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (
        empty($full_name) ||
        empty($phone) ||
        empty($password) ||
        empty($confirm_password)
    ) {

        $error = "Please fill all required fields.";

    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Invalid email address.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } else {

        $check = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            OR phone = ?
            LIMIT 1
        ");

        $check->bind_param(
            "ss",
            $email,
            $phone
        );

        $check->execute();

        $exists = $check->get_result()->fetch_assoc();

        if ($exists) {

            $error = "Email or phone number already exists.";

        } else {

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $role = "buyer";
            $status = "active";

            $stmt = $conn->prepare("
                INSERT INTO users (
                    full_name,
                    email,
                    phone,
                    password,
                    role,
                    status,
                    created_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, NOW()
                )
            ");

            $stmt->bind_param(
                "ssssss",
                $full_name,
                $email,
                $phone,
                $hashedPassword,
                $role,
                $status
            );

            if ($stmt->execute()) {

                $success =
                "Registration successful. You can now login.";

            } else {

                $error =
                "Failed to create account.";
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

<title>Register | Karliakoo</title>

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
justify-content:center;
align-items:center;
padding:30px;
}

.register-card{
width:100%;
max-width:600px;
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
}

</style>

</head>

<body>

<div class="register-card">

<div class="brand">

<h2>Karliakoo</h2>

<p>Create Your Marketplace Account</p>

</div>

<?php if($error): ?>

<div class="alert alert-danger">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>

<?php if($success): ?>

<div class="alert alert-success">

<?= htmlspecialchars($success) ?>

<br><br>

<a href="login.php" class="btn btn-success">

Login Now

</a>

</div>

<?php endif; ?>

<form method="POST">

<div class="row">

<div class="col-md-12 mb-3">

<label class="form-label">

Full Name

</label>

<input
type="text"
name="full_name"
class="form-control"
required>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Email

</label>

<input
type="email"
name="email"
class="form-control">

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Phone Number

</label>

<input
type="text"
name="phone"
class="form-control"
required>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Password

</label>

<input
type="password"
name="password"
class="form-control"
required>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Confirm Password

</label>

<input
type="password"
name="confirm_password"
class="form-control"
required>

</div>

</div>

<div class="d-grid">

<button
type="submit"
class="btn btn-primary">

<i class="fas fa-user-plus"></i>
Create Account

</button>

</div>

</form>

<div class="text-center mt-3">

Already have an account?

<a href="login.php">

Login

</a>

</div>

</div>

</body>
</html>
