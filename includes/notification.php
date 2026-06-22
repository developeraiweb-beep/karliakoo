<?php

function createNotification(
    $conn,
    $user_id,
    $title,
    $message,
    $type='system',
    $reference_id=null
)
{
    $stmt = $conn->prepare("
        INSERT INTO notifications(
            user_id,
            title,
            message,
            type,
            reference_id
        )
        VALUES(?,?,?,?,?)
    ");

    $stmt->bind_param(
        "isssi",
        $user_id,
        $title,
        $message,
        $type,
        $reference_id
    );

    return $stmt->execute();
}