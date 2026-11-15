<?php
// api/v1/auth/2fa.php
// POST /api/v1/auth/2fa
// Second step of API login for 2FA-enabled accounts: verifies the TOTP or
// recovery code and only then issues a token (mirrors the web login's
// complete_2fa_login() / complete_2fa_recovery_login()).

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
api_cors_headers();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$pendingUserId = (int)($input['pending_2fa_user_id'] ?? 0);
$totpCode = $input['totp_code'] ?? $input['code'] ?? '';
$recoveryCode = $input['recovery_code'] ?? '';

if ($pendingUserId <= 0 || ($totpCode === '' && $recoveryCode === '')) {
    api_error('pending_2fa_user_id and a TOTP or recovery code are required', 422);
}

$stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
$stmt->execute([$pendingUserId]);
$user = $stmt->fetch();

if (!$user || empty($user['two_factor_enabled'])) {
    api_error('Invalid 2FA request', 401);
}

if (login_is_locked($user)) {
    api_error('Account locked due to too many failed login attempts. Try again later.', 423);
}

$ok = false;
// complete_2fa_*() read the pending user id from the session (same as
// the web challenge), so set it first, then call them with the code only.
$_SESSION['pending_2fa_user_id'] = $pendingUserId;
if ($recoveryCode !== '') {
    $ok = complete_2fa_recovery_login($recoveryCode);
} else {
    $ok = complete_2fa_login($totpCode);
}

if (!$ok) {
    api_error('Invalid 2FA code', 401);
}

$user = current_user();

// Issue a token, valid for 30 days (rotated on every login).
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + 30 * 24 * 60 * 60);
db()->prepare("UPDATE users SET api_token = ?, api_token_expires_at = ?, last_login_at = NOW() WHERE id = ?")
    ->execute([$token, $expiresAt, $user['id']]);

log_activity('auth', 'login', 'users', (int)$user['id'], 'User logged in via API (2FA)', (int)$user['id']);

api_json([
    'token' => $token,
    'expires_at' => $expiresAt,
    'user' => [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role_name'] ?? null,
    ],
]);
