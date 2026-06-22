<?php

declare(strict_types=1);

session_start();

require_once "config/db.php";

if (!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

$userId =
(int)$_SESSION['user_id'];

$productId =
(int)($_GET['product_id'] ?? 0);

if ($productId <= 0)
{
    die("Invalid product.");
}

/*
|--------------------------------------------------------------------------
| LOAD PRODUCT
|--------------------------------------------------------------------------
*/

$productStmt =
$conn->prepare("
    SELECT
        id,
        name,
        slug
    FROM products
    WHERE id=?
    AND approved=1
    AND status='active'
    LIMIT 1
");

$productStmt->bind_param(
    "i",
    $productId
);

$productStmt->execute();

$product =
$productStmt
->get_result()
->fetch_assoc();

if (!$product)
{
    die("Product not found.");
}

/*
|--------------------------------------------------------------------------
| CHECK EXISTING REVIEW
|--------------------------------------------------------------------------
*/

$reviewStmt =
$conn->prepare("
    SELECT id
    FROM reviews
    WHERE product_id=?
    AND user_id=?
    LIMIT 1
");

$reviewStmt->bind_param(
    "ii",
    $productId,
    $userId
);

$reviewStmt->execute();

$existingReview =
$reviewStmt
->get_result()
->fetch_assoc();

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| SUBMIT REVIEW
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    if ($existingReview)
    {
        $error =
        "You have already reviewed this product.";
    }
    else
    {
        $rating =
        (int)($_POST['rating'] ?? 0);

        $review =
        trim($_POST['review'] ?? '');

        if (
            $rating < 1 ||
            $rating > 5
        )
        {
            $error =
            "Please select a valid rating.";
        }
        else
        {
            $insert =
            $conn->prepare("
                INSERT INTO reviews
                (
                    product_id,
                    user_id,
                    rating,
                    review
                )
                VALUES
                (
                    ?,?,?,?
                )
            ");

            $insert->bind_param(
                "iiis",
                $productId,
                $userId,
                $rating,
                $review
            );

            if ($insert->execute())
            {
                $success =
                "Review submitted successfully.";
            }
            else
            {
                $error =
                "Failed to submit review.";
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

<title>

Submit Review

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

<div class="col-lg-8">

<div class="card shadow-sm">

<div class="card-header">

<h4 class="mb-0">

Review Product

</h4>

</div>

<div class="card-body">

<h5>

<?= htmlspecialchars(
$product['name']
) ?>

</h5>

<hr>

<?php if($success): ?>

<div class="alert alert-success">

<?= htmlspecialchars($success) ?>

</div>

<div class="mt-3">

<a
href="product-details.php?id=<?= $productId ?>"
class="btn btn-primary">

Back To Product

</a>

</div>

<?php elseif($existingReview): ?>

<div class="alert alert-warning">

You have already reviewed this product.

</div>

<a
href="product-details.php?id=<?= $productId ?>"
class="btn btn-secondary">

Back

</a>

<?php else: ?>

<?php if($error): ?>

<div class="alert alert-danger">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>

<form method="POST">

<div class="mb-3">

<label class="form-label">

Rating

</label>

<select
name="rating"
class="form-select"
required>

<option value="">

Select Rating

</option>

<option value="5">

★★★★★ Excellent

</option>

<option value="4">

★★★★ Very Good

</option>

<option value="3">

★★★ Good

</option>

<option value="2">

★★ Fair

</option>

<option value="1">

★ Poor

</option>

</select>

</div>

<div class="mb-4">

<label class="form-label">

Review

</label>

<textarea
name="review"
rows="5"
class="form-control"
placeholder="Share your experience..."></textarea>

</div>

<div class="d-flex gap-2">

<button
type="submit"
class="btn btn-primary">

Submit Review

</button>

<a
href="product-details.php?id=<?= $productId ?>"
class="btn btn-secondary">

Cancel

</a>

</div>

</form>

<?php endif; ?>

</div>

</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
