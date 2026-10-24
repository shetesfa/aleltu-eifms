<?php
/**
 * POST /api/v1/auth/login
 * Body: { "email": "...", "password": "..." }
 * Returns: { "token": "...", "user": { ... } }
 */
require_once __DIR__ . '/../../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_error('Method not allowed', 405);

$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true);
if (!is_array($body)) {
    $body = $_POST;
}

$login = trim($body['username'] ?? ($body['email'] ?? ''));
$pass  = $body['password'] ?? '';

if (!$login || !$pass) api_error('username/email and password are required');

$stmt = db()->prepare(
    "SELECT u.*, r.name AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE (u.email = ? OR u.name = ?) AND u.is_active = 1 LIMIT 1"
);
$stmt->execute([$login, $login]);
$user = $stmt->fetch();

if (!$user || !password_verify($pass, $user['password'])) {
    api_error('Invalid credentials', 401);
}

// Generate or reuse API token
if (empty($user['api_token'])) {
    $token = bin2hex(random_bytes(32));
    db()->prepare("UPDATE users SET api_token = ? WHERE id = ?")->execute([$token, $user['id']]);
} else {
    $token = $user['api_token'];
}

log_activity('auth', 'login', 'users', $user['id'], 'API login from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

api_json([
    'token' => $token,
    'user'  => [
        'id'    => (int) $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ],
]);
