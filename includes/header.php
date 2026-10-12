<?php
require_once __DIR__ . '/auth.php';
require_login();
$user = current_user();
$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="<?= h(current_locale()) ?>" data-theme="<?= h(current_theme()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2b5c33">
    <link rel="manifest" href="/aleltu-eifms/manifest.json">
    <link rel="apple-touch-icon" href="/aleltu-eifms/assets/icons/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= h(t('company.name')) ?></title>
    <link rel="stylesheet" href="/aleltu-eifms/assets/css/app.css?v=<?= time() ?>">
</head>
<body>
<div class="topbar" style="position:fixed;top:0;left:0;right:0;height:55px;z-index:1000">
    <div class="brand">
        <button type="button" id="navToggle" class="nav-toggle" aria-label="Menu" aria-controls="sidebar" aria-expanded="false">☰</button>
        🌾 <span class="brand-text"><?= h(t('company.name')) ?></span>
    </div>
    <div class="topbar-actions">
        <form method="post" action="/aleltu-eifms/modules/profile/set_theme.php" style="display:inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn secondary" style="padding:4px 10px" title="Toggle dark mode">
                <?= current_theme() === 'dark' ? '☀️' : '🌙' ?>
            </button>
        </form>
        <a href="/aleltu-eifms/modules/notifications.php" style="position:relative">
            🔔<span class="label-text"> <?= h(t('nav.notifications')) ?></span>
            <?php $unread = notifications_unread_count(); ?>
            <?php if ($unread > 0): ?>
                <span style="background:#b3261e;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px"><?= $unread ?></span>
            <?php endif; ?>
        </a>
        <form method="post" action="/aleltu-eifms/modules/profile/set_locale.php" style="display:inline">
            <?= csrf_field() ?>
            <select name="locale" onchange="this.form.submit()" style="padding:4px 6px;border-radius:4px;border:1px solid #4a7c53;background:#fff;color:#24291f">
                <?php foreach (SUPPORTED_LOCALES as $code => $label): ?>
                    <option value="<?= h($code) ?>" <?= current_locale() === $code ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <span class="label-text"><?= h($user['name']) ?> (<?= h($user['role']) ?>)</span>
        <a href="/aleltu-eifms/modules/profile/change_password.php" title="<?= h(t('common.password', 'Password')) ?>">🔑</a>
        <a href="/aleltu-eifms/modules/profile/two_factor.php" title="2FA">🔐</a>
        <a href="/aleltu-eifms/modules/auth/logout.php"><span class="label-text"><?= h(t('common.logout')) ?></span><span class="icon-only">🚪</span></a>
    </div>
</div>
<div id="navOverlay" class="nav-overlay"></div>
<?php
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';

$sidebarMenu = [
    [
        'title' => t('section.dairy', 'Dairy Management'),
        'items' => [
            ['url' => '/aleltu-eifms/modules/dairy/dashboard.php',        'label' => '📊 ' . t('nav.dairy_dashboard', 'Dairy Dashboard'),       'roles' => ['general_manager', 'dairy_manager', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/dairy/cows.php',             'label' => '🐄 ' . t('nav.cows', 'Cows'),                             'roles' => ['general_manager', 'dairy_manager', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/dairy/milking.php',          'label' => '🥛 ' . t('nav.milking', 'Milking Entry'),                  'roles' => ['general_manager', 'dairy_manager', 'field_worker', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/dairy/cow_history.php',      'label' => '📋 ' . t('nav.cow_history', 'Cow Full History'),           'roles' => ['general_manager', 'dairy_manager', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/dairy/material_request.php', 'label' => '📦 ' . t('reqs.title_dairy', 'Feed & Supplies Request'),   'roles' => ['dairy_manager', 'general_manager', 'super_admin']],
        ]
    ],
    [
        'title' => t('section.poultry', 'Poultry Farm'),
        'items' => [
            ['url' => '/aleltu-eifms/modules/poultry/dashboard.php',        'label' => '📊 ' . t('nav.poultry_dashboard', 'Poultry Dashboard'),          'roles' => ['general_manager', 'poultry_manager', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/poultry/egg_entry.php',        'label' => '🥚 ' . t('nav.egg_entry', 'Egg Collection Entry'),               'roles' => ['general_manager', 'poultry_manager', 'field_worker', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/poultry/flocks.php',           'label' => '🐓 ' . t('nav.flocks', 'Flocks & Mortality'),                    'roles' => ['general_manager', 'poultry_manager', 'field_worker', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/poultry/material_request.php', 'label' => '📦 ' . t('reqs.title_poultry', 'Feed & Supplies Request'),        'roles' => ['poultry_manager', 'general_manager', 'super_admin']],
        ]
    ],
    [
        'title' => t('section.fish_farm', 'Fish Farm'),
        'items' => [
            ['url' => '/aleltu-eifms/modules/fish/dashboard.php',        'label' => '📊 ' . t('nav.fish_dashboard', 'Fish Dashboard'),         'roles' => ['general_manager', 'fish_manager', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/fish/ponds.php',            'label' => '🐟 ' . t('nav.ponds', 'Fish Ponds'),                      'roles' => ['general_manager', 'fish_manager', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/fish/material_request.php', 'label' => '📦 ' . t('reqs.title_fish', 'Feed & Supplies Request'),   'roles' => ['fish_manager', 'general_manager', 'super_admin']],
        ]
    ],
    [
        'title' => t('section.feed_production', 'Feed Production'),
        'items' => [
            ['url' => '/aleltu-eifms/modules/feed_production/plots.php',        'label' => '🌾 ' . t('nav.plots', 'Crop Plots'),                'roles' => ['general_manager', 'feed_production_manager', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/feed_production/distribution.php', 'label' => '🚚 ' . t('nav.distribution_requests', 'Send to Warehouse'), 'roles' => ['general_manager', 'feed_production_manager', 'super_admin']],
        ]
    ],
    [
        'title' => t('section.warehouse', 'Warehouse & Store'),
        'items' => [
            ['url' => '/aleltu-eifms/modules/warehouse/dashboard.php',    'label' => '📊 ' . t('nav.wh_dashboard', 'Warehouse Dashboard'),      'roles' => ['general_manager', 'warehouse_keeper', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/warehouse/receiving.php',    'label' => '📥 ' . t('nav.receiving', 'Receiving Control'),            'roles' => ['general_manager', 'warehouse_keeper', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/warehouse/requisitions.php', 'label' => '📦 ' . t('reqs.store_queue', 'Material Requests Queue'),   'roles' => ['general_manager', 'warehouse_keeper', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/warehouse/stock.php',        'label' => '📊 ' . t('nav.stock', 'Stock Inventory'),                  'roles' => ['general_manager', 'warehouse_keeper', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/warehouse/stock_adjust.php', 'label' => '⚖️ ' . t('nav.stock_adjust', 'Stock Adjustment'),         'roles' => ['general_manager', 'warehouse_keeper', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/warehouse/issue.php',        'label' => '📤 ' . t('nav.issue', 'Direct Issue'),                    'roles' => ['general_manager', 'warehouse_keeper', 'super_admin']],
        ]
    ],
    [
        'title' => t('section.distribution', 'Distribution & Sales'),
        'items' => [
            ['url' => '/aleltu-eifms/modules/distribution/vehicles.php',   'label' => '🚚 ' . t('nav.vehicles', 'Vehicles Registry'),             'roles' => ['general_manager', 'warehouse_keeper', 'distribution_officer', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/distribution/dispatches.php', 'label' => '💰 ' . t('nav.dispatches', 'Dispatches & Sales Payments'), 'roles' => ['general_manager', 'warehouse_keeper', 'finance_officer', 'distribution_officer', 'super_admin']],
        ]
    ],
    [
        'title' => t('section.veterinary', 'Veterinary Services'),
        'items' => [
            ['url' => '/aleltu-eifms/modules/veterinary/dashboard.php',    'label' => '🩺 ' . t('nav.vet_overview', 'Vet Overview'),             'roles' => ['general_manager', 'veterinary_officer', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/veterinary/health.php',       'label' => '📋 ' . t('nav.health_records', 'Health Records'),         'roles' => ['general_manager', 'veterinary_officer', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/veterinary/vaccinations.php', 'label' => '💉 ' . t('nav.vaccinations', 'Vaccinations'),             'roles' => ['general_manager', 'veterinary_officer', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/veterinary/disease.php',      'label' => '⚠️ ' . t('nav.disease_incidents', 'Disease Incidents'),  'roles' => ['general_manager', 'veterinary_officer', 'super_admin']],
        ]
    ],
    [
        'title' => t('section.employees', 'Human Resources'),
        'items' => [
            ['url' => '/aleltu-eifms/modules/employees/dashboard.php',  'label' => '📊 ' . t('nav.hr_dashboard', 'HR Dashboard'),       'roles' => ['general_manager', 'hr_officer', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/employees/list.php',       'label' => '👥 ' . t('nav.employees', 'Employees List'),        'roles' => ['general_manager', 'hr_officer', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/employees/attendance.php', 'label' => '📅 ' . t('nav.attendance', 'Staff Attendance'),    'roles' => ['general_manager', 'hr_officer', 'field_worker', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/employees/leave.php',      'label' => '🏖️ ' . t('nav.leave_requests', 'Leave Requests'), 'roles' => ['general_manager', 'hr_officer', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/employees/tasks.php',      'label' => '📋 ' . t('nav.tasks', 'Assigned Tasks'),           'roles' => ['general_manager', 'hr_officer', 'field_worker', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/employees/id_card.php',    'label' => '🪪 ' . t('nav.id_card', 'Digital ID Card'),        'roles' => null],
        ]
    ],
    [
        'title' => t('section.finance', 'Finance & Accounting'),
        'items' => [
            ['url' => '/aleltu-eifms/modules/finance/dashboard.php',        'label' => '💰 ' . (current_locale()==='am'?'የፋይናንስና ካዝና ማዕከል':'Cash & Bank Dashboard'), 'roles' => ['general_manager', 'finance_officer', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/finance/income_statement.php', 'label' => '📊 ' . (current_locale()==='am'?'ቀላል የትርፍና ኪሳራ መግለጫ':'Simple Profit & Loss (P&L)'), 'roles' => ['general_manager', 'finance_officer', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/finance/expenses.php',         'label' => '💸 ' . (current_locale()==='am'?'ዕለታዊ ወጪዎች':'Daily Expenses'), 'roles' => ['general_manager', 'finance_officer', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/finance/purchasing.php',       'label' => '🛒 ' . (current_locale()==='am'?'የግዢ ትዕዛዞች':'Purchasing & POs'), 'roles' => ['general_manager', 'finance_officer', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/finance/receivables.php',      'label' => '👥 ' . (current_locale()==='am'?'የደንበኞች ዱቤና ተሰብሳቢ':'Customer Receivables & Credit'), 'roles' => ['general_manager', 'finance_officer', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/finance/payroll.php',          'label' => '💳 ' . (current_locale()==='am'?'የሰራተኞች ወርሃዊ ደሞዝ':'Staff Payroll & Salaries'), 'roles' => ['general_manager', 'finance_officer', 'super_admin']],
        ]
    ],
    [
        'title' => t('section.system', 'System & Admin'),
        'items' => [
            ['url' => '/aleltu-eifms/modules/admin/dashboard.php',        'label' => '🛡️ ' . t('nav.super_admin', 'Super Admin Control'),  'roles' => ['super_admin']],
            ['url' => '/aleltu-eifms/modules/admin/gm_dashboard.php',     'label' => '🏢 ' . t('nav.gm_overview', 'GM Overview'),           'roles' => ['general_manager', 'super_admin']],
            ['url' => '/aleltu-eifms/modules/admin/users.php',            'label' => '👤 ' . t('nav.user_mgmt', 'User Management'),         'roles' => ['super_admin']],
            ['url' => '/aleltu-eifms/modules/admin/settings.php',         'label' => '⚙️ ' . t('nav.settings', 'System Settings'),         'roles' => ['super_admin']],
            ['url' => '/aleltu-eifms/modules/reports.php',                'label' => '📈 ' . t('nav.reports', 'Executive Reports'),          'roles' => ['super_admin', 'general_manager']],
            ['url' => '/aleltu-eifms/modules/module_reports.php',         'label' => '📋 ' . t('nav.module_reports', 'Module Reports'),     'roles' => null],
            ['url' => '/aleltu-eifms/modules/employees/self_service.php', 'label' => '👤 ' . t('nav.self_service', 'My Self-Service'),      'roles' => null],
            ['url' => '/aleltu-eifms/modules/notifications.php',          'label' => '🔔 ' . t('nav.notifications', 'Notifications'),       'roles' => null],
            ['url' => '/aleltu-eifms/modules/audit_log.php',              'label' => '📜 ' . t('nav.audit_log', 'Audit Trail'),             'roles' => ['super_admin', 'general_manager']],
        ]
    ],
];

// Map each role to their dedicated dashboard
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
$targetDashboard = $dashboardRoutes[$user['role'] ?? ''] ?? '/aleltu-eifms/modules/employees/self_service.php';
$isDashboardActive = ($currentScript === '/aleltu-eifms/index.php' || $currentScript === $targetDashboard || in_array($currentScript, array_values($dashboardRoutes)));
?>
<div class="layout" style="margin-top:55px;display:flex">
    <div class="sidebar" id="sidebar" style="position:fixed;top:55px;left:0;bottom:0;width:250px;overflow-y:auto;z-index:900;background:var(--card);border-right:1px solid var(--border);padding:20px 12px;box-sizing:border-box">
<?php if (($user['role'] ?? '') === 'super_admin'): ?>
            <a href="/aleltu-eifms/modules/admin/dashboard.php" class="<?= ($currentScript === '/aleltu-eifms/modules/admin/dashboard.php' || $currentScript === '/aleltu-eifms/index.php') ? 'active' : '' ?>">📊 <?= h(t('nav.dashboard')) ?></a>
        <?php endif; ?>
        <?php foreach ($sidebarMenu as $sec): ?>
            <?php
            $visibleItems = array_filter($sec['items'], function($item) {
                return empty($item['roles']) || has_role($item['roles']);
            });
            ?>
            <?php if (!empty($visibleItems)): ?>
                <div class="section"><?= h($sec['title']) ?></div>
                <?php foreach ($visibleItems as $item): ?>
                    <?php $isActive = ($currentScript === $item['url']); ?>
                    <a href="<?= h($item['url']) ?>" class="<?= $isActive ? 'active' : '' ?>"><?= h($item['label']) ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <div class="content" style="margin-left:250px;flex:1;padding:28px 36px;max-width:1400px;min-width:0;box-sizing:border-box">
        <?php foreach ($flashes as $f): ?>
            <div class="flash <?= h($f['type']) ?>"><?= h($f['msg']) ?></div>
        <?php endforeach; ?>
