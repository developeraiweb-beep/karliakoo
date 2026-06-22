<?php

require_once "config/db.php";

$category_id = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

if ($category_id <= 0) {
    header("Location: products.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Category
|--------------------------------------------------------------------------
*/
$catStmt = $conn->prepare("
    SELECT *
    FROM categories
    WHERE id = ?
    LIMIT 1
");

$catStmt->bind_param(
    "i",
    $category_id
);

$catStmt->execute();

$category = $catStmt
    ->get_result()
    ->fetch_assoc();

if (!$category) {
    header("Location: products.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/
$sort = $_GET['sort'] ?? 'latest';

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
$page = max(
    1,
    (int)($_GET['page'] ?? 1)
);

$limit = 20;
$offset = ($page - 1) * $limit;

/*
|--------------------------------------------------------------------------
| Count Products
|--------------------------------------------------------------------------
*/
$countStmt = $conn->prepare("
    SELECT COUNT(*) total
    FROM products
    WHERE category_id = ?
    AND status='active'
");

$countStmt->bind_param(
    "i",
    $category_id
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
$productStmt = $conn->prepare("
    SELECT
        p.*,
        s.shop_name
    FROM products p
    LEFT JOIN shops s
        ON p.shop_id=s.id
    WHERE p.category_id=?
    AND p.status='active'
    ORDER BY $orderBy
    LIMIT $limit OFFSET $offset
");

$productStmt->bind_param(
    "i",
    $category_id
);

$productStmt->execute();

$products =
$productStmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
<?= htmlspecialchars(
$category['category_name']
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

.category-banner{
    background:#fff;
    border-radius:12px;
    overflow:hidden;
    margin-bottom:20px;
}

.category-image{
    width:100%;
    height:300px;
    object-fit:cover;
}

.product-card{
    background:#fff;
    border-radius:12px;
    overflow:hidden;
    transition:.3s;
}

.product-card:hover{
    transform:translateY(-5px);
}

.product-image{
    width:100%;
    height:220px;
    object-fit:cover;
}

.price{
    color:#0d6efd;
    font-size:20px;
    font-weight:bold;
}

</style>

</head>

<body>

<div class="container py-4">

<!-- Breadcrumb -->

<nav>

<ol class="breadcrumb">

<li class="breadcrumb-item">

<a href="index.php">
Home
</a>

</li>

<li class="breadcrumb-item">

<a href="products.php">
Products
</a>

</li>

<li class="breadcrumb-item active">

<?= htmlspecialchars(
$category['category_name']
) ?>

</li>

</ol>

</nav>

<!-- Category Banner -->

<div class="category-banner shadow-sm">

<?php if(!empty($category['image'])): ?>

<img
src="uploads/categories/<?=
htmlspecialchars(
$category['image']
)
?>"
class="category-image">

<?php endif; ?>

<div class="p-4">

<h2>

<?= htmlspecialchars(
$category['category_name']
) ?>

</h2>

<p class="text-muted">

<?= number_format(
$totalProducts
) ?>

Products Available

</p>

</div>

</div>

<!-- Sorting -->

<div
class="d-flex justify-content-between align-items-center mb-3">

<h5>

Category Products

</h5>

<form method="GET">

<input
type="hidden"
name="id"
value="<?= $category_id ?>">

<select
name="sort"
class="form-select"
onchange="this.form.submit()">

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

<small
class="text-muted">

<?= htmlspecialchars(
$product['shop_name']
) ?>

</small>

<div class="price my-2">

TZS
<?= number_format(
$product['price']
) ?>

</div>

<a
href="product.php?id=<?=
$product['id']
?>"
class="btn btn-primary w-100">

View Product

</a>

</div>

</div>

</div>

<?php endwhile; ?>

<?php else: ?>

<div class="col-12">

<div class="alert alert-warning">

No products found in this category.

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
<?= $page==$i
? 'active'
: '' ?>">

<a
class="page-link"
href="?id=<?= $category_id ?>&page=<?= $i ?>&sort=<?= $sort ?>">

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