<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        set_user_locale(current_user()['id'], $_POST['locale']);
    } catch (Throwable $e) {
        // Invalid locale submitted — just ignore and fall through to the redirect.
    }
}

$back = $_SERVER['HTTP_REFERER'] ?? '/aleltu-eifms/index.php';
header('Location: ' . $back);
exit;
