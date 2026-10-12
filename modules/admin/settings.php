<?php
/**
 * System Settings page (super_admin only)
 * Manage: company name, locale, theme, low-stock threshold, etc.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_permission('system', 'admin');
    $settings = [
        'company_name'        => trim($_POST['company_name'] ?? 'Aleltu Enterprise Farm'),
        'company_location'    => trim($_POST['company_location'] ?? ''),
        'default_locale'      => $_POST['default_locale'] ?? 'en',
        'default_theme'       => $_POST['default_theme'] ?? 'light',
        'low_stock_threshold' => (int)($_POST['low_stock_threshold'] ?? 10),
        'payroll_tax_rate'    => (float)($_POST['payroll_tax_rate'] ?? 0),
        'fiscal_year_start'   => $_POST['fiscal_year_start'] ?? '01-01',
        'session_timeout_min' => (int)($_POST['session_timeout_min'] ?? 60),
    ];
    foreach ($settings as $k => $v) {
        setting_set($k, (string)$v);
    }
    log_activity('system', 'update', 'system_settings', 0, 'Settings updated by ' . (current_user()['name'] ?? 'admin'));
    flash('System settings saved.');
    header('Location: /aleltu-eifms/modules/admin/settings.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

// Load existing settings
$company_name     = setting_get('company_name')     ?? 'Aleltu Enterprise Farm';
$company_location = setting_get('company_location') ?? '';
$default_locale   = setting_get('default_locale')   ?? 'en';
$default_theme    = setting_get('default_theme')     ?? 'light';
$low_stock        = setting_get('low_stock_threshold') ?? '10';
$tax_rate         = setting_get('payroll_tax_rate')   ?? '0';
$fiscal_start     = setting_get('fiscal_year_start')  ?? '01-01';
$session_timeout  = setting_get('session_timeout_min') ?? '60';

// System info
$phpVer   = phpversion();
$dbVer    = db()->query("SELECT VERSION()")->fetchColumn();
$userCount = db()->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
$empCount  = db()->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h2 class="mt-0">⚙️ <?= h(t('nav.settings', 'System Settings')) ?></h2>
</div>

<!-- System Info KPIs -->
<div class="kpi-row" style="margin-bottom:20px">
    <div class="kpi"><div class="label">PHP <?= current_locale() === 'am' ? 'ስሪት' : 'Version' ?></div><div class="value" style="font-size:16px"><?= h($phpVer) ?></div></div>
    <div class="kpi"><div class="label">MySQL <?= current_locale() === 'am' ? 'ስሪት' : 'Version' ?></div><div class="value" style="font-size:16px"><?= h($dbVer) ?></div></div>
    <div class="kpi"><div class="label"><?= current_locale() === 'am' ? 'ንቁ ተጠቃሚዎች' : 'Active Users' ?></div><div class="value"><?= (int)$userCount ?></div></div>
    <div class="kpi"><div class="label"><?= h(t('emp.active_staff', 'Active Employees')) ?></div><div class="value"><?= (int)$empCount ?></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div class="card">
        <h3><?= current_locale() === 'am' ? 'አጠቃላይ የስርዓት ቅንብሮች' : 'General Settings' ?></h3>
        <form method="post"><?= csrf_field() ?>
            <div class="form-group"><label><?= current_locale() === 'am' ? 'የድርጅቱ ስም *' : 'Company Name *' ?></label>
                <input type="text" name="company_name" value="<?= h($company_name) ?>" required>
            </div>
            <div class="form-group"><label><?= current_locale() === 'am' ? 'የድርጅቱ አድራሻ / ቦታ' : 'Company Location' ?></label>
                <input type="text" name="company_location" value="<?= h($company_location) ?>" placeholder="ለምሳሌ፡ አለልቱ፣ ኦሮሚያ፣ ኢትዮጵያ">
            </div>
            <div class="form-group"><label><?= current_locale() === 'am' ? 'ዋና ቋንቋ' : 'Default Language' ?></label>
                <select name="default_locale">
                    <option value="en" <?= $default_locale === 'en' ? 'selected' : '' ?>>English</option>
                    <option value="am" <?= $default_locale === 'am' ? 'selected' : '' ?>>አማርኛ (Amharic)</option>
                </select>
            </div>
            <div class="form-group"><label><?= current_locale() === 'am' ? 'የገጽታ ቀለም' : 'Default Theme' ?></label>
                <select name="default_theme">
                    <option value="light" <?= $default_theme === 'light' ? 'selected' : '' ?>><?= current_locale() === 'am' ? 'ነጭ (Light)' : 'Light' ?></option>
                    <option value="dark"  <?= $default_theme === 'dark'  ? 'selected' : '' ?>><?= current_locale() === 'am' ? 'ጨለማ (Dark)' : 'Dark' ?></option>
                </select>
            </div>
            <div class="form-group"><label><?= current_locale() === 'am' ? 'የክፍለ ጊዜ ማብቂያ (በደቂቃዎች)' : 'Session Timeout (minutes)' ?></label>
                <input type="number" name="session_timeout_min" value="<?= h($session_timeout) ?>" min="10" max="480">
            </div>
            <button class="btn" type="submit"><?= h(t('common.save', 'Save Settings')) ?></button>
        </form>
    </div>

    <div class="card">
        <h3><?= current_locale() === 'am' ? 'የፋይናንስ እና የመጋዘን ቅንብሮች' : 'Finance & Warehouse Settings' ?></h3>
        <form method="post"><?= csrf_field() ?>
            <!-- Hidden fields for other settings to not blank them -->
            <input type="hidden" name="company_name"     value="<?= h($company_name) ?>">
            <input type="hidden" name="company_location" value="<?= h($company_location) ?>">
            <input type="hidden" name="default_locale"   value="<?= h($default_locale) ?>">
            <input type="hidden" name="default_theme"    value="<?= h($default_theme) ?>">
            <input type="hidden" name="session_timeout_min" value="<?= h($session_timeout) ?>">
            <div class="form-group"><label><?= current_locale() === 'am' ? 'ዝቅተኛ የክምችት መጠን ወሰን' : 'Low Stock Threshold (default)' ?></label>
                <input type="number" name="low_stock_threshold" value="<?= h($low_stock) ?>" min="0">
                <small class="text-muted"><?= current_locale() === 'am' ? 'ከዚህ መጠን በታች የሆኑ ዕቃዎች ዝቅተኛ ክምችት ማስጠንቀቂያ ያሳያሉ።' : 'Items below this quantity trigger a low-stock alert.' ?></small>
            </div>
            <div class="form-group"><label><?= current_locale() === 'am' ? 'የደመወዝ ግብር ተመን (%)' : 'Payroll Tax Rate (%)' ?></label>
                <input type="number" step="0.01" name="payroll_tax_rate" value="<?= h($tax_rate) ?>" min="0" max="100">
                <small class="text-muted"><?= current_locale() === 'am' ? 'ከደመወዝ የሚቀነስ የገቢ ግብር (0 = አውቶማቲክ ቅናሽ የለም)' : 'Income tax deducted from net pay (0 = no automatic deduction).' ?></small>
            </div>
            <div class="form-group"><label><?= current_locale() === 'am' ? 'የበጀት ዓመት መጀመሪያ (ወር-ቀን)' : 'Fiscal Year Start (MM-DD)' ?></label>
                <input type="text" name="fiscal_year_start" value="<?= h($fiscal_start) ?>" placeholder="ለምሳሌ፡ 07-08 ለኢትዮጵያ በጀት ዓመት">
            </div>
            <button class="btn" type="submit"><?= h(t('common.save', 'Save Settings')) ?></button>
        </form>
    </div>
</div>

<!-- Quick Admin Links -->
<div class="card" style="margin-top:20px">
    <h3><?= current_locale() === 'am' ? 'ፈጣን የአስተዳደር አቋራጮች' : 'Quick Administration' ?></h3>
    <div style="display:flex;flex-wrap:wrap;gap:10px">
        <a href="/aleltu-eifms/modules/admin/users.php"           class="btn secondary">👤 <?= h(t('nav.user_mgmt', 'Manage Users')) ?></a>
        <a href="/aleltu-eifms/modules/audit_log.php"             class="btn secondary">🔍 <?= h(t('nav.audit_log', 'Audit Log')) ?></a>
        <a href="/aleltu-eifms/modules/notifications.php"         class="btn secondary">🔔 <?= h(t('nav.notifications', 'Notifications')) ?></a>
        <a href="/aleltu-eifms/modules/module_reports.php"        class="btn secondary">📋 <?= h(t('nav.module_reports', 'Module Reports')) ?></a>
        <a href="/aleltu-eifms/system_check.php"                  class="btn secondary">🩺 <?= current_locale() === 'am' ? 'የሲስተም ጤንነት' : 'System Health' ?></a>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
