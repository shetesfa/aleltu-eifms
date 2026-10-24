<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (current_user()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
if (empty($_SESSION['pending_2fa_user_id'])) {
    header('Location: ' . BASE_URL . '/modules/auth/login.php');
    exit;
}

$isRecoveryMode = (isset($_GET['mode']) && $_GET['mode'] === 'recovery');
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['use_recovery_code'])) {
        $recoveryCode = trim($_POST['recovery_code'] ?? '');
        if (complete_2fa_recovery_login($recoveryCode)) {
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
        $error = 'Invalid or already used recovery code. Please check your backup codes and try again.';
        $isRecoveryMode = true;
    } else {
        $code = trim($_POST['code'] ?? '');
        if (complete_2fa_login($code)) {
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
        $error = 'Invalid or expired verification code. Check your authenticator app and try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="<?= h(current_locale()) ?>" data-theme="<?= h(current_theme()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(t('company.name')) ?> — 2FA</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body class="login-body">
<div class="login-wrap">
    <div class="login-card">
        <div class="brand-header">
            <div class="brand-icon">🔐</div>
            <h1><?= $isRecoveryMode ? h(t('login.recovery_title', 'Recovery Verification')) : h(t('login.two_factor_title', 'Verification Required')) ?></h1>
            <p class="sub">
                <?= $isRecoveryMode 
                    ? h(t('login.recovery_prompt', 'Enter one of your 8-character backup recovery codes.')) 
                    : h(t('login.two_factor_prompt', 'Enter the 6-digit code from your authenticator app.')) ?>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="flash error"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if ($isRecoveryMode): ?>
            <!-- Backup Recovery Code Form -->
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="use_recovery_code" value="1">
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'የአንድ ጊዜ የመጠባበቂያ ኮድ' : 'One-Time Recovery Code' ?></label>
                    <input type="text" name="recovery_code" maxlength="10" required autofocus
                           placeholder="ABCD-1234"
                           style="text-align:center;font-size:22px;letter-spacing:4px;text-transform:uppercase;font-family:monospace;">
                    <small class="text-muted" style="display:block;margin-top:6px;text-align:center;">
                        <?= current_locale() === 'am' ? 'እያንዳንዱ የመጠባበቂያ ኮድ የሚያገለግለው ለአንድ ጊዜ ብቻ ነው።' : 'Each recovery code can only be used once.' ?>
                    </small>
                </div>
                <button class="btn btn-primary btn-block" type="submit"><?= h(t('login.verify_button', 'Verify Recovery Code')) ?></button>
            </form>

            <div style="margin-top:20px;text-align:center;">
                <a href="?mode=app" style="color:var(--accent, #007bff);text-decoration:none;">
                    &larr; <?= h(t('login.use_app', 'Use Authenticator App Instead')) ?>
                </a>
            </div>

        <?php else: ?>
            <!-- Regular TOTP Form -->
            <form method="post">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'የማረጋገጫ ኮድ' : 'Authentication Code' ?></label>
                    <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus
                           placeholder="000000"
                           style="text-align:center;font-size:24px;letter-spacing:6px;font-family:monospace;">
                </div>
                <button class="btn btn-primary btn-block" type="submit"><?= h(t('login.verify_button', 'Verify')) ?></button>
            </form>

            <div style="margin-top:20px;text-align:center;">
                <a href="?mode=recovery" style="color:var(--accent, #007bff);font-size:14px;text-decoration:none;">
                    <?= h(t('login.use_recovery', 'Lost your device? Use a backup recovery code')) ?> &rarr;
                </a>
            </div>
        <?php endif; ?>

        <p class="text-muted" style="margin-top:25px;text-align:center;border-top:1px solid #eee;padding-top:15px;">
            <a href="<?= BASE_URL ?>/modules/auth/login.php" style="color:#888;text-decoration:none;">&larr; Back to login</a>
        </p>
    </div>
</div>
</body>
</html>
