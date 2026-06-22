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

$addressId =
(int)($_GET['id'] ?? 0);

if ($addressId <= 0)
{
    $_SESSION['error'] =
    "Invalid address selected.";

    header("Location: addresses.php");
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
    FROM addresses
    WHERE id=?
    AND user_id=?
    LIMIT 1
");

$checkStmt->bind_param(
    "ii",
    $addressId,
    $userId
);

$checkStmt->execute();

$address =
$checkStmt
->get_result()
->fetch_assoc();

if (!$address)
{
    $_SESSION['error'] =
    "Address not found.";

    header("Location: addresses.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| SET DEFAULT ADDRESS
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try
{
    /*
    | Remove current default
    */

    $resetStmt =
    $conn->prepare("
        UPDATE addresses
        SET is_default=0
        WHERE user_id=?
    ");

    $resetStmt->bind_param(
        "i",
        $userId
    );

    $resetStmt->execute();

    /*
    | Set selected address
    */

    $defaultStmt =
    $conn->prepare("
        UPDATE addresses
        SET is_default=1
        WHERE id=?
        AND user_id=?
        LIMIT 1
    ");

    $defaultStmt->bind_param(
        "ii",
        $addressId,
        $userId
    );

    $defaultStmt->execute();

    $conn->commit();

    $_SESSION['success'] =
    "Default address updated successfully.";
}
catch(Exception $e)
{
    $conn->rollback();

    $_SESSION['error'] =
    "Failed to update default address.";
}

header("Location: addresses.php");
exit;
?>
