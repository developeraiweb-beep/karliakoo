<?php

require_once "config/db.php";

$keyword = trim($_GET['q'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$shop = (int)($_GET['shop'] ?? 0);
$sort = $_GET['sort'] ?? 'latest';

$min_price = isset($_GET['min_price'])
    ? (float)$_GET['min_price']
    : 0;

$max_price = isset($_GET['max_price'])
    ? (float)$_GET['max_price']
    : 999999999;

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/
$categories = [];

$catQuery = $conn->query("
    SELECT id, category_name
    FROM categories
    WHERE status='active'
    ORDER BY category_name ASC
");

while ($row = $catQuery->fetch_assoc()) {
    $categories[] = $row;
}

/*
|--------------------------------------------------------------------------
| Shops
|--------------------------------------------------------------------------
*/
$shops = [];

$shopQuery = $conn->query("
    SELECT id, shop_name
    FROM shops
    WHERE status='approved'
    ORDER BY shop_name ASC
");

while ($row = $shopQuery->fetch_assoc()) {
    $shops[] = $row;
}

/*
|--------------------------------------------------------------------------
| Search Conditions
|--------------------------------------------------------------------------
*/
$where = " WHERE p.status='active' ";
$params = [];
$types = '';

if (!empty($keyword)) {

    $where .= "
        AND (
            p.name LIKE ?
            OR p.description LIKE ?
        )
    ";

    $searchTerm = "%{$keyword}%";

    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

if ($category > 0) {

    $where .= "
        AND p.category_id = ?
    ";

    $params[] = $category;
    $types .= "i";
}

if ($shop > 0) {

    $where .= "
        AND p.shop_id = ?
    ";

    $params[] = $shop;
    $types .= "i";
}

$where .= "
    AND p.price BETWEEN ? AND ?
";

$params[] = $min_price;
$params[] = $max_price;
$types .= "dd";

/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/
$orderBy = "p.id DESC";

switch ($sort) {

    case 'price_low':
        $orderBy = "p.price ASC";
        break;

    case 'price_high':
        $orderBy = "p.price DESC";
        break;

    case 'name':
        $orderBy = "p.name ASC";
        break;

    case 'popular':
        $orderBy = "p.views DESC";
        break;
}

/*
|--------------------------------------------------------------------------
| Count Results
|--------------------------------------------------------------------------
*/
$countSql = "
    SELECT COUNT(*) total
    FROM products p
    $where
";

$countStmt = $conn->prepare($countSql);

if (!empty($params)) {
    $countStmt->bind_param(
        $types,
        ...$params
    );
}

$countStmt->execute();

$totalResults = $countStmt
    ->get_result()
    ->fetch_assoc()['total'];

$totalPages = ceil(
    $totalResults / $limit
);

/*
|--------------------------------------------------------------------------
| Fetch Products
|--------------------------------------------------------------------------
*/
$sql = "
SELECT
    p.*,
    s.shop_name,
    c.category_name
FROM products p
LEFT JOIN shops s
    ON p.shop_id=s.id
LEFT JOIN categories c
    ON p.category_id=c.id
$where
ORDER BY $orderBy
LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$products = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>
Search Results | Karliakoo
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

.filter-box{
    background:#fff;
    padding:20px;
    border-radius:12px;
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

<h2 class="mb-4">

Search Products

</h2>

<div class="row">

<!-- FILTERS -->

<div class="col-lg-3 mb-4">

<div class="filter-box">

<form method="GET">

<input
type="text"
name="q"
value="<?= htmlspecialchars($keyword) ?>"
class="form-control mb-3"
placeholder="Search products">

<select
name="category"
class="form-select mb-3">

<option value="0">
All Categories
</option>

<?php foreach($categories as $cat): ?>

<option
value="<?= $cat['id'] ?>"
<?= $category == $cat['id']
? 'selected'
: '' ?>>

<?= htmlspecialchars(
$cat['category_name']
) ?>

</option>

<?php endforeach; ?>

</select>

<select
name="shop"
class="form-select mb-3">

<option value="0">
All Shops
</option>

<?php foreach($shops as $s): ?>

<option
value="<?= $s['id'] ?>"
<?= $shop == $s['id']
? 'selected'
: '' ?>>

<?= htmlspecialchars(
$s['shop_name']
) ?>

</option>

<?php endforeach; ?>

</select>

<input
type="number"
name="min_price"
value="<?= $min_price ?>"
class="form-control mb-2"
placeholder="Min Price">

<input
type="number"
name="max_price"
value="<?= $max_price ?>"
class="form-control mb-3"
placeholder="Max Price">

<select
name="sort"
class="form-select mb-3">

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

<button
class="btn btn-primary w-100">

Apply Filters

</button>

</form>

</div>

</div>

<!-- RESULTS -->

<div class="col-lg-9">

<div class="mb-3">

<strong>

<?= number_format(
$totalResults
) ?>

Products Found

</strong>

</div>

<div class="row">

<?php if(
$products->num_rows > 0
): ?>

<?php while(
$product =
$products->fetch_assoc()
): ?>

<div class="col-md-4 mb-4">

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
href="product-details.php?id=<?=
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

No matching products found.

</div>

</div>

<?php endif; ?>

</div>

<!-- PAGINATION -->

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
href="?<?= http_build_query(
array_merge(
$_GET,
['page'=>$i]
)
) ?>">

<?= $i ?>

</a>

</li>

<?php endfor; ?>

</ul>

</nav>

<?php endif; ?>

</div>

</div>

</div>

</body>
</html>