<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

$product_id = (int)($_GET['product_id'] ?? 0);

if ($product_id <= 0) {
    die("Invalid product.");
}

/*
|--------------------------------------------------------------------------
| PRODUCT
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        p.*,
        s.seller_id,
        s.shop_name
    FROM products p
    LEFT JOIN shops s
        ON s.id = p.shop_id
    WHERE p.id=?
    LIMIT 1
");

$stmt->bind_param("i", $product_id);
$stmt->execute();

$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    die("Product not found.");
}

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| SUBMIT RFQ
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $quantity = (int)$_POST['quantity'];
    $target_price = (float)$_POST['target_price'];
    $delivery_location = trim($_POST['delivery_location']);
    $message = trim($_POST['message']);

    if (
        $quantity <
        (int)$product['minimum_order_qty']
    ) {

        $error =
            "Minimum quantity is " .
            number_format(
                $product['minimum_order_qty']
            );

    } else {

        $quote_number =
            'RFQ-' .
            strtoupper(
                substr(
                    md5(
                        uniqid()
                    ),
                    0,
                    10
                )
            );

        $insert = $conn->prepare("
            INSERT INTO rfq_requests
            (
                quote_number,
                buyer_id,
                supplier_id,
                product_id,
                quantity,
                target_price,
                delivery_location,
                message
            )
            VALUES
            (
                ?,?,?,?,?,?,?,?
            )
        ");

        $insert->bind_param(
            "siiiidss",
            $quote_number,
            $user['id'],
            $product['seller_id'],
            $product_id,
            $quantity,
            $target_price,
            $delivery_location,
            $message
        );

        if ($insert->execute()) {

            $success =
                "Quotation request submitted successfully.";

        } else {

            $error =
                "Unable to submit quotation request.";
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>
Request Quote
</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f6fa;
}

.quote-card{
    background:#fff;
    border-radius:12px;
}

</style>

</head>

<body>

<div class="container py-5">

<div class="row justify-content-center">

<div class="col-lg-8">

<div class="card quote-card shadow-sm">

<div class="card-body p-4">

<h3 class="mb-4">

Request Quotation

</h3>

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

<div class="mb-4">

<h5>
<?= htmlspecialchars($product['product_name']) ?>
</h5>

<p>
Supplier:
<strong>
<?= htmlspecialchars($product['shop_name']) ?>
</strong>
</p>

<p>
Wholesale Price:
<strong>
TZS <?= number_format($product['wholesale_price'],2) ?>
</strong>
</p>

<p>
MOQ:
<strong>
<?= number_format($product['minimum_order_qty']) ?>
 units
</strong>
</p>

</div>

<form method="POST">

<div class="mb-3">

<label class="form-label">
Required Quantity
</label>

<input
type="number"
name="quantity"
min="<?= $product['minimum_order_qty'] ?>"
class="form-control"
required>

</div>

<div class="mb-3">

<label class="form-label">
Target Price (Optional)
</label>

<input
type="number"
step="0.01"
name="target_price"
class="form-control">

</div>

<div class="mb-3">

<label class="form-label">
Delivery Location
</label>

<input
type="text"
name="delivery_location"
class="form-control"
required>

</div>

<div class="mb-3">

<label class="form-label">
Additional Requirements
</label>

<textarea
name="message"
rows="5"
class="form-control"
placeholder="Packaging requirements, delivery timeline, payment terms, etc."></textarea>

</div>

<button
type="submit"
class="btn btn-success">

Submit RFQ

</button>

<a
href="product.php?id=<?= $product_id ?>"
class="btn btn-secondary">

Back

</a>

</form>

</div>

</div>

</div>

</div>

</div>

</body>
</html>