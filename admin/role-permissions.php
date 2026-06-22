<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['admin']);

/*
|--------------------------------------------------------------------------
| ASSIGN PERMISSION
|--------------------------------------------------------------------------
*/
if(isset($_POST['assign']))
{
    $roleId =
    (int)$_POST['role_id'];

    $permissionId =
    (int)$_POST['permission_id'];

    $stmt = $conn->prepare("
    INSERT IGNORE INTO
    role_permissions(
        role_id,
        permission_id
    )
    VALUES(?,?)
    ");

    $stmt->bind_param(
        "ii",
        $roleId,
        $permissionId
    );

    $stmt->execute();

    header(
        "Location: role-permissions.php"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| REMOVE
|--------------------------------------------------------------------------
*/
if(isset($_GET['remove']))
{
    $id =
    (int)$_GET['remove'];

    $stmt = $conn->prepare("
    DELETE FROM role_permissions
    WHERE id=?
    ");

    $stmt->bind_param(
        "i",
        $id
    );

    $stmt->execute();

    header(
        "Location: role-permissions.php"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| DATA
|--------------------------------------------------------------------------
*/
$roles =
$conn->query("
SELECT *
FROM roles
ORDER BY name
");

$permissions =
$conn->query("
SELECT *
FROM permissions
ORDER BY module,permission_name
");

$assignments =
$conn->query("
SELECT

rp.id,

r.name role_name,

p.permission_name,

p.permission_key,

p.module

FROM role_permissions rp

INNER JOIN roles r
ON r.id=rp.role_id

INNER JOIN permissions p
ON p.id=rp.permission_id

ORDER BY r.name,p.module
");

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>
Roles & Permissions
</title>

<meta
name="viewport"
content="width=device-width,initial-scale=1">

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

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Role & Permission Management

</h2>

<!-- ASSIGN -->

<div class="card-box">

<h5>

Assign Permission

</h5>

<form method="POST">

<div class="row">

<div class="col-md-4">

<select
name="role_id"
class="form-select"
required>

<option value="">
Select Role
</option>

<?php
$roles->data_seek(0);

while(
$role=
$roles->fetch_assoc()
):
?>

<option
value="<?= $role['id'] ?>">

<?= ucfirst(
$role['name']
) ?>

</option>

<?php endwhile; ?>

</select>

</div>

<div class="col-md-5">

<select
name="permission_id"
class="form-select"
required>

<option value="">
Select Permission
</option>

<?php
$permissions->data_seek(0);

while(
$permission=
$permissions->fetch_assoc()
):
?>

<option
value="<?= $permission['id'] ?>">

<?= $permission['module'] ?>

-

<?= $permission['permission_name'] ?>

</option>

<?php endwhile; ?>

</select>

</div>

<div class="col-md-3">

<button
name="assign"
class="btn btn-primary w-100">

Assign

</button>

</div>

</div>

</form>

</div>

<!-- ASSIGNMENTS -->

<div class="card-box">

<h5>

Current Permissions

</h5>

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Role</th>
<th>Module</th>
<th>Permission</th>
<th>Key</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php while(
$item=
$assignments->fetch_assoc()
): ?>

<tr>

<td>

<span class="badge bg-dark">

<?= ucfirst(
$item['role_name']
) ?>

</span>

</td>

<td>

<?= htmlspecialchars(
$item['module']
) ?>

</td>

<td>

<?= htmlspecialchars(
$item['permission_name']
) ?>

</td>

<td>

<code>

<?= htmlspecialchars(
$item['permission_key']
) ?>

</code>

</td>

<td>

<a
href="?remove=<?= $item['id'] ?>"
class="btn btn-sm btn-danger"
onclick="return confirm('Remove permission?')">

Remove

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