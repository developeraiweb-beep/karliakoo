<?php

declare(strict_types=1);

session_start();

require_once "config/db.php";

if (!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

$addressId =
(int)($_GET['id'] ?? 0);

if ($addressId <= 0)
{
    die("Invalid address.");
}

/*
|--------------------------------------------------------------------------
| LOAD ADDRESS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM addresses
    WHERE id=?
    AND user_id=?
    LIMIT 1
");

$stmt->bind_param(
    "ii",
    $addressId,
    $userId
);

$stmt->execute();

$address =
$stmt->get_result()->fetch_assoc();

if (!$address)
{
    die("Address not found.");
}

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| UPDATE ADDRESS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $recipient_name =
    trim($_POST['recipient_name'] ?? '');

    $phone =
    trim($_POST['phone'] ?? '');

    $region =
    trim($_POST['region'] ?? '');

    $district =
    trim($_POST['district'] ?? '');

    $ward =
    trim($_POST['ward'] ?? '');

    $street =
    trim($_POST['street'] ?? '');

    $is_default =
    isset($_POST['is_default']) ? 1 : 0;

    if (
        empty($recipient_name) ||
        empty($phone) ||
        empty($region) ||
        empty($district) ||
        empty($ward) ||
        empty($street)
    )
    {
        $error =
        "All fields are required.";
    }
    else
    {
        $conn->begin_transaction();

        try
        {
            if ($is_default === 1)
            {
                $reset =
                $conn->prepare("
                    UPDATE addresses
                    SET is_default=0
                    WHERE user_id=?
                ");

                $reset->bind_param(
                    "i",
                    $userId
                );

                $reset->execute();
            }

            $update =
            $conn->prepare("
                UPDATE addresses
                SET
                    recipient_name=?,
                    phone=?,
                    region=?,
                    district=?,
                    ward=?,
                    street=?,
                    is_default=?
                WHERE id=?
                AND user_id=?
            ");

            $update->bind_param(
                "ssssssiii",
                $recipient_name,
                $phone,
                $region,
                $district,
                $ward,
                $street,
                $is_default,
                $addressId,
                $userId
            );

            $update->execute();

            $conn->commit();

            $success =
            "Address updated successfully.";

            $refresh =
            $conn->prepare("
                SELECT *
                FROM addresses
                WHERE id=?
                LIMIT 1
            ");

            $refresh->bind_param(
                "i",
                $addressId
            );

            $refresh->execute();

            $address =
            $refresh
            ->get_result()
            ->fetch_assoc();
        }
        catch(Exception $e)
        {
            $conn->rollback();

            $error =
            "Failed to update address.";
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

<title>Edit Address</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">

</head>

<body>

<div class="container py-5">

<div class="row justify-content-center">

<div class="col-lg-8">

<div class="card shadow-sm">

<div class="card-header">

<h4 class="mb-0">

<i class="bi bi-pencil-square"></i>

Edit Address

</h4>

</div>

<div class="card-body">

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

<form method="POST">

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">

Recipient Name

</label>

<input
type="text"
name="recipient_name"
class="form-control"
required
value="<?= htmlspecialchars($address['recipient_name']) ?>">

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Phone Number

</label>

<input
type="text"
name="phone"
class="form-control"
required
value="<?= htmlspecialchars($address['phone']) ?>">

</div>

</div>

<div class="row">

<div class="col-md-4 mb-3">

<label class="form-label">

Region

</label>

<input
type="text"
name="region"
class="form-control"
required
value="<?= htmlspecialchars($address['region']) ?>">

</div>

<div class="col-md-4 mb-3">

<label class="form-label">

District

</label>

<input
type="text"
name="district"
class="form-control"
required
value="<?= htmlspecialchars($address['district']) ?>">

</div>

<div class="col-md-4 mb-3">

<label class="form-label">

Ward

</label>

<input
type="text"
name="ward"
class="form-control"
required
value="<?= htmlspecialchars($address['ward']) ?>">

</div>

</div>

<div class="mb-3">

<label class="form-label">

Street / Full Address

</label>

<textarea
name="street"
rows="4"
class="form-control"
required><?= htmlspecialchars($address['street']) ?></textarea>

</div>

<div class="form-check mb-4">

<input
type="checkbox"
name="is_default"
id="is_default"
class="form-check-input"

<?= (int)$address['is_default'] === 1 ? 'checked' : '' ?>>

<label
for="is_default"
class="form-check-label">

Make this my default address

</label>

</div>

<div class="d-flex gap-2">

<button
type="submit"
class="btn btn-primary">

<i class="bi bi-save"></i>

Update Address

</button>

<a
href="addresses.php"
class="btn btn-secondary">

Cancel

</a>

</div>

</form>

</div>

</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
