<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$shopId = (int)($_GET['shop_id'] ?? 0);

if ($shopId <= 0) {
    die("Invalid shop.");
}

/*
|--------------------------------------------------------------------------
| SHOP DETAILS
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT

s.*,

u.id AS seller_id,
u.full_name,
u.email,
u.phone,
u.created_at AS joined_at

FROM shops s

LEFT JOIN users u
ON u.id=s.seller_id

WHERE s.id=?

LIMIT 1
");

$stmt->bind_param("i", $shopId);
$stmt->execute();

$shop =
$stmt
->get_result()
->fetch_assoc();

if (!$shop) {
    die("Shop not found.");
}

/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $status = $_POST['status'];
    $verified = (int)$_POST['verified'];
    $suspended = (int)$_POST['suspended'];

    $update = $conn->prepare("
        UPDATE shops
        SET

        status=?,
        verified=?,
        suspended=?

        WHERE id=?
    ");

    $update->bind_param(
        "siii",
        $status,
        $verified,
        $suspended,
        $shopId
    );

    $update->execute();

    header(
        "Location: shop-details.php?id={$shopId}&updated=1"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| PRODUCT STATS
|--------------------------------------------------------------------------
*/
$productStats = $conn->prepare("
SELECT

COUNT(*) total_products,

SUM(status='active')
active_products

FROM products

WHERE shop_id=?
");

$productStats->bind_param(
    "i",
    $shop['id']
);

$productStats->execute();

$productStats =
$productStats
->get_result()
->fetch_assoc();

/*
|--------------------------------------------------------------------------
| ORDER STATS
|--------------------------------------------------------------------------
*/
$orderStats = [

'total_orders'=>0,
'total_revenue'=>0

];

$orderTable =
$conn->query(
"SHOW TABLES LIKE 'orders'"
);

if ($orderTable->num_rows) {

    $stmt = $conn->prepare("
        SELECT

        COUNT(*) total_orders,

        COALESCE(
        SUM(total_amount),
        0
        ) total_revenue

        FROM orders

        WHERE shop_id=?
    ");

    $stmt->bind_param(
        "i",
        $shopId
    );

    $stmt->execute();

    $orderStats =
    $stmt
    ->get_result()
    ->fetch_assoc();
}

/*
|--------------------------------------------------------------------------
| RECENT PRODUCTS
|--------------------------------------------------------------------------
*/
$products = $conn->prepare("
SELECT

id,
product_name,
price,
stock_quantity,
status

FROM products

WHERE shop_id=?

ORDER BY id DESC

LIMIT 15
");

$products->bind_param(
    "i",
    $shop['id']
);

$products->execute();

$products =
$products->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width,initial-scale=1">

<title>

Shop Details

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
background:#f5f6fa;
}

.card-box{
background:#fff;
padding:20px;
border-radius:12px;
}

.shop-logo{
width:100px;
height:100px;
border-radius:50%;
object-fit:cover;
}

.metric{
font-size:24px;
font-weight:700;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<a
href="shops.php"
class="btn btn-secondary mb-3">

← Back to Shops

</a>

<?php if(isset($_GET['updated'])): ?>

<div class="alert alert-success">

Shop updated successfully.

</div>

<?php endif; ?>

<div class="row">

<!-- LEFT -->

<div class="col-lg-4">

<div class="card-box shadow-sm mb-4">

<?php if(!empty($shop['logo'])): ?>

<img
src="../uploads/shops/<?= htmlspecialchars($shop['logo']) ?>"
class="shop-logo mb-3">

<?php endif; ?>

<h4>

<?= htmlspecialchars(
$shop['shop_name']
) ?>

</h4>

<p>

<?= nl2br(
htmlspecialchars(
$shop['description']
)
) ?>

</p>

<hr>

<p>

<strong>Owner:</strong>

<?= htmlspecialchars(
$shop['full_name']
) ?>

</p>

<p>

<strong>Email:</strong>

<?= htmlspecialchars(
$shop['email']
) ?>

</p>

<p>

<strong>Phone:</strong>

<?= htmlspecialchars(
$shop['phone']
) ?>

</p>

<p>

<strong>City:</strong>

<?= htmlspecialchars(
$shop['city']
) ?>

</p>

<p>

<strong>Region:</strong>

<?= htmlspecialchars(
$shop['region']
) ?>

</p>

<p>

<strong>Followers:</strong>

<?= number_format(
$shop['followers']
) ?>

</p>

<p>

<strong>Views:</strong>

<?= number_format(
$shop['views']
) ?>

</p>

</div>

<div class="card-box shadow-sm">

<h5>

Shop Controls

</h5>

<hr>

<form method="POST">

<div class="mb-3">

<label>Status</label>

<select
name="status"
class="form-select">

<option
value="pending"
<?= $shop['status']=='pending' ? 'selected':'' ?>>

Pending

</option>

<option
value="approved"
<?= $shop['status']=='approved' ? 'selected':'' ?>>

Approved

</option>

<option
value="rejected"
<?= $shop['status']=='rejected' ? 'selected':'' ?>>

Rejected

</option>

</select>

</div>

<div class="mb-3">

<label>Verification</label>

<select
name="verified"
class="form-select">

<option
value="1"
<?= $shop['verified'] ? 'selected':'' ?>>

Verified

</option>

<option
value="0"
<?= !$shop['verified'] ? 'selected':'' ?>>

Not Verified

</option>

</select>

</div>

<div class="mb-3">

<label>Account Status</label>

<select
name="suspended"
class="form-select">

<option
value="0"
<?= !$shop['suspended'] ? 'selected':'' ?>>

Active

</option>

<option
value="1"
<?= $shop['suspended'] ? 'selected':'' ?>>

Suspended

</option>

</select>

</div>

<button
class="btn btn-primary w-100">

Save Changes

</button>

</form>

</div>

</div>

<!-- RIGHT -->

<div class="col-lg-8">

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="card-box shadow-sm">

<div class="metric">

<?= number_format(
$productStats['total_products']
) ?>

</div>

Products

</div>

</div>

<div class="col-md-3">

<div class="card-box shadow-sm">

<div class="metric">

<?= number_format(
$productStats['active_products']
) ?>

</div>

Active

</div>

</div>

<div class="col-md-3">

<div class="card-box shadow-sm">

<div class="metric">

<?= number_format(
$orderStats['total_orders']
) ?>

</div>

Orders

</div>

</div>

<div class="col-md-3">

<div class="card-box shadow-sm">

<div class="metric">

TZS

<?= number_format(
$orderStats['total_revenue'],
2
) ?>

</div>

Revenue

</div>

</div>

</div>

<!-- PRODUCTS -->

<div class="card-box shadow-sm">

<h4>

Recent Products

</h4>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>ID</th>
<th>Product</th>
<th>Price</th>
<th>Stock</th>
<th>Status</th>

</tr>

</thead>

<tbody>

<?php while($product = $products->fetch_assoc()): ?>

<tr>

<td>

#<?= $product['id'] ?>

</td>

<td>

<?= htmlspecialchars(
$product['product_name']
) ?>

</td>

<td>

TZS

<?= number_format(
$product['price'],
2
) ?>

</td>

<td>

<?= number_format(
$product['stock_quantity']
) ?>

</td>

<td>

<?= ucfirst(
$product['status']
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

</div>

</body>
</html>