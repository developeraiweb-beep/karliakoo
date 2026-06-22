<?php

function logActivity(
    mysqli $conn,
    int $userId,
    string $type,
    string $title,
    string $description = '',
    ?string $referenceType = null,
    ?int $referenceId = null
)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $stmt = $conn->prepare("
    INSERT INTO user_activities(
        user_id,
        activity_type,
        activity_title,
        description,
        reference_type,
        reference_id,
        ip_address
    )
    VALUES(?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "issssis",
        $userId,
        $type,
        $title,
        $description,
        $referenceType,
        $referenceId,
        $ip
    );

    return $stmt->execute();
}