<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$dispute_id = (int)($_GET['id'] ?? 0);

if ($dispute_id <= 0) {
    die("Invalid dispute.");
}

/*
|--------------------------------------------------------------------------
| UPDATE DISPUTE
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $status = trim($_POST['status']);
    $resolution_notes = trim($_POST['resolution_notes']);

    $admin_id = (int)currentUser()['id'];

    $stmt = $conn->prepare("
        UPDATE b2b_disputes
        SET

            status=?,
            resolution_notes=?,
            resolved_by=?,

            resolved_at=
            CASE
                WHEN ? IN ('resolved','closed')
                THEN NOW()
                ELSE resolved_at
            END,

            updated_at=NOW()

        WHERE id=?
    ");

    $stmt->bind_param(
        "ssissi",
        $status,
        $resolution_notes,
        $admin_id,
        $status,
        $dispute_id
    );

    $stmt->execute();

    header(
        "Location:b2b-dispute-details.php?id={$dispute_id}&updated=1"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| DISPUTE
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT

d.*,

buyer.full_name buyer_name,
buyer.email buyer_email,
buyer.phone buyer_phone,

s.shop_name,
s.shop_slug,

o.order_number,
o.total_amount,

admin.full_name resolved_admin

FROM b2b_disputes d

LEFT JOIN users buyer
ON buyer.id=d.buyer_id

LEFT JOIN shops s
ON s.seller_id=d.supplier_id

LEFT JOIN b2b_orders o
ON o.id=d.order_id

LEFT JOIN users admin
ON admin.id=d.resolved_by

WHERE d.id=?

LIMIT 1
");

$stmt->bind_param(
    "i",
    $dispute_id
);

$stmt->execute();

$dispute =
    $stmt
    ->get_result()
    ->fetch_assoc();

if (!$dispute) {
    die("Dispute not found.");
}

/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/
$messages = [];

$tableExists = $conn->query(
    "SHOW TABLES LIKE 'dispute_messages'"
);

if ($tableExists->num_rows > 0) {

    $msgStmt = $conn->prepare("
        SELECT

        dm.*,

        u.full_name

        FROM dispute_messages dm

        LEFT JOIN users u
        ON u.id=dm.user_id

        WHERE dm.dispute_id=?

        ORDER BY dm.created_at ASC
    ");

    $msgStmt->bind_param(
        "i",
        $dispute_id
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
Dispute Details
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

</style>

</head>

<body>

<div class="container-fluid py-4">

<a
href="b2b-disputes.php"
class="btn btn-secondary mb-3">

← Back

</a>

<?php if($updated): ?>

<div class="alert alert-success">

Dispute updated successfully.

</div>

<?php endif; ?>

<div class="row">

<!-- LEFT -->

<div class="col-lg-8">

<div class="card-box shadow-sm p-4 mb-4">

<h3>

<?= htmlspecialchars(
$dispute['dispute_number']
) ?>

</h3>

<hr>

<p>

Subject:

<strong>

<?= htmlspecialchars(
$dispute['subject']
) ?>

</strong>

</p>

<p>

Type:

<strong>

<?= ucfirst(
$dispute['dispute_type']
) ?>

</strong>

</p>

<p>

Status:

<strong>

<?= ucfirst(
$dispute['status']
) ?>

</strong>

</p>

<p>

Description:

</p>

<div class="border rounded p-3">

<?= nl2br(
htmlspecialchars(
$dispute['description']
)
) ?>

</div>

</div>

<div class="card-box shadow-sm p-4 mb-4">

<h4>

Buyer

</h4>

<hr>

<p>

<?= htmlspecialchars(
$dispute['buyer_name']
) ?>

</p>

<p>

<?= htmlspecialchars(
$dispute['buyer_email']
) ?>

</p>

<p>

<?= htmlspecialchars(
$dispute['buyer_phone']
) ?>

</p>

</div>

<div class="card-box shadow-sm p-4 mb-4">

<h4>

Supplier

</h4>

<hr>

<p>

<?= htmlspecialchars(
$dispute['shop_name']
) ?>

</p>

<p>

<?= htmlspecialchars(
$dispute['shop_slug']
) ?>

</p>

</div>

<div class="card-box shadow-sm p-4 mb-4">

<h4>

Related Order

</h4>

<hr>

<p>

Order Number:

<strong>

<?= htmlspecialchars(
$dispute['order_number']
) ?>

</strong>

</p>

<p>

Order Value:

<strong>

TZS

<?= number_format(
$dispute['total_amount'],
2
) ?>

</strong>

</p>

</div>

<?php if(!empty($dispute['evidence'])): ?>

<div class="card-box shadow-sm p-4 mb-4">

<h4>

Evidence

</h4>

<hr>

<a
href="../uploads/disputes/<?= htmlspecialchars($dispute['evidence']) ?>"
target="_blank"
class="btn btn-primary">

View Evidence

</a>

</div>

<?php endif; ?>

<?php if($messages): ?>

<div class="card-box shadow-sm p-4">

<h4>

Dispute Conversation

</h4>

<hr>

<?php while($msg = $messages->fetch_assoc()): ?>

<div class="border rounded p-3 mb-2">

<strong>

<?= htmlspecialchars(
$msg['full_name']
) ?>

</strong>

<br><br>

<?= nl2br(
htmlspecialchars(
$msg['message']
)
) ?>

<br>

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

Resolution Center

</h4>

<hr>

<form method="POST">

<div class="mb-3">

<label>Status</label>

<select
name="status"
class="form-select">

<?php

$statuses = [
'open',
'investigating',
'resolved',
'closed'
];

foreach($statuses as $status):

?>

<option
value="<?= $status ?>"
<?= $dispute['status']===$status ? 'selected' : '' ?>>

<?= ucfirst($status) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="mb-3">

<label>

Resolution Notes

</label>

<textarea
name="resolution_notes"
rows="8"
class="form-control"><?= htmlspecialchars(
$dispute['resolution_notes']
?? ''
) ?></textarea>

</div>

<button
class="btn btn-success w-100">

Save Resolution

</button>

</form>

</div>

<div class="card-box shadow-sm p-4 mt-4">

<h5>

Audit Trail

</h5>

<hr>

<p>

Created:

<br>

<?= date(
'd M Y H:i',
strtotime(
$dispute['created_at']
)
) ?>

</p>

<?php if($dispute['resolved_at']): ?>

<p>

Resolved:

<br>

<?= date(
'd M Y H:i',
strtotime(
$dispute['resolved_at']
)
) ?>

</p>

<?php endif; ?>

<?php if($dispute['resolved_admin']): ?>

<p>

Resolved By:

<br>

<strong>

<?= htmlspecialchars(
$dispute['resolved_admin']
) ?>

</strong>

</p>

<?php endif; ?>

</div>

</div>

</div>

</div>

</body>
</html>