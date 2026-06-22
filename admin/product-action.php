<?php

declare(strict_types=1);

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();
requireRole(['admin']);

$admin = currentUser();

$productId = (int)($_GET['id'] ?? 0);
$action    = trim($_GET['action'] ?? '');

if ($productId <= 0 || empty($action))
{
    $_SESSION['error'] = "Invalid request.";
    header("Location: products.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD PRODUCT
|--------------------------------------------------------------------------
*/

$productStmt = $conn->prepare("
    SELECT
        id,
        name,
        status,
        approved,
        featured,
        image
    FROM products
    WHERE id = ?
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
    $_SESSION['error'] = "Product not found.";
    header("Location: products.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function writeAuditLog(
    mysqli $conn,
    int $userId,
    string $action,
    string $description
): void
{
    $stmt = $conn->prepare("
        INSERT INTO audit_logs
        (
            user_id,
            action,
            description,
            created_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            NOW()
        )
    ");

    if($stmt)
    {
        $stmt->bind_param(
            "iss",
            $userId,
            $action,
            $description
        );

        $stmt->execute();
    }
}

/*
|--------------------------------------------------------------------------
| PROCESS ACTIONS
|--------------------------------------------------------------------------
*/

try
{
    $conn->begin_transaction();

    switch($action)
    {

        /*
        |--------------------------------------------------------------------------
        | APPROVE PRODUCT
        |--------------------------------------------------------------------------
        */
        case "approve":

            $stmt = $conn->prepare("
                UPDATE products
                SET approved = 1
                WHERE id = ?
            ");

            $stmt->bind_param(
                "i",
                $productId
            );

            $stmt->execute();

            writeAuditLog(
                $conn,
                (int)$admin['id'],
                "approve_product",
                "Approved product #" .
                $productId .
                " (" .
                $product['name'] .
                ")"
            );

            $_SESSION['success'] =
            "Product approved successfully.";

        break;

        /*
        |--------------------------------------------------------------------------
        | ACTIVATE PRODUCT
        |--------------------------------------------------------------------------
        */
        case "activate":

            $stmt = $conn->prepare("
                UPDATE products
                SET status='active'
                WHERE id=?
            ");

            $stmt->bind_param(
                "i",
                $productId
            );

            $stmt->execute();

            writeAuditLog(
                $conn,
                (int)$admin['id'],
                "activate_product",
                "Activated product #" .
                $productId
            );

            $_SESSION['success'] =
            "Product activated.";

        break;

        /*
        |--------------------------------------------------------------------------
        | DISABLE PRODUCT
        |--------------------------------------------------------------------------
        */
        case "disable":

            $stmt = $conn->prepare("
                UPDATE products
                SET status='inactive'
                WHERE id=?
            ");

            $stmt->bind_param(
                "i",
                $productId
            );

            $stmt->execute();

            writeAuditLog(
                $conn,
                (int)$admin['id'],
                "disable_product",
                "Disabled product #" .
                $productId
            );

            $_SESSION['success'] =
            "Product disabled.";

        break;

        /*
        |--------------------------------------------------------------------------
        | FEATURE PRODUCT
        |--------------------------------------------------------------------------
        */
        case "feature":

            $stmt = $conn->prepare("
                UPDATE products
                SET featured=1
                WHERE id=?
            ");

            $stmt->bind_param(
                "i",
                $productId
            );

            $stmt->execute();

            writeAuditLog(
                $conn,
                (int)$admin['id'],
                "feature_product",
                "Featured product #" .
                $productId
            );

            $_SESSION['success'] =
            "Product marked as featured.";

        break;

        /*
        |--------------------------------------------------------------------------
        | REMOVE FEATURE
        |--------------------------------------------------------------------------
        */
        case "unfeature":

            $stmt = $conn->prepare("
                UPDATE products
                SET featured=0
                WHERE id=?
            ");

            $stmt->bind_param(
                "i",
                $productId
            );

            $stmt->execute();

            writeAuditLog(
                $conn,
                (int)$admin['id'],
                "unfeature_product",
                "Removed featured status from product #" .
                $productId
            );

            $_SESSION['success'] =
            "Featured status removed.";

        break;

        /*
        |--------------------------------------------------------------------------
        | DELETE PRODUCT
        |--------------------------------------------------------------------------
        */
        case "delete":

            /*
            |----------------------------------------------------------
            | DELETE IMAGE
            |----------------------------------------------------------
            */

            if(
                !empty($product['image'])
                &&
                file_exists(
                    "../" . $product['image']
                )
            )
            {
                @unlink(
                    "../" . $product['image']
                );
            }

            /*
            |----------------------------------------------------------
            | DELETE PRODUCT IMAGES
            |----------------------------------------------------------
            */

            $gallery =
            $conn->prepare("
                SELECT image
                FROM product_images
                WHERE product_id = ?
            ");

            if($gallery)
            {
                $gallery->bind_param(
                    "i",
                    $productId
                );

                $gallery->execute();

                $images =
                $gallery
                ->get_result();

                while(
                    $img =
                    $images->fetch_assoc()
                )
                {
                    if(
                        !empty($img['image'])
                        &&
                        file_exists(
                            "../" .
                            $img['image']
                        )
                    )
                    {
                        @unlink(
                            "../" .
                            $img['image']
                        );
                    }
                }
            }

            /*
            |----------------------------------------------------------
            | DELETE RELATED RECORDS
            |----------------------------------------------------------
            */

            $tables = [

                "product_images",
                "product_variants",
                "product_specifications",
                "reviews",
                "wishlist",
                "cart",
                "recently_viewed"

            ];

            foreach($tables as $table)
            {
                $sql =
                "DELETE FROM {$table}
                 WHERE product_id=?";

                $delete =
                $conn->prepare($sql);

                if($delete)
                {
                    $delete->bind_param(
                        "i",
                        $productId
                    );

                    $delete->execute();
                }
            }

            /*
            |----------------------------------------------------------
            | DELETE PRODUCT
            |----------------------------------------------------------
            */

            $deleteProduct =
            $conn->prepare("
                DELETE FROM products
                WHERE id=?
            ");

            $deleteProduct->bind_param(
                "i",
                $productId
            );

            $deleteProduct->execute();

            writeAuditLog(
                $conn,
                (int)$admin['id'],
                "delete_product",
                "Deleted product #" .
                $productId .
                " (" .
                $product['name'] .
                ")"
            );

            $_SESSION['success'] =
            "Product deleted successfully.";

        break;

        default:

            throw new Exception(
                "Invalid action."
            );

    }

    $conn->commit();
}
catch(Exception $e)
{
    $conn->rollback();

    $_SESSION['error'] =
    $e->getMessage();
}

/*
|--------------------------------------------------------------------------
| REDIRECT
|--------------------------------------------------------------------------
*/

header(
    "Location: products.php"
);

exit;