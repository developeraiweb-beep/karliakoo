<?php

function auditLog(
    mysqli $conn,
    ?int $userId,
    ?string $role,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    ?string $description = null
){

    $ip =
        $_SERVER['REMOTE_ADDR']
        ?? null;

    $agent =
        $_SERVER['HTTP_USER_AGENT']
        ?? null;

    $stmt = $conn->prepare("
        INSERT INTO b2b_audit_logs(

            user_id,
            role,
            action,
            entity_type,
            entity_id,
            description,
            ip_address,
            user_agent

        ) VALUES(?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "isssisss",
        $userId,
        $role,
        $action,
        $entityType,
        $entityId,
        $description,
        $ip,
        $agent
    );

    $stmt->execute();
}