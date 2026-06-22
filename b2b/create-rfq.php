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

$productId =
(int)($_GET['product_id'] ?? 0);

$product = null;

/*
|--------------------------------------------------------------------------
| LOAD PRODUCT IF PROVIDED
|--------------------------------------------------------------------------
*/

if ($productId > 0)
{
    $stmt =
    $conn->prepare("
        SELECT
            p.*,
            s.shop_name
        FROM products p
        LEFT JOIN shops s
        ON s.id = p.shop_id
        WHERE
            p.id=?
            AND p.approved=1
            AND p.status='active'
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $productId
    );

    $stmt->execute();

    $product =
    $stmt
    ->get_result()
    ->fetch_assoc();
}

/*
|--------------------------------------------------------------------------
| SUPPLIERS
|--------------------------------------------------------------------------
*/

$suppliers =
mysqli_query(
$conn,
"
SELECT
id,
shop_name
FROM shops
WHERE
status='approved'
AND suspended=0
ORDER BY shop_name ASC
"
);

/*
|--------------------------------------------------------------------------
| CREATE RFQ
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $supplierId =
    (int)($_POST['supplier_id'] ?? 0);

    $productId =
    (int)($_POST['product_id'] ?? 0);

    $quantity =
    (int)($_POST['quantity'] ?? 0);

    $targetPrice =
    (float)($_POST['target_price'] ?? 0);

    $deliveryLocation =
    trim(
        $_POST['delivery_location']
        ?? ''
    );

    $message =
    trim(
        $_POST['message']
        ?? ''
    );

    if ($supplierId <= 0)
    {
        $error =
        "Please select supplier.";
    }
    elseif ($productId <= 0)
    {
        $error =
        "Please select product.";
    }
    elseif ($quantity <= 0)
    {
        $error =
        "Invalid quantity.";
    }
    else
    {
        $quoteNumber =
        'RFQ' .
        date('YmdHis') .
        rand(100,999);

        $insert =
        $conn->prepare("
            INSERT INTO rfq_requests
            (
                quote_number,
                buyer_id,
                supplier_id,
                product_id,
                quantity,
                target_price,
                delivery_location,
                message,
                status,
                created_at
            )
            VALUES
            (
                ?,?,?,?,?,?,?,?,
                'pending',
                NOW()
            )
        ");

        $insert->bind_param(
            "siiiidss",
            $quoteNumber,
            $userId,
            $supplierId,
            $productId,
            $quantity,
            $targetPrice,
            $deliveryLocation,
            $message
        );

        if ($insert->execute())
        {
            $success =
            "RFQ submitted successfully.";

            $_POST = [];
        }
        else
        {
            $error =
            "Failed to submit RFQ.";
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

Create RFQ

</title>

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

<h3 class="mb-0">

Request For Quotation

</h3>

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

<?php if($product): ?>

<div class="card border-success mb-4">

<div class="card-header bg-success text-white">

Selected Product

</div>

<div class="card-body">

<div class="row align-items-center">

<div class="col-md-3">

<?php

$productImage =
$product['featured_image']
?: $product['image'];

if(empty($productImage))
{
    $productImage =
    "../assets/images/no-image.jpg";
}

?>

<img
src="<?= htmlspecialchars('../' . ltrim($productImage,'./')) ?>"
class="img-fluid rounded">

</div>

<div class="col-md-9">

<h5>

<?= htmlspecialchars(
$product['name']
) ?>

</h5>

<p class="mb-2">

Supplier:

<strong>

<?= htmlspecialchars(
$product['shop_name']
) ?>

</strong>

</p>

<p class="mb-2">

Wholesale Price:

<strong>

TZS

<?= number_format(
(float)$product['wholesale_price']
) ?>

</strong>

</p>

<p class="mb-0">

Minimum Order Quantity:

<strong>

<?= (int)(
$product['minimum_order_qty']
?: 1
) ?>

</strong>

</p>

</div>

</div>

</div>

</div>

<?php endif; ?>

<form method="POST">

<input
type="hidden"
name="product_id"
value="<?= (int)(
$product['id']
?? 0
) ?>">

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">

Supplier

<span class="text-danger">*</span>

</label>

<select
name="supplier_id"
class="form-select"
required>

<option value="">

Select Supplier

</option>

<?php while(
$supplier =
mysqli_fetch_assoc(
$suppliers
)
): ?>

<option
value="<?= (int)$supplier['id'] ?>">

<?= htmlspecialchars(
$supplier['shop_name']
) ?>

</option>

<?php endwhile; ?>

</select>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Product ID

</label>

<input
type="number"
name="product_id"
class="form-control"
required
value="<?= (int)(
$product['id']
?? ($_POST['product_id'] ?? 0)
) ?>">

<div class="form-text">

Product you want quoted.

</div>

</div>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">

Required Quantity

<span class="text-danger">*</span>

</label>

<input
type="number"
name="quantity"
class="form-control"
required
min="1"
value="<?= htmlspecialchars(
$_POST['quantity']
?? ''
) ?>">

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Target Unit Price (TZS)

</label>

<input
type="number"
step="0.01"
name="target_price"
class="form-control"
value="<?= htmlspecialchars(
$_POST['target_price']
?? ''
) ?>">

<div class="form-text">

Optional negotiation target.

</div>

</div>

</div>

<div class="mb-3">

<label class="form-label">

Delivery Location

<span class="text-danger">*</span>

</label>

<input
type="text"
name="delivery_location"
class="form-control"
required
placeholder="Dar es Salaam, Tanzania"
value="<?= htmlspecialchars(
$_POST['delivery_location']
?? ''
) ?>">

</div>

<div class="mb-4">

<label class="form-label">

Requirements / Message

</label>

<textarea
name="message"
rows="6"
class="form-control"
placeholder="Describe specifications, packaging, delivery requirements, preferred brands, timelines, certifications, etc."><?= htmlspecialchars(
$_POST['message']
?? ''
) ?></textarea>

</div>

<div class="alert alert-info">

<i class="bi bi-info-circle"></i>

Suppliers will review your RFQ and may respond with pricing,
delivery timelines,
minimum order requirements,
and commercial terms.

</div>

<div class="d-flex gap-2">

<button
type="submit"
class="btn btn-primary">

<i class="bi bi-send"></i>

Submit RFQ

</button>

<a
href="index.php"
class="btn btn-outline-secondary">

Cancel

</a>

</div>

</form>

</div>

</div>

</div>

</div>

</div>

<hr class="my-5">

<?php

/*
|--------------------------------------------------------------------------
| RFQ SIDEBAR DATA
|--------------------------------------------------------------------------
*/

$rfqStats =
mysqli_query(
$conn,
"
SELECT
COUNT(*) total_rfqs
FROM rfq_requests
"
);

$rfqTotal = 0;

if($row = mysqli_fetch_assoc($rfqStats))
{
    $rfqTotal =
    (int)$row['total_rfqs'];
}

$featuredSuppliers =
mysqli_query(
$conn,
"
SELECT
id,
shop_name,
verified,
followers
FROM shops
WHERE
status='approved'
AND suspended=0
ORDER BY
verified DESC,
followers DESC
LIMIT 5
"
);

?>

<div class="row">

<div class="col-lg-8">

<div class="card border-0 shadow-sm">

<div class="card-header">

RFQ Process

</div>

<div class="card-body">

<div class="row text-center">

<div class="col-md-3">

<div class="mb-3">

<i
class="bi bi-file-earmark-text fs-1 text-primary"> </i>

</div>

<h6>

Submit RFQ

</h6>

<p class="small text-muted">

Describe product requirements.

</p>

</div>

<div class="col-md-3">

<div class="mb-3">

<i
class="bi bi-building fs-1 text-success"> </i>

</div>

<h6>

Suppliers Review

</h6>

<p class="small text-muted">

Qualified suppliers receive your RFQ.

</p>

</div>

<div class="col-md-3">

<div class="mb-3">

<i
class="bi bi-cash-stack fs-1 text-warning"> </i>

</div>

<h6>

Receive Quotes

</h6>

<p class="small text-muted">

Compare offers and negotiate.

</p>

</div>

<div class="col-md-3">

<div class="mb-3">

<i
class="bi bi-check-circle fs-1 text-danger"> </i>

</div>

<h6>

Place Order

</h6>

<p class="small text-muted">

Convert RFQ into a B2B order.

</p>

</div>

</div>

</div>

</div>

<div class="card border-0 shadow-sm mt-4">

<div class="card-header">

RFQ Best Practices

</div>

<div class="card-body">

<ul class="mb-0">

<li>

Provide accurate quantities.

</li>

<li>

Specify delivery location clearly.

</li>

<li>

Mention packaging requirements.

</li>

<li>

Include preferred brands if necessary.

</li>

<li>

Request certifications where applicable.

</li>

<li>

Set realistic target pricing.

</li>

<li>

Clearly indicate delivery deadlines.

</li>

</ul>

</div>

</div>

</div>

<div class="col-lg-4">

<div class="card border-0 shadow-sm mb-4">

<div class="card-body text-center">

<h3>

<?= number_format(
$rfqTotal
) ?>

</h3>

<p class="mb-0">

RFQs Submitted

</p>

</div>

</div>

<div class="card border-0 shadow-sm mb-4">

<div class="card-header">

Top Suppliers

</div>

<div class="list-group list-group-flush">

<?php while(
$supplier =
mysqli_fetch_assoc(
$featuredSuppliers
)
): ?>

<a
href="../shop-details.php?id=<?= (int)$supplier['id'] ?>"
class="list-group-item list-group-item-action">

<div class="d-flex justify-content-between">

<div>

<?= htmlspecialchars(
$supplier['shop_name']
) ?>

</div>

<div>

<?php if(
(int)$supplier['verified'] === 1
): ?>

<span
class="badge bg-success">

Verified

</span>

<?php endif; ?>

</div>

</div>

<small class="text-muted">

<?= number_format(
(int)$supplier['followers']
) ?>

Followers

</small>

</a>

<?php endwhile; ?>

</div>

</div>

<div class="card border-0 shadow-sm">

<div class="card-body">

<h5>

Need Help?

</h5>

<p>

Our B2B marketplace helps buyers connect
with trusted suppliers and manufacturers.

</p>

<div class="d-grid gap-2">

<a
href="suppliers.php"
class="btn btn-outline-primary">

Browse Suppliers

</a>

<a
href="products.php"
class="btn btn-outline-success">

Wholesale Products

</a>

<a
href="rfqs.php"
class="btn btn-outline-dark">

View RFQs

</a>

</div>

</div>

</div>

</div>

</div>

<footer class="mt-5 pt-4 border-top text-center text-muted">

<p>

© <?= date('Y') ?>

Karliakoo B2B Marketplace

</p>

<p>

RFQs • Procurement • Wholesale Trade • Supplier Network

</p>

</footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
