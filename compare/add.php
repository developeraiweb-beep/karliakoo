<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user_id =
(int)$_SESSION['user_id'];

$product_id =
(int)$_POST['product_id'];

$count = $conn->prepare("
SELECT COUNT(*)
total
FROM compare_products
WHERE user_id=?
");

$count->bind_param(
"i",
$user_id
);

$count->execute();

$total =
$count
->get_result()
->fetch_assoc()['total'];

if($total < 4)
{
    $stmt = $conn->prepare("
        INSERT IGNORE
        INTO compare_products(
            user_id,
            product_id
        )
        VALUES(?,?)
    ");

    $stmt->bind_param(
        "ii",
        $user_id,
        $product_id
    );

    $stmt->execute();
}

header(
"Location: ../compare.php"
);
exit;