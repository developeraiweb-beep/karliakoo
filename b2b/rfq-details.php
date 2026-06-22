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
    die("Invalid RFQ ID.");
}

/*
|--------------------------------------------------------------------------
| LOAD RFQ
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
SELECT
r.*,
p.name AS product_name,
p.image,
p.featured_image,
s.shop_name
FROM rfq_requests r
LEFT JOIN products p ON p.id = r.product_id
LEFT JOIN shops s ON s.id = r.supplier_id
WHERE r.id = ?
AND r.buyer_id = ?
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
| UPDATE VIEW (optional tracking)
|--------------------------------------------------------------------------
*/

$conn->query("
UPDATE rfq_requests
SET updated_at = NOW()
WHERE id = $rfqId
");

/*
|--------------------------------------------------------------------------
| SUPPLIER RESPONSE (future-ready structure)
|--------------------------------------------------------------------------
| If you later add rfq_responses table, this is already prepared.
|--------------------------------------------------------------------------
*/

$response = null;

$responseQuery = $conn->prepare("
SELECT *
FROM rfq_responses
WHERE rfq_id = ?
LIMIT 1
");

if ($responseQuery)
{
    $responseQuery->bind_param("i", $rfqId);
    $responseQuery->execute();
    $response = $responseQuery->get_result()->fetch_assoc();
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="utf-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>RFQ Details</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

</head>

<body>

<div class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>RFQ Details</h2>

<p class="text-muted">

Quote #<?= htmlspecialchars($rfq['quote_number'] ?? ('RFQ-'.$rfq['id'])) ?>

</p>

</div>

<a href="rfqs.php" class="btn btn-outline-secondary">

Back

</a>

</div>

<div class="row">

<div class="col-lg-8">

<div class="card shadow-sm mb-4">

<div class="card-header">

RFQ Information

</div>

<div class="card-body">

<div class="row">

<div class="col-md-4">

<?php

$image =
$rfq['featured_image']
?: $rfq['image'];

if(empty($image))
{
    $image = "../assets/images/no-image.jpg";
}
else
{
    $image = "../" . ltrim($image,'./');
}

?>

<img
src="<?= htmlspecialchars($image) ?>"
class="img-fluid rounded border">

</div>

<div class="col-md-8">

<h4>

<?= htmlspecialchars($rfq['product_name'] ?? 'Product Removed') ?>

</h4>

<p class="text-muted">

Supplier:

<strong>

<?= htmlspecialchars($rfq['shop_name'] ?? 'Unknown Supplier') ?>

</strong>

</p>

<hr>

<p>

<strong>Quantity:</strong>

<?= number_format((int)$rfq['quantity']) ?>

</p>

<p>

<strong>Target Price:</strong>

<?php if(!empty($rfq['target_price'])): ?>

TZS <?= number_format((float)$rfq['target_price']) ?>

<?php else: ?>

Not specified

<?php endif; ?>

</p>

<p>

<strong>Delivery Location:</strong>

<?= htmlspecialchars($rfq['delivery_location'] ?: 'N/A') ?>

</p>

<p>

<strong>Status:</strong>

<?php

$badge = 'secondary';

switch($rfq['status'])
{
    case 'pending': $badge = 'warning'; break;
    case 'quoted': $badge = 'info'; break;
    case 'accepted': $badge = 'success'; break;
    case 'rejected': $badge = 'danger'; break;
    case 'expired': $badge = 'dark'; break;
}

?>

<span class="badge bg-<?= $badge ?>">

<?= ucfirst($rfq['status']) ?>

</span>

</p>

</div>

</div>

</div>

</div>

<?php if(!empty($rfq['message'])): ?>

<div class="card shadow-sm mb-4">

<div class="card-header">

Buyer Message

</div>

<div class="card-body">

<?= nl2br(htmlspecialchars($rfq['message'])) ?>

</div>

</div>

<?php endif; ?>

<?php if($response): ?>

<div class="card shadow-sm mb-4">

<div class="card-header bg-success text-white">

Supplier Quotation

</div>

<div class="card-body">

<p>

<strong>Price:</strong>

TZS <?= number_format((float)$response['price']) ?>

</p>

<p>

<strong>Delivery Time:</strong>

<?= htmlspecialchars($response['delivery_time'] ?? 'N/A') ?>

</p>

<p>

<strong>Notes:</strong><br>

<?= nl2br(htmlspecialchars($response['notes'] ?? '')) ?>

</p>

</div>

</div>

<?php endif; ?>

<?php if($rfq['status'] === 'quoted'): ?>

<div class="card shadow-sm mb-4">

<div class="card-header">

Take Action

</div>

<div class="card-body d-flex gap-2">

<a href="accept-rfq.php?id=<?= (int)$rfq['id'] ?>" class="btn btn-success">

Accept Quote

</a>

<a href="cancel-rfq.php?id=<?= (int)$rfq['id'] ?>" class="btn btn-danger">

Reject Quote

</a>

</div>

</div>

<?php endif; ?>

</div>

<div class="col-lg-4">

<div class="card shadow-sm">

<div class="card-header">

RFQ Timeline

</div>

<div class="card-body">

<ul class="list-group list-group-flush">

<li class="list-group-item">

<i class="bi bi-check-circle text-primary"></i>

RFQ Submitted

</li>

<li class="list-group-item">

<i class="bi bi-clock text-warning"></i>

Waiting Supplier Response

</li>

<li class="list-group-item">

<i class="bi bi-chat-dots text-info"></i>

Quotation Received

</li>

<li class="list-group-item">

<i class="bi bi-check2-circle text-success"></i>

Completed / Accepted

</li>

</ul>

</div>

</div>

<div class="card shadow-sm mt-4">

<div class="card-body">

<h5>Need Help?</h5>

<p class="text-muted">

Contact supplier directly or submit another RFQ for comparison.

</p>

<a href="create-rfq.php" class="btn btn-primary w-100">

Create New RFQ

</a>

</div>

</div>

</div>

</div>

<hr class="my-5">

<div class="row">

<div class="col-lg-8">

<div class="card shadow-sm">

<div class="card-header">

RFQ Activity Log

</div>

<div class="card-body">

<?php

/*
|--------------------------------------------------------------------------
| FUTURE READY ACTIVITY LOG
|--------------------------------------------------------------------------
| (If you later add rfq_logs table, this will activate automatically)
|--------------------------------------------------------------------------
*/

$logsQuery = $conn->prepare("
SELECT *
FROM rfq_logs
WHERE rfq_id = ?
ORDER BY id DESC
LIMIT 10
");

$logs = null;

if($logsQuery)
{
    $logsQuery->bind_param("i", $rfqId);
    $logsQuery->execute();
    $logs = $logsQuery->get_result();
}

?>

<?php if($logs && $logs->num_rows > 0): ?>

<ul class="list-group">

<?php while($log = $logs->fetch_assoc()): ?>

<li class="list-group-item">

<strong><?= htmlspecialchars($log['action'] ?? 'Update') ?></strong><br>

<small class="text-muted">

<?= htmlspecialchars($log['description'] ?? '') ?>

</small><br>

<small class="text-secondary">

<?= date('d M Y H:i', strtotime($log['created_at'])) ?>

</small>

</li>

<?php endwhile; ?>

</ul>

<?php else: ?>

<div class="text-muted">

No activity logs available yet.

</div>

<?php endif; ?>

</div>

</div>

</div>

<div class="col-lg-4">

<div class="card shadow-sm">

<div class="card-header">

Quick Actions

</div>

<div class="card-body d-grid gap-2">

<a href="rfqs.php" class="btn btn-outline-primary">

Back to RFQs

</a>

<a href="create-rfq.php?product_id=<?= (int)($rfq['product_id'] ?? 0) ?>" class="btn btn-success">

Request Similar RFQ

</a>

<a href="products.php" class="btn btn-outline-dark">

Browse Products

</a>

</div>

</div>

<div class="card shadow-sm mt-4">

<div class="card-body text-center">

<h6 class="mb-2">

RFQ Status

</h6>

<span class="badge bg-primary">

<?= strtoupper($rfq['status']) ?>

</span>

<p class="mt-2 text-muted small">

Last updated:

<?= date('d M Y', strtotime($rfq['updated_at'])) ?>

</p>

</div>

</div>

</div>

</div>

<footer class="mt-5 pt-4 border-top text-center text-muted">

<p>

© <?= date('Y') ?> Karliakoo B2B Marketplace

</p>

<p>

RFQs • Procurement • Supplier Network • Wholesale Trade

</p>

</footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
