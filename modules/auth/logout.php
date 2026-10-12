<?php
require_once __DIR__ . '/../../includes/auth.php';
logout();
header('Location: /aleltu-eifms/modules/auth/login.php');
exit;
