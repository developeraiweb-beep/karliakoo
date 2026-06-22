<?php

function createNotification(
    mysqli $conn,
    int $userId,
    string $title,
    string $message,
    string $type='system',
    ?int $referenceId=null,
    ?string $referenceType=null
)
{
    $stmt = $conn->prepare("
    INSERT INTO b2b_notifications(
        user_id,
        title,
        message,
        notification_type,
        reference_id,
        reference_type
    )
    VALUES(?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "isssis",
        $userId,
        $title,
        $message,
        $type,
        $referenceId,
        $referenceType
    );

    return $stmt->execute();
}