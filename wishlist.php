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

$stmt =
$conn->prepare("
SELECT

w.id AS wishlist_id,

w.created_at,

p.id,
p.name,
p.slug,
p.price,
p.sale_price,
p.stock,
p.image,
p.featured_image,

s.shop_name

FROM wishlists w

INNER JOIN products p
ON p.id = w.product_id

LEFT JOIN shops s
ON s.id = p.shop_id

WHERE w.user_id=?

AND p.approved=1
AND p.status='active'

ORDER BY w.id DESC
");

$stmt->bind_param(
"i",
$userId
);

$stmt->execute();

$wishlist =
$stmt->get_result();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

My Wishlist

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">

<style>

.product-image{
height:220px;
object-fit:cover;
width:100%;
}

.old-price{
text-decoration:line-through;
color:#888;
font-size:14px;
}

.card{
transition:.3s;
}

.card:hover{
transform:translateY(-5px);
}

</style>

</head>

<body>

<div class="container py-5">

<div
class="d-flex justify-content-between align-items-center mb-4">

<h2>

<i class="bi bi-heart-fill text-danger"></i>

My Wishlist

</h2>

<a
href="products.php"
class="btn btn-primary">

Continue Shopping

</a>

</div>

<?php if(isset($_SESSION['success'])): ?>

<div class="alert alert-success">

<?= htmlspecialchars($_SESSION['success']) ?>

</div>

<?php unset($_SESSION['success']); ?>

<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>

<div class="alert alert-danger">

<?= htmlspecialchars($_SESSION['error']) ?>

</div>

<?php unset($_SESSION['error']); ?>

<?php endif; ?>

<?php if(isset($_SESSION['info'])): ?>

<div class="alert alert-info">

<?= htmlspecialchars($_SESSION['info']) ?>

</div>

<?php unset($_SESSION['info']); ?>

<?php endif; ?>

<div class="row">

<?php if($wishlist->num_rows > 0): ?>

<?php while(
$item =
$wishlist->fetch_assoc()
): ?>

<?php

$image =
$item['featured_image']
?: $item['image'];

if(empty($image))
{
$image =
"assets/images/no-image.jpg";
}

?>

<div class="col-lg-3 col-md-4 col-sm-6 mb-4">

<div class="card h-100 shadow-sm">

<img
src="<?= htmlspecialchars($image) ?>"
class="product-image"
alt="<?= htmlspecialchars($item['name']) ?>">

<div class="card-body">

<div
class="small text-muted mb-1">

<?= htmlspecialchars(
$item['shop_name']
?? 'Shop'
) ?>

</div>

<h6>

<?= htmlspecialchars(
$item['name']
) ?>

</h6>

<?php if(
!empty($item['sale_price'])
&&
$item['sale_price'] > 0
): ?>

<div
class="fw-bold text-danger">

TZS

<?= number_format(
(float)$item['sale_price']
) ?>

</div>

<div class="old-price">

TZS

<?= number_format(
(float)$item['price']
) ?>

</div>

<?php else: ?>

<div
class="fw-bold text-success">

TZS

<?= number_format(
(float)$item['price']
) ?>

</div>

<?php endif; ?>

<div class="mt-2">

<?php if(
(int)$item['stock'] > 0
): ?>

<span
class="badge bg-success">

In Stock

</span>

<?php else: ?>

<span
class="badge bg-danger">

Out Of Stock

</span>

<?php endif; ?>

</div>

</div>

<div class="card-footer bg-white">

<div
class="d-grid gap-2">

<a
href="product-details.php?id=<?= (int)$item['id'] ?>"
class="btn btn-outline-primary btn-sm">

View Product

</a>

<a
href="cart-add.php?id=<?= (int)$item['id'] ?>"
class="btn btn-success btn-sm">

<i class="bi bi-cart-plus"></i>

Add To Cart

</a>

<a
href="wishlist-remove.php?id=<?= (int)$item['wishlist_id'] ?>"
class="btn btn-outline-danger btn-sm"
onclick="return confirm('Remove from wishlist?')">

Remove

</a>

</div>

</div>

</div>

</div>

<?php endwhile; ?>

<?php else: ?>

<div class="col-12">

<div class="alert alert-light text-center p-5">

<h4>

Your wishlist is empty

</h4>

<p>

Save products you love and
access them later.

</p>

<a
href="products.php"
class="btn btn-primary">

Browse Products

</a>

</div>

</div>

<?php endif; ?>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
