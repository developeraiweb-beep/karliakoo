<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$rfq_id = (int)($_GET['id'] ?? 0);

if ($rfq_id <= 0) {
    die("Invalid RFQ.");
}

/*
|--------------------------------------------------------------------------
| UPDATE ADMIN ACTIONS
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $status = trim($_POST['status']);
    $admin_notes = trim($_POST['admin_notes']);

    $stmt = $conn->prepare("
        UPDATE rfq_requests
        SET
            status=?,
            admin_notes=?,
            updated_at=NOW()
        WHERE id=?
    ");

    $stmt->bind_param(
        "ssi",
        $status,
        $admin_notes,
        $rfq_id
    );

    $stmt->execute();

    header(
        "Location:b2b-rfq-details.php?id={$rfq_id}&updated=1"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| RFQ DETAILS
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT

r.*,

p.id product_id,
p.id,
p.image,

buyer.full_name buyer_name,
buyer.email buyer_email,
buyer.phone buyer_phone,

supplier.shop_name,
supplier.shop_slug,
supplier.logo,
supplier.verified,

resp.quoted_price,
resp.delivery_days,
resp.payment_terms,
resp.notes supplier_notes

FROM rfq_requests r

LEFT JOIN products p
ON p.id=r.product_id

LEFT JOIN users buyer
ON buyer.id=r.buyer_id

LEFT JOIN shops supplier
ON supplier.seller_id=r.supplier_id

LEFT JOIN rfq_responses resp
ON resp.rfq_id=r.id

WHERE r.id=?

LIMIT 1
");

$stmt->bind_param("i", $rfq_id);
$stmt->execute();

$rfq = $stmt->get_result()->fetch_assoc();

if (!$rfq) {
    die("RFQ not found.");
}

/*
|--------------------------------------------------------------------------
| RFQ MESSAGES
|--------------------------------------------------------------------------
*/
$messages = [];

$messageCheck = $conn->query("
SHOW TABLES LIKE 'rfq_messages'
");

if ($messageCheck->num_rows > 0) {

    $msgStmt = $conn->prepare("
        SELECT *
        FROM rfq_messages
        WHERE rfq_id=?
        ORDER BY created_at ASC
    ");

    $msgStmt->bind_param(
        "i",
        $rfq_id
    );

    $msgStmt->execute();

    $messages =
        $msgStmt
        ->get_result();
}

$updated = isset($_GET['updated']);

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>

RFQ Details

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f6fa;
}

.card-box{
    background:#fff;
    border-radius:12px;
}

.product-image{
    width:120px;
    height:120px;
    object-fit:cover;
    border-radius:10px;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<a
href="b2b-rfqs.php"
class="btn btn-secondary mb-3">

← Back

</a>

<?php if($updated): ?>

<div class="alert alert-success">

RFQ updated successfully.

</div>

<?php endif; ?>

<div class="row">

<!-- LEFT -->

<div class="col-lg-8">

<div class="card-box shadow-sm p-4 mb-4">

<h3>

<?= htmlspecialchars(
$rfq['quote_number']
) ?>

</h3>

<hr>

<div class="row">

<div class="col-md-3">

<?php if(!empty($rfq['image'])): ?>

<img
src="../uploads/products/<?= htmlspecialchars($rfq['image']) ?>"
class="product-image">

<?php endif; ?>

</div>

<div class="col-md-9">

<h5>

<?= htmlspecialchars(
$rfq['id']
) ?>

</h5>

<p>

Quantity:

<strong>

<?= number_format(
$rfq['quantity']
) ?>

</strong>

</p>

<p>

Target Price:

<strong>

TZS

<?= number_format(
(float)$rfq['target_price'],
2
) ?>

</strong>

</p>

<p>

Status:

<strong>

<?= ucfirst(
$rfq['status']
) ?>

</strong>

</p>

</div>

</div>

</div>

<!-- BUYER -->

<div class="card-box shadow-sm p-4 mb-4">

<h4>

Buyer Information

</h4>

<hr>

<p>

Name:
<strong>

<?= htmlspecialchars(
$rfq['buyer_name']
) ?>

</strong>

</p>

<p>

Email:

<?= htmlspecialchars(
$rfq['buyer_email']
) ?>

</p>

<p>

Phone:

<?= htmlspecialchars(
$rfq['buyer_phone']
) ?>

</p>

</div>

<!-- SUPPLIER -->

<div class="card-box shadow-sm p-4 mb-4">

<h4>

Supplier Information

</h4>

<hr>

<p>

Shop:

<strong>

<?= htmlspecialchars(
$rfq['shop_name']
) ?>

</strong>

<?php if($rfq['verified']): ?>
✅
<?php endif; ?>

</p>

<p>

Slug:

<?= htmlspecialchars(
$rfq['shop_slug']
) ?>

</p>

</div>

<!-- QUOTATION -->

<div class="card-box shadow-sm p-4 mb-4">

<h4>

Supplier Quotation

</h4>

<hr>

<?php if($rfq['quoted_price']): ?>

<p>

Quoted Price:

<strong>

TZS

<?= number_format(
$rfq['quoted_price'],
2
) ?>

</strong>

</p>

<p>

Delivery Days:

<strong>

<?= number_format(
$rfq['delivery_days']
) ?>

</strong>

</p>

<p>

Payment Terms:

<br>

<?= nl2br(
htmlspecialchars(
$rfq['payment_terms']
)
) ?>

</p>

<p>

Supplier Notes:

<br>

<?= nl2br(
htmlspecialchars(
$rfq['supplier_notes']
)
) ?>

</p>

<?php else: ?>

<div class="alert alert-warning">

No quotation submitted yet.

</div>

<?php endif; ?>

</div>

<!-- MESSAGES -->

<?php if($messages): ?>

<div class="card-box shadow-sm p-4">

<h4>

RFQ Conversation

</h4>

<hr>

<?php while($msg = $messages->fetch_assoc()): ?>

<div class="border rounded p-3 mb-2">

<p>

<?= nl2br(
htmlspecialchars(
$msg['message']
)
) ?>

</p>

<small class="text-muted">

<?= $msg['created_at'] ?>

</small>

</div>

<?php endwhile; ?>

</div>

<?php endif; ?>

</div>

<!-- RIGHT -->

<div class="col-lg-4">

<div class="card-box shadow-sm p-4">

<h4>

Admin Controls

</h4>

<hr>

<form method="POST">

<div class="mb-3">

<label>

RFQ Status

</label>

<select
name="status"
class="form-select">

<?php

$statuses = [
'pending',
'quoted',
'accepted',
'rejected',
'expired'
];

foreach($statuses as $status):

?>

<option
value="<?= $status ?>"
<?= $rfq['status']===$status ? 'selected' : '' ?>>

<?= ucfirst($status) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="mb-3">

<label>

Admin Notes

</label>

<textarea
name="admin_notes"
rows="6"
class="form-control"><?= htmlspecialchars(
$rfq['admin_notes'] ?? ''
) ?></textarea>

</div>

<button
class="btn btn-primary w-100">

Update RFQ

</button>

</form>

</div>

<div class="card-box shadow-sm p-4 mt-4">

<h5>

RFQ Summary

</h5>

<hr>

<p>

Created:

<?= date(
'd M Y H:i',
strtotime(
$rfq['created_at']
)
) ?>

</p>

<p>

Updated:

<?= !empty($rfq['updated_at'])
? date(
'd M Y H:i',
strtotime(
$rfq['updated_at']
))
: 'N/A'
?>

</p>

<p>

RFQ ID:

<?= $rfq['id'] ?>

</p>

</div>

</div>

</div>

</div>

</body>
</html>