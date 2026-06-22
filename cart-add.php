<?php

declare(strict_types=1);

session_start();

require_once "config/db.php";

/*
|--------------------------------------------------------------------------
| AUTH CHECK
|--------------------------------------------------------------------------
*/

if(!isset($_SESSION['user_id']))
{
    $_SESSION['error'] =
    "Please login first.";

    header(
        "Location: login.php"
    );
    exit;
}

$userId =
(int)$_SESSION['user_id'];

$productId =
(int)($_POST['product_id'] ?? 0);

$quantity =
max(
    1,
    (int)($_POST['quantity'] ?? 1)
);

if($productId <= 0)
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
| LOAD PRODUCT
|--------------------------------------------------------------------------
*/

$stmt =
$conn->prepare("
SELECT
id,
name,
stock,
status,
approved
FROM products
WHERE id=?
LIMIT 1
");

$stmt->bind_param(
"i",
$productId
);

$stmt->execute();

$product =
$stmt
->get_result()
->fetch_assoc();

if(!$product)
{
    $_SESSION['error'] =
    "Product not found.";

    header(
        "Location: products.php"
    );
    exit;
}

if(
$product['approved'] != 1
||
$product['status'] !== 'active'
)
{
    $_SESSION['error'] =
    "Product unavailable.";

    header(
        "Location: products.php"
    );
    exit;
}

if(
(int)$product['stock'] <= 0
)
{
    $_SESSION['error'] =
    "Product out of stock.";

    header(
        "Location: product-details.php?id=" .
        $productId
    );
    exit;
}

if(
$quantity >
(int)$product['stock']
)
{
    $quantity =
    (int)$product['stock'];
}

/*
|--------------------------------------------------------------------------
| CHECK EXISTING CART ITEM
|--------------------------------------------------------------------------
*/

$cartStmt =
$conn->prepare("
SELECT
id,
quantity
FROM cart
WHERE
user_id=?
AND
product_id=?
LIMIT 1
");

$cartStmt->bind_param(
"ii",
$userId,
$productId
);

$cartStmt->execute();

$existing =
$cartStmt
->get_result()
->fetch_assoc();

/*
|--------------------------------------------------------------------------
| UPDATE OR INSERT
|--------------------------------------------------------------------------
*/

if($existing)
{
    $newQty =
    (int)$existing['quantity']
    +
    $quantity;

    if(
        $newQty >
        (int)$product['stock']
    )
    {
        $newQty =
        (int)$product['stock'];
    }

    $update =
    $conn->prepare("
    UPDATE cart
    SET quantity=?
    WHERE id=?
    ");

    $update->bind_param(
    "ii",
    $newQty,
    $existing['id']
    );

    $update->execute();
}
else
{
    $insert =
    $conn->prepare("
    INSERT INTO cart
    (
        user_id,
        product_id,
        quantity
    )
    VALUES
    (
        ?,
        ?,
        ?
    )
    ");

    $insert->bind_param(
    "iii",
    $userId,
    $productId,
    $quantity
    );

    $insert->execute();
}

/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/

$_SESSION['success'] =
"Product added to cart successfully.";

/*
|--------------------------------------------------------------------------
| RETURN
|--------------------------------------------------------------------------
*/

$redirect =
$_SERVER['HTTP_REFERER']
?? 'cart.php';

header(
"Location: " .
$redirect
);

exit;