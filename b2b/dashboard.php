<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['b2b']);

$user = currentUser();
$userId = (int)$user['id'];

/*
|--------------------------------------------------------------------------
| DASHBOARD STATS
|--------------------------------------------------------------------------
*/

function getCount($conn, $sql, $userId)
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    return (int)$stmt
        ->get_result()
        ->fetch_row()[0];
}

$totalRfqs = getCount(
    $conn,
    "SELECT COUNT(*) FROM b2b_rfqs WHERE buyer_id=?",
    $userId
);

$activeRfqs = getCount(
    $conn,
    "SELECT COUNT(*) FROM b2b_rfqs WHERE buyer_id=? AND status='open'",
    $userId
);

$totalQuotes = getCount(
    $conn,
    "SELECT COUNT(*) FROM b2b_quotes WHERE buyer_id=?",
    $userId
);

$totalContracts = getCount(
    $conn,
    "SELECT COUNT(*) FROM b2b_contracts WHERE buyer_id=?",
    $userId
);

$totalOrders = getCount(
    $conn,
    "SELECT COUNT(*) FROM b2b_orders WHERE buyer_id=?",
    $userId
);

$activeSuppliers = getCount(
    $conn,
    "SELECT COUNT(*) FROM b2b_connections
     WHERE sender_id=? AND status='accepted'",
    $userId
);

$unreadNotifications = getCount(
    $conn,
    "SELECT COUNT(*) FROM b2b_notifications
     WHERE user_id=? AND is_read=0",
    $userId
);

$pendingCompliance = getCount(
    $conn,
    "SELECT COUNT(*) FROM b2b_compliance_documents
     WHERE user_id=? AND status='pending'",
    $userId
);

/*
|--------------------------------------------------------------------------
| PROCUREMENT VALUE
|--------------------------------------------------------------------------
*/
$spendStmt = $conn->prepare("
SELECT COALESCE(SUM(total_amount),0)
FROM b2b_orders
WHERE buyer_id=?
");

$spendStmt->bind_param(
    "i",
    $userId
);

$spendStmt->execute();

$totalSpend =
$spendStmt
->get_result()
->fetch_row()[0];

/*
|--------------------------------------------------------------------------
| RECENT RFQS
|--------------------------------------------------------------------------
*/
$rfqs = $conn->prepare("
SELECT id,title,status,created_at
FROM b2b_rfqs
WHERE buyer_id=?
ORDER BY id DESC
LIMIT 5
");

$rfqs->bind_param(
    "i",
    $userId
);

$rfqs->execute();

$rfqs =
$rfqs->get_result();

/*
|--------------------------------------------------------------------------
| RECENT ORDERS
|--------------------------------------------------------------------------
*/
$orders = $conn->prepare("
SELECT id,order_number,status,total_amount
FROM b2b_orders
WHERE buyer_id=?
ORDER BY id DESC
LIMIT 5
");

$orders->bind_param(
    "i",
    $userId
);

$orders->execute();

$orders =
$orders->get_result();

/*
|--------------------------------------------------------------------------
| RECENT NOTIFICATIONS
|--------------------------------------------------------------------------
*/
$notifications = $conn->prepare("
SELECT title,notification_type,created_at
FROM b2b_notifications
WHERE user_id=?
ORDER BY id DESC
LIMIT 5
");

$notifications->bind_param(
    "i",
    $userId
);

$notifications->execute();

$notifications =
$notifications->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>B2B Dashboard</title>

<meta name="viewport"
content="width=device-width, initial-scale=1">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
rel="stylesheet">

<style>

body{
background:#f4f6f9;
}

.card-box{
background:#fff;
padding:20px;
border-radius:16px;
box-shadow:0 2px 10px rgba(0,0,0,.08);
height:100%;
}

.metric{
font-size:30px;
font-weight:700;
}

.section-title{
font-size:18px;
font-weight:600;
margin-bottom:15px;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between mb-4">

<div>

<h2>

Welcome,
<?= htmlspecialchars($user['full_name']) ?>

</h2>

<p class="text-muted">

Procurement & Supplier Management Dashboard

</p>

</div>

<div>

<a href="request-quote.php"
class="btn btn-primary">

<i class="fas fa-plus"></i>
New RFQ

</a>

</div>

</div>

<!-- KPI ROW -->

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card-box text-center">
<div class="metric text-primary">
<?= number_format($totalRfqs) ?>
</div>
RFQs
</div>
</div>

<div class="col-md-3">
<div class="card-box text-center">
<div class="metric text-success">
<?= number_format($totalQuotes) ?>
</div>
Quotes
</div>
</div>

<div class="col-md-3">
<div class="card-box text-center">
<div class="metric text-warning">
<?= number_format($totalContracts) ?>
</div>
Contracts
</div>
</div>

<div class="col-md-3">
<div class="card-box text-center">
<div class="metric text-danger">
<?= number_format($totalOrders) ?>
</div>
Orders
</div>
</div>

</div>

<!-- SECOND ROW -->

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card-box text-center">
<div class="metric">
<?= number_format($activeSuppliers) ?>
</div>
Suppliers
</div>
</div>

<div class="col-md-3">
<div class="card-box text-center">
<div class="metric text-danger">
<?= number_format($unreadNotifications) ?>
</div>
Unread Alerts
</div>
</div>

<div class="col-md-3">
<div class="card-box text-center">
<div class="metric text-warning">
<?= number_format($pendingCompliance) ?>
</div>
Compliance Issues
</div>
</div>

<div class="col-md-3">
<div class="card-box text-center">
<div class="metric text-success">
TZS <?= number_format($totalSpend,2) ?>
</div>
Procurement Spend
</div>
</div>

</div>

<!-- QUICK ACTIONS -->

<div class="card-box mb-4">

<div class="section-title">

Quick Actions

</div>

<div class="d-flex flex-wrap gap-2">

<a href="request-quote.php" class="btn btn-primary">
Create RFQ
</a>

<a href="my-quotes.php" class="btn btn-success">
Quotes
</a>

<a href="contracts.php" class="btn btn-warning">
Contracts
</a>

<a href="bulk-orders.php" class="btn btn-info">
Orders
</a>

<a href="business-network.php" class="btn btn-secondary">
Suppliers
</a>

<a href="messages.php" class="btn btn-dark">
Messages
</a>

<a href="notifications.php" class="btn btn-danger">
Notifications
</a>

</div>

</div>

<!-- DATA SECTIONS -->

<div class="row">

<div class="col-lg-4">

<div class="card-box">

<div class="section-title">

Recent RFQs

</div>

<?php while($row = $rfqs->fetch_assoc()): ?>

<div class="border-bottom pb-2 mb-2">

<strong>
<?= htmlspecialchars($row['title']) ?>
</strong>

<br>

<small>

<?= ucfirst($row['status']) ?>

</small>

</div>

<?php endwhile; ?>

</div>

</div>

<div class="col-lg-4">

<div class="card-box">

<div class="section-title">

Recent Orders

</div>

<?php while($row = $orders->fetch_assoc()): ?>

<div class="border-bottom pb-2 mb-2">

<strong>

<?= htmlspecialchars(
$row['order_number']
) ?>

</strong>

<br>

TZS
<?= number_format(
$row['total_amount'],
2
) ?>

</div>

<?php endwhile; ?>

</div>

</div>

<div class="col-lg-4">

<div class="card-box">

<div class="section-title">

Latest Notifications

</div>

<?php while(
$row =
$notifications->fetch_assoc()
): ?>

<div class="border-bottom pb-2 mb-2">

<strong>

<?= htmlspecialchars(
$row['title']
) ?>

</strong>

<br>

<small>

<?= ucfirst(
$row['notification_type']
) ?>

</small>

</div>

<?php endwhile; ?>

</div>

</div>

</div>

</div>

</body>
</html>