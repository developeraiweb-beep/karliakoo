<?php

declare(strict_types=1);

session_start();

require_once "config/db.php";

if(!isset($_SESSION['user_id']))
{
    header("Location: login.php");
    exit;
}

$userId =
(int)$_SESSION['user_id'];

$stmt =
$conn->prepare("
SELECT *
FROM addresses
WHERE user_id=?
ORDER BY is_default DESC, id DESC
");

$stmt->bind_param(
"i",
$userId
);

$stmt->execute();

$addresses =
$stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

My Addresses

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">

<style>

.address-card{
transition:.3s;
border:1px solid #e5e5e5;
}

.address-card:hover{
transform:translateY(-3px);
box-shadow:0 5px 20px rgba(0,0,0,.08);
}

.default-badge{
position:absolute;
top:15px;
right:15px;
}

</style>

</head>
<?php if(isset($_SESSION['success'])): ?>

<div class="alert alert-success">

<?= htmlspecialchars($_SESSION['success']) ?>

</div>

<?php unset($_SESSION['success']); ?>

<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>

<div class="alert alert-danger">

<?= htmlspecialchars($_SESSION['error']) ?>

</div>

<?php unset($_SESSION['error']); ?>

<?php endif; ?>
<body>

<div class="container py-5">

<div
class="d-flex justify-content-between align-items-center mb-4">

<h2>

<i class="bi bi-geo-alt"></i>

My Addresses

</h2>

<a
href="add-address.php"
class="btn btn-primary">

<i class="bi bi-plus-circle"></i>

Add Address

</a>

</div>

<div class="row">

<?php if($addresses->num_rows > 0): ?>

<?php while(
$address =
$addresses->fetch_assoc()
): ?>

<div class="col-lg-6 mb-4">

<div
class="card address-card h-100 position-relative">

<?php if(
(int)$address['is_default'] === 1
): ?>

<span
class="badge bg-success default-badge">

Default

</span>

<?php endif; ?>

<div class="card-body">

<h5>

<?= htmlspecialchars(
$address['recipient_name']
) ?>

</h5>

<p class="mb-1">

<i class="bi bi-telephone"></i>

<?= htmlspecialchars(
$address['phone']
) ?>

</p>

<p class="mb-1">

<?= htmlspecialchars(
$address['street']
) ?>

</p>

<p class="mb-1">

<?= htmlspecialchars(
$address['ward']
) ?>

</p>

<p class="mb-1">

<?= htmlspecialchars(
$address['district']
) ?>

</p>

<p>

<?= htmlspecialchars(
$address['region']
) ?>

</p>

</div>

<div class="card-footer bg-white">

<div class="btn-group w-100">

<a
href="edit-address.php?id=<?= (int)$address['id'] ?>"
class="btn btn-outline-primary">

Edit

</a>

<a
href="set-default-address.php?id=<?= (int)$address['id'] ?>"
class="btn btn-outline-success">

Default

</a>

<a
href="delete-address.php?id=<?= (int)$address['id'] ?>"
class="btn btn-outline-danger"
onclick="return confirm('Delete this address?')">

Delete

</a>

</div>

</div>

</div>

</div>

<?php endwhile; ?>

<?php else: ?>

<div class="col-12">

<div class="alert alert-info">

You have not added any address yet.

</div>

</div>

<?php endif; ?>

</div>

<div class="mt-4">

<a
href="checkout.php"
class="btn btn-success">

Proceed To Checkout

</a>

<a
href="my-account.php"
class="btn btn-outline-secondary">

Back To Account

</a>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>