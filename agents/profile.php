<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

if (!$user || $user['role'] !== 'agent') {
    die("Access denied.");
}

$agent_id = (int)$user['id'];

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| UPDATE PROFILE
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_profile'])
) {

    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);

    if (empty($full_name)) {

        $error = "Full name is required.";

    } else {

        $photo = $user['profile_photo'];

        /*
        |--------------------------------------------------------------------------
        | PHOTO UPLOAD
        |--------------------------------------------------------------------------
        */
        if (
            isset($_FILES['profile_photo']) &&
            $_FILES['profile_photo']['error'] === 0
        ) {

            $allowed = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];

            $mime = mime_content_type(
                $_FILES['profile_photo']['tmp_name']
            );

            if (!in_array($mime, $allowed)) {

                $error = "Invalid image format.";

            } else {

                $ext = pathinfo(
                    $_FILES['profile_photo']['name'],
                    PATHINFO_EXTENSION
                );

                $filename =
                    'agent_' .
                    $agent_id .
                    '_' .
                    time() .
                    '.' .
                    $ext;

                $uploadDir =
                    "../uploads/profiles/";

                if (!is_dir($uploadDir)) {
                    mkdir(
                        $uploadDir,
                        0755,
                        true
                    );
                }

                move_uploaded_file(
                    $_FILES['profile_photo']['tmp_name'],
                    $uploadDir . $filename
                );

                $photo = $filename;
            }
        }

        if (!$error) {

            $stmt = $conn->prepare("
                UPDATE users
                SET
                    full_name=?,
                    phone=?,
                    profile_photo=?
                WHERE id=?
            ");

            $stmt->bind_param(
                "sssi",
                $full_name,
                $phone,
                $photo,
                $agent_id
            );

            if ($stmt->execute()) {

                $success =
                    "Profile updated successfully.";

                $user = currentUser();

            } else {

                $error =
                    "Failed to update profile.";
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| CHANGE PASSWORD
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['change_password'])
) {

    $current_password =
        $_POST['current_password'];

    $new_password =
        $_POST['new_password'];

    $confirm_password =
        $_POST['confirm_password'];

    if (
        empty($current_password) ||
        empty($new_password)
    ) {

        $error =
            "All password fields are required.";

    } elseif (
        $new_password !== $confirm_password
    ) {

        $error =
            "Passwords do not match.";

    } elseif (
        strlen($new_password) < 8
    ) {

        $error =
            "Password must be at least 8 characters.";

    } else {

        $stmt = $conn->prepare("
            SELECT password
            FROM users
            WHERE id=?
        ");

        $stmt->bind_param(
            "i",
            $agent_id
        );

        $stmt->execute();

        $row =
            $stmt->get_result()
                 ->fetch_assoc();

        if (
            !password_verify(
                $current_password,
                $row['password']
            )
        ) {

            $error =
                "Current password incorrect.";

        } else {

            $hash =
                password_hash(
                    $new_password,
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
                $hash,
                $agent_id
            );

            $update->execute();

            $success =
                "Password changed successfully.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| REFERRAL STATS
|--------------------------------------------------------------------------
*/
$stats = $conn->prepare("
    SELECT COUNT(*) total
    FROM agent_referrals
    WHERE agent_id=?
");

$stats->bind_param(
    "i",
    $agent_id
);

$stats->execute();

$totalReferrals =
    $stats->get_result()
          ->fetch_assoc()['total'];

$referralCode =
    $user['referral_code']
    ?: 'AGENT'.$agent_id;

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Agent Profile</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">

<style>

body{
    background:#f5f5f5;
}

.card-box{
    background:#fff;
    border-radius:12px;
    padding:20px;
}

.profile-photo{
    width:120px;
    height:120px;
    border-radius:50%;
    object-fit:cover;
}

</style>

</head>

<body>

<div class="container py-4">

<h2 class="mb-4">
My Profile
</h2>

<?php if($success): ?>
<div class="alert alert-success">
<?= $success ?>
</div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger">
<?= $error ?>
</div>
<?php endif; ?>

<div class="row">

<div class="col-md-4">

<div class="card-box shadow-sm text-center">

<?php if(!empty($user['profile_photo'])): ?>

<img
src="../uploads/profiles/<?= htmlspecialchars($user['profile_photo']) ?>"
class="profile-photo">

<?php else: ?>

<img
src="https://via.placeholder.com/120"
class="profile-photo">

<?php endif; ?>

<h4 class="mt-3">
<?= htmlspecialchars($user['full_name']) ?>
</h4>

<p>
<?= htmlspecialchars($user['email']) ?>
</p>

<hr>

<p>
<strong>Referral Code:</strong><br>
<?= htmlspecialchars($referralCode) ?>
</p>

<p>
<strong>Total Referrals:</strong><br>
<?= number_format($totalReferrals) ?>
</p>

</div>

</div>

<div class="col-md-8">

<div class="card-box shadow-sm mb-4">

<h5>Update Profile</h5>

<form method="POST"
      enctype="multipart/form-data">

<input type="hidden"
       name="update_profile"
       value="1">

<div class="mb-3">

<label>Full Name</label>

<input type="text"
       name="full_name"
       class="form-control"
       value="<?= htmlspecialchars($user['full_name']) ?>"
       required>

</div>

<div class="mb-3">

<label>Phone</label>

<input type="text"
       name="phone"
       class="form-control"
       value="<?= htmlspecialchars($user['phone']) ?>">

</div>

<div class="mb-3">

<label>Profile Photo</label>

<input type="file"
       name="profile_photo"
       class="form-control">

</div>

<button class="btn btn-primary">
Save Changes
</button>

</form>

</div>

<div class="card-box shadow-sm">

<h5>Change Password</h5>

<form method="POST">

<input type="hidden"
       name="change_password"
       value="1">

<div class="mb-3">

<label>Current Password</label>

<input type="password"
       name="current_password"
       class="form-control"
       required>

</div>

<div class="mb-3">

<label>New Password</label>

<input type="password"
       name="new_password"
       class="form-control"
       required>

</div>

<div class="mb-3">

<label>Confirm Password</label>

<input type="password"
       name="confirm_password"
       class="form-control"
       required>

</div>

<button class="btn btn-success">
Change Password
</button>

</form>

</div>

</div>

</div>

</div>

</body>
</html>