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
    "Invalid address.";

    header("Location: addresses.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| VERIFY ADDRESS OWNERSHIP
|--------------------------------------------------------------------------
*/

$stmt =
$conn->prepare("
    SELECT
        id,
        is_default
    FROM addresses
    WHERE id=?
    AND user_id=?
    LIMIT 1
");

$stmt->bind_param(
    "ii",
    $addressId,
    $userId
);

$stmt->execute();

$address =
$stmt
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
| DELETE ADDRESS
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try
{
    $delete =
    $conn->prepare("
        DELETE FROM addresses
        WHERE id=?
        AND user_id=?
        LIMIT 1
    ");

    $delete->bind_param(
        "ii",
        $addressId,
        $userId
    );

    $delete->execute();

    /*
    |--------------------------------------------------------------------------
    | IF DEFAULT ADDRESS WAS DELETED,
    | ASSIGN NEW DEFAULT ADDRESS
    |--------------------------------------------------------------------------
    */

    if ((int)$address['is_default'] === 1)
    {
        $nextAddress =
        $conn->prepare("
            SELECT id
            FROM addresses
            WHERE user_id=?
            ORDER BY id ASC
            LIMIT 1
        ");

        $nextAddress->bind_param(
            "i",
            $userId
        );

        $nextAddress->execute();

        $next =
        $nextAddress
        ->get_result()
        ->fetch_assoc();

        if ($next)
        {
            $setDefault =
            $conn->prepare("
                UPDATE addresses
                SET is_default=1
                WHERE id=?
                LIMIT 1
            ");

            $setDefault->bind_param(
                "i",
                $next['id']
            );

            $setDefault->execute();
        }
    }

    $conn->commit();

    $_SESSION['success'] =
    "Address deleted successfully.";
}
catch (Exception $e)
{
    $conn->rollback();

    $_SESSION['error'] =
    "Failed to delete address.";
}

header("Location: addresses.php");
exit;
?>
