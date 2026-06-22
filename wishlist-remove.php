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

$wishlistId =
(int)($_GET['id'] ?? 0);

if ($wishlistId <= 0)
{
    $_SESSION['error'] =
    "Invalid wishlist item.";

    header("Location: wishlist.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| VERIFY OWNERSHIP
|--------------------------------------------------------------------------
*/

$checkStmt =
$conn->prepare("
    SELECT id
    FROM wishlists
    WHERE id=?
    AND user_id=?
    LIMIT 1
");

$checkStmt->bind_param(
    "ii",
    $wishlistId,
    $userId
);

$checkStmt->execute();

$item =
$checkStmt
->get_result()
->fetch_assoc();

if (!$item)
{
    $_SESSION['error'] =
    "Wishlist item not found.";

    header("Location: wishlist.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE ITEM
|--------------------------------------------------------------------------
*/

$deleteStmt =
$conn->prepare("
    DELETE FROM wishlists
    WHERE id=?
    AND user_id=?
    LIMIT 1
");

$deleteStmt->bind_param(
    "ii",
    $wishlistId,
    $userId
);

if ($deleteStmt->execute())
{
    $_SESSION['success'] =
    "Item removed from wishlist.";
}
else
{
    $_SESSION['error'] =
    "Failed to remove wishlist item.";
}

header("Location: wishlist.php");
exit;
?>
