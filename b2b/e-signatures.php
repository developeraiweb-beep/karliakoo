<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['b2b']);

$userId = $_SESSION['user_id'];

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| CONTRACTS NEEDING SIGNATURE
|--------------------------------------------------------------------------
*/
$contracts = $conn->prepare("
SELECT
id,
contract_number,
contract_title,
status

FROM b2b_contracts

WHERE buyer_id=?
ORDER BY id DESC
");

$contracts->bind_param(
    "i",
    $userId
);

$contracts->execute();

$contracts =
$contracts
->get_result();

/*
|--------------------------------------------------------------------------
| SIGN CONTRACT
|--------------------------------------------------------------------------
*/
if(isset($_POST['sign_contract']))
{
    $contractId =
    (int)$_POST['contract_id'];

    $signerName =
    trim($_POST['signer_name']);

    $signerEmail =
    trim($_POST['signer_email']);

    $signerRole =
    trim($_POST['signer_role']);

    $ip =
    $_SERVER['REMOTE_ADDR'];

    if(
        empty($_FILES['signature']['name'])
    )
    {
        $error =
        "Please upload a signature.";
    }
    else
    {
        $dir =
        "../uploads/signatures/";

        if(!is_dir($dir))
        {
            mkdir(
                $dir,
                0777,
                true
            );
        }

        $file =
        time().'_'.
        basename(
            $_FILES['signature']['name']
        );

        $path =
        $dir.$file;

        if(
            move_uploaded_file(
                $_FILES['signature']['tmp_name'],
                $path
            )
        )
        {
            $stmt =
            $conn->prepare("
            INSERT INTO
            b2b_contract_signatures(

                contract_id,
                user_id,

                signer_name,
                signer_email,
                signer_role,

                signature_file,

                signed_at,

                ip_address,

                status

            )

            VALUES(
                ?,?,?,?,?,?,
                NOW(),
                ?,
                'signed'
            )
            ");

            $stmt->bind_param(
                "iisssss",
                $contractId,
                $userId,
                $signerName,
                $signerEmail,
                $signerRole,
                $path,
                $ip
            );

            $stmt->execute();

            /*
            ----------------------------------------
            AUDIT LOG
            ----------------------------------------
            */

            $log =
            $conn->prepare("
            INSERT INTO
            b2b_signature_logs(

                contract_id,
                user_id,

                action,
                ip_address

            )

            VALUES(
                ?,
                ?,
                'contract_signed',
                ?
            )
            ");

            $log->bind_param(
                "iis",
                $contractId,
                $userId,
                $ip
            );

            $log->execute();

            $message =
            "Contract signed successfully.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| SIGNATURE HISTORY
|--------------------------------------------------------------------------
*/
$history =
$conn->prepare("
SELECT

s.*,

c.contract_number,
c.contract_title

FROM
b2b_contract_signatures s

INNER JOIN b2b_contracts c
ON c.id=s.contract_id

WHERE s.user_id=?

ORDER BY s.id DESC
");

$history->bind_param(
    "i",
    $userId
);

$history->execute();

$history =
$history->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>E-Signatures</title>

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

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Electronic Signatures

</h2>

<?php if($message): ?>
<div class="alert alert-success">
<?= $message ?>
</div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger">
<?= $error ?>
</div>
<?php endif; ?>

<!-- SIGN CONTRACT -->

<div class="card-box">

<h5>

Sign Contract

</h5>

<form
method="POST"
enctype="multipart/form-data">

<div class="row g-3">

<div class="col-md-4">

<select
name="contract_id"
class="form-select"
required>

<option value="">
Select Contract
</option>

<?php while(
$contract =
$contracts->fetch_assoc()
): ?>

<option
value="<?= $contract['id'] ?>">

<?= htmlspecialchars(
$contract['contract_number']
) ?>

-

<?= htmlspecialchars(
$contract['contract_title']
) ?>

</option>

<?php endwhile; ?>

</select>

</div>

<div class="col-md-4">

<input
type="text"
name="signer_name"
class="form-control"
placeholder="Signer Name"
required>

</div>

<div class="col-md-4">

<input
type="email"
name="signer_email"
class="form-control"
placeholder="Signer Email"
required>

</div>

<div class="col-md-4">

<input
type="text"
name="signer_role"
class="form-control"
placeholder="Role / Position">

</div>

<div class="col-md-4">

<input
type="file"
name="signature"
accept=".png,.jpg,.jpeg"
class="form-control"
required>

</div>

<div class="col-md-4">

<button
name="sign_contract"
class="btn btn-success">

Sign Contract

</button>

</div>

</div>

</form>

</div>

<!-- SIGNATURE HISTORY -->

<div class="card-box">

<h5>

Signed Contracts

</h5>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Contract</th>
<th>Signer</th>
<th>Status</th>
<th>Date</th>
<th>Signature</th>

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
$row['contract_number']
) ?>

<br>

<small>

<?= htmlspecialchars(
$row['contract_title']
) ?>

</small>

</td>

<td>

<?= htmlspecialchars(
$row['signer_name']
) ?>

<br>

<small>

<?= htmlspecialchars(
$row['signer_email']
) ?>

</small>

</td>

<td>

<span class="badge bg-success">

<?= ucfirst(
$row['status']
) ?>

</span>

</td>

<td>

<?= date(
'd M Y H:i',
strtotime(
$row['signed_at']
)
) ?>

</td>

<td>

<a
href="<?= $row['signature_file'] ?>"
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

</div>

</body>
</html>