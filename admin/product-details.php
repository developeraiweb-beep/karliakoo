<?php

declare(strict_types=1);

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();
requireRole(['admin']);

$admin = currentUser();

$productId =
(int)($_GET['id'] ?? 0);

if($productId <= 0)
{
    $_SESSION['error'] =
    "Invalid product.";

    header(
        "Location: products.php"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD PRODUCT
|--------------------------------------------------------------------------
*/

$stmt =
$conn->prepare("
    SELECT

        p.*,

        c.category_name,

        s.shop_name,
        s.shop_slug,
        s.verified,

        u.id seller_id,
        u.full_name seller_name,
        u.email seller_email,
        u.phone seller_phone

    FROM products p

    LEFT JOIN categories c
    ON c.id = p.category_id

    LEFT JOIN shops s
    ON s.id = p.shop_id

    LEFT JOIN users u
    ON u.id = s.seller_id

    WHERE p.id = ?

    LIMIT 1
");

$stmt->bind_param(
    "i",
    $productId
);

$stmt->execute();

$product =
$stmt
->get_result()
->fetch_assoc();

if(!$product)
{
    $_SESSION['error'] =
    "Product not found.";

    header(
        "Location: products.php"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| PRODUCT STATS
|--------------------------------------------------------------------------
*/

$productViews =
(int)($product['views'] ?? 0);

$productSales =
(int)($product['sold_count'] ?? 0);

$currentStock =
(int)($product['stock'] ?? 0);

$statusColor =
match($product['status'])
{
    'active'       => 'success',
    'inactive'     => 'secondary',
    'out_of_stock' => 'danger',
    default        => 'dark'
};

/*
|--------------------------------------------------------------------------
| PRODUCT GALLERY
|--------------------------------------------------------------------------
*/

$galleryImages = [];

$galleryStmt = $conn->prepare("
    SELECT *
    FROM product_images
    WHERE product_id = ?
    ORDER BY id ASC
");

if($galleryStmt)
{
    $galleryStmt->bind_param(
        "i",
        $productId
    );

    $galleryStmt->execute();

    $galleryResult =
    $galleryStmt->get_result();

    while(
        $row =
        $galleryResult->fetch_assoc()
    )
    {
        $galleryImages[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| PRODUCT SPECIFICATIONS
|--------------------------------------------------------------------------
*/

$specifications = [];

$specStmt = $conn->prepare("
    SELECT *
    FROM product_specifications
    WHERE product_id = ?
");

if($specStmt)
{
    $specStmt->bind_param(
        "i",
        $productId
    );

    $specStmt->execute();

    $specResult =
    $specStmt->get_result();

    while(
        $row =
        $specResult->fetch_assoc()
    )
    {
        $specifications[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| PRODUCT VARIANTS
|--------------------------------------------------------------------------
*/

$variants = [];

$variantStmt = $conn->prepare("
    SELECT *
    FROM product_variants
    WHERE product_id = ?
");

if($variantStmt)
{
    $variantStmt->bind_param(
        "i",
        $productId
    );

    $variantStmt->execute();

    $variantResult =
    $variantStmt->get_result();

    while(
        $row =
        $variantResult->fetch_assoc()
    )
    {
        $variants[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| REVIEWS
|--------------------------------------------------------------------------
*/

$reviews = [];

$reviewStmt = $conn->prepare("
    SELECT
        r.*,
        u.full_name
    FROM reviews r
    LEFT JOIN users u
    ON u.id = r.user_id
    WHERE r.product_id = ?
    ORDER BY r.id DESC
");

if($reviewStmt)
{
    $reviewStmt->bind_param(
        "i",
        $productId
    );

    $reviewStmt->execute();

    $reviewResult =
    $reviewStmt->get_result();

    while(
        $row =
        $reviewResult->fetch_assoc()
    )
    {
        $reviews[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| WISHLIST COUNT
|--------------------------------------------------------------------------
*/

$wishlistCount = 0;

$wishlistQuery = $conn->prepare("
    SELECT COUNT(*) total
    FROM wishlists
    WHERE product_id = ?
");

if($wishlistQuery)
{
    $wishlistQuery->bind_param(
        "i",
        $productId
    );

    $wishlistQuery->execute();

    $wishlistCount =
    (int)$wishlistQuery
    ->get_result()
    ->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| RECENTLY VIEWED COUNT
|--------------------------------------------------------------------------
*/

$viewCount = 0;

$viewQuery = $conn->prepare("
    SELECT COUNT(*) total
    FROM recently_viewed
    WHERE product_id = ?
");

if($viewQuery)
{
    $viewQuery->bind_param(
        "i",
        $productId
    );

    $viewQuery->execute();

    $viewCount =
    (int)$viewQuery
    ->get_result()
    ->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| SELLER PERFORMANCE
|--------------------------------------------------------------------------
*/

$sellerStats = [];

$sellerQuery = $conn->prepare("
    SELECT

        COUNT(DISTINCT p.id) total_products,

        SUM(p.sold_count) total_sales,

        SUM(p.views) total_views

    FROM products p

    WHERE p.shop_id = ?
");

if($sellerQuery)
{
    $sellerQuery->bind_param(
        "i",
        $product['shop_id']
    );

    $sellerQuery->execute();

    $sellerStats =
    $sellerQuery
    ->get_result()
    ->fetch_assoc();
}

/*
|--------------------------------------------------------------------------
| AUDIT LOGS
|--------------------------------------------------------------------------
*/

$auditLogs = [];

$auditStmt = $conn->prepare("
    SELECT *
    FROM audit_logs
    ORDER BY id DESC
    LIMIT 20
");

if($auditStmt)
{
    $auditStmt->execute();

    $auditResult =
    $auditStmt->get_result();

    while(
        $row =
        $auditResult->fetch_assoc()
    )
    {
        if(
            stripos(
                $row['description'] ?? '',
                (string)$productId
            ) !== false
        )
        {
            $auditLogs[] = $row;
        }
    }
}

?>

<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Product Details

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f7fb;
}

.card{
    border:none;
    border-radius:12px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.product-image{
    width:100%;
    max-height:450px;
    object-fit:cover;
    border-radius:12px;
}

.stat-box{
    text-align:center;
    padding:20px;
}

.stat-number{
    font-size:30px;
    font-weight:bold;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between mb-4">

<div>

<h2>

<i class="fas fa-box-open"></i>

Product Details

</h2>

<p class="text-muted">

Product moderation panel

</p>

</div>

<div>

<a
href="products.php"
class="btn btn-secondary">

Back

</a>

</div>

</div>
<div class="row mb-4">

<div class="col-md-3">

<div class="card">

<div class="card-body stat-box">

<div class="stat-number text-primary">

<?= number_format(
$productViews
) ?>

</div>

<div>Views</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card">

<div class="card-body stat-box">

<div class="stat-number text-success">

<?= number_format(
$productSales
) ?>

</div>

<div>Units Sold</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card">

<div class="card-body stat-box">

<div class="stat-number text-warning">

<?= number_format(
$currentStock
) ?>

</div>

<div>Stock</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card">

<div class="card-body stat-box">

<div class="stat-number text-info">

TZS

<?= number_format(
(float)$product['price'],
2
) ?>

</div>

<div>Price</div>

</div>

</div>

</div>

</div>
<div class="row">

<div class="col-lg-8">

<div class="card mb-4">

<div class="card-header">

Product Information

</div>

<div class="card-body">

<?php

$imagePath = '';

if(!empty($product['image']))
{
    $imagePath =
    str_replace(
        '../',
        '',
        $product['image']
    );
}

?>

<?php if(!empty($imagePath)): ?>

<img
src="../<?= htmlspecialchars($imagePath) ?>"
class="product-image mb-4">

<?php endif; ?>

<h3>

<?= htmlspecialchars(
$product['name']
) ?>

</h3>

<p>

<?php if(
(int)$product['approved'] === 1
): ?>

<span class="badge bg-success">

Approved

</span>

<?php else: ?>

<span class="badge bg-warning">

Pending Approval

</span>

<?php endif; ?>

<span
class="badge bg-<?= $statusColor ?>">

<?= ucfirst(
str_replace(
'_',
' ',
$product['status']
)
) ?>

</span>

<?php if(
(int)$product['featured'] === 1
): ?>

<span class="badge bg-dark">

Featured

</span>

<?php endif; ?>

</p>

<hr>

<h6>

Short Description

</h6>

<p>

<?= nl2br(
htmlspecialchars(
$product['short_description']
?? ''
)
) ?>

</p>

<hr>

<h6>

Full Description

</h6>

<div>

<?= nl2br(
htmlspecialchars(
$product['description']
?? ''
)
) ?>

</div>

</div>

</div>

</div>
<div class="col-lg-4">

<div class="card mb-4">

<div class="card-header">

Seller Information

</div>

<div class="card-body">

<h5>

<?= htmlspecialchars(
$product['seller_name']
?? 'Unknown'
) ?>

</h5>

<p>

<?= htmlspecialchars(
$product['seller_email']
?? '-'
) ?>

</p>

<p>

<?= htmlspecialchars(
$product['seller_phone']
?? '-'
) ?>

</p>

<hr>

<strong>

Shop

</strong>

<br>

<?= htmlspecialchars(
$product['shop_name']
?? '-'
) ?>

<br>

<?php if(
(int)$product['verified'] === 1
): ?>

<span
class="badge bg-success">

Verified Shop

</span>

<?php endif; ?>

</div>

</div>
<div class="card">

<div class="card-header">

Product Summary

</div>

<div class="card-body">

<table class="table">

<tr>
<th>ID</th>
<td><?= (int)$product['id'] ?></td>
</tr>

<tr>
<th>SKU</th>
<td><?= htmlspecialchars($product['sku'] ?? '-') ?></td>
</tr>

<tr>
<th>Category</th>
<td><?= htmlspecialchars($product['category_name'] ?? '-') ?></td>
</tr>

<tr>
<th>Stock</th>
<td><?= number_format((int)$product['stock']) ?></td>
</tr>

<tr>
<th>Min Stock</th>
<td><?= number_format((int)$product['min_stock_level']) ?></td>
</tr>

<tr>
<th>Created</th>
<td><?= htmlspecialchars($product['created_at']) ?></td>
</tr>

<tr>
<th>Updated</th>
<td><?= htmlspecialchars($product['updated_at'] ?? '-') ?></td>
</tr>

</table>

</div>

</div>

</div>

</div>
<div class="card mt-4">

<div class="card-header">

Product Gallery

</div>

<div class="card-body">

<?php if(count($galleryImages) > 0): ?>

<div class="row">

<?php foreach($galleryImages as $image): ?>

<?php

$imagePath =
str_replace(
    '../',
    '',
    $image['image']
);

?>

<div class="col-md-3 mb-3">

<img
src="../<?= htmlspecialchars($imagePath) ?>"
class="img-fluid rounded border">

</div>

<?php endforeach; ?>

</div>

<?php else: ?>

<div class="alert alert-light">

No gallery images uploaded.

</div>

<?php endif; ?>

</div>

</div>
<div class="card mt-4">

<div class="card-header">

Specifications

</div>

<div class="card-body">

<?php if(count($specifications) > 0): ?>

<table class="table table-bordered">

<thead>

<tr>

<th>Attribute</th>
<th>Value</th>

</tr>

</thead>

<tbody>

<?php foreach($specifications as $spec): ?>

<tr>

<td>

<?= htmlspecialchars(
$spec['spec_name']
?? $spec['attribute']
?? '-'
) ?>

</td>

<td>

<?= htmlspecialchars(
$spec['spec_value']
?? $spec['value']
?? '-'
) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php else: ?>

<div class="alert alert-light">

No specifications found.

</div>

<?php endif; ?>

</div>

</div>
<div class="card mt-4">

<div class="card-header">

Variants

</div>

<div class="card-body">

<?php if(count($variants) > 0): ?>

<div class="table-responsive">

<table class="table table-striped">

<thead>

<tr>

<th>ID</th>
<th>Name</th>
<th>SKU</th>
<th>Price</th>
<th>Stock</th>

</tr>

</thead>

<tbody>

<?php foreach($variants as $variant): ?>

<tr>

<td>

<?= (int)$variant['id'] ?>

</td>

<td>

<?= htmlspecialchars(
$variant['variant_name']
?? $variant['name']
?? '-'
) ?>

</td>

<td>

<?= htmlspecialchars(
$variant['sku']
?? '-'
) ?>

</td>

<td>

TZS

<?= number_format(
(float)(
$variant['price']
?? 0
),
2
) ?>

</td>

<td>

<?= number_format(
(int)(
$variant['stock']
?? 0
)
) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php else: ?>

<div class="alert alert-light">

No variants found.

</div>

<?php endif; ?>

</div>

</div>
<div class="card mt-4">

<div class="card-header">

Customer Reviews

</div>

<div class="card-body">

<?php if(count($reviews) > 0): ?>

<?php foreach($reviews as $review): ?>

<div class="border rounded p-3 mb-3">

<div class="d-flex justify-content-between">

<strong>

<?= htmlspecialchars(
$review['full_name']
?? 'Customer'
) ?>

</strong>

<span>

Rating:

<?= (int)(
$review['rating']
?? 0
) ?>/5

</span>

</div>

<p class="mt-2 mb-1">

<?= nl2br(
htmlspecialchars(
$review['review']
?? ''
)
) ?>

</p>

<small class="text-muted">

<?= htmlspecialchars(
$review['created_at']
?? ''
) ?>

</small>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="alert alert-light">

No customer reviews.

</div>

<?php endif; ?>

</div>

</div>

<?php

/*
|--------------------------------------------------------------------------
| PRODUCT PERFORMANCE
|--------------------------------------------------------------------------
*/

$analyticsStmt = $conn->prepare("
    SELECT

        COUNT(DISTINCT oi.order_id) total_orders,

        SUM(oi.quantity) total_units,

        SUM(
            oi.quantity * oi.price
        ) total_revenue

    FROM order_items oi

    WHERE oi.product_id = ?
");

$analyticsStmt->bind_param(
    "i",
    $productId
);

$analyticsStmt->execute();

$analytics =
$analyticsStmt
->get_result()
->fetch_assoc();

$totalOrders =
(int)(
$analytics['total_orders']
?? 0
);

$totalUnits =
(int)(
$analytics['total_units']
?? 0
);

$totalRevenue =
(float)(
$analytics['total_revenue']
?? 0
);

?>

<div class="card mt-4">

<div class="card-header">

Sales Analytics

</div>

<div class="card-body">

<div class="row">

<div class="col-md-4">

<div class="alert alert-primary">

<h5>

<?= number_format(
$totalOrders
) ?>

</h5>

Orders

</div>

</div>

<div class="col-md-4">

<div class="alert alert-success">

<h5>

<?= number_format(
$totalUnits
) ?>

</h5>

Units Sold

</div>

</div>

<div class="col-md-4">

<div class="alert alert-warning">

<h5>

TZS

<?= number_format(
$totalRevenue,
2
) ?>

</h5>

Revenue

</div>

</div>

</div>

</div>

</div>
<div class="card mt-4">

<div class="card-header">

Customer Engagement

</div>

<div class="card-body">

<div class="row">

<div class="col-md-6">

<div class="alert alert-info">

<h4>

<?= number_format(
$wishlistCount
) ?>

</h4>

Users Added To Wishlist

</div>

</div>

<div class="col-md-6">

<div class="alert alert-primary">

<h4>

<?= number_format(
$viewCount
) ?>

</h4>

Recently Viewed Records

</div>

</div>

</div>

</div>

</div>

<div class="card mt-4">

<div class="card-header">

Seller Performance

</div>

<div class="card-body">

<table class="table">

<tr>

<th>Total Products</th>

<td>

<?= number_format(
(int)($sellerStats['total_products'] ?? 0)
) ?>

</td>

</tr>

<tr>

<th>Total Sales</th>

<td>

<?= number_format(
(int)($sellerStats['total_sales'] ?? 0)
) ?>

</td>

</tr>

<tr>

<th>Total Product Views</th>

<td>

<?= number_format(
(int)($sellerStats['total_views'] ?? 0)
) ?>

</td>

</tr>

</table>

</div>

</div>
<div class="card mt-4 border-warning">

<div class="card-header bg-warning">

Admin Moderation Center

</div>

<div class="card-body">

<div class="d-flex flex-wrap gap-2">

<?php if(
(int)$product['approved'] === 0
): ?>

<a
href="product-action.php?action=approve&id=<?= (int)$product['id'] ?>"
class="btn btn-success"
onclick="return confirm('Approve this product?')">

<i class="fas fa-check-circle"></i>

Approve Product

</a>

<?php endif; ?>

<?php if(
$product['status'] === 'active'
): ?>

<a
href="product-action.php?action=disable&id=<?= (int)$product['id'] ?>"
class="btn btn-secondary"
onclick="return confirm('Disable this product?')">

<i class="fas fa-ban"></i>

Disable

</a>

<?php else: ?>

<a
href="product-action.php?action=activate&id=<?= (int)$product['id'] ?>"
class="btn btn-primary"
onclick="return confirm('Activate this product?')">

<i class="fas fa-play"></i>

Activate

</a>

<?php endif; ?>

<?php if(
(int)$product['featured'] === 0
): ?>

<a
href="product-action.php?action=feature&id=<?= (int)$product['id'] ?>"
class="btn btn-warning"
onclick="return confirm('Feature this product?')">

<i class="fas fa-star"></i>

Feature

</a>

<?php else: ?>

<a
href="product-action.php?action=unfeature&id=<?= (int)$product['id'] ?>"
class="btn btn-dark"
onclick="return confirm('Remove featured status?')">

<i class="fas fa-star-half-alt"></i>

Unfeature

</a>

<?php endif; ?>

<a
href="edit-product.php?id=<?= (int)$product['id'] ?>"
class="btn btn-info">

<i class="fas fa-edit"></i>

Edit

</a>

<a
href="product-action.php?action=delete&id=<?= (int)$product['id'] ?>"
class="btn btn-danger"
onclick="return confirm('DELETE THIS PRODUCT? This action cannot be undone.')">

<i class="fas fa-trash"></i>

Delete

</a>

</div>

</div>

</div>
<div class="card mt-4">

<div class="card-header">

Audit History

</div>

<div class="card-body">

<?php if(count($auditLogs) > 0): ?>

<div class="table-responsive">

<table class="table table-striped">

<thead>

<tr>

<th>ID</th>
<th>Action</th>
<th>Description</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php foreach($auditLogs as $log): ?>

<tr>

<td>

<?= (int)$log['id'] ?>

</td>

<td>

<?= htmlspecialchars(
$log['action']
?? '-'
) ?>

</td>

<td>

<?= htmlspecialchars(
$log['description']
?? '-'
) ?>

</td>

<td>

<?= htmlspecialchars(
$log['created_at']
?? '-'
) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php else: ?>

<div class="alert alert-light">

No audit records found.

</div>

<?php endif; ?>

</div>

</div>
<div class="card mt-4">

<div class="card-header">

Inventory Health

</div>

<div class="card-body">

<?php

$currentStock =
(int)$product['stock'];

$minimumStock =
(int)$product['min_stock_level'];

?>

<?php if($currentStock <= 0): ?>

<div class="alert alert-danger">

Out of stock.

Immediate replenishment required.

</div>

<?php elseif(
$currentStock <= $minimumStock
): ?>

<div class="alert alert-warning">

Low inventory.

Stock level is approaching minimum threshold.

</div>

<?php else: ?>

<div class="alert alert-success">

Inventory levels are healthy.

</div>

<?php endif; ?>

</div>

</div>