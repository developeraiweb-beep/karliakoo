<?php

declare(strict_types=1);

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();
requireRole(['admin']);

$admin = currentUser();

$search =
trim($_GET['search'] ?? '');

/*
|--------------------------------------------------------------------------
| WALLET STATISTICS
|--------------------------------------------------------------------------
*/

$stats = [

    'wallets'            => 0,
    'available_balance'  => 0,
    'pending_balance'    => 0,
    'total_earned'       => 0,
    'total_withdrawn'    => 0

];

$statsSql = "
SELECT

    COUNT(*) AS wallets,

    SUM(available_balance) AS available_balance,

    SUM(pending_balance) AS pending_balance,

    SUM(total_earned) AS total_earned,

    SUM(total_withdrawn) AS total_withdrawn

FROM seller_wallets
";

$statsResult =
$conn->query(
    $statsSql
);

if(
    $statsResult &&
    $statsResult->num_rows
)
{
    $stats =
    $statsResult
    ->fetch_assoc();
}

/*
|--------------------------------------------------------------------------
| SEARCH FILTER
|--------------------------------------------------------------------------
*/

$where = "";
$params = [];
$types  = "";

if(!empty($search))
{
    $where = "
        WHERE

            u.full_name LIKE ?
            OR u.email LIKE ?
            OR s.shop_name LIKE ?
    ";

    $searchLike =
    "%" .
    $search .
    "%";

    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;

    $types .= "sss";
}

/*
|--------------------------------------------------------------------------
| LOAD SELLER WALLETS
|--------------------------------------------------------------------------
*/

$sql = "

SELECT

    sw.*,

    u.id AS user_id,
    u.full_name,
    u.email,
    u.phone,
    u.status AS user_status,

    s.id AS shop_id,
    s.shop_name,
    s.status AS shop_status,
    s.verified

FROM seller_wallets sw

INNER JOIN users u
    ON u.id = sw.seller_id

LEFT JOIN shops s
    ON s.seller_id = sw.seller_id

{$where}

ORDER BY sw.available_balance DESC

";

$stmt =
$conn->prepare($sql);

if(!empty($params))
{
    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$wallets =
$stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Seller Wallets

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

.card-stat{
    border:none;
    border-radius:15px;
    box-shadow:0 2px 12px rgba(0,0,0,.08);
}

.stat-value{
    font-size:28px;
    font-weight:700;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>

<i class="fas fa-wallet"></i>

Seller Wallets

</h2>

<p class="text-muted">

Marketplace Financial Management

</p>

</div>

<a
href="dashboard.php"
class="btn btn-secondary">

Dashboard

</a>

</div>
<div class="row mb-4">

<div class="col-md-3">

<div class="card card-stat">

<div class="card-body">

<h6>

Total Wallets

</h6>

<div class="stat-value text-primary">

<?= number_format(
(int)$stats['wallets']
) ?>

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card card-stat">

<div class="card-body">

<h6>

Available Balance

</h6>

<div class="stat-value text-success">

<?= number_format(
(float)$stats['available_balance'],
2
) ?>

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card card-stat">

<div class="card-body">

<h6>

Pending Balance

</h6>

<div class="stat-value text-warning">

<?= number_format(
(float)$stats['pending_balance'],
2
) ?>

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card card-stat">

<div class="card-body">

<h6>

Total Withdrawn

</h6>

<div class="stat-value text-danger">

<?= number_format(
(float)$stats['total_withdrawn'],
2
) ?>

</div>

</div>

</div>

</div>

</div>

<div class="card mb-4">

<div class="card-body">

<form method="GET">

<div class="row">

<div class="col-md-6">

<input
type="text"
name="search"
class="form-control"
placeholder="Search seller, email or shop..."
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-2">

<button
class="btn btn-primary w-100">

Search

</button>

</div>

<div class="col-md-2">

<a
href="seller-wallets.php"
class="btn btn-secondary w-100">

Reset

</a>

</div>

</div>

</form>

</div>

</div>
<div class="card">

<div class="card-header">

Seller Wallet Accounts

</div>

<div class="card-body table-responsive">

<table class="table table-bordered table-hover align-middle">

<thead>

<tr>

<th>ID</th>

<th>Seller</th>

<th>Shop</th>

<th>Available</th>

<th>Pending</th>

<th>Earned</th>

<th>Withdrawn</th>

<th>Status</th>

<th>Actions</th>

</tr>

</thead>

<tbody>
<?php if($wallets->num_rows > 0): ?>

<?php while($wallet = $wallets->fetch_assoc()): ?>

<?php

$userStatusColor =
match($wallet['user_status'])
{
    'active'    => 'success',
    'pending'   => 'warning',
    'suspended' => 'danger',
    default     => 'secondary'
};

$shopStatusColor =
match($wallet['shop_status'])
{
    'approved' => 'success',
    'pending'  => 'warning',
    'rejected' => 'danger',
    default    => 'secondary'
};

$riskFlag = false;

if(
    (float)$wallet['pending_balance']
    >
    (float)$wallet['available_balance']
)
{
    $riskFlag = true;
}

if(
    (float)$wallet['available_balance']
    > 5000000
)
{
    $riskFlag = true;
}

?>

<tr>

<td>

#<?= (int)$wallet['id'] ?>

</td>

<td>

<strong>

<?= htmlspecialchars(
$wallet['full_name']
) ?>

</strong>

<br>

<small class="text-muted">

<?= htmlspecialchars(
$wallet['email']
) ?>

</small>

<br>

<small class="text-muted">

<?= htmlspecialchars(
$wallet['phone']
) ?>

</small>

<br>

<span
class="badge bg-<?= $userStatusColor ?>">

<?= ucfirst(
$wallet['user_status']
) ?>

</span>

</td>

<td>

<strong>

<?= htmlspecialchars(
$wallet['shop_name']
?? 'No Shop'
) ?>

</strong>

<br>

<span
class="badge bg-<?= $shopStatusColor ?>">

<?= ucfirst(
$wallet['shop_status']
?? 'unknown'
) ?>

</span>

<?php if(
    (int)($wallet['verified'] ?? 0)
    === 1
): ?>

<span
class="badge bg-primary">

Verified

</span>

<?php endif; ?>

</td>

<td class="text-success">

<strong>

TZS

<?= number_format(
(float)$wallet['available_balance'],
2
) ?>

</strong>

</td>

<td class="text-warning">

TZS

<?= number_format(
(float)$wallet['pending_balance'],
2
) ?>

</td>

<td>

TZS

<?= number_format(
(float)$wallet['total_earned'],
2
) ?>

</td>

<td class="text-danger">

TZS

<?= number_format(
(float)$wallet['total_withdrawn'],
2
) ?>

</td>

<td>

<?php if($riskFlag): ?>

<span
class="badge bg-danger">

Risk Review

</span>

<?php else: ?>

<span
class="badge bg-success">

Normal

</span>

<?php endif; ?>

</td>

<td>

<div
class="btn-group"
role="group">

<a
href="seller-wallet-details.php?id=<?= (int)$wallet['seller_id'] ?>"
class="btn btn-sm btn-primary">

Wallet

</a>

<a
href="withdrawals.php?search=<?= urlencode($wallet['full_name']) ?>"
class="btn btn-sm btn-warning">

Withdrawals

</a>

<a
href="seller-payouts.php?seller_id=<?= (int)$wallet['seller_id'] ?>"
class="btn btn-sm btn-success">

Payouts

</a>

</div>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td
colspan="9"
class="text-center text-muted">

No seller wallets found.

</td>

</tr>

<?php endif; ?>
</tbody>

</table>

</div>

</div>
<div class="card mt-4">

<div class="card-header">

Financial Monitoring

</div>

<div class="card-body">

<div class="row">

<div class="col-md-4">

<div class="alert alert-success">

<strong>

Healthy Wallets

</strong>

<br>

Balanced financial activity.

</div>

</div>

<div class="col-md-4">

<div class="alert alert-warning">

<strong>

Pending Reviews

</strong>

<br>

Large pending balances may require attention.

</div>

</div>

<div class="col-md-4">

<div class="alert alert-danger">

<strong>

Risk Detection

</strong>

<br>

Large withdrawals and unusual balances should be audited.

</div>

</div>

</div>

</div>

</div>