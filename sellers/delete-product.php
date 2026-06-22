<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

if (!$user)
{
    header("Location: ../login.php");
    exit;
}

$sellerId = (int)$user['id'];

/*
|--------------------------------------------------------------------------
| CSRF CHECK
|--------------------------------------------------------------------------
*/

if (
    empty($_GET['token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $_GET['token']
    )
)
{
    $_SESSION['error'] =
    "Invalid security token.";

    header(
        "Location: products.php"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| PRODUCT ID
|--------------------------------------------------------------------------
*/

$productId =
(int)($_GET['id'] ?? 0);

if ($productId <= 0)
{
    $_SESSION['error'] =
    "Invalid product.";

    header(
        "Location: products.php"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| VERIFY SHOP
|--------------------------------------------------------------------------
*/

$shopStmt = $conn->prepare("
    SELECT
        id,
        status,
        suspended
    FROM shops
    WHERE seller_id = ?
    LIMIT 1
");

$shopStmt->bind_param(
    "i",
    $sellerId
);

$shopStmt->execute();

$shop =
$shopStmt
->get_result()
->fetch_assoc();

if (!$shop)
{
    $_SESSION['error'] =
    "Shop not found.";

    header(
        "Location: products.php"
    );
    exit;
}

if (
    $shop['status'] !== 'approved'
)
{
    $_SESSION['error'] =
    "Shop not approved.";

    header(
        "Location: products.php"
    );
    exit;
}

if (
    (int)$shop['suspended'] === 1
)
{
    $_SESSION['error'] =
    "Shop suspended.";

    header(
        "Location: products.php"
    );
    exit;
}

$shopId =
(int)$shop['id'];

/*
|--------------------------------------------------------------------------
| VERIFY PRODUCT OWNERSHIP
|--------------------------------------------------------------------------
*/

$productStmt = $conn->prepare("
    SELECT
        id,
        name,
        image
    FROM products
    WHERE id = ?
    AND shop_id = ?
    AND deleted_at IS NULL
    LIMIT 1
");

$productStmt->bind_param(
    "ii",
    $productId,
    $shopId
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

    header(
        "Location: products.php"
    );
    exit;
}

try
{
    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | SOFT DELETE
    |--------------------------------------------------------------------------
    */

    $deleteStmt = $conn->prepare("
        UPDATE products
        SET

            deleted_at = NOW(),
            status = 'inactive',
            updated_at = NOW()

        WHERE id = ?
        LIMIT 1
    ");

    $deleteStmt->bind_param(
        "i",
        $productId
    );

    if (
        !$deleteStmt->execute()
    )
    {
        throw new Exception(
            $deleteStmt->error
        );
    }

    /*
    |--------------------------------------------------------------------------
    | OPTIONAL AUDIT LOG
    |--------------------------------------------------------------------------
    */

    $auditExists = $conn->query("
        SHOW TABLES LIKE 'audit_logs'
    ");

    if (
        $auditExists &&
        $auditExists->num_rows > 0
    )
    {
        $auditStmt = $conn->prepare("
            INSERT INTO audit_logs (

                user_id,
                action,
                description,
                created_at

            )

            VALUES (

                ?,
                'DELETE_PRODUCT',
                ?,
                NOW()

            )
        ");

        $description =
        "Deleted product: " .
        $product['name'];

        $auditStmt->bind_param(
            "is",
            $sellerId,
            $description
        );

        $auditStmt->execute();
    }

    $conn->commit();

    $_SESSION['success'] =
    "Product deleted successfully.";
}
catch(Exception $e)
{
    $conn->rollback();

    $_SESSION['error'] =
    "Delete failed: " .
    $e->getMessage();
}

header(
    "Location: products.php"
);

exit;