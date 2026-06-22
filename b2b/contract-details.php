<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['b2b']);

$userId = $_SESSION['user_id'];

$contractId = (int)($_GET['id'] ?? 0);

if($contractId <= 0)
{
    header("Location: contracts.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CONTRACT
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
SELECT *
FROM b2b_contracts
WHERE id=?
AND buyer_id=?
LIMIT 1
");

$stmt->bind_param(
    "ii",
    $contractId,
    $userId
);

$stmt->execute();

$contract =
$stmt
->get_result()
->fetch_assoc();

if(!$contract)
{
    die("Contract not found.");
}

/*
|--------------------------------------------------------------------------
| CHANGE STATUS
|--------------------------------------------------------------------------
*/
if(isset($_POST['change_status']))
{
    $status = $_POST['status'];

    $allowed = [
        'draft',
        'pending',
        'active',
        'expired',
        'terminated'
    ];

    if(in_array($status,$allowed))
    {
        $update = $conn->prepare("
        UPDATE b2b_contracts
        SET status=?
        WHERE id=?
        ");

        $update->bind_param(
            "si",
            $status,
            $contractId
        );

        $update->execute();

        $log = $conn->prepare("
        INSERT INTO b2b_contract_history(
            contract_id,
            action,
            performed_by
        )
        VALUES(
            ?,
            ?,
            ?
        )
        ");

        $action =
        "status_changed_to_".$status;

        $log->bind_param(
            "isi",
            $contractId,
            $action,
            $userId
        );

        $log->execute();
    }

    header(
        "Location: contract-details.php?id=".$contractId
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| FILE UPLOAD
|--------------------------------------------------------------------------
*/
$message = '';

if(isset($_POST['upload_file']))
{
    if(
        !empty($_FILES['document']['name'])
    )
    {
        $dir =
        "../uploads/contracts/";

        if(!is_dir($dir))
        {
            mkdir(
                $dir,
                0777,
                true
            );
        }

        $fileName =
        time().'_'.
        basename(
            $_FILES['document']['name']
        );

        $target =
        $dir.$fileName;

        if(
            move_uploaded_file(
                $_FILES['document']['tmp_name'],
                $target
            )
        )
        {
            $insert =
            $conn->prepare("
            INSERT INTO
            b2b_contract_files(
                contract_id,
                file_name,
                file_path,
                uploaded_by
            )
            VALUES(?,?,?,?)
            ");

            $insert->bind_param(
                "issi",
                $contractId,
                $fileName,
                $target,
                $userId
            );

            $insert->execute();

            $message =
            "File uploaded successfully.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| FILES
|--------------------------------------------------------------------------
*/
$files = $conn->prepare("
SELECT *
FROM b2b_contract_files
WHERE contract_id=?
ORDER BY id DESC
");

$files->bind_param(
    "i",
    $contractId
);

$files->execute();

$files =
$files->get_result();

/*
|--------------------------------------------------------------------------
| HISTORY
|--------------------------------------------------------------------------
*/
$history = $conn->prepare("
SELECT *
FROM b2b_contract_history
WHERE contract_id=?
ORDER BY id DESC
");

$history->bind_param(
    "i",
    $contractId
);

$history->execute();

$history =
$history->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>Contract Details</title>

<meta name="viewport"
content="width=device-width,initial-scale=1">

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
margin-bottom:20px;
border-radius:15px;
box-shadow:0 2px 10px rgba(0,0,0,.08);
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between mb-4">

<h2>

Contract Details

</h2>

<a
href="contracts.php"
class="btn btn-secondary">

Back

</a>

</div>

<?php if($message): ?>

<div class="alert alert-success">

<?= $message ?>

</div>

<?php endif; ?>

<!-- CONTRACT -->

<div class="card-box">

<h4>

<?= htmlspecialchars(
$contract['contract_title']
) ?>

</h4>

<hr>

<div class="row">

<div class="col-md-6">

<p>
<strong>Contract Number:</strong>
<?= htmlspecialchars($contract['contract_number']) ?>
</p>

<p>
<strong>Type:</strong>
<?= ucfirst($contract['contract_type']) ?>
</p>

<p>
<strong>Status:</strong>
<?= ucfirst($contract['status']) ?>
</p>

<p>
<strong>Supplier ID:</strong>
<?= $contract['supplier_id'] ?>
</p>

</div>

<div class="col-md-6">

<p>
<strong>Start Date:</strong>
<?= $contract['start_date'] ?>
</p>

<p>
<strong>End Date:</strong>
<?= $contract['end_date'] ?>
</p>

<p>
<strong>Value:</strong>
TZS <?= number_format($contract['contract_value'],2) ?>
</p>

</div>

</div>

<div class="mt-3">

<strong>Notes</strong>

<p>

<?= nl2br(
htmlspecialchars(
$contract['notes']
)
) ?>

</p>

</div>

</div>

<!-- STATUS -->

<div class="card-box">

<h5>

Contract Status

</h5>

<form method="POST">

<div class="row">

<div class="col-md-4">

<select
name="status"
class="form-select">

<option value="draft">Draft</option>
<option value="pending">Pending</option>
<option value="active">Active</option>
<option value="expired">Expired</option>
<option value="terminated">Terminated</option>

</select>

</div>

<div class="col-md-3">

<button
name="change_status"
class="btn btn-primary">

Update Status

</button>

</div>

</div>

</form>

</div>

<!-- FILES -->

<div class="card-box">

<h5>

Contract Documents

</h5>

<form
method="POST"
enctype="multipart/form-data">

<div class="row">

<div class="col-md-6">

<input
type="file"
name="document"
class="form-control"
required>

</div>

<div class="col-md-3">

<button
name="upload_file"
class="btn btn-success">

Upload

</button>

</div>

</div>

</form>

<hr>

<table class="table table-hover">

<thead>

<tr>
<th>File</th>
<th>Date</th>
<th>Action</th>
</tr>

</thead>

<tbody>

<?php while(
$file =
$files->fetch_assoc()
): ?>

<tr>

<td>

<?= htmlspecialchars(
$file['file_name']
) ?>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$file['created_at']
)
) ?>

</td>

<td>

<a
href="<?= $file['file_path'] ?>"
target="_blank"
class="btn btn-sm btn-primary">

Download

</a>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

<!-- HISTORY -->

<div class="card-box">

<h5>

Contract Activity

</h5>

<table class="table table-striped">

<thead>

<tr>
<th>Action</th>
<th>User</th>
<th>Date</th>
</tr>

</thead>

<tbody>

<?php while(
$row =
$history->fetch_assoc()
): ?>

<tr>

<td>

<?= htmlspecialchars(
$row['action']
) ?>

</td>

<td>

#<?= $row['performed_by'] ?>

</td>

<td>

<?= date(
'd M Y H:i',
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

</body>
</html>