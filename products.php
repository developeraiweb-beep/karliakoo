<?php

declare(strict_types=1);

session_start();

require_once "config/db.php";

/*
|--------------------------------------------------------------------------
| FILTER INPUTS
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

$category = (int)($_GET['category'] ?? 0);

$subcategory = (int)($_GET['subcategory'] ?? 0);

$sort = $_GET['sort'] ?? 'newest';

$page = max(
    1,
    (int)($_GET['page'] ?? 1)
);

$minPrice =
(float)($_GET['min_price'] ?? 0);

$maxPrice =
(float)($_GET['max_price'] ?? 0);

$featuredOnly =
isset($_GET['featured']);

$wholesaleOnly =
isset($_GET['wholesale']);

$inStockOnly =
isset($_GET['in_stock']);

/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$perPage = 20;

$offset =
($page - 1) * $perPage;

/*
|--------------------------------------------------------------------------
| LOAD CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = [];

$catStmt = $conn->prepare("
    SELECT
        id,
        category_name
    FROM categories
    WHERE status='active'
    ORDER BY category_name ASC
");

$catStmt->execute();

$catResult =
$catStmt->get_result();

while(
    $row =
    $catResult->fetch_assoc()
)
{
    $categories[] = $row;
}

/*
|--------------------------------------------------------------------------
| LOAD SUBCATEGORIES
|--------------------------------------------------------------------------
*/

$subcategories = [];

$subStmt = $conn->prepare("
    SELECT
        id,
        category_id,
        name
    FROM subcategories
    ORDER BY name ASC
");

$subStmt->execute();

$subResult =
$subStmt->get_result();

while(
    $row =
    $subResult->fetch_assoc()
)
{
    $subcategories[] = $row;
}

/*
|--------------------------------------------------------------------------
| BUILD WHERE CLAUSE
|--------------------------------------------------------------------------
*/

$where = "
    p.approved = 1
    AND p.status = 'active'
    AND p.deleted_at IS NULL
";

$params = [];
$types = '';

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if(!empty($search))
{
    $where .= "
        AND
        (
            p.name LIKE ?
            OR p.description LIKE ?
            OR p.short_description LIKE ?
        )
    ";

    $keyword =
    "%{$search}%";

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;

    $types .= "sss";
}

/*
|--------------------------------------------------------------------------
| CATEGORY
|--------------------------------------------------------------------------
*/

if($category > 0)
{
    $where .= "
        AND p.category_id = ?
    ";

    $params[] = $category;

    $types .= "i";
}

/*
|--------------------------------------------------------------------------
| SUBCATEGORY
|--------------------------------------------------------------------------
*/

if($subcategory > 0)
{
    $where .= "
        AND p.subcategory_id = ?
    ";

    $params[] = $subcategory;

    $types .= "i";
}

/*
|--------------------------------------------------------------------------
| PRICE FILTERS
|--------------------------------------------------------------------------
*/

if($minPrice > 0)
{
    $where .= "
        AND
        (
            CASE
                WHEN p.sale_price > 0
                THEN p.sale_price
                ELSE p.price
            END
        ) >= ?
    ";

    $params[] = $minPrice;

    $types .= "d";
}

if($maxPrice > 0)
{
    $where .= "
        AND
        (
            CASE
                WHEN p.sale_price > 0
                THEN p.sale_price
                ELSE p.price
            END
        ) <= ?
    ";

    $params[] = $maxPrice;

    $types .= "d";
}

/*
|--------------------------------------------------------------------------
| FEATURED FILTER
|--------------------------------------------------------------------------
*/

if($featuredOnly)
{
    $where .= "
        AND p.featured = 1
    ";
}

/*
|--------------------------------------------------------------------------
| WHOLESALE FILTER
|--------------------------------------------------------------------------
*/

if($wholesaleOnly)
{
    $where .= "
        AND p.is_wholesale = 1
    ";
}

/*
|--------------------------------------------------------------------------
| STOCK FILTER
|--------------------------------------------------------------------------
*/

if($inStockOnly)
{
    $where .= "
        AND p.stock > 0
    ";
}

/*
|--------------------------------------------------------------------------
| SORTING
|--------------------------------------------------------------------------
*/

$orderBy = "p.created_at DESC";

switch($sort)
{
    case 'price_low':

        $orderBy =
        "p.price ASC";

        break;

    case 'price_high':

        $orderBy =
        "p.price DESC";

        break;

    case 'popular':

        $orderBy =
        "p.views DESC";

        break;

    case 'sold':

        $orderBy =
        "p.sold_count DESC";

        break;
}

/*
|--------------------------------------------------------------------------
| TOTAL PRODUCTS
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*) total

    FROM products p

    WHERE {$where}
";

$countStmt =
$conn->prepare($countSql);

if(!empty($params))
{
    $countStmt->bind_param(
        $types,
        ...$params
    );
}

$countStmt->execute();

$totalProducts =
(int)
$countStmt
->get_result()
->fetch_assoc()['total'];

$totalPages =
max(
    1,
    (int)ceil(
        $totalProducts /
        $perPage
    )
);

/*
|--------------------------------------------------------------------------
| LOAD PRODUCTS
|--------------------------------------------------------------------------
*/

$productSql = "
SELECT

    p.*,

    c.category_name,

    s.shop_name,

    IFNULL(
        rv.avg_rating,
        0
    ) AS avg_rating,

    IFNULL(
        rv.total_reviews,
        0
    ) AS total_reviews

FROM products p

LEFT JOIN categories c
ON c.id = p.category_id

LEFT JOIN shops s
ON s.id = p.shop_id

LEFT JOIN
(
    SELECT

        product_id,

        AVG(rating)
        AS avg_rating,

        COUNT(*)
        AS total_reviews

    FROM reviews

    GROUP BY product_id

) rv

ON rv.product_id = p.id

WHERE {$where}

ORDER BY {$orderBy}

LIMIT {$offset}, {$perPage}
";

$productStmt =
$conn->prepare($productSql);

if(!empty($params))
{
    $productStmt->bind_param(
        $types,
        ...$params
    );
}

$productStmt->execute();

$products =
$productStmt
->get_result();

/*
|--------------------------------------------------------------------------
| TRENDING PRODUCTS
|--------------------------------------------------------------------------
*/

$trendingProducts =
mysqli_query(
    $conn,
    "
    SELECT

        id,
        name,
        slug,
        image,
        price,
        sale_price

    FROM products

    WHERE
        approved = 1
        AND status='active'

    ORDER BY views DESC

    LIMIT 8
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

<title>Products</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">

<style>

body{
    background:#f8f9fa;
}

.product-card{
    border:none;
    overflow:hidden;
    transition:.25s;
    box-shadow:0 2px 12px rgba(0,0,0,.08);
}

.product-card:hover{
    transform:translateY(-4px);
}

.product-image{
    width:100%;
    height:230px;
    object-fit:cover;
}

.sale-price{
    color:#dc3545;
    font-weight:700;
    font-size:18px;
}

.old-price{
    text-decoration:line-through;
    color:#6c757d;
    font-size:13px;
}

.featured-badge{
    position:absolute;
    top:10px;
    left:10px;
    z-index:5;
}

.wholesale-badge{
    position:absolute;
    top:10px;
    right:10px;
    z-index:5;
}

.discount-badge{
    position:absolute;
    top:48px;
    left:10px;
    z-index:5;
}

.sidebar-card{
    margin-bottom:20px;
}

@media(max-width:768px)
{
    .product-image{
        height:180px;
    }
}

</style>

</head>

<body>

<div class="container py-4">

<div class="row">

<!-- SIDEBAR -->

<div class="col-lg-3 mb-4">

<div class="card sidebar-card">

<div class="card-header">

Categories

</div>

<div class="list-group list-group-flush">

<a
href="products.php"
class="list-group-item">

All Products

</a>

<?php foreach($categories as $cat): ?>

<a
href="?category=<?= (int)$cat['id'] ?>"
class="list-group-item <?= $category == $cat['id'] ? 'active' : '' ?>">

<?= htmlspecialchars($cat['category_name']) ?>

</a>

<?php endforeach; ?>

</div>

</div>

<div class="card sidebar-card">

<div class="card-header">

Subcategories

</div>

<div class="list-group list-group-flush">

<?php foreach($subcategories as $sub): ?>

<a
href="?subcategory=<?= (int)$sub['id'] ?>"
class="list-group-item <?= $subcategory == $sub['id'] ? 'active' : '' ?>">

<?= htmlspecialchars($sub['name']) ?>

</a>

<?php endforeach; ?>

</div>

</div>

<div class="card">

<div class="card-header">

Advanced Filters

</div>

<div class="card-body">

<form method="GET">

<input
type="hidden"
name="category"
value="<?= $category ?>">

<input
type="hidden"
name="subcategory"
value="<?= $subcategory ?>">

<div class="mb-3">

<label class="form-label">

Minimum Price

</label>

<input
type="number"
name="min_price"
value="<?= $minPrice ?>"
class="form-control">

</div>

<div class="mb-3">

<label class="form-label">

Maximum Price

</label>

<input
type="number"
name="max_price"
value="<?= $maxPrice ?>"
class="form-control">

</div>

<div class="form-check">

<input
class="form-check-input"
type="checkbox"
name="featured"
<?= $featuredOnly ? 'checked' : '' ?>>

<label class="form-check-label">

Featured

</label>

</div>

<div class="form-check">

<input
class="form-check-input"
type="checkbox"
name="wholesale"
<?= $wholesaleOnly ? 'checked' : '' ?>>

<label class="form-check-label">

Wholesale

</label>

</div>

<div class="form-check">

<input
class="form-check-input"
type="checkbox"
name="in_stock"
<?= $inStockOnly ? 'checked' : '' ?>>

<label class="form-check-label">

In Stock

</label>

</div>

<button
class="btn btn-primary w-100 mt-3">

Apply Filters

</button>

</form>

</div>

</div>

</div>

<!-- CONTENT -->

<div class="col-lg-9">

<h2 class="mb-3">

Marketplace Products

</h2>

<div class="card mb-4">

<div class="card-body">

<form method="GET">

<div class="row g-2">

<div class="col-md-5">

<input
type="text"
name="search"
value="<?= htmlspecialchars($search) ?>"
placeholder="Search products..."
class="form-control">

</div>

<div class="col-md-3">

<select
name="sort"
class="form-select">

<option value="newest">

Newest

</option>

<option
value="price_low"
<?= $sort=='price_low'?'selected':'' ?>>

Price Low → High

</option>

<option
value="price_high"
<?= $sort=='price_high'?'selected':'' ?>>

Price High → Low

</option>

<option
value="popular"
<?= $sort=='popular'?'selected':'' ?>>

Most Viewed

</option>

<option
value="sold"
<?= $sort=='sold'?'selected':'' ?>>

Best Selling

</option>

</select>

</div>

<div class="col-md-2">

<input
type="hidden"
name="category"
value="<?= $category ?>">

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

</div>

<div class="alert alert-light">

<strong>

<?= number_format($totalProducts) ?>

</strong>

Products Found

</div>

<div class="row">

<?php while($product = $products->fetch_assoc()): ?>

<?php

$image =
$product['featured_image']
?: $product['image'];

if(empty($image))
{
    $image =
    "assets/images/no-image.jpg";
}

$discount = 0;

if(
    !empty($product['sale_price'])
    &&
    $product['sale_price'] > 0
)
{
    $discount =
    round(
        (
            (
                $product['price']
                -
                $product['sale_price']
            )
            /
            $product['price']
        ) * 100
    );
}

?>

<div class="col-lg-4 col-md-6 mb-4">

<div class="card product-card h-100">

<div class="position-relative">

<?php if((int)$product['featured'] === 1): ?>

<span
class="badge bg-warning featured-badge">

Featured

</span>

<?php endif; ?>

<?php if((int)$product['is_wholesale'] === 1): ?>

<span
class="badge bg-success wholesale-badge">

Wholesale

</span>

<?php endif; ?>

<?php if($discount > 0): ?>

<span
class="badge bg-danger discount-badge">

-<?= $discount ?>%

</span>

<?php endif; ?>

<img
src="<?= htmlspecialchars($image) ?>"
class="product-image"
alt="<?= htmlspecialchars($product['name']) ?>">

</div>

<div class="card-body">

<div class="small text-muted mb-1">

<?= htmlspecialchars($product['shop_name'] ?? 'Unknown Shop') ?>

</div>

<h6>

<?= htmlspecialchars($product['name']) ?>

</h6>

<div class="small text-secondary mb-2">

<?= htmlspecialchars($product['category_name'] ?? '') ?>

</div>

<div class="mb-2">

<?php

$rating =
round(
(float)$product['avg_rating']
);

for($i=1;$i<=5;$i++):

?>

<i
class="bi bi-star<?= $i <= $rating ? '-fill' : '' ?> text-warning"></i>

<?php endfor; ?>

<small class="text-muted">

(<?= (int)$product['total_reviews'] ?>)

</small>

</div>

<?php if(
!empty($product['sale_price'])
&&
$product['sale_price'] > 0
): ?>

<div class="sale-price">

TZS
<?= number_format((float)$product['sale_price']) ?>

</div>

<div class="old-price">

TZS
<?= number_format((float)$product['price']) ?>

</div>

<?php else: ?>

<div class="sale-price">

TZS
<?= number_format((float)$product['price']) ?>

</div>

<?php endif; ?>

<div class="small mt-2">

Stock:
<?= (int)$product['stock'] ?>

</div>

</div>

<div class="card-footer bg-white">

<div class="d-flex gap-2">

<a
href="product-details.php?id=<?= (int)$product['id'] ?>"
class="btn btn-primary flex-fill">

View

</a>

<a
href="wishlist-add.php?id=<?= (int)$product['id'] ?>"
class="btn btn-outline-danger">

<i class="bi bi-heart"></i>

</a>

<a
href="compare-add.php?id=<?= (int)$product['id'] ?>"
class="btn btn-outline-dark">

<i class="bi bi-bar-chart"></i>

</a>

</div>

</div>

</div>

</div>

<?php endwhile; ?>

</div>

</div> <!-- End Products Row -->

<!-- PAGINATION -->

<?php if($totalPages > 1): ?>

<nav class="mt-4">

<ul class="pagination justify-content-center">

<?php for($i=1; $i<=$totalPages; $i++): ?>

<li
class="page-item <?= $page == $i ? 'active' : '' ?>">

<a
class="page-link"
href="?page=<?= $i ?>
&search=<?= urlencode($search) ?>
&category=<?= $category ?>
&subcategory=<?= $subcategory ?>
&sort=<?= urlencode($sort) ?>
&min_price=<?= $minPrice ?>
&max_price=<?= $maxPrice ?>">

<?= $i ?>

</a>

</li>

<?php endfor; ?>

</ul>

</nav>

<?php endif; ?>

</div> <!-- End Content -->
</div> <!-- End Main Row -->

<hr class="my-5">

<!-- TRENDING PRODUCTS -->

<h3 class="mb-4">

 Trending Products

</h3>

<div class="row">

<?php while($trend = mysqli_fetch_assoc($trendingProducts)): ?>

<?php

$trendImage =
$trend['image']
?: 'assets/images/no-image.jpg';

?>

<div class="col-lg-3 col-md-4 col-6 mb-4">

<div class="card h-100">

<img
src="<?= htmlspecialchars($trendImage) ?>"
style="
height:180px;
object-fit:cover;
">

<div class="card-body">

<h6>

<?= htmlspecialchars($trend['name']) ?>

</h6>

<strong>

TZS

<?= number_format(
(float)(
$trend['sale_price']
?: $trend['price']
)
) ?>

</strong>

</div>

<a
href="product-details.php?id=<?= (int)$trend['id'] ?>"
class="btn btn-outline-primary btn-sm m-2">

View Product

</a>

</div>

</div>

<?php endwhile; ?>

</div>

<?php

/*
|--------------------------------------------------------------------------
| RECOMMENDED PRODUCTS
|--------------------------------------------------------------------------
*/

$recommendedProducts =
mysqli_query(
$conn,
"
SELECT
id,
name,
image,
price,
sale_price
FROM products
WHERE
approved=1
AND status='active'
AND featured=1
ORDER BY RAND()
LIMIT 8
"
);

?>

<hr class="my-5">

<h3 class="mb-4">

 Recommended For You

</h3>

<div class="row">

<?php while($rec = mysqli_fetch_assoc($recommendedProducts)): ?>

<div class="col-lg-3 col-md-4 col-6 mb-4">

<div class="card h-100">

<img
src="<?= htmlspecialchars(
$rec['image']
?: 'assets/images/no-image.jpg'
) ?>"
style="
height:180px;
object-fit:cover;
">

<div class="card-body">

<h6>

<?= htmlspecialchars($rec['name']) ?>

</h6>

<strong>

TZS

<?= number_format(
(float)(
$rec['sale_price']
?: $rec['price']
)
) ?>

</strong>

</div>

<a
href="product-details.php?id=<?= (int)$rec['id'] ?>"
class="btn btn-outline-success btn-sm m-2">

View Product

</a>

</div>

</div>

<?php endwhile; ?>

</div>

<?php

/*
|--------------------------------------------------------------------------
| RECENTLY VIEWED
|--------------------------------------------------------------------------
*/

$recentIds =
$_SESSION['recently_viewed']
?? [];

if(!empty($recentIds))
{
    $recentIds =
    array_map(
        'intval',
        $recentIds
    );

    $ids =
    implode(
        ',',
        $recentIds
    );

    $recentProducts =
    mysqli_query(
        $conn,
        "
        SELECT
            id,
            name,
            image,
            price
        FROM products
        WHERE id IN ($ids)
        LIMIT 8
        "
    );
?>

<hr class="my-5">

<h3 class="mb-4">

🕒 Continue where you left

</h3>

<div class="row">

<?php while($recent = mysqli_fetch_assoc($recentProducts)): ?>

<div class="col-lg-3 col-md-4 col-6 mb-4">

<div class="card h-100">

<img
src="<?= htmlspecialchars(
$recent['image']
?: 'assets/images/no-image.jpg'
) ?>"
style="
height:180px;
object-fit:cover;
">

<div class="card-body">

<h6>

<?= htmlspecialchars(
$recent['name']
) ?>

</h6>

<strong>

TZS

<?= number_format(
(float)$recent['price']
) ?>

</strong>

</div>

<a
href="product-details.php?id=<?= (int)$recent['id'] ?>"
class="btn btn-outline-secondary btn-sm m-2">

View Again

</a>

</div>

</div>

<?php endwhile; ?>

</div>

<?php } ?>

</div> <!-- Container -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


</body>
</html>