<?php

declare(strict_types=1);

session_start();

require_once "config/db.php";

if (!isset($_SESSION['user_id']))
{
    $_SESSION['error'] =
    "Please login first.";

    header("Location: login.php");
    exit;
}

$userId =
(int)$_SESSION['user_id'];

$productId =
(int)($_GET['id'] ?? 0);

if ($productId <= 0)
{
    $_SESSION['error'] =
    "Invalid product.";

    header("Location: products.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| VERIFY PRODUCT EXISTS
|--------------------------------------------------------------------------
*/

$productStmt =
$conn->prepare("
    SELECT
        id,
        name
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
    $_SESSION['error'] =
    "Product not found.";

    header("Location: products.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CHECK DUPLICATE
|--------------------------------------------------------------------------
*/

$checkStmt =
$conn->prepare("
    SELECT id
    FROM wishlists
    WHERE user_id=?
    AND product_id=?
    LIMIT 1
");

$checkStmt->bind_param(
    "ii",
    $userId,
    $productId
);

$checkStmt->execute();

$exists =
$checkStmt
->get_result()
->fetch_assoc();

if ($exists)
{
    $_SESSION['info'] =
    "Product already exists in your wishlist.";
}
else
{
    $insertStmt =
    $conn->prepare("
        INSERT INTO wishlists
        (
            user_id,
            product_id
        )
        VALUES
        (
            ?,?
        )
    ");

    $insertStmt->bind_param(
        "ii",
        $userId,
        $productId
    );

    if ($insertStmt->execute())
    {
        $_SESSION['success'] =
        "Added to wishlist successfully.";
    }
    else
    {
        $_SESSION['error'] =
        "Failed to add wishlist item.";
    }
}

/*
|--------------------------------------------------------------------------
| REDIRECT BACK
|--------------------------------------------------------------------------
*/

$redirect =
$_SERVER['HTTP_REFERER']
?? 'products.php';

header(
    "Location: " .
    $redirect
);

exit;
?>
