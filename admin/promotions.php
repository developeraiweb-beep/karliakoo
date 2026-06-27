<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$admin = currentUser();

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function clean($v): string {
    return trim((string)$v);
}

/*
|--------------------------------------------------------------------------
| IMAGE UPLOAD
|--------------------------------------------------------------------------
*/
function uploadPromoImage($file): ?string
{
    if (empty($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Upload failed");
    }

    $allowed = ['jpg','jpeg','png','webp'];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        throw new Exception("Invalid image type");
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("Image too large");
    }

    if (!getimagesize($file['tmp_name'])) {
        throw new Exception("Invalid image");
    }

    $dir = dirname(__DIR__) . "/uploads/promotions/";

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $name = "promo_" . bin2hex(random_bytes(16)) . "." . $ext;

    $path = $dir . $name;

    move_uploaded_file($file['tmp_name'], $path);

    return "uploads/promotions/" . $name;
}

/*
|--------------------------------------------------------------------------
| AUDIT LOG
|--------------------------------------------------------------------------
*/
function audit($conn, $adminId, $action, $description)
{
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (user_id, action, table_name, created_at)
        VALUES (?, ?, ?, NOW())
    ");

    $table = "homepage_promotions";

    $stmt->bind_param(
        "iss",
        $adminId,
        $action,
        $table
    );

    $stmt->execute();
}

/*
|--------------------------------------------------------------------------
| CREATE PROMOTION
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {

    try {

        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            throw new Exception("Invalid CSRF");
        }

        $conn->begin_transaction();

        $product_id = (int)$_POST['product_id'];
        $title = clean($_POST['title']);
        $subtitle = clean($_POST['subtitle']);
        $badge = clean($_POST['badge']);
        $button_text = clean($_POST['button_text']);
        $bg = clean($_POST['background_color']);
        $text = clean($_POST['text_color']);
        $order = (int)$_POST['display_order'];

        $image = uploadPromoImage($_FILES['image'] ?? null);

        $stmt = $conn->prepare("
            INSERT INTO homepage_promotions
            (product_id,title,subtitle,badge,button_text,background_color,text_color,image,display_order,active)
            VALUES (?,?,?,?,?,?,?,?,?,1)
        ");

        $stmt->bind_param(
            "isssssssi",
            $product_id,
            $title,
            $subtitle,
            $badge,
            $button_text,
            $bg,
            $text,
            $image,
            $order
        );

        $stmt->execute();

        audit($conn, $admin['id'], "create_promotion", $title);

        $conn->commit();

        $message = "Promotion created successfully";

    } catch (Throwable $e) {

        $conn->rollback();

        $error = $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| DELETE PROMOTION
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete'])) {

    $id = (int)$_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM homepage_promotions WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    audit($conn, $admin['id'], "delete_promotion", "ID $id");

    header("Location: promotions.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| TOGGLE STATUS
|--------------------------------------------------------------------------
*/
if (isset($_GET['toggle'])) {

    $id = (int)$_GET['toggle'];

    $conn->query("
        UPDATE homepage_promotions
        SET active = 1 - active
        WHERE id = $id
    ");

    audit($conn, $admin['id'], "toggle_promotion", "ID $id");

    header("Location: promotions.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| DATA
|--------------------------------------------------------------------------
*/
$products = $conn->query("SELECT id,name FROM products ORDER BY name");
$promos = $conn->query("
    SELECT p.*, pr.name as product_name
    FROM homepage_promotions p
    JOIN products pr ON pr.id = p.product_id
    ORDER BY p.id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Promotions</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-4">

<h3>Homepage Promotions</h3>

<?php if(!empty($message)): ?>
<div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>

<?php if(!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<!-- CREATE FORM -->
<form method="POST" enctype="multipart/form-data" class="card p-3 mb-4">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="row g-2">

<div class="col-md-4">
<select name="product_id" class="form-control" required>
<option value="">Select Product</option>
<?php while($p=$products->fetch_assoc()): ?>
<option value="<?= $p['id'] ?>"><?= $p['name'] ?></option>
<?php endwhile; ?>
</select>
</div>

<div class="col-md-4">
<input name="title" class="form-control" placeholder="Title" required>
</div>

<div class="col-md-4">
<input name="subtitle" class="form-control" placeholder="Subtitle">
</div>

<div class="col-md-3">
<input name="badge" class="form-control" placeholder="Badge">
</div>

<div class="col-md-3">
<input name="button_text" class="form-control" value="Shop Now">
</div>

<div class="col-md-2">
<input name="background_color" class="form-control" value="#0d6efd">
</div>

<div class="col-md-2">
<input name="text_color" class="form-control" value="#ffffff">
</div>

<div class="col-md-2">
<input type="number" name="display_order" class="form-control" value="0">
</div>

<div class="col-md-6">
<input type="file" name="image" class="form-control">
</div>

<div class="col-md-6">
<button name="create" class="btn btn-primary w-100">
Create Promotion
</button>
</div>

</div>

</form>

<!-- LIST -->
<div class="card p-3">

<table class="table table-bordered">

<tr>
<th>ID</th>
<th>Product</th>
<th>Title</th>
<th>Status</th>
<th>Actions</th>
</tr>

<?php while($r=$promos->fetch_assoc()): ?>

<tr>

<td><?= $r['id'] ?></td>
<td><?= $r['product_name'] ?></td>
<td><?= $r['title'] ?></td>

<td>
<?= $r['active'] ? 'Active' : 'Inactive' ?>
</td>

<td>

<a href="?toggle=<?= $r['id'] ?>" class="btn btn-sm btn-warning">
Toggle
</a>

<a href="?delete=<?= $r['id'] ?>" class="btn btn-sm btn-danger">
Delete
</a>

</td>

</tr>

<?php endwhile; ?>

</table>

</div>

</div>

</body>
</html>

