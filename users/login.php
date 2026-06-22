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

/*
|--------------------------------------------------------------------------
| Login Handler
|--------------------------------------------------------------------------
*/
if($_SERVER['REQUEST_METHOD'] === "POST")
{
    if(
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ){
        die("Invalid request.");
    }

    $identifier = trim($_POST['identifier']); // email or phone
    $password = $_POST['password'];

    if(empty($identifier) || empty($password)){
        $error = "All fields are required.";
    }
    else
    {
        /*
        |--------------------------------------------------------------------------
        | Find user
        |--------------------------------------------------------------------------
        */
        $stmt = $conn->prepare("
            SELECT id, full_name, email, phone, password, role
            FROM users
            WHERE email = ? OR phone = ?
            LIMIT 1
        ");

        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        if(!$user){
            $error = "Invalid login credentials.";
        }
        else
        {
            /*
            |--------------------------------------------------------------------------
            | Verify Password
            |--------------------------------------------------------------------------
            */
            if(!password_verify($password, $user['password'])){
                $error = "Invalid login credentials.";
            }
            else
            {
                /*
                |--------------------------------------------------------------------------
                | Create Session
                |--------------------------------------------------------------------------
                */
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];

                /*
                |--------------------------------------------------------------------------
                | Role-based Redirect
                |--------------------------------------------------------------------------
                */
                switch($user['role'])
                {
                    case 'admin':
                        header("Location: ../admin/dashboard.php");
                        break;

                    case 'seller':
                        header("Location: ../sellers/dashboard.php");
                        break;

                    default:
                        header("Location: ../index.php");
                        break;
                }
                exit;
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

<title>Login | Karliakoo</title>

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

<h3 class="mb-3 text-center">Login to Karliakoo</h3>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="mb-3">
    <label>Email or Phone</label>
    <input type="text" name="identifier" class="form-control" required>
</div>

<div class="mb-3">
    <label>Password</label>
    <input type="password" name="password" class="form-control" required>
</div>

<button class="btn btn-success w-100">
    Login
</button>

</form>

<hr>

<p class="text-center">
Don't have an account?
<a href="register.php">Register</a>
</p>

</div>

</body>
</html>