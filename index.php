<?php
session_start();
require_once "config/db.php";

/*
|--------------------------------------------------------------------------
| Fetch Categories
|--------------------------------------------------------------------------
*/
$categories = [];
$catQuery = mysqli_query($conn, "
    SELECT *
    FROM categories
    WHERE status='active'
    ORDER BY category_name ASC
    LIMIT 12
");

if($catQuery){
    while($row = mysqli_fetch_assoc($catQuery)){
        $categories[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Featured Products
|--------------------------------------------------------------------------
*/
$products = [];
$productQuery = mysqli_query($conn, "
    SELECT p.*, s.shop_name
    FROM products p
    LEFT JOIN shops s ON p.shop_id=s.id
    WHERE p.status='active'
    ORDER BY p.id DESC
    LIMIT 12
");

if($productQuery){
    while($row = mysqli_fetch_assoc($productQuery)){
        $products[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Karliakoo Marketplace</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

<style>
body{
    background:#f5f6fa;
}

.topbar{
    background:#0d6efd;
    color:white;
    padding:8px 0;
    font-size:14px;
}

.navbar-brand{
    font-weight:700;
    font-size:28px;
}

.hero{
    height:450px;
    background:url('assets/images/hero.jpg') center center/cover;
    border-radius:15px;
    overflow:hidden;
    position:relative;
}

.hero-overlay{
    background:rgba(0,0,0,.55);
    height:100%;
    display:flex;
    align-items:center;
    color:white;
    padding:40px;
}

.category-card{
    background:white;
    border-radius:12px;
    padding:20px;
    text-align:center;
    transition:.3s;
}

.category-card:hover{
    transform:translateY(-5px);
}

.product-card{
    background:white;
    border-radius:15px;
    overflow:hidden;
    transition:.3s;
}

.product-card:hover{
    transform:translateY(-6px);
}

.product-image{
    height:220px;
    object-fit:cover;
    width:100%;
}

.shop-badge{
    background:#198754;
    color:white;
    padding:4px 10px;
    border-radius:50px;
    font-size:12px;
}

.footer{
    background:#111827;
    color:white;
    padding:50px 0;
}
</style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
<div class="container">
<div class="d-flex justify-content-between">
<div>
<i class="fa fa-phone"></i> +255 760 342 004
</div>
<div>
Free Delivery | Trusted Sellers | Secure Payments
</div>
</div>
</div>
</div>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
<div class="container">

<a class="navbar-brand text-primary" href="#">
Karliakoo
</a>

<form action="search.php" method="GET" class="d-flex">

<input
type="text"
name="q"
class="form-control me-2"
placeholder="Search products...">

<button class="btn btn-primary">
Search
</button>

</form>

<div>
<a href="login.php" class="btn btn-outline-primary">
Login
</a>

<a href="register.php" class="btn btn-primary">
Register
</a>
</div>

</div>
</nav>


<!-- HERO -->
<div class="container mt-4">
<div class="hero">
<div class="hero-overlay">

<div>
<h1 class="display-4 fw-bold">
Karliakoo Marketplace
</h1>

<p class="lead">
Buy, Sell and Grow your business across Tanzania.
</p>

<a href="products.php" class="btn btn-warning btn-lg">
Shop Now
</a>
</div>

</div>
</div>
</div>

<!-- CATEGORIES -->
<div class="container mt-5">

<h3 class="mb-4">
Popular Categories
</h3>

<div class="row">

<?php foreach($categories as $cat): ?>

<div class="col-lg-2 col-md-3 col-6 mb-3">

<div class="category-card">

<i class="fa fa-box fa-2x text-primary mb-2"></i>

<h6>
<?= htmlspecialchars($cat['category_name']) ?>
</h6>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

<!-- PRODUCTS -->
<div class="container mt-5">

<div class="d-flex justify-content-between mb-3">

<h3>Trending Products</h3>

<a href="products.php">
View All
</a>

</div>

<div class="row">

<?php foreach($products as $product): ?>

<div class="col-lg-3 col-md-4 col-sm-6 mb-4">

<div class="product-card shadow-sm">

<img
src="uploads/products/<?= htmlspecialchars($product['image']) ?>"
class="product-image"
alt=""
>

<div class="p-3">

<div class="shop-badge mb-2">
<?= htmlspecialchars($product['shop_name']) ?>
</div>

<h6>
<?= htmlspecialchars($product['name']) ?>
</h6>

<h5 class="text-primary">
TZS <?= number_format($product['price']) ?>
</h5>

<a
href="product.php?id=<?= $product['id'] ?>"
class="btn btn-primary w-100"
>
View Product
</a>

<a href="category.php?id=<?= $cat['id'] ?>">
    <?= htmlspecialchars($cat['category_name']) ?>
</a>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

<!-- WHOLESALE -->
<div class="container mt-5">

<div class="bg-primary text-white p-5 rounded">

<h2>
B2B Wholesale Marketplace
</h2>

<p>
Connect with suppliers and wholesalers across Tanzania.
</p>

<a href="b2b/index.php" class="btn btn-light">
Explore B2B
</a>

</div>

</div>

<!-- FOOTER -->
<footer class="footer mt-5">

<div class="container">

<div class="row">

<div class="col-md-4">
<h4>Karliakoo</h4>
<p>
The future of Tanzanian eCommerce.
</p>
</div>

<div class="col-md-4">
<h5>Quick Links</h5>

<ul class="list-unstyled">
<li><a href="#" class="text-white">Products</a></li>
<li><a href="#" class="text-white">Shops</a></li>
<li><a href="#" class="text-white">B2B</a></li>
<li><a href="#" class="text-white">Delivery</a></li>
</ul>

</div>

<div class="col-md-4">

<h5>Contact</h5>

<p>
support@karliakoo.com
</p>

<p>
Dar es Salaam, Tanzania
</p>

</div>

</div>

<hr>

<p class="text-center mb-0">
© <?php echo date("Y"); ?> Karliakoo Marketplace
</p>

</div>

</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>