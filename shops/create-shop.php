<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

/*
|--------------------------------------------------------------------------
| Only sellers allowed to create shop
|--------------------------------------------------------------------------
*/
$user_id = (int) $_SESSION['user_id'];

$error = "";
$success = "";

/*
|--------------------------------------------------------------------------
| Check if seller already has a shop
|--------------------------------------------------------------------------
*/
$check = $conn->prepare("
    SELECT id
    FROM shops
    WHERE seller_id = ?
    LIMIT 1
");

$check->bind_param("i", $user_id);
$check->execute();

$existing = $check->get_result()->fetch_assoc();

if ($existing) {
    header("Location: ../sellers/dashboard.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| Slug generator
|--------------------------------------------------------------------------
*/
function slugify($text)
{
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

/*
|--------------------------------------------------------------------------
| CREATE SHOP
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === "POST") {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid request.");
    }

    $shop_name = trim($_POST['shop_name']);
    $description = trim($_POST['description']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $region = trim($_POST['region']);

    if (empty($shop_name)) {
        $error = "Shop name is required.";
    } else {

        /*
        |--------------------------------------------------------------------------
        | Logo Upload
        |--------------------------------------------------------------------------
        */
        $logo = null;

        if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === 0) {

            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $error = "Invalid logo format.";
            } else {

                $logo = time() . "_logo_" . uniqid() . "." . $ext;

                move_uploaded_file(
                    $_FILES['logo']['tmp_name'],
                    "../uploads/shops/" . $logo
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Banner Upload
        |--------------------------------------------------------------------------
        */
        $banner = null;

        if (!empty($_FILES['banner']['name']) && $_FILES['banner']['error'] === 0) {

            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $error = "Invalid banner format.";
            } else {

                $banner = time() . "_banner_" . uniqid() . "." . $ext;

                move_uploaded_file(
                    $_FILES['banner']['tmp_name'],
                    "../uploads/shops/" . $banner
                );
            }
        }

        if (empty($error)) {

            /*
            |--------------------------------------------------------------------------
            | Slug handling (unique-safe approach)
            |--------------------------------------------------------------------------
            */
            $base_slug = slugify($shop_name);
            $slug = $base_slug;

            $i = 1;

            $slugCheck = $conn->prepare("
                SELECT id
                FROM shops
                WHERE shop_slug = ?
                LIMIT 1
            ");

            while (true) {

                $slugCheck->bind_param("s", $slug);
                $slugCheck->execute();

                $exists = $slugCheck->get_result()->fetch_assoc();

                if (!$exists) break;

                $slug = $base_slug . "-" . $i;
                $i++;
            }

            /*
            |--------------------------------------------------------------------------
            | Insert shop
            |--------------------------------------------------------------------------
            */
            $stmt = $conn->prepare("
                INSERT INTO shops (
                    seller_id,
                    shop_name,
                    shop_slug,
                    slug,
                    logo,
                    banner,
                    description,
                    address,
                    city,
                    region,
                    status,
                    verified,
                    followers,
                    views,
                    created_at
                )
                VALUES (
                    ?,?,?,?,?,?,?,?,?,?,'pending',0,0,0,NOW()
                )
            ");

            $stmt->bind_param(
                "isssssssss",
                $user_id,
                $shop_name,
                $slug,
                $slug,
                $logo,
                $banner,
                $description,
                $address,
                $city,
                $region
            );

            if ($stmt->execute()) {

                $success = "Shop created successfully. Awaiting approval.";

                header("Location: ../sellers/dashboard.php");
                exit;

            } else {
                $error = "Failed to create shop.";
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

<title>Create Shop | Karliakoo</title>

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
    max-width:600px;
}

</style>

</head>

<body>

<div class="card-box shadow">

<h3 class="mb-3 text-center">Create Your Shop</h3>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="row">

<div class="col-md-12 mb-3">
    <label>Shop Name</label>
    <input type="text" name="shop_name" class="form-control" required>
</div>

<div class="col-md-6 mb-3">
    <label>City</label>
    <input type="text" name="city" class="form-control">
</div>

<div class="col-md-6 mb-3">
    <label>Region</label>
    <input type="text" name="region" class="form-control">
</div>

<div class="col-md-12 mb-3">
    <label>Address</label>
    <textarea name="address" class="form-control"></textarea>
</div>

<div class="col-md-12 mb-3">
    <label>Description</label>
    <textarea name="description" class="form-control"></textarea>
</div>

<div class="col-md-6 mb-3">
    <label>Logo</label>
    <input type="file" name="logo" class="form-control">
</div>

<div class="col-md-6 mb-3">
    <label>Banner</label>
    <input type="file" name="banner" class="form-control">
</div>

</div>

<button class="btn btn-primary w-100">
    Create Shop
</button>

</form>

</div>

</body>
</html>