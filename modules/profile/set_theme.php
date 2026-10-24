<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $next = current_theme() === 'dark' ? 'light' : 'dark';
    set_user_theme(current_user()['id'], $next);
}

$back = $_SERVER['HTTP_REFERER'] ?? BASE_URL . '/index.php';
header('Location: ' . $back);
exit;
