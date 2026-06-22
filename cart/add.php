<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

$user_id = $_SESSION['user_id'];

$product_id = (int)$_POST['product_id'];
$quantity = max(1, (int)$_POST['quantity']);

$check = $conn->prepare("
    SELECT id, quantity
    FROM cart
    WHERE user_id = ?
    AND product_id = ?
");

$check->bind_param(
    "ii",
    $user_id,
    $product_id
);

$check->execute();

$result = $check->get_result();

if($row = $result->fetch_assoc()){

    $newQty =
    $row['quantity'] + $quantity;

    $update = $conn->prepare("
        UPDATE cart
        SET quantity = ?
        WHERE id = ?
    ");

    $update->bind_param(
        "ii",
        $newQty,
        $row['id']
    );

    $update->execute();

}else{

    $insert = $conn->prepare("
        INSERT INTO cart(
            user_id,
            product_id,
            quantity
        )
        VALUES(?,?,?)
    ");

    $insert->bind_param(
        "iii",
        $user_id,
        $product_id,
        $quantity
    );

    $insert->execute();
}

header("Location: ../cart.php");
exit;