<?php
/**
 * Aleltu Farm EIFMS - database connection (plain PDO, XAMPP-ready).
 * Edit these four values to match your XAMPP MySQL setup.
 * Default XAMPP MySQL: host=127.0.0.1, user=root, password=(empty).
 */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'aleltu_eifms');
define('DB_USER', 'root');
define('DB_PASS', '');

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
