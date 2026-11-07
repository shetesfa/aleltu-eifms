<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'warehouse_keeper']);
header('Location: ' . BASE_URL . '/modules/warehouse/receiving.php?tab=manual');
exit;
