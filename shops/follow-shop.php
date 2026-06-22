<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user_id = (int) $_SESSION['user_id'];

$slug = $_GET['shop'] ?? '';

if (empty($slug)) {
    die("Invalid request.");
}

/*
|--------------------------------------------------------------------------
| FETCH SHOP
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT id, followers
    FROM shops
    WHERE shop_slug = ?
    LIMIT 1
");

$stmt->bind_param("s", $slug);
$stmt->execute();

$shop = $stmt->get_result()->fetch_assoc();

if (!$shop) {
    die("Shop not found.");
}

$shop_id = (int) $shop['id'];

/*
|--------------------------------------------------------------------------
| CHECK IF ALREADY FOLLOWING
|--------------------------------------------------------------------------
*/
$check = $conn->prepare("
    SELECT id
    FROM shop_follows
    WHERE shop_id = ?
    AND user_id = ?
    LIMIT 1
");

$check->bind_param("ii", $shop_id, $user_id);
$check->execute();

$existing = $check->get_result()->fetch_assoc();

/*
|--------------------------------------------------------------------------
| TOGGLE FOLLOW / UNFOLLOW
|--------------------------------------------------------------------------
*/
if ($existing) {

    /*
    |--------------------------------------------------------------------------
    | UNFOLLOW
    |--------------------------------------------------------------------------
    */
    $delete = $conn->prepare("
        DELETE FROM shop_follows
        WHERE shop_id = ?
        AND user_id = ?
    ");

    $delete->bind_param("ii", $shop_id, $user_id);
    $delete->execute();

    /*
    |--------------------------------------------------------------------------
    | DECREMENT FOLLOWERS (SAFE FLOOR AT 0)
    |--------------------------------------------------------------------------
    */
    $update = $conn->prepare("
        UPDATE shops
        SET followers = GREATEST(followers - 1, 0)
        WHERE id = ?
    ");

    $update->bind_param("i", $shop_id);
    $update->execute();

    header("Location: shop-profile.php?shop=" . urlencode($slug) . "&followed=0");
    exit;

} else {

    /*
    |--------------------------------------------------------------------------
    | FOLLOW
    |--------------------------------------------------------------------------
    */
    $insert = $conn->prepare("
        INSERT INTO shop_follows (
            shop_id,
            user_id,
            created_at
        )
        VALUES (?,?,NOW())
    ");

    $insert->bind_param("ii", $shop_id, $user_id);
    $insert->execute();

    /*
    |--------------------------------------------------------------------------
    | INCREMENT FOLLOWERS
    |--------------------------------------------------------------------------
    */
    $update = $conn->prepare("
        UPDATE shops
        SET followers = followers + 1
        WHERE id = ?
    ");

    $update->bind_param("i", $shop_id);
    $update->execute();

    header("Location: shop-profile.php?shop=" . urlencode($slug) . "&followed=1");
    exit;
}