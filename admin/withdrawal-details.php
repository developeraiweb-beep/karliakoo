<?php

declare(strict_types=1);

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

requireRole(['admin']);

$admin = currentUser();

$withdrawalId =
(int)(
    $_GET['id']
    ?? 0
);

if($withdrawalId <= 0)
{
    header(
        "Location: withdrawals.php"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD WITHDRAWAL
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT

        w.*,

        u.full_name,
        u.email,
        u.phone,
        u.role,
        u.status AS user_status,

        sw.available_balance,
        sw.pending_balance,
        sw.total_earned,
        sw.total_withdrawn,

        s.id AS shop_id,
        s.shop_name,
        s.shop_slug,
        s.verified,
        s.status AS shop_status

    FROM withdrawals w

    INNER JOIN users u
        ON u.id = w.user_id

    LEFT JOIN seller_wallets sw
        ON sw.seller_id = u.id

    LEFT JOIN shops s
        ON s.seller_id = u.id

    WHERE w.id = ?

    LIMIT 1
");

$stmt->bind_param(
    "i",
    $withdrawalId
);

$stmt->execute();

$withdrawal =
$stmt
->get_result()
->fetch_assoc();

if(!$withdrawal)
{
    header(
        "Location: withdrawals.php?error=Withdrawal not found"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD PREVIOUS WITHDRAWALS
|--------------------------------------------------------------------------
*/

$historyStmt =
$conn->prepare("
    SELECT

        id,
        amount,
        method,
        status,
        requested_at,
        processed_at

    FROM withdrawals

    WHERE user_id = ?

    ORDER BY id DESC

    LIMIT 10
");

$historyStmt->bind_param(
    "i",
    $withdrawal['user_id']
);

$historyStmt->execute();

$withdrawalHistory =
$historyStmt
->get_result();

/*
|--------------------------------------------------------------------------
| LOAD PAYOUTS
|--------------------------------------------------------------------------
*/

$payoutStmt =
$conn->prepare("
    SELECT *

    FROM seller_payouts

    WHERE seller_id = ?

    ORDER BY id DESC

    LIMIT 10
");

$payoutStmt->bind_param(
    "i",
    $withdrawal['user_id']
);

$payoutStmt->execute();

$payouts =
$payoutStmt
->get_result();

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if(
    empty(
        $_SESSION['csrf_token']
    )
)
{
    $_SESSION['csrf_token'] =
    bin2hex(
        random_bytes(32)
    );
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Withdrawal Details

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
    border-radius:14px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.amount{
    font-size:34px;
    font-weight:700;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>

<i class="fas fa-file-invoice-dollar"></i>

Withdrawal Details

</h2>

<p class="text-muted">

Review seller withdrawal request

</p>

</div>

<a
href="withdrawals.php"
class="btn btn-secondary">

Back

</a>

</div>

<div class="card mb-4">

<div class="card-body">

<div class="row">

<div class="col-md-8">

<h4>

Withdrawal #<?= (int)$withdrawal['id'] ?>

</h4>

<div class="amount text-success">

TZS

<?= number_format(
(float)$withdrawal['amount'],
2
) ?>

</div>

<p>

Method:

<strong>

<?= htmlspecialchars(
$withdrawal['method']
) ?>

</strong>

</p>

<p>

Reference:

<strong>

<?= htmlspecialchars(
$withdrawal['transaction_reference']
?? '-'
) ?>

</strong>

</p>

</div>

<div class="col-md-4 text-end">

<?php

$badge =
match(
    $withdrawal['status']
)
{
    'pending'  => 'warning',
    'approved' => 'success',
    'paid'     => 'primary',
    'rejected' => 'danger',
    default    => 'secondary'
};

?>

<span
class="badge bg-<?= $badge ?> p-3">

<?= strtoupper(
$withdrawal['status']
) ?>

</span>

</div>

</div>

</div>

</div>

<div class="card mb-4">

<div class="card-header">

Seller Information

</div>

<div class="card-body">

<div class="row">

<div class="col-md-6">

<p>

<strong>Name:</strong>

<?= htmlspecialchars(
$withdrawal['full_name']
) ?>

</p>

<p>

<strong>Email:</strong>

<?= htmlspecialchars(
$withdrawal['email']
) ?>

</p>

<p>

<strong>Phone:</strong>

<?= htmlspecialchars(
$withdrawal['phone']
) ?>

</p>

</div>

<div class="col-md-6">

<p>

<strong>Shop:</strong>

<?= htmlspecialchars(
$withdrawal['shop_name']
?? '-'
) ?>

</p>

<p>

<strong>Shop Status:</strong>

<?= htmlspecialchars(
$withdrawal['shop_status']
?? '-'
) ?>

</p>

<p>

<strong>Verified:</strong>

<?= (int)$withdrawal['verified'] === 1
? 'Yes'
: 'No' ?>

</p>

</div>

</div>

</div>

</div>
<div class="card mb-4">

<div class="card-header">

Wallet Information

</div>

<div class="card-body">

<div class="row">

<div class="col-md-3">

<h6>

Available Balance

</h6>

<h4 class="text-success">

<?= number_format(
(float)$withdrawal['available_balance'],
2
) ?>

</h4>

</div>

<div class="col-md-3">

<h6>

Pending Balance

</h6>

<h4 class="text-warning">

<?= number_format(
(float)$withdrawal['pending_balance'],
2
) ?>

</h4>

</div>

<div class="col-md-3">

<h6>

Total Earned

</h6>

<h4>

<?= number_format(
(float)$withdrawal['total_earned'],
2
) ?>

</h4>

</div>

<div class="col-md-3">

<h6>

Total Withdrawn

</h6>

<h4 class="text-danger">

<?= number_format(
(float)$withdrawal['total_withdrawn'],
2
) ?>

</h4>

</div>

</div>

</div>

</div>

<div class="card mb-4">

<div class="card-header">

Payment Details

</div>

<div class="card-body">

<div class="row">

<div class="col-md-6">

<p>

<strong>Payment Method:</strong>

<?= htmlspecialchars(
$withdrawal['method']
) ?>

</p>

<p>

<strong>Account Name:</strong>

<?= htmlspecialchars(
$withdrawal['account_name']
?? '-'
) ?>

</p>

<p>

<strong>Account Number:</strong>

<?= htmlspecialchars(
$withdrawal['account_number']
?? '-'
) ?>

</p>

</div>

<div class="col-md-6">

<p>

<strong>Requested At:</strong>

<?= date(
'd M Y H:i',
strtotime(
$withdrawal['requested_at']
)
) ?>

</p>

<p>

<strong>Processed At:</strong>

<?= !empty(
$withdrawal['processed_at']
)
? date(
'd M Y H:i',
strtotime(
$withdrawal['processed_at']
))
: '-'; ?>

</p>

<p>

<strong>Transaction Reference:</strong>

<?= htmlspecialchars(
$withdrawal['transaction_reference']
?? '-'
) ?>

</p>

</div>

</div>

</div>

</div>

<div class="card mb-4">

<div class="card-header bg-light">

Risk Assessment

</div>

<div class="card-body">

<?php

$riskFlags = [];

if(
(float)$withdrawal['amount'] > 1000000
)
{
    $riskFlags[] =
    "Large withdrawal amount.";
}

if(
(int)$withdrawal['verified'] === 0
)
{
    $riskFlags[] =
    "Seller shop is not verified.";
}

if(
$withdrawal['shop_status'] !== 'approved'
)
{
    $riskFlags[] =
    "Shop status is not approved.";
}

?>

<?php if(
!empty($riskFlags)
): ?>

<div class="alert alert-warning">

<strong>

Review Required:

</strong>

<ul class="mb-0">

<?php foreach(
$riskFlags as $flag
): ?>

<li>

<?= htmlspecialchars(
$flag
) ?>

</li>

<?php endforeach; ?>

</ul>

</div>

<?php else: ?>

<div class="alert alert-success">

No risk indicators detected.

</div>

<?php endif; ?>

</div>

</div>

<div class="card mb-4">

<div class="card-header">

Recent Withdrawals

</div>

<div class="card-body table-responsive">

<table class="table table-bordered">

<thead>

<tr>

<th>ID</th>
<th>Amount</th>
<th>Method</th>
<th>Status</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php while(
$item =
$withdrawalHistory->fetch_assoc()
): ?>

<tr>

<td>

#<?= (int)$item['id'] ?>

</td>

<td>

TZS

<?= number_format(
(float)$item['amount'],
2
) ?>

</td>

<td>

<?= htmlspecialchars(
$item['method']
) ?>

</td>

<td>

<?= ucfirst(
$item['status']
) ?>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$item['requested_at']
)
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<div class="card mb-4">

<div class="card-header">

Payout History

</div>

<div class="card-body table-responsive">

<table class="table table-striped">

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

<?php if(
$payouts->num_rows > 0
): ?>

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

<?php else: ?>

<tr>

<td colspan="6">

No payouts found.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

<div class="card mb-4">

<div class="card-header">

Admin Notes & Proof

</div>

<div class="card-body">

<p>

<strong>Admin Notes:</strong>

</p>

<div class="border rounded p-3 bg-light">

<?= nl2br(
htmlspecialchars(
$withdrawal['admin_notes']
?? $withdrawal['admin_note']
?? 'No notes available.'
)
) ?>

</div>

<hr>

<p>

<strong>Payment Proof:</strong>

</p>

<?php if(
!empty(
$withdrawal['payment_proof']
)
): ?>

<a
href="../<?= htmlspecialchars(
$withdrawal['payment_proof']
) ?>"
target="_blank"
class="btn btn-primary">

View Payment Proof

</a>

<?php else: ?>

<span class="text-muted">

No proof uploaded.

</span>

<?php endif; ?>

</div>

</div>

<div class="card mb-4">

<div class="card-body">

<div class="d-flex gap-2 flex-wrap">

<?php if(
$withdrawal['status']
=== 'pending'
): ?>

<form
method="POST"
action="withdrawal-action.php">

<input
type="hidden"
name="csrf_token"
value="<?= $_SESSION['csrf_token'] ?>">

<input
type="hidden"
name="withdrawal_id"
value="<?= (int)$withdrawal['id'] ?>">

<input
type="hidden"
name="action"
value="approve">

<button
class="btn btn-success">

Approve

</button>

</form>

<form
method="POST"
action="withdrawal-action.php">

<input
type="hidden"
name="csrf_token"
value="<?= $_SESSION['csrf_token'] ?>">

<input
type="hidden"
name="withdrawal_id"
value="<?= (int)$withdrawal['id'] ?>">

<input
type="hidden"
name="action"
value="reject">

<button
class="btn btn-danger">

Reject

</button>

</form>

<?php endif; ?>

<?php if(
$withdrawal['status']
=== 'approved'
): ?>

<form
method="POST"
action="withdrawal-action.php">

<input
type="hidden"
name="csrf_token"
value="<?= $_SESSION['csrf_token'] ?>">

<input
type="hidden"
name="withdrawal_id"
value="<?= (int)$withdrawal['id'] ?>">

<input
type="hidden"
name="action"
value="mark_paid">

<button
class="btn btn-primary">

Mark Paid

</button>

</form>

<?php endif; ?>

<a
href="withdrawals.php"
class="btn btn-secondary">

Back

</a>

</div>

</div>

</div>
</div>

</body>

</html>