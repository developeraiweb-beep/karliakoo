<?php

require_once "../config/db.php";
session_start();

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/
if(empty($_SESSION['csrf_token'])){
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";
$success = "";

/*
|--------------------------------------------------------------------------
| Registration Handler
|--------------------------------------------------------------------------
*/
if($_SERVER['REQUEST_METHOD'] === "POST")
{
    if(
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ){
        die("Invalid request.");
    }

    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if(
        empty($full_name) ||
        empty($email) ||
        empty($password)
    ){
        $error = "All required fields must be filled.";
    }
    elseif($password !== $confirm){
        $error = "Passwords do not match.";
    }
    elseif(strlen($password) < 6){
        $error = "Password must be at least 6 characters.";
    }
    else
    {
        /*
        |--------------------------------------------------------------------------
        | Check if user exists
        |--------------------------------------------------------------------------
        */
        $check = $conn->prepare("
            SELECT id
            FROM users
            WHERE email=? OR phone=?
            LIMIT 1
        ");

        $check->bind_param("ss", $email, $phone);
        $check->execute();

        $exists = $check->get_result();

        if($exists->num_rows > 0){
            $error = "Account already exists with this email or phone.";
        }
        else
        {
            /*
            |--------------------------------------------------------------------------
            | Create User
            |--------------------------------------------------------------------------
            */
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $conn->prepare("
                INSERT INTO users(
                    full_name,
                    email,
                    phone,
                    password,
                    role,
                    created_at
                )
                VALUES(?,?,?,?, 'user', NOW())
            ");

            $stmt->bind_param(
                "ssss",
                $full_name,
                $email,
                $phone,
                $hashedPassword
            );

            if($stmt->execute())
            {
                $user_id = $stmt->insert_id;

                /*
                |--------------------------------------------------------------------------
                | Auto Login
                |--------------------------------------------------------------------------
                */
                $_SESSION['user_id'] = $user_id;
                $_SESSION['role'] = 'user';

                header("Location: ../index.php");
                exit;
            }
            else
            {
                $error = "Registration failed. Try again.";
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

<title>Register | Karliakoo</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background: linear-gradient(120deg, #0d6efd, #6610f2);
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
    max-width:450px;
}

</style>

</head>

<body>

<div class="card-box shadow">

<h3 class="mb-3 text-center">Create Account</h3>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="mb-3">
    <label>Full Name</label>
    <input type="text" name="full_name" class="form-control" required>
</div>

<div class="mb-3">
    <label>Email</label>
    <input type="email" name="email" class="form-control" required>
</div>

<div class="mb-3">
    <label>Phone (optional)</label>
    <input type="text" name="phone" class="form-control">
</div>

<div class="mb-3">
    <label>Password</label>
    <input type="password" name="password" class="form-control" required>
</div>

<div class="mb-3">
    <label>Confirm Password</label>
    <input type="password" name="confirm_password" class="form-control" required>
</div>

<button class="btn btn-primary w-100">
    Register
</button>

</form>

<hr>

<p class="text-center">
Already have an account?
<a href="login.php">Login</a>
</p>

</div>

</body>
</html>