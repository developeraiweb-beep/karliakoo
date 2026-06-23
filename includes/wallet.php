<?php

function getOrCreateWallet(
    mysqli $conn,
    int $userId
): int
{
    $stmt = $conn->prepare("
        SELECT id
        FROM wallets
        WHERE user_id=?
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $userId
    );

    $stmt->execute();

    $wallet =
    $stmt
    ->get_result()
    ->fetch_assoc();

    if ($wallet) {

        return (int)$wallet['id'];
    }

    $stmt = $conn->prepare("
        INSERT INTO wallets
        (
            user_id,
            balance
        )
        VALUES
        (
            ?,
            0
        )
    ");

    $stmt->bind_param(
        "i",
        $userId
    );

    $stmt->execute();

    return (int)$conn->insert_id;
}

function getWalletBalance(
    mysqli $conn,
    int $walletId
): float
{
    $stmt = $conn->prepare("
        SELECT balance
        FROM wallets
        WHERE id=?
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $walletId
    );

    $stmt->execute();

    $row =
    $stmt
    ->get_result()
    ->fetch_assoc();

    return (float)($row['balance'] ?? 0);
}
?>
