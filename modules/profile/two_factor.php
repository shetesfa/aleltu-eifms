<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/totp.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$userId = (int) current_user()['id'];
$stmt = db()->prepare("SELECT two_factor_enabled, two_factor_secret FROM users WHERE id = ?");
$stmt->execute([$userId]);
$row = $stmt->fetch();
$enabled = (bool) ($row['two_factor_enabled'] ?? false);

$error = null;
$freshRecoveryCodes = $_SESSION['fresh_2fa_recovery_codes'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Disable 2FA
    if (isset($_POST['disable'])) {
        db()->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?")->execute([$userId]);
        db()->prepare("DELETE FROM user_2fa_recovery_codes WHERE user_id = ?")->execute([$userId]);
        unset($_SESSION['fresh_2fa_recovery_codes']);
        log_activity('auth', 'update', 'users', $userId, 'Two-factor authentication disabled');
        flash('Two-factor authentication has been disabled.');
        header('Location: ' . BASE_URL . '/modules/profile/two_factor.php');
        exit;
    }

    // Confirm code and enable 2FA
    if (isset($_POST['confirm_code'])) {
        $pendingSecret = $_SESSION['pending_2fa_setup_secret'] ?? '';
        if ($pendingSecret && totp_verify_code($pendingSecret, $_POST['code'] ?? '')) {
            db()->prepare("UPDATE users SET two_factor_enabled = 1, two_factor_secret = ? WHERE id = ?")
                ->execute([$pendingSecret, $userId]);
            
            // Generate 8 initial one-time backup recovery codes
            $newRecoveryCodes = totp_generate_recovery_codes(8);
            totp_store_recovery_codes($userId, $newRecoveryCodes);
            $_SESSION['fresh_2fa_recovery_codes'] = $newRecoveryCodes;

            unset($_SESSION['pending_2fa_setup_secret']);
            log_activity('auth', 'update', 'users', $userId, 'Two-factor authentication enabled with recovery codes');
            flash('Two-factor authentication is now active on your account! Please save your recovery codes below.');
            header('Location: ' . BASE_URL . '/modules/profile/two_factor.php');
            exit;
        }
        $error = 'That code didn\'t match. Make sure your authenticator app is showing the current code, then try again.';
    }

    // Regenerate backup recovery codes
    if (isset($_POST['regenerate_codes']) && $enabled) {
        $newRecoveryCodes = totp_generate_recovery_codes(8);
        totp_store_recovery_codes($userId, $newRecoveryCodes);
        $_SESSION['fresh_2fa_recovery_codes'] = $newRecoveryCodes;
        log_activity('auth', 'update', 'users', $userId, '2FA recovery codes regenerated');
        flash('New recovery codes generated. Your previous recovery codes are now invalid.');
        header('Location: ' . BASE_URL . '/modules/profile/two_factor.php');
        exit;
    }
}

// Generate (or reuse) a pending secret for setup
$pendingSecret = null;
$qrDataUri = null;
if (!$enabled) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' || empty($_SESSION['pending_2fa_setup_secret'])) {
        $_SESSION['pending_2fa_setup_secret'] = totp_generate_secret();
    }
    $pendingSecret = $_SESSION['pending_2fa_setup_secret'];
    $qrDataUri = totp_generate_qr_data_uri($pendingSecret, current_user()['email']);
}

$remainingCodes = $enabled ? totp_remaining_recovery_codes($userId) : 0;

require_once __DIR__ . '/../../includes/header.php';
?>
<h2 class="mt-0">🔐 Two-Factor Authentication (2FA)</h2>

<?php if ($freshRecoveryCodes): ?>
    <div class="card" style="max-width:620px;border-left:5px solid var(--accent, #007bff);margin-bottom:24px;">
        <h3 style="margin-top:0;color:#28a745;">⚠️ Save Your Backup Recovery Codes</h3>
        <p>If you ever lose access to your phone or authenticator app, each of these 8 codes can be used <strong>once</strong> to log in. Store them in a secure place (password manager, encrypted notes, or safe printout).</p>
        
        <div style="background:var(--bg, #f4f6f9);padding:14px;border-radius:6px;font-family:monospace;font-size:16px;display:grid;grid-template-columns:1fr 1fr;gap:10px;text-align:center;letter-spacing:1px;margin:15px 0;">
            <?php foreach ($freshRecoveryCodes as $code): ?>
                <div style="padding:6px 10px;background:var(--card-bg, #fff);border:1px solid #ddd;border-radius:4px;font-weight:bold;">
                    <?= h($code) ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">
            <button type="button" class="btn" onclick="copyRecoveryCodes()">📋 Copy All Codes</button>
            <button type="button" class="btn secondary" onclick="downloadRecoveryCodes()">📥 Download (.txt)</button>
        </div>
    </div>
    <?php unset($_SESSION['fresh_2fa_recovery_codes']); ?>
<?php endif; ?>

<?php if ($enabled): ?>
    <div class="card" style="max-width:620px">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:15px;">
            <span style="font-size:32px;">🛡️</span>
            <div>
                <h3 style="margin:0;color:#28a745;">Two-Factor Authentication is Active</h3>
                <p class="text-muted" style="margin:4px 0 0;">Your account is protected by an authenticator application.</p>
            </div>
        </div>

        <div style="background:var(--bg, #f4f6f9);padding:15px;border-radius:6px;margin:15px 0;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <strong>Backup Recovery Codes</strong><br>
                    <span class="text-muted"><?= $remainingCodes ?> of 8 unused codes remaining</span>
                </div>
                <form method="post" style="margin:0;"><?= csrf_field() ?>
                    <input type="hidden" name="regenerate_codes" value="1">
                    <button class="btn secondary btn-sm" type="submit" onclick="return confirm('Regenerate backup codes? Any previously saved recovery codes will no longer work.');">
                        🔄 Generate New Codes
                    </button>
                </form>
            </div>
        </div>

        <div style="margin-top:25px;border-top:1px solid #eee;padding-top:15px;">
            <form method="post"><?= csrf_field() ?>
                <input type="hidden" name="disable" value="1">
                <button class="btn danger" type="submit" onclick="return confirm('Are you sure you want to turn off two-factor authentication for your account?');">
                    Disable Two-Factor Authentication
                </button>
            </form>
        </div>
    </div>

<?php else: ?>
    <div class="card" style="max-width:620px">
        <?php if ($error): ?><div class="flash error"><?= h($error) ?></div><?php endif; ?>

        <h3>Set Up Two-Factor Authentication</h3>
        <p class="text-muted">Two-Factor Authentication adds an extra layer of security. In addition to your password, you will enter a 6-digit verification code from your mobile device.</p>

        <div style="display:flex;gap:25px;align-items:center;margin:20px 0;flex-wrap:wrap;">
            <div style="text-align:center;background:#fff;padding:12px;border:1px solid #ddd;border-radius:8px;display:inline-block;">
                <img src="<?= $qrDataUri ?>" alt="2FA QR Code" width="180" height="180" style="display:block;margin:0 auto;">
                <small style="color:#666;display:block;margin-top:6px;">Scan with Authenticator App</small>
            </div>

            <div style="flex:1;min-width:240px;">
                <h4 style="margin-top:0;">Quick Setup Instructions:</h4>
                <ol style="padding-left:18px;margin:0 0 10px;line-height:1.5;">
                    <li>Open <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong>, or <strong>Authy</strong>.</li>
                    <li>Tap <strong>+</strong> and select <strong>Scan a QR code</strong> to scan the image on the left.</li>
                    <li><em>Or enter this key manually:</em></li>
                </ol>
                <div style="background:var(--bg, #f4f6f9);padding:8px 12px;border-radius:4px;font-family:monospace;letter-spacing:2px;font-weight:bold;font-size:15px;word-break:break-all;">
                    <?= h(chunk_split($pendingSecret, 4, ' ')) ?>
                </div>
            </div>
        </div>

        <div style="background:var(--bg, #f4f6f9);padding:18px;border-radius:6px;margin-top:20px;">
            <h4 style="margin-top:0;">Confirm Verification Code</h4>
            <p style="margin-bottom:12px;">Enter the 6-digit code currently shown in your authenticator app to complete activation:</p>
            
            <form method="post"><?= csrf_field() ?>
                <input type="hidden" name="confirm_code" value="1">
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus
                           placeholder="000000"
                           style="text-align:center;font-size:24px;letter-spacing:6px;width:170px;padding:6px 10px;">
                    <button class="btn" type="submit">Verify & Enable 2FA</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
function copyRecoveryCodes() {
    const codes = <?= json_encode($freshRecoveryCodes ?? []) ?>.join("\n");
    navigator.clipboard.writeText(codes).then(() => {
        alert("Recovery codes copied to clipboard!");
    }).catch(() => {
        prompt("Copy your recovery codes:", codes);
    });
}

function downloadRecoveryCodes() {
    const codes = <?= json_encode($freshRecoveryCodes ?? []) ?>.join("\n");
    const content = "Aleltu Farm EIFMS - 2FA Backup Recovery Codes\n" +
                    "Account: <?= h(current_user()['email']) ?>\n" +
                    "Generated: <?= date('Y-m-d H:i:s') ?>\n\n" +
                    "Each code can only be used ONCE if you lose your phone.\n\n" +
                    codes + "\n";
    const blob = new Blob([content], { type: 'text/plain' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'aleltu-farm-recovery-codes.txt';
    link.click();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
