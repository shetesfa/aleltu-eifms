<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$role = $user['role'] ?? '';

// Role-specific router: each role is directed straight to their dedicated dashboard
$dashboardRoutes = [
    'super_admin'             => url('/modules/admin/dashboard.php'),
    'general_manager'         => url('/modules/admin/gm_dashboard.php'),
    'dairy_manager'           => url('/modules/dairy/dashboard.php'),
    'poultry_manager'         => url('/modules/poultry/dashboard.php'),
    'fish_manager'            => url('/modules/fish/dashboard.php'),
    'warehouse_keeper'        => url('/modules/warehouse/dashboard.php'),
    'finance_officer'         => url('/modules/finance/dashboard.php'),
    'hr_officer'              => url('/modules/employees/dashboard.php'),
    'feed_production_manager' => url('/modules/feed_production/plots.php'),
    'veterinary_officer'      => url('/modules/veterinary/dashboard.php'),
    'distribution_officer'    => url('/modules/distribution/dispatches.php'),
    'field_worker'            => url('/modules/employees/self_service.php'),
];

$targetRoute = $dashboardRoutes[$role] ?? url('/modules/employees/self_service.php');
header("Location: {$targetRoute}");
exit;
