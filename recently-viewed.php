<?php

require_once "config/db.php";
require_once "includes/auth.php";

requireLogin();

$user_id = (int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Clear History
|--------------------------------------------------------------------------
*/
if(isset($_GET['clear']))
{
    $stmt = $conn->prepare("
        DELETE FROM recently_viewed
        WHERE user_id=?
    ");

    $stmt->bind_param(
        "i",
        $user_id
    );

    $stmt->execute();

    header("Location: recently-viewed.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch History
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT
    rv.viewed_at,
    p.id,
    p.name,
    p.price,
    p.image,
    p.stock,
    s.shop_name
FROM recently_viewed rv

INNER JOIN products p
ON rv.product_id=p.id

LEFT JOIN shops s
ON p.shop_id=s.id

WHERE rv.user_id=?

ORDER BY rv.viewed_at DESC

LIMIT 100
");

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$products = $stmt->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
Recently Viewed Products
</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
rel="stylesheet">

<style>

body{
background:#f5f5f5;
}

.product-card{
background:white;
border-radius:12px;
overflow:hidden;
height:100%;
}

.product-image{
width:100%;
height:220px;
object-fit:cover;
}

.price{
font-size:20px;
font-weight:bold;
color:#0d6efd;
}

</style>

</head>

<body>

<div class="container py-5">

<div
class="d-flex justify-content-between align-items-center mb-4">

<h2>

<i class="fa fa-history"></i>

Recently Viewed

</h2>

<a
href="?clear=1"
class="btn btn-danger"
onclick="return confirm('Clear history?')">

Clear History

</a>

</div>

<?php if(
$products->num_rows > 0
): ?>

<div class="row">

<?php while(
$product =
$products->fetch_assoc()
): ?>

<div class="col-md-3 mb-4">

<div class="product-card shadow-sm">

<img
src="uploads/products/<?=
htmlspecialchars(
$product['image']
)
?>"
class="product-image">

<div class="p-3">

<h6>

<?= htmlspecialchars(
$product['name']
) ?>

</h6>

<p
class="text-muted mb-1">

<?= htmlspecialchars(
$product['shop_name']
) ?>

</p>

<div class="price">

TZS
<?= number_format(
$product['price']
) ?>

</div>

<?php if(
$product['stock'] > 0
): ?>

<span
class="badge bg-success">

In Stock

</span>

<?php else: ?>

<span
class="badge bg-danger">

Out of Stock

</span>

<?php endif; ?>

<hr>

<small
class="text-muted">

Viewed:
<?= date(
"d M Y H:i",
strtotime(
$product['viewed_at']
)
) ?>

</small>

<a
href="product.php?id=<?=
$product['id']
?>"
class="btn btn-primary w-100 mt-2">

View Product

</a>

</div>

</div>

</div>

<?php endwhile; ?>

</div>

<?php else: ?>

<div class="alert alert-info">

You haven't viewed any products yet.

</div>

<a
href="products.php"
class="btn btn-primary">

Browse Products

</a>

<?php endif; ?>

</div>

</body>
</html>