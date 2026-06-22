<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['b2b']);

$userId = (int)$_SESSION['user_id'];

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| SEND CONNECTION REQUEST
|--------------------------------------------------------------------------
*/
if(isset($_POST['connect']))
{
    $receiverId =
    (int)$_POST['receiver_id'];

    $relationship =
    trim($_POST['relationship_type']);

    $message =
    trim($_POST['message']);

    if($receiverId != $userId)
    {
        $check = $conn->prepare("
        SELECT id
        FROM b2b_connections
        WHERE sender_id=?
        AND receiver_id=?
        LIMIT 1
        ");

        $check->bind_param(
            "ii",
            $userId,
            $receiverId
        );

        $check->execute();

        if(
            $check
            ->get_result()
            ->num_rows == 0
        )
        {
            $insert =
            $conn->prepare("
            INSERT INTO b2b_connections(
                sender_id,
                receiver_id,
                relationship_type,
                message
            )
            VALUES(?,?,?,?)
            ");

            $insert->bind_param(
                "iiss",
                $userId,
                $receiverId,
                $relationship,
                $message
            );

            $insert->execute();

            $success =
            "Connection request sent.";
        }
        else
        {
            $error =
            "Connection already exists.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| COMPANY SEARCH
|--------------------------------------------------------------------------
*/
$keyword =
trim($_GET['keyword'] ?? '');

$sql = "
SELECT *
FROM b2b_companies
WHERE user_id != ?
";

$params = [$userId];
$types = "i";

if(!empty($keyword))
{
    $sql .= "
    AND (
        company_name LIKE ?
        OR industry LIKE ?
        OR country LIKE ?
    )
    ";

    $search = "%{$keyword}%";

    $params[] = $search;
    $params[] = $search;
    $params[] = $search;

    $types .= "sss";
}

$sql .= "
ORDER BY verification_status DESC,
company_name ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types,...$params);
$stmt->execute();

$companies =
$stmt->get_result();

/*
|--------------------------------------------------------------------------
| MY CONNECTIONS
|--------------------------------------------------------------------------
*/
$connections =
$conn->prepare("
SELECT
c.*,
cp.company_name

FROM b2b_connections c

LEFT JOIN b2b_companies cp
ON cp.user_id = c.receiver_id

WHERE c.sender_id=?

ORDER BY c.id DESC
");

$connections->bind_param(
    "i",
    $userId
);

$connections->execute();

$connections =
$connections
->get_result();

/*
|--------------------------------------------------------------------------
| NETWORK STATS
|--------------------------------------------------------------------------
*/
$stats =
$conn->prepare("
SELECT

COUNT(*) total

FROM b2b_connections

WHERE sender_id=?
AND status='accepted'
");

$stats->bind_param(
    "i",
    $userId
);

$stats->execute();

$totalConnections =
$stats
->get_result()
->fetch_assoc()['total'];

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>Business Network</title>

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
background:#fff;
padding:20px;
border-radius:15px;
margin-bottom:20px;
box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.metric{
font-size:32px;
font-weight:bold;
}

.company-card{
border:1px solid #eee;
padding:15px;
border-radius:10px;
margin-bottom:15px;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Business Network

</h2>

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

<!-- KPI -->

<div class="row mb-4">

<div class="col-md-4">

<div class="card-box text-center">

<div class="metric">

<?= number_format(
$totalConnections
) ?>

</div>

Business Connections

</div>

</div>

</div>

<!-- SEARCH -->

<div class="card-box">

<form method="GET">

<div class="row">

<div class="col-md-6">

<input
type="text"
name="keyword"
class="form-control"
placeholder="Search companies, industries, countries"
value="<?= htmlspecialchars($keyword) ?>">

</div>

<div class="col-md-2">

<button
class="btn btn-primary">

Search

</button>

</div>

</div>

</form>

</div>

<!-- COMPANY DIRECTORY -->

<div class="card-box">

<h5>

Supplier & Partner Directory

</h5>

<?php while(
$company =
$companies->fetch_assoc()
): ?>

<div class="company-card">

<div class="row">

<div class="col-md-8">

<h5>

<?= htmlspecialchars(
$company['company_name']
) ?>

</h5>

<p>

<?= htmlspecialchars(
$company['description']
)
?>

</p>

<p>

<strong>Industry:</strong>

<?= htmlspecialchars(
$company['industry']
)
?>

<br>

<strong>Location:</strong>

<?= htmlspecialchars(
$company['city']
)
?>

,

<?= htmlspecialchars(
$company['country']
)
?>

</p>

</div>

<div class="col-md-4">

<span class="badge bg-<?=
$company['verification_status']
==
'verified'
?
'success'
:
'warning'
?>">

<?= ucfirst(
$company['verification_status']
)
?>

</span>

<form
method="POST"
class="mt-3">

<input
type="hidden"
name="receiver_id"
value="<?= $company['user_id'] ?>">

<select
name="relationship_type"
class="form-select mb-2">

<option value="supplier">
Supplier
</option>

<option value="partner">
Partner
</option>

<option value="distributor">
Distributor
</option>

<option value="service_provider">
Service Provider
</option>

</select>

<textarea
name="message"
class="form-control mb-2"
rows="2"
placeholder="Message"></textarea>

<button
name="connect"
class="btn btn-success btn-sm">

Connect

</button>

</form>

</div>

</div>

</div>

<?php endwhile; ?>

</div>

<!-- MY CONNECTIONS -->

<div class="card-box">

<h5>

My Network

</h5>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Company</th>
<th>Relationship</th>
<th>Status</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php while(
$row =
$connections->fetch_assoc()
): ?>

<tr>

<td>

<?= htmlspecialchars(
$row['company_name']
?? 'Unknown Company'
) ?>

</td>

<td>

<?= ucfirst(
str_replace(
'_',
' ',
$row['relationship_type']
)
) ?>

</td>

<td>

<span class="badge bg-<?=
$row['status']=='accepted'
?
'success'
:
(
$row['status']=='pending'
?
'warning'
:
'danger'
)
?>">

<?= ucfirst(
$row['status']
) ?>

</span>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$row['created_at']
)
) ?>

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