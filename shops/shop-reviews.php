<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user_id = (int) $_SESSION['user_id'];

$slug = $_GET['shop'] ?? '';

if (empty($slug)) {
    die("Shop not found.");
}

/*
|--------------------------------------------------------------------------
| FETCH SHOP
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT *
    FROM shops
    WHERE shop_slug = ?
    LIMIT 1
");

$stmt->bind_param("s", $slug);
$stmt->execute();

$shop = $stmt->get_result()->fetch_assoc();

if (!$shop) {
    die("Shop does not exist.");
}

$shop_id = $shop['id'];

$error = "";
$success = "";

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
| SUBMIT REVIEW
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === "POST") {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid request.");
    }

    $rating = (int) $_POST['rating'];
    $comment = trim($_POST['comment']);

    if ($rating < 1 || $rating > 5) {
        $error = "Rating must be between 1 and 5.";
    } else {

        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate review (1 per user per shop)
        |--------------------------------------------------------------------------
        */
        $check = $conn->prepare("
            SELECT id
            FROM shop_reviews
            WHERE shop_id = ?
            AND user_id = ?
            LIMIT 1
        ");

        $check->bind_param("ii", $shop_id, $user_id);
        $check->execute();

        $exists = $check->get_result()->fetch_assoc();

        if ($exists) {
            $error = "You already reviewed this shop.";
        } else {

            $stmt = $conn->prepare("
                INSERT INTO shop_reviews (
                    shop_id,
                    user_id,
                    rating,
                    comment,
                    created_at
                )
                VALUES (?,?,?,?,NOW())
            ");

            $stmt->bind_param(
                "iiis",
                $shop_id,
                $user_id,
                $rating,
                $comment
            );

            if ($stmt->execute()) {
                $success = "Review submitted successfully.";
            } else {
                $error = "Failed to submit review.";
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH REVIEWS
|--------------------------------------------------------------------------
*/
$reviewsStmt = $conn->prepare("
    SELECT r.*, u.full_name
    FROM shop_reviews r
    JOIN users u ON u.id = r.user_id
    WHERE r.shop_id = ?
    ORDER BY r.id DESC
");

$reviewsStmt->bind_param("i", $shop_id);
$reviewsStmt->execute();

$reviews = $reviewsStmt->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Reviews | <?= htmlspecialchars($shop['shop_name']) ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f5f5f5;
}

.card-box{
    background:white;
    border-radius:12px;
    padding:20px;
}

.star{
    color: gold;
    font-size: 18px;
}

</style>

</head>

<body>

<div class="container py-4">

<h3 class="mb-3">
    Reviews - <?= htmlspecialchars($shop['shop_name']) ?>
</h3>

<!-- REVIEW FORM -->
<div class="card-box shadow-sm mb-4">

<h5>Leave a Review</h5>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<?php if($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<form method="POST">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="mb-3">
    <label>Rating (1 - 5)</label>
    <select name="rating" class="form-control" required>
        <option value="5">★★★★★ (5)</option>
        <option value="4">★★★★ (4)</option>
        <option value="3">★★★ (3)</option>
        <option value="2">★★ (2)</option>
        <option value="1">★ (1)</option>
    </select>
</div>

<div class="mb-3">
    <label>Comment</label>
    <textarea name="comment" class="form-control"></textarea>
</div>

<button class="btn btn-primary">
    Submit Review
</button>

</form>

</div>

<!-- REVIEWS LIST -->
<div class="card-box shadow-sm">

<h5>All Reviews</h5>

<?php while($r = $reviews->fetch_assoc()): ?>

<div class="border-bottom py-3">

<strong><?= htmlspecialchars($r['full_name']) ?></strong>

<div>
    <?php for($i = 1; $i <= 5; $i++): ?>
        <span class="star">
            <?= $i <= $r['rating'] ? "★" : "☆" ?>
        </span>
    <?php endfor; ?>
</div>

<p class="mb-0">
    <?= htmlspecialchars($r['comment']) ?>
</p>

</div>

<?php endwhile; ?>

</div>

</div>

</body>
</html>