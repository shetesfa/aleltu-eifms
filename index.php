<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$role = $user['role'] ?? '';

// Role-specific router: each role is directed straight to their dedicated dashboard
$dashboardRoutes = [
    'super_admin'             => '/aleltu-eifms/modules/admin/dashboard.php',
    'general_manager'         => '/aleltu-eifms/modules/admin/gm_dashboard.php',
    'dairy_manager'           => '/aleltu-eifms/modules/dairy/dashboard.php',
    'poultry_manager'         => '/aleltu-eifms/modules/poultry/dashboard.php',
    'fish_manager'            => '/aleltu-eifms/modules/fish/dashboard.php',
    'warehouse_keeper'        => '/aleltu-eifms/modules/warehouse/dashboard.php',
    'finance_officer'         => '/aleltu-eifms/modules/finance/dashboard.php',
    'hr_officer'              => '/aleltu-eifms/modules/employees/dashboard.php',
    'feed_production_manager' => '/aleltu-eifms/modules/feed_production/plots.php',
    'veterinary_officer'      => '/aleltu-eifms/modules/veterinary/dashboard.php',
    'distribution_officer'    => '/aleltu-eifms/modules/distribution/dispatches.php',
    'field_worker'            => '/aleltu-eifms/modules/employees/self_service.php',
];

$targetRoute = $dashboardRoutes[$role] ?? '/aleltu-eifms/modules/employees/self_service.php';
header("Location: {$targetRoute}");
exit;
