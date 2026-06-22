<?php

declare(strict_types=1);

require_once "../config/db.php";
require_once "../includes/auth.php";

requireLogin();

requireRole(['admin']);

$admin = currentUser();

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
)
{
    header(
        "Location: withdrawals.php"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF VALIDATION
|--------------------------------------------------------------------------
*/

if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token']
    )
)
{
    die("Invalid CSRF token.");
}

$withdrawalId =
(int)(
    $_POST['withdrawal_id']
    ?? 0
);

$action =
trim(
    $_POST['action']
    ?? ''
);

if (
    $withdrawalId <= 0
)
{
    header(
        "Location: withdrawals.php?error=Invalid withdrawal."
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD WITHDRAWAL
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT *
    FROM withdrawals
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $withdrawalId
);

$stmt->execute();

$withdrawal =
$stmt
->get_result()
->fetch_assoc();

if (!$withdrawal)
{
    header(
        "Location: withdrawals.php?error=Withdrawal not found."
    );
    exit;
}

$userId =
(int)$withdrawal['user_id'];

$amount =
(float)$withdrawal['amount'];

$status =
$withdrawal['status'];

$conn->begin_transaction();

try
{
    /*
    |--------------------------------------------------------------------------
    | APPROVE REQUEST
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'approve'
    )
    {
        if (
            $status !== 'pending'
        )
        {
            throw new Exception(
                "Only pending withdrawals can be approved."
            );
        }

        $update =
        $conn->prepare("
            UPDATE withdrawals
            SET
                status='approved',
                processed_at=NOW()
            WHERE id=?
        ");

        $update->bind_param(
            "i",
            $withdrawalId
        );

        $update->execute();

        $message =
        "Withdrawal approved successfully.";
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT REQUEST
    |--------------------------------------------------------------------------
    */

    elseif (
        $action === 'reject'
    )
    {
        if (
            $status !== 'pending'
        )
        {
            throw new Exception(
                "Only pending withdrawals can be rejected."
            );
        }

        /*
        |--------------------------------------------------------------
        | RETURN FUNDS TO WALLET
        |--------------------------------------------------------------
        */

        $wallet =
        $conn->prepare("
            UPDATE seller_wallets
            SET

                pending_balance =
                pending_balance - ?,

                available_balance =
                available_balance + ?

            WHERE seller_id = ?
        ");

        $wallet->bind_param(
            "ddi",
            $amount,
            $amount,
            $userId
        );

        $wallet->execute();

        $reject =
        $conn->prepare("
            UPDATE withdrawals
            SET
                status='rejected',
                processed_at=NOW()
            WHERE id=?
        ");

        $reject->bind_param(
            "i",
            $withdrawalId
        );

        $reject->execute();

        $message =
        "Withdrawal rejected.";
    }

    /*
    |--------------------------------------------------------------------------
    | MARK AS PAID
    |--------------------------------------------------------------------------
    */

    elseif (
        $action === 'mark_paid'
    )
    {
        if (
            $status !== 'approved'
        )
        {
            throw new Exception(
                "Only approved withdrawals can be paid."
            );
        }

        $reference =
        "WD-" .
        date("Ymd") .
        "-" .
        strtoupper(
            substr(
                bin2hex(
                    random_bytes(4)
                ),
                0,
                8
            )
        );

        /*
        |--------------------------------------------------------------
        | UPDATE WITHDRAWAL
        |--------------------------------------------------------------
        */

        $paid =
        $conn->prepare("
            UPDATE withdrawals
            SET

                status='paid',

                processed_at=NOW(),

                transaction_reference=?

            WHERE id=?
        ");

        $paid->bind_param(
            "si",
            $reference,
            $withdrawalId
        );

        $paid->execute();

        /*
        |--------------------------------------------------------------
        | UPDATE WALLET
        |--------------------------------------------------------------
        */

        $wallet =
        $conn->prepare("
            UPDATE seller_wallets
            SET

                pending_balance =
                pending_balance - ?,

                total_withdrawn =
                total_withdrawn + ?

            WHERE seller_id = ?
        ");

        $wallet->bind_param(
            "ddi",
            $amount,
            $amount,
            $userId
        );

        $wallet->execute();

        /*
        |--------------------------------------------------------------
        | RECORD PAYOUT
        |--------------------------------------------------------------
        */

        $payout =
        $conn->prepare("
            INSERT INTO seller_payouts
            (
                seller_id,
                amount,
                status,
                payment_method,
                reference_no
            )
            VALUES
            (
                ?,
                ?,
                'paid',
                ?,
                ?
            )
        ");

        $payout->bind_param(
            "idss",
            $userId,
            $amount,
            $withdrawal['method'],
            $reference
        );

        $payout->execute();

        $message =
        "Withdrawal marked as paid.";
    }

    else
    {
        throw new Exception(
            "Invalid action."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AUDIT LOG
    |--------------------------------------------------------------------------
    */

    $audit =
    $conn->prepare("
        INSERT INTO audit_logs
        (
            user_id,
            action,
            description,
            created_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            NOW()
        )
    ");

    $auditAction =
    "withdrawal_" .
    $action;

    $auditDescription =
    "Admin #" .
    $admin['id'] .
    " performed " .
    $action .
    " on withdrawal #" .
    $withdrawalId;

    $audit->bind_param(
        "iss",
        $admin['id'],
        $auditAction,
        $auditDescription
    );

    $audit->execute();

    $conn->commit();

    header(
        "Location: withdrawals.php?success=" .
        urlencode($message)
    );
    exit;
}
catch(Exception $e)
{
    $conn->rollback();

    header(
        "Location: withdrawals.php?error=" .
        urlencode(
            $e->getMessage()
        )
    );
    exit;
}