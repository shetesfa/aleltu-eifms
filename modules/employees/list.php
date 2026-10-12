<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'hr_officer']);
require_once __DIR__ . '/../../includes/header.php';

$search = trim($_GET['search'] ?? '');
$deptFilter = (int) ($_GET['department_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT e.*, d.name AS department_name, u.email AS user_email, u.is_active AS user_active
        FROM employees e 
        LEFT JOIN departments d ON d.id = e.department_id 
        LEFT JOIN users u ON u.id = e.user_id
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (e.full_name LIKE ? OR e.employee_number LIKE ? OR e.phone LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($deptFilter > 0) {
    $sql .= " AND e.department_id = ?";
    $params[] = $deptFilter;
}

if ($statusFilter !== '') {
    $sql .= " AND e.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY e.full_name ASC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

$departments = db()->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">👥 <?= h(t('emp.mgmt', 'Employee Management & Directory')) ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የሰራተኞች መዝገብ መፈለጊያ፣ የስራ ሁኔታ፣ መገኘት እና የግል አገልግሎት መለያ አስተዳደር' : 'Search staff records, manage employment status, attendance, and self-service account linkage.' ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="/aleltu-eifms/modules/employees/add.php" class="btn">+ <?= h(t('emp.add_new', 'Register Employee')) ?></a>
        <a href="/aleltu-eifms/modules/employees/attendance.php" class="btn secondary">📅 <?= h(t('nav.attendance', 'Attendance')) ?></a>
        <a href="/aleltu-eifms/modules/employees/leave.php" class="btn secondary">🏖️ <?= h(t('nav.leave_requests', 'Leave Requests')) ?></a>
        <a href="/aleltu-eifms/modules/employees/tasks.php" class="btn secondary">📋 <?= h(t('nav.tasks', 'Tasks')) ?></a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card" style="margin-bottom:20px;padding:14px 18px">
    <form method="get" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="margin:0;flex:1;min-width:200px">
            <label style="font-size:12px"><?= current_locale() === 'am' ? 'በስም፣ በሰራተኛ ቁጥር ወይም በስልክ ፈልግ' : 'Search by Name, Employee # or Phone' ?></label>
            <input type="text" name="search" value="<?= h($search) ?>" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ አበበ ከበደ ወይም EMP-101' : 'e.g. Abebe Kebede or EMP-101' ?>">
        </div>
        <div class="form-group" style="margin:0;width:180px">
            <label style="font-size:12px"><?= current_locale() === 'am' ? 'የስራ ክፍል / መምሪያ' : 'Department' ?></label>
            <select name="department_id">
                <option value="0"><?= current_locale() === 'am' ? 'ሁሉም ክፍሎች' : 'All Departments' ?></option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= $deptFilter === (int)$d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;width:150px">
            <label style="font-size:12px"><?= h(t('common.status', 'Status')) ?></label>
            <select name="status">
                <option value=""><?= current_locale() === 'am' ? 'ሁሉም ሁኔታዎች' : 'All Statuses' ?></option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>><?= current_locale() === 'am' ? 'ንቁ ሰራተኛ' : 'Active' ?></option>
                <option value="on_leave" <?= $statusFilter === 'on_leave' ? 'selected' : '' ?>><?= current_locale() === 'am' ? 'በፈቃድ ላይ' : 'On Leave' ?></option>
                <option value="terminated" <?= $statusFilter === 'terminated' ? 'selected' : '' ?>><?= current_locale() === 'am' ? 'የተሰናበተ' : 'Terminated / Inactive' ?></option>
            </select>
        </div>
        <button class="btn" type="submit" style="padding:7px 16px">🔍 <?= h(t('common.search', 'Search')) ?></button>
        <?php if ($search !== '' || $deptFilter > 0 || $statusFilter !== ''): ?>
            <a href="/aleltu-eifms/modules/employees/list.php" class="btn secondary" style="padding:7px 12px"><?= current_locale() === 'am' ? 'አጽዳ' : 'Clear' ?></a>
        <?php endif; ?>
    </form>
</div>

<!-- Employees Directory Table -->
<div class="card">
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= h(t('emp.employee_number', 'Emp #')) ?></th>
                    <th><?= h(t('emp.full_name', 'Full Name')) ?></th>
                    <th><?= current_locale() === 'am' ? 'መምሪያ' : 'Department' ?></th>
                    <th><?= h(t('emp.job_title', 'Job Title')) ?></th>
                    <th><?= h(t('common.status', 'Status')) ?></th>
                    <th><?= current_locale() === 'am' ? 'የግል መለያ አካውንት' : 'Self-Service Account' ?></th>
                    <th><?= h(t('common.actions', 'Actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($employees as $e): 
                $statusBadge = match($e['status']) {
                    'active' => 'badge milking',
                    'on_leave' => 'badge dry',
                    default => 'badge sold'
                };
                $stText = match($e['status']) {
                    'active' => (current_locale() === 'am' ? 'ንቁ' : 'Active'),
                    'on_leave' => (current_locale() === 'am' ? 'በፈቃድ ላይ' : 'On Leave'),
                    default => (current_locale() === 'am' ? 'የተሰናበተ' : 'Inactive')
                };
            ?>
                <tr>
                    <td><code><?= h($e['employee_number']) ?></code></td>
                    <td><strong><?= h($e['full_name']) ?></strong></td>
                    <td><?= h($e['department_name'] ?: '—') ?></td>
                    <td><?= h($e['job_title'] ?: '—') ?></td>
                    <td><span class="<?= $statusBadge ?>"><?= h($stText) ?></span></td>
                    <td>
                        <?php if ($e['user_email']): ?>
                            <span style="font-size:12px">🔑 <?= h($e['user_email']) ?></span>
                            <?php if ($e['user_active'] == 0): ?><br><span class="badge sold" style="font-size:9px"><?= current_locale() === 'am' ? 'አካውንቱ ቦዝኗል' : 'Account Inactive' ?></span><?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted" style="font-size:12px">⚠️ <?= current_locale() === 'am' ? 'አልተያያዘም' : 'Not linked' ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/aleltu-eifms/modules/employees/view.php?id=<?= (int) $e['id'] ?>" class="btn secondary" style="padding:3px 8px;font-size:12px;white-space:nowrap"><?= h(t('common.view', 'View Profile')) ?> &rarr;</a>
                        <a href="/aleltu-eifms/modules/employees/id_card.php?id=<?= (int) $e['id'] ?>" target="_blank" class="btn secondary" style="padding:3px 8px;font-size:12px;white-space:nowrap">🆔 <?= h(t('emp.id_card', 'ID Card')) ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($employees)): ?>
                <tr><td colspan="7" class="text-muted" style="text-align:center;padding:20px"><?= h(t('common.no_records', 'No employee records match your search criteria.')) ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
