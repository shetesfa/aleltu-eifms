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
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/icons/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= h(t('company.name')) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css?v=<?= time() ?>">
</head>
<body>
<div class="topbar">
    <div class="brand" title="<?= h(t('company.name')) ?>">
        <button type="button" id="navToggle" class="nav-toggle" aria-label="Menu" aria-controls="sidebar" aria-expanded="false">☰</button>
        <a href="<?= BASE_URL ?>/index.php" style="color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:8px;max-width:100%;overflow:hidden">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0" aria-hidden="true">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>
            <span class="brand-text"><?= current_locale()==='am' ? 'ተክሉ ጌታቸው የከብት እርባታ እና የወተት ተዋጽኦ ማቀነባበሪያ ድርጅት' : 'Teklu Getachew Cattle Breeding & Dairy Processing' ?></span>
        </a>
    </div>
    <!-- Persistent Ethiopian Calendar & Clock Header Widget -->
    <div class="eth-header-widget">
        <span class="eth-date-part" style="display:inline-flex;align-items:center;gap:4px">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            <?= eth_date(date('Y-m-d'), 'medium') ?>
        </span>
        <span class="eth-sep-part" style="opacity:0.35">|</span>
        <span id="ethLiveClock" class="eth-clock-part" style="display:inline-flex;align-items:center;gap:4px">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            <?= eth_time(date('H:i:s')) ?>
        </span>
    </div>
    <div class="topbar-actions">
        <form method="post" action="<?= BASE_URL ?>/modules/profile/set_theme.php" style="display:inline;margin:0">
            <?= csrf_field() ?>
            <button type="submit" class="btn secondary topbar-btn" title="Toggle theme" style="padding:4px 8px;margin:0;line-height:1;display:inline-flex;align-items:center">
                <?php if (current_theme() === 'dark'): ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                <?php else: ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                <?php endif; ?>
            </button>
        </form>
        <a href="<?= BASE_URL ?>/modules/notifications.php" class="topbar-btn" title="<?= h(t('nav.notifications', 'ማሳወቂያዎች')) ?>" style="position:relative;padding:5px 8px;margin:0;text-decoration:none;display:inline-flex;align-items:center">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            <?php $unread = notifications_unread_count(); ?>
            <?php if ($unread > 0): ?>
                <span class="topbar-badge"><?= $unread ?></span>
            <?php endif; ?>
        </a>
        <form method="post" action="<?= BASE_URL ?>/modules/profile/set_locale.php" style="display:inline;margin:0">
            <?= csrf_field() ?>
            <select name="locale" onchange="this.form.submit()" class="topbar-locale-select">
                <?php foreach (SUPPORTED_LOCALES as $code => $label): ?>
                    <option value="<?= h($code) ?>" <?= current_locale() === $code ? 'selected' : '' ?>><?= $code === 'am' ? 'አማርኛ' : 'EN' ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <span class="topbar-user-badge" title="<?= h($user['name']) ?> (<?= h($user['role']) ?>)" style="display:inline-flex;align-items:center;gap:5px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            <span class="user-name"><?= h($user['name']) ?></span>
        </span>
        <a href="<?= BASE_URL ?>/modules/profile/change_password.php" class="topbar-btn" title="<?= h(t('common.password', 'Password')) ?>" style="padding:4px 6px;margin:0;display:inline-flex;align-items:center">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="4.5"></circle><path d="m21 2-9.6 9.6"></path><path d="m15.5 7.5 3 3L22 7l-3-3"></path></svg>
        </a>
        <a href="<?= BASE_URL ?>/modules/profile/two_factor.php" class="topbar-btn" title="2FA" style="padding:4px 6px;margin:0;display:inline-flex;align-items:center">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        </a>
        <a href="<?= BASE_URL ?>/modules/auth/logout.php" class="topbar-logout-btn" title="<?= h(t('common.logout', 'ውጣ')) ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span class="logout-label"><?= current_locale()==='am' ? 'ውጣ' : 'Logout' ?></span>
        </a>
    </div>
</div>
<div id="navOverlay" class="nav-overlay"></div>
<?php
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
$userRole = $user['role'] ?? '';

if ($userRole === 'super_admin') {
    // ═════════════════════════════════════════════════════════════════════
    // 1. SUPER ADMIN / EXECUTIVE (EXECUTIVE CONTROL & ADVANCED REPORTING)
    // ═════════════════════════════════════════════════════════════════════
    $sidebarMenu = [
        [
            'title' => current_locale() === 'am' ? 'የከፍተኛ አመራር ትንታኔ' : 'Executive Command & Analytics',
            'items' => [
                ['url' => BASE_URL . '/modules/admin/dashboard.php',    'label' => (current_locale()==='am'?'የሱፐር አድሚን ማዕከል':'Super Admin Control'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/admin/analytics.php',    'label' => (current_locale()==='am'?'የእርሻ አፈጻጸም ትንተና':'Performance Analytics'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/admin/gm_dashboard.php', 'label' => (current_locale()==='am'?'የስራ አስኪያጅ ዳሽቦርድ እይታ':'GM Operations View'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/reports.php',            'label' => (current_locale()==='am'?'የከፍተኛ አመራር ሪፖርት':'Executive Reports'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/module_reports.php',     'label' => (current_locale()==='am'?'የስራ ክፍሎች ማጠቃለያ':'Departmental Reports'), 'roles' => ['super_admin']],
            ]
        ],
        [
            'title' => current_locale() === 'am' ? 'የፋይናንስና ካዝና ቁጥጥር' : 'Financial Oversight & Treasury',
            'items' => [
                ['url' => BASE_URL . '/modules/finance/dashboard.php',        'label' => (current_locale()==='am'?'የፋይናንስና ካዝና ማዕከል':'Cash & Bank Treasury'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/finance/income_statement.php', 'label' => (current_locale()==='am'?'የትርፍና ኪሳራ መግለጫ (P&L)':'Income Statement (P&L)'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/finance/expenses.php',         'label' => (current_locale()==='am'?'ወጪዎችና ማጽደቂያዎች':'Expenses & Approvals'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/finance/payroll.php',          'label' => (current_locale()==='am'?'የሰራተኞች ደሞዝ ክፍያ':'Staff Payroll Oversight'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/finance/transfer.php',         'label' => (current_locale()==='am'?'የአካውንቶች ዝውውር':'Internal Transfers'), 'roles' => ['super_admin']],
            ]
        ],
        [
            'title' => current_locale() === 'am' ? 'የሰራተኞችና HR ቁጥጥር' : 'Workforce & HR Oversight',
            'items' => [
                ['url' => BASE_URL . '/modules/employees/dashboard.php',  'label' => (current_locale()==='am'?'የHR ማዕከል':'HR Command Center'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/employees/list.php',       'label' => (current_locale()==='am'?'የሰራተኞች ሙሉ ዝርዝር':'All Staff Directory'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/employees/attendance.php', 'label' => (current_locale()==='am'?'የመገኘትና ሰዓት ቁጥጥር':'Staff Attendance'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/employees/leave.php',      'label' => (current_locale()==='am'?'የፈቃድ ውሳኔዎች':'Leave Approvals'), 'roles' => ['super_admin']],
            ]
        ],
        [
            'title' => current_locale() === 'am' ? 'የስራ ክፍሎች አጠቃላይ እይታ' : 'Farm Units Overview',
            'items' => [
                ['url' => BASE_URL . '/modules/dairy/dashboard.php',          'label' => (current_locale()==='am'?'የወተት ምርት ማጠቃለያ':'Dairy Overview'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/poultry/dashboard.php',        'label' => (current_locale()==='am'?'የዶሮ እርባታ ማጠቃለያ':'Poultry Overview'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/fish/dashboard.php',           'label' => (current_locale()==='am'?'የዓሳ እርባታ ማጠቃለያ':'Fish Overview'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/feed_production/plots.php',    'label' => (current_locale()==='am'?'የመኖ ሰብል ማሳዎች':'Feed Plots'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/warehouse/dashboard.php',      'label' => (current_locale()==='am'?'የመጋዘን ክምችት ማጠቃለያ':'Warehouse Overview'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/warehouse/purchasing.php',     'label' => (current_locale()==='am'?'የመጋዘን ግዢና ክምችት':'Warehouse Purchasing'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/distribution/dispatches.php',  'label' => (current_locale()==='am'?'ስርጭት እና ሽያጭ':'Distribution & Sales'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/veterinary/dashboard.php',     'label' => (current_locale()==='am'?'የእንስሳት ጤና ማጠቃለያ':'Veterinary Overview'), 'roles' => ['super_admin']],
            ]
        ],
        [
            'title' => current_locale() === 'am' ? 'የስርዓት አስተዳደርና ደህንነት' : 'System Governance & Security',
            'items' => [
                ['url' => BASE_URL . '/modules/admin/users.php',            'label' => (current_locale()==='am'?'የተጠቃሚዎች መለያ':'User Accounts & Roles'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/audit_log.php',              'label' => (current_locale()==='am'?'የደህንነት ኦዲት መዝገብ':'Security Audit Trail'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/admin/backup.php',           'label' => (current_locale()==='am'?'የመረጃ ቋት ምትኬ (Backup)':'Database Backup'), 'roles' => ['super_admin']],
                ['url' => BASE_URL . '/modules/admin/settings.php',         'label' => (current_locale()==='am'?'የስርዓት ቅንብሮች':'System Settings'), 'roles' => ['super_admin']],
            ]
        ],
    ];
} else {
    // ═════════════════════════════════════════════════════════════════════
    // 2. GENERAL MANAGER & DEPARTMENT OPERATIONAL TEAMS
    // ═════════════════════════════════════════════════════════════════════
    $sidebarMenu = [
        [
            'title' => t('section.dairy', 'Dairy Management'),
            'items' => [
                ['url' => BASE_URL . '/modules/dairy/dashboard.php',        'label' => t('nav.dairy_dashboard', 'Dairy Dashboard'),       'roles' => ['general_manager', 'dairy_manager']],
                ['url' => BASE_URL . '/modules/dairy/cows.php',             'label' => t('nav.cows', 'Cows'),                             'roles' => ['general_manager', 'dairy_manager']],
                ['url' => BASE_URL . '/modules/dairy/milking.php',          'label' => t('nav.milking', 'Milking Entry'),                  'roles' => ['general_manager', 'dairy_manager', 'field_worker']],
                ['url' => BASE_URL . '/modules/dairy/cow_history.php',      'label' => t('nav.cow_history', 'Cow Full History'),           'roles' => ['general_manager', 'dairy_manager']],
                ['url' => BASE_URL . '/modules/dairy/cow_exit_history.php', 'label' => (current_locale()==='am'?'የሽያጭና ሞት ታሪክ':'Sale & Death History'), 'roles' => ['general_manager', 'dairy_manager']],
                ['url' => BASE_URL . '/modules/dairy/material_request.php', 'label' => t('reqs.title_dairy', 'Feed & Supplies Request'),   'roles' => ['dairy_manager', 'general_manager']],
            ]
        ],
        [
            'title' => t('section.poultry', 'Poultry Farm'),
            'items' => [
                ['url' => BASE_URL . '/modules/poultry/dashboard.php',        'label' => t('nav.poultry_dashboard', 'Poultry Dashboard'),          'roles' => ['general_manager', 'poultry_manager']],
                ['url' => BASE_URL . '/modules/poultry/egg_entry.php',        'label' => t('nav.egg_entry', 'Egg Collection Entry'),               'roles' => ['general_manager', 'poultry_manager', 'field_worker']],
                ['url' => BASE_URL . '/modules/poultry/flocks.php',           'label' => t('nav.flocks', 'Flocks & Mortality'),                    'roles' => ['general_manager', 'poultry_manager', 'field_worker']],
                ['url' => BASE_URL . '/modules/poultry/material_request.php', 'label' => t('reqs.title_poultry', 'Feed & Supplies Request'),        'roles' => ['poultry_manager', 'general_manager']],
            ]
        ],
        [
            'title' => t('section.fish_farm', 'Fish Farm'),
            'items' => [
                ['url' => BASE_URL . '/modules/fish/dashboard.php',        'label' => t('nav.fish_dashboard', 'Fish Dashboard'),         'roles' => ['general_manager', 'fish_manager']],
                ['url' => BASE_URL . '/modules/fish/ponds.php',            'label' => t('nav.ponds', 'Fish Ponds'),                      'roles' => ['general_manager', 'fish_manager']],
                ['url' => BASE_URL . '/modules/fish/feeding.php',          'label' => (current_locale()==='am'?'ዕለታዊ መመገቢያ':'Daily Feeding'), 'roles' => ['general_manager', 'fish_manager']],
                ['url' => BASE_URL . '/modules/fish/material_request.php', 'label' => t('reqs.title_fish', 'Feed & Supplies Request'),   'roles' => ['fish_manager', 'general_manager']],
            ]
        ],
        [
            'title' => t('section.feed_production', 'Feed Production'),
            'items' => [
                ['url' => BASE_URL . '/modules/feed_production/plots.php',        'label' => t('nav.plots', 'Crop Plots'),                'roles' => ['general_manager', 'feed_production_manager']],
                ['url' => BASE_URL . '/modules/feed_production/distribution.php', 'label' => t('nav.distribution_requests', 'Send to Warehouse'), 'roles' => ['general_manager', 'feed_production_manager']],
            ]
        ],
        [
            'title' => t('section.warehouse', 'Warehouse & Store'),
            'items' => [
                ['url' => BASE_URL . '/modules/warehouse/dashboard.php',    'label' => t('nav.wh_dashboard', 'Warehouse Dashboard'),      'roles' => ['general_manager', 'warehouse_keeper']],
                ['url' => BASE_URL . '/modules/warehouse/receiving.php',    'label' => t('nav.receiving', 'Receiving Control'),            'roles' => ['general_manager', 'warehouse_keeper']],
                ['url' => BASE_URL . '/modules/warehouse/requisitions.php', 'label' => t('reqs.store_queue', 'Material Requests Queue'),   'roles' => ['general_manager', 'warehouse_keeper']],
                ['url' => BASE_URL . '/modules/warehouse/stock.php',        'label' => t('nav.stock', 'Stock Inventory'),                  'roles' => ['general_manager', 'warehouse_keeper']],
                ['url' => BASE_URL . '/modules/warehouse/stock_adjust.php', 'label' => t('nav.stock_adjust', 'Stock Adjustment'),         'roles' => ['general_manager', 'warehouse_keeper']],
                ['url' => BASE_URL . '/modules/warehouse/issue.php',        'label' => t('nav.issue', 'Direct Issue'),                    'roles' => ['general_manager', 'warehouse_keeper']],
                ['url' => BASE_URL . '/modules/warehouse/purchasing.php',   'label' => (current_locale()==='am'?'የዕቃዎች ግዢ ጥያቄ':'Purchase Requests'),     'roles' => ['general_manager', 'warehouse_keeper']],
            ]
        ],
        [
            'title' => t('section.distribution', 'Distribution & Sales'),
            'items' => [
                ['url' => BASE_URL . '/modules/distribution/vehicles.php',   'label' => (current_locale() === 'am' ? 'የመዳረሻ ሱቆችና ቅርንጫፎች' : 'Shops & Outlets'),             'roles' => ['general_manager', 'warehouse_keeper', 'distribution_officer']],
                ['url' => BASE_URL . '/modules/distribution/dispatches.php', 'label' => t('nav.dispatches', 'Dispatches & Sales'), 'roles' => ['general_manager', 'warehouse_keeper', 'distribution_officer']],
            ]
        ],
        [
            'title' => t('section.veterinary', 'Veterinary Services'),
            'items' => [
                ['url' => BASE_URL . '/modules/veterinary/dashboard.php',    'label' => t('nav.vet_overview', 'Vet Overview'),             'roles' => ['general_manager', 'veterinary_officer']],
                ['url' => BASE_URL . '/modules/veterinary/health.php',       'label' => t('nav.health_records', 'Health Records'),         'roles' => ['general_manager', 'veterinary_officer']],
                ['url' => BASE_URL . '/modules/veterinary/vaccinations.php', 'label' => t('nav.vaccinations', 'Vaccinations'),             'roles' => ['general_manager', 'veterinary_officer']],
                ['url' => BASE_URL . '/modules/veterinary/disease.php',      'label' => t('nav.disease_incidents', 'Disease Incidents'),  'roles' => ['general_manager', 'veterinary_officer']],
            ]
        ],
        [
            'title' => t('section.employees', 'Human Resources'),
            'items' => [
                ['url' => BASE_URL . '/modules/employees/dashboard.php',  'label' => t('nav.hr_dashboard', 'HR Dashboard'),       'roles' => ['general_manager', 'hr_officer']],
                ['url' => BASE_URL . '/modules/employees/list.php',       'label' => t('nav.employees', 'Employees List'),        'roles' => ['general_manager', 'hr_officer']],
                ['url' => BASE_URL . '/modules/employees/attendance.php', 'label' => t('nav.attendance', 'Staff Attendance'),    'roles' => ['general_manager', 'hr_officer', 'field_worker']],
                ['url' => BASE_URL . '/modules/employees/leave.php',      'label' => t('nav.leave_requests', 'Leave Requests'), 'roles' => ['general_manager', 'hr_officer']],
                ['url' => BASE_URL . '/modules/employees/tasks.php',      'label' => t('nav.tasks', 'Assigned Tasks'),           'roles' => ['general_manager', 'hr_officer', 'field_worker']],
                ['url' => BASE_URL . '/modules/employees/id_card.php',    'label' => t('nav.id_card', 'Digital ID Card'),        'roles' => null],
            ]
        ],
        [
            'title' => t('section.finance', 'Finance & Accounting'),
            'items' => [
                ['url' => BASE_URL . '/modules/finance/dashboard.php',        'label' => (current_locale()==='am'?'የፋይናንስና ካዝና ማዕከል':'Cash & Bank Dashboard'), 'roles' => ['general_manager', 'finance_officer']],
                ['url' => BASE_URL . '/modules/finance/purchasing.php',       'label' => (current_locale()==='am'?'የመጋዘን ግዢ ጥያቄዎችና ክፍያ':'Purchasing & Payments'), 'roles' => ['general_manager', 'finance_officer']],
                ['url' => BASE_URL . '/modules/finance/inventory_value.php',  'label' => (current_locale()==='am'?'የዕቃዎች ዋጋ በገንዘብ (Valuation)':'Inventory Valuation'), 'roles' => ['general_manager', 'finance_officer']],
                ['url' => BASE_URL . '/modules/finance/receivables.php',      'label' => (current_locale()==='am'?'ተሰብሳቢ ሂሳቦች':'Receivables'), 'roles' => ['general_manager', 'finance_officer']],
                ['url' => BASE_URL . '/modules/finance/payables.php',         'label' => (current_locale()==='am'?'ተከፋይ ሂሳቦች':'Payables'), 'roles' => ['general_manager', 'finance_officer']],
                ['url' => BASE_URL . '/modules/finance/payroll.php',          'label' => (current_locale()==='am'?'የሰራተኞች ወርሃዊ ደሞዝ':'Staff Payroll & Salaries'), 'roles' => ['general_manager', 'finance_officer']],
                ['url' => BASE_URL . '/modules/finance/cheques.php',          'label' => (current_locale()==='am'?'የቼክ ሂሳቦችና እንቅስቃሴ':'Cheque Management'), 'roles' => ['general_manager', 'finance_officer']],
                ['url' => BASE_URL . '/modules/finance/expenses.php',         'label' => (current_locale()==='am'?'ዕለታዊ ወጪዎች':'Daily Expenses'), 'roles' => ['general_manager', 'finance_officer']],
                ['url' => BASE_URL . '/modules/finance/income_statement.php', 'label' => (current_locale()==='am'?'ቀላል የትርፍና ኪሳራ መግለጫ':'Simple Profit & Loss (P&L)'), 'roles' => ['general_manager', 'finance_officer']],
                ['url' => BASE_URL . '/modules/finance/transfer.php',         'label' => (current_locale()==='am'?'የሂሳብ አካውንቶች ዝውውር':'Internal Transfers'), 'roles' => ['general_manager', 'finance_officer']],
            ]
        ],
        [
            'title' => t('section.system', 'System & Admin'),
            'items' => [
                ['url' => BASE_URL . '/modules/admin/gm_dashboard.php',     'label' => t('nav.gm_overview', 'GM Overview'),           'roles' => ['general_manager']],
                ['url' => BASE_URL . '/modules/reports.php',                'label' => t('nav.reports', 'Executive Reports'),          'roles' => ['general_manager']],
                ['url' => BASE_URL . '/modules/module_reports.php',         'label' => t('nav.module_reports', 'Module Reports'),     'roles' => null],
                ['url' => BASE_URL . '/modules/employees/self_service.php', 'label' => t('nav.self_service', 'My Self-Service'),      'roles' => null],
                ['url' => BASE_URL . '/modules/notifications.php',          'label' => t('nav.notifications', 'Notifications'),       'roles' => null],
                ['url' => BASE_URL . '/modules/audit_log.php',              'label' => t('nav.audit_log', 'Audit Trail'),             'roles' => ['general_manager']],
            ]
        ],
    ];
}

// Map each role to their dedicated dashboard
$dashboardRoutes = [
    'super_admin'             => BASE_URL . '/modules/admin/dashboard.php',
    'general_manager'         => BASE_URL . '/modules/admin/gm_dashboard.php',
    'dairy_manager'           => BASE_URL . '/modules/dairy/dashboard.php',
    'poultry_manager'         => BASE_URL . '/modules/poultry/dashboard.php',
    'fish_manager'            => BASE_URL . '/modules/fish/dashboard.php',
    'warehouse_keeper'        => BASE_URL . '/modules/warehouse/dashboard.php',
    'finance_officer'         => BASE_URL . '/modules/finance/dashboard.php',
    'hr_officer'              => BASE_URL . '/modules/employees/dashboard.php',
    'feed_production_manager' => BASE_URL . '/modules/feed_production/plots.php',
    'veterinary_officer'      => BASE_URL . '/modules/veterinary/dashboard.php',
    'distribution_officer'    => BASE_URL . '/modules/distribution/dispatches.php',
    'field_worker'            => BASE_URL . '/modules/employees/self_service.php',
];
$targetDashboard = $dashboardRoutes[$user['role'] ?? ''] ?? BASE_URL . '/modules/employees/self_service.php';
$isDashboardActive = ($currentScript === BASE_URL . '/index.php' || $currentScript === $targetDashboard || in_array($currentScript, array_values($dashboardRoutes)));
?>
<div class="layout">
    <div class="sidebar" id="sidebar">
        <?php if ($userRole === 'super_admin'): ?>
            <div style="padding:10px 12px;margin-bottom:14px;background:rgba(46,125,50,0.1);border:1px solid rgba(46,125,50,0.3);border-radius:8px;text-align:center">
                <strong style="color:var(--primary,#2e7d32);font-size:13px;display:block">የበላይ አመራር</strong>
                <small style="color:var(--text-muted);font-size:11px">የስትራቴጂና ትንታኔ ቁጥጥር</small>
            </div>
            <a href="<?= BASE_URL ?>/modules/admin/dashboard.php" class="<?= ($currentScript === BASE_URL . '/modules/admin/dashboard.php' || $currentScript === BASE_URL . '/index.php') ? 'active' : '' ?>"><?= h(t('nav.dashboard')) ?></a>
        <?php elseif ($userRole === 'general_manager'): ?>
            <div style="padding:10px 12px;margin-bottom:14px;background:rgba(21,101,192,0.1);border:1px solid rgba(21,101,192,0.3);border-radius:8px;text-align:center">
                <strong style="color:#1565c0;font-size:13px;display:block">ጠቅላላ ስራ አስኪያጅ (GM)</strong>
                <small style="color:var(--text-muted);font-size:11px">የዕለት ተዕለት ኦፕሬሽኖች መምሪያ</small>
            </div>
            <a href="<?= BASE_URL ?>/modules/admin/gm_dashboard.php" class="<?= ($currentScript === BASE_URL . '/modules/admin/gm_dashboard.php' || $currentScript === BASE_URL . '/index.php') ? 'active' : '' ?>"><?= h(t('nav.dashboard')) ?></a>
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
    <div class="content">
        <?php foreach ($flashes as $f): ?>
            <div class="flash <?= h($f['type']) ?>"><?= h($f['msg']) ?></div>
        <?php endforeach; ?>
