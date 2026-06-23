<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session.php';

/*
|--------------------------------------------------------------------------
| AUTHENTICATION HELPERS
|--------------------------------------------------------------------------
*/

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/*
|--------------------------------------------------------------------------
| LOGIN REQUIRED
|--------------------------------------------------------------------------
*/

function requireLogin(): void
{
    if (!isLoggedIn()) {

        $redirect = urlencode(
            $_SERVER['REQUEST_URI'] ?? '/'
        );

        header(
            "Location: /Karliakoo/login.php?redirect={$redirect}"
        );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

function currentUser(): ?array
{
    global $conn;

    if (!isLoggedIn()) {
        return null;
    }

    static $userCache = null;

    if ($userCache !== null) {
        return $userCache;
    }

    $userId = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare("
        SELECT
            id,
            full_name,
            email,
            phone,
            role,
            status,
            profile_photo,
            email_verified,
            phone_verified,
            created_at,
            last_login
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $userId
    );

    $stmt->execute();

    $user =
    $stmt->get_result()->fetch_assoc();

    /*
    |--------------------------------------------------------------------------
    | INVALID USER
    |--------------------------------------------------------------------------
    */

    if (!$user) {

        logoutUser();

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | ACCOUNT STATUS CHECK
    |--------------------------------------------------------------------------
    */

    if (
        !isset($user['status']) ||
        $user['status'] !== 'active'
    ) {

        logoutUser();

        header(
            "Location: /Karliakoo/login.php?error=account_inactive"
        );

        exit;
    }

    $userCache = $user;

    return $userCache;
}

/*
|--------------------------------------------------------------------------
| ROLE CHECK
|--------------------------------------------------------------------------
*/

function requireRole(array $roles): void
{
    requireLogin();

    $user = currentUser();

    if (
        !$user ||
        !in_array(
            $user['role'],
            $roles,
            true
        )
    ) {

        http_response_code(403);

        die(
            "403 Forbidden - Access Denied"
        );
    }
}

/*
|--------------------------------------------------------------------------
| ROLE HELPER
|--------------------------------------------------------------------------
*/

function hasRole(string $role): bool
{
    $user = currentUser();

    return
        $user &&
        $user['role'] === $role;
}

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
*/

function can(string $permission): bool
{
    $user = currentUser();

    if (!$user) {
        return false;
    }

    $permissions = [

        'admin' => [
            'all'
        ],

        'seller' => [
            'products.manage',
            'orders.manage',
            'shop.manage',
            'withdrawals.request'
        ],

        'buyer' => [
            'orders.create',
            'orders.view',
            'wishlist.use',
            'reviews.create'
        ],

        'b2b' => [
            'rfq.create',
            'rfq.manage',
            'quotes.view',
            'contracts.view',
            'orders.create'
        ],

        'delivery' => [
            'deliveries.manage',
            'deliveries.update'
        ],

        'agent' => [
            'commissions.view',
            'referrals.manage'
        ]
    ];

    $rolePermissions =
        $permissions[$user['role']]
        ?? [];

    return
        in_array(
            'all',
            $rolePermissions,
            true
        )
        ||
        in_array(
            $permission,
            $rolePermissions,
            true
        );
}

/*
|--------------------------------------------------------------------------
| DASHBOARD ROUTER
|--------------------------------------------------------------------------
*/

function dashboardUrl(
    string $role
): string
{
    return match($role) {

        'admin'
            => '/Karliakoo/admin/dashboard.php',

        'seller'
            => '/Karliakoo/seller/dashboard.php',

        'buyer'
            => '/Karliakoo/users/dashboard.php',

        'b2b'
            => '/Karliakoo/b2b/dashboard.php',

        'agent'
            => '/Karliakoo/agent/dashboard.php',

        'delivery'
            => '/Karliakoo/delivery/dashboard.php',

        default
            => '/Karliakoo/login.php'
    };
}

/*
|--------------------------------------------------------------------------
| REDIRECT IF LOGGED IN
|--------------------------------------------------------------------------
*/

function redirectIfAuthenticated(): void
{
    if (!isLoggedIn()) {
        return;
    }

    $user = currentUser();

    if (!$user) {
        return;
    }

    header(
        "Location: " .
        dashboardUrl(
            $user['role']
        )
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

function logoutUser(): void
{
    $_SESSION = [];

    if (
        ini_get(
            "session.use_cookies"
        )
    ) {

        $params =
        session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/*
|--------------------------------------------------------------------------
| AUTHENTICATED USER ID
|--------------------------------------------------------------------------
*/

function userId(): int
{
    return (int)(
        $_SESSION['user_id']
        ?? 0
    );
}
?>
