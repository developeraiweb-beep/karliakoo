<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$paymentId = (int)($_GET['id'] ?? 0);

if ($paymentId <= 0) {
    die("Invalid payment.");
}

/*
|--------------------------------------------------------------------------
| PAYMENT DETAILS
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT

p.*,

o.id order_id,
o.order_number,
o.order_status,

u.id user_id,
u.full_name,
u.email,
u.phone

FROM payments p

LEFT JOIN orders o
ON o.id = p.order_id

LEFT JOIN users u
ON u.id = p.user_id

WHERE p.id=?

LIMIT 1
");

$stmt->bind_param("i", $paymentId);
$stmt->execute();

$payment =
$stmt
->get_result()
->fetch_assoc();

if (!$payment) {
    die("Payment not found.");
}

/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if (
        in_array(
            $action,
            ['paid','failed','refunded']
        )
    ) {

        $update = $conn->prepare("
            UPDATE payments
            SET payment_status=?
            WHERE id=?
        ");

        $update->bind_param(
            "si",
            $action,
            $paymentId
        );

        $update->execute();

        /*
        |--------------------------------------------------------------------------
        | AUDIT LOG
        |--------------------------------------------------------------------------
        */
        if (
            $conn->query(
            "SHOW TABLES LIKE 'payment_logs'"
            )->num_rows
        ) {

            $adminId =
            $_SESSION['user_id'];

            $log = $conn->prepare("
                INSERT INTO payment_logs(

                    payment_id,
                    admin_id,
                    action

                ) VALUES(?,?,?)
            ");

            $log->bind_param(
                "iis",
                $paymentId,
                $adminId,
                $action
            );

            $log->execute();
        }

        header(
            "Location: payment-details.php?id={$paymentId}&updated=1"
        );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| PAYMENT LOGS
|--------------------------------------------------------------------------
*/
$logs = [];

if (
$conn->query(
"SHOW TABLES LIKE 'payment_logs'"
)->num_rows
) {

    $stmt = $conn->prepare("
        SELECT

        l.*,
        u.full_name

        FROM payment_logs l

        LEFT JOIN users u
        ON u.id=l.admin_id

        WHERE payment_id=?

        ORDER BY l.id DESC
    ");

    $stmt->bind_param(
        "i",
        $paymentId
    );

    $stmt->execute();

    $logs =
    $stmt->get_result();
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Payment Details

</title>

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
border-radius:12px;
}

.metric{
font-size:24px;
font-weight:bold;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<a
href="payments.php"
class="btn btn-secondary mb-3">

← Back to Payments

</a>

<?php if(isset($_GET['updated'])): ?>

<div class="alert alert-success">

Payment updated successfully.

</div>

<?php endif; ?>

<div class="row">

<!-- LEFT COLUMN -->

<div class="col-lg-4">

<div class="card-box shadow-sm mb-4">

<h4>

Transaction

</h4>

<hr>

<p>
<strong>ID:</strong><br>
#<?= $payment['id'] ?>
</p>

<p>
<strong>Transaction ID:</strong><br>
<?= htmlspecialchars($payment['transaction_id']) ?>
</p>

<p>
<strong>Method:</strong><br>
<?= htmlspecialchars($payment['payment_method']) ?>
</p>

<p>
<strong>Status:</strong><br>

<?php

$color = match($payment['payment_status']){

'paid' => 'success',
'failed' => 'danger',
'refunded' => 'warning',
default => 'secondary'

};

?>

<span
class="badge bg-<?= $color ?>">

<?= ucfirst(
$payment['payment_status']
) ?>

</span>

</p>

<p>
<strong>Currency:</strong><br>
<?= htmlspecialchars($payment['currency']) ?>
</p>

<p>
<strong>Amount:</strong><br>

TZS

<?= number_format(
$payment['amount'],
2
) ?>

</p>

<p>
<strong>Paid At:</strong><br>

<?= $payment['paid_at']
?: 'Not Available' ?>

</p>

</div>

<div class="card-box shadow-sm">

<h4>

Payment Actions

</h4>

<hr>

<form method="POST">

<div class="d-grid gap-2">

<button
name="action"
value="paid"
class="btn btn-success">

Mark Paid

</button>

<button
name="action"
value="failed"
class="btn btn-danger">

Mark Failed

</button>

<button
name="action"
value="refunded"
class="btn btn-warning">

Refund Payment

</button>

</div>

</form>

</div>

</div>

<!-- RIGHT COLUMN -->

<div class="col-lg-8">

<div class="card-box shadow-sm mb-4">

<h4>

Customer Details

</h4>

<hr>

<div class="row">

<div class="col-md-4">

<strong>Name</strong>

<p>

<?= htmlspecialchars(
$payment['full_name']
) ?>

</p>

</div>

<div class="col-md-4">

<strong>Email</strong>

<p>

<?= htmlspecialchars(
$payment['email']
) ?>

</p>

</div>

<div class="col-md-4">

<strong>Phone</strong>

<p>

<?= htmlspecialchars(
$payment['phone']
) ?>

</p>

</div>

</div>

</div>

<div class="card-box shadow-sm mb-4">

<h4>

Order Details

</h4>

<hr>

<p>

<strong>Order Number:</strong>

<?= htmlspecialchars(
$payment['order_number']
) ?>

</p>

<p>

<strong>Order Status:</strong>

<?= htmlspecialchars(
$payment['order_status']
) ?>

</p>

<a
href="order-details.php?id=<?= $payment['order_id'] ?>"
class="btn btn-outline-primary">

View Order

</a>

</div>

<div class="card-box shadow-sm mb-4">

<h4>

Gateway Response

</h4>

<hr>

<pre style="
white-space:pre-wrap;
word-break:break-word;
">

<?= htmlspecialchars(
$payment['gateway_response']
?? 'No response data'
) ?>

</pre>

</div>

<div class="card-box shadow-sm">

<h4>

Audit Log

</h4>

<hr>

<?php if($logs && $logs->num_rows): ?>

<table class="table">

<thead>

<tr>

<th>Admin</th>
<th>Action</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php while($log = $logs->fetch_assoc()): ?>

<tr>

<td>

<?= htmlspecialchars(
$log['full_name']
) ?>

</td>

<td>

<?= ucfirst(
$log['action']
) ?>

</td>

<td>

<?= date(
'd M Y H:i',
strtotime(
$log['created_at']
)
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="alert alert-info">

No audit logs found.

</div>

<?php endif; ?>

</div>

</div>

</div>

</div>

</body>
</html>