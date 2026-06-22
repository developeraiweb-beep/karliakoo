<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user_id = (int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Get Seller Shop
|--------------------------------------------------------------------------
*/
$shopStmt = $conn->prepare("
    SELECT *
    FROM shops
    WHERE shop_name=?
    LIMIT 1
");

$shopStmt->bind_param("i", $user_id);
$shopStmt->execute();

$shop = $shopStmt->get_result()->fetch_assoc();

if(!$shop){
    die("Shop not found.");
}

$shop_id = $shop['id'];

/*
|--------------------------------------------------------------------------
| Wallet (create if not exists)
|--------------------------------------------------------------------------
*/
$conn->query("
INSERT IGNORE INTO seller_wallets(seller_id, balance)
VALUES($seller_id, 0)
");

/*
|--------------------------------------------------------------------------
| Fetch Wallet Balance
|--------------------------------------------------------------------------
*/
$wallet = $conn->query("
SELECT *
FROM seller_wallets
WHERE seller_id=$seller_id
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| Earnings Calculation (simple model)
|--------------------------------------------------------------------------
*/
$earnings = $conn->query("
SELECT
    SUM(oi.price * oi.quantity) as total_earnings
FROM order_items oi
WHERE oi.shop_id=$shop_id
")->fetch_assoc()['total_earnings'] ?? 0;

/*
|--------------------------------------------------------------------------
| Withdraw Request
|--------------------------------------------------------------------------
*/
$error = "";
$success = "";

if($_SERVER['REQUEST_METHOD'] === "POST")
{
    $amount = (float)$_POST['amount'];
    $account = trim($_POST['account_number']);
    $method = trim($_POST['method']);

    if($amount <= 0){
        $error = "Invalid amount.";
    }
    elseif($amount > $wallet['balance']){
        $error = "Insufficient wallet balance.";
    }
    else
    {
        $stmt = $conn->prepare("
            INSERT INTO withdrawals(
                user_id,
                amount,
                method,
                account_number
            )
            VALUES(?,?,?,?)
        ");

        $stmt->bind_param(
            "idss",
            $shop_id,
            $amount,
            $method,
            $account
        );

        if($stmt->execute())
        {
            // Deduct from wallet immediately (pending withdrawal hold)
            $update = $conn->prepare("
                UPDATE seller_wallets
                SET balance = balance - ?
                WHERE shop_id=?
            ");

            $update->bind_param("di", $amount, $shop_id);
            $update->execute();

            $success = "Withdrawal request submitted successfully.";
        }
        else
        {
            $error = "Failed to submit withdrawal.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| Withdrawal History
|--------------------------------------------------------------------------
*/
$withdrawals = $conn->prepare("
SELECT *
FROM withdrawals
WHERE shop_id=?
ORDER BY id DESC
");

$withdrawals->bind_param("i", $shop_id);
$withdrawals->execute();

$list = $withdrawals->get_result();

/*
|--------------------------------------------------------------------------
| Status Badge
|--------------------------------------------------------------------------
*/
function badge($status){
    return match($status){
        'pending' => 'warning',
        'approved' => 'info',
        'paid' => 'success',
        'rejected' => 'danger',
        default => 'secondary'
    };
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Withdrawals</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f5f5f5;
}

.card-box{
    background:white;
    padding:20px;
    border-radius:12px;
}

</style>

</head>

<body>

<div class="container py-4">

<h2 class="mb-4">Withdrawals</h2>

<!-- Wallet Summary -->
<div class="row mb-4">

<div class="col-md-6">
    <div class="card-box">
        <h4>TZS <?= number_format($wallet['balance']) ?></h4>
        <p>Available Balance</p>
    </div>
</div>

<div class="col-md-6">
    <div class="card-box">
        <h4>TZS <?= number_format($earnings) ?></h4>
        <p>Total Earnings</p>
    </div>
</div>

</div>

<!-- Withdraw Form -->
<div class="card-box mb-4">

<h5>Request Withdrawal</h5>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<?php if($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<form method="POST">

<div class="row">

<div class="col-md-4">
    <input type="number" name="amount" class="form-control" placeholder="Amount" required>
</div>

<div class="col-md-4">
    <select name="method" class="form-select">
        <option value="mobile_money">Mobile Money</option>
        <option value="bank">Bank Transfer</option>
    </select>
</div>

<div class="col-md-4">
    <input type="text" name="account_number" class="form-control" placeholder="Account / Phone Number" required>
</div>

</div>

<button class="btn btn-primary mt-3">Submit Request</button>

</form>

</div>

<!-- Withdrawal History -->
<div class="card-box">

<h5>Withdrawal History</h5>

<table class="table">

<thead>

<tr>
<th>ID</th>
<th>Amount</th>
<th>Method</th>
<th>Account</th>
<th>Status</th>
<th>Date</th>
</tr>

</thead>

<tbody>

<?php while($w = $list->fetch_assoc()): ?>

<tr>

<td>#<?= $w['id'] ?></td>

<td>TZS <?= number_format($w['amount']) ?></td>

<td><?= ucfirst($w['method']) ?></td>

<td><?= htmlspecialchars($w['account_number']) ?></td>

<td>
<span class="badge bg-<?= badge($w['status']) ?>">
<?= ucfirst($w['status']) ?>
</span>
</td>

<td>
<?= date("d M Y", strtotime($w['created_at'])) ?>
</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</body>
</html>