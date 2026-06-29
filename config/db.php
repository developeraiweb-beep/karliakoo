<?php
/**
 * Karliakoo Marketplace
 * Database Configuration
 */

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*
|--------------------------------------------------------------------------
| Database Credentials
|--------------------------------------------------------------------------
|
| Local Development (XAMPP)
|
*/
$db_host = "localhost";
$db_name = "karliakoo";
$db_user = "root";
$db_pass = "";

/*
|--------------------------------------------------------------------------
| Production Example
|--------------------------------------------------------------------------
|
| Uncomment and update when deploying
|
| $db_host = "localhost";
| $db_name = "u123456789_karliakoo";
| $db_user = "u123456789_admin";
| $db_pass = "StrongPassword";
|
*/

try {

    $conn = new mysqli(
        $db_host,
        $db_user,
        $db_pass,
        $db_name
    );

    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {

    error_log(
        "[" . date('Y-m-d H:i:s') . "] Database Error: " .
        $e->getMessage() . PHP_EOL,
        3,
        __DIR__ . "/../logs/database.log"
    );

    die(
        "System temporarily unavailable. Please try again later."
    );
}

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function clean_input(string $data): string
{
    return htmlspecialchars(
        trim($data),
        ENT_QUOTES,
        'UTF-8'
    );
}

function generate_token(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function redirect(string $url): void
{
    header("Location: " . $url);
    exit();
}