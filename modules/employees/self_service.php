<?php
/**
 * Employee Self-Service Portal
 * Modern personal dashboard:
 * - Personal Profile & Digital ID Card
 * - 1-Click Check-in with Live Clock
 * - KPI Metrics Overview
 * - Attendance History
 * - Leave Applications & Status Tracking
 * - Payslips & Printable Earnings
 * - My Assigned Work Tasks
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$me     = current_user();
$userId = (int) $me['id'];

// Find employee record linked to this user account
$empStmt = db()->prepare(
    "SELECT e.*, d.name AS department_name 
     FROM employees e 
     LEFT JOIN departments d ON d.id = e.department_id 
     WHERE e.user_id = ? AND e.status != 'terminated'"
);
$empStmt->execute([$userId]);
$emp = $empStmt->fetch();

// Smart auto-link or fallback auto-create so self-service ALWAYS works seamlessly
if (!$emp && !empty($me['id'])) {
    $find = db()->prepare("SELECT id FROM employees WHERE user_id IS NULL AND (LOWER(full_name) = LOWER(?) OR id = ?)");
    $find->execute([$me['name'], $userId]);
    $matchId = $find->fetchColumn();
    if ($matchId) {
        db()->prepare("UPDATE employees SET user_id = ? WHERE id = ?")->execute([$userId, (int)$matchId]);
    } else {
        $empNum = 'EMP-ST' . str_pad((string)$userId, 3, '0', STR_PAD_LEFT);
        $ins = db()->prepare("INSERT INTO employees (user_id, department_id, employee_number, full_name, job_title, hire_date, status) VALUES (?, 1, ?, ?, 'Farm Staff Member', CURDATE(), 'active')");
        $ins->execute([$userId, $empNum, $me['name']]);
    }
    $empStmt->execute([$userId]);
    $emp = $empStmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $emp) {
    csrf_verify();
    $empId = (int)$emp['id'];
    try {
        if (isset($_POST['quick_checkin'])) {
            $today = date('Y-m-d');
            $existing = db()->prepare("SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = ?");
            $existing->execute([$empId, $today]);
            if (!$existing->fetch()) {
                db()->prepare("INSERT INTO attendance (employee_id, attendance_date, status, check_in_at) VALUES (?, ?, 'present', NOW())")
                    ->execute([$empId, $today]);
                flash(current_locale() === 'am' ? '✓ የዛሬ መገኘትዎ በተሳካ ሁኔታ ተመዝግቧል!' : '✓ Check-in recorded for today.');
            } else {
                flash(current_locale() === 'am' ? 'የዛሬ መገኘት አስቀድሞ ተመዝግቧል።' : 'Attendance already logged for today.');
            }
        } elseif (isset($_POST['submit_leave'])) {
            $sDate = $_POST['start_date'];
            $eDate = $_POST['end_date'];
            $reason = trim($_POST['reason'] ?? '');
            if ($eDate < $sDate) throw new InvalidArgumentException('End date must be after start date.');
            
            leave_request($empId, $sDate, $eDate, $reason);
            log_activity('employees', 'create', 'leave_requests', $empId, "Self-service leave: {$sDate} to {$eDate}");
            flash(current_locale() === 'am' ? '✓ የፈቃድ ጥያቄዎ ለHR ኃላፊ ተልኳል።' : 'Leave request submitted to HR for approval.');
        } elseif (isset($_POST['complete_task'])) {
            $taskId = (int) $_POST['task_id'];
            db()->prepare("UPDATE tasks SET status = 'completed', completed_at = NOW() WHERE id = ? AND assigned_to = ?")
                ->execute([$taskId, $empId]);
            flash(current_locale() === 'am' ? '✓ የስራ ተግባር መጠናቀቁ ተረጋግጧል!' : 'Task marked as completed.');
        }
        header('Location: ' . BASE_URL . '/modules/employees/self_service.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

if (!$emp) {
    echo '<div class="card" style="max-width:600px;margin:40px auto;text-align:center;padding:36px;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,0.06);">';
    echo '<div style="font-size:48px;margin-bottom:12px">👤</div>';
    echo '<h2 style="margin:0 0 8px;">' . h(t('ss.title', 'Employee Self-Service Portal')) . '</h2>';
    echo '<p class="text-muted">' . (current_locale() === 'am' ? 'የሰራተኛ መገለጫዎ ገና አልተገናኘም። እባክዎ የHR ኃላፊን ያነጋግሩ።' : 'Account is being set up. Please contact HR.') . '</p>';
    echo '</div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$empId = (int)$emp['id'];

// Attendance stats (last 30 days)
$attendanceSummary = db()->prepare(
    "SELECT status, COUNT(*) AS cnt FROM attendance
     WHERE employee_id = ? AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     GROUP BY status"
);
$attendanceSummary->execute([$empId]);
$attRows = $attendanceSummary->fetchAll();
$attByStatus = [];
foreach ($attRows as $r) $attByStatus[$r['status']] = (int)$r['cnt'];
$presentDays = ($attByStatus['present'] ?? 0) + ($attByStatus['half_day'] ?? 0);
$absentDays  = $attByStatus['absent'] ?? 0;

$todayCheckinRow = db()->query("SELECT check_in_at FROM attendance WHERE employee_id = {$empId} AND attendance_date = CURDATE()")->fetch();
$todayCheckedIn = (bool) $todayCheckinRow;
$checkinTime = $todayCheckedIn && !empty($todayCheckinRow['check_in_at']) ? eth_time($todayCheckinRow['check_in_at']) : null;

$recentAtt = db()->prepare(
    "SELECT attendance_date, status, check_in_at FROM attendance
     WHERE employee_id = ? ORDER BY attendance_date DESC LIMIT 14"
);
$recentAtt->execute([$empId]);
$recentAtt = $recentAtt->fetchAll();

$myLeaves = db()->prepare(
    "SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY id DESC LIMIT 8"
);
$myLeaves->execute([$empId]);
$myLeaves = $myLeaves->fetchAll();

$myPayslips = db()->prepare(
    "SELECT p.*, r.period_start, r.period_end, r.status AS run_status
     FROM fin_payslips p
     JOIN fin_payroll_runs r ON r.id = p.payroll_run_id
     WHERE p.employee_id = ?
     ORDER BY r.id DESC LIMIT 6"
);
$myPayslips->execute([$empId]);
$myPayslips = $myPayslips->fetchAll();

$myTasks = db()->prepare(
    "SELECT * FROM tasks WHERE assigned_to = ? AND status != 'completed' ORDER BY due_date ASC LIMIT 8"
);
$myTasks->execute([$empId]);
$myTasks = $myTasks->fetchAll();

$hour = (int)date('H');
$greeting = $hour < 12 
    ? (current_locale()==='am'?'እንደምን አደሩ':'Good Morning') 
    : ($hour < 17 ? (current_locale()==='am'?'እንደምን ዋሉ':'Good Afternoon') : (current_locale()==='am'?'እንደምን አመሹ':'Good Evening'));
?>

<!-- Hero Banner Card -->
<div class="card" style="margin-bottom:24px;padding:24px 28px;background:var(--card);border-radius:14px;border:1px solid var(--border);border-left:5px solid var(--primary);box-shadow:0 6px 20px rgba(0,0,0,0.04);">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;">
        
        <!-- Left: Avatar & Info -->
        <div style="display:flex;align-items:center;gap:18px;">
            <div style="position:relative;display:inline-block">
                <?php if (employee_photo_exists($emp['photo_path'] ?? null)): ?>
                    <img src="<?= h($emp['photo_path']) ?>?v=<?= time() ?>" alt="Photo" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid var(--border);box-shadow:0 6px 14px rgba(0,0,0,0.12);">
                <?php else: ?>
                    <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg, #2e7d32, #1b5e20);color:#fff;display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:bold;box-shadow:0 6px 14px rgba(46,125,50,0.25);">
                        <?= strtoupper(substr($emp['full_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/modules/employees/photo_upload.php?id=<?= (int)$emp['id'] ?>" title="Change Photo" style="position:absolute;bottom:-2px;right:-2px;background:var(--primary);color:#fff;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;border:2px solid var(--card);text-decoration:none;box-shadow:0 2px 6px rgba(0,0,0,0.2)">📷</a>
            </div>
            <div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <h2 style="margin:0;font-size:22px;color:var(--text);"><?= h($greeting) ?>, <?= h($emp['full_name']) ?>!</h2>
                    <span style="font-size:20px;">👋</span>
                </div>
                <div style="font-size:14px;color:var(--text-muted);margin-top:4px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span style="background:var(--hover, rgba(127,127,127,0.15));color:var(--text);padding:2px 8px;border-radius:4px;font-family:monospace;font-weight:bold;"><?= h($emp['employee_number']) ?></span>
                    <span>&bull;</span>
                    <strong style="color:var(--text);"><?= h($emp['job_title'] ?: 'Staff Member') ?></strong>
                    <span>&bull;</span>
                    <span><?= h($emp['department_name'] ?: 'Operations') ?></span>
                </div>
            </div>
        </div>
        
        <!-- Right: Live Clock & Quick Actions -->
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <!-- Live Clock Widget -->
            <div style="background:var(--card);padding:8px 16px;border-radius:10px;border:1px solid var(--border);text-align:center;box-shadow:0 2px 6px rgba(0,0,0,0.03);">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;"><?= eth_date(date('Y-m-d')) ?></div>
                <div id="liveClock" style="font-size:18px;font-weight:bold;font-family:monospace;color:var(--text);">--:--:--</div>
            </div>

            <!-- Check-in Action -->
            <?php if (!$todayCheckedIn): ?>
                <form method="post" style="margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="quick_checkin" value="1">
                    <button class="btn" type="submit" style="padding:11px 22px;font-size:14px;font-weight:bold;background:var(--primary);border-color:var(--primary);border-radius:10px;box-shadow:0 4px 14px rgba(46,125,50,0.3);cursor:pointer;color:#fff">
                        🕒 <?= current_locale() === 'am' ? 'የዛሬ መገኘት መዝግብ (Check-in)' : "Record Today's Check-in" ?>
                    </button>
                </form>
            <?php else: ?>
                <div style="background:var(--success-bg, rgba(46,125,50,0.15));border:1px solid var(--success, #2e7d32);color:var(--success, #2e7d32);padding:10px 18px;border-radius:10px;font-weight:bold;font-size:14px;display:flex;align-items:center;gap:8px;box-shadow:0 2px 8px rgba(46,125,50,0.1);">
                    <span style="font-size:16px;">✓</span>
                    <span><?= current_locale() === 'am' ? "ዛሬ ተገኝተዋል" : "Checked-in for Today" ?> <?= $checkinTime ? "({$checkinTime})" : "" ?></span>
                </div>
            <?php endif; ?>

            <!-- ID Card Link -->
            <a href="<?= BASE_URL ?>/modules/employees/id_card.php?id=<?= $empId ?>" class="btn secondary" target="_blank" style="padding:10px 16px;border-radius:10px;text-decoration:none;font-weight:600;">
                🪪 <?= current_locale() === 'am' ? 'ዲጂታል መታወቂያ' : 'Digital ID' ?>
            </a>
        </div>
    </div>
</div>

<!-- Modern KPI Metrics Grid -->
<div class="kpi-row" style="margin-bottom:24px;display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:16px;">
    
    <!-- 1. Present Days -->
    <div class="kpi" style="border-radius:12px;padding:18px 20px;border-left:5px solid var(--success, #2e7d32);background:var(--card);color:var(--text);box-shadow:0 4px 15px rgba(0,0,0,0.03);">
        <div class="label" style="font-size:13px;color:var(--text-muted);font-weight:600;">
            📅 <?= current_locale() === 'am' ? 'የተገኘሁባቸው ቀናት (30 ቀናት)' : 'Present Days (30 Days)' ?>
        </div>
        <div class="value" style="color:var(--success, #2e7d32);font-size:26px;font-weight:bold;margin-top:6px;">
            <?= $presentDays ?> <span style="font-size:13px;color:var(--text-muted);font-weight:normal;">/ 30</span>
        </div>
    </div>

    <!-- 2. Absences -->
    <div class="kpi" style="border-radius:12px;padding:18px 20px;border-left:5px solid #c62828;background:var(--card);color:var(--text);box-shadow:0 4px 15px rgba(0,0,0,0.03);">
        <div class="label" style="font-size:13px;color:var(--text-muted);font-weight:600;">
            🔴 <?= current_locale() === 'am' ? 'ያልተገኘሁባቸው ቀናት' : 'Absences (30 Days)' ?>
        </div>
        <div class="value" style="color:<?= $absentDays > 1 ? '#c62828' : 'inherit' ?>;font-size:26px;font-weight:bold;margin-top:6px;">
            <?= $absentDays ?>
        </div>
    </div>

    <!-- 3. Base Salary -->
    <div class="kpi" style="border-radius:12px;padding:18px 20px;border-left:5px solid var(--primary, #1565c0);background:var(--card);color:var(--text);box-shadow:0 4px 15px rgba(0,0,0,0.03);">
        <div class="label" style="font-size:13px;color:var(--text-muted);font-weight:600;">
            💰 <?= current_locale() === 'am' ? 'የወር የተጣራ ደመወዝ' : 'Monthly Base Salary' ?>
        </div>
        <div class="value" style="color:var(--primary, #1565c0);font-size:24px;font-weight:bold;margin-top:6px;">
            <?= number_format((float)($emp['base_salary'] ?? 0), 2) ?> <span style="font-size:13px;color:var(--text-muted);font-weight:normal;">ETB</span>
        </div>
    </div>

    <!-- 4. Tasks -->
    <div class="kpi" style="border-radius:12px;padding:18px 20px;border-left:5px solid #ef6c00;background:var(--card);color:var(--text);box-shadow:0 4px 15px rgba(0,0,0,0.03);">
        <div class="label" style="font-size:13px;color:var(--text-muted);font-weight:600;">
            📋 <?= current_locale() === 'am' ? 'የሚጠበቁ የስራ ተግባራት' : 'Pending Work Tasks' ?>
        </div>
        <div class="value" style="color:#ef6c00;font-size:26px;font-weight:bold;margin-top:6px;">
            <?= count($myTasks) ?>
        </div>
    </div>
</div>

<!-- Core Sections 2x2 Responsive Grid -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:20px;margin-bottom:28px;">
    
    <!-- SECTION 1: ATTENDANCE HISTORY -->
    <div class="card" style="border-radius:12px;padding:22px;box-shadow:0 4px 18px rgba(0,0,0,0.04);border:1px solid var(--border);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;font-size:17px;display:flex;align-items:center;gap:8px;">
                <span>📅</span> <?= current_locale() === 'am' ? 'የመገኘት ታሪክ (ያለፉት 14 ቀናት)' : 'My Recent Attendance' ?>
            </h3>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border);text-align:left;">
                        <th style="padding:8px 10px;"><?= current_locale() === 'am' ? 'ቀን' : 'Date' ?></th>
                        <th style="padding:8px 10px;"><?= current_locale() === 'am' ? 'ሁኔታ' : 'Status' ?></th>
                        <th style="padding:8px 10px;text-align:right;"><?= current_locale() === 'am' ? 'የመግቢያ ሰዓት' : 'Check-in Time' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentAtt as $a):
                        $cls = match($a['status']) { 'present'=>'milking','absent'=>'sold','half_day'=>'dry', default=>'active' };
                        $lbl = match($a['status']) {
                            'present'  => (current_locale()==='am'?'🟢 ተገኝቷል':'🟢 Present'),
                            'absent'   => (current_locale()==='am'?'🔴 ቀሪ':'🔴 Absent'),
                            'half_day' => (current_locale()==='am'?'🟡 ግማሽ ቀን':'🟡 Half Day'),
                            'on_leave' => (current_locale()==='am'?'🏖️ ፈቃድ':'🏖️ Leave'),
                            default    => $a['status']
                        };
                    ?>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:10px;font-weight:600;"><?= h(eth_date($a['attendance_date'])) ?></td>
                        <td style="padding:10px;"><span class="badge <?= $cls ?>"><?= h($lbl) ?></span></td>
                        <td style="padding:10px;text-align:right;" class="text-muted"><?= h($a['check_in_at'] ? eth_time($a['check_in_at']) : '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentAtt)): ?>
                        <tr><td colspan="3" class="text-muted" style="text-align:center;padding:24px;"><?= current_locale() === 'am' ? 'ምንም መረጃ የለም።' : 'No records yet.' ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECTION 2: TIME OFF & LEAVE REQUEST -->
    <div class="card" style="border-radius:12px;padding:22px;box-shadow:0 4px 18px rgba(0,0,0,0.04);border:1px solid var(--border);">
        <h3 style="margin:0 0 16px;font-size:17px;display:flex;align-items:center;gap:8px;">
            <span>🏖️</span> <?= current_locale() === 'am' ? 'የፈቃድ መጠየቂያ ማመልከቻ' : 'Request Time Off / Leave' ?>
        </h3>
        <form method="post" style="margin-bottom:18px;background:var(--card);padding:16px;border-radius:10px;border:1px solid var(--border);">
            <?= csrf_field() ?>
            <input type="hidden" name="submit_leave" value="1">
            <div class="form-row" style="display:flex;gap:12px;margin-bottom:12px;flex-wrap:wrap;">
                <div class="form-group" style="flex:1;min-width:120px;margin:0;">
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?= current_locale() === 'am' ? 'የመጀመሪያ ቀን *' : 'Start Date *' ?></label>
                    <input type="date" name="start_date" required min="<?= date('Y-m-d') ?>" style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid var(--border);font-size:13px;background:var(--card);color:var(--text)">
                </div>
                <div class="form-group" style="flex:1;min-width:120px;margin:0;">
                    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?= current_locale() === 'am' ? 'የማብቂያ ቀን *' : 'End Date *' ?></label>
                    <input type="date" name="end_date" required min="<?= date('Y-m-d') ?>" style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid var(--border);font-size:13px;background:var(--card);color:var(--text)">
                </div>
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;"><?= current_locale() === 'am' ? 'የፈቃድ ምክንያት' : 'Reason / Note' ?></label>
                <input type="text" name="reason" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ የዓመት ፈቃድ፣ የህክምና፣ የቤተሰብ ጉዳይ' : 'e.g. Annual leave, Medical, Family' ?>" style="width:100%;padding:8px 12px;border-radius:6px;border:1px solid var(--border);font-size:13px;background:var(--card);color:var(--text)">
            </div>
            <button class="btn" type="submit" style="width:100%;padding:9px;border-radius:6px;font-weight:bold;font-size:13px;">
                ✓ <?= current_locale() === 'am' ? 'የፈቃድ ጥያቄ አስገባ' : 'Submit Leave Request' ?>
            </button>
        </form>

        <h4 style="margin:16px 0 8px;font-size:14px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">
            <?= current_locale() === 'am' ? 'የቅርብ ጊዜ የፈቃድ ጥያቄዎች ታሪክ' : 'My Leave Requests Log' ?>
        </h4>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border);text-align:left;">
                        <th style="padding:6px 8px;"><?= current_locale() === 'am' ? 'የቀን ገደብ' : 'Dates' ?></th>
                        <th style="padding:6px 8px;"><?= current_locale() === 'am' ? 'ምክንያት' : 'Reason' ?></th>
                        <th style="padding:6px 8px;text-align:right;"><?= current_locale() === 'am' ? 'ሁኔታ' : 'Status' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myLeaves as $l):
                        $cls = match($l['status']) { 'approved'=>'milking','rejected'=>'sold', default=>'dry' };
                        $lbl = match($l['status']) {
                            'approved' => (current_locale()==='am'?'✓ ተፈቅዷል':'Approved'),
                            'rejected' => (current_locale()==='am'?'✗ ውድቅ ሆኗል':'Rejected'),
                            default    => (current_locale()==='am'?'⏳ በመጠባበቅ ላይ':'Pending')
                        };
                    ?>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:8px;font-size:12px;"><?= h(eth_date($l['start_date'])) ?> → <?= h(eth_date($l['end_date'])) ?></td>
                        <td style="padding:8px;"><?= h($l['reason'] ?: '—') ?></td>
                        <td style="padding:8px;text-align:right;"><span class="badge <?= $cls ?>"><?= h($lbl) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($myLeaves)): ?>
                        <tr><td colspan="3" class="text-muted" style="text-align:center;padding:16px;"><?= current_locale() === 'am' ? 'ምንም የፈቃድ ጥያቄ የለም።' : 'No leave requests.' ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECTION 3: PAYSLIPS & EARNINGS -->
    <div class="card" style="border-radius:12px;padding:22px;box-shadow:0 4px 18px rgba(0,0,0,0.04);border:1px solid var(--border);">
        <h3 style="margin:0 0 16px;font-size:17px;display:flex;align-items:center;gap:8px;">
            <span>💰</span> <?= current_locale() === 'am' ? 'የወር ደመወዝ ወረቀቶች (Payslips)' : 'My Monthly Payslips' ?>
        </h3>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border);text-align:left;">
                        <th style="padding:8px 10px;"><?= current_locale() === 'am' ? 'የክፍያ ወቅት' : 'Payroll Period' ?></th>
                        <th style="padding:8px 10px;"><?= current_locale() === 'am' ? 'የተጣራ ደመወዝ' : 'Net Pay' ?></th>
                        <th style="padding:8px 10px;"><?= current_locale() === 'am' ? 'ሁኔታ' : 'Status' ?></th>
                        <th style="padding:8px 10px;text-align:right;"><?= current_locale() === 'am' ? 'ማተሚያ' : 'Print' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myPayslips as $p): ?>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:10px;font-size:12px;"><?= h(eth_date($p['period_start'])) ?> → <?= h(eth_date($p['period_end'])) ?></td>
                        <td style="padding:10px;"><strong style="color:var(--success, #2e7d32);"><?= number_format((float)($p['net_pay'] ?? $p['base_salary']), 2) ?> ETB</strong></td>
                        <td style="padding:10px;">
                            <?php
                            $netPay = (float)($p['net_pay'] ?? $p['base_salary']);
                            $totalPaid = (float)($p['total_paid'] ?? 0);
                            if ($totalPaid >= $netPay && $netPay > 0): ?>
                                <span class="badge milking">✓ <?= current_locale() === 'am' ? '100% ተከፍሏል' : 'Paid' ?></span>
                            <?php elseif ($totalPaid > 0): ?>
                                <span class="badge pregnant">⏳ <?= current_locale() === 'am' ? 'በከፊል ተከፍሏል' : 'Partially Paid' ?> (<?= number_format($totalPaid, 2) ?>)</span>
                            <?php else: ?>
                                <span class="badge dry"><?= current_locale() === 'am' ? 'ገና ያልተከፈለ' : 'Unpaid' ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:10px;text-align:right;">
                            <a href="<?= BASE_URL ?>/modules/finance/payslip_print.php?id=<?= (int)$p['id'] ?>" class="btn secondary" style="padding:4px 10px;font-size:12px;border-radius:6px;text-decoration:none;" target="_blank">
                                🖨️ <?= current_locale() === 'am' ? 'አትም' : 'Print' ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($myPayslips)): ?>
                        <tr><td colspan="4" class="text-muted" style="text-align:center;padding:28px;">
                            <div style="font-size:24px;margin-bottom:6px;">📄</div>
                            <?= current_locale() === 'am' ? 'እስካሁን የተዘጋጀ የደመወዝ ወረቀት የለም።' : 'No payslips available yet.' ?>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECTION 4: ASSIGNED WORK TASKS -->
    <div class="card" style="border-radius:12px;padding:22px;box-shadow:0 4px 18px rgba(0,0,0,0.04);border:1px solid var(--border);">
        <h3 style="margin:0 0 16px;font-size:17px;display:flex;align-items:center;gap:8px;">
            <span>📋</span> <?= current_locale() === 'am' ? 'የተመደቡልኝ የስራ ተግባራት (My Tasks)' : 'My Assigned Work Tasks' ?>
        </h3>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border);text-align:left;">
                        <th style="padding:8px 10px;"><?= current_locale() === 'am' ? 'የስራ ተግባር' : 'Task Title' ?></th>
                        <th style="padding:8px 10px;"><?= current_locale() === 'am' ? 'የማጠናቀቂያ ቀን' : 'Due Date' ?></th>
                        <th style="padding:8px 10px;"><?= current_locale() === 'am' ? 'ቅድሚያ' : 'Priority' ?></th>
                        <th style="padding:8px 10px;text-align:right;"><?= current_locale() === 'am' ? 'ድርጊት' : 'Action' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myTasks as $t): ?>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:10px;"><strong><?= h($t['title']) ?></strong></td>
                        <td style="padding:10px;font-size:12px;color:var(--text-muted);"><?= h($t['due_date'] ? eth_date($t['due_date']) : '—') ?></td>
                        <td style="padding:10px;"><span class="badge <?= $t['priority'] === 'high' ? 'sold' : 'active' ?>"><?= h(ucfirst($t['priority'])) ?></span></td>
                        <td style="padding:10px;text-align:right;">
                            <form method="post" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="complete_task" value="1">
                                <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
                                <button class="btn" type="submit" style="padding:5px 12px;font-size:12px;background:var(--primary);border-color:var(--primary);border-radius:6px;cursor:pointer;">
                                    ✓ <?= current_locale() === 'am' ? 'አጠናቀቅኩ' : 'Mark Done' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($myTasks)): ?>
                        <tr><td colspan="4" class="text-muted" style="text-align:center;padding:28px;">
                            <div style="font-size:26px;margin-bottom:6px;">🎉</div>
                            <?= current_locale() === 'am' ? 'ሁሉም የተመደቡ የስራ ተግባራት ተጠናቀዋል!' : 'All assigned tasks are completed!' ?>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
// Real-time Ethiopian Digital Clock (12-hour local format)
function updateLiveClock() {
    const el = document.getElementById('liveClock');
    if (!el) return;
    const now = new Date();
    const h = now.getHours();
    const m = String(now.getMinutes()).padStart(2, '0');
    const s = String(now.getSeconds()).padStart(2, '0');
    
    let ethHour = (h >= 6) ? (h - 6) : (h + 6);
    if (ethHour === 0) ethHour = 12;

    let period = '';
    if (h >= 6 && h < 12) {
        period = 'ጠዋት';
    } else if (h >= 12 && h < 18) {
        period = 'ከሰዓት';
    } else if (h >= 18 && h < 24) {
        period = 'ምሽት';
    } else {
        period = 'ሌሊት';
    }

    el.textContent = `${ethHour}:${m}:${s} (${period})`;
}
setInterval(updateLiveClock, 1000);
updateLiveClock();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
