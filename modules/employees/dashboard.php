<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'hr_officer']);
require_once __DIR__ . '/../../includes/header.php';

// HR KPIs
$totalEmployees = (int) db()->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
$onLeaveEmployees = (int) db()->query("SELECT COUNT(*) FROM employees WHERE status = 'on_leave'")->fetchColumn();
$pendingLeaveReqs = (int) db()->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();
$todayPresent = (int) db()->query("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE attendance_date = CURDATE() AND status = 'present'")->fetchColumn();

// Pending Leave Requests Table
$pendingLeaves = db()->query(
    "SELECT l.*, e.full_name, e.employee_number, d.name AS dept_name
     FROM leave_requests l
     JOIN employees e ON e.id = l.employee_id
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE l.status = 'pending'
     ORDER BY l.id ASC LIMIT 8"
)->fetchAll();

// Recent Attendance Log
$recentAtt = db()->query(
    "SELECT a.*, e.full_name, e.employee_number
     FROM attendance a
     JOIN employees e ON e.id = a.employee_id
     WHERE a.attendance_date = CURDATE()
     ORDER BY a.check_in_at DESC LIMIT 8"
)->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">👷 <?= h(t('nav.hr_dashboard', 'HR & Workforce Dashboard')) ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የሰራተኞች መረጃ፣ የዕለት መገኘት ክትትል፣ የፈቃድ ጥያቄዎች እና የተጣሉ ስራዎች አስተዳደር' : 'Employee records, daily attendance tracking, leave requests, and task assignments.' ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/modules/employees/add.php" class="btn">+ <?= h(t('emp.add_new', 'Register Employee')) ?></a>
        <a href="<?= BASE_URL ?>/modules/employees/list.php" class="btn secondary">👥 <?= h(t('nav.employees', 'Employee Directory')) ?></a>
        <a href="<?= BASE_URL ?>/modules/employees/attendance.php" class="btn secondary">📅 <?= h(t('nav.attendance', 'Attendance Sheet')) ?></a>
        <a href="<?= BASE_URL ?>/modules/employees/leave.php" class="btn secondary">🏖️ <?= h(t('nav.leave_requests', 'Leave Requests')) ?> (<?= $pendingLeaveReqs ?>)</a>
    </div>
</div>

<!-- HR KPIs -->
<div class="kpi-row" style="margin-bottom:20px">
    <div class="kpi">
        <div class="label"><?= h(t('emp.active_staff', 'Active Employees')) ?></div>
        <div class="value"><?= $totalEmployees ?> <?= current_locale() === 'am' ? 'ሰራተኞች' : 'staff' ?></div>
    </div>
    <div class="kpi">
        <div class="label"><?= current_locale() === 'am' ? 'ዛሬ የተገኙ' : 'Present Today' ?></div>
        <div class="value" style="color:var(--primary)"><?= $todayPresent ?> <span style="font-size:13px;color:var(--text-muted)">/ <?= $totalEmployees ?></span></div>
    </div>
    <div class="kpi">
        <div class="label"><?= h(t('emp.on_leave', 'On Approved Leave')) ?></div>
        <div class="value"><?= $onLeaveEmployees ?></div>
    </div>
    <div class="kpi">
        <div class="label"><?= current_locale() === 'am' ? 'በመጠባበቅ ላይ ያሉ የፈቃድ ጥያቄዎች' : 'Pending Leave Requests' ?></div>
        <div class="value" style="color:<?= $pendingLeaveReqs > 0 ? '#b3261e' : 'inherit' ?>"><?= $pendingLeaveReqs ?></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <!-- Pending Leave Approvals -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
            <h3 style="margin:0"><?= current_locale() === 'am' ? 'ማረጋገጫ የሚጠብቁ የፈቃድ ጥያቄዎች' : 'Pending Leave Approvals' ?></h3>
            <a href="<?= BASE_URL ?>/modules/employees/leave.php" class="btn secondary" style="padding:3px 8px;font-size:12px"><?= h(t('common.view_all', 'View All')) ?></a>
        </div>
        <table>
            <thead>
                <tr>
                    <th><?= h(t('common.name', 'Employee')) ?></th>
                    <th><?= h(t('common.date', 'Dates')) ?></th>
                    <th><?= current_locale() === 'am' ? 'ምክንያት' : 'Reason' ?></th>
                    <th><?= h(t('common.actions', 'Action')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingLeaves as $l): ?>
                    <tr>
                        <td><strong><?= h($l['full_name']) ?></strong><br><small class="text-muted"><?= h($l['employee_number']) ?> (<?= h($l['dept_name'] ?: '—') ?>)</small></td>
                        <td><?= h($l['start_date']) ?> → <?= h($l['end_date']) ?></td>
                        <td><?= h($l['reason'] ?: '—') ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/modules/employees/leave.php" class="btn secondary" style="padding:3px 8px;font-size:12px"><?= current_locale() === 'am' ? 'መርምር' : 'Review' ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($pendingLeaves)): ?>
                    <tr><td colspan="4" class="text-muted"><?= h(t('common.no_records', 'No pending leave requests.')) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Today's Attendance -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
            <h3 style="margin:0"><?= current_locale() === 'am' ? 'የዛሬው የሰራተኞች መገኘት' : "Today's Attendance Check-Ins" ?></h3>
            <a href="<?= BASE_URL ?>/modules/employees/attendance.php" class="btn secondary" style="padding:3px 8px;font-size:12px"><?= current_locale() === 'am' ? 'ሙሉ መዝገብ' : 'Full Attendance Sheet' ?></a>
        </div>
        <table>
            <thead>
                <tr>
                    <th><?= h(t('common.name', 'Employee')) ?></th>
                    <th><?= h(t('common.status', 'Status')) ?></th>
                    <th><?= current_locale() === 'am' ? 'የመግቢያ ሰዓት' : 'Check In' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentAtt as $a): 
                    $badgeCls = match($a['status']) {
                        'present' => 'badge milking',
                        'half_day' => 'badge dry',
                        'absent' => 'badge sold',
                        default => 'badge'
                    };
                    $stText = match($a['status']) {
                        'present' => (current_locale() === 'am' ? 'ተገኝቷል' : 'Present'),
                        'half_day' => (current_locale() === 'am' ? 'ግማሽ ቀን' : 'Half Day'),
                        'absent' => (current_locale() === 'am' ? 'ቀርቷል' : 'Absent'),
                        default => (current_locale() === 'am' ? 'በፈቃድ ላይ' : 'On Leave')
                    };
                ?>
                    <tr>
                        <td><strong><?= h($a['full_name']) ?></strong> (<?= h($a['employee_number']) ?>)</td>
                        <td><span class="<?= $badgeCls ?>"><?= h($stText) ?></span></td>
                        <td><?= h($a['check_in_at'] ? substr($a['check_in_at'], 11, 5) : '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($recentAtt)): ?>
                    <tr><td colspan="3" class="text-muted"><?= h(t('common.no_records', 'No attendance marked yet today.')) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
