<?php
// api/v1/auth/login.php
// POST /api/v1/auth/login
// Accepts: username (or email), password, optional totp_code / recovery_code.
// NOTE: if the account has 2FA enabled, password-only login does NOT issue
// a token (audit 2026-11-15, Batch 2 — the old API skipped 2FA entirely).
// The client receives {requires_2fa: true, pending_2fa_user_id} and must
// call POST /api/v1/auth/2fa with the code to receive the token.

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
api_cors_headers();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$totpCode = $input['totp_code'] ?? $input['code'] ?? '';
$recoveryCode = $input['recovery_code'] ?? '';

if ($username === '' || $password === '') {
    api_error('Username and password are required', 422);
}

$stmt = db()->prepare("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1");
$stmt->execute([$username, $username]);
$user = $stmt->fetch();

if (!$user) {
    api_error('Invalid credentials', 401);
}

// Brute-force lockout: 5 failed attempts lock the account for 15
// minutes. Checked BEFORE password_verify, same as the web login.
if (login_is_locked($user)) {
    api_error('Account locked due to too many failed login attempts. Try again later.', 423);
}

if (!password_verify($password, $user['password'])) {
    login_record_failed_attempt((int)$user['id']);
    api_error('Invalid credentials', 401);
}

// --- 2FA (mirrors the web login: includes/auth.php attempt_login()) ---
if (!empty($user['two_factor_enabled'])) {
    // Client supplied a code in the same call: verify it now.
    // complete_2fa_*() read the pending user id from the session (same
    // as the web challenge), so set it first, then call them with the
    // code only.
    if ($totpCode !== '' || $recoveryCode !== '') {
        $_SESSION['pending_2fa_user_id'] = (int)$user['id'];
        $ok = false;
        if ($recoveryCode !== '') {
            $ok = complete_2fa_recovery_login($recoveryCode);
        } else {
            $ok = complete_2fa_login($totpCode);
        }
        if (!$ok) {
            api_error('Invalid 2FA code', 401);
        }
        $user = current_user();
    } else {
        // Password is correct but 2FA is enabled: NO token is issued.
        api_json([
            'requires_2fa' => true,
            'pending_2fa_user_id' => (int)$user['id'],
            'message' => 'Two-factor authentication required',
        ], 200);
    }
} else {
    login_reset_failed_attempts((int)$user['id']);
}

// --- Issue a token, valid for 30 days (rotated on every login) ---
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + 30 * 24 * 60 * 60);
db()->prepare("UPDATE users SET api_token = ?, api_token_expires_at = ?, last_login_at = NOW() WHERE id = ?")
    ->execute([$token, $expiresAt, $user['id']]);

log_activity('auth', 'login', 'users', (int)$user['id'], 'User logged in via API', (int)$user['id']);

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
