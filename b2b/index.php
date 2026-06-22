<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";

/*
|--------------------------------------------------------------------------
| PLATFORM STATISTICS
|--------------------------------------------------------------------------
*/

$totalCompanies = 0;
$totalSuppliers = 0;
$totalWholesaleProducts = 0;
$totalOrders = 0;

/* Companies */

$result =
mysqli_query(
$conn,
"
SELECT COUNT(*) total
FROM b2b_companies
WHERE verification_status='verified'
"
);

if($row = mysqli_fetch_assoc($result))
{
    $totalCompanies =
    (int)$row['total'];
}

/* Suppliers */

$result =
mysqli_query(
$conn,
"
SELECT COUNT(*) total
FROM shops
WHERE status='approved'
AND suspended=0
"
);

if($row = mysqli_fetch_assoc($result))
{
    $totalSuppliers =
    (int)$row['total'];
}

/* Wholesale Products */

$result =
mysqli_query(
$conn,
"
SELECT COUNT(*) total
FROM products
WHERE approved=1
AND status='active'
AND is_wholesale=1
"
);

if($row = mysqli_fetch_assoc($result))
{
    $totalWholesaleProducts =
    (int)$row['total'];
}

/* B2B Orders */

$result =
mysqli_query(
$conn,
"
SELECT COUNT(*) total
FROM b2b_orders
"
);

if($row = mysqli_fetch_assoc($result))
{
    $totalOrders =
    (int)$row['total'];
}

/*
|--------------------------------------------------------------------------
| FEATURED CATEGORIES
|--------------------------------------------------------------------------
*/

$categories =
mysqli_query(
$conn,
"
SELECT
id,
category_name,
image
FROM categories
WHERE status='active'
ORDER BY id DESC
LIMIT 8
"
);

/*
|--------------------------------------------------------------------------
| FEATURED WHOLESALE PRODUCTS
|--------------------------------------------------------------------------
*/

$products =
mysqli_query(
$conn,
"
SELECT

p.*,
s.shop_name

FROM products p

LEFT JOIN shops s
ON s.id = p.shop_id

WHERE

p.approved=1
AND p.status='active'
AND p.is_wholesale=1

ORDER BY p.featured DESC,
p.id DESC

LIMIT 12
"
);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Karliakoo B2B Marketplace

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">

<style>

.hero{
background:
linear-gradient(
135deg,
#0d6efd,
#0a58ca
);
color:white;
padding:100px 0;
}

.stat-box{
background:white;
border-radius:15px;
padding:20px;
text-align:center;
box-shadow:
0 5px 20px rgba(0,0,0,.08);
height:100%;
}

.category-card{
transition:.3s;
}

.category-card:hover{
transform:translateY(-5px);
}

.category-image{
height:150px;
object-fit:cover;
width:100%;
}

</style>

</head>

<body>

<section class="hero">

<div class="container">

<div class="row align-items-center">

<div class="col-lg-7">

<h1 class="display-4 fw-bold">

Africa's Wholesale Marketplace

</h1>

<p class="lead">

Connect with verified suppliers,
manufacturers,
distributors,
and wholesalers.

</p>

<div class="mt-4">

<a
href="products.php"
class="btn btn-light btn-lg">

Browse Wholesale Products

</a>

<a
href="register-company.php"
class="btn btn-outline-light btn-lg">

Register Company

</a>

</div>

</div>

<div class="col-lg-5">

<img
src="../assets/images/b2b-hero.png"
class="img-fluid"
alt="B2B Marketplace">

</div>

</div>

</div>

</section>

<div class="container py-5">

<div class="row g-4 mb-5">

<div class="col-md-3">

<div class="stat-box">

<h2>

<?= number_format(
$totalCompanies
) ?>

</h2>

<p>

Verified Companies

</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-box">

<h2>

<?= number_format(
$totalSuppliers
) ?>

</h2>

<p>

Suppliers

</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-box">

<h2>

<?= number_format(
$totalWholesaleProducts
) ?>

</h2>

<p>

Wholesale Products

</p>

</div>

</div>

<div class="col-md-3">

<div class="stat-box">

<h2>

<?= number_format(
$totalOrders
) ?>

</h2>

<p>

B2B Orders

</p>

</div>

</div>

</div>

<h3 class="mb-4">

Popular Categories

</h3>

<div class="row">

<?php while($category = mysqli_fetch_assoc($categories)): ?>

<div class="col-lg-3 col-md-4 col-sm-6 mb-4">

<div class="card category-card h-100 border-0 shadow-sm">

<?php

$catImage =
!empty($category['image'])
? "../" . ltrim($category['image'],'/')
: "../assets/images/category-placeholder.jpg";

?>

<img
src="<?= htmlspecialchars($catImage) ?>"
class="category-image"
alt="<?= htmlspecialchars($category['category_name']) ?>">

<div class="card-body text-center">

<h6>

<?= htmlspecialchars(
$category['category_name']
) ?>

</h6>

<a
href="products.php?category=<?= (int)$category['id'] ?>"
class="btn btn-outline-primary btn-sm">

Explore

</a>

</div>

</div>

</div>

<?php endwhile; ?>

</div>

<?php

/*
|--------------------------------------------------------------------------
| RFQ STATISTICS
|--------------------------------------------------------------------------
*/

$rfqPending = 0;

$rfqResult =
mysqli_query(
$conn,
"
SELECT COUNT(*) total
FROM rfq_requests
WHERE status='pending'
"
);

if($row = mysqli_fetch_assoc($rfqResult))
{
    $rfqPending =
    (int)$row['total'];
}

?>

<div class="row my-5">

<div class="col-lg-8">

<div class="card border-0 shadow">

<div class="card-body p-5">

<h2>

Request For Quotation (RFQ)

</h2>

<p class="lead">

Need bulk quantities?

Send one RFQ and receive quotations
from multiple suppliers.

</p>

<div class="row text-center mt-4">

<div class="col-md-4">

<h3 class="text-primary">

<?= number_format($rfqPending) ?>

</h3>

<p>

Open RFQs

</p>

</div>

<div class="col-md-4">

<h3 class="text-success">

<?= number_format($totalSuppliers) ?>

</h3>

<p>

Active Suppliers

</p>

</div>

<div class="col-md-4">

<h3 class="text-warning">

<?= number_format($totalWholesaleProducts) ?>

</h3>

<p>

Bulk Products

</p>

</div>

</div>

<div class="mt-4">

<a
href="create-rfq.php"
class="btn btn-primary btn-lg">

Submit RFQ

</a>

<a
href="rfqs.php"
class="btn btn-outline-dark btn-lg">

View RFQs

</a>

</div>

</div>

</div>

</div>

<div class="col-lg-4">

<div class="card border-0 shadow h-100">

<div class="card-body">

<h4>

Why Buy B2B?

</h4>

<hr>

<ul class="list-unstyled">

<li class="mb-3">

<i class="bi bi-check-circle-fill text-success"></i>

Negotiated Pricing

</li>

<li class="mb-3">

<i class="bi bi-check-circle-fill text-success"></i>

Bulk Quantities

</li>

<li class="mb-3">

<i class="bi bi-check-circle-fill text-success"></i>

Verified Suppliers

</li>

<li class="mb-3">

<i class="bi bi-check-circle-fill text-success"></i>

Direct Communication

</li>

<li class="mb-3">

<i class="bi bi-check-circle-fill text-success"></i>

Contract-Based Procurement

</li>

</ul>

</div>

</div>

</div>

</div>

<h3 class="mb-4">

Featured Wholesale Products

</h3>

<div class="row">

<?php while($product = mysqli_fetch_assoc($products)): ?>

<?php

$image =
$product['featured_image']
?: $product['image'];

if(empty($image))
{
    $image =
    "../assets/images/no-image.jpg";
}

?>

<div class="col-lg-3 col-md-4 col-sm-6 mb-4">

<div class="card h-100 border-0 shadow-sm">

<img
src="<?= htmlspecialchars('../' . ltrim($image,'./')) ?>"
style="
height:220px;
object-fit:cover;
width:100%;
">

<div class="card-body">

<span
class="badge bg-success mb-2">

Wholesale

</span>

<h6>

<?= htmlspecialchars(
$product['name']
) ?>

</h6>

<div class="small text-muted mb-2">

Supplier:

<?= htmlspecialchars(
$product['shop_name']
) ?>

</div>

<div class="fw-bold text-primary">

TZS

<?= number_format(
(float)$product['wholesale_price']
)
?>

</div>

<?php if(
$product['wholesale_price'] > 0
): ?>

<div class="small text-muted">

Retail:

TZS

<?= number_format(
(float)$product['price']
) ?>

</div>

<?php endif; ?>

<div class="mt-2">

MOQ:

<strong>

<?= (int)(
$product['minimum_order_qty']
?: 1
) ?>

</strong>

Units

</div>

</div>

<div class="card-footer bg-white">

<div class="d-grid gap-2">

<a
href="product-details.php?id=<?= (int)$product['id'] ?>"
class="btn btn-primary btn-sm">

View Product

</a>

<a
href="create-rfq.php?product_id=<?= (int)$product['id'] ?>"
class="btn btn-outline-success btn-sm">

Request Quote

</a>

</div>

</div>

</div>

</div>

<?php endwhile; ?>

</div>

<div class="text-center my-5">

<a
href="products.php"
class="btn btn-lg btn-primary">

View All Wholesale Products

</a>

</div>

<div class="row">

<?php

/*
|--------------------------------------------------------------------------
| VERIFIED SUPPLIERS
|--------------------------------------------------------------------------
*/

$suppliers =
mysqli_query(
$conn,
"
SELECT
id,
shop_name,
logo,
city,
region,
verified,
followers
FROM shops
WHERE
status='approved'
AND suspended=0
ORDER BY verified DESC,
followers DESC,
id DESC
LIMIT 8
"
);

/*
|--------------------------------------------------------------------------
| VERIFIED COMPANIES
|--------------------------------------------------------------------------
*/

$companies =
mysqli_query(
$conn,
"
SELECT *
FROM b2b_companies
WHERE verification_status='verified'
ORDER BY id DESC
LIMIT 6
"
);

/*
|--------------------------------------------------------------------------
| RECENT RFQs
|--------------------------------------------------------------------------
*/

$latestRfqs =
mysqli_query(
$conn,
"
SELECT
*
FROM rfq_requests
ORDER BY id DESC
LIMIT 8
"
);

/*
|--------------------------------------------------------------------------
| CONTRACT STATISTICS
|--------------------------------------------------------------------------
*/

$totalContracts = 0;

$contractQuery =
mysqli_query(
$conn,
"
SELECT COUNT(*) total
FROM b2b_contracts
WHERE status='active'
"
);

if($row = mysqli_fetch_assoc($contractQuery))
{
    $totalContracts =
    (int)$row['total'];
}

?>

<div class="col-12">

<h3 class="mb-4">

Verified Suppliers

</h3>

</div>

<?php while($supplier = mysqli_fetch_assoc($suppliers)): ?>

<?php

$logo =
!empty($supplier['logo'])
? "../" . ltrim($supplier['logo'],'/')
: "../assets/images/shop-placeholder.png";

?>

<div class="col-lg-3 col-md-4 col-sm-6 mb-4">

<div class="card border-0 shadow-sm h-100">

<div class="card-body text-center">

<img
src="<?= htmlspecialchars($logo) ?>"
class="rounded-circle mb-3"
style="
width:90px;
height:90px;
object-fit:cover;
">

<h6>

<?= htmlspecialchars(
$supplier['shop_name']
) ?>

</h6>

<p class="small text-muted">

<?= htmlspecialchars(
$supplier['city']
) ?>

<?= !empty($supplier['region'])
? ', '.htmlspecialchars($supplier['region'])
: '' ?>

</p>

<?php if(
(int)$supplier['verified'] === 1
): ?>

<span
class="badge bg-success">

Verified Supplier

</span>

<?php endif; ?>

<div class="mt-2">

Followers:

<strong>

<?= number_format(
(int)$supplier['followers']
) ?>

</strong>

</div>

</div>

<div class="card-footer bg-white">

<a
href="../shop-details.php?id=<?= (int)$supplier['id'] ?>"
class="btn btn-outline-primary w-100">

View Supplier

</a>

</div>

</div>

</div>

<?php endwhile; ?>

</div>

<hr class="my-5">

<h3 class="mb-4">

Verified Companies

</h3>

<div class="row">

<?php while($company = mysqli_fetch_assoc($companies)): ?>

<div class="col-lg-4 mb-4">

<div class="card border-0 shadow-sm h-100">

<div class="card-body">

<h5>

<?= htmlspecialchars(
$company['company_name']
) ?>

</h5>

<p class="text-muted">

<?= htmlspecialchars(
$company['industry']
?: 'General Business'
) ?>

</p>

<p>

<?= htmlspecialchars(
$company['city']
?: ''
) ?>

<?= !empty($company['country'])
? ', '.htmlspecialchars($company['country'])
: '' ?>

</p>

<span
class="badge bg-success">

Verified

</span>

</div>

</div>

</div>

<?php endwhile; ?>

</div>

<hr class="my-5">

<div class="row">

<div class="col-lg-8">

<h3 class="mb-4">

Latest RFQ Opportunities

</h3>

<div class="card border-0 shadow-sm">

<div class="table-responsive">

<table class="table table-hover mb-0">

<thead>

<tr>

<th>RFQ #</th>

<th>Quantity</th>

<th>Status</th>

<th>Location</th>

</tr>

</thead>

<tbody>

<?php while($rfq = mysqli_fetch_assoc($latestRfqs)): ?>

<tr>

<td>

<?= htmlspecialchars(
$rfq['quote_number']
?: ('RFQ-'.$rfq['id'])
) ?>

</td>

<td>

<?= number_format(
(int)$rfq['quantity']
) ?>

</td>

<td>

<span
class="badge bg-warning text-dark">

<?= htmlspecialchars(
ucfirst($rfq['status'])
) ?>

</span>

</td>

<td>

<?= htmlspecialchars(
$rfq['delivery_location']
?: 'N/A'
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

<div class="col-lg-4">

<div class="card border-0 shadow">

<div class="card-body">

<h4>

B2B Marketplace Metrics

</h4>

<hr>

<div class="mb-3">

<h3 class="text-primary">

<?= number_format(
$totalOrders
) ?>

</h3>

<p>

Total Orders

</p>

</div>

<div class="mb-3">

<h3 class="text-success">

<?= number_format(
$totalContracts
) ?>

</h3>

<p>

Active Contracts

</p>

</div>

<div class="mb-3">

<h3 class="text-warning">

<?= number_format(
$totalCompanies
) ?>

</h3>

<p>

Verified Companies

</p>

</div>

</div>

</div>

</div>

</div>

<section class="py-5 mt-5 bg-primary text-white rounded">

<div class="container text-center">

<h2>

Grow Your Business With Karliakoo B2B

</h2>

<p class="lead">

Reach thousands of buyers,
suppliers,
manufacturers and distributors.

</p>

<div class="mt-4">

<a
href="register-company.php"
class="btn btn-light btn-lg">

Register Company

</a>

<a
href="supplier-register.php"
class="btn btn-outline-light btn-lg">

Become Supplier

</a>

</div>

</div>

</section>

<footer class="py-5 text-center text-muted">

<hr>

<p>

© <?= date('Y') ?>

Karliakoo B2B Marketplace

</p>

<p>

Wholesale • Procurement • RFQ • Contracts

</p>

</footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
