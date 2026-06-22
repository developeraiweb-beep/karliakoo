<?php

/*
|--------------------------------------------------------------------------
| SECURE SESSION INITIALIZATION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {

    // Hardened session cookie settings BEFORE session_start()
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');

    // Enable HTTPS-only cookies in production (uncomment when live)
    // ini_set('session.cookie_secure', 1);

    session_start();

    /*
    |--------------------------------------------------------------------------
    | SESSION FIXATION PROTECTION (SAFE VERSION)
    |--------------------------------------------------------------------------
    | Regenerate ID only ONCE per session lifecycle, not every request.
    |--------------------------------------------------------------------------
    */

    if (!isset($_SESSION['initialized'])) {

        session_regenerate_id(true);

        $_SESSION['initialized'] = true;
        $_SESSION['created_at'] = time();
    }
}

/*
|--------------------------------------------------------------------------
| SESSION EXPIRY CONTROL
|--------------------------------------------------------------------------
| Auto logout after inactivity (e.g., 30 minutes)
|--------------------------------------------------------------------------
*/

$timeout = 30 * 60; // 30 minutes

if (isset($_SESSION['last_activity'])) {

    if (time() - $_SESSION['last_activity'] > $timeout) {

        session_unset();
        session_destroy();

        header("Location: /users/login.php?timeout=1");
        exit;
    }
}

$_SESSION['last_activity'] = time();

/*
|--------------------------------------------------------------------------
| OPTIONAL: GLOBAL SECURITY CONTEXT
|--------------------------------------------------------------------------
| Helps detect session hijacking attempts
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['ip_address'])) {
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;
}

if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
}

/*
| Validate session consistency (light protection)
*/
if (
    isset($_SESSION['ip_address'], $_SESSION['user_agent']) &&
    ($_SESSION['ip_address'] !== ($_SERVER['REMOTE_ADDR'] ?? null) ||
     $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? null))
) {
    session_unset();
    session_destroy();

    header("Location: /users/login.php?security=1");
    exit;
}