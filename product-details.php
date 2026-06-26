<?php

declare(strict_types=1);

session_start();

require_once "config/db.php";

$productId =
(int)($_GET['id'] ?? 0);

if($productId <= 0)
{
    die("Invalid product");
}

/*
|--------------------------------------------------------------------------
| LOAD PRODUCT
|--------------------------------------------------------------------------
*/

$stmt =
$conn->prepare("
SELECT

p.*,

s.shop_name,
s.logo,
s.shop_slug,
s.verified,

c.category_name

FROM products p

LEFT JOIN shops s
ON s.id = p.shop_id

LEFT JOIN categories c
ON c.id = p.category_id

WHERE

p.id=?

AND p.approved=1

AND p.status='active'

LIMIT 1
");

$stmt->bind_param(
"i",
$productId
);

$stmt->execute();

$product =
$stmt
->get_result()
->fetch_assoc();

if(!$product)
{
    die("Product not found");
}

/*
|--------------------------------------------------------------------------
| INCREMENT VIEWS
|--------------------------------------------------------------------------
*/

$conn->query("
UPDATE products
SET views = views + 1
WHERE id = {$productId}
");

/*
|--------------------------------------------------------------------------
| RECENTLY VIEWED
|--------------------------------------------------------------------------
*/

if(
!isset(
$_SESSION['recently_viewed']
)
)
{
$_SESSION['recently_viewed'] = [];
}

$_SESSION['recently_viewed'][] =
$productId;

$_SESSION['recently_viewed'] =
array_unique(
$_SESSION['recently_viewed']
);

$_SESSION['recently_viewed'] =
array_slice(
$_SESSION['recently_viewed'],
-20
);

/*
|--------------------------------------------------------------------------
| MAIN IMAGE
|--------------------------------------------------------------------------
*/

$image =
$product['featured_image']
?: $product['image'];

if(empty($image))
{
$image =
"assets/images/no-image.jpg";
}

/*
|--------------------------------------------------------------------------
| GALLERY
|--------------------------------------------------------------------------
*/

$gallery =
$conn->query("
SELECT *
FROM product_images
WHERE product_id={$productId}
");

/*
|--------------------------------------------------------------------------
| VARIANTS
|--------------------------------------------------------------------------
*/

$variants =
$conn->query("
SELECT *
FROM product_variants
WHERE product_id={$productId}
");

/*
|--------------------------------------------------------------------------
| SPECIFICATIONS
|--------------------------------------------------------------------------
*/

$specifications =
$conn->query("
SELECT *
FROM product_specifications
WHERE product_id={$productId}
");

/*
|--------------------------------------------------------------------------
| REVIEWS
|--------------------------------------------------------------------------
*/

$reviews =
$conn->query("
SELECT

r.*,
u.full_name

FROM reviews r

LEFT JOIN users u
ON u.id=r.user_id

WHERE r.product_id={$productId}

ORDER BY r.id DESC
");

/*
|--------------------------------------------------------------------------
| REVIEW SUMMARY
|--------------------------------------------------------------------------
*/

$ratingSummary =
$conn->query("
SELECT

COUNT(*) total_reviews,

IFNULL(
AVG(rating),
0
) avg_rating

FROM reviews

WHERE product_id={$productId}
")->fetch_assoc();

/*
|--------------------------------------------------------------------------
| RELATED PRODUCTS
|--------------------------------------------------------------------------
*/

$relatedProducts =
$conn->query("
SELECT
id,
name,
image,
price,
sale_price

FROM products

WHERE

category_id =
{$product['category_id']}

AND id != {$productId}

AND approved=1

AND status='active'

LIMIT 8
");
?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="utf-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>

<?= htmlspecialchars($product['name']) ?>

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">

<style>

.product-main-image{
    width:100%;
    height:500px;
    object-fit:cover;
    border-radius:12px;
}

.gallery-thumb{
    width:90px;
    height:90px;
    object-fit:cover;
    cursor:pointer;
    border-radius:8px;
    border:2px solid #eee;
}

.gallery-thumb:hover{
    border-color:#0d6efd;
}

.price{
    font-size:32px;
    font-weight:700;
    color:#dc3545;
}

.old-price{
    text-decoration:line-through;
    color:#777;
}

.shop-card{
    border:none;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.rating-stars{
    color:#ffc107;
}

.spec-table td{
    padding:10px;
}

</style>

</head>

<body>

<div class="container py-4">

<div class="row">

<!-- PRODUCT IMAGE -->

<div class="col-lg-5 mb-4">

<img
id="mainImage"
src="<?= htmlspecialchars($image) ?>"
class="product-main-image"
alt="<?= htmlspecialchars($product['name']) ?>">

<div class="d-flex flex-wrap gap-2 mt-3">

<img
src="<?= htmlspecialchars($image) ?>"
class="gallery-thumb"
onclick="changeImage(this.src)">

<?php while($img = $gallery->fetch_assoc()): ?>

<img
src="<?= htmlspecialchars($img['image']) ?>"
class="gallery-thumb"
onclick="changeImage(this.src)">

<?php endwhile; ?>

</div>

</div>

<!-- PRODUCT INFO -->

<div class="col-lg-7">

<div class="mb-2 text-muted">

<?= htmlspecialchars($product['category_name']) ?>

</div>

<h2>

<?= htmlspecialchars($product['name']) ?>

</h2>

<?php

$avgRating =
round(
(float)$ratingSummary['avg_rating']
);

?>

<div class="mb-3">

<?php for($i=1;$i<=5;$i++): ?>

<i
class="bi bi-star<?= $i <= $avgRating ? '-fill' : '' ?> rating-stars"></i>

<?php endfor; ?>

<span class="ms-2">

<?= number_format(
(float)$ratingSummary['avg_rating'],
1
) ?>

(
<?= (int)$ratingSummary['total_reviews'] ?>

reviews)

</span>

</div>

<!-- PRICE -->

<?php if(
!empty($product['sale_price'])
&&
$product['sale_price'] > 0
): ?>

<div class="price">

TZS

<?= number_format(
(float)$product['sale_price']
) ?>

</div>

<div class="old-price">

TZS

<?= number_format(
(float)$product['price']
) ?>

</div>

<?php else: ?>

<div class="price">

TZS

<?= number_format(
(float)$product['price']
) ?>

</div>

<?php endif; ?>

<!-- STOCK -->

<div class="mt-3">

<?php if((int)$product['stock'] > 0): ?>

<span class="badge bg-success">

In Stock

</span>

<?php else: ?>

<span class="badge bg-danger">

Out Of Stock

</span>

<?php endif; ?>

</div>

<!-- WHOLESALE -->

<?php if(
(int)$product['is_wholesale'] === 1
): ?>

<div class="alert alert-info mt-3">

Wholesale Available

<br>

Minimum Order:

<strong>

<?= (int)$product['minimum_order_qty'] ?>

</strong>

<br>

Wholesale Price:

<strong>

TZS

<?= number_format(
(float)$product['wholesale_price']
) ?>

</strong>

</div>

<?php endif; ?>

<!-- SHORT DESCRIPTION -->

<?php if(
!empty(
$product['short_description']
)
): ?>

<p class="mt-4">

<?= nl2br(
htmlspecialchars(
$product['short_description']
)
) ?>

</p>

<?php endif; ?>

<!-- VARIANTS -->

<?php if(
$variants->num_rows > 0
): ?>

<div class="mt-4">

<h5>

Available Options

</h5>

<select
class="form-select">

<?php while(
$variant =
$variants->fetch_assoc()
): ?>

<option>

<?= htmlspecialchars(
$variant['variant_name']
) ?>

:

<?= htmlspecialchars(
$variant['variant_value']
) ?>

</option>

<?php endwhile; ?>

</select>

</div>

<?php endif; ?>

<!-- CART FORM -->

<form
action="cart-add.php"
method="POST"
class="mt-4">

<input
type="hidden"
name="product_id"
value="<?= (int)$product['id'] ?>">

<div class="row">

<div class="col-md-3">

<input
type="number"
name="quantity"
value="1"
min="1"
max="<?= (int)$product['stock'] ?>"
class="form-control">

</div>

<div class="col-md-9">

<button
type="submit"
class="btn btn-primary">

<i class="bi bi-cart-plus"></i>

Add To Cart

</button>

<a
href="wishlist-add.php?id=<?= (int)$product['id'] ?>"
class="btn btn-outline-danger">

<i class="bi bi-heart"></i>

Wishlist

</a>

</div>

</div>

</form>

</div>

</div>

<!-- DESCRIPTION -->

<div class="card mt-5">

<div class="card-header">

Product Description

</div>

<div class="card-body">

<?= nl2br(
htmlspecialchars(
$product['description']
)
) ?>

</div>

</div>

<!-- SHOP INFO -->

<div class="card shop-card mt-4">

<div class="card-header">

Seller Information

</div>

<div class="card-body">

<div class="row align-items-center">

<div class="col-md-2">

<?php

/*
|--------------------------------------------------------------------------
| SHOP LOGO HANDLER (PRODUCTION SAFE)
|--------------------------------------------------------------------------
*/

$shopLogo = 'assets/images/no-shop.png';

/*
|--------------------------------------------------------------------------
| CHECK SHOP DATA SAFELY
|--------------------------------------------------------------------------
*/

$logoFile =
$product['logo']
?? null;

if (!empty($logoFile))
{
    /*
    |--------------------------------------------------------------------------
    | POSSIBLE STORAGE PATHS
    |--------------------------------------------------------------------------
    */

    $candidates = [
        'uploads/shops/' . $logoFile,
        'uploads/logos/' . $logoFile,
        'assets/uploads/' . $logoFile,
        $logoFile
    ];

    foreach ($candidates as $path)
    {
        $cleanPath = ltrim($path, '/');

        if (file_exists($cleanPath))
        {
            $shopLogo = $cleanPath;
            break;
        }
    }
}

?>

<img
src="<?= htmlspecialchars($shopLogo) ?>"
alt="Shop Logo"
class="img-fluid rounded"
loading="lazy">


</div>

<div class="col-md-10">

<h5>

<?= htmlspecialchars(
$product['shop_name']
) ?>

<?php if(
(int)$product['verified'] === 1
): ?>

<i
class="bi bi-patch-check-fill text-primary"></i>

<?php endif; ?>

</h5>

<a
href="shop.php?slug=<?= urlencode(
$product['shop_slug']
) ?>"
class="btn btn-outline-primary btn-sm">

Visit Shop

</a>

</div>

</div>

</div>

</div>

<!-- SPECIFICATIONS -->

<?php if($specifications->num_rows > 0): ?>

<div class="card mt-4">

<div class="card-header">

Specifications

</div>

<div class="card-body p-0">

<table class="table table-striped mb-0 spec-table">

<tbody>

<?php while($spec = $specifications->fetch_assoc()): ?>

<tr>

<td width="35%">

<strong>

<?= htmlspecialchars($spec['spec_name']) ?>

</strong>

</td>

<td>

<?= nl2br(
htmlspecialchars(
$spec['spec_value']
)
) ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<?php endif; ?>



<!-- CUSTOMER REVIEWS -->

<div class="card mt-5">

<div class="card-header">

Customer Reviews

(
<?= (int)$ratingSummary['total_reviews'] ?>

)

</div>

<div class="card-body">

<?php if($reviews->num_rows > 0): ?>

<?php while($review = $reviews->fetch_assoc()): ?>

<div class="border-bottom pb-3 mb-3">

<div class="d-flex justify-content-between">

<strong>

<?= htmlspecialchars(
$review['full_name']
) ?>

</strong>

<small class="text-muted">

<?= date(
'd M Y',
strtotime(
$review['created_at']
)
) ?>

</small>

</div>

<div class="mb-2">

<?php for($i=1;$i<=5;$i++): ?>

<i
class="bi bi-star<?= $i <= (int)$review['rating'] ? '-fill' : '' ?> text-warning">
</i>

<?php endfor; ?>

</div>

<p class="mb-0">

<?= nl2br(
htmlspecialchars(
$review['review']
)
) ?>

</p>

</div>

<?php endwhile; ?>

<?php else: ?>

<div class="alert alert-light">

No reviews yet.

</div>

<?php endif; ?>

</div>

</div>



<?php

$alreadyReviewed = false;

if(isset($_SESSION['user_id']))
{
    $reviewCheck = $conn->prepare("
        SELECT id
        FROM reviews
        WHERE product_id=?
        AND user_id=?
        LIMIT 1
    ");

    $reviewCheck->bind_param(
        "ii",
        $product['id'],
        $_SESSION['user_id']
    );

    $reviewCheck->execute();

    $alreadyReviewed =
    $reviewCheck
    ->get_result()
    ->num_rows > 0;
}

if(empty($_SESSION['csrf_token']))
{
    $_SESSION['csrf_token'] =
    bin2hex(random_bytes(32));
}

?>

<?php if(isset($_SESSION['user_id'])): ?>

<a
href="submit-review.php?product_id=<?= (int)$product['id'] ?>"
class="btn btn-outline-primary">

Write Review

</a>



<div class="card mt-4 shadow-sm border-0">

<div class="card-header bg-white">

<h5 class="mb-0">





</h5>

</div>

<div class="card-body">

<?php if($alreadyReviewed): ?>

<div class="alert alert-info">

You have already reviewed this product.

</div>

<?php else: ?>

<form
action="submit-review.php"
method="POST"
id="reviewForm">

<input
type="hidden"
name="product_id"
value="<?= (int)$product['id'] ?>">

<input
type="hidden"
name="csrf_token"
value="<?= $_SESSION['csrf_token'] ?>">

<input
type="hidden"
name="redirect"
value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">

<div class="mb-4">

<label class="form-label fw-bold">

Your Rating

</label>

<div class="d-flex gap-3">

<?php for($i=5;$i>=1;$i--): ?>

<div class="form-check">

<input
class="form-check-input"
type="radio"
name="rating"
value="<?= $i ?>"
id="rating<?= $i ?>"

<?= $i === 5 ? 'checked' : '' ?>>

<label
class="form-check-label"
for="rating<?= $i ?>">

<?= str_repeat('★',$i) ?>

</label>

</div>

<?php endfor; ?>

</div>

</div>

<div class="mb-3">

<label class="form-label fw-bold">

Review

</label>

<textarea
name="review"
id="reviewText"
rows="5"
maxlength="1000"
class="form-control"
placeholder="Share your experience with this product..."
required></textarea>

<div
class="text-end small text-muted mt-1">

<span id="charCount">

0

</span>

/1000

</div>

</div>

<button
type="submit"
id="reviewBtn"
class="btn btn-primary">

<i class="bi bi-send"></i>

Submit Review

</button>

</form>

<?php endif; ?>

</div>

</div>

<script>

const reviewText =
document.getElementById(
'reviewText'
);

const charCount =
document.getElementById(
'charCount'
);

if(reviewText)
{
reviewText.addEventListener(
'input',
function()
{
charCount.innerText =
this.value.length;
}
);
}

const reviewForm =
document.getElementById(
'reviewForm'
);

if(reviewForm)
{
reviewForm.addEventListener(
'submit',
function()
{
document.getElementById(
'reviewBtn'
).innerHTML =
'Submitting...';

document.getElementById(
'reviewBtn'
).disabled = true;
}
);
}

</script>

<?php else: ?>

<div class="card mt-4">

<div class="card-body text-center">

<p class="mb-3">

Please login to review this product.

</p>

<a
href="login.php"
class="btn btn-primary">

Login

</a>

</div>

</div>

<?php endif; ?>




<!-- RELATED PRODUCTS -->

<hr class="my-5">

<h3 class="mb-4">

Related Products

</h3>

<div class="row">

<?php while($related = $relatedProducts->fetch_assoc()): ?>

<?php

$relatedImage =
$related['image']
?: 'assets/images/no-image.jpg';

$relatedPrice =
!empty($related['sale_price'])
?
(float)$related['sale_price']
:
(float)$related['price'];

?>

<div class="col-lg-3 col-md-4 col-sm-6 mb-4">

<div class="card h-100">

<img
src="<?= htmlspecialchars($relatedImage) ?>"
style="
height:220px;
object-fit:cover;
">

<div class="card-body">

<h6>

<?= htmlspecialchars(
$related['name']
) ?>

</h6>

<strong>

TZS

<?= number_format(
$relatedPrice
) ?>

</strong>

</div>

<div class="card-footer bg-white">

<a
href="product-details.php?id=<?= (int)$related['id'] ?>"
class="btn btn-outline-primary w-100">

View Product

</a>

</div>

</div>

</div>

<?php endwhile; ?>

</div>



<!-- RECENTLY VIEWED -->

<?php

$recentIds =
$_SESSION['recently_viewed']
?? [];

$recentIds =
array_diff(
$recentIds,
[$productId]
);

if(count($recentIds) > 0):

$ids =
implode(
',',
array_map(
'intval',
$recentIds
)
);

$recentQuery =
mysqli_query(
$conn,
"
SELECT
id,
name,
image,
price
FROM products
WHERE id IN ($ids)
LIMIT 8
"
);

?>

<hr class="my-5">

<h3 class="mb-4">

Recently Viewed

</h3>

<div class="row">

<?php while(
$recent =
mysqli_fetch_assoc(
$recentQuery
)
): ?>

<div class="col-lg-3 col-md-4 col-sm-6 mb-4">

<div class="card h-100">

<img
src="<?= htmlspecialchars(
$recent['image']
?: 'assets/images/no-image.jpg'
) ?>"
style="
height:220px;
object-fit:cover;
">

<div class="card-body">

<h6>

<?= htmlspecialchars(
$recent['name']
) ?>

</h6>

<strong>

TZS

<?= number_format(
(float)$recent['price']
) ?>

</strong>

</div>

<div class="card-footer bg-white">

<a
href="product-details.php?id=<?= (int)$recent['id'] ?>"
class="btn btn-outline-secondary w-100">

View Again

</a>

</div>

</div>

</div>

<?php endwhile; ?>

</div>

<?php endif; ?>

</div>



<script>

function changeImage(src)
{
document
.getElementById(
'mainImage'
)
.src = src;
}

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>