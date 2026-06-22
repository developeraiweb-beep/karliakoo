<?php

declare(strict_types=1);

session_start();

require_once "config/db.php";

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

$userId =
(int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| LOAD CART ITEMS
|--------------------------------------------------------------------------
*/

$stmt =
$conn->prepare("
SELECT

c.id cart_id,
c.quantity,

p.id product_id,
p.name,
p.price,
p.sale_price,
p.stock,
p.shop_id

FROM cart c

INNER JOIN products p
ON p.id = c.product_id

WHERE c.user_id=?
");

$stmt->bind_param(
"i",
$userId
);

$stmt->execute();

$cartItems =
$stmt->get_result();

if($cartItems->num_rows === 0)
{
    $_SESSION['error'] =
    "Your cart is empty.";

    header("Location: cart.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| USER ADDRESSES
|--------------------------------------------------------------------------
*/

$addressStmt =
$conn->prepare("
SELECT *
FROM addresses
WHERE user_id=?
ORDER BY is_default DESC,id DESC
");

$addressStmt->bind_param(
"i",
$userId
);

$addressStmt->execute();

$addresses =
$addressStmt->get_result();

/*
|--------------------------------------------------------------------------
| TOTALS
|--------------------------------------------------------------------------
*/

$cartData = [];
$subtotal = 0;

while($item = $cartItems->fetch_assoc())
{
    $price =
    !empty($item['sale_price'])
    ?
    (float)$item['sale_price']
    :
    (float)$item['price'];

    $rowTotal =
    $price *
    (int)$item['quantity'];

    $subtotal += $rowTotal;

    $cartData[] =
    [
        'product_id' => $item['product_id'],
        'shop_id'    => $item['shop_id'],
        'quantity'   => $item['quantity'],
        'price'      => $price,
        'stock'      => $item['stock']
    ];
}

$shippingFee = 5000;
$totalAmount =
$subtotal +
$shippingFee;

/*
|--------------------------------------------------------------------------
| CREATE ORDER
|--------------------------------------------------------------------------
*/

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $addressId =
    (int)($_POST['address_id'] ?? 0);

    if($addressId <= 0)
    {
        $error =
        "Please select delivery address.";
    }
    else
    {
        $orderNumber =
        "ORD-" .
        date("YmdHis") .
        rand(100,999);

        $conn->begin_transaction();

        try
        {
            /*
            --------------------------------------------------
            CREATE ORDER
            --------------------------------------------------
            */

            $orderStmt =
            $conn->prepare("
            INSERT INTO orders
            (
                order_number,
                user_id,
                address_id,
                subtotal,
                shipping_fee,
                total_amount,
                payment_status,
                order_status
            )
            VALUES
            (
                ?,?,?,?,?,?,
                'pending',
                'pending'
            )
            ");

            $orderStmt->bind_param(
            "siiddd",
            $orderNumber,
            $userId,
            $addressId,
            $subtotal,
            $shippingFee,
            $totalAmount
            );

            $orderStmt->execute();

            $orderId =
            $conn->insert_id;

            /*
            --------------------------------------------------
            ORDER ITEMS
            --------------------------------------------------
            */

            foreach($cartData as $item)
            {
                $platformFee = 0;

                $itemStmt =
                $conn->prepare("
                INSERT INTO order_items
                (
                    order_id,
                    product_id,
                    shop_id,
                    quantity,
                    price,
                    platform_fee
                )
                VALUES
                (
                    ?,?,?,?,?,?
                )
                ");

                $itemStmt->bind_param(
                "iiiidd",
                $orderId,
                $item['product_id'],
                $item['shop_id'],
                $item['quantity'],
                $item['price'],
                $platformFee
                );

                $itemStmt->execute();

                /*
                ------------------------------
                REDUCE STOCK
                ------------------------------
                */

                $stockStmt =
                $conn->prepare("
                UPDATE products
                SET stock =
                stock - ?
                WHERE id=?
                ");

                $stockStmt->bind_param(
                "ii",
                $item['quantity'],
                $item['product_id']
                );

                $stockStmt->execute();
            }

            /*
            --------------------------------------------------
            CLEAR CART
            --------------------------------------------------
            */

            $clearStmt =
            $conn->prepare("
            DELETE FROM cart
            WHERE user_id=?
            ");

            $clearStmt->bind_param(
            "i",
            $userId
            );

            $clearStmt->execute();

            $conn->commit();

            header(
            "Location: payment.php?order_id=" .
            $orderId
            );
            exit;
        }
        catch(Exception $e)
        {
            $conn->rollback();

            $error =
            "Order creation failed.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>Checkout</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

</head>

<body>

<div class="container py-5">

<h2 class="mb-4">

Checkout

</h2>

<?php if(!empty($error)): ?>

<div class="alert alert-danger">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>

<form method="POST">

<div class="row">

<div class="col-lg-8">

<div class="card mb-4">

<div class="card-header">

Delivery Address

</div>

<div class="card-body">

<?php if($addresses->num_rows > 0): ?>

<?php while($address = $addresses->fetch_assoc()): ?>

<div class="form-check border rounded p-3 mb-3">

<input
class="form-check-input"
type="radio"
name="address_id"
value="<?= (int)$address['id'] ?>"
id="address<?= (int)$address['id'] ?>"
<?= (int)$address['is_default'] === 1 ? 'checked' : '' ?>
required>

<label
class="form-check-label w-100"
for="address<?= (int)$address['id'] ?>">

<strong>

<?= htmlspecialchars(
$address['recipient_name']
) ?>

</strong>

<br>

<?= htmlspecialchars(
$address['phone']
) ?>

<br>

<?= htmlspecialchars(
$address['street']
) ?>

<br>

<?= htmlspecialchars(
$address['ward']
) ?>,

<?= htmlspecialchars(
$address['district']
) ?>,

<?= htmlspecialchars(
$address['region']
) ?>

<?php if(
(int)$address['is_default'] === 1
): ?>

<span class="badge bg-success ms-2">

Default

</span>

<?php endif; ?>

</label>

</div>

<?php endwhile; ?>

<?php else: ?>

<div class="alert alert-warning">

No delivery address found.

<br><br>

<a
href="addresses.php"
class="btn btn-sm btn-primary">

Add Address

</a>

</div>

<?php endif; ?>

</div>

</div>

</div>

<!-- RIGHT SIDEBAR -->

<div class="col-lg-4">

<div class="card">

<div class="card-header">

Order Summary

</div>

<div class="card-body">

<table class="table table-sm align-middle">

<thead>

<tr>

<th>Item</th>

<th class="text-center">

Qty

</th>

<th class="text-end">

Total

</th>

</tr>

</thead>

<tbody>

<?php foreach($cartData as $item): ?>

<?php

$productStmt =
$conn->prepare("
SELECT
name
FROM products
WHERE id=?
LIMIT 1
");

$productStmt->bind_param(
"i",
$item['product_id']
);

$productStmt->execute();

$productName =
$productStmt
->get_result()
->fetch_assoc();

$itemTotal =
$item['price']
*
$item['quantity'];

?>

<tr>

<td>

<?= htmlspecialchars(
$productName['name']
?? 'Product'
) ?>

</td>

<td class="text-center">

<?= (int)$item['quantity'] ?>

</td>

<td class="text-end">

TZS

<?= number_format(
$itemTotal
)
?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<hr>

<div
class="d-flex justify-content-between mb-2">

<span>

Subtotal

</span>

<strong>

TZS

<?= number_format(
$subtotal
) ?>

</strong>

</div>

<div
class="d-flex justify-content-between mb-2">

<span>

Shipping Fee

</span>

<strong>

TZS

<?= number_format(
$shippingFee
) ?>

</strong>

</div>

<hr>

<div
class="d-flex justify-content-between">

<h5>

Grand Total

</h5>

<h5 class="text-success">

TZS

<?= number_format(
$totalAmount
) ?>

</h5>

</div>

</div>

<div class="card-footer">

<button
type="submit"
class="btn btn-success w-100">

Place Order

</button>

<a
href="cart.php"
class="btn btn-outline-secondary w-100 mt-2">

Back To Cart

</a>

</div>

</div>

</div>

</div>
</form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>