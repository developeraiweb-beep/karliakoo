<?php

function hasPermission(
    mysqli $conn,
    string $permission
)
{
    if(empty($_SESSION['role']))
    {
        return false;
    }

    $stmt = $conn->prepare("
    SELECT COUNT(*) total

    FROM role_permissions rp

    INNER JOIN roles r
    ON r.id=rp.role_id

    INNER JOIN permissions p
    ON p.id=rp.permission_id

    WHERE r.name=?
    AND p.permission_key=?
    ");

    $stmt->bind_param(
        "ss",
        $_SESSION['role'],
        $permission
    );

    $stmt->execute();

    return (
        $stmt
        ->get_result()
        ->fetch_assoc()['total']
    ) > 0;
}

function requirePermission(
    mysqli $conn,
    string $permission
)
{
    if(
        !hasPermission(
            $conn,
            $permission
        )
    )
    {
        http_response_code(403);

        die("Permission Denied");
    }
}