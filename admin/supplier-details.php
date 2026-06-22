<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$shop_id = (int)($_GET['id'] ?? 0);

if ($shop_id <= 0) {
    die("Invalid supplier.");
}

/*
|--------------------------------------------------------------------------
| SHOP DETAILS
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT

s.*,

u.id seller_id,
u.full_name,
u.email,
u.phone

FROM shops s

LEFT JOIN users u
ON u.id=s.seller_id

WHERE s.id=?

LIMIT 1
");

$stmt->bind_param("i", $shop_id);
$stmt->execute();

$shop = $stmt->get_result()->fetch_assoc();

if (!$shop) {
    die("Supplier not found.");
}

/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $status = trim($_POST['status']);
    $verified = (int)$_POST['verified'];
    $suspended = (int)$_POST['suspended'];

    $stmt = $conn->prepare("
        UPDATE shops
        SET

        status=?,
        verified=?,
        suspended=?

        WHERE id=?

        LIMIT 1
    ");

    $stmt->bind_param(
        "siii",
        $status,
        $verified,
        $suspended,
        $shop_id
    );

    $stmt->execute();

    header(
        "Location:supplier-details.php?id={$shop_id}&updated=1"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| ANALYTICS
|--------------------------------------------------------------------------
*/
$analyticsStmt = $conn->prepare("
SELECT

COUNT(*) total_orders,

COALESCE(
SUM(total_amount),
0
) revenue,

COALESCE(
AVG(total_amount),
0
) average_order

FROM b2b_orders

WHERE shop_id=?
");

$analyticsStmt->bind_param(
    "i",
    $shop_id
);

$analyticsStmt->execute();

$analytics =
    $analyticsStmt
    ->get_result()
    ->fetch_assoc();

/*
|--------------------------------------------------------------------------
| RFQ COUNT
|--------------------------------------------------------------------------
*/
$rfqCount = 0;

$rfqStmt = $conn->prepare("
SELECT COUNT(*) total
FROM rfq_requests
WHERE supplier_id=?
");

$rfqStmt->bind_param(
    "i",
    $shop['seller_id']
);

$rfqStmt->execute();

$rfqCount =
    $rfqStmt
    ->get_result()
    ->fetch_assoc()['total'] ?? 0;

/*
|--------------------------------------------------------------------------
| PRODUCTS
|--------------------------------------------------------------------------
*/
$productsStmt = $conn->prepare("
SELECT

id,
name,
price,
stock,
status

FROM products

WHERE shop_id=?

ORDER BY id DESC

LIMIT 20
");

$productsStmt->bind_param(
    "i",
    $shop['seller_id']
);

$productsStmt->execute();

$products =
    $productsStmt->get_result();

/*
|--------------------------------------------------------------------------
| ORDERS
|--------------------------------------------------------------------------
*/
$ordersStmt = $conn->prepare("
SELECT

id,
order_number,
total_amount,
order_status,
payment_status,
created_at

FROM b2b_orders

WHERE shop_id=?

ORDER BY id DESC

LIMIT 15
");

$ordersStmt->bind_param(
    "i",
    $shop_id
);

$ordersStmt->execute();

$orders =
    $ordersStmt
    ->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>

Supplier Details

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
    border-radius:12px;
}

.shop-logo{
    width:90px;
    height:90px;
    border-radius:50%;
    object-fit:cover;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<a
href="b2b-suppliers.php"
class="btn btn-secondary mb-3">

← Back

</a>

<?php if(isset($_GET['updated'])): ?>

<div class="alert alert-success">

Supplier updated successfully.

</div>

<?php endif; ?>

<div class="row">

<!-- LEFT -->

<div class="col-lg-4">

<div class="card-box shadow-sm p-4 mb-4">

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

<?= htmlspecialchars(
$shop['description']
)
?>

</p>

<hr>

<p>

Owner:

<strong>

<?= htmlspecialchars(
$shop['full_name']
) ?>

</strong>

</p>

<p>

Email:

<?= htmlspecialchars(
$shop['email']
) ?>

</p>

<p>

Phone:

<?= htmlspecialchars(
$shop['phone']
) ?>

</p>

<p>

City:

<?= htmlspecialchars(
$shop['city']
) ?>

</p>

<p>

Region:

<?= htmlspecialchars(
$shop['region']
) ?>

</p>

<p>

Followers:

<?= number_format(
$shop['followers']
) ?>

</p>

<p>

Views:

<?= number_format(
$shop['views']
) ?>

</p>

</div>

<div class="card-box shadow-sm p-4">

<h5>

Supplier Controls

</h5>

<hr>

<form method="POST">

<div class="mb-3">

<label>Status</label>

<select
name="status"
class="form-select">

<option value="pending"
<?= $shop['status']=='pending' ? 'selected':'' ?>>

Pending

</option>

<option value="approved"
<?= $shop['status']=='approved' ? 'selected':'' ?>>

Approved

</option>

<option value="rejected"
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

<option value="1"
<?= $shop['verified'] ? 'selected':'' ?>>

Verified

</option>

<option value="0"
<?= !$shop['verified'] ? 'selected':'' ?>>

Not Verified

</option>

</select>

</div>

<div class="mb-3">

<label>Account State</label>

<select
name="suspended"
class="form-select">

<option value="0"
<?= !$shop['suspended'] ? 'selected':'' ?>>

Active

</option>

<option value="1"
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

<div class="card-box shadow-sm p-3">

<h4>

<?= number_format(
$analytics['total_orders']
) ?>

</h4>

<small>Orders</small>

</div>

</div>

<div class="col-md-3">

<div class="card-box shadow-sm p-3">

<h4>

<?= number_format(
$rfqCount
) ?>

</h4>

<small>RFQs</small>

</div>

</div>

<div class="col-md-3">

<div class="card-box shadow-sm p-3">

<h4>

TZS

<?= number_format(
$analytics['revenue'],
2
) ?>

</h4>

<small>Revenue</small>

</div>

</div>

<div class="col-md-3">

<div class="card-box shadow-sm p-3">

<h4>

TZS

<?= number_format(
$analytics['average_order'],
2
) ?>

</h4>

<small>Avg Order</small>

</div>

</div>

</div>

<!-- PRODUCTS -->

<div class="card-box shadow-sm p-4 mb-4">

<h4>

Products

</h4>

<hr>

<div class="table-responsive">

<table class="table">

<thead>

<tr>

<th>Name</th>
<th>Price</th>
<th>Stock</th>
<th>Status</th>

</tr>

</thead>

<tbody>

<?php while($product = $products->fetch_assoc()): ?>

<tr>

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

<!-- ORDERS -->

<div class="card-box shadow-sm p-4">

<h4>

Recent Orders

</h4>

<hr>

<div class="table-responsive">

<table class="table">

<thead>

<tr>

<th>Order</th>
<th>Amount</th>
<th>Status</th>
<th>Payment</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php while($order = $orders->fetch_assoc()): ?>

<tr>

<td>

<?= htmlspecialchars(
$order['order_number']
) ?>

</td>

<td>

TZS

<?= number_format(
$order['total_amount'],
2
) ?>

</td>

<td>

<?= ucfirst(
$order['order_status']
) ?>

</td>

<td>

<?= ucfirst(
$order['payment_status']
) ?>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$order['created_at']
)
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