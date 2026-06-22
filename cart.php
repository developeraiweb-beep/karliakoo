<?php

declare(strict_types=1);

session_start();

require_once "config/db.php";

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| UPDATE QUANTITY
|--------------------------------------------------------------------------
*/

if(
isset($_POST['update_cart'])
)
{
    foreach(
        ($_POST['qty'] ?? [])
        as $cartId => $qty
    )
    {
        $cartId =
        (int)$cartId;

        $qty =
        max(
            1,
            (int)$qty
        );

        $stmt =
        $conn->prepare("
        UPDATE cart
        SET quantity=?
        WHERE
        id=?
        AND user_id=?
        ");

        $stmt->bind_param(
        "iii",
        $qty,
        $cartId,
        $userId
        );

        $stmt->execute();
    }

    $_SESSION['success'] =
    "Cart updated successfully.";

    header(
    "Location: cart.php"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| REMOVE ITEM
|--------------------------------------------------------------------------
*/

if(
isset($_GET['remove'])
)
{
    $cartId =
    (int)$_GET['remove'];

    $stmt =
    $conn->prepare("
    DELETE FROM cart
    WHERE
    id=?
    AND user_id=?
    ");

    $stmt->bind_param(
    "ii",
    $cartId,
    $userId
    );

    $stmt->execute();

    $_SESSION['success'] =
    "Item removed.";

    header(
    "Location: cart.php"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD CART
|--------------------------------------------------------------------------
*/

$sql = "
SELECT

c.id cart_id,
c.quantity,

p.id product_id,
p.name,
p.slug,
p.image,
p.featured_image,
p.price,
p.sale_price,
p.stock,

s.shop_name

FROM cart c

INNER JOIN products p
ON p.id = c.product_id

LEFT JOIN shops s
ON s.id = p.shop_id

WHERE c.user_id=?

ORDER BY c.id DESC
";

$stmt =
$conn->prepare($sql);

$stmt->bind_param(
"i",
$userId
);

$stmt->execute();

$cartItems =
$stmt->get_result();

$subtotal = 0;
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Shopping Cart

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">

<style>

.cart-image{
width:90px;
height:90px;
object-fit:cover;
border-radius:10px;
}

.qty-input{
width:90px;
}

</style>

</head>

<body>

<div class="container py-5">

<h2 class="mb-4">

<i class="bi bi-cart3"></i>

My Cart

</h2>

<?php if(isset($_SESSION['success'])): ?>

<div class="alert alert-success">

<?= $_SESSION['success']; ?>

</div>

<?php unset($_SESSION['success']); ?>

<?php endif; ?>

<form method="POST">

<div class="table-responsive">

<table class="table align-middle">

<thead>

<tr>

<th>Product</th>

<th>Shop</th>

<th>Price</th>

<th>Qty</th>

<th>Total</th>

<th></th>

</tr>

</thead>

<tbody>

<?php if($cartItems->num_rows > 0): ?>

<?php while(
$item =
$cartItems->fetch_assoc()
): ?>

<?php

$image =
$item['featured_image']
?:
$item['image'];

if(empty($image))
{
$image =
"assets/images/no-image.jpg";
}

$price =
!empty(
$item['sale_price']
)
?
(float)$item['sale_price']
:
(float)$item['price'];

$rowTotal =
$price *
(int)$item['quantity'];

$subtotal +=
$rowTotal;

?>

<tr>

<td>

<div
class="d-flex align-items-center gap-3">

<img
src="<?= htmlspecialchars($image) ?>"
class="cart-image">

<div>

<a
href="product-details.php?id=<?= (int)$item['product_id'] ?>"
class="text-decoration-none">

<?= htmlspecialchars(
$item['name']
) ?>

</a>

<br>

<small>

Stock:

<?= (int)$item['stock'] ?>

</small>

</div>

</div>

</td>

<td>

<?= htmlspecialchars(
$item['shop_name']
?? 'N/A'
) ?>

</td>

<td>

TZS

<?= number_format(
$price
) ?>

</td>

<td>

<input
type="number"
name="qty[<?= (int)$item['cart_id'] ?>]"
value="<?= (int)$item['quantity'] ?>"
min="1"
max="<?= (int)$item['stock'] ?>"
class="form-control qty-input">

</td>

<td>

TZS

<?= number_format(
$rowTotal
) ?>

</td>

<td>

<a
href="cart.php?remove=<?= (int)$item['cart_id'] ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Remove item?')">

<i class="bi bi-trash"></i>

</a>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td colspan="6">

<div class="alert alert-info mb-0">

Your cart is empty.

</div>

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

<?php if($subtotal > 0): ?>

<div class="row">

<div class="col-lg-4 ms-auto">

<div class="card">

<div class="card-header">

Cart Summary

</div>

<div class="card-body">

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

<hr>

<div
class="d-flex justify-content-between">

<span>

Total

</span>

<h5>

TZS

<?= number_format(
$subtotal
) ?>

</h5>

</div>

</div>

<div class="card-footer">

<div class="d-grid gap-2">

<button
type="submit"
name="update_cart"
class="btn btn-primary">

Update Cart

</button>

<a
href="checkout.php"
class="btn btn-success">

Proceed To Checkout

</a>

<a
href="products.php"
class="btn btn-outline-secondary">

Continue Shopping

</a>

</div>

</div>

</div>

</div>

</div>

<?php endif; ?>

</form>

</div>

</body>
</html>