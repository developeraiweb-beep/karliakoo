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

$success = "";
$error = "";

/*
|--------------------------------------------------------------------------
| LOAD SHOP
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
    die("Shop approval required.");
}

if (
    (int)$shop['suspended'] === 1
)
{
    die("Shop suspended.");
}

/*
|--------------------------------------------------------------------------
| LOAD WALLET
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

if (!$wallet)
{
    die("Seller wallet not found.");
}

$availableBalance =
(float)$wallet['available_balance'];

/*
|--------------------------------------------------------------------------
| HANDLE REQUEST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
)
{
    $amount =
    (float)(
        $_POST['amount']
        ?? 0
    );

    $method =
    trim(
        $_POST['method']
        ?? ''
    );

    $accountName =
    trim(
        $_POST['account_name']
        ?? ''
    );

    $accountNumber =
    trim(
        $_POST['account_number']
        ?? ''
    );

    if (
        $amount <= 0
    )
    {
        $error =
        "Invalid withdrawal amount.";
    }
    elseif (
        $amount > $availableBalance
    )
    {
        $error =
        "Insufficient wallet balance.";
    }
    elseif (
        empty($method)
    )
    {
        $error =
        "Select payment method.";
    }
    elseif (
        empty($accountName)
    )
    {
        $error =
        "Account name required.";
    }
    elseif (
        empty($accountNumber)
    )
    {
        $error =
        "Account number required.";
    }
    else
    {
        $conn->begin_transaction();

        try
        {
            /*
            |--------------------------------------------------------------------------
            | CREATE WITHDRAWAL
            |--------------------------------------------------------------------------
            */

            $withdrawStmt =
            $conn->prepare("
                INSERT INTO withdrawals
                (
                    user_id,
                    amount,
                    method,
                    account_name,
                    account_number,
                    status
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, 'pending'
                )
            ");

            $withdrawStmt->bind_param(
                "idsss",
                $sellerId,
                $amount,
                $method,
                $accountName,
                $accountNumber
            );

            $withdrawStmt->execute();


            /*
|--------------------------------------------------------------------------
| RECENT WITHDRAWALS
|--------------------------------------------------------------------------
*/

$historyStmt = $conn->prepare("
    SELECT
        id,
        amount,
        method,
        status,
        requested_at,
        processed_at,
        transaction_reference,
        admin_note,
        admin_notes,
        payment_proof
    FROM withdrawals
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 20
");

$historyStmt->bind_param(
    "i",
    $sellerId
);

$historyStmt->execute();

$withdrawalHistory =
$historyStmt
->get_result();

            /*
            |--------------------------------------------------------------------------
            | UPDATE WALLET
            |--------------------------------------------------------------------------
            */

            $walletUpdate =
            $conn->prepare("
                UPDATE seller_wallets
                SET
                    available_balance =
                    available_balance - ?,

                    pending_balance =
                    pending_balance + ?

                WHERE seller_id = ?
            ");

            $walletUpdate->bind_param(
                "ddi",
                $amount,
                $amount,
                $sellerId
            );

            $walletUpdate->execute();

            $conn->commit();

            $success =
            "Withdrawal request submitted successfully.";

            $availableBalance -= $amount;
        }
        catch(Exception $e)
        {
            $conn->rollback();

            $error =
            "Failed to submit withdrawal request.";
        }
    }
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

Request Withdrawal

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

.card-box{
    border:none;
    border-radius:16px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.balance{
    font-size:36px;
    font-weight:700;
}

</style>

</head>

<body>

<div class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>

<i class="fas fa-money-bill-wave"></i>

Request Withdrawal

</h2>

<p class="text-muted mb-0">

<?= htmlspecialchars($shop['shop_name']) ?>

</p>

</div>

<div>

<a
href="earnings.php"
class="btn btn-secondary">

Back to Earnings

</a>

</div>

</div>

<!-- WALLET SUMMARY -->

<div class="card card-box mb-4">

<div class="card-body">

<div class="row">

<div class="col-md-6">

<h6>

Available Balance

</h6>

<div class="balance text-success">

TZS

<?= number_format(
$availableBalance,
2
) ?>

</div>

</div>

<div class="col-md-6 text-md-end">

<h6>

Status

</h6>

<span class="badge bg-success">

Active Wallet

</span>

</div>

</div>

</div>

</div>

<?php if($success): ?>

<div class="alert alert-success">

<?= htmlspecialchars($success) ?>

</div>

<?php endif; ?>

<?php if($error): ?>

<div class="alert alert-danger">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>

<!-- WITHDRAWAL FORM -->

<div class="card card-box">

<div class="card-header">

Withdrawal Details

</div>

<div class="card-body">

<form method="POST">

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">

Withdrawal Amount (TZS)

</label>

<input
type="number"
step="0.01"
min="1000"
max="<?= (float)$availableBalance ?>"
name="amount"
class="form-control"
required>

<small class="text-muted">

Minimum withdrawal: TZS 1,000

</small>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Payment Method

</label>

<select
name="method"
class="form-select"
required>

<option value="">

Select Method

</option>

<option value="M-Pesa">

M-Pesa

</option>

<option value="Airtel Money">

Airtel Money

</option>

<option value="Tigo Pesa">

Tigo Pesa

</option>

<option value="HaloPesa">

HaloPesa

</option>

<option value="Bank Transfer">

Bank Transfer

</option>

</select>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Account Holder Name

</label>

<input
type="text"
name="account_name"
class="form-control"
required>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Phone / Account Number

</label>

<input
type="text"
name="account_number"
class="form-control"
required>

</div>

<div class="col-md-12">

<div class="alert alert-info">

<strong>Important:</strong>

Withdrawal requests are reviewed by the finance team before approval. Approved requests are paid to the selected mobile wallet or bank account.

</div>

</div>

<div class="col-md-12">

<button
type="submit"
class="btn btn-success">

<i class="fas fa-paper-plane"></i>

Submit Withdrawal Request

</button>

<a
href="earnings.php"
class="btn btn-outline-secondary">

Cancel

</a>

</div>

</div>

</form>

</div>

</div>

<div class="row mt-4">

<div class="col-md-4">

<div class="card card-box">

<div class="card-body text-center">

<h6>

Pending Withdrawals

</h6>

<?php

$pendingTotal = 0;

$pendingQuery = $conn->prepare("
    SELECT SUM(amount) total
    FROM withdrawals
    WHERE user_id = ?
    AND status='pending'
");

$pendingQuery->bind_param(
    "i",
    $sellerId
);

$pendingQuery->execute();

$pendingTotal =
(float)(
$pendingQuery
->get_result()
->fetch_assoc()['total']
?? 0
);

?>

<h3 class="text-warning">

TZS <?= number_format($pendingTotal,2) ?>

</h3>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card card-box">

<div class="card-body text-center">

<h6>

Approved Withdrawals

</h6>

<?php

$approvedTotal = 0;

$approvedQuery = $conn->prepare("
    SELECT SUM(amount) total
    FROM withdrawals
    WHERE user_id = ?
    AND status IN ('approved','paid')
");

$approvedQuery->bind_param(
    "i",
    $sellerId
);

$approvedQuery->execute();

$approvedTotal =
(float)(
$approvedQuery
->get_result()
->fetch_assoc()['total']
?? 0
);

?>

<h3 class="text-success">

TZS <?= number_format($approvedTotal,2) ?>

</h3>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card card-box">

<div class="card-body text-center">

<h6>

Rejected Withdrawals

</h6>

<?php

$rejectedTotal = 0;

$rejectedQuery = $conn->prepare("
    SELECT SUM(amount) total
    FROM withdrawals
    WHERE user_id = ?
    AND status='rejected'
");

$rejectedQuery->bind_param(
    "i",
    $sellerId
);

$rejectedQuery->execute();

$rejectedTotal =
(float)(
$rejectedQuery
->get_result()
->fetch_assoc()['total']
?? 0
);

?>

<h3 class="text-danger">

TZS <?= number_format($rejectedTotal,2) ?>

</h3>

</div>

</div>

</div>

</div>

<div class="card card-box mt-4">

<div class="card-header">

Recent Withdrawal Requests

</div>

<div class="card-body table-responsive">

<table class="table table-bordered table-hover align-middle">

<thead>

<tr>

<th>ID</th>
<th>Amount</th>
<th>Method</th>
<th>Status</th>
<th>Reference</th>
<th>Requested</th>
<th>Processed</th>
<th>Notes</th>

</tr>

</thead>

<tbody>

<?php if(
$withdrawalHistory->num_rows > 0
): ?>

<?php while(
$row =
$withdrawalHistory->fetch_assoc()
): ?>

<?php

$statusColor =
match($row['status'])
{
    'approved' => 'success',
    'paid'     => 'primary',
    'rejected' => 'danger',
    default    => 'warning'
};

?>

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

<span
class="badge bg-<?= $statusColor ?>">

<?= ucfirst(
$row['status']
) ?>

</span>

</td>

<td>

<?= htmlspecialchars(
$row['transaction_reference']
?? '-'
) ?>

</td>

<td>

<?= date(
'd M Y H:i',
strtotime(
$row['requested_at']
)
) ?>

</td>

<td>

<?= !empty($row['processed_at'])
? date(
'd M Y H:i',
strtotime(
$row['processed_at']
)
)
: '-'; ?>

</td>

<td>

<?= htmlspecialchars(
$row['admin_notes']
?: $row['admin_note']
?: '-'
) ?>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td colspan="8" class="text-center text-muted">

No withdrawal requests found.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

