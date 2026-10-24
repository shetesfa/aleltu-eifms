<?php
/**
 * Aleltu Farm EIFMS - database connection (plain PDO, XAMPP-ready).
 * Edit these four values to match your XAMPP MySQL setup.
 * Default XAMPP MySQL: host=127.0.0.1, user=root, password=(empty).
 */
$isLocal = (php_sapi_name() === 'cli') || in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']);

if ($isLocal) {
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'aleltu_eifms');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // InfinityFree MySQL Details (Get these from InfinityFree vPanel -> MySQL Databases)
    define('DB_HOST', 'sqlxxx.infinityfree.com'); // e.g., sql102.infinityfree.com
    define('DB_NAME', 'if0_xxxxxxx_aleltu_eifms'); // Full DB name with prefix
    define('DB_USER', 'if0_xxxxxxx');             // Your vPanel username
    define('DB_PASS', 'YOUR_ACCOUNT_PASSWORD');    // Your vPanel password
}

// Dynamic BASE_URL detection:
// When running in a subfolder like /aleltu-eifms (e.g. localhost XAMPP): BASE_URL = '/aleltu-eifms'
// When hosted at domain root on InfinityFree: BASE_URL = ''
if (!defined('BASE_URL')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($scriptDir, '/aleltu-eifms') !== false) {
        define('BASE_URL', '/aleltu-eifms');
    } else {
        define('BASE_URL', '');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string {
        return BASE_URL . '/' . ltrim($path, '/');
    }
}

// SAD section 16.4 — outbound-only integration point with the external
// Aleltu POS System. Leave blank until Aleltu's POS team confirms an
// endpoint; Distribution safely no-ops without it (see dispatch_push_to_pos()
// in includes/functions.php).
if (!defined('POS_WEBHOOK_URL')) {
    define('POS_WEBHOOK_URL', '');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            die('Database connection failed: ' . htmlspecialchars($e->getMessage()) .
                '<br>Check config/db.php — is MySQL running in XAMPP, and does the "aleltu_eifms" database exist?');
        }
    }
    return $pdo;
}
