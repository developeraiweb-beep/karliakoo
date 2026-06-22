<?php

require_once "config/db.php";

$shop_id = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

if($shop_id <= 0){
    header("Location: products.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Shop Details
|--------------------------------------------------------------------------
*/
$shopStmt = $conn->prepare("
SELECT
    s.*,
    u.full_name,
    u.phone,
    u.email
FROM shops s
LEFT JOIN users u
    ON s.user_id = u.id
WHERE s.id=?
LIMIT 1
");

$shopStmt->bind_param(
    "i",
    $shop_id
);

$shopStmt->execute();

$shop =
$shopStmt
->get_result()
->fetch_assoc();

if(!$shop){
    header("Location: products.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Shop Statistics
|--------------------------------------------------------------------------
*/
$statsStmt = $conn->prepare("
SELECT
COUNT(*) as products,
SUM(stock) as stock
FROM products
WHERE shop_id=?
");

$statsStmt->bind_param(
    "i",
    $shop_id
);

$statsStmt->execute();

$stats =
$statsStmt
->get_result()
->fetch_assoc();

/*
|--------------------------------------------------------------------------
| Shop Rating
|--------------------------------------------------------------------------
*/
$ratingStmt = $conn->prepare("
SELECT
ROUND(AVG(r.rating),1) avg_rating,
COUNT(r.id) total_reviews
FROM reviews r
INNER JOIN products p
ON r.product_id=p.id
WHERE p.shop_id=?
");

$ratingStmt->bind_param(
    "i",
    $shop_id
);

$ratingStmt->execute();

$rating =
$ratingStmt
->get_result()
->fetch_assoc();

/*
|--------------------------------------------------------------------------
| Search + Sort
|--------------------------------------------------------------------------
*/
$keyword =
trim($_GET['q'] ?? '');

$sort =
$_GET['sort'] ?? 'latest';

$where =
" WHERE p.shop_id=? AND p.status='active' ";

$params = [$shop_id];
$types = "i";

if(!empty($keyword)){

    $where .= "
    AND (
        p.name LIKE ?
        OR p.description LIKE ?
    )";

    $search = "%{$keyword}%";

    $params[] = $search;
    $params[] = $search;

    $types .= "ss";
}

$orderBy = "p.id DESC";

switch($sort){

    case 'price_low':
        $orderBy = "p.price ASC";
        break;

    case 'price_high':
        $orderBy = "p.price DESC";
        break;

    case 'popular':
        $orderBy = "p.views DESC";
        break;

    case 'name':
        $orderBy = "p.name ASC";
        break;
}

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/
$page =
max(
1,
(int)($_GET['page'] ?? 1)
);

$limit = 20;
$offset =
($page - 1) * $limit;

/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/
$countSql = "
SELECT COUNT(*) total
FROM products p
$where
";

$countStmt =
$conn->prepare($countSql);

$countStmt->bind_param(
$types,
...$params
);

$countStmt->execute();

$totalProducts =
$countStmt
->get_result()
->fetch_assoc()['total'];

$totalPages =
ceil(
$totalProducts / $limit
);

/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
*/
$productSql = "
SELECT *
FROM products p
$where
ORDER BY $orderBy
LIMIT $limit OFFSET $offset
";

$productStmt =
$conn->prepare($productSql);

$productStmt->bind_param(
$types,
...$params
);

$productStmt->execute();

$products =
$productStmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
<?= htmlspecialchars(
$shop['shop_name']
) ?>
 | Karliakoo
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

.shop-banner{
height:300px;
object-fit:cover;
width:100%;
}

.shop-logo{
width:120px;
height:120px;
object-fit:cover;
border-radius:50%;
border:4px solid white;
margin-top:-60px;
background:white;
}

.shop-card{
background:white;
border-radius:12px;
padding:20px;
}

.product-card{
background:white;
border-radius:12px;
overflow:hidden;
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

<div class="container py-4">

<!-- Banner -->

<div class="shop-card p-0 overflow-hidden mb-4">

<?php if(!empty($shop['banner'])): ?>

<img
src="uploads/shops/<?= $shop['banner'] ?>"
class="shop-banner">

<?php endif; ?>

<div class="p-4">

<img
src="uploads/shops/<?= $shop['logo'] ?>"
class="shop-logo">

<h2 class="mt-3">

<?= htmlspecialchars(
$shop['shop_name']
) ?>

</h2>

<p>

<?= nl2br(
htmlspecialchars(
$shop['description']
))
?>

</p>

<div class="row">

<div class="col-md-3">

<strong>
Products
</strong>

<br>

<?= number_format(
$stats['products']
) ?>

</div>

<div class="col-md-3">

<strong>
Stock
</strong>

<br>

<?= number_format(
$stats['stock']
) ?>

</div>

<div class="col-md-3">

<strong>
Rating
</strong>

<br>

⭐
<?= $rating['avg_rating']
?: 0 ?>

</div>

<div class="col-md-3">

<strong>
Reviews
</strong>

<br>

<?= $rating['total_reviews']
?: 0 ?>

</div>

</div>

<hr>

<a
href="mailto:<?= $shop['email'] ?>"
class="btn btn-primary">

Contact Seller

</a>

<a
href="follow-shop.php?id=<?= $shop_id ?>"
class="btn btn-outline-success">

Follow Shop

</a>

</div>

</div>

<!-- Search -->

<div class="shop-card mb-4">

<form
method="GET">

<input
type="hidden"
name="id"
value="<?= $shop_id ?>">

<div class="row">

<div class="col-md-6">

<input
type="text"
name="q"
value="<?= htmlspecialchars($keyword) ?>"
class="form-control"
placeholder="Search products">

</div>

<div class="col-md-4">

<select
name="sort"
class="form-select">

<option value="latest">
Latest
</option>

<option value="price_low">
Price Low → High
</option>

<option value="price_high">
Price High → Low
</option>

<option value="popular">
Most Popular
</option>

<option value="name">
Name A-Z
</option>

</select>

</div>

<div class="col-md-2">

<button
class="btn btn-primary w-100">

Search

</button>

</div>

</div>

</form>

</div>

<!-- Products -->

<div class="row">

<?php if(
$products->num_rows > 0
): ?>

<?php while(
$product =
$products->fetch_assoc()
): ?>

<div class="col-md-3 mb-4">

<div class="product-card shadow-sm">

<img
src="uploads/products/<?= $product['image'] ?>"
class="product-image">

<div class="p-3">

<h6>

<?= htmlspecialchars(
$product['name']
) ?>

</h6>

<div class="price">

TZS
<?= number_format(
$product['price']
) ?>

</div>

<a
href="product.php?id=<?= $product['id'] ?>"
class="btn btn-primary w-100 mt-2">

View Product

</a>

</div>

</div>

</div>

<?php endwhile; ?>

<?php else: ?>

<div class="col-12">

<div class="alert alert-warning">

No products found.

</div>

</div>

<?php endif; ?>

</div>

<!-- Pagination -->

<?php if($totalPages > 1): ?>

<nav>

<ul class="pagination justify-content-center">

<?php for(
$i=1;
$i<=$totalPages;
$i++
): ?>

<li
class="page-item
<?= $i==$page
? 'active'
: '' ?>">

<a
class="page-link"
href="?id=<?= $shop_id ?>&page=<?= $i ?>&q=<?= urlencode($keyword) ?>&sort=<?= $sort ?>">

<?= $i ?>

</a>

</li>

<?php endfor; ?>

</ul>

</nav>

<?php endif; ?>

</div>

</body>
</html>