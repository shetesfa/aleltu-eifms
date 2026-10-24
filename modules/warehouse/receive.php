<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
header('Location: ' . BASE_URL . '/modules/warehouse/receiving.php?tab=manual');
exit;
