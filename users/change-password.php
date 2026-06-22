<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";

if (!isset($_SESSION['user_id']))
{
    header("Location: ../login.php");
    exit;
}

$userId =
(int)$_SESSION['user_id'];

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| LOAD USER
|--------------------------------------------------------------------------
*/

$stmt =
$conn->prepare("
    SELECT
        id,
        email,
        password
    FROM users
    WHERE id=?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$user =
$stmt
->get_result()
->fetch_assoc();

if (!$user)
{
    session_destroy();

    header("Location: ../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CHANGE PASSWORD
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $currentPassword =
    trim($_POST['current_password'] ?? '');

    $newPassword =
    trim($_POST['new_password'] ?? '');

    $confirmPassword =
    trim($_POST['confirm_password'] ?? '');

    if (
        empty($currentPassword) ||
        empty($newPassword) ||
        empty($confirmPassword)
    )
    {
        $error =
        "All fields are required.";
    }
    elseif (
        !password_verify(
            $currentPassword,
            $user['password']
        )
    )
    {
        $error =
        "Current password is incorrect.";
    }
    elseif (
        strlen($newPassword) < 8
    )
    {
        $error =
        "New password must be at least 8 characters.";
    }
    elseif (
        $newPassword !== $confirmPassword
    )
    {
        $error =
        "Password confirmation does not match.";
    }
    elseif (
        password_verify(
            $newPassword,
            $user['password']
        )
    )
    {
        $error =
        "New password must be different from the current password.";
    }
    else
    {
        $hashedPassword =
        password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        $update =
        $conn->prepare("
            UPDATE users
            SET password=?
            WHERE id=?
        ");

        $update->bind_param(
            "si",
            $hashedPassword,
            $userId
        );

        if ($update->execute())
        {
            $success =
            "Password changed successfully.";
        }
        else
        {
            $error =
            "Failed to update password.";
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

<title>

Change Password

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">

</head>

<body>

<div class="container py-5">

<div class="row justify-content-center">

<div class="col-lg-6">

<div class="card shadow-sm">

<div class="card-header">

<h4 class="mb-0">

<i class="bi bi-shield-lock"></i>

Change Password

</h4>

</div>

<div class="card-body">

<?php if($success): ?>

<div class="alert alert-success">

<?= htmlspecialchars($success) ?>

</div>

<?php endif; ?>

<?php if($error): ?>

<div class="alert alert-danger">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>

<form method="POST">

<div class="mb-3">

<label class="form-label">

Current Password

</label>

<input
type="password"
name="current_password"
class="form-control"
required>

</div>

<div class="mb-3">

<label class="form-label">

New Password

</label>

<input
type="password"
name="new_password"
class="form-control"
required>

<div class="form-text">

Minimum 8 characters.

</div>

</div>

<div class="mb-4">

<label class="form-label">

Confirm New Password

</label>

<input
type="password"
name="confirm_password"
class="form-control"
required>

</div>

<div class="d-flex gap-2">

<button
type="submit"
class="btn btn-primary">

Update Password

</button>

<a
href="profile.php"
class="btn btn-secondary">

Back

</a>

</div>

</form>

</div>

</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
