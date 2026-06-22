<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";

if (!isset($_SESSION['user_id']))
{
    header("Location: ../login.php");
    exit;
}

$userId =
(int)$_SESSION['user_id'];

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| LOAD USER
|--------------------------------------------------------------------------
*/

$stmt =
$conn->prepare("
    SELECT *
    FROM users
    WHERE id=?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$user =
$stmt
->get_result()
->fetch_assoc();

if (!$user)
{
    session_destroy();

    header("Location: ../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE PROFILE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $fullName =
    trim($_POST['full_name'] ?? '');

    $phone =
    trim($_POST['phone'] ?? '');

    $email =
    trim($_POST['email'] ?? '');

    if (
        empty($fullName) ||
        empty($email)
    )
    {
        $error =
        "Name and email are required.";
    }
    elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    )
    {
        $error =
        "Invalid email address.";
    }
    else
    {
        $checkEmail =
        $conn->prepare("
            SELECT id
            FROM users
            WHERE email=?
            AND id!=?
            LIMIT 1
        ");

        $checkEmail->bind_param(
            "si",
            $email,
            $userId
        );

        $checkEmail->execute();

        if (
            $checkEmail
            ->get_result()
            ->num_rows > 0
        )
        {
            $error =
            "Email already exists.";
        }
        else
        {
            $update =
            $conn->prepare("
                UPDATE users
                SET
                    full_name=?,
                    email=?,
                    phone=?
                WHERE id=?
            ");

            $update->bind_param(
                "sssi",
                $fullName,
                $email,
                $phone,
                $userId
            );

            if ($update->execute())
            {
                $success =
                "Profile updated successfully.";

                $reload =
                $conn->prepare("
                    SELECT *
                    FROM users
                    WHERE id=?
                    LIMIT 1
                ");

                $reload->bind_param(
                    "i",
                    $userId
                );

                $reload->execute();

                $user =
                $reload
                ->get_result()
                ->fetch_assoc();
            }
            else
            {
                $error =
                "Failed to update profile.";
            }
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

My Profile

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">

<style>

.profile-card{
border:none;
box-shadow:0 3px 15px rgba(0,0,0,.08);
}

.avatar{
width:100px;
height:100px;
border-radius:50%;
display:flex;
align-items:center;
justify-content:center;
font-size:40px;
font-weight:bold;
background:#0d6efd;
color:#fff;
margin:auto;
}

</style>

</head>

<body>

<div class="container py-5">

<div class="row justify-content-center">

<div class="col-lg-8">

<div class="card profile-card">

<div class="card-body text-center">

<div class="avatar">

<?= strtoupper(
substr(
$user['full_name']
?? $user['name']
?? 'U',
0,
1
)
) ?>

</div>

<h3 class="mt-3">

<?= htmlspecialchars(
$user['full_name']
?? $user['name']
?? 'User'
) ?>

</h3>

<p class="text-muted">

Member Profile

</p>

</div>

</div>

<br>
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

<div class="card profile-card mb-4">

<div class="card-header">

<h5 class="mb-0">

Edit Profile

</h5>

</div>

<div class="card-body">

<form method="POST">

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">

Full Name

</label>

<input
type="text"
name="full_name"
class="form-control"
required
value="<?= htmlspecialchars(
$user['full_name']
?? $user['name']
?? ''
) ?>">

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Email Address

</label>

<input
type="email"
name="email"
class="form-control"
required
value="<?= htmlspecialchars(
$user['email']
?? ''
) ?>">

</div>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">

Phone Number

</label>

<input
type="text"
name="phone"
class="form-control"
value="<?= htmlspecialchars(
$user['phone']
?? ''
) ?>">

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

User ID

</label>

<input
type="text"
class="form-control"
readonly
value="<?= (int)$user['id'] ?>">

</div>

</div>

<button
type="submit"
class="btn btn-primary">

<i class="bi bi-save"></i>

Save Changes

</button>

</form>

</div>

</div>

<div class="row">

<div class="col-md-6 mb-4">

<div class="card profile-card h-100">

<div class="card-header">

Account Information

</div>

<div class="card-body">

<table class="table table-borderless">

<tr>

<th>

Name

</th>

<td>

<?= htmlspecialchars(
$user['full_name']
?? $user['name']
?? '-'
) ?>

</td>

</tr>

<tr>

<th>

Email

</th>

<td>

<?= htmlspecialchars(
$user['email']
?? '-'
) ?>

</td>

</tr>

<tr>

<th>

Phone

</th>

<td>

<?= htmlspecialchars(
$user['phone']
?? '-'
) ?>

</td>

</tr>

<tr>

<th>

User ID

</th>

<td>

#<?= (int)$user['id'] ?>

</td>

</tr>

</table>

</div>

</div>

</div>

<div class="col-md-6 mb-4">

<div class="card profile-card h-100">

<div class="card-header">

Quick Links

</div>

<div class="list-group list-group-flush">

<a
href="../my-orders.php"
class="list-group-item list-group-item-action">

<i class="bi bi-bag-check"></i>

My Orders

</a>

<a
href="../wishlist.php"
class="list-group-item list-group-item-action">

<i class="bi bi-heart"></i>

Wishlist

</a>

<a
href="../cart.php"
class="list-group-item list-group-item-action">

<i class="bi bi-cart"></i>

Shopping Cart

</a>

<a
href="../addresses.php"
class="list-group-item list-group-item-action">

<i class="bi bi-geo-alt"></i>

Addresses

</a>

<a
href="dashboard.php"
class="list-group-item list-group-item-action">

<i class="bi bi-speedometer2"></i>

Dashboard

</a>

</div>

</div>

</div>

</div>

<div class="card profile-card">

<div class="card-header">

Security Settings

</div>

<div class="card-body">

<p class="mb-3">

Manage your account security and password.

</p>

<a
href="change-password.php"
class="btn btn-warning">

<i class="bi bi-shield-lock"></i>

Change Password

</a>

</div>

</div>

<br>
<?php

/*
|--------------------------------------------------------------------------
| USER STATISTICS
|--------------------------------------------------------------------------
*/

$orderCount = 0;
$wishlistCount = 0;
$cartCount = 0;
$addressCount = 0;

/* Orders */

$q =
$conn->prepare("
SELECT COUNT(*) total
FROM orders
WHERE user_id=?
");

$q->bind_param(
"i",
$userId
);

$q->execute();

$orderCount =
(int)$q
->get_result()
->fetch_assoc()['total'];

/* Wishlist */

$q =
$conn->prepare("
SELECT COUNT(*) total
FROM wishlists
WHERE user_id=?
");

$q->bind_param(
"i",
$userId
);

$q->execute();

$wishlistCount =
(int)$q
->get_result()
->fetch_assoc()['total'];

/* Cart */

$q =
$conn->prepare("
SELECT COUNT(*) total
FROM cart
WHERE user_id=?
");

$q->bind_param(
"i",
$userId
);

$q->execute();

$cartCount =
(int)$q
->get_result()
->fetch_assoc()['total'];

/* Addresses */

$q =
$conn->prepare("
SELECT COUNT(*) total
FROM addresses
WHERE user_id=?
");

$q->bind_param(
"i",
$userId
);

$q->execute();

$addressCount =
(int)$q
->get_result()
->fetch_assoc()['total'];

?>

<div class="row mb-4">

<div class="col-md-3 mb-3">

<div class="card text-center shadow-sm">

<div class="card-body">

<i
class="bi bi-bag-check-fill text-primary fs-1"> </i>

<h3>

<?= number_format($orderCount) ?>

</h3>

<p class="mb-0">

Orders

</p>

</div>

</div>

</div>

<div class="col-md-3 mb-3">

<div class="card text-center shadow-sm">

<div class="card-body">

<i
class="bi bi-heart-fill text-danger fs-1"> </i>

<h3>

<?= number_format($wishlistCount) ?>

</h3>

<p class="mb-0">

Wishlist

</p>

</div>

</div>

</div>

<div class="col-md-3 mb-3">

<div class="card text-center shadow-sm">

<div class="card-body">

<i
class="bi bi-cart-fill text-success fs-1"> </i>

<h3>

<?= number_format($cartCount) ?>

</h3>

<p class="mb-0">

Cart Items

</p>

</div>

</div>

</div>

<div class="col-md-3 mb-3">

<div class="card text-center shadow-sm">

<div class="card-body">

<i
class="bi bi-geo-alt-fill text-warning fs-1"> </i>

<h3>

<?= number_format($addressCount) ?>

</h3>

<p class="mb-0">

Addresses

</p>

</div>

</div>

</div>

</div>

<?php

/*
|--------------------------------------------------------------------------
| RECENT ORDERS
|--------------------------------------------------------------------------
*/

$recentOrders =
$conn->prepare("
SELECT
id,
order_number,
total_amount,
created_at
FROM orders
WHERE user_id=?
ORDER BY id DESC
LIMIT 5
");

$recentOrders->bind_param(
"i",
$userId
);

$recentOrders->execute();

$recentOrdersResult =
$recentOrders->get_result();

?>

<div class="card profile-card mb-4">

<div class="card-header">

Recent Orders

</div>

<div class="card-body">

<?php if(
$recentOrdersResult->num_rows > 0
): ?>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Order</th>

<th>Date</th>

<th>Total</th>

<th></th>

</tr>

</thead>

<tbody>

<?php while(
$order =
$recentOrdersResult->fetch_assoc()
): ?>

<tr>

<td>

<?= htmlspecialchars(
$order['order_number']
?? ('ORD'.$order['id'])
) ?>

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

TZS

<?= number_format(
(float)$order['total_amount']
) ?>

</td>

<td>

<a
href="../order-details.php?id=<?= (int)$order['id'] ?>"
class="btn btn-sm btn-outline-primary">

View

</a>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

<?php else: ?>

<div class="text-center py-4">

<p class="text-muted">

No orders found.

</p>

<a
href="../products.php"
class="btn btn-primary">

Start Shopping

</a>

</div>

<?php endif; ?>

</div>

</div>

<div class="text-center text-muted mt-4">

<p>

Member since

<?= !empty($user['created_at'])
? date(
'F Y',
strtotime(
$user['created_at']
)
)
: 'Recently'
?>

</p>

<p>

Karliakoo Marketplace

</p>

</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
