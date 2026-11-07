<?php
/**
 * User Profile Overview & Account Settings
 * Aleltu Enterprise Integrated Farm Management System
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$user = current_user();
$userId = (int) $user['id'];

// Fetch latest user details and 2FA status
$stmt = db()->prepare("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch() ?: $user;

// Fetch employee profile if linked
$empStmt = db()->prepare("SELECT * FROM employees WHERE user_id = ?");
$empStmt->execute([$userId]);
$employee = $empStmt->fetch();

require_once __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">👤 <?= current_locale() === 'am' ? 'የግል መገለጫ እና መለያ ደህንነት' : 'User Profile & Account Security' ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የመለያ መረጃዎች፣ የይለፍ ቃል ለውጥ፣ የ2FA ባለሁለት ደረጃ ደህንነት እና የግል ምርጫዎች' : 'Manage your credentials, 2FA authentication, and system preferences.' ?></p>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:20px;margin-bottom:24px">
    <!-- User Info Card -->
    <div class="card" style="margin:0">
        <h3 style="margin-top:0">📋 <?= current_locale() === 'am' ? 'የመለያ አጠቃላይ መረጃ' : 'Account Details' ?></h3>
        <table style="width:100%">
            <tr>
                <td style="font-weight:600;width:40%"><?= current_locale() === 'am' ? 'የተጠቃሚ ስም' : 'Username' ?>:</td>
                <td><?= h($profile['name']) ?></td>
            </tr>
            <tr>
                <td style="font-weight:600"><?= current_locale() === 'am' ? 'ኢሜይል' : 'Email' ?>:</td>
                <td><?= h($profile['email']) ?></td>
            </tr>
            <tr>
                <td style="font-weight:600"><?= current_locale() === 'am' ? 'የስራ ሚና (Role)' : 'Role' ?>:</td>
                <td><span class="badge" style="background:#e8f5e9;color:#2e7d32"><?= h($profile['role_name'] ?? $profile['role']) ?></span></td>
            </tr>
            <tr>
                <td style="font-weight:600"><?= current_locale() === 'am' ? 'የመለያ ሁኔታ' : 'Status' ?>:</td>
                <td>
                    <?php if ((int)$profile['is_active'] === 1): ?>
                        <span class="badge" style="background:#e8f5e9;color:#2e7d32"><?= current_locale() === 'am' ? 'ንቁ (Active)' : 'Active' ?></span>
                    <?php else: ?>
                        <span class="badge" style="background:#ffebee;color:#c62828"><?= current_locale() === 'am' ? 'የታገደ' : 'Inactive' ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="font-weight:600"><?= current_locale() === 'am' ? 'የመጨረሻ መግቢያ' : 'Last Login' ?>:</td>
                <td><?= $profile['last_login_at'] ? eth_datetime($profile['last_login_at']) : '—' ?></td>
            </tr>
            <?php if ($employee): ?>
                <tr>
                    <td style="font-weight:600"><?= current_locale() === 'am' ? 'የሰራተኛ መለያ' : 'Employee ID' ?>:</td>
                    <td><strong><?= h($employee['employee_number']) ?></strong> (<?= h($employee['job_title']) ?>)</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Security & Preferences Actions -->
    <div class="card" style="margin:0">
        <h3 style="margin-top:0">🔒 <?= current_locale() === 'am' ? 'የደህንነት እና የግል ቅንብሮች' : 'Security & Preferences' ?></h3>
        
        <div style="display:flex;flex-direction:column;gap:12px">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:var(--bg,#f8f9fa);border-radius:6px;border:1px solid var(--border,#e0e0e0)">
                <div>
                    <strong>🔑 <?= current_locale() === 'am' ? 'የይለፍ ቃል መቀየሪያ' : 'Change Password' ?></strong>
                    <div style="font-size:12px;color:var(--text-muted,#666)"><?= current_locale() === 'am' ? 'የይለፍ ቃልዎን በየጊዜው ያዘምኑ' : 'Update your account login password' ?></div>
                </div>
                <a href="<?= BASE_URL ?>/modules/profile/change_password.php" class="btn secondary" style="padding:6px 12px;font-size:13px">
                    <?= current_locale() === 'am' ? 'ቀይር' : 'Change' ?> &rarr;
                </a>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:var(--bg,#f8f9fa);border-radius:6px;border:1px solid var(--border,#e0e0e0)">
                <div>
                    <strong>🛡️ <?= current_locale() === 'am' ? 'ባለሁለት ደረጃ ደህንነት (2FA)' : 'Two-Factor Authentication' ?></strong>
                    <div style="font-size:12px;color:var(--text-muted,#666)">
                        <?= !empty($profile['two_factor_enabled']) ? '✓ ' . (current_locale() === 'am' ? 'በአሁኑ ሰዓት በርቷል' : 'Enabled') : '🔴 ' . (current_locale() === 'am' ? 'አልበራም' : 'Disabled') ?>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/modules/profile/two_factor.php" class="btn <?= !empty($profile['two_factor_enabled']) ? 'secondary' : '' ?>" style="padding:6px 12px;font-size:13px">
                    <?= !empty($profile['two_factor_enabled']) ? (current_locale() === 'am' ? 'አስተዳድር' : 'Manage') : (current_locale() === 'am' ? 'አብራ' : 'Enable') ?> &rarr;
                </a>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:var(--bg,#f8f9fa);border-radius:6px;border:1px solid var(--border,#e0e0e0)">
                <div>
                    <strong>🌐 <?= current_locale() === 'am' ? 'የስርዓቱ ቋንቋ' : 'Interface Language' ?></strong>
                    <div style="font-size:12px;color:var(--text-muted,#666)"><?= current_locale() === 'am' ? 'አማርኛ (Amharic)' : 'English' ?></div>
                </div>
                <form method="post" action="<?= BASE_URL ?>/modules/profile/set_locale.php" style="margin:0">
                    <?= csrf_field() ?>
                    <select name="locale" onchange="this.form.submit()" style="padding:4px 8px;font-size:13px">
                        <?php foreach (SUPPORTED_LOCALES as $code => $label): ?>
                            <option value="<?= h($code) ?>" <?= current_locale() === $code ? 'selected' : '' ?>><?= $code === 'am' ? 'አማርኛ' : 'English' ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:var(--bg,#f8f9fa);border-radius:6px;border:1px solid var(--border,#e0e0e0)">
                <div>
                    <strong>🌓 <?= current_locale() === 'am' ? 'የገጽታ ቀለም (Theme)' : 'Display Theme' ?></strong>
                    <div style="font-size:12px;color:var(--text-muted,#666)"><?= current_theme() === 'dark' ? (current_locale() === 'am' ? 'ጨለማ ገጽታ' : 'Dark Mode') : (current_locale() === 'am' ? 'ብርሃን ገጽታ' : 'Light Mode') ?></div>
                </div>
                <form method="post" action="<?= BASE_URL ?>/modules/profile/set_theme.php" style="margin:0">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn secondary" style="padding:5px 12px;font-size:13px">
                        <?= current_theme() === 'dark' ? '☀️ ' . (current_locale() === 'am' ? 'ወደ ብርሃን ቀይር' : 'Switch to Light') : '🌙 ' . (current_locale() === 'am' ? 'ወደ ጨለማ ቀይር' : 'Switch to Dark') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
