<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$userId = (int)($_GET['id'] ?? 0);

if($userId <= 0)
{
    header("Location: users.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT *
FROM users
WHERE id=?
LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$user =
$stmt
->get_result()
->fetch_assoc();

if(!$user)
{
    die("User not found.");
}

/*
|--------------------------------------------------------------------------
| ACCOUNT ACTIONS
|--------------------------------------------------------------------------
*/
if(isset($_POST['change_status']))
{
    $status = $_POST['status'];

    $allowed = [
        'active',
        'pending',
        'suspended'
    ];

    if(in_array($status,$allowed))
    {
        $update = $conn->prepare("
        UPDATE users
        SET status=?
        WHERE id=?
        ");

        $update->bind_param(
            "si",
            $status,
            $userId
        );

        $update->execute();
    }

    header(
        "Location:user-details.php?id=".$userId
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/
$orderCount = 0;
$totalSpent = 0;
$productCount = 0;
$shopCount = 0;
$withdrawCount = 0;
$referralCount = 0;

if(
$conn->query(
"SHOW TABLES LIKE 'orders'"
)->num_rows
){

$stmt = $conn->prepare("
SELECT
COUNT(*) total_orders,
COALESCE(SUM(total_amount),0) total_spent
FROM orders
WHERE user_id=?
");

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$data =
$stmt
->get_result()
->fetch_assoc();

$orderCount =
$data['total_orders'];

$totalSpent =
$data['total_spent'];

}

if(
$conn->query(
"SHOW TABLES LIKE 'products'"
)->num_rows
){

$stmt = $conn->prepare("
SELECT COUNT(*) total
FROM products
WHERE shop_id=?
");

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$productCount =
$stmt
->get_result()
->fetch_assoc()['total'];

}

if(
$conn->query(
"SHOW TABLES LIKE 'shops'"
)->num_rows
){

$stmt = $conn->prepare("
SELECT COUNT(*) total
FROM shops
WHERE seller_id=?
");

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$shopCount =
$stmt
->get_result()
->fetch_assoc()['total'];

}

if(
$conn->query(
"SHOW TABLES LIKE 'withdrawals'"
)->num_rows
){

$stmt = $conn->prepare("
SELECT COUNT(*) total
FROM withdrawals
WHERE user_id=?
");

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$withdrawCount =
$stmt
->get_result()
->fetch_assoc()['total'];

}

if(
$conn->query(
"SHOW TABLES LIKE 'referrals'"
)->num_rows
){

$stmt = $conn->prepare("
SELECT COUNT(*) total
FROM referrals
WHERE agent_id=?
");

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$referralCount =
$stmt
->get_result()
->fetch_assoc()['total'];

}

/*
|--------------------------------------------------------------------------
| RECENT ORDERS
|--------------------------------------------------------------------------
*/
$orders = [];

if(
$conn->query(
"SHOW TABLES LIKE 'orders'"
)->num_rows
){

$stmt = $conn->prepare("
SELECT
id,
order_number,
total_amount,
order_status,
created_at

FROM orders

WHERE user_id=?

ORDER BY id DESC

LIMIT 10
");

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$orders =
$stmt->get_result();

}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>User Details</title>

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
background:#f4f6f9;
}

.card-box{
background:#fff;
padding:20px;
border-radius:15px;
box-shadow:0 2px 10px rgba(0,0,0,.08);
margin-bottom:20px;
}

.metric{
font-size:28px;
font-weight:700;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between mb-4">

<h2>

User Details

</h2>

<a
href="users.php"
class="btn btn-secondary">

Back

</a>

</div>

<!-- PROFILE -->

<div class="card-box">

<div class="row">

<div class="col-md-6">

<h4>

<?= htmlspecialchars(
$user['full_name']
) ?>

</h4>

<p>

<strong>Email:</strong>

<?= htmlspecialchars(
$user['email']
) ?>

</p>

<p>

<strong>Role:</strong>

<span class="badge bg-dark">

<?= ucfirst(
$user['role']
) ?>

</span>

</p>

<p>

<strong>Status:</strong>

<span class="badge bg-<?=
$user['status']=='active'
?
'success'
:
(
$user['status']=='suspended'
?
'danger'
:
'warning'
)
?>">

<?= ucfirst(
$user['status']
) ?>

</span>

</p>

</div>

<div class="col-md-6">

<form method="POST">

<label>

Change Status

</label>

<div class="input-group">

<select
name="status"
class="form-select">

<option value="active">
Active
</option>

<option value="pending">
Pending
</option>

<option value="suspended">
Suspended
</option>

</select>

<button
name="change_status"
class="btn btn-primary">

Update

</button>

</div>

</form>

</div>

</div>

</div>

<!-- STATS -->

<div class="row">

<div class="col-md-2">
<div class="card-box text-center">
<div class="metric">
<?= number_format($orderCount) ?>
</div>
Orders
</div>
</div>

<div class="col-md-2">
<div class="card-box text-center">
<div class="metric">
TZS <?= number_format($totalSpent,0) ?>
</div>
Spent
</div>
</div>

<div class="col-md-2">
<div class="card-box text-center">
<div class="metric">
<?= number_format($productCount) ?>
</div>
Products
</div>
</div>

<div class="col-md-2">
<div class="card-box text-center">
<div class="metric">
<?= number_format($shopCount) ?>
</div>
Shops
</div>
</div>

<div class="col-md-2">
<div class="card-box text-center">
<div class="metric">
<?= number_format($withdrawCount) ?>
</div>
Withdrawals
</div>
</div>

<div class="col-md-2">
<div class="card-box text-center">
<div class="metric">
<?= number_format($referralCount) ?>
</div>
Referrals
</div>
</div>

</div>

<!-- RECENT ORDERS -->

<div class="card-box">

<h4>

Recent Orders

</h4>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Order</th>
<th>Amount</th>
<th>Status</th>
<th>Date</th>
<th></th>

</tr>

</thead>

<tbody>

<?php if(!empty($orders)): ?>

<?php while(
$order =
$orders->fetch_assoc()
): ?>

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

<span class="badge bg-info">

<?= ucfirst(
$order['order_status']
) ?>

</span>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$order['created_at']
)
) ?>

</td>

<td>

<a
href="order-details.php?id=<?= $order['id'] ?>"
class="btn btn-sm btn-primary">

View

</a>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td colspan="5">

No orders found

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

<!-- QUICK ACTIONS -->

<div class="card-box">

<h5>

Administrative Actions

</h5>

<div class="d-flex flex-wrap gap-2">

<a
href="user-activity.php?id=<?= $userId ?>"
class="btn btn-dark">

Activity

</a>

<a
href="login-history.php?id=<?= $userId ?>"
class="btn btn-secondary">

Login History

</a>

<a
href="audit-log.php?user_id=<?= $userId ?>"
class="btn btn-warning">

Audit Log

</a>

<a
href="orders.php?user_id=<?= $userId ?>"
class="btn btn-primary">

Orders

</a>

<a
href="payments.php?user_id=<?= $userId ?>"
class="btn btn-success">

Payments

</a>

</div>

</div>

</div>

</body>
</html>