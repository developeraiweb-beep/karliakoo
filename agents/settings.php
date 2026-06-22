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
| CREATE DEFAULT SETTINGS
|--------------------------------------------------------------------------
*/
$check = $conn->prepare("
    SELECT *
    FROM agent_settings
    WHERE agent_id = ?
    LIMIT 1
");

$check->bind_param("i", $agent_id);
$check->execute();

$settings = $check->get_result()->fetch_assoc();

if (!$settings) {

    $insert = $conn->prepare("
        INSERT INTO agent_settings (agent_id)
        VALUES (?)
    ");

    $insert->bind_param("i", $agent_id);
    $insert->execute();

    header("Location: settings.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| SAVE SETTINGS
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email_notifications =
        isset($_POST['email_notifications']) ? 1 : 0;

    $sms_notifications =
        isset($_POST['sms_notifications']) ? 1 : 0;

    $order_notifications =
        isset($_POST['order_notifications']) ? 1 : 0;

    $commission_notifications =
        isset($_POST['commission_notifications']) ? 1 : 0;

    $payout_method =
        trim($_POST['payout_method']);

    $payout_account_name =
        trim($_POST['payout_account_name']);

    $payout_account_number =
        trim($_POST['payout_account_number']);

    $two_factor_enabled =
        isset($_POST['two_factor_enabled']) ? 1 : 0;

    $update = $conn->prepare("
        UPDATE agent_settings

        SET

        email_notifications=?,
        sms_notifications=?,
        order_notifications=?,
        commission_notifications=?,

        payout_method=?,
        payout_account_name=?,
        payout_account_number=?,

        two_factor_enabled=?

        WHERE agent_id=?
    ");

    $update->bind_param(
        "iiiisssii",

        $email_notifications,
        $sms_notifications,
        $order_notifications,
        $commission_notifications,

        $payout_method,
        $payout_account_name,
        $payout_account_number,

        $two_factor_enabled,

        $agent_id
    );

    if ($update->execute()) {

        $success =
            "Settings updated successfully.";

        $check->execute();
        $settings =
            $check->get_result()->fetch_assoc();

    } else {

        $error =
            "Failed to save settings.";
    }
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1">

<title>Agent Settings</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">

<style>

body{
    background:#f5f5f5;
}

.card-box{
    background:white;
    border-radius:12px;
    padding:20px;
}

</style>

</head>

<body>

<div class="container py-4">

<h2 class="mb-4">
Settings
</h2>

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

<form method="POST">

<div class="card-box shadow-sm mb-4">

<h5 class="mb-3">
Notification Settings
</h5>

<div class="form-check mb-2">

<input
class="form-check-input"
type="checkbox"
name="email_notifications"
<?= $settings['email_notifications'] ? 'checked' : '' ?>>

<label class="form-check-label">
Email Notifications
</label>

</div>

<div class="form-check mb-2">

<input
class="form-check-input"
type="checkbox"
name="sms_notifications"
<?= $settings['sms_notifications'] ? 'checked' : '' ?>>

<label class="form-check-label">
SMS Notifications
</label>

</div>

<div class="form-check mb-2">

<input
class="form-check-input"
type="checkbox"
name="order_notifications"
<?= $settings['order_notifications'] ? 'checked' : '' ?>>

<label class="form-check-label">
Order Notifications
</label>

</div>

<div class="form-check">

<input
class="form-check-input"
type="checkbox"
name="commission_notifications"
<?= $settings['commission_notifications'] ? 'checked' : '' ?>>

<label class="form-check-label">
Commission Notifications
</label>

</div>

</div>

<div class="card-box shadow-sm mb-4">

<h5 class="mb-3">
Payout Settings
</h5>

<div class="mb-3">

<label class="form-label">
Default Method
</label>

<select
name="payout_method"
class="form-select">

<option value="mpesa"
<?= $settings['payout_method']=='mpesa' ? 'selected' : '' ?>>
M-Pesa
</option>

<option value="tigopesa"
<?= $settings['payout_method']=='tigopesa' ? 'selected' : '' ?>>
Tigo Pesa
</option>

<option value="airtelmoney"
<?= $settings['payout_method']=='airtelmoney' ? 'selected' : '' ?>>
Airtel Money
</option>

<option value="halopesa"
<?= $settings['payout_method']=='halopesa' ? 'selected' : '' ?>>
HaloPesa
</option>

<option value="bank"
<?= $settings['payout_method']=='bank' ? 'selected' : '' ?>>
Bank
</option>

</select>

</div>

<div class="mb-3">

<label class="form-label">
Account Name
</label>

<input
type="text"
name="payout_account_name"
class="form-control"
value="<?= htmlspecialchars($settings['payout_account_name']) ?>">

</div>

<div class="mb-3">

<label class="form-label">
Account Number
</label>

<input
type="text"
name="payout_account_number"
class="form-control"
value="<?= htmlspecialchars($settings['payout_account_number']) ?>">

</div>

</div>

<div class="card-box shadow-sm mb-4">

<h5 class="mb-3">
Security
</h5>

<div class="form-check">

<input
class="form-check-input"
type="checkbox"
name="two_factor_enabled"
<?= $settings['two_factor_enabled'] ? 'checked' : '' ?>>

<label class="form-check-label">
Enable Two-Factor Authentication
</label>

</div>

</div>

<button class="btn btn-primary">
Save Settings
</button>

</form>

</div>

</body>
</html>