<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin']);

$error = null;

// Handle block/unblock toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    csrf_verify();
    $uid = (int) $_POST['user_id'];
    // Prevent locking yourself out
    if ($uid === (int) current_user()['id']) {
        flash('You cannot block your own account.', 'error');
    } else {
        $current = (int) db()->prepare("SELECT is_active FROM users WHERE id = ?")->execute([$uid]) ? 
            db()->query("SELECT is_active FROM users WHERE id = $uid")->fetchColumn() : 1;
        $newActive = $current ? 0 : 1;
        db()->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$newActive, $uid]);
        $action = $newActive ? 'unblocked' : 'blocked';
        log_activity('admin', 'update', 'users', $uid, "User account {$action} by admin");
        flash("User account " . ($newActive ? 'unblocked and active.' : 'blocked and cannot login.'));
    }
    header('Location: /aleltu-eifms/modules/admin/users.php');
    exit;
}

// Handle 2FA reset by admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_2fa'])) {
    csrf_verify();
    $uid = (int) $_POST['user_id'];
    db()->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?")->execute([$uid]);
    db()->prepare("DELETE FROM user_2fa_recovery_codes WHERE user_id = ?")->execute([$uid]);
    log_activity('admin', 'update', 'users', $uid, "2FA reset by admin");
    flash(current_locale() === 'am' ? 'የተጠቃሚው 2FA በተሳካ ሁኔታ ተሰርዟል።' : 'Two-factor authentication has been reset for this user.');
    header('Location: /aleltu-eifms/modules/admin/users.php');
    exit;
}

// Handle new user registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    csrf_verify();
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $role_id    = (int) ($_POST['role_id'] ?? 0);
    $dept_id    = ($_POST['department_id'] ?? '') !== '' ? (int) $_POST['department_id'] : null;

    if ($name === '' || $password === '' || $role_id === 0) {
        $error = current_locale() === 'am' ? 'ስም፣ የይለፍ ቃል እና የስራ ሚና የግዴታ ናቸው።' : 'Name, password and role are required.';
    } elseif (strlen($password) < 8) {
        $error = current_locale() === 'am' ? 'የይለፍ ቃል ቢያንስ 8 ፊደላት መሆን አለበት።' : 'Password must be at least 8 characters.';
    } else {
        // Auto-generate email identifier if not provided
        if ($email === '') {
            $baseSlug = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($name));
            if ($baseSlug === '') $baseSlug = 'user';
            $email = $baseSlug . '_' . time() . '@aleltu.farm';
        }
        $chk = db()->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ((int)$chk->fetchColumn() > 0) {
            $email = preg_replace('/@.*$/', '', $email) . '_' . rand(100, 999) . '@aleltu.farm';
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare(
            "INSERT INTO users (name, email, password, role_id, department_id) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$name, $email, $hash, $role_id, $dept_id]);
        $newId = (int) db()->lastInsertId();
        log_activity('admin', 'create', 'users', $newId, "New user registered: {$name}");
        flash(current_locale() === 'am' ? "ተጠቃሚ '{$name}' በተሳካ ሁኔታ ተፈጥሯል። አሁን መግባት ይችላሉ።" : "User '{$name}' created successfully. They can now log in.");
        header('Location: /aleltu-eifms/modules/admin/users.php');
        exit;
    }
}

// Handle edit user (name, role, department)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    csrf_verify();
    $uid     = (int) $_POST['user_id'];
    $name    = trim($_POST['name'] ?? '');
    $role_id = (int) ($_POST['role_id'] ?? 0);
    $dept_id = ($_POST['department_id'] ?? '') !== '' ? (int) $_POST['department_id'] : null;

    if ($uid === (int) current_user()['id'] && $role_id !== (int) db()->query("SELECT role_id FROM users WHERE id = $uid")->fetchColumn()) {
        flash("You cannot change your own role.", 'error');
    } elseif ($name !== '' && $role_id > 0) {
        db()->prepare("UPDATE users SET name = ?, role_id = ?, department_id = ? WHERE id = ?")->execute([$name, $role_id, $dept_id, $uid]);
        log_activity('admin', 'update', 'users', $uid, "User profile updated by admin");
        flash("User updated.");
    } else {
        flash('Name and Role are required.', 'error');
    }
    header('Location: /aleltu-eifms/modules/admin/users.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

$users = db()->query(
    "SELECT u.id, u.name, u.email, u.is_active, u.two_factor_enabled, u.last_login_at, u.created_at,
            r.name AS role_name, r.id AS role_id,
            d.name AS department_name, d.id AS department_id
     FROM users u
     JOIN roles r ON r.id = u.role_id
     LEFT JOIN departments d ON d.id = u.department_id
     ORDER BY u.created_at DESC"
)->fetchAll();

$roles = db()->query("SELECT id, name, description FROM roles ORDER BY name")->fetchAll();
$departments = db()->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0">
    <h2 class="mt-0">👤 <?= h(t('nav.user_mgmt', 'User Management')) ?></h2>
    <button class="btn" onclick="document.getElementById('modal-create').style.display='flex'">+ <?= current_locale() === 'am' ? 'አዲስ ተጠቃሚ ጨምር' : 'Add New User' ?></button>
</div>
<p class="text-muted"><?= current_locale() === 'am' ? 'የተጠቃሚዎች አካውንት መመዝገቢያ፣ የስራ ሚና መመደቢያ እና አካውንት ማገጃ/ማንሻ። ይህን የሚያከናውነው የበላይ ኃላፊ (Super Admin) ብቻ ነው።' : 'Register users, assign roles, and block/unblock access. Only super_admin can do this.' ?></p>

<?php if ($error): ?>
    <div class="flash error"><?= h($error) ?></div>
<?php endif; ?>

<div class="card" style="padding:0;overflow:hidden">
    <table>
        <thead>
            <tr>
                <th><?= h(t('common.name', 'Name')) ?></th>
                <th><?= h(t('login.email', 'Email')) ?></th>
                <th><?= current_locale() === 'am' ? 'የስራ ሚና' : 'Role' ?></th>
                <th><?= current_locale() === 'am' ? 'መምሪያ' : 'Department' ?></th>
                <th><?= current_locale() === 'am' ? 'የመጨረሻ መግቢያ' : 'Last Login' ?></th>
                <th><?= h(t('common.status', 'Status')) ?></th>
                <th>2FA</th>
                <th><?= h(t('common.actions', 'Actions')) ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr style="<?= !$u['is_active'] ? 'opacity:0.55' : '' ?>">
                <td><strong><?= h($u['name']) ?></strong></td>
                <td><?= h($u['email']) ?></td>
                <td><span class="badge"><?= h(str_replace('_', ' ', $u['role_name'])) ?></span></td>
                <td><?= h($u['department_name'] ?? '—') ?></td>
                <td class="text-muted" style="font-size:12px"><?= $u['last_login_at'] ? h(eth_datetime($u['last_login_at'])) : (current_locale() === 'am' ? 'አልገባም' : 'Never') ?></td>
                <td>
                    <?php if ($u['is_active']): ?>
                        <span class="badge milking"><?= h(t('common.approved', 'Active')) ?></span>
                    <?php else: ?>
                        <span class="badge sold"><?= current_locale() === 'am' ? 'የታገደ' : 'Blocked' ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($u['two_factor_enabled']): ?>
                        <span class="badge" style="background:#e8f5e9;color:#2e7d32;font-size:11px;">🔐 <?= current_locale() === 'am' ? 'በርቷል' : 'Enabled' ?></span>
                    <?php else: ?>
                        <span class="text-muted" style="font-size:12px;"><?= current_locale() === 'am' ? 'አልበራም' : 'Off' ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn secondary" style="padding:4px 10px;font-size:12px;margin-bottom:0"
                        onclick="openEdit(<?= $u['id'] ?>, <?= h(json_encode($u['name'])) ?>, <?= $u['role_id'] ?>, <?= $u['department_id'] ?: 'null' ?>)">
                        ✏️ <?= h(t('common.edit', 'Edit')) ?>
                    </button>
                    <?php if ($u['two_factor_enabled']): ?>
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" name="reset_2fa" value="1"
                                class="btn secondary"
                                style="padding:4px 8px;font-size:12px;margin-bottom:0;color:#c0392b;border-color:#e74c3c;"
                                onclick="return confirm('<?= current_locale() === 'am' ? 'የዚህን ተጠቃሚ 2FA ዳግም ማስጀመር ይፈልጋሉ? ተጠቃሚው ያለ 2FA መግባት ይችላል።' : 'Reset 2FA for this user? Their 2FA and recovery codes will be removed.' ?>')">
                                🔄 Reset 2FA
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($u['id'] !== (int) current_user()['id']): ?>
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" name="toggle_active" value="1"
                                class="btn <?= $u['is_active'] ? 'danger' : 'secondary' ?>"
                                style="padding:4px 10px;font-size:12px;margin-bottom:0"
                                onclick="return confirm('<?= $u['is_active'] ? (current_locale() === 'am' ? 'ይህን ተጠቃሚ ማገድ ይፈልጋሉ?' : 'Block this user?') : (current_locale() === 'am' ? 'የዚህን ተጠቃሚ እገዳ ማንሳት ይፈልጋሉ?' : 'Unblock this user?') ?>')">
                                <?= $u['is_active'] ? '🔒 ' . (current_locale() === 'am' ? 'እገድ' : 'Block') : '✅ ' . (current_locale() === 'am' ? 'እገዳ አንሳ' : 'Unblock') ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- CREATE USER MODAL -->
<div id="modal-create" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:999;align-items:center;justify-content:center">
    <div style="background:var(--card);border-radius:12px;padding:32px;width:480px;max-width:95vw;box-shadow:0 20px 50px rgba(0,0,0,0.2)">
        <h3 style="margin-top:0"><?= current_locale() === 'am' ? 'አዲስ ተጠቃሚ መመዝገቢያ' : 'Create New User' ?></h3>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የተጠቃሚው ሙሉ ስም / የተጠቃሚ ስም *' : 'Full Name / Username *' ?></label>
                <input type="text" name="name" required placeholder="ለምሳሌ፡ አበበ በቀለ" style="width:100%">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'የይለፍ ቃል * (ቢያንስ 8 ፊደላት)' : 'Password * (min 8 chars)' ?></label>
                    <input type="password" name="password" required minlength="8">
                </div>
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'የስራ ሚና *' : 'Role *' ?></label>
                    <select name="role_id" required>
                        <option value="">— <?= h(t('common.select', 'Select Role')) ?> —</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= h(str_replace('_', ' ', ucfirst($r['name']))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የስራ ክፍል / መምሪያ (አማራጭ)' : 'Department (optional)' ?></label>
                <select name="department_id">
                    <option value="">— <?= current_locale() === 'am' ? 'የለም' : 'None' ?> —</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= h($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
                <button type="button" class="btn secondary" onclick="document.getElementById('modal-create').style.display='none'"><?= h(t('common.cancel', 'Cancel')) ?></button>
                <button type="submit" name="create_user" value="1" class="btn"><?= current_locale() === 'am' ? 'ተጠቃሚ ፍጠር' : 'Create User' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT USER MODAL -->
<div id="modal-edit" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:999;align-items:center;justify-content:center">
    <div style="background:var(--card);border-radius:12px;padding:32px;width:480px;max-width:95vw;box-shadow:0 20px 50px rgba(0,0,0,0.2)">
        <h3 style="margin-top:0"><?= current_locale() === 'am' ? 'የተጠቃሚ መረጃ ማስተካከያ' : 'Edit User' ?></h3>
        <form method="post" id="form-edit">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'ሙሉ ስም *' : 'Full Name *' ?></label>
                <input type="text" name="name" id="edit_name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'የስራ ሚና *' : 'Role *' ?></label>
                    <select name="role_id" id="edit_role_id" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= h(str_replace('_', ' ', ucfirst($r['name']))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'የስራ ክፍል / መምሪያ' : 'Department' ?></label>
                    <select name="department_id" id="edit_dept_id">
                        <option value="">— <?= current_locale() === 'am' ? 'የለም' : 'None' ?> —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= h($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
                <button type="button" class="btn secondary" onclick="document.getElementById('modal-edit').style.display='none'"><?= h(t('common.cancel', 'Cancel')) ?></button>
                <button type="submit" name="edit_user" value="1" class="btn"><?= h(t('common.save', 'Save Changes')) ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(id, name, roleId, deptId) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_role_id').value = roleId;
    document.getElementById('edit_dept_id').value = deptId ?? '';
    document.getElementById('modal-edit').style.display = 'flex';
}
// Close modals on outside click
['modal-create','modal-edit'].forEach(function(id) {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
