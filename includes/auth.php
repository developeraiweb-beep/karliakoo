<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session.php';

/*
|--------------------------------------------------------------------------
| BASIC HELPERS
|--------------------------------------------------------------------------
*/

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/*
|--------------------------------------------------------------------------
| FORCE LOGIN
|--------------------------------------------------------------------------
*/
function requireLogin(): void
{
    if (!isLoggedIn()) {

        // prevent open redirect issues
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');

        header("Location: ../users/login.php?redirect={$redirect}");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| FETCH CURRENT USER (OPTIMIZED)
|--------------------------------------------------------------------------
| NOTE: In production scale systems, replace this with session caching
| to avoid DB hit on every request.
|--------------------------------------------------------------------------
*/
function currentUser(): ?array
{
    global $conn;

    if (!isLoggedIn()) {
        return null;
    }

    static $cachedUser = null;

    if ($cachedUser !== null) {
        return $cachedUser;
    }

    $id = (int) $_SESSION['user_id'];

    $stmt = $conn->prepare("
        SELECT
            id,
            full_name,
            email,
            phone,
            role,
            created_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        // invalid session cleanup
        session_unset();
        session_destroy();
        return null;
    }

    $cachedUser = $user;
    return $cachedUser;
}

/*
|--------------------------------------------------------------------------
| ROLE-BASED ACCESS CONTROL
|--------------------------------------------------------------------------
*/
function requireRole(array $roles): void
{
    requireLogin();

    $user = currentUser();

    if (!$user || !in_array($user['role'], $roles, true)) {

        // security: invalidate session on unauthorized access
        session_unset();
        session_destroy();

        header("Location: ../users/login.php?error=unauthorized");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| OPTIONAL: STRICT ROLE CHECK (NO AUTO-LOGOUT)
|--------------------------------------------------------------------------
| Use this for soft restrictions (UI hiding instead of session destruction)
|--------------------------------------------------------------------------
*/
function hasRole(string $role): bool
{
    $user = currentUser();
    return $user && $user['role'] === $role;
}

/*
|--------------------------------------------------------------------------
| OPTIONAL: PERMISSION GATE (SCALABLE FOR FUTURE RBAC)
|--------------------------------------------------------------------------
*/
function can(string $permission): bool
{
    $user = currentUser();

    if (!$user) return false;

    $role = $user['role'];

    $permissions = [
        'admin' => ['all'],
        'seller' => [
            'products.manage',
            'orders.manage',
            'withdrawals.request'
        ],
        'user' => [
            'orders.create',
            'wishlist.use'
        ],
        'delivery' => [
            'deliveries.manage'
        ],
        'agent' => [
            'commissions.view'
        ]
    ];

    return in_array('all', $permissions[$role] ?? [])
        || in_array($permission, $permissions[$role] ?? []);
}

/*
|--------------------------------------------------------------------------
| SAFE REDIRECT HELPER
|--------------------------------------------------------------------------
*/
function redirectIfAuthenticated(): void
{
    if (isLoggedIn()) {
        header("Location: /index.php");
        exit;
    }
}