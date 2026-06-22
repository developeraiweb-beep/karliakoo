<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['b2b']);

$userId = $_SESSION['user_id'];

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| UPLOAD DOCUMENT
|--------------------------------------------------------------------------
*/
if(isset($_POST['upload_document']))
{
    $documentType =
    trim($_POST['document_type']);

    $documentName =
    trim($_POST['document_name']);

    $expiryDate =
    $_POST['expiry_date'];

    if(
        !empty($_FILES['document']['name'])
    )
    {
        $uploadDir =
        "../uploads/compliance/";

        if(!is_dir($uploadDir))
        {
            mkdir(
                $uploadDir,
                0777,
                true
            );
        }

        $fileName =
        time().'_'.
        basename(
            $_FILES['document']['name']
        );

        $filePath =
        $uploadDir.$fileName;

        if(
            move_uploaded_file(
                $_FILES['document']['tmp_name'],
                $filePath
            )
        )
        {
            $stmt =
            $conn->prepare("
            INSERT INTO
            b2b_compliance_documents(

                user_id,
                document_type,
                document_name,
                file_path,
                expiry_date

            )
            VALUES(
                ?,?,?,?,?
            )
            ");

            $stmt->bind_param(
                "issss",
                $userId,
                $documentType,
                $documentName,
                $filePath,
                $expiryDate
            );

            $stmt->execute();

            $success =
            "Document uploaded successfully.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| COMPLIANCE STATS
|--------------------------------------------------------------------------
*/
$stats =
$conn->prepare("
SELECT

COUNT(*) total_documents,

SUM(
CASE
WHEN status='approved'
THEN 1
ELSE 0
END
) approved_documents,

SUM(
CASE
WHEN status='pending'
THEN 1
ELSE 0
END
) pending_documents,

SUM(
CASE
WHEN status='expired'
THEN 1
ELSE 0
END
) expired_documents

FROM b2b_compliance_documents

WHERE user_id=?
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

/*
|--------------------------------------------------------------------------
| DOCUMENTS
|--------------------------------------------------------------------------
*/
$documents =
$conn->prepare("
SELECT *
FROM b2b_compliance_documents
WHERE user_id=?
ORDER BY id DESC
");

$documents->bind_param(
    "i",
    $userId
);

$documents->execute();

$documents =
$documents
->get_result();

/*
|--------------------------------------------------------------------------
| INCIDENTS
|--------------------------------------------------------------------------
*/
$incidents =
$conn->prepare("
SELECT *
FROM b2b_compliance_incidents
WHERE user_id=?
ORDER BY id DESC
LIMIT 20
");

$incidents->bind_param(
    "i",
    $userId
);

$incidents->execute();

$incidents =
$incidents
->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>Compliance Center</title>

<meta
name="viewport"
content="width=device-width,initial-scale=1">

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
margin-bottom:20px;
border-radius:15px;
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

Compliance Center

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

<!-- DASHBOARD -->

<div class="row g-3 mb-4">

<div class="col-md-3">

<div class="card-box text-center">

<div class="metric">

<?= number_format(
$stats['total_documents']
) ?>

</div>

Documents

</div>

</div>

<div class="col-md-3">

<div class="card-box text-center">

<div class="metric text-success">

<?= number_format(
$stats['approved_documents']
) ?>

</div>

Approved

</div>

</div>

<div class="col-md-3">

<div class="card-box text-center">

<div class="metric text-warning">

<?= number_format(
$stats['pending_documents']
) ?>

</div>

Pending

</div>

</div>

<div class="col-md-3">

<div class="card-box text-center">

<div class="metric text-danger">

<?= number_format(
$stats['expired_documents']
) ?>

</div>

Expired

</div>

</div>

</div>

<!-- DOCUMENT UPLOAD -->

<div class="card-box">

<h5>

Upload Compliance Document

</h5>

<form
method="POST"
enctype="multipart/form-data">

<div class="row g-3">

<div class="col-md-3">

<select
name="document_type"
class="form-select"
required>

<option value="">
Select Type
</option>

<option value="business_license">
Business License
</option>

<option value="tax_certificate">
Tax Certificate
</option>

<option value="vat_certificate">
VAT Certificate
</option>

<option value="kyc_document">
KYC Document
</option>

<option value="incorporation_certificate">
Certificate of Incorporation
</option>

<option value="bank_verification">
Bank Verification
</option>

<option value="insurance_certificate">
Insurance Certificate
</option>

</select>

</div>

<div class="col-md-3">

<input
type="text"
name="document_name"
class="form-control"
placeholder="Document Name"
required>

</div>

<div class="col-md-3">

<input
type="date"
name="expiry_date"
class="form-control">

</div>

<div class="col-md-3">

<input
type="file"
name="document"
class="form-control"
required>

</div>

<div class="col-md-3">

<button
name="upload_document"
class="btn btn-primary">

Upload

</button>

</div>

</div>

</form>

</div>

<!-- DOCUMENTS -->

<div class="card-box">

<h5>

Compliance Documents

</h5>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Type</th>
<th>Name</th>
<th>Status</th>
<th>Expiry</th>
<th>File</th>

</tr>

</thead>

<tbody>

<?php while(
$doc =
$documents->fetch_assoc()
): ?>

<tr>

<td>

<?= ucfirst(
str_replace(
'_',
' ',
$doc['document_type']
)
) ?>

</td>

<td>

<?= htmlspecialchars(
$doc['document_name']
) ?>

</td>

<td>

<span class="badge bg-<?=
$doc['status']=='approved'
?
'success'
:
(
$doc['status']=='pending'
?
'warning'
:
'danger'
)
?>">

<?= ucfirst(
$doc['status']
) ?>

</span>

</td>

<td>

<?= $doc['expiry_date'] ?>

</td>

<td>

<a
href="<?= $doc['file_path'] ?>"
target="_blank"
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

<!-- INCIDENTS -->

<div class="card-box">

<h5>

Compliance Incidents

</h5>

<table class="table table-striped">

<thead>

<tr>

<th>Severity</th>
<th>Issue</th>
<th>Status</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php while(
$incident =
$incidents->fetch_assoc()
): ?>

<tr>

<td>

<span class="badge bg-<?=
$incident['severity']=='critical'
?
'danger'
:
(
$incident['severity']=='high'
?
'warning'
:
'secondary'
)
?>">

<?= ucfirst(
$incident['severity']
) ?>

</span>

</td>

<td>

<?= htmlspecialchars(
$incident['title']
) ?>

</td>

<td>

<?= ucfirst(
$incident['status']
) ?>

</td>

<td>

<?= date(
'd M Y',
strtotime(
$incident['created_at']
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