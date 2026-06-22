<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole(['b2b']);

$userId = $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| CREATE CONTACT
|--------------------------------------------------------------------------
*/
if(isset($_POST['add_contact']))
{
    $companyName = trim($_POST['company_name']);
    $contactPerson = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $country = trim($_POST['country']);
    $city = trim($_POST['city']);
    $category = trim($_POST['category']);
    $notes = trim($_POST['notes']);

    $stmt = $conn->prepare("
    INSERT INTO b2b_contacts(
        user_id,
        company_name,
        contact_person,
        email,
        phone,
        country,
        city,
        category,
        notes
    )
    VALUES(?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "issssssss",
        $userId,
        $companyName,
        $contactPerson,
        $email,
        $phone,
        $country,
        $city,
        $category,
        $notes
    );

    $stmt->execute();

    header("Location: contacts.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE CONTACT
|--------------------------------------------------------------------------
*/
if(isset($_GET['delete']))
{
    $contactId = (int)$_GET['delete'];

    $stmt = $conn->prepare("
    DELETE FROM b2b_contacts
    WHERE id=?
    AND user_id=?
    ");

    $stmt->bind_param(
        "ii",
        $contactId,
        $userId
    );

    $stmt->execute();

    header("Location: contacts.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? '');

$where = "user_id=?";
$params = [$userId];
$types = "i";

if(!empty($search))
{
    $where .= "
    AND (
        company_name LIKE ?
        OR contact_person LIKE ?
        OR email LIKE ?
    )
    ";

    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";

    $types .= "sss";
}

/*
|--------------------------------------------------------------------------
| CONTACTS
|--------------------------------------------------------------------------
*/
$sql = "
SELECT *
FROM b2b_contacts
WHERE {$where}
ORDER BY id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

$contacts = $stmt->get_result();

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
) active_contacts

FROM b2b_contacts

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

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">

<title>B2B Contacts</title>

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
font-size:28px;
font-weight:700;
}

</style>

</head>

<body>

<div class="container-fluid py-4">

<h2 class="mb-4">

Business Contacts

</h2>

<!-- STATS -->

<div class="row mb-4">

<div class="col-md-6">

<div class="card-box text-center">

<div class="metric">

<?= number_format($stats['total']) ?>

</div>

Total Contacts

</div>

</div>

<div class="col-md-6">

<div class="card-box text-center">

<div class="metric text-success">

<?= number_format($stats['active_contacts']) ?>

</div>

Active Contacts

</div>

</div>

</div>

<!-- ADD CONTACT -->

<div class="card-box">

<h5>Add Contact</h5>

<form method="POST">

<div class="row g-3">

<div class="col-md-4">
<input
type="text"
name="company_name"
class="form-control"
placeholder="Company Name"
required>
</div>

<div class="col-md-4">
<input
type="text"
name="contact_person"
class="form-control"
placeholder="Contact Person"
required>
</div>

<div class="col-md-4">
<input
type="email"
name="email"
class="form-control"
placeholder="Email">
</div>

<div class="col-md-3">
<input
type="text"
name="phone"
class="form-control"
placeholder="Phone">
</div>

<div class="col-md-3">
<input
type="text"
name="country"
class="form-control"
placeholder="Country">
</div>

<div class="col-md-3">
<input
type="text"
name="city"
class="form-control"
placeholder="City">
</div>

<div class="col-md-3">
<input
type="text"
name="category"
class="form-control"
placeholder="Category">
</div>

<div class="col-md-12">
<textarea
name="notes"
class="form-control"
rows="3"
placeholder="Notes"></textarea>
</div>

<div class="col-md-3">
<button
name="add_contact"
class="btn btn-primary">

Save Contact

</button>
</div>

</div>

</form>

</div>

<!-- SEARCH -->

<div class="card-box">

<form method="GET">

<div class="row">

<div class="col-md-4">

<input
type="text"
name="search"
value="<?= htmlspecialchars($search) ?>"
class="form-control"
placeholder="Search Contacts">

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

<!-- CONTACTS -->

<div class="card-box">

<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

<th>Company</th>
<th>Contact</th>
<th>Email</th>
<th>Phone</th>
<th>Category</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php while(
$contact =
$contacts->fetch_assoc()
): ?>

<tr>

<td>

<?= htmlspecialchars(
$contact['company_name']
) ?>

</td>

<td>

<?= htmlspecialchars(
$contact['contact_person']
) ?>

</td>

<td>

<?= htmlspecialchars(
$contact['email']
) ?>

</td>

<td>

<?= htmlspecialchars(
$contact['phone']
) ?>

</td>

<td>

<?= htmlspecialchars(
$contact['category']
) ?>

</td>

<td>

<span class="badge bg-success">

<?= ucfirst(
$contact['status']
) ?>

</span>

</td>

<td>

<a
href="contact-details.php?id=<?= $contact['id'] ?>"
class="btn btn-sm btn-primary">

View

</a>

<a
href="?delete=<?= $contact['id'] ?>"
class="btn btn-sm btn-danger"
onclick="return confirm('Delete contact?')">

Delete

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