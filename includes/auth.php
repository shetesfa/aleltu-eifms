<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: /aleltu-eifms/modules/auth/login.php');
        exit;
    }
}

function has_role($allowedRoles): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    if ($user['role'] === 'super_admin') {
        return true;
    }
    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    return in_array($user['role'], $allowedRoles, true);
}

// Simple role-based access check (mirrors SAD section 13's role list).
// Usage: require_role(['super_admin', 'dairy_manager'])
function require_role(array $allowedRoles): void
{
    require_login();
    if (!has_role($allowedRoles)) {
        http_response_code(403);
        die('<h2>403 - You do not have permission to view this page.</h2><p><a href="/aleltu-eifms/index.php">Back to dashboard</a></p>');
    }
}

/* ===================== CSRF PROTECTION ===================== */
// Every POST form includes csrf_field(); every POST handler calls
// csrf_verify() as its first statement. A mismatched or missing token
// stops the request before any business logic runs.

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        die('<h2>419 - Your session expired or this form was submitted from an untrusted source.</h2><p><a href="javascript:history.back()">Go back and try again</a></p>');
    }
}

/**
 * Two-step when 2FA is enabled: password success alone does NOT create a
 * logged-in session — it returns 'needs_2fa' and stashes a pending user ID
 * in the session, which complete_2fa_login() consumes after a valid code.
 * Returns: 'ok' | 'needs_2fa' | 'invalid' | 'inactive'
 *
 * Login is now by username (u.name) instead of email.
 */
function attempt_login(string $username, string $password): string
{
    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.email, u.password, u.role_id, u.is_active, u.locale, u.theme, u.two_factor_enabled, u.two_factor_secret, r.name AS role
         FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.name = ?
         LIMIT 1"
    );
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password'])) {
        return 'invalid';
    }

    // Check if user account is deactivated
    if ((int)$row['is_active'] === 0) {
        return 'inactive';
    }

    // Check if linked employee profile is terminated / inactive
    $empStmt = db()->prepare("SELECT status FROM employees WHERE user_id = ?");
    $empStmt->execute([$row['id']]);
    $empStatus = $empStmt->fetchColumn();
    if ($empStatus && in_array($empStatus, ['terminated', 'inactive'], true)) {
        return 'inactive';
    }

    if ($row['two_factor_enabled']) {
        $_SESSION['pending_2fa_user_id'] = (int) $row['id'];
        return 'needs_2fa';
    }

    unset($row['password'], $row['two_factor_enabled'], $row['two_factor_secret']);
    $_SESSION['user'] = $row;
    db()->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$row['id']]);
    if (function_exists('log_activity')) {
        log_activity('auth', 'login', 'users', (int) $row['id'], 'User logged in');
    }
    return 'ok';
}

/**
 * Second step of a 2FA login: verifies the submitted code against the
 * pending user set by attempt_login(), and only NOW creates the session.
 */
function complete_2fa_login(string $code): bool
{
    require_once __DIR__ . '/totp.php';

    $pendingId = $_SESSION['pending_2fa_user_id'] ?? null;
    if (!$pendingId) {
        return false;
    }

    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.email, u.role_id, u.locale, u.theme, u.two_factor_secret, r.name AS role
         FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.id = ? AND u.is_active = 1"
    );
    $stmt->execute([$pendingId]);
    $row = $stmt->fetch();

    if (!$row || !totp_verify_code($row['two_factor_secret'], $code)) {
        return false;
    }

    unset($_SESSION['pending_2fa_user_id'], $row['two_factor_secret']);
    $_SESSION['user'] = $row;
    db()->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$row['id']]);
    if (function_exists('log_activity')) {
        log_activity('auth', 'login', 'users', (int) $row['id'], 'User logged in (2FA)');
    }
    return true;
}

/**
 * Second step of a 2FA login via a one-time backup recovery code.
 */
function complete_2fa_recovery_login(string $recoveryCode): bool
{
    require_once __DIR__ . '/totp.php';

    $pendingId = $_SESSION['pending_2fa_user_id'] ?? null;
    if (!$pendingId) {
        return false;
    }

    if (!totp_verify_recovery_code((int) $pendingId, $recoveryCode)) {
        return false;
    }

    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.email, u.role_id, u.locale, u.theme, r.name AS role
         FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.id = ? AND u.is_active = 1"
    );
    $stmt->execute([$pendingId]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    unset($_SESSION['pending_2fa_user_id']);
    $_SESSION['user'] = $row;
    db()->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$row['id']]);
    if (function_exists('log_activity')) {
        log_activity('auth', 'login', 'users', (int) $row['id'], 'User logged in (2FA Recovery Code)');
    }
    return true;
}

function logout(): void
{
    if (current_user() && function_exists('log_activity')) {
        log_activity('auth', 'logout', 'users', (int) current_user()['id'], 'User logged out');
    }
    session_destroy();
}

// FR-X03: every module calls this after create/update/delete so nothing
// is silently unaudited (SAD section 18).
// log_activity() is defined in includes/functions.php
