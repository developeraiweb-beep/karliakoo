<?php

declare(strict_types=1);

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

if (!$user)
{
    header("Location: ../login.php");
    exit;
}

$sellerId = (int)$user['id'];

$productId =
(int)($_GET['id'] ?? 0);

if ($productId <= 0)
{
    die("Invalid product.");
}

/*
|--------------------------------------------------------------------------
| VERIFY SHOP
|--------------------------------------------------------------------------
*/

$shopStmt = $conn->prepare("
    SELECT
        id,
        shop_name,
        status,
        suspended
    FROM shops
    WHERE seller_id = ?
    LIMIT 1
");

$shopStmt->bind_param(
    "i",
    $sellerId
);

$shopStmt->execute();

$shop =
$shopStmt
->get_result()
->fetch_assoc();

if (!$shop)
{
    die("Shop not found.");
}

if (
    $shop['status'] !== 'approved'
)
{
    die("Shop not approved.");
}

if (
    (int)$shop['suspended'] === 1
)
{
    die("Shop suspended.");
}

$shopId =
(int)$shop['id'];

/*
|--------------------------------------------------------------------------
| PRODUCT DETAILS
|--------------------------------------------------------------------------
*/

$productStmt = $conn->prepare("
    SELECT

        p.*,

        c.category_name

    FROM products p

    LEFT JOIN categories c
    ON c.id = p.category_id

    WHERE p.id = ?
    AND p.shop_id = ?
    AND p.deleted_at IS NULL

    LIMIT 1
");

$productStmt->bind_param(
    "ii",
    $productId,
    $shopId
);

$productStmt->execute();

$product =
$productStmt
->get_result()
->fetch_assoc();

if (!$product)
{
    die("Product not found.");
}

/*
|--------------------------------------------------------------------------
| PRODUCT METRICS
|--------------------------------------------------------------------------
*/

$productStats = [

    'views' =>
    (int)($product['views'] ?? 0),

    'sold_count' =>
    (int)($product['sold_count'] ?? 0),

    'stock' =>
    (int)($product['stock'] ?? 0),

    'revenue' =>
    (
        (float)$product['price']
        *
        (int)$product['sold_count']
    )
];

/*
|--------------------------------------------------------------------------
| LOW STOCK CHECK
|--------------------------------------------------------------------------
*/

$isLowStock =
(
    (int)$product['stock']
    <=
    (int)$product['min_stock_level']
);

/*
|--------------------------------------------------------------------------
| STATUS COLORS
|--------------------------------------------------------------------------
*/

$statusColors = [

    'active' => 'success',
    'inactive' => 'secondary',
    'out_of_stock' => 'danger'
];

$statusColor =
$statusColors[
    $product['status']
] ?? 'secondary';

$approvalBadge =
(
    (int)$product['approved'] === 1
)
?
'<span class="badge bg-success">Approved</span>'
:
'<span class="badge bg-warning text-dark">Pending Approval</span>';

$featuredBadge =
(
    (int)$product['featured'] === 1
)
?
'<span class="badge bg-primary">Featured</span>'
:
'';

$wholesaleBadge =
(
    (int)$product['is_wholesale'] === 1
)
?
'<span class="badge bg-info">Wholesale</span>'
:
'';
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

<?= htmlspecialchars($product['name']) ?>

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

.info-card{
    border:none;
    border-radius:16px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.product-image{
    width:100%;
    max-height:420px;
    object-fit:cover;
    border-radius:14px;
}

.metric-card{
    border:none;
    border-radius:15px;
    text-align:center;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.metric-number{
    font-size:1.6rem;
    font-weight:700;
}

</style>

</head>

<body>

<div class="container py-4">

<div
class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>

<?= htmlspecialchars($product['name']) ?>

</h2>

<p class="text-muted mb-0">

SKU:
<?= htmlspecialchars($product['sku']) ?>

</p>

</div>

<div>

<a
href="products.php"
class="btn btn-secondary">

<i class="fas fa-arrow-left"></i>

Back

</a>

<a
href="edit-product.php?id=<?= (int)$product['id'] ?>"
class="btn btn-warning">

<i class="fas fa-edit"></i>

Edit

</a>

</div>

</div>

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="card metric-card">

<div class="card-body">

<div class="metric-number">

<?= number_format(
(int)$productStats['views']
) ?>

</div>

<div>

Views

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card metric-card">

<div class="card-body">

<div class="metric-number">

<?= number_format(
(int)$productStats['sold_count']
) ?>

</div>

<div>

Units Sold

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card metric-card">

<div class="card-body">

<div class="metric-number">

<?= number_format(
(int)$productStats['stock']
) ?>

</div>

<div>

In Stock

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card metric-card">

<div class="card-body">

<div class="metric-number">

TZS

<?= number_format(
(float)$productStats['revenue'],
2
) ?>

</div>

<div>

Revenue

</div>

</div>

</div>

</div>

</div>

<div class="row">

<div class="col-lg-5">

<div class="card info-card mb-4">

<div class="card-body">

<?php if(!empty($product['image'])): ?>

<img
src="<?= htmlspecialchars($product['image']) ?>"
class="product-image">

<?php else: ?>

<div
class="bg-light rounded p-5 text-center">

<i
class="fas fa-image fa-4x text-muted">
</i>

</div>

<?php endif; ?>

</div>

</div>

</div>

<div class="col-lg-7">

<div class="card info-card">

<div class="card-body">

<h4 class="mb-3">

Product Information

</h4>

<p>

<?= $approvalBadge ?>

<?= $featuredBadge ?>

<?= $wholesaleBadge ?>

</p>

<table class="table">

<tr>

<th width="220">

Category

</th>

<td>

<?= htmlspecialchars(
$product['category_name']
?? 'N/A'
) ?>

</td>

</tr>

<tr>

<th>

Status

</th>

<td>

<span
class="badge bg-<?= $statusColor ?>">

<?= ucfirst(
str_replace(
'_',
' ',
(string)$product['status']
)
) ?>

</span>

</td>

</tr>

<tr>

<th>

Price

</th>

<td>

TZS

<?= number_format(
(float)$product['price'],
2
) ?>

</td>

</tr>

<tr>

<th>

Sale Price

</th>

<td>

<?php if(!empty($product['sale_price'])): ?>

TZS

<?= number_format(
(float)$product['sale_price'],
2
) ?>

<?php else: ?>

-

<?php endif; ?>

</td>

</tr>

<tr>

<th>

Stock

</th>

<td>

<?php if($isLowStock): ?>

<span class="badge bg-danger">

Low Stock

</span>

<?php endif; ?>

<?= number_format(
(int)$product['stock']
) ?>

</td>

</tr>

<tr>

<th>

Minimum Stock

</th>

<td>

<?= number_format(
(int)$product['min_stock_level']
) ?>

</td>

</tr>

<tr>

<th>

Weight

</th>

<td>

<?= htmlspecialchars(
(string)$product['weight']
) ?>

KG

</td>

</tr>

<tr>

<th>

Minimum Order Qty

</th>

<td>

<?= number_format(
(int)$product['minimum_order_qty']
) ?>

</td>

</tr>

<tr>

<th>

Wholesale Price

</th>

<td>

TZS

<?= number_format(
(float)$product['wholesale_price'],
2
) ?>

</td>

</tr>

<tr>

<th>

Created

</th>

<td>

<?= htmlspecialchars(
(string)$product['created_at']
) ?>

</td>

</tr>

<tr>

<th>

Last Updated

</th>

<td>

<?= htmlspecialchars(
(string)$product['updated_at']
) ?>

</td>

</tr>

</table>

</div>

</div>

</div>

</div>

<div class="row mt-4">

    <!-- DESCRIPTION -->

    <div class="col-lg-8">

        <div class="card info-card mb-4">

            <div class="card-header bg-white">
                <h5 class="mb-0">
                    Product Description
                </h5>
            </div>

            <div class="card-body">

                <?php if(!empty($product['short_description'])): ?>

                    <div class="alert alert-light border">

                        <strong>
                            Short Description
                        </strong>

                        <hr>

                        <?= nl2br(
                            htmlspecialchars(
                                $product['short_description']
                            )
                        ) ?>

                    </div>

                <?php endif; ?>

                <?php if(!empty($product['description'])): ?>

                    <?= nl2br(
                        htmlspecialchars(
                            $product['description']
                        )
                    ) ?>

                <?php else: ?>

                    <p class="text-muted">
                        No description available.
                    </p>

                <?php endif; ?>

            </div>

        </div>

        <!-- PRODUCT PERFORMANCE -->

        <div class="card info-card">

            <div class="card-header bg-white">

                <h5 class="mb-0">
                    Product Performance
                </h5>

            </div>

            <div class="card-body">

                <?php

                $views =
                max(
                    1,
                    (int)$product['views']
                );

                $sales =
                (int)$product['sold_count'];

                $conversionRate =
                round(
                    ($sales / $views) * 100,
                    2
                );

                ?>

                <div class="row">

                    <div class="col-md-4">

                        <div class="border rounded p-3 text-center">

                            <h4>

                                <?= number_format(
                                    $views
                                ) ?>

                            </h4>

                            <small>
                                Total Views
                            </small>

                        </div>

                    </div>

                    <div class="col-md-4">

                        <div class="border rounded p-3 text-center">

                            <h4>

                                <?= number_format(
                                    $sales
                                ) ?>

                            </h4>

                            <small>
                                Units Sold
                            </small>

                        </div>

                    </div>

                    <div class="col-md-4">

                        <div class="border rounded p-3 text-center">

                            <h4>

                                <?= $conversionRate ?>%

                            </h4>

                            <small>
                                Conversion Rate
                            </small>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- SIDEBAR -->

    <div class="col-lg-4">

        <!-- INVENTORY HEALTH -->

        <div class="card info-card mb-4">

            <div class="card-header bg-white">

                <h5 class="mb-0">
                    Inventory Health
                </h5>

            </div>

            <div class="card-body">

                <?php

                $stock =
                (int)$product['stock'];

                $minStock =
                (int)$product['min_stock_level'];

                if($stock <= 0)
                {
                    $health = "Out of Stock";
                    $healthClass = "danger";
                }
                elseif($stock <= $minStock)
                {
                    $health = "Low Stock";
                    $healthClass = "warning";
                }
                else
                {
                    $health = "Healthy";
                    $healthClass = "success";
                }

                ?>

                <div class="text-center">

                    <span
                    class="badge bg-<?= $healthClass ?> fs-6">

                        <?= $health ?>

                    </span>

                </div>

                <hr>

                <table class="table">

                    <tr>
                        <th>Available</th>
                        <td>
                            <?= number_format($stock) ?>
                        </td>
                    </tr>

                    <tr>
                        <th>Minimum</th>
                        <td>
                            <?= number_format($minStock) ?>
                        </td>
                    </tr>

                    <tr>
                        <th>Sold</th>
                        <td>
                            <?= number_format(
                                (int)$product['sold_count']
                            ) ?>
                        </td>
                    </tr>

                </table>

            </div>

        </div>

        <!-- PRODUCT TIMELINE -->

        <div class="card info-card">

            <div class="card-header bg-white">

                <h5 class="mb-0">
                    Product Timeline
                </h5>

            </div>

            <div class="card-body">

                <ul class="list-group list-group-flush">

                    <li class="list-group-item">

                        <strong>
                            Created
                        </strong>

                        <br>

                        <?= htmlspecialchars(
                            (string)$product['created_at']
                        ) ?>

                    </li>

                    <?php if(!empty($product['updated_at'])): ?>

                    <li class="list-group-item">

                        <strong>
                            Last Updated
                        </strong>

                        <br>

                        <?= htmlspecialchars(
                            (string)$product['updated_at']
                        ) ?>

                    </li>

                    <?php endif; ?>

                    <li class="list-group-item">

                        <strong>
                            Approval Status
                        </strong>

                        <br>

                        <?= (int)$product['approved'] === 1
                            ? 'Approved'
                            : 'Pending Approval'
                        ?>

                    </li>

                </ul>

            </div>

        </div>

    </div>

</div>