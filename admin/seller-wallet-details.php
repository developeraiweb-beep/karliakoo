<?php

declare(strict_types=1);

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();
requireRole(['admin']);

$admin = currentUser();

$sellerId = (int)($_GET['id'] ?? 0);

if ($sellerId <= 0)
{
    header("Location: seller-wallets.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| SELLER + WALLET
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT

        u.id,
        u.full_name,
        u.email,
        u.phone,
        u.status,
        u.created_at,

        sw.available_balance,
        sw.pending_balance,
        sw.total_earned,
        sw.total_withdrawn,

        s.shop_name,
        s.shop_slug,
        s.verified,
        s.status AS shop_status

    FROM users u

    LEFT JOIN seller_wallets sw
        ON sw.seller_id = u.id

    LEFT JOIN shops s
        ON s.seller_id = u.id

    WHERE u.id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $sellerId
);

$stmt->execute();

$seller =
$stmt
->get_result()
->fetch_assoc();

if(!$seller)
{
    die("Seller not found.");
}

/*
|--------------------------------------------------------------------------
| COMMISSION TOTALS
|--------------------------------------------------------------------------
*/

$commissionSummary = [

    'earned' => 0,
    'paid' => 0,
    'pending' => 0

];

$commissionQuery =
$conn->prepare("
    SELECT

        status,
        SUM(amount) total

    FROM commissions

    WHERE user_id = ?

    GROUP BY status
");

$commissionQuery->bind_param(
    "i",
    $sellerId
);

$commissionQuery->execute();

$commissionResult =
$commissionQuery->get_result();

while(
    $row =
    $commissionResult->fetch_assoc()
)
{
    $commissionSummary[
        $row['status']
    ] =
    (float)$row['total'];
}

/*
|--------------------------------------------------------------------------
| TOTAL WITHDRAWALS
|--------------------------------------------------------------------------
*/

$withdrawStats =
$conn->prepare("
    SELECT

        COUNT(*) total_requests,
        SUM(amount) total_amount

    FROM withdrawals

    WHERE user_id = ?
");

$withdrawStats->bind_param(
    "i",
    $sellerId
);

$withdrawStats->execute();

$withdrawSummary =
$withdrawStats
->get_result()
->fetch_assoc();

/*
|--------------------------------------------------------------------------
| RECENT WITHDRAWALS
|--------------------------------------------------------------------------
*/

$withdrawalsStmt =
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
    LIMIT 20
");

$withdrawalsStmt->bind_param(
    "i",
    $sellerId
);

$withdrawalsStmt->execute();

$withdrawals =
$withdrawalsStmt
->get_result();

/*
|--------------------------------------------------------------------------
| PAYOUT HISTORY
|--------------------------------------------------------------------------
*/

$payoutsStmt =
$conn->prepare("
    SELECT
        *
    FROM seller_payouts
    WHERE seller_id = ?
    ORDER BY id DESC
    LIMIT 20
");

$payoutsStmt->bind_param(
    "i",
    $sellerId
);

$payoutsStmt->execute();

$payouts =
$payoutsStmt
->get_result();
/*
|--------------------------------------------------------------------------
| COMMISSION HISTORY
|--------------------------------------------------------------------------
*/

$commissionStmt =
$conn->prepare("
    SELECT
        *
    FROM commissions
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 20
");

$commissionStmt->bind_param(
    "i",
    $sellerId
);

$commissionStmt->execute();

$commissions =
$commissionStmt
->get_result();
/*
|--------------------------------------------------------------------------
| AUDIT LOGS
|--------------------------------------------------------------------------
*/

$auditStmt =
$conn->prepare("
    SELECT
        *
    FROM audit_logs
    WHERE
        action LIKE ?
    ORDER BY id DESC
    LIMIT 50
");

$auditSearch =
"%seller #" .
$sellerId .
    "%";

$auditStmt->bind_param(
    "s",
    $auditSearch
);

$auditStmt->execute();

$auditLogs =
$auditStmt
->get_result();


$walletMessage = '';
$walletError   = '';

if (
$_SERVER['REQUEST_METHOD'] === 'POST'
&&
isset($_POST['adjust_wallet'])
)
{
$amount = (float)($_POST['amount'] ?? 0);


$type = trim(
    $_POST['adjustment_type']
    ?? ''
);

$note = trim(
    $_POST['note']
    ?? ''
);

if ($amount <= 0)
{
    $walletError = 'Invalid amount.';
}
elseif (
    !in_array(
        $type,
        ['credit','debit'],
        true
    )
)
{
    $walletError = 'Invalid adjustment type.';
}
else
{
    $conn->begin_transaction();

    try
    {
        /*
        |--------------------------------------------------------------------------
        | GET OR CREATE WALLET
        |--------------------------------------------------------------------------
        */

        $walletStmt = $conn->prepare("
            SELECT id, balance
            FROM wallets
            WHERE user_id = ?
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

        if (!$wallet)
        {
            $createWallet =
            $conn->prepare("
                INSERT INTO wallets
                (
                    user_id,
                    balance
                )
                VALUES
                (
                    ?,
                    0
                )
            ");

            $createWallet->bind_param(
                "i",
                $sellerId
            );

            $createWallet->execute();

            $walletId =
            (int)$conn->insert_id;

            $currentBalance = 0;
        }
        else
        {
            $walletId =
            (int)$wallet['id'];

            $currentBalance =
            (float)$wallet['balance'];
        }

        /*
        |--------------------------------------------------------------------------
        | DEBIT VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $type === 'debit'
            &&
            $currentBalance < $amount
        )
        {
            throw new Exception(
                'Insufficient wallet balance.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE BALANCE
        |--------------------------------------------------------------------------
        */

        if ($type === 'credit')
        {
            $newBalance =
            $currentBalance + $amount;
        }
        else
        {
            $newBalance =
            $currentBalance - $amount;
        }

        $updateWallet =
        $conn->prepare("
            UPDATE wallets
            SET balance = ?
            WHERE id = ?
            LIMIT 1
        ");

        $updateWallet->bind_param(
            "di",
            $newBalance,
            $walletId
        );

        $updateWallet->execute();

        /*
        |--------------------------------------------------------------------------
        | LOG TRANSACTION
        |--------------------------------------------------------------------------
        */

        $description =
        !empty($note)
        ? $note
        : 'Manual wallet adjustment by admin';

        $transaction =
        $conn->prepare("
            INSERT INTO wallet_transactions
            (
                wallet_id,
                type,
                amount,
                description
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?
            )
        ");

        $transaction->bind_param(
            "isds",
            $walletId,
            $type,
            $amount,
            $description
        );

        $transaction->execute();

        /*
        |--------------------------------------------------------------------------
        | OPTIONAL SELLER WALLET TABLE
        |--------------------------------------------------------------------------
        */

        $sellerWalletExists =
        $conn->query("
            SHOW TABLES LIKE 'seller_wallets'
        ");

        if (
            $sellerWalletExists &&
            $sellerWalletExists->num_rows > 0
        )
        {
            if ($type === 'credit')
            {
                $sellerWallet =
                $conn->prepare("
                    UPDATE seller_wallets
                    SET
                        available_balance =
                        available_balance + ?,

                        total_earned =
                        total_earned + ?

                    WHERE seller_id = ?
                ");

                $sellerWallet->bind_param(
                    "ddi",
                    $amount,
                    $amount,
                    $sellerId
                );
            }
            else
            {
                $sellerWallet =
                $conn->prepare("
                    UPDATE seller_wallets
                    SET
                        available_balance =
                        available_balance - ?
                    WHERE seller_id = ?
                ");

                $sellerWallet->bind_param(
                    "di",
                    $amount,
                    $sellerId
                );
            }

            $sellerWallet->execute();
        }

        $conn->commit();

        $walletMessage =
        'Wallet adjusted successfully.';
    }
    catch (Exception $e)
    {
        $conn->rollback();

        $walletError =
        $e->getMessage();
    }
}


}


            /*
            |----------------------------------------------------------
            | WALLET TRANSACTION LOG
            |----------------------------------------------------------
            */

            $transaction =
            $conn->prepare("
                INSERT INTO wallet_transactions
                (
                    wallet_id,
                    amount,
                    type,
                    description,
                    created_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    NOW()
                )
            ");

            $transaction->bind_param(
                "idss",
                $sellerId,
                $amount,
                $type,
                $note
            );

            $transaction->execute();


                                                                        

$audit =
$conn->prepare("
INSERT INTO audit_logs
(
user_id,
action,
table_name,
created_at
)
VALUES
(
?,
?,
?,
NOW()
)
");

if (!$audit)
{
throw new Exception(
'Failed to prepare audit log statement.'
);
}

$action = 'wallet_adjustment';



$safeType = strtoupper(
(string)($type ?? 'UNKNOWN')
);

$safeNote = trim(
(string)($note ?? '')
);

if ($safeNote === '')
{
$safeNote =
'Manual wallet adjustment';
}


$safeAmount =
is_numeric($amount)
? (float)$amount
: 0.00;

$safeSellerId =
isset($sellerId)
? (int)$sellerId
: 0;

$auditDescription = sprintf(
'%s | Amount: TZS %s | Seller ID: %d | Note: %s',
$safeType,
number_format(
$safeAmount,
2
),
$safeSellerId,
$safeNote
);






$audit->bind_param(
"iss",
$admin['id'],
$action,
$auditDescription
);

if (!$audit->execute())
{
throw new Exception(
'Failed to write audit log.'
);
}



$conn->commit();

$walletMessage =
sprintf(
'Wallet successfully %sed by TZS %s.',
$type === 'debit'
? 'debit'
: 'credit',
$safeAmount =
is_numeric($amount)
? (float)$amount
: 0.00
);

        
    

?>
<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Seller Wallet Details

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
rel="stylesheet">

<style>

body{
    background:#f4f6fa;
}

.card{
    border:none;
    border-radius:14px;
    box-shadow:0 2px 12px rgba(0,0,0,.08);
}

.stat{
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

Seller Wallet Details

</h2>

<p class="text-muted">

Financial profile and transaction overview

</p>

</div>

<a
href="seller-wallets.php"
class="btn btn-secondary">

Back

</a>

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
$seller['full_name']
) ?>

</p>

<p>

<strong>Email:</strong>

<?= htmlspecialchars(
$seller['email']
) ?>

</p>

<p>

<strong>Phone:</strong>

<?= htmlspecialchars(
$seller['phone']
) ?>

</p>

</div>

<div class="col-md-6">

<p>

<strong>Shop:</strong>

<?= htmlspecialchars(
$seller['shop_name']
?? 'No Shop'
) ?>

</p>

<p>

<strong>Shop Status:</strong>

<?= htmlspecialchars(
$seller['shop_status']
?? '-'
) ?>

</p>

<p>

<strong>Verified:</strong>

<?= (int)$seller['verified'] === 1
? 'Yes'
: 'No' ?>

</p>

</div>

</div>

</div>

</div>
<div class="row mb-4">

<div class="col-md-3">

<div class="card">

<div class="card-body text-center">

<h6>

Available Balance

</h6>

<div class="stat text-success">

<?= number_format(
(float)$seller['available_balance'],
2
) ?>

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card">

<div class="card-body text-center">

<h6>

Pending Balance

</h6>

<div class="stat text-warning">

<?= number_format(
(float)$seller['pending_balance'],
2
) ?>

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card">

<div class="card-body text-center">

<h6>

Total Earned

</h6>

<div class="stat text-primary">

<?= number_format(
(float)$seller['total_earned'],
2
) ?>

</div>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card">

<div class="card-body text-center">

<h6>

Total Withdrawn

</h6>

<div class="stat text-danger">

<?= number_format(
(float)$seller['total_withdrawn'],
2
) ?>

</div>

</div>

</div>

</div>

</div>

<div class="card mb-4">

<div class="card-header">

Commission Overview

</div>

<div class="card-body">

<div class="row">

<div class="col-md-4">

<h6>Earned</h6>

<h4 class="text-success">

<?= number_format(
(float)$commissionSummary['earned'],
2
) ?>

</h4>

</div>

<div class="col-md-4">

<h6>Paid</h6>

<h4 class="text-primary">

<?= number_format(
(float)$commissionSummary['paid'],
2
) ?>

</h4>

</div>

<div class="col-md-4">

<h6>Pending</h6>

<h4 class="text-warning">

<?= number_format(
(float)$commissionSummary['pending'],
2
) ?>

</h4>

</div>

</div>

</div>

</div>
<div class="card mb-4">

<div class="card-header">

Withdrawal Summary

</div>

<div class="card-body">

<p>

<strong>Total Requests:</strong>

<?= (int)$withdrawSummary['total_requests'] ?>

</p>

<p>

<strong>Total Withdrawal Amount:</strong>

TZS

<?= number_format(
(float)$withdrawSummary['total_amount'],
2
) ?>

</p>

</div>

</div>
<div class="card mb-4">

<div class="card-header">

Recent Withdrawals

</div>

<div class="card-body table-responsive">

<table class="table table-bordered table-hover">

<thead>

<tr>

<th>ID</th>
<th>Amount</th>
<th>Method</th>
<th>Status</th>
<th>Requested</th>
<th>Processed</th>

</tr>

</thead>

<tbody>

<?php if($withdrawals->num_rows > 0): ?>

<?php while($row = $withdrawals->fetch_assoc()): ?>

<tr>

<td>

#<?= (int)$row['id'] ?>

</td>

<td>

TZS
<?= number_format(
(float)$row['amount'],
2
) ?>

</td>

<td>

<?= htmlspecialchars(
$row['method']
) ?>

</td>

<td>

<?php

$badge =
match($row['status'])
{
    'pending'  => 'warning',
    'approved' => 'success',
    'paid'     => 'primary',
    'rejected' => 'danger',
    default    => 'secondary'
};

?>

<span
class="badge bg-<?= $badge ?>">

<?= ucfirst(
$row['status']
) ?>

</span>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$row['requested_at']
)
) ?>

</td>

<td>

<?= !empty($row['processed_at'])
? date(
'd M Y',
strtotime(
$row['processed_at']
))
: '-'; ?>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td colspan="6">

No withdrawals found.

</td>

</tr>

<?php endif; ?>

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

<?php if($payouts->num_rows > 0): ?>

<?php while($payout = $payouts->fetch_assoc()): ?>

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

Commission Ledger

</div>

<div class="card-body table-responsive">

<table class="table table-bordered">

<thead>

<tr>

<th>ID</th>
<th>Order</th>
<th>Type</th>
<th>Amount</th>
<th>Status</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php if($commissions->num_rows > 0): ?>

<?php while($commission = $commissions->fetch_assoc()): ?>

<tr>

<td>

#<?= (int)$commission['id'] ?>

</td>

<td>

<?= (int)(
$commission['order_id']
?? 0
) ?>

</td>

<td>

<?= ucfirst(
$commission['commission_type']
) ?>

</td>

<td>

TZS
<?= number_format(
(float)$commission['amount'],
2
) ?>

</td>

<td>

<?= ucfirst(
$commission['status']
) ?>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$commission['created_at']
)
) ?>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td colspan="6">

No commission records found.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>
<div class="card mb-4">

<div class="card-header">

Financial Risk Assessment

</div>

<div class="card-body">

<?php

$riskMessages = [];

if(
(float)$seller['pending_balance']
>
(float)$seller['available_balance']
)
{
    $riskMessages[] =
    "Pending balance exceeds available balance.";
}

if(
(float)$seller['total_withdrawn']
>
(float)$seller['total_earned']
)
{
    $riskMessages[] =
    "Withdrawals exceed recorded earnings.";
}

if(
(float)$seller['available_balance']
>
10000000
)
{
    $riskMessages[] =
    "High wallet balance requires review.";
}

?>

<?php if(!empty($riskMessages)): ?>

<div class="alert alert-danger">

<ul class="mb-0">

<?php foreach($riskMessages as $risk): ?>

<li>

<?= htmlspecialchars(
$risk
) ?>

</li>

<?php endforeach; ?>

</ul>

</div>

<?php else: ?>

<div class="alert alert-success">

No financial risks detected.

</div>

<?php endif; ?>

</div>

</div>
<div class="card mb-4">

<div class="card-header">

Admin Actions

</div>

<div class="card-body">

<div class="d-flex gap-2 flex-wrap">

<a
href="withdrawals.php?search=<?= urlencode($seller['full_name']) ?>"
class="btn btn-warning">

View Withdrawals

</a>

<a
href="seller-payouts.php?seller_id=<?= $sellerId ?>"
class="btn btn-success">

View Payouts

</a>

<a
href="commission-reports.php?seller_id=<?= $sellerId ?>"
class="btn btn-primary">

Commission Report

</a>

</div>

</div>

</div>
<div class="card mb-4">

<div class="card-header">

Wallet Adjustment

</div>

<div class="card-body">

<?php if($walletMessage): ?>

<div class="alert alert-success">

<?= htmlspecialchars(
$walletMessage
) ?>

</div>

<?php endif; ?>

<?php if($walletError): ?>

<div class="alert alert-danger">

<?= htmlspecialchars(
$walletError
) ?>

</div>

<?php endif; ?>

<form method="POST">

<div class="row">

<div class="col-md-3">

<select
name="adjustment_type"
class="form-select"
required>

<option value="credit">

Credit

</option>

<option value="debit">

Debit

</option>

</select>

</div>

<div class="col-md-3">

<input
type="number"
step="0.01"
name="amount"
class="form-control"
placeholder="Amount"
required>

</div>

<div class="col-md-4">

<input
type="text"
name="note"
class="form-control"
placeholder="Reason"
required>

</div>

<div class="col-md-2">

<button
type="submit"
name="adjust_wallet"
class="btn btn-primary w-100">

Apply

</button>

</div>

</div>

</form>

</div>

</div>

<div class="card mb-4">

<div class="card-header">

Audit Timeline

</div>

<div class="card-body">

<?php if(
$auditLogs->num_rows > 0
): ?>

<div class="list-group">

<?php while(
$log =
$auditLogs->fetch_assoc()
): ?>

<div
class="list-group-item">

<strong>

<?= htmlspecialchars(
$log['action']
) ?>

</strong>

<br>

<?= htmlspecialchars(
$log['description']
) ?>

<br>

<small class="text-muted">

<?= date(
'd M Y H:i',
strtotime(
$log['created_at']
)
) ?>

</small>

</div>

<?php endwhile; ?>

</div>

<?php else: ?>

<div class="alert alert-info">

No audit logs found.

</div>

<?php endif; ?>

</div>

</div>
<div class="card mb-4">

<div class="card-header">

Export Financial Data

</div>

<div class="card-body">

<div class="d-flex gap-2">

<a
href="export-wallet-pdf.php?id=<?= $sellerId ?>"
class="btn btn-danger">

PDF Report

</a>

<a
href="export-wallet-excel.php?id=<?= $sellerId ?>"
class="btn btn-success">

Excel Report

</a>

</div>

</div>

</div>
<div class="card mb-4">

<div class="card-header">

Fraud Monitoring

</div>

<div class="card-body">

<?php

$fraudWarnings = [];

if(
(float)$seller['total_withdrawn']
>
(
(float)$seller['total_earned']
* 0.95
)
)
{
    $fraudWarnings[] =
    "Seller has withdrawn almost all earnings.";
}

if(
(float)$seller['pending_balance']
>
5000000
)
{
    $fraudWarnings[] =
    "Large pending balance detected.";
}

?>

<?php if(!empty($fraudWarnings)): ?>

<div class="alert alert-warning">

<ul>

<?php foreach(
$fraudWarnings
as $warning
): ?>

<li>

<?= htmlspecialchars(
$warning
) ?>

</li>

<?php endforeach; ?>

</ul>

</div>

<?php else: ?>

<div class="alert alert-success">

No fraud indicators detected.

</div>

<?php endif; ?>

</div>

</div>
</div>

</body>

</html>