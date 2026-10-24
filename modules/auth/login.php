<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (current_user()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if (isset($_GET['lang']) && array_key_exists($_GET['lang'], SUPPORTED_LOCALES)) {
    $_SESSION['guest_locale'] = $_GET['lang'];
}
if (isset($_GET['theme']) && in_array($_GET['theme'], ['light', 'dark'], true)) {
    $_SESSION['guest_theme'] = $_GET['theme'];
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $result = attempt_login($username, $password);
    if ($result === 'ok') {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    } elseif ($result === 'needs_2fa') {
        header('Location: ' . BASE_URL . '/modules/auth/two_factor_challenge.php');
        exit;
    } elseif ($result === 'inactive') {
        $error = 'Your employee account is currently inactive. Please contact management.';
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="<?= h(current_locale()) ?>" data-theme="<?= h(current_theme()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2b5c33">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/icons/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title><?= h(t('company.name')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body class="login-body">
<div class="login-wrap">
    <div class="login-card">
        <div class="login-controls">
            <a href="?theme=<?= current_theme()==='dark'?'light':'dark' ?>" class="pill-btn" title="Toggle theme">
                <?= current_theme()==='dark'?'☀️ Light':'🌙 Dark' ?>
            </a>
            <div class="lang-pills">
                <?php foreach (SUPPORTED_LOCALES as $code => $label): ?>
                    <a href="?lang=<?= h($code) ?>" class="pill-btn <?= current_locale()===$code ? 'active' : '' ?>"><?= h($label) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="brand-header">
            <div class="brand-icon">🐄</div>
            <h1><?= h(t('login.title')) ?></h1>
            <p class="sub"><?= h(t('login.subtitle')) ?></p>
        </div>

        <?php if ($error): ?>
            <div class="flash error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <div class="form-group">
                <label><?= h(t('login.username')) ?></label>
                <input type="text" name="username" required autofocus placeholder="<?= current_locale() === 'am' ? 'የተጠቃሚ ስም ያስገቡ' : 'Enter your username' ?>" value="<?= h($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><?= h(t('login.password')) ?></label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button class="btn btn-primary btn-block" type="submit"><?= h(t('login.button')) ?></button>
        </form>

        <div class="demo-notice">
            <span>🔑 <strong><?= current_locale() === 'am' ? 'የሙከራ መግቢያ' : 'Default Login' ?>:</strong> <?= current_locale() === 'am' ? 'የተጠቃሚ ስም' : 'Username' ?>: admin / Demo@2026</span>
        </div>
    </div>
</div>
</body>
</html>
