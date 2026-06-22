<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$account = trim($_GET['account'] ?? '');
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$where = ["1=1"];
$params = [];
$types = '';

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/
if(!empty($account))
{
    $where[] = "account_code=?";
    $params[] = $account;
    $types .= "s";
}

if(!empty($from))
{
    $where[] = "DATE(transaction_date) >= ?";
    $params[] = $from;
    $types .= "s";
}

if(!empty($to))
{
    $where[] = "DATE(transaction_date) <= ?";
    $params[] = $to;
    $types .= "s";
}

$whereSql = implode(" AND ", $where);

/*
|--------------------------------------------------------------------------
| TOTALS
|--------------------------------------------------------------------------
*/
$totalDebit = 0;
$totalCredit = 0;

$stmt = $conn->prepare("
SELECT

COALESCE(SUM(debit),0) total_debit,
COALESCE(SUM(credit),0) total_credit

FROM general_ledger

WHERE {$whereSql}
");

if(!empty($params))
{
    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$totals =
$stmt
->get_result()
->fetch_assoc();

$totalDebit =
$totals['total_debit'];

$totalCredit =
$totals['total_credit'];

/*
|--------------------------------------------------------------------------
| RECORD COUNT
|--------------------------------------------------------------------------
*/
$countStmt = $conn->prepare("
SELECT COUNT(*) total
FROM general_ledger
WHERE {$whereSql}
");

if(!empty($params))
{
    $countStmt->bind_param(
        $types,
        ...$params
    );
}

$countStmt->execute();

$totalRows =
$countStmt
->get_result()
->fetch_assoc()['total'];

$totalPages =
max(
1,
ceil($totalRows/$limit)
);

/*
|--------------------------------------------------------------------------
| LEDGER ENTRIES
|--------------------------------------------------------------------------
*/
$sql = "
SELECT *
FROM general_ledger

WHERE {$whereSql}

ORDER BY transaction_date DESC

LIMIT ?,?
";

$stmt = $conn->prepare($sql);

$bindTypes = $types . "ii";

$bindParams = $params;
$bindParams[] = $offset;
$bindParams[] = $limit;

$stmt->bind_param(
    $bindTypes,
    ...$bindParams
);

$stmt->execute();

$entries =
$stmt->get_result();

/*
|--------------------------------------------------------------------------
| ACCOUNT LIST
|--------------------------------------------------------------------------
*/
$accounts = [];

$accQuery = $conn->query("
SELECT DISTINCT

account_code,
account_name

FROM general_ledger

ORDER BY account_code
");

while($row = $accQuery->fetch_assoc())
{
    $accounts[] = $row;
}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>
General Ledger
</title>

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{
background:#f5f7fb;
}

.card-box{
background:#fff;
padding:20px;
border-radius:15px;
box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.metric{
font-size:28px;
font-weight:700;
}

.balance-ok{
color:green;
font-weight:bold;
}

.balance-error{
color:red;
font-weight:bold;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

General Ledger

</h2>

<!-- SUMMARY -->

<div class="row g-3 mb-4">

<div class="col-md-4">

<div class="card-box">

<div class="metric">

TZS <?= number_format($totalDebit,2) ?>

</div>

Total Debits

</div>

</div>

<div class="col-md-4">

<div class="card-box">

<div class="metric">

TZS <?= number_format($totalCredit,2) ?>

</div>

Total Credits

</div>

</div>

<div class="col-md-4">

<div class="card-box">

<div class="metric">

<?= number_format($totalRows) ?>

</div>

Transactions

</div>

</div>

</div>

<!-- BALANCE STATUS -->

<div class="card-box mb-4">

<h5>

Ledger Status:

<?php if(abs($totalDebit - $totalCredit) < 0.01): ?>

<span class="balance-ok">

Balanced

</span>

<?php else: ?>

<span class="balance-error">

Out Of Balance

(TZS <?= number_format(abs($totalDebit-$totalCredit),2) ?>)

</span>

<?php endif; ?>

</h5>

</div>

<!-- FILTERS -->

<div class="card-box mb-4">

<form method="GET">

<div class="row">

<div class="col-md-3">

<label>Account</label>

<select
name="account"
class="form-select">

<option value="">
All Accounts
</option>

<?php foreach($accounts as $acc): ?>

<option
value="<?= $acc['account_code'] ?>">

<?= $acc['account_code'] ?>

-

<?= htmlspecialchars(
$acc['account_name']
) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-3">

<label>From</label>

<input
type="date"
name="from"
value="<?= htmlspecialchars($from) ?>"
class="form-control">

</div>

<div class="col-md-3">

<label>To</label>

<input
type="date"
name="to"
value="<?= htmlspecialchars($to) ?>"
class="form-control">

</div>

<div class="col-md-2">

<label>&nbsp;</label>

<button
class="btn btn-primary w-100">

Filter

</button>

</div>

</div>

</form>

</div>

<!-- LEDGER TABLE -->

<div class="card-box">

<div class="table-responsive">

<table class="table table-bordered table-hover">

<thead>

<tr>

<th>Date</th>
<th>Account</th>
<th>Description</th>
<th>Reference</th>
<th>Debit</th>
<th>Credit</th>

</tr>

</thead>

<tbody>

<?php while($row = $entries->fetch_assoc()): ?>

<tr>

<td>

<?= date(
'd M Y H:i',
strtotime(
$row['transaction_date']
)
) ?>

</td>

<td>

<strong>

<?= htmlspecialchars(
$row['account_code']
) ?>

</strong>

<br>

<?= htmlspecialchars(
$row['account_name']
) ?>

</td>

<td>

<?= htmlspecialchars(
$row['description']
) ?>

</td>

<td>

<?= htmlspecialchars(
$row['reference_type']
) ?>

#

<?= $row['reference_id'] ?>

</td>

<td>

<?php if($row['debit'] > 0): ?>

TZS <?= number_format($row['debit'],2) ?>

<?php endif; ?>

</td>

<td>

<?php if($row['credit'] > 0): ?>

TZS <?= number_format($row['credit'],2) ?>

<?php endif; ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<!-- PAGINATION -->

<nav class="mt-4">

<ul class="pagination">

<?php for($i=1;$i<=$totalPages;$i++): ?>

<li class="page-item <?= $page==$i?'active':'' ?>">

<a
class="page-link"
href="?page=<?= $i ?>">

<?= $i ?>

</a>

</li>

<?php endfor; ?>

</ul>

</nav>

</div>

</body>
</html>