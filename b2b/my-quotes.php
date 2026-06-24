<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user = currentUser();

$buyer_id = (int)$user['id'];

$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = [
    "r.buyer_id=?"
];

$params = [$buyer_id];
$types = "i";

if (!empty($status)) {

    $where[] = "r.status=?";

    $params[] = $status;
    $types .= "s";
}

if (!empty($search)) {

    $where[] = "(
        r.quote_number LIKE ?
        OR p.product_name LIKE ?
        OR s.shop_name LIKE ?
    )";

    $like = "%{$search}%";

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

    $types .= "sss";
}

$whereSQL = implode(" AND ", $where);

$sql = "

SELECT

r.*,

p.id,
p.image,

s.shop_name,

resp.quoted_price,
resp.delivery_days

FROM rfq_requests r

LEFT JOIN products p
ON p.id = r.product_id

LEFT JOIN shops s
ON s.seller_id = r.supplier_id

LEFT JOIN (

    SELECT x.*
    FROM rfq_responses x

) resp

ON resp.rfq_id = r.id

WHERE {$whereSQL}

ORDER BY r.id DESC

";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    $types,
    ...$params
);

$stmt->execute();

$quotes = $stmt->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<meta name="viewport"
content="width=device-width,initial-scale=1">

<title>My Quotations</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
    background:#f5f6fa;
}

.product-img{
    width:60px;
    height:60px;
    object-fit:cover;
    border-radius:8px;
}

</style>

</head>
<body>

<div class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<h2>My RFQ Quotations</h2>

<a
href="wholesale-products.php"
class="btn btn-primary">

Browse Products

</a>

</div>

<form method="GET" class="mb-4">

<div class="row">

<div class="col-md-5">

<input
type="text"
name="search"
value="<?= htmlspecialchars($search) ?>"
class="form-control"
placeholder="Search RFQ">

</div>

<div class="col-md-4">

<select
name="status"
class="form-select">

<option value="">
All Status
</option>

<option value="pending">
Pending
</option>

<option value="quoted">
Quoted
</option>

<option value="accepted">
Accepted
</option>

<option value="rejected">
Rejected
</option>

<option value="expired">
Expired
</option>

</select>

</div>

<div class="col-md-3">

<button class="btn btn-success w-100">

Filter

</button>

</div>

</div>

</form>

<div class="card shadow-sm">

<div class="card-header">

My RFQs

</div>

<div class="table-responsive">

<table class="table table-hover mb-0">

<thead>

<tr>

<th>Quote No</th>
<th>Product</th>
<th>Supplier</th>
<th>Qty</th>
<th>Quoted Price</th>
<th>Status</th>
<th>Date</th>
<th></th>

</tr>

</thead>

<tbody>

<?php while($quote = $quotes->fetch_assoc()): ?>

<tr>

<td>

<strong>

<?= htmlspecialchars(
$quote['quote_number']
) ?>

</strong>

</td>

<td>

<div class="d-flex align-items-center">

<img
src="../uploads/products/<?= htmlspecialchars($quote['image']) ?>"
class="product-img me-2">

<div>

<?= htmlspecialchars(
$quote['product_name']
) ?>

</div>

</div>

</td>

<td>

<?= htmlspecialchars(
$quote['shop_name']
) ?>

</td>

<td>

<?= number_format(
$quote['quantity']
) ?>

</td>

<td>

<?php if($quote['quoted_price']): ?>

TZS
<?= number_format(
$quote['quoted_price'],
2
) ?>

<?php else: ?>

<span class="text-muted">

Waiting

</span>

<?php endif; ?>

</td>

<td>

<?php

$badge = match($quote['status']) {

'pending' => 'warning',

'quoted' => 'primary',

'accepted' => 'success',

'rejected' => 'danger',

'expired' => 'secondary',

default => 'dark'

};

?>

<span class="badge bg-<?= $badge ?>">

<?= ucfirst(
$quote['status']
) ?>

</span>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$quote['created_at']
)
) ?>

</td>

<td>

<a
href="quote-details.php?id=<?= $quote['id'] ?>"
class="btn btn-sm btn-primary">

View

</a>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>