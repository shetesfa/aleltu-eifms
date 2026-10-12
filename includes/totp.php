<?php
/**
 * Aleltu Farm EIFMS - TOTP (Time-based One-Time Password) implementation.
 *
 * Pure PHP, RFC 6238 / RFC 4226 compliant, zero dependencies — works with
 * any standard authenticator app (Google Authenticator, Authy, Microsoft
 * Authenticator, etc.) since they all implement this same open standard.
 * No QR code image library is used (that would need a dependency); instead
 * the setup page shows the secret key for manual entry, which every
 * authenticator app supports as an alternative to scanning.
 */

function totp_generate_secret(int $length = 20): string
{
    return totp_base32_encode(random_bytes($length));
}

function totp_base32_encode(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($data) as $char) {
        $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $output = '';
    foreach (str_split($bits, 5) as $chunk) {
        $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $output .= $alphabet[bindec($chunk)];
    }
    return $output;
}

function totp_base32_decode(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $b32));
    $bits = '';
    foreach (str_split($b32) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) continue;
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $output = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) < 8) break; // drop incomplete trailing bits, standard TOTP padding behavior
        $output .= chr(bindec($byte));
    }
    return $output;
}

/**
 * Generates the current code for a given secret. $digits/$period/$time are
 * only overridden by tests — real call sites always use the 6-digit,
 * 30-second defaults every authenticator app assumes.
 */
function totp_generate_code(string $secret, int $digits = 6, int $period = 30, ?int $time = null): string
{
    $time = $time ?? time();
    $counter = intdiv($time, $period);
    $binCounter = pack('N*', 0) . pack('N*', $counter); // 8-byte big-endian counter

    $key = totp_base32_decode($secret);
    $hash = hash_hmac('sha1', $binCounter, $key, true);

    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
    $truncated = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    );

    $code = $truncated % (10 ** $digits);
    return str_pad((string) $code, $digits, '0', STR_PAD_LEFT);
}

/**
 * Verifies a submitted code, tolerating +/- 1 time step (30s) either side
 * so a slightly-out-of-sync phone clock or slow typing doesn't lock
 * someone out — this is standard, expected TOTP verification behavior.
 */
function totp_verify_code(string $secret, string $submittedCode, int $window = 1): bool
{
    $submittedCode = trim($submittedCode);
    if (!preg_match('/^\d{6}$/', $submittedCode)) {
        return false;
    }
    for ($i = -$window; $i <= $window; $i++) {
        $expected = totp_generate_code($secret, 6, 30, time() + ($i * 30));
        if (hash_equals($expected, $submittedCode)) {
            return true;
        }
    }
    return false;
}

function totp_provisioning_uri(string $secret, string $accountEmail, string $issuer = 'Aleltu Farm EIFMS'): string
{
    $label = rawurlencode("{$issuer}:{$accountEmail}");
    $params = http_build_query([
        'secret' => $secret,
        'issuer' => $issuer,
        'algorithm' => 'SHA1',
        'digits' => 6,
        'period' => 30,
    ]);
    return "otpauth://totp/{$label}?{$params}";
}

/**
 * Generates an offline base64 data URI PNG image for a given secret and email.
 */
function totp_generate_qr_data_uri(string $secret, string $accountEmail, string $issuer = 'Aleltu Farm EIFMS'): string
{
    require_once __DIR__ . '/qrcode.php';
    $uri = totp_provisioning_uri($secret, $accountEmail, $issuer);
    $qr = new QRCode($uri, ['sf' => 5, 'p' => 8]);
    $img = $qr->render_image();
    ob_start();
    imagepng($img);
    $pngData = ob_get_clean();
    imagedestroy($img);
    return 'data:image/png;base64,' . base64_encode($pngData);
}

/**
 * Generates a list of random one-time recovery codes (format: XXXX-XXXX).
 */
function totp_generate_recovery_codes(int $count = 8): array
{
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $codes = [];
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $count; $i++) {
        $part1 = '';
        $part2 = '';
        for ($j = 0; $j < 4; $j++) {
            $part1 .= $chars[random_int(0, $max)];
            $part2 .= $chars[random_int(0, $max)];
        }
        $codes[] = "{$part1}-{$part2}";
    }
    return $codes;
}

/**
 * Stores hashed recovery codes for a user, replacing any previous unused codes.
 */
function totp_store_recovery_codes(int $userId, array $plainCodes): void
{
    $pdo = db();
    $pdo->prepare("DELETE FROM user_2fa_recovery_codes WHERE user_id = ?")->execute([$userId]);
    $stmt = $pdo->prepare("INSERT INTO user_2fa_recovery_codes (user_id, code_hash) VALUES (?, ?)");
    foreach ($plainCodes as $code) {
        $clean = strtoupper(str_replace([' ', '-'], '', trim($code)));
        $hash = password_hash($clean, PASSWORD_DEFAULT);
        $stmt->execute([$userId, $hash]);
    }
}

/**
 * Verifies a submitted recovery code against unused codes for the user.
 * If valid, marks the code as used and returns true.
 */
function totp_verify_recovery_code(int $userId, string $submittedCode): bool
{
    $clean = strtoupper(str_replace([' ', '-'], '', trim($submittedCode)));
    if (strlen($clean) !== 8) {
        return false;
    }

    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, code_hash FROM user_2fa_recovery_codes WHERE user_id = ? AND used_at IS NULL");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        if (password_verify($clean, $row['code_hash'])) {
            $pdo->prepare("UPDATE user_2fa_recovery_codes SET used_at = NOW() WHERE id = ?")->execute([$row['id']]);
            return true;
        }
    }
    return false;
}

/**
 * Returns count of remaining unused recovery codes for a user.
 */
function totp_remaining_recovery_codes(int $userId): int
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM user_2fa_recovery_codes WHERE user_id = ? AND used_at IS NULL");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

