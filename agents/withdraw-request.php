<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

if (!$user || $user['role'] !== 'agent') {
    die("Access denied.");
}

$agent_id = (int)$user['id'];

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| AVAILABLE BALANCE
|--------------------------------------------------------------------------
*/
$balanceStmt = $conn->prepare("
    SELECT
        COALESCE(SUM(commission_amount),0) balance
    FROM agent_commissions
    WHERE agent_id = ?
    AND status = 'approved'
");

$balanceStmt->bind_param("i", $agent_id);
$balanceStmt->execute();

$availableBalance = (float)$balanceStmt
    ->get_result()
    ->fetch_assoc()['balance'];

/*
|--------------------------------------------------------------------------
| SUBMIT REQUEST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $amount = (float)($_POST['amount'] ?? 0);

    $method = trim($_POST['method'] ?? '');

    $account_name = trim($_POST['account_name'] ?? '');

    $account_number = trim($_POST['account_number'] ?? '');

    if ($amount <= 0) {

        $error = "Enter a valid amount.";

    } elseif ($amount > $availableBalance) {

        $error = "Amount exceeds available balance.";

    } elseif (
        empty($method) ||
        empty($account_name) ||
        empty($account_number)
    ) {

        $error = "All fields are required.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | CHECK PENDING REQUEST
        |--------------------------------------------------------------------------
        */
        $pendingCheck = $conn->prepare("
            SELECT id
            FROM agent_withdrawals
            WHERE agent_id = ?
            AND status = 'pending'
            LIMIT 1
        ");

        $pendingCheck->bind_param("i", $agent_id);
        $pendingCheck->execute();

        if ($pendingCheck->get_result()->num_rows > 0) {

            $error = "You already have a pending withdrawal request.";

        } else {

            $conn->begin_transaction();

            try {

                /*
                |--------------------------------------------------------------------------
                | CREATE WITHDRAWAL REQUEST
                |--------------------------------------------------------------------------
                */
                $insert = $conn->prepare("
                    INSERT INTO agent_withdrawals
                    (
                        agent_id,
                        amount,
                        method,
                        account_name,
                        account_number,
                        status
                    )
                    VALUES
                    (
                        ?,?,?,?,?, 'pending'
                    )
                ");

                $insert->bind_param(
                    "idsss",
                    $agent_id,
                    $amount,
                    $method,
                    $account_name,
                    $account_number
                );

                $insert->execute();

                /*
                |--------------------------------------------------------------------------
                | LOCK COMMISSIONS
                |--------------------------------------------------------------------------
                | Optional:
                | Mark approved commissions as withdrawal_requested
                | if you add such a status later.
                |--------------------------------------------------------------------------
                */

                $conn->commit();

                $success =
                    "Withdrawal request submitted successfully.";

            } catch (Exception $e) {

                $conn->rollback();

                $error =
                    "Failed to process request.";
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| RECENT REQUESTS
|--------------------------------------------------------------------------
*/
$history = $conn->prepare("
    SELECT *
    FROM agent_withdrawals
    WHERE agent_id = ?
    ORDER BY id DESC
    LIMIT 20
");

$history->bind_param("i", $agent_id);
$history->execute();

$requests = $history->get_result();

?>
<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport"
      content="width=device-width,initial-scale=1">

<title>Withdraw Funds</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">

<style>

body{
    background:#f5f5f5;
}

.card-box{
    background:#fff;
    border-radius:12px;
    padding:20px;
}

</style>

</head>
<body>

<div class="container py-4">

<h2 class="mb-4">
Withdraw Earnings
</h2>

<div class="card-box shadow-sm mb-4">

<h4 class="text-success">
Available Balance:
TZS <?= number_format($availableBalance,2) ?>
</h4>

</div>

<?php if($success): ?>
<div class="alert alert-success">
<?= $success ?>
</div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger">
<?= $error ?>
</div>
<?php endif; ?>

<div class="card-box shadow-sm mb-4">

<form method="POST">

<div class="mb-3">

<label class="form-label">
Amount (TZS)
</label>

<input
    type="number"
    step="0.01"
    name="amount"
    class="form-control"
    required>

</div>

<div class="mb-3">

<label class="form-label">
Withdrawal Method
</label>

<select
    name="method"
    class="form-select"
    required>

<option value="">
Select Method
</option>

<option value="mpesa">
M-Pesa
</option>

<option value="tigopesa">
Tigo Pesa
</option>

<option value="airtelmoney">
Airtel Money
</option>

<option value="halopesa">
HaloPesa
</option>

<option value="bank">
Bank Account
</option>

</select>

</div>

<div class="mb-3">

<label class="form-label">
Account Name
</label>

<input
    type="text"
    name="account_name"
    class="form-control"
    required>

</div>

<div class="mb-3">

<label class="form-label">
Account Number / Phone
</label>

<input
    type="text"
    name="account_number"
    class="form-control"
    required>

</div>

<button
    class="btn btn-success">

Submit Withdrawal Request

</button>

</form>

</div>

<div class="card shadow-sm">

<div class="card-header">
Withdrawal History
</div>

<div class="card-body p-0">

<table class="table table-striped mb-0">

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

<?php while($row = $requests->fetch_assoc()): ?>

<tr>

<td>
#<?= $row['id'] ?>
</td>

<td>
TZS <?= number_format($row['amount'],2) ?>
</td>

<td>
<?= strtoupper($row['method']) ?>
</td>

<td>

<?php

$badge = match($row['status']) {

'pending' => 'warning',
'approved' => 'primary',
'paid' => 'success',
'rejected' => 'danger',

default => 'secondary'

};

?>

<span class="badge bg-<?= $badge ?>">
<?= ucfirst($row['status']) ?>
</span>

</td>

<td>
<?= date(
'd M Y H:i',
strtotime($row['created_at'])
) ?>
</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>