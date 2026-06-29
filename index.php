<?php

session_start();

require_once __DIR__ . '/config/db.php';

/*
|--------------------------------------------------------------------------
| SITE SETTINGS
|--------------------------------------------------------------------------
*/

$siteName = "Karliakoo Marketplace";


/*
|--------------------------------------------------------------------------
| NEW ARRIVALS
|--------------------------------------------------------------------------
*/

$newArrivals = [];

$newArrivalQuery = mysqli_query(
    $conn,
    "
    SELECT

        p.*,

        s.shop_name,

        c.category_name

    FROM products p

    LEFT JOIN shops s
        ON s.id = p.shop_id

    LEFT JOIN categories c
        ON c.id = p.category_id

    WHERE

        p.status='active'

    ORDER BY

        p.created_at DESC,
        p.id DESC

    LIMIT 46
    "
);

if($newArrivalQuery)
{
    while($row = mysqli_fetch_assoc($newArrivalQuery))
    {
        $newArrivals[] = $row;
    }
}


$seed = floor(time() / 30); // every 5 minutes

$sql = "

SELECT
p.*,
s.shop_name,
c.category_name

FROM products p

LEFT JOIN shops s
ON s.id=p.shop_id

LEFT JOIN categories c
ON c.id=p.category_id

WHERE p.status='active'

ORDER BY
CRC32(CONCAT(p.id, {$seed}))

LIMIT 8

";

/*
|--------------------------------------------------------------------------
| FEATURED PRODUCTS
|--------------------------------------------------------------------------
*/

$products = [];

$productSql = "
SELECT
    p.id,
    p.name,
    p.slug,
    p.price,
    p.sale_price,
    p.image,
    p.featured_image,
    p.short_description,
    p.views,
    p.sold_count,

    s.shop_name,

    c.category_name

FROM products p

LEFT JOIN shops s
ON p.shop_id = s.id

LEFT JOIN categories c
ON p.category_id = c.id

WHERE
p.status='active'
AND p.approved=1

ORDER BY
p.views DESC,
p.sold_count DESC,
p.id DESC

LIMIT 12
";

$productResult =
mysqli_query(
    $conn,
    $productSql
);

if ($productResult) {

    while (
        $row =
        mysqli_fetch_assoc(
            $productResult
        )
    ) {

        $products[] = $row;
    }
}

$promotions = [];

$sql = "
SELECT
    hp.*,
    p.price,
    p.sale_price,
    p.name AS product_name
FROM homepage_promotions hp
INNER JOIN products p
ON hp.product_id = p.id
WHERE hp.active = 1
AND (
        hp.starts_at IS NULL
        OR hp.starts_at <= NOW()
    )
AND (
        hp.ends_at IS NULL
        OR hp.ends_at >= NOW()
    )
ORDER BY hp.display_order ASC,
hp.id DESC
";

$result = mysqli_query($conn, $sql);

while($row = mysqli_fetch_assoc($result))
{
    $promotions[] = $row;
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
<?= htmlspecialchars($siteName) ?>
</title>

<meta
name="description"
content="Buy and sell products across Tanzania on Karliakoo Marketplace.">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
rel="stylesheet">

<style>

:root{
--primary:#0d6efd;
--dark:#111827;
--light:#f8fafc;
}

body{
background:#f5f6fa;
font-family:Segoe UI,Tahoma,Geneva,Verdana,sans-serif;
}

.topbar{
background:var(--dark);
color:#fff;
padding:8px 0;
font-size:14px;
}

.navbar-brand{
font-weight:800;
font-size:28px;
}

.hero{
height:520px;
background:
linear-gradient(
rgba(0,0,0,.55),
rgba(0,0,0,.55)
),
url('assets/images/hero.jpg');
background-size:cover;
background-position:center;
border-radius:20px;
overflow:hidden;
display:flex;
align-items:center;
padding:50px;
color:#fff;
}

.hero h1{
font-size:56px;
font-weight:800;
}

.hero p{
font-size:20px;
max-width:650px;
}

.category-card{
background:#fff;
border-radius:16px;
padding:25px;
text-align:center;
transition:.3s;
height:100%;
box-shadow:0 2px 10px rgba(0,0,0,.05);
}

.category-card:hover{
transform:translateY(-6px);
box-shadow:0 10px 20px rgba(0,0,0,.1);
}

.category-icon{
font-size:40px;
color:var(--primary);
margin-bottom:12px;
}

.section-title{
font-size:32px;
font-weight:700;
}

.product-card{
background:#fff;
border-radius:18px;
overflow:hidden;
transition:.3s;
height:100%;
box-shadow:0 2px 10px rgba(0,0,0,.06);
}

.product-card:hover{
transform:translateY(-5px);
box-shadow:0 15px 30px rgba(0,0,0,.1);
}

.product-image{
width:100%;
height:250px;
object-fit:cover;
background:#fff;
}

.shop-badge{
background:#198754;
color:#fff;
padding:4px 10px;
border-radius:50px;
font-size:12px;
}

.sticky-top{
z-index:1030;
}

.product-card{
border:none;
border-radius:18px;
overflow:hidden;
transition:all .25s ease;
background:#fff;
}

.product-card:hover{
transform:translateY(-8px);
box-shadow:0 15px 35px rgba(0,0,0,.12);
}

.product-image{
width:100%;
height:250px;
object-fit:cover;
background:#f8f9fa;
}

.shop-badge{
background:#198754;
color:#fff;
padding:4px 10px;
border-radius:50px;
font-size:12px;
font-weight:600;
}

.carousel-item{
    transition:transform .7s ease-in-out;
}

.carousel img{
    transition:transform .4s;
}

.carousel img:hover{
    transform:scale(1.05);
}

.carousel-control-prev,
.carousel-control-next{
    width:5%;
}

.carousel-indicators button{
    width:12px;
    height:12px;
    border-radius:50%;
}

.carousel .badge{
    font-size:.95rem;
    padding:.55rem 1rem;
}

</style>

</head>

<body>

<!-- TOPBAR -->

<div class="topbar">

<div class="container">

<div class="d-flex justify-content-between">

<div>

<i class="fas fa-phone"></i>
+255 760 342 004

</div>

<div>

Trusted Sellers |
Secure Payments |
Fast Delivery

</div>

</div>

</div>

</div>

<!-- NAVIGATION -->

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">

<div class="container">

<a
class="navbar-brand text-primary"
href="index.php">

Karliakoo

</a>

<button
class="navbar-toggler"
type="button"
data-bs-toggle="collapse"
data-bs-target="#navbarMain">

<span class="navbar-toggler-icon"></span>

</button>

<div
class="collapse navbar-collapse"
id="navbarMain">

<ul class="navbar-nav me-auto">

<li class="nav-item">
<a
class="nav-link"
href="index.php">
Home
</a>
</li>

<li class="nav-item">
<a
class="nav-link"
href="products.php">
Products
</a>
</li>

<li class="nav-item">
<a
class="nav-link"
href="category.php">
Categories
</a>
</li>

<li class="nav-item">
<a
class="nav-link"
href="shop.php">
Shops
</a>
</li>

<li class="nav-item">
<a
class="nav-link"
href="b2b/index.php">
B2B Wholesale
</a>
</li>

<li class="nav-item">
<a
class="nav-link"
href="contact.php">

</a>
</li>

</ul>

<form
action="products.php"
method="GET"
class="d-flex">

<select
name="category"
class="form-select">

<option value="">

Browse Categories

</option>

<?php foreach($categories as $cat): ?>

<option
value="<?= (int)$cat['id'] ?>">

<?= htmlspecialchars($cat['category_name']) ?>

</option>

<?php endforeach; ?>

</select>

<button
class="btn btn-primary ms-2">

Go

</button>

</form>


<form
action="search.php"
method="GET"
class="d-flex me-3">

<input
type="search"
name="q"
class="form-control me-2"
placeholder="Search products">

<button
class="btn btn-primary">

<i class="fas fa-search"></i>

</button>

</form>

<div class="d-flex gap-2">

<a
href="wishlist.php"
class="btn btn-outline-secondary">

<i class="fas fa-heart"></i>

</a>

<a
href="cart.php"
class="btn btn-outline-primary">

<i class="fas fa-shopping-cart"></i>

</a>

<a
href="login.php"
class="btn btn-outline-dark">

Login

</a>

<a
href="register.php"
class="btn btn-primary">

Register

</a>

</div>

</div>

</div>

</nav>


<!-- HERO -->

<div class="container mt-4">

<div
id="homepageCarousel"
class="carousel slide carousel-fade shadow rounded overflow-hidden"
data-bs-ride="carousel"
data-bs-interval="5000">

<div class="carousel-inner">

<?php foreach($promotions as $index=>$promo): ?>

<?php

$image="assets/images/no-image.jpg";

if(!empty($promo['image']))
{
    $candidate=ltrim($promo['image'],'/');

    if(file_exists($candidate))
    {
        $image=$candidate;
    }
}

?>

<div
class="carousel-item <?= $index==0 ? 'active':'' ?>">

<div
class="p-5"
style="
min-height:450px;
background:
linear-gradient(
135deg,
<?= htmlspecialchars($promo['background_color']) ?>,
#111827
);
color:
<?= htmlspecialchars($promo['text_color']) ?>;
">

<div class="row align-items-center">

<div class="col-lg-6">

<?php if(!empty($promo['badge'])): ?>

<span class="badge bg-warning text-dark fs-6 mb-3">

<?= htmlspecialchars($promo['badge']) ?>

</span>

<?php endif; ?>

<h1 class="display-4 fw-bold">

<?= htmlspecialchars($promo['title']) ?>

</h1>

<p class="lead">

<?= htmlspecialchars($promo['subtitle']) ?>

</p>

<?php if($promo['sale_price']>0): ?>

<h2>

<span class="text-warning">

TZS <?= number_format($promo['sale_price']) ?>

</span>

<small
class="text-decoration-line-through ms-2">

TZS <?= number_format($promo['price']) ?>

</small>

</h2>

<?php endif; ?>

<a
href="product-details.php?id=<?= (int)$promo['product_id'] ?>"
class="btn btn-warning btn-lg mt-3">

<?= htmlspecialchars($promo['button_text']) ?>

</a>

</div>

<div class="col-lg-6 text-center">

<img
src="<?= htmlspecialchars($image) ?>"
class="img-fluid"
style="
max-height:360px;
object-fit:contain;
">

</div>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

<button
class="carousel-control-prev"
type="button"
data-bs-target="#homepageCarousel"
data-bs-slide="prev">

<span class="carousel-control-prev-icon"></span>

</button>

<button
class="carousel-control-next"
type="button"
data-bs-target="#homepageCarousel"
data-bs-slide="next">

<span class="carousel-control-next-icon"></span>

</button>

</div>

</div>


<!-- ==========================
NEW ARRIVALS
=========================== -->

<div class="container mt-5">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2 class="section-title mb-1">

 New Arrivals

</h2>

<p class="text-muted mb-0">

Recently added products from trusted sellers.

</p>

</div>

<a
href="products.php?sort=new"
class="btn btn-outline-primary">

View All

</a>

</div>

<div class="row">

<?php foreach($newArrivals as $product): ?>

<?php

$image = $product['featured_image']
    ?: $product['image'];

$image = ltrim($image, '/');

if(
    empty($image)
    ||
    !file_exists($image)
)
{
    $image = "assets/images/no-image.jpg";
}

?>

<div class="col-lg-3 col-md-4 col-sm-6 mb-4">

<a
href="product-details.php?id=<?= (int)$product['id'] ?>"
class="text-decoration-none text-dark">

<div class="card border-0 shadow-sm h-100">

<div class="position-relative">

<img
src="<?= htmlspecialchars($image) ?>"
class="card-img-top"
style="
height:220px;
object-fit:cover;">

<span
class="badge bg-success position-absolute top-0 end-0 m-2">

NEW

</span>

</div>

<div class="card-body">

<h6 class="fw-bold">

<?= htmlspecialchars($product['name']) ?>

</h6>

<p class="small text-muted mb-2">

<?= htmlspecialchars($product['shop_name']) ?>

</p>

<h5 class="text-primary">

TZS <?= number_format($product['price']) ?>

</h5>

</div>

</div>

</a>

</div>

<?php endforeach; ?>

</div>

</div>



<!-- PRODUCTS HEADER -->

<div class="container mt-5">

<div class="d-flex justify-content-between align-items-center mb-4">

<h2 class="section-title">

Trending Products

</h2>

<a
href="products.php">

View All Products

</a>

</div>

<div class="row">

<?php foreach($products as $product): ?>

<?php

/*
|--------------------------------------------------------------------------
| PRODUCT IMAGE HANDLER
|--------------------------------------------------------------------------
*/

$productImage = "assets/images/no-image.png";

$imagePath = '';

if (!empty($product['featured_image'])) {

    $imagePath = trim($product['featured_image']);

} elseif (!empty($product['image'])) {

    $imagePath = trim($product['image']);
}

/*
|--------------------------------------------------------------------------
| NORMALIZE PATH
|--------------------------------------------------------------------------
*/

$imagePath = ltrim($imagePath, '/\\');

/*
|--------------------------------------------------------------------------
| VERIFY FILE EXISTS
|--------------------------------------------------------------------------
*/

if (
    !empty($imagePath)
    &&
    file_exists(__DIR__ . '/' . $imagePath)
) {
    $productImage = $imagePath;
}

/*
|--------------------------------------------------------------------------
| PRICING
|--------------------------------------------------------------------------
*/

$price = (float)($product['price'] ?? 0);

$salePrice = (float)($product['sale_price'] ?? 0);

$finalPrice =
(
    $salePrice > 0
    &&
    $salePrice < $price
)
? $salePrice
: $price;

/*
|--------------------------------------------------------------------------
| PRODUCT URL
|--------------------------------------------------------------------------
*/

$productUrl =
!empty($product['slug'])
? "product-details.php?slug=" . urlencode($product['slug'])
: "product-details.php?id=" . (int)$product['id'];

?>

<div class="col-lg-3 col-md-4 col-sm-6 mb-4">

<div class="product-card h-100 shadow-sm">

<div class="position-relative">

<img
src="<?= htmlspecialchars($productImage) ?>"
alt="<?= htmlspecialchars($product['name']) ?>"
class="product-image"
loading="lazy">

<?php if($salePrice > 0 && $salePrice < $price): ?>

<span
class="badge bg-danger position-absolute top-0 end-0 m-2">

SALE

</span>

<?php endif; ?>

</div>

<div class="p-3 d-flex flex-column h-100">

<div class="d-flex justify-content-between mb-2">

<span class="shop-badge">

<?= htmlspecialchars(
$product['shop_name']
?? 'Marketplace'
) ?>

</span>

<span class="badge bg-light text-dark">

<?= htmlspecialchars(
$product['category_name']
?? 'General'
) ?>

</span>

</div>

<h6
class="fw-bold mb-2"
style="
min-height:48px;
">

<?= htmlspecialchars(
$product['name']
) ?>

</h6>

<p
class="text-muted small mb-3"
style="
min-height:60px;
">

<?= htmlspecialchars(
mb_strimwidth(
$product['short_description']
?? '',
0,
90,
'...'
)
) ?>

</p>

<?php if($salePrice > 0 && $salePrice < $price): ?>

<div>

<span class="text-danger fw-bold">

TZS

<?= number_format(
$salePrice
) ?>

</span>

<br>

<small
class="text-decoration-line-through text-muted">

TZS

<?= number_format(
$price
) ?>

</small>

</div>

<?php else: ?>

<h5 class="text-primary fw-bold">

TZS

<?= number_format(
$price
) ?>

</h5>

<?php endif; ?>

<div class="d-grid gap-2 mt-3">

<a
href="product-details.php?id=<?= (int)$product['id'] ?>"
class="btn btn-primary">

View Product

</a>

<a
href="wishlist-add.php?id=<?= (int)$product['id'] ?>"
class="btn btn-outline-secondary">

Add Wishlist

</a>

</div>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

<!-- B2B SECTION -->

<div class="container mt-5">

<div
class="p-5 rounded-4 text-white"
style="
background:
linear-gradient(
135deg,
#0d6efd,
#084298
);
">

<div class="row align-items-center">

<div class="col-lg-8">

<h2 class="fw-bold">

B2B Wholesale Marketplace

</h2>

<p class="lead">

Connect with suppliers,
manufacturers,
importers and distributors
across Tanzania.

</p>

</div>

<div class="col-lg-4 text-lg-end">

<a
href="b2b/index.php"
class="btn btn-light btn-lg">

Explore B2B

</a>

</div>

</div>

</div>

</div>

<!-- MARKETPLACE STATS -->

<div class="container mt-5">

<div class="row text-center">

<div class="col-md-3 mb-3">

<div class="bg-white rounded-4 p-4 shadow-sm">

<h2 class="text-primary">

1000+

</h2>

<p class="mb-0">

Products

</p>

</div>

</div>

<div class="col-md-3 mb-3">

<div class="bg-white rounded-4 p-4 shadow-sm">

<h2 class="text-success">

500+

</h2>

<p class="mb-0">

Verified Sellers

</p>

</div>

</div>

<div class="col-md-3 mb-3">

<div class="bg-white rounded-4 p-4 shadow-sm">

<h2 class="text-warning">

50+

</h2>

<p class="mb-0">

Regions Served

</p>

</div>

</div>

<div class="col-md-3 mb-3">

<div class="bg-white rounded-4 p-4 shadow-sm">

<h2 class="text-danger">

24/7

</h2>

<p class="mb-0">

Support

</p>

</div>

</div>

</div>

</div>

<!-- NEWSLETTER -->

<div class="container mt-5">

<div class="bg-white rounded-4 p-5 shadow-sm">

<div class="row align-items-center">

<div class="col-lg-6">

<h3>

Subscribe To Updates

</h3>

<p class="text-muted">

Receive promotions,
offers and marketplace news.

</p>

</div>

<div class="col-lg-6">

<form
action="subscribe.php"
method="POST">

<div class="input-group">

<input
type="email"
name="email"
class="form-control"
placeholder="Enter email address"
required>

<button
class="btn btn-primary">

Subscribe

</button>

</div>

</form>

</div>

</div>

</div>

</div>

<!-- FOOTER -->

<footer
style="
background:#111827;
color:white;
"
class="mt-5 py-5">

<div class="container">

<div class="row">

<div class="col-lg-4 mb-4">

<h4>

Karliakoo Marketplace

</h4>

<p>

The future of Tanzanian
eCommerce and B2B trade.

</p>

</div>

<div class="col-lg-2 mb-4">

<h5>

Marketplace

</h5>

<ul class="list-unstyled">

<li>
<a
href="products.php"
class="text-white text-decoration-none">
Products
</a>
</li>

<li>
<a
href="categories.php"
class="text-white text-decoration-none">
Categories
</a>
</li>

<li>
<a
href="shops.php"
class="text-white text-decoration-none">
Shops
</a>
</li>

</ul>

</div>

<div class="col-lg-2 mb-4">

<h5>

B2B

</h5>

<ul class="list-unstyled">

<li>
<a
href="b2b/index.php"
class="text-white text-decoration-none">
Wholesale
</a>
</li>

<li>
<a
href="b2b/rfqs.php"
class="text-white text-decoration-none">
RFQs
</a>
</li>

<li>
<a
href="b2b/orders.php"
class="text-white text-decoration-none">
Orders
</a>
</li>

</ul>

</div>

<div class="col-lg-4">

<h5>

Contact

</h5>

<p>

<i class="fas fa-envelope"></i>
[support@karliakoo.com](mailto:support@karliakoo.com)

</p>

<p>

<i class="fas fa-phone"></i>
+255 760 342 004

</p>

<p>

<i class="fas fa-location-dot"></i>
Dar es Salaam, Tanzania

</p>

</div>

</div>

<hr>

<div class="text-center">

© <?= date('Y') ?>

Karliakoo Marketplace.

All Rights Reserved.

</div>

</div>

</footer>

<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>
</html>
