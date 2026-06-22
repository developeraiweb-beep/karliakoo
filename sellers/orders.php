<?php

declare(strict_types=1);

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

if(!$user)
{
    header("Location: ../login.php");
    exit;
}

$sellerId = (int)$user['id'];


/*
|--------------------------------------------------------------------------
| SHOP VERIFICATION
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

if(!$shop)
{
    die("Shop not found.");
}

if(
    $shop['status'] !== 'approved'
)
{
    die("Shop not approved.");
}

if(
    (int)$shop['suspended'] === 1
)
{
    die("Shop suspended.");
}

$shopId =
(int)$shop['id'];

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search =
trim(
    $_GET['search'] ?? ''
);

$status =
trim(
    $_GET['status'] ?? ''
);

$page =
max(
    1,
    (int)($_GET['page'] ?? 1)
);

$limit = 20;

$offset =
($page - 1)
*
$limit;

/*
|--------------------------------------------------------------------------
| DASHBOARD STATS
|--------------------------------------------------------------------------
*/

$statsSql = "
SELECT

COUNT(DISTINCT o.id) total_orders,

SUM(
CASE
WHEN o.order_status='pending'
THEN 1 ELSE 0
END
) pending_orders,

SUM(
CASE
WHEN o.order_status='processing'
THEN 1 ELSE 0
END
) processing_orders,

SUM(
CASE
WHEN o.order_status='shipped'
THEN 1 ELSE 0
END
) shipped_orders,

SUM(
CASE
WHEN o.order_status='delivered'
THEN 1 ELSE 0
END
) delivered_orders,

SUM(
oi.price * oi.quantity)
gross_sales

FROM orders o

INNER JOIN order_items oi
ON oi.order_id = o.id

WHERE oi.shop_id = ?
";

$statsStmt =
$conn->prepare(
    $statsSql
);

$statsStmt->bind_param(
    "i",
    $shopId
);

$statsStmt->execute();

$stats =
$statsStmt
->get_result()
->fetch_assoc();

/*
|--------------------------------------------------------------------------
| ORDER QUERY
|--------------------------------------------------------------------------
*/

$where =
" WHERE oi.shop_id = ? ";

$params = [$shopId];
$types = "i";

if(!empty($search))
{
    $where .= "
    AND
    (
        o.order_number LIKE ?
        OR u.full_name LIKE ?
        OR u.email LIKE ?
    )
    ";

    $searchLike =
    "%{$search}%";

    $params[] =
    $searchLike;

    $params[] =
    $searchLike;

    $params[] =
    $searchLike;

    $types .= "sss";
}

if(!empty($status))
{
    $where .= "
    AND o.order_status = ?
    ";

    $params[] =
    $status;

    $types .= "s";
}
$orderSql = "

SELECT

o.id,
o.order_number,
o.total_amount,
o.order_status,
o.payment_status,
o.created_at,

u.full_name,
u.email,

COUNT(oi.id)
items_count,

SUM(
oi.quantity
) total_qty

FROM orders o

INNER JOIN users u
ON u.id = o.user_id

INNER JOIN order_items oi
ON oi.order_id = o.id

{$where}

GROUP BY o.id

ORDER BY o.id DESC

LIMIT {$limit}
OFFSET {$offset}
";

$orderStmt =
$conn->prepare(
    $orderSql
);

$orderStmt->bind_param(
    $types,
    ...$params
);

$orderStmt->execute();

$orders =
$orderStmt
->get_result();

$countSql = "

SELECT
COUNT(
DISTINCT o.id
) total

FROM orders o

INNER JOIN order_items oi
ON oi.order_id = o.id

INNER JOIN users u
ON u.id = o.user_id

{$where}
";

$countStmt =
$conn->prepare(
    $countSql
);

$countStmt->bind_param(
    $types,
    ...$params
);

$countStmt->execute();

$totalOrders =
(int)(
$countStmt
->get_result()
->fetch_assoc()['total']
?? 0
);

$totalPages =
max(
1,
ceil(
$totalOrders /
$limit
)
);

$statusColors = [

'pending' => 'warning',

'processing' => 'info',

'packed' => 'primary',

'shipped' => 'secondary',

'delivered' => 'success',

'cancelled' => 'danger'
];

$paymentColors = [

'pending' => 'warning',

'paid' => 'success',

'failed' => 'danger'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
Seller Orders
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

.stat-card{
    border:none;
    border-radius:16px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.table-card{
    border:none;
    border-radius:16px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>
<i class="fas fa-shopping-bag"></i>
Orders
</h2>

<p class="text-muted mb-0">

<?= htmlspecialchars($shop['shop_name']) ?>

</p>

</div>

</div>

<a
href="export-orders.php"
class="btn btn-success">

<i class="fas fa-file-csv"></i>

Export CSV

</a>

<!-- STATS -->

<div class="row g-3 mb-4">

<div class="col-md-2">

<div class="card stat-card">

<div class="card-body text-center">

<h4>

<?= number_format(
(int)($stats['total_orders'] ?? 0)
) ?>

</h4>

<small>
Total Orders
</small>

</div>

</div>

</div>

<div class="col-md-2">

<div class="card stat-card">

<div class="card-body text-center">

<h4>

<?= number_format(
(int)($stats['pending_orders'] ?? 0)
) ?>

</h4>

<small>
Pending
</small>

</div>

</div>

</div>

<div class="col-md-2">

<div class="card stat-card">

<div class="card-body text-center">

<h4>

<?= number_format(
(int)($stats['processing_orders'] ?? 0)
) ?>

</h4>

<small>
Processing
</small>

</div>

</div>

</div>

<div class="col-md-2">

<div class="card stat-card">

<div class="card-body text-center">

<h4>

<?= number_format(
(int)($stats['shipped_orders'] ?? 0)
) ?>

</h4>

<small>
Shipped
</small>

</div>

</div>

</div>

<div class="col-md-2">

<div class="card stat-card">

<div class="card-body text-center">

<h4>

<?= number_format(
(int)($stats['delivered_orders'] ?? 0)
) ?>

</h4>

<small>
Delivered
</small>

</div>

</div>

</div>

<div class="col-md-2">

<div class="card stat-card">

<div class="card-body text-center">

<h4>

TZS

<?= number_format(
(float)($stats['gross_sales'] ?? 0),
2
) ?>

</h4>

<small>
Revenue
</small>

</div>

</div>

</div>

</div>

<!-- FILTERS -->

<div class="card table-card mb-4">

<div class="card-body">

<form method="GET">

<div class="row g-3">

<div class="col-md-5">

<input
type="text"
name="search"
class="form-control"
placeholder="Order Number, Customer Name, Email"
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-3">

<select
name="status"
class="form-select">

<option value="">
All Statuses
</option>

<option value="pending"
<?= $status==='pending'?'selected':'' ?>>
Pending
</option>

<option value="processing"
<?= $status==='processing'?'selected':'' ?>>
Processing
</option>

<option value="packed"
<?= $status==='packed'?'selected':'' ?>>
Packed
</option>

<option value="shipped"
<?= $status==='shipped'?'selected':'' ?>>
Shipped
</option>

<option value="delivered"
<?= $status==='delivered'?'selected':'' ?>>
Delivered
</option>

<option value="cancelled"
<?= $status==='cancelled'?'selected':'' ?>>
Cancelled
</option>

</select>

</div>

<div class="col-md-2">

<button
class="btn btn-primary w-100">

<i class="fas fa-search"></i>

Search

</button>

</div>

<div class="col-md-2">

<a
href="orders.php"
class="btn btn-secondary w-100">

Reset

</a>

</div>

</div>

</form>

</div>

</div>

<div class="d-flex justify-content-between mb-3">

<div>

<strong>

Total Orders:

</strong>

<?= number_format(
$totalOrders
) ?>

</div>

<div>

<a
href="orders.php?status=pending"
class="btn btn-outline-warning btn-sm">

Pending

</a>

<a
href="orders.php?status=processing"
class="btn btn-outline-info btn-sm">

Processing

</a>

<a
href="orders.php?status=shipped"
class="btn btn-outline-secondary btn-sm">

Shipped

</a>

<a
href="orders.php?status=delivered"
class="btn btn-outline-success btn-sm">

Delivered

</a>

</div>

</div>

<!-- ORDERS TABLE -->

<div class="card table-card">

<div class="card-body">

<div class="table-responsive">

<table
class="table table-hover align-middle">

<thead>

<tr>

<th>#</th>

<th>Order</th>

<th>Customer</th>

<th>Items</th>

<th>Qty</th>

<th>Total</th>

<th>Payment</th>

<th>Status</th>

<th>Date</th>

<th>Action</th>

</tr>

</thead>

<tbody>

<?php if($orders->num_rows > 0): ?>

<?php while($order = $orders->fetch_assoc()): ?>

<tr>

<td>

<?= (int)$order['id'] ?>

</td>

<td>

<strong>

<?= htmlspecialchars(
$order['order_number']
) ?>

</strong>

</td>

<td>

<div>

<?= htmlspecialchars(
$order['full_name']
) ?>

</div>

<small class="text-muted">

<?= htmlspecialchars(
$order['email']
) ?>

</small>

</td>

<td>

<?= number_format(
(int)$order['items_count']
) ?>

</td>

<td>

<?= number_format(
(int)$order['total_qty']
) ?>

</td>

<td>

TZS

<?= number_format(
(float)$order['total_amount'],
2
) ?>

</td>

<td>

<span
class="badge bg-<?=
$paymentColors[
$order['payment_status']
] ?? 'secondary'
?>">

<?= ucfirst(
$order['payment_status']
) ?>

</span>

</td>

<td>

<span
class="badge bg-<?=
$statusColors[
$order['order_status']
] ?? 'secondary'
?>">

<?= ucfirst(
$order['order_status']
) ?>

</span>

</td>

<td>

<?= date(
"d M Y",
strtotime(
$order['created_at']
)
) ?>

</td>

<td>

<a
href="order-details.php?id=<?= (int)$order['id'] ?>"
class="btn btn-sm btn-primary">

View

</a>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td
colspan="10"
class="text-center py-5">

No orders found.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

</div>

<?php if($totalPages > 1): ?>

<nav class="mt-4">

<ul class="pagination justify-content-center">

<?php for(
$i=1;
$i<=$totalPages;
$i++
): ?>

<li
class="page-item
<?= $page == $i ? 'active' : '' ?>">

<a
class="page-link"
href="?page=<?= $i ?>
&search=<?= urlencode($search) ?>
&status=<?= urlencode($status) ?>">

<?= $i ?>

</a>

</li>

<?php endfor; ?>

</ul>

</nav>

<?php endif; ?>

<?php

/*
|--------------------------------------------------------------------------
| EXTRA KPIs
|--------------------------------------------------------------------------
*/

$todayOrders = 0;
$todayRevenue = 0;

$todayStmt = $conn->prepare("
    SELECT

        COUNT(DISTINCT o.id) total_orders,

        SUM(
            oi.price * oi.quantity
        ) total_revenue

    FROM orders o

    INNER JOIN order_items oi
    ON oi.order_id = o.id

    WHERE oi.shop_id = ?
    AND DATE(o.created_at) = CURDATE()
");

$todayStmt->bind_param(
    "i",
    $shopId
);

$todayStmt->execute();

$todayData =
$todayStmt
->get_result()
->fetch_assoc();

$todayOrders =
(int)($todayData['total_orders'] ?? 0);

$todayRevenue =
(float)($todayData['total_revenue'] ?? 0);

/*
|--------------------------------------------------------------------------
| PENDING SHIPMENTS
|--------------------------------------------------------------------------
*/

$pendingShipmentStmt =
$conn->prepare("
    SELECT
        COUNT(DISTINCT o.id) total

    FROM orders o

    INNER JOIN order_items oi
    ON oi.order_id = o.id

    WHERE oi.shop_id = ?
    AND o.order_status IN
    (
        'processing',
        'packed'
    )
");

$pendingShipmentStmt->bind_param(
    "i",
    $shopId
);

$pendingShipmentStmt->execute();

$pendingShipments =
(int)(
$pendingShipmentStmt
->get_result()
->fetch_assoc()['total']
?? 0
);
?>

<div class="row mt-4">

<div class="col-md-4">

<div class="card stat-card">

<div class="card-body text-center">

<h4>

<?= number_format($todayOrders) ?>

</h4>

<small>

Today's Orders

</small>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card stat-card">

<div class="card-body text-center">

<h4>

TZS

<?= number_format(
$todayRevenue,
2
) ?>

</h4>

<small>

Today's Revenue

</small>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card stat-card">

<div class="card-body text-center">

<h4>

<?= number_format(
$pendingShipments
) ?>

</h4>

<small>

Awaiting Shipment

</small>

</div>

</div>

</div>

</div>

<?php if($pendingShipments > 0): ?>

<div class="alert alert-warning mt-4">

<strong>

Attention:

</strong>

You currently have

<strong>

<?= number_format(
$pendingShipments
) ?>

</strong>

orders awaiting shipment.

</div>

<?php endif; ?>