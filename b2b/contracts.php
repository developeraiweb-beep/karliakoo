<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['b2b']);

$userId = $_SESSION['user_id'];

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| CREATE CONTRACT
|--------------------------------------------------------------------------
*/
if(isset($_POST['create_contract']))
{
    $contractNumber =
    'CNT-' . date('Ymd') . '-' . rand(1000,9999);

    $title =
    trim($_POST['contract_title']);

    $type =
    trim($_POST['contract_type']);

    $supplierId =
    (int)($_POST['supplier_id'] ?? 0);

    $startDate =
    $_POST['start_date'];

    $endDate =
    $_POST['end_date'];

    $value =
    (float)$_POST['contract_value'];

    $notes =
    trim($_POST['notes']);

    $stmt = $conn->prepare("
    INSERT INTO b2b_contracts(
        buyer_id,
        supplier_id,
        contract_number,
        contract_title,
        contract_type,
        start_date,
        end_date,
        contract_value,
        notes
    )
    VALUES(?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "iisssssds",
        $userId,
        $supplierId,
        $contractNumber,
        $title,
        $type,
        $startDate,
        $endDate,
        $value,
        $notes
    );

    if($stmt->execute())
    {
        $message = "Contract created successfully.";
    }
}

/*
|--------------------------------------------------------------------------
| TERMINATE CONTRACT
|--------------------------------------------------------------------------
*/
if(isset($_GET['terminate']))
{
    $contractId =
    (int)$_GET['terminate'];

    $stmt = $conn->prepare("
    UPDATE b2b_contracts
    SET status='terminated'
    WHERE id=?
    AND buyer_id=?
    ");

    $stmt->bind_param(
        "ii",
        $contractId,
        $userId
    );

    $stmt->execute();

    header("Location: contracts.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CONTRACTS
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT *
FROM b2b_contracts
WHERE buyer_id=?
ORDER BY id DESC
");

$stmt->bind_param(
    "i",
    $userId
);

$stmt->execute();

$contracts =
$stmt->get_result();

/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/
$stats = $conn->prepare("
SELECT

COUNT(*) total,

SUM(
CASE
WHEN status='active'
THEN 1
ELSE 0
END
) active_contracts,

SUM(
CASE
WHEN status='expired'
THEN 1
ELSE 0
END
) expired_contracts,

COALESCE(
SUM(contract_value),
0
) total_value

FROM b2b_contracts

WHERE buyer_id=?
");

$stats->bind_param(
    "i",
    $userId
);

$stats->execute();

$stats =
$stats
->get_result()
->fetch_assoc();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>B2B Contracts</title>

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
background:#f4f6f9;
}

.card-box{
background:white;
padding:20px;
border-radius:15px;
margin-bottom:20px;
box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.metric{
font-size:30px;
font-weight:700;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Contracts & Agreements

</h2>

<?php if($message): ?>

<div class="alert alert-success">

<?= $message ?>

</div>

<?php endif; ?>

<!-- STATS -->

<div class="row mb-4">

<div class="col-md-3">

<div class="card-box text-center">

<div class="metric">

<?= number_format(
$stats['total']
) ?>

</div>

Contracts

</div>

</div>

<div class="col-md-3">

<div class="card-box text-center">

<div class="metric text-success">

<?= number_format(
$stats['active_contracts']
) ?>

</div>

Active

</div>

</div>

<div class="col-md-3">

<div class="card-box text-center">

<div class="metric text-danger">

<?= number_format(
$stats['expired_contracts']
) ?>

</div>

Expired

</div>

</div>

<div class="col-md-3">

<div class="card-box text-center">

<div class="metric text-primary">

TZS <?= number_format(
$stats['total_value'],
2
) ?>

</div>

Value

</div>

</div>

</div>

<!-- CREATE -->

<div class="card-box">

<h5>

Create Contract

</h5>

<form method="POST">

<div class="row g-3">

<div class="col-md-6">

<input
type="text"
name="contract_title"
class="form-control"
placeholder="Contract Title"
required>

</div>

<div class="col-md-3">

<select
name="contract_type"
class="form-select">

<option value="purchase">
Purchase Agreement
</option>

<option value="supply">
Supply Agreement
</option>

<option value="service">
Service Agreement
</option>

<option value="nda">
NDA
</option>

<option value="partnership">
Partnership
</option>

</select>

</div>

<div class="col-md-3">

<input
type="number"
step="0.01"
name="contract_value"
class="form-control"
placeholder="Contract Value">

</div>

<div class="col-md-3">

<input
type="date"
name="start_date"
class="form-control">

</div>

<div class="col-md-3">

<input
type="date"
name="end_date"
class="form-control">

</div>

<div class="col-md-3">

<input
type="number"
name="supplier_id"
class="form-control"
placeholder="Supplier ID">

</div>

<div class="col-md-12">

<textarea
name="notes"
rows="4"
class="form-control"
placeholder="Contract Notes"></textarea>

</div>

<div class="col-md-3">

<button
name="create_contract"
class="btn btn-primary">

Create Contract

</button>

</div>

</div>

</form>

</div>

<!-- CONTRACT LIST -->

<div class="card-box">

<h5>

Contract Registry

</h5>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Number</th>
<th>Title</th>
<th>Type</th>
<th>Value</th>
<th>Status</th>
<th>End Date</th>
<th>Actions</th>

</tr>

</thead>

<tbody>

<?php while(
$contract =
$contracts->fetch_assoc()
): ?>

<tr>

<td>

<?= htmlspecialchars(
$contract['contract_number']
) ?>

</td>

<td>

<?= htmlspecialchars(
$contract['contract_title']
) ?>

</td>

<td>

<?= ucfirst(
$contract['contract_type']
) ?>

</td>

<td>

TZS

<?= number_format(
$contract['contract_value'],
2
) ?>

</td>

<td>

<span class="badge bg-<?=
$contract['status']=='active'
?
'success'
:
(
$contract['status']=='expired'
?
'danger'
:
'secondary'
)
?>">

<?= ucfirst(
$contract['status']
) ?>

</span>

</td>

<td>

<?= $contract['end_date'] ?>

</td>

<td>

<a
href="contract-details.php?id=<?= $contract['id'] ?>"
class="btn btn-sm btn-primary">

View

</a>

<?php if(
$contract['status']!='terminated'
): ?>

<a
href="?terminate=<?= $contract['id'] ?>"
class="btn btn-sm btn-danger"
onclick="return confirm('Terminate contract?')">

Terminate

</a>

<?php endif; ?>

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