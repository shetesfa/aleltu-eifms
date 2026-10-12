<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
header('Location: /aleltu-eifms/modules/warehouse/receiving.php?tab=manual');
exit;
