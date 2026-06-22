<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";

if (!isset($_SESSION['user_id']))
{
    header("Location: ../login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

$rfqId = (int)($_GET['id'] ?? 0);

if ($rfqId <= 0)
{
    die("Invalid RFQ.");
}

/*
|--------------------------------------------------------------------------
| LOAD RFQ
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
SELECT *
FROM rfq_requests
WHERE id = ?
AND buyer_id = ?
LIMIT 1
");

$stmt->bind_param("ii", $rfqId, $userId);
$stmt->execute();

$rfq = $stmt->get_result()->fetch_assoc();

if (!$rfq)
{
    die("RFQ not found.");
}

/*
|--------------------------------------------------------------------------
| PREVENT DOUBLE CONVERSION
|--------------------------------------------------------------------------
*/

if ($rfq['status'] !== 'quoted')
{
    die("RFQ must be quoted before accepting.");
}

/*
|--------------------------------------------------------------------------
| GENERATE ORDER NUMBER
|--------------------------------------------------------------------------
*/

$orderNumber =
"ORD-" . date("YmdHis") . rand(100,999);

/*
|--------------------------------------------------------------------------
| CREATE ORDER
|--------------------------------------------------------------------------
*/

$orderStmt = $conn->prepare("
INSERT INTO b2b_orders
(
    order_number,
    rfq_id,
    buyer_id,
    supplier_id,
    shop_id,
    total_amount,
    payment_status,
    order_status,
    delivery_address,
    created_at
)
VALUES
(
    ?,?,?,?,?,?,
    'pending',
    'pending',
    ?,
    NOW()
)
");

/*
|--------------------------------------------------------------------------
| CALCULATE AMOUNT
|--------------------------------------------------------------------------
*/

$totalAmount =
(float)(
$rfq['target_price'] > 0
? $rfq['target_price'] * $rfq['quantity']
: 0
);

$orderStmt->bind_param(
"siiiids",
$orderNumber,
$rfqId,
$rfq['buyer_id'],
$rfq['supplier_id'],
$rfq['supplier_id'],
$totalAmount,
$rfq['delivery_location']
);

if ($orderStmt->execute())
{
    $orderId = $conn->insert_id;

    /*
    |--------------------------------------------------------------------------
    | UPDATE RFQ STATUS
    |--------------------------------------------------------------------------
    */

    $conn->query("
    UPDATE rfq_requests
    SET status='accepted'
    WHERE id=$rfqId
    ");

    header("Location: payment.php?order_id=" . $orderId);
    exit;
}

die("Failed to create order.");
?>
<?php

session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id']))
{
    header("Location: ../login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId <= 0)
{
    die("Invalid order.");
}

/*
|--------------------------------------------------------------------------
| LOAD ORDER
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
SELECT *
FROM b2b_orders
WHERE id = ?
AND buyer_id = ?
LIMIT 1
");

$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();

if (!$order)
{
    die("Order not found.");
}

$message = "";

/*
|--------------------------------------------------------------------------
| PAYMENT SIMULATION / ENTRY
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $method = $_POST['method'] ?? 'mpesa';
    $ref = "TXN-" . rand(100000,999999);

    $pay = $conn->prepare("
    INSERT INTO payments
    (
        order_id,
        user_id,
        amount,
        method,
        status,
        transaction_ref,
        created_at
    )
    VALUES
    (?,?,?,?, 'paid', ?, NOW())
    ");

    $pay->bind_param(
        "iidss",
        $orderId,
        $userId,
        $order['total_amount'],
        $method,
        $ref
    );

    if ($pay->execute())
    {
        $conn->query("
        UPDATE b2b_orders
        SET payment_status='paid',
            order_status='processing'
        WHERE id=$orderId
        ");

        header("Location: order-success.php?id=" . $orderId);
        exit;
    }

    $message = "Payment failed.";
}

?>

<!DOCTYPE html>

<html>

<head>
<title>Payment</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<div class="container py-5">

<div class="col-lg-6 mx-auto">

<div class="card">

<div class="card-header">

Payment

</div>

<div class="card-body">

<h5>Order #<?= htmlspecialchars($order['order_number']) ?></h5>

<p>Total: <strong>TZS <?= number_format((float)$order['total_amount']) ?></strong></p>

<?php if($message): ?>

<div class="alert alert-danger"><?= $message ?></div>
<?php endif; ?>

<form method="POST">

<label>Payment Method</label>

<select name="method" class="form-select mb-3">

<option value="mpesa">M-Pesa</option>
<option value="card">Card</option>
<option value="bank">Bank Transfer</option>

</select>

<button class="btn btn-success w-100">

Pay Now

</button>

</form>

</div>

</div>

</div>

</div>

</body>

</html>

<?php

require_once "../config/db.php";

$orderId = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
SELECT *
FROM b2b_orders
WHERE id=?
LIMIT 1
");

$stmt->bind_param("i", $orderId);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();

if (!$order)
{
    die("Order not found.");
}

?>

<!DOCTYPE html>

<html>

<head>

<title>Order Success</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container py-5 text-center">

<h1 class="text-success">Order Placed Successfully</h1>

<p>Order #: <?= htmlspecialchars($order['order_number']) ?></p>

<p>Total Paid: TZS <?= number_format((float)$order['total_amount']) ?></p>

<a href="orders.php" class="btn btn-primary">

View My Orders

</a>

</div>

</body>

</html>
