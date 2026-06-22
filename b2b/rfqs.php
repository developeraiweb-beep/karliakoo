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

$status =
trim(
$_GET['status']
?? ''
);

$search =
trim(
$_GET['search']
?? ''
);

$page =
max(
1,
(int)($_GET['page'] ?? 1)
);

$perPage = 15;
$offset =
($page - 1)
*
$perPage;

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$where =
"buyer_id=?";

$params = [$userId];
$types = "i";

if(!empty($status))
{
    $where .= "
    AND status=?
    ";

    $params[] =
    $status;

    $types .= "s";
}

if(!empty($search))
{
    $where .= "
    AND (
        quote_number LIKE ?
        OR delivery_location LIKE ?
    )
    ";

    $keyword =
    "%{$search}%";

    $params[] =
    $keyword;

    $params[] =
    $keyword;

    $types .= "ss";
}

/*
|--------------------------------------------------------------------------
| RFQ STATS
|--------------------------------------------------------------------------
*/

$stats = [];

$statuses = [
'pending',
'quoted',
'accepted',
'rejected',
'expired'
];

foreach($statuses as $rfqStatus)
{
    $stmt =
    $conn->prepare("
        SELECT COUNT(*) total
        FROM rfq_requests
        WHERE buyer_id=?
        AND status=?
    ");

    $stmt->bind_param(
        "is",
        $userId,
        $rfqStatus
    );

    $stmt->execute();

    $stats[$rfqStatus] =
    (int)
    $stmt
    ->get_result()
    ->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| COUNT RFQS
|--------------------------------------------------------------------------
*/

$countSql = "
SELECT COUNT(*)
total
FROM rfq_requests
WHERE {$where}
";

$countStmt =
$conn->prepare(
$countSql
);

$countStmt->bind_param(
$types,
...$params
);

$countStmt->execute();

$totalRecords =
(int)
$countStmt
->get_result()
->fetch_assoc()['total'];

$totalPages =
(int)ceil(
$totalRecords
/
$perPage
);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

My RFQs

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">

</head>

<body>

<div class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>

My RFQs

</h2>

<p class="text-muted">

Manage all quotation requests.

</p>

</div>

<a
href="create-rfq.php"
class="btn btn-primary">

<i class="bi bi-plus-circle"></i>

Create RFQ

</a>

</div>

<div class="row mb-4">

<div class="col-md">

<div class="card text-center">

<div class="card-body">

<h4>

<?= $stats['pending'] ?>

</h4>

Pending

</div>

</div>

</div>

<div class="col-md">

<div class="card text-center">

<div class="card-body">

<h4>

<?= $stats['quoted'] ?>

</h4>

Quoted

</div>

</div>

</div>

<div class="col-md">

<div class="card text-center">

<div class="card-body">

<h4>

<?= $stats['accepted'] ?>

</h4>

Accepted

</div>

</div>

</div>

<div class="col-md">

<div class="card text-center">

<div class="card-body">

<h4>

<?= $stats['rejected'] ?>

</h4>

Rejected

</div>

</div>

</div>

<div class="col-md">

<div class="card text-center">

<div class="card-body">

<h4>

<?= $stats['expired'] ?>

</h4>

Expired

</div>

</div>

</div>

</div>

<form
method="GET"
class="row g-2 mb-4">

<div class="col-md-5">

<input
type="text"
name="search"
class="form-control"
placeholder="Search RFQ..."
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-3">

<select
name="status"
class="form-select">

<option value="">

All Statuses

</option>

<option
value="pending"
<?= $status=='pending'?'selected':'' ?>>

Pending

</option>

<option
value="quoted"
<?= $status=='quoted'?'selected':'' ?>>

Quoted

</option>

<option
value="accepted"
<?= $status=='accepted'?'selected':'' ?>>

Accepted

</option>

<option
value="rejected"
<?= $status=='rejected'?'selected':'' ?>>

Rejected

</option>

<option
value="expired"
<?= $status=='expired'?'selected':'' ?>>

Expired

</option>

</select>

</div>

<div class="col-md-2">

<button
class="btn btn-primary w-100">

Filter

</button>

</div>

<div class="col-md-2">

<a
href="rfqs.php"
class="btn btn-secondary w-100">

Reset

</a>

</div>

</form>

<?php

/*
|--------------------------------------------------------------------------
| LOAD RFQS
|--------------------------------------------------------------------------
*/

$sql = "
SELECT

r.*,

p.name AS product_name,

s.shop_name

FROM rfq_requests r

LEFT JOIN products p
ON p.id = r.product_id

LEFT JOIN shops s
ON s.id = r.supplier_id

WHERE {$where}

ORDER BY r.id DESC

LIMIT {$offset}, {$perPage}
";

$stmt =
$conn->prepare(
$sql
);

$stmt->bind_param(
$types,
...$params
);

$stmt->execute();

$rfqs =
$stmt
->get_result();

?>

<div class="card shadow-sm">

<div class="card-header">

RFQ Requests

</div>

<div class="card-body p-0">

<?php if(
$rfqs->num_rows > 0
): ?>

<div class="table-responsive">

<table class="table table-hover align-middle mb-0">

<thead>

<tr>

<th>

RFQ #

</th>

<th>

Product

</th>

<th>

Supplier

</th>

<th>

Quantity

</th>

<th>

Target Price

</th>

<th>

Status

</th>

<th>

Date

</th>

<th>

Actions

</th>

</tr>

</thead>

<tbody>

<?php while(
$rfq =
$rfqs->fetch_assoc()
): ?>

<tr>

<td>

<strong>

<?= htmlspecialchars(
$rfq['quote_number']
?: ('RFQ-'.$rfq['id'])
) ?>

</strong>

</td>

<td>

<?= htmlspecialchars(
$rfq['product_name']
?: 'Product Removed'
) ?>

</td>

<td>

<?= htmlspecialchars(
$rfq['shop_name']
?: 'Supplier Removed'
) ?>

</td>

<td>

<?= number_format(
(int)$rfq['quantity']
) ?>

</td>

<td>

<?php if(
!empty(
$rfq['target_price']
)
): ?>

TZS

<?= number_format(
(float)$rfq['target_price']
) ?>

<?php else: ?>

*

<?php endif; ?>

</td>

<td>

<?php

$badge =
'secondary';

switch(
$rfq['status']
)
{
    case 'pending':
        $badge = 'warning';
        break;

    case 'quoted':
        $badge = 'info';
        break;

    case 'accepted':
        $badge = 'success';
        break;

    case 'rejected':
        $badge = 'danger';
        break;

    case 'expired':
        $badge = 'dark';
        break;
}

?>

<span
class="badge bg-<?= $badge ?>">

<?= ucfirst(
$rfq['status']
) ?>

</span>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$rfq['created_at']
)
) ?>

</td>

<td>

<div
class="btn-group btn-group-sm">

<a
href="rfq-details.php?id=<?= (int)$rfq['id'] ?>"
class="btn btn-outline-primary">

View

</a>

<?php if(
$rfq['status']
=== 'quoted'
): ?>

<a
href="accept-rfq.php?id=<?= (int)$rfq['id'] ?>"
class="btn btn-success"
onclick="
return confirm(
'Accept this quotation?'
);
">

Accept

</a>

<?php endif; ?>

<?php if(
in_array(
$rfq['status'],
[
'pending',
'quoted'
]
)
): ?>

<a
href="cancel-rfq.php?id=<?= (int)$rfq['id'] ?>"
class="btn btn-danger"
onclick="
return confirm(
'Cancel this RFQ?'
);
">

Cancel

</a>

<?php endif; ?>

</div>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

<?php else: ?>

<div class="text-center py-5">

<i
class="bi bi-file-earmark-text fs-1 text-muted"> </i>

<h5 class="mt-3">

No RFQs Found

</h5>

<p class="text-muted">

You have not submitted any RFQs yet.

</p>

<a
href="create-rfq.php"
class="btn btn-primary">

Create First RFQ

</a>

</div>

<?php endif; ?>

</div>

</div>

<?php

/*
|--------------------------------------------------------------------------
| RECENT RFQ ACTIVITY
|--------------------------------------------------------------------------
*/

$recentActivity =
mysqli_query(
$conn,
"
SELECT
quote_number,
status,
created_at
FROM rfq_requests
WHERE buyer_id={$userId}
ORDER BY id DESC
LIMIT 5
"
);

?>

<div class="row mt-4">

<div class="col-lg-8">

<?php if($totalPages > 1): ?>

<nav class="mt-4">

<ul class="pagination justify-content-center">

<?php for(
$i = 1;
$i <= $totalPages;
$i++
): ?>

<li
class="page-item <?= $page == $i ? 'active' : '' ?>">

<a
class="page-link"
href="?page=<?= $i ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>">

<?= $i ?>

</a>

</li>

<?php endfor; ?>

</ul>

</nav>

<?php endif; ?>

<div class="card shadow-sm mt-4">

<div class="card-header">

RFQ Status Guide

</div>

<div class="card-body">

<div class="row">

<div class="col-md-6">

<p>

<span class="badge bg-warning">

Pending

</span>

Supplier has not responded yet.

</p>

<p>

<span class="badge bg-info">

Quoted

</span>

Supplier has provided a quotation.

</p>

<p>

<span class="badge bg-success">

Accepted

</span>

Quotation accepted.

</p>

</div>

<div class="col-md-6">

<p>

<span class="badge bg-danger">

Rejected

</span>

Quotation rejected.

</p>

<p>

<span class="badge bg-dark">

Expired

</span>

RFQ expired.

</p>

</div>

</div>

</div>

</div>

</div>

<div class="col-lg-4">

<div class="card shadow-sm mb-4">

<div class="card-header">

Recent Activity

</div>

<div class="list-group list-group-flush">

<?php while(
$activity =
mysqli_fetch_assoc(
$recentActivity
)
): ?>

<div class="list-group-item">

<div class="fw-bold">

<?= htmlspecialchars(
$activity['quote_number']
) ?>

</div>

<div class="small text-muted">

<?= ucfirst(
$activity['status']
) ?>

</div>

<div class="small text-secondary">

<?= date(
'd M Y H:i',
strtotime(
$activity['created_at']
)
) ?>

</div>

</div>

<?php endwhile; ?>

</div>

</div>

<div class="card shadow-sm">

<div class="card-header">

Quick Actions

</div>

<div class="card-body">

<div class="d-grid gap-2">

<a
href="create-rfq.php"
class="btn btn-primary">

<i class="bi bi-plus-circle"></i>

New RFQ

</a>

<a
href="products.php"
class="btn btn-outline-success">

<i class="bi bi-box"></i>

Browse Products

</a>

<a
href="suppliers.php"
class="btn btn-outline-info">

<i class="bi bi-building"></i>

Find Suppliers

</a>

<a
href="orders.php"
class="btn btn-outline-dark">

<i class="bi bi-cart"></i>

B2B Orders

</a>

</div>

</div>

</div>

<div class="card shadow-sm mt-4">

<div class="card-body text-center">

<h5>

Need Bulk Pricing?

</h5>

<p class="text-muted">

Submit detailed RFQs and receive
competitive quotations from verified suppliers.

</p>

<a
href="create-rfq.php"
class="btn btn-success">

Request Quotation

</a>

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

RFQs • Procurement • Wholesale Trading

</p>

</footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
