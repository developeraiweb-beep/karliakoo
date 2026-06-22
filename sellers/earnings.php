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
| SHOP VALIDATION
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

$shopId = (int)$shop['id'];

/*
|--------------------------------------------------------------------------
| WALLET
|--------------------------------------------------------------------------
*/

$walletStmt = $conn->prepare("
    SELECT *
    FROM seller_wallets
    WHERE seller_id = ?
    LIMIT 1
");

$walletStmt->bind_param(
    "i",
    $sellerId
);

$walletStmt->execute();

$wallet =
$walletStmt
->get_result()
->fetch_assoc();

if(!$wallet)
{
    $createWallet = $conn->prepare("
        INSERT INTO seller_wallets
        (
            seller_id
        )
        VALUES
        (
            ?
        )
    ");

    $createWallet->bind_param(
        "i",
        $sellerId
    );

    $createWallet->execute();

    $wallet = [

        'available_balance' => 0,
        'pending_balance'   => 0,
        'total_earned'      => 0,
        'total_withdrawn'   => 0

    ];
}

/*
|--------------------------------------------------------------------------
| SALES STATISTICS
|--------------------------------------------------------------------------
*/

$salesStmt = $conn->prepare("
    SELECT

        COUNT(
            DISTINCT o.id
        ) total_orders,

        SUM(
            oi.quantity
        ) total_items,

        SUM(
            oi.price * oi.quantity
        ) gross_sales,

        SUM(
            oi.platform_fee
        ) platform_fees

    FROM order_items oi

    INNER JOIN orders o
    ON o.id = oi.order_id

    WHERE oi.shop_id = ?
");

$salesStmt->bind_param(
    "i",
    $shopId
);

$salesStmt->execute();

$sales =
$salesStmt
->get_result()
->fetch_assoc();

$totalOrders =
(int)($sales['total_orders'] ?? 0);

$totalItems =
(int)($sales['total_items'] ?? 0);

$grossSales =
(float)($sales['gross_sales'] ?? 0);

$platformFees =
(float)($sales['platform_fees'] ?? 0);

$netRevenue =
$grossSales -
$platformFees;

/*
|--------------------------------------------------------------------------
| THIS MONTH
|--------------------------------------------------------------------------
*/

$monthStmt = $conn->prepare("
    SELECT

        SUM(
            oi.price * oi.quantity
        ) revenue

    FROM order_items oi

    INNER JOIN orders o
    ON o.id = oi.order_id

    WHERE oi.shop_id = ?
    AND MONTH(o.created_at) = MONTH(CURRENT_DATE())
    AND YEAR(o.created_at) = YEAR(CURRENT_DATE())
");

$monthStmt->bind_param(
    "i",
    $shopId
);

$monthStmt->execute();

$monthRevenue =
(float)(
$monthStmt
->get_result()
->fetch_assoc()['revenue']
?? 0
);

/*
|--------------------------------------------------------------------------
| TODAY
|--------------------------------------------------------------------------
*/

$todayStmt = $conn->prepare("
    SELECT

        SUM(
            oi.price * oi.quantity
        ) revenue

    FROM order_items oi

    INNER JOIN orders o
    ON o.id = oi.order_id

    WHERE oi.shop_id = ?
    AND DATE(o.created_at)=CURDATE()
");

$todayStmt->bind_param(
    "i",
    $shopId
);

$todayStmt->execute();

$todayRevenue =
(float)(
$todayStmt
->get_result()
->fetch_assoc()['revenue']
?? 0
);

/*
|--------------------------------------------------------------------------
| COMMISSIONS
|--------------------------------------------------------------------------
*/

$commissionStmt = $conn->prepare("
    SELECT

        SUM(amount) total

    FROM commissions

    WHERE user_id = ?
    AND commission_type='seller'
");

$commissionStmt->bind_param(
    "i",
    $sellerId
);

$commissionStmt->execute();

$totalCommissions =
(float)(
$commissionStmt
->get_result()
->fetch_assoc()['total']
?? 0
);

/*
|--------------------------------------------------------------------------
| RECENT PAYOUTS
|--------------------------------------------------------------------------
*/

$payoutStmt = $conn->prepare("
    SELECT *
    FROM seller_payouts
    WHERE seller_id = ?
    ORDER BY id DESC
    LIMIT 10
");

$payoutStmt->bind_param(
    "i",
    $sellerId
);

$payoutStmt->execute();

$payouts =
$payoutStmt
->get_result();

/*
|--------------------------------------------------------------------------
| WITHDRAW REQUESTS
|--------------------------------------------------------------------------
*/

$withdrawStmt = $conn->prepare("
    SELECT *
    FROM withdrawals
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 10
");

$withdrawStmt->bind_param(
    "i",
    $sellerId
);

$withdrawStmt->execute();

$withdrawals =
$withdrawStmt
->get_result();

/*
|--------------------------------------------------------------------------
| RECENT COMMISSIONS
|--------------------------------------------------------------------------
*/

$commissionHistoryStmt =
$conn->prepare("
    SELECT *
    FROM commissions
    WHERE user_id = ?
    AND commission_type='seller'
    ORDER BY id DESC
    LIMIT 20
");

$commissionHistoryStmt->bind_param(
    "i",
    $sellerId
);

$commissionHistoryStmt->execute();

$commissionHistory =
$commissionHistoryStmt
->get_result();

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Seller Earnings

</title>

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

.dashboard-card{
    border:none;
    border-radius:16px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
    transition:.3s;
}

.dashboard-card:hover{
    transform:translateY(-3px);
}

.metric{
    font-size:28px;
    font-weight:700;
}

.currency{
    font-size:14px;
    color:#6c757d;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>

<i class="fas fa-wallet"></i>

Seller Earnings

</h2>

<p class="text-muted mb-0">

<?= htmlspecialchars(
$shop['shop_name']
) ?>

</p>

</div>

<div>

<a
href="dashboard.php"
class="btn btn-secondary">

Dashboard

</a>

</div>

</div>

<!-- WALLET -->

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="card dashboard-card">

<div class="card-body">

<h6>

Available Balance

</h6>

<div class="metric text-success">

<?= number_format(
(float)$wallet['available_balance'],
2
) ?>

</div>

<div class="currency">

TZS

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card dashboard-card">

<div class="card-body">

<h6>

Pending Balance

</h6>

<div class="metric text-warning">

<?= number_format(
(float)$wallet['pending_balance'],
2
) ?>

</div>

<div class="currency">

TZS

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card dashboard-card">

<div class="card-body">

<h6>

Total Earned

</h6>

<div class="metric text-primary">

<?= number_format(
(float)$wallet['total_earned'],
2
) ?>

</div>

<div class="currency">

TZS

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card dashboard-card">

<div class="card-body">

<h6>

Total Withdrawn

</h6>

<div class="metric text-danger">

<?= number_format(
(float)$wallet['total_withdrawn'],
2
) ?>

</div>

<div class="currency">

TZS

</div>

</div>

</div>

</div>

</div>

<!-- SALES -->

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="card dashboard-card">

<div class="card-body">

<h6>

Gross Sales

</h6>

<div class="metric">

<?= number_format(
$grossSales,
2
) ?>

</div>

<div class="currency">

TZS

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card dashboard-card">

<div class="card-body">

<h6>

Platform Fees

</h6>

<div class="metric text-danger">

<?= number_format(
$platformFees,
2
) ?>

</div>

<div class="currency">

TZS

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card dashboard-card">

<div class="card-body">

<h6>

Net Revenue

</h6>

<div class="metric text-success">

<?= number_format(
$netRevenue,
2
) ?>

</div>

<div class="currency">

TZS

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card dashboard-card">

<div class="card-body">

<h6>

Seller Commissions

</h6>

<div class="metric text-info">

<?= number_format(
$totalCommissions,
2
) ?>

</div>

<div class="currency">

TZS

</div>

</div>

</div>

</div>

</div>

<!-- ORDERS -->

<div class="row g-3 mb-4">

<div class="col-md-4">

<div class="card dashboard-card">

<div class="card-body">

<h6>

Total Orders

</h6>

<div class="metric">

<?= number_format(
$totalOrders
) ?>

</div>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card dashboard-card">

<div class="card-body">

<h6>

Items Sold

</h6>

<div class="metric">

<?= number_format(
$totalItems
) ?>

</div>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card dashboard-card">

<div class="card-body">

<h6>

Withdrawable Balance

</h6>

<div class="metric text-success">

<?= number_format(
(float)$wallet['available_balance'],
2
) ?>

</div>

<div class="currency">

TZS

</div>

</div>

</div>

</div>

</div>

<!-- PERIOD OVERVIEW -->

<div class="row g-3 mb-4">

<div class="col-md-6">

<div class="card dashboard-card">

<div class="card-body">

<h5>

Today's Revenue

</h5>

<h2 class="text-success">

TZS

<?= number_format(
$todayRevenue,
2
) ?>

</h2>

</div>

</div>

</div>

<div class="col-md-6">

<div class="card dashboard-card">

<div class="card-body">

<h5>

This Month Revenue

</h5>

<h2 class="text-primary">

TZS

<?= number_format(
$monthRevenue,
2
) ?>

</h2>

</div>

</div>

</div>

</div>

<div class="card dashboard-card mb-4">

<div class="card-header">

Recent Seller Earnings

</div>

<div class="card-body table-responsive">

<table class="table table-striped">

<thead>

<tr>

<th>ID</th>
<th>Order</th>
<th>Amount</th>
<th>Status</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php while(
$row =
$commissionHistory->fetch_assoc()
): ?>

<tr>

<td>

#<?= (int)$row['id'] ?>

</td>

<td>

<?= (int)$row['order_id'] ?>

</td>

<td>

TZS

<?= number_format(
(float)$row['amount'],
2
) ?>

</td>

<td>

<span class="badge bg-success">

<?= ucfirst(
$row['status']
) ?>

</span>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$row['created_at']
)
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<div class="card dashboard-card mb-4">

<div class="card-header">

Payout History

</div>

<div class="card-body table-responsive">

<table class="table table-bordered">

<thead>

<tr>

<th>ID</th>
<th>Amount</th>
<th>Status</th>
<th>Method</th>
<th>Reference</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php while(
$payout =
$payouts->fetch_assoc()
): ?>

<tr>

<td>

#<?= (int)$payout['id'] ?>

</td>

<td>

TZS

<?= number_format(
(float)$payout['amount'],
2
) ?>

</td>

<td>

<?= ucfirst(
$payout['status']
) ?>

</td>

<td>

<?= htmlspecialchars(
$payout['payment_method']
?? '-'
) ?>

</td>

<td>

<?= htmlspecialchars(
$payout['reference_no']
?? '-'
) ?>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$payout['created_at']
)
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<div class="card dashboard-card mb-4">

<div class="card-header">

Withdrawal Requests

</div>

<div class="card-body table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>ID</th>
<th>Amount</th>
<th>Method</th>
<th>Status</th>
<th>Requested</th>

</tr>

</thead>

<tbody>

<?php while(
$withdraw =
$withdrawals->fetch_assoc()
): ?>

<tr>

<td>

#<?= (int)$withdraw['id'] ?>

</td>

<td>

TZS

<?= number_format(
(float)$withdraw['amount'],
2
) ?>

</td>

<td>

<?= htmlspecialchars(
$withdraw['method']
)
?>

</td>

<td>

<?php

$status =
$withdraw['status'];

$badge =
match($status)
{
    'approved' => 'success',
    'paid' => 'primary',
    'rejected' => 'danger',
    default => 'warning'
};

?>

<span
class="badge bg-<?= $badge ?>">

<?= ucfirst(
$status
) ?>

</span>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$withdraw['requested_at']
)
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<div class="card dashboard-card mb-4">

<div class="card-body">

<div class="row">

<div class="col-md-3 d-grid">

<a
href="request-withdrawal.php"
class="btn btn-success">

Request Withdrawal

</a>

</div>

<div class="col-md-3 d-grid">

<a
href="transactions.php"
class="btn btn-primary">

Transactions

</a>

</div>

<div class="col-md-3 d-grid">

<a
href="payouts.php"
class="btn btn-warning">

Payouts

</a>

</div>

<div class="col-md-3 d-grid">

<a
href="earnings-export.php"
class="btn btn-dark">

Export Report

</a>

</div>

</div>

</div>

</div>

</div>

</body>
</html>