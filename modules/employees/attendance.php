<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'hr_officer', 'super_admin']);

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

$calMonth = (int) ($_GET['cal_month'] ?? date('n', strtotime($date)));
$calYear  = (int) ($_GET['cal_year']  ?? date('Y', strtotime($date)));
$viewMode = $_GET['view'] ?? 'roster'; // 'roster', 'matrix', 'calendar'
$deptFilter = (int)($_GET['dept_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['mark_single'])) {
            $empId   = (int)$_POST['employee_id'];
            $attDate = $_POST['attendance_date'];
            $status  = $_POST['status'];
            attendance_mark($empId, $attDate, $status);
            flash(current_locale() === 'am' ? '✓ መገኘት ተመዝግቧል።' : 'Attendance updated.');
        } elseif (isset($_POST['mark_all_present'])) {
            $attDate = $_POST['attendance_date'];
            $activeEmps = db()->query("SELECT id FROM employees WHERE status = 'active'")->fetchAll();
            foreach ($activeEmps as $ae) {
                attendance_mark((int)$ae['id'], $attDate, 'present');
            }
            log_activity('attendance', 'create', 'attendance', 0, "Marked ALL active staff as PRESENT for {$attDate}");
            flash(current_locale() === 'am' ? '✓ ሁሉም ንቁ ሰራተኞች ዛሬ "ተገኝተዋል" ተብለው ተመዝግበዋል!' : 'All active staff marked as Present.');
        }
        header("Location: /aleltu-eifms/modules/employees/attendance.php?date=" . urlencode($date) . "&view=" . urlencode($viewMode) . "&dept_id={$deptFilter}&cal_month={$calMonth}&cal_year={$calYear}");
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

// Departments list
$departments = db()->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

// Employee Attendance Query
$sql = "SELECT e.id, e.employee_number, e.full_name, e.amharic_name, e.job_title, e.photo_path,
               d.name AS dept_name, a.status AS marked_status, a.check_in_at
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN attendance a ON a.employee_id = e.id AND a.attendance_date = ?
        WHERE e.status = 'active'";
$params = [$date];

if ($deptFilter > 0) {
    $sql .= " AND e.department_id = ?";
    $params[] = $deptFilter;
}
if ($search !== '') {
    $sql .= " AND (e.full_name LIKE ? OR e.employee_number LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$sql .= " ORDER BY e.full_name ASC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Today statistics
$totalStaff = count($employees);
$presentCount = 0;
$absentCount  = 0;
$halfDayCount = 0;
$onLeaveCount = 0;
$unmarkedCount= 0;

foreach ($employees as $emp) {
    match($emp['marked_status']) {
        'present'  => $presentCount++,
        'absent'   => $absentCount++,
        'half_day' => $halfDayCount++,
        'on_leave' => $onLeaveCount++,
        default    => $unmarkedCount++
    };
}
$attRate = $totalStaff > 0 ? (($presentCount + ($halfDayCount * 0.5)) / $totalStaff) * 100 : 0;

// Calendar calculations
$firstOfMonth = mktime(0, 0, 0, $calMonth, 1, $calYear);
$daysInMonth  = (int) date('t', $firstOfMonth);
$startWeekday = (int) date('w', $firstOfMonth);
$prevMonth = $calMonth - 1; $prevYear = $calYear; if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $calMonth + 1; $nextYear = $calYear; if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
$monthSummary = attendance_month_summary($calYear, $calMonth);

// Matrix Data Query (for monthly grid view)
$matrixData = [];
if ($viewMode === 'matrix') {
    $matrixStmt = db()->prepare(
        "SELECT employee_id, DAY(attendance_date) AS day_num, status
         FROM attendance
         WHERE YEAR(attendance_date) = ? AND MONTH(attendance_date) = ?"
    );
    $matrixStmt->execute([$calYear, $calMonth]);
    foreach ($matrixStmt->fetchAll() as $m) {
        $matrixData[$m['employee_id']][$m['day_num']] = $m['status'];
    }
}
?>

<!-- Header -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">
            📅 <?= current_locale() === 'am' ? 'የሰራተኞች የዕለትና የወር መገኘት መቆጣጠሪያ (Attendance Hub)' : 'Staff Attendance & Timesheet Hub' ?>
        </h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am'
                ? 'የዕለት ሮስተር፣ የወር ማትሪክስ ሰንጠረዥ፣ እና በ1-ክሊክ ፈጣን የመገኘት መመዝገቢያ'
                : 'Daily check-in roster, visual monthly timesheet matrix, and 1-click bulk attendance recording.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap" class="no-print">
        <form method="post" style="display:inline" onsubmit="return confirm('<?= current_locale()==='am'?'ሁሉንም ንቁ ሰራተኞች ዛሬ ተገኝተዋል ብለው መመዝገብ ይፈልጋሉ?':'Mark ALL active staff as Present today?' ?>');">
            <?= csrf_field() ?>
            <input type="hidden" name="mark_all_present" value="1">
            <input type="hidden" name="attendance_date" value="<?= h($date) ?>">
            <button class="btn" type="submit" style="background:#2e7d32;border-color:#2e7d32;font-weight:700">
                ✓ <?= current_locale() === 'am' ? 'ሁሉንም ዛሬ ተገኝተዋል በል (Mark All Present)' : 'Mark All Present Today' ?>
            </button>
        </form>
        <button class="btn secondary" onclick="window.print()">🖨️ <?= current_locale() === 'am' ? 'አትም / PDF' : 'Print Timesheet' ?></button>
        <a href="/aleltu-eifms/modules/employees/dashboard.php" class="btn secondary">← <?= current_locale() === 'am' ? 'ወደ HR ዳሽቦርድ' : 'HR Dashboard' ?></a>
    </div>
</div>

<!-- Top Statistics KPIs -->
<div class="kpi-row" style="margin-bottom:20px">
    <div class="kpi" style="border-left:4px solid #2e7d32">
        <div class="label">🟢 <?= current_locale() === 'am' ? 'ዛሬ የተገኙ (Present)' : 'Present Today' ?></div>
        <div class="value" style="color:#2e7d32"><?= $presentCount ?> <span style="font-size:13px;color:var(--text-muted)">/ <?= $totalStaff ?></span></div>
    </div>
    <div class="kpi" style="border-left:4px solid #b3261e">
        <div class="label">🔴 <?= current_locale() === 'am' ? 'ዛሬ የቀሩ (Absent)' : 'Absent Today' ?></div>
        <div class="value" style="color:<?= $absentCount > 0 ? '#b3261e' : 'inherit' ?>"><?= $absentCount ?></div>
    </div>
    <div class="kpi" style="border-left:4px solid #e65100">
        <div class="label">🟡 <?= current_locale() === 'am' ? 'ግማሽ ቀን / ፈቃድ' : 'Half Day / Leave' ?></div>
        <div class="value" style="color:#e65100"><?= $halfDayCount + $onLeaveCount ?></div>
    </div>
    <div class="kpi" style="border:2px solid var(--primary)">
        <div class="label">📊 <?= current_locale() === 'am' ? 'የዕለቱ የመገኘት ምጣኔ' : 'Attendance Rate' ?></div>
        <div class="value" style="color:var(--primary)"><strong style="font-size:22px"><?= number_format($attRate, 1) ?>%</strong></div>
    </div>
</div>

<!-- Control Bar (Tabs, Department, Search, Date) -->
<div class="card no-print" style="margin-bottom:20px;padding:14px 18px">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px">
        <!-- View Mode Tabs -->
        <div style="display:flex;gap:6px">
            <a href="?view=roster&date=<?= h($date) ?>&dept_id=<?= $deptFilter ?>&cal_month=<?= $calMonth ?>&cal_year=<?= $calYear ?>" 
               class="btn <?= $viewMode==='roster' ? '' : 'secondary' ?>" style="padding:7px 14px;font-size:13px">
                📋 <?= current_locale() === 'am' ? 'የዕለት ሮስተር (Daily Roster)' : 'Daily Roster' ?>
            </a>
            <a href="?view=matrix&date=<?= h($date) ?>&dept_id=<?= $deptFilter ?>&cal_month=<?= $calMonth ?>&cal_year=<?= $calYear ?>" 
               class="btn <?= $viewMode==='matrix' ? '' : 'secondary' ?>" style="padding:7px 14px;font-size:13px">
                📊 <?= current_locale() === 'am' ? 'የወር ማትሪክስ ሰንጠረዥ (Monthly Timesheet)' : 'Monthly Timesheet Matrix' ?>
            </a>
            <a href="?view=calendar&date=<?= h($date) ?>&dept_id=<?= $deptFilter ?>&cal_month=<?= $calMonth ?>&cal_year=<?= $calYear ?>" 
               class="btn <?= $viewMode==='calendar' ? '' : 'secondary' ?>" style="padding:7px 14px;font-size:13px">
                🗓️ <?= current_locale() === 'am' ? 'የቀን መቁጠሪያ (Calendar View)' : 'Calendar View' ?>
            </a>
        </div>

        <!-- Filters Form -->
        <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0">
            <input type="hidden" name="view" value="<?= h($viewMode) ?>">
            <input type="hidden" name="cal_month" value="<?= $calMonth ?>">
            <input type="hidden" name="cal_year" value="<?= $calYear ?>">
            
            <input type="text" name="search" placeholder="<?= current_locale()==='am'?'በስም ፈልግ...':'Search staff...' ?>" value="<?= h($search) ?>" style="padding:6px 10px;border-radius:4px;border:1px solid var(--border);width:150px">
            
            <select name="dept_id" onchange="this.form.submit()" style="padding:6px 10px;border-radius:4px;border:1px solid var(--border)">
                <option value="0"><?= current_locale() === 'am' ? '— ሁሉም የስራ ክፍሎች —' : 'All Departments' ?></option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= (int)$dept['id'] ?>" <?= $deptFilter === (int)$dept['id'] ? 'selected' : '' ?>><?= h($dept['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="date" value="<?= h($date) ?>" onchange="this.form.submit()" style="padding:6px 10px;border-radius:4px;border:1px solid var(--border)">
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- VIEW 1: DAILY ROSTER (የዕለት ሮስተር)        -->
<!-- ========================================== -->
<?php if ($viewMode === 'roster'): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:20px;align-items:start">
    <!-- Quick Mini Calendar -->
    <div class="card no-print">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
            <a class="btn secondary" style="padding:3px 10px" href="?view=roster&date=<?= h($date) ?>&cal_month=<?= $prevMonth ?>&cal_year=<?= $prevYear ?>&dept_id=<?= $deptFilter ?>">&larr;</a>
            <strong><?= h(date('F Y', $firstOfMonth)) ?> (<?= eth_date($date) ?>)</strong>
            <a class="btn secondary" style="padding:3px 10px" href="?view=roster&date=<?= h($date) ?>&cal_month=<?= $nextMonth ?>&cal_year=<?= $nextYear ?>&dept_id=<?= $deptFilter ?>">&rarr;</a>
        </div>
        <table style="width:100%;text-align:center;font-size:12px">
            <tr><?php foreach (['S','M','T','W','T','F','S'] as $wd): ?><th style="padding:4px"><?= $wd ?></th><?php endforeach; ?></tr>
            <?php
            $cell = 0;
            echo '<tr>';
            for ($i = 0; $i < $startWeekday; $i++) { echo '<td></td>'; $cell++; }
            for ($d = 1; $d <= $daysInMonth; $d++) {
                if ($cell > 0 && $cell % 7 === 0) echo '</tr><tr>';
                $dateStr = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $d);
                $isSelected = ($dateStr === $date);
                $sum = $monthSummary[$d] ?? null;
                if ($isSelected) { $bg = 'var(--primary)'; $color = '#fff'; }
                elseif ($sum && (int)$sum['absent'] > 0) { $bg = 'rgba(198,40,40,0.15)'; $color = 'inherit'; }
                elseif ($sum && (int)$sum['present'] > 0) { $bg = 'rgba(46,125,50,0.15)'; $color = 'inherit'; }
                else { $bg = 'transparent'; $color = 'inherit'; }
                echo '<td style="padding:2px"><a href="?view=roster&date=' . $dateStr . '&cal_month=' . $calMonth . '&cal_year=' . $calYear . '&dept_id=' . $deptFilter . '" '
                   . 'style="display:block;padding:5px 0;border-radius:4px;background:' . $bg . ';color:' . $color . ';text-decoration:none;font-weight:' . ($isSelected?'bold':'normal') . '">' . $d . '</a></td>';
                $cell++;
            }
            echo '</tr>';
            ?>
        </table>
        <div style="display:flex;gap:6px;justify-content:center;margin-top:10px;font-size:11px;color:var(--text-muted)">
            <span style="color:#2e7d32">● <?= current_locale()==='am'?'ተገኝተዋል':'Present' ?></span>
            <span style="color:#c62828">● <?= current_locale()==='am'?'ቀሪ አለ':'Absent' ?></span>
        </div>
    </div>

    <!-- Daily Roster Table -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
            <h3 style="margin:0">
                📅 <?= eth_date($date) ?> <span style="font-size:13px;color:var(--text-muted);font-weight:normal">(<?= h(date('l, j F Y', strtotime($date))) ?>)</span>
                <span class="badge" style="margin-left:8px;font-size:12px"><?= $presentCount ?>/<?= $totalStaff ?> <?= current_locale()==='am'?'ተገኝተዋል':'present' ?></span>
            </h3>
        </div>

        <?php if (empty($employees)): ?>
            <p class="text-muted"><?= current_locale() === 'am' ? 'ምንም ሰራተኛ አልተገኘም።' : 'No staff found.' ?></p>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= current_locale() === 'am' ? 'ፎቶ' : 'Photo' ?></th>
                    <th><?= current_locale() === 'am' ? 'የሰራተኛ ስም' : 'Staff Member' ?></th>
                    <th><?= current_locale() === 'am' ? 'መምሪያ / የስራ መደብ' : 'Department & Role' ?></th>
                    <th><?= current_locale() === 'am' ? 'የዛሬ ሁኔታ' : 'Status' ?></th>
                    <th style="text-align:right"><?= current_locale() === 'am' ? 'መገኘት መዝግብ' : 'Fast Actions' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $emp):
                    $st = $emp['marked_status'];
                    $stBadge = match($st) {
                        'present'  => 'badge milking',
                        'absent'   => 'badge sold',
                        'half_day' => 'badge dry',
                        'on_leave' => 'badge active',
                        default    => 'badge'
                    };
                    $stLabel = match($st) {
                        'present'  => (current_locale()==='am'?'🟢 ተገኝቷል':'🟢 Present'),
                        'absent'   => (current_locale()==='am'?'🔴 ቀሪ':'🔴 Absent'),
                        'half_day' => (current_locale()==='am'?'🟡 ግማሽ ቀን':'🟡 Half Day'),
                        'on_leave' => (current_locale()==='am'?'🏖️ ፈቃድ':'🏖️ On Leave'),
                        default    => (current_locale()==='am'?'⏳ አልተመዘገበም':'⏳ Unmarked')
                    };
                ?>
                <tr>
                    <td style="width:40px;text-align:center">
                        <?php if (employee_photo_exists($emp['photo_path'] ?? null)): ?>
                            <img src="<?= h($emp['photo_path']) ?>" style="width:34px;height:34px;border-radius:50%;object-fit:cover">
                        <?php else: ?>
                            <span style="font-size:22px">👤</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= h($emp['full_name']) ?></strong>
                        <div style="font-size:11px;color:var(--text-muted)"><code><?= h($emp['employee_number']) ?></code></div>
                    </td>
                    <td>
                        <div><?= h($emp['dept_name'] ?: 'Operations') ?></div>
                        <small class="text-muted"><?= h($emp['job_title'] ?: 'Staff') ?></small>
                    </td>
                    <td>
                        <span class="<?= $stBadge ?>"><?= $stLabel ?></span>
                    </td>
                    <td style="text-align:right">
                        <form method="post" style="display:inline-flex;gap:4px">
                            <?= csrf_field() ?>
                            <input type="hidden" name="mark_single" value="1">
                            <input type="hidden" name="employee_id" value="<?= (int)$emp['id'] ?>">
                            <input type="hidden" name="attendance_date" value="<?= h($date) ?>">
                            
                            <button class="btn <?= $st==='present'?'':'secondary' ?>" type="submit" name="status" value="present" 
                                    style="padding:4px 8px;font-size:12px;<?= $st==='present'?'background:#2e7d32;border-color:#2e7d32':'' ?>" title="Present">
                                🟢 <?= current_locale()==='am'?'ተገኝቷል':'P' ?>
                            </button>
                            <button class="btn <?= $st==='absent'?'':'secondary' ?>" type="submit" name="status" value="absent" 
                                    style="padding:4px 8px;font-size:12px;<?= $st==='absent'?'background:#b3261e;border-color:#b3261e':'' ?>" title="Absent">
                                🔴 <?= current_locale()==='am'?'ቀሪ':'A' ?>
                            </button>
                            <button class="btn <?= $st==='half_day'?'':'secondary' ?>" type="submit" name="status" value="half_day" 
                                    style="padding:4px 8px;font-size:12px;<?= $st==='half_day'?'background:#e65100;border-color:#e65100':'' ?>" title="Half Day">
                                🟡 <?= current_locale()==='am'?'ግማሽ':'H' ?>
                            </button>
                            <button class="btn <?= $st==='on_leave'?'':'secondary' ?>" type="submit" name="status" value="on_leave" 
                                    style="padding:4px 8px;font-size:12px;<?= $st==='on_leave'?'background:#1565c0;border-color:#1565c0':'' ?>" title="On Leave">
                                🏖️ <?= current_locale()==='am'?'ፈቃድ':'L' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================== -->
<!-- VIEW 2: MONTHLY TIMESHEET MATRIX           -->
<!-- ========================================== -->
<?php elseif ($viewMode === 'matrix'): ?>
<div class="card" style="overflow-x:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <h3 style="margin:0">📊 <?= current_locale() === 'am' ? 'የወር የመገኘት ማትሪክስ ሰንጠረዥ — ' . date('F Y', $firstOfMonth) : 'Monthly Staff Timesheet Matrix — ' . date('F Y', $firstOfMonth) ?></h3>
        <div style="display:flex;gap:6px">
            <a class="btn secondary" style="padding:4px 10px" href="?view=matrix&date=<?= h($date) ?>&cal_month=<?= $prevMonth ?>&cal_year=<?= $prevYear ?>&dept_id=<?= $deptFilter ?>">&larr; <?= date('M Y', mktime(0,0,0,$prevMonth,1,$prevYear)) ?></a>
            <a class="btn secondary" style="padding:4px 10px" href="?view=matrix&date=<?= h($date) ?>&cal_month=<?= $nextMonth ?>&cal_year=<?= $nextYear ?>&dept_id=<?= $deptFilter ?>"><?= date('M Y', mktime(0,0,0,$nextMonth,1,$nextYear)) ?> &rarr;</a>
        </div>
    </div>

    <table class="att-matrix" style="width:100%;border-collapse:collapse;font-size:11px;text-align:center">
        <thead>
            <tr style="background:var(--bg)">
                <th style="text-align:left;min-width:180px;padding:6px"><?= current_locale() === 'am' ? 'የሰራተኛ ስም' : 'Staff Member' ?></th>
                <?php for ($d = 1; $d <= $daysInMonth; $d++):
                    $dayOfWeek = date('N', mktime(0,0,0,$calMonth,$d,$calYear));
                    $isWeekend = ($dayOfWeek >= 6);
                ?>
                    <th style="padding:4px 2px;min-width:26px;background:<?= $isWeekend?'rgba(0,0,0,0.05)':'transparent' ?>"><?= $d ?></th>
                <?php endfor; ?>
                <th style="color:#2e7d32;font-weight:bold;padding:6px">P</th>
                <th style="color:#b3261e;font-weight:bold;padding:6px">A</th>
                <th style="color:#e65100;font-weight:bold;padding:6px">H</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employees as $emp):
                $pCount = 0; $aCount = 0; $hCount = 0;
                $empRecords = $matrixData[$emp['id']] ?? [];
            ?>
            <tr style="border-bottom:1px solid var(--border)" data-emp-row="<?= (int)$emp['id'] ?>">
                <td style="text-align:left;padding:6px">
                    <strong><?= h($emp['full_name']) ?></strong>
                    <div style="font-size:10px;color:var(--text-muted)"><?= h($emp['dept_name']) ?></div>
                </td>
                <?php for ($d = 1; $d <= $daysInMonth; $d++):
                    $st = $empRecords[$d] ?? '';
                    $dateStr = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $d);
                    if ($st === 'present') { $pCount++; $mark = 'P'; $cellClass = 'att-p'; }
                    elseif ($st === 'absent') { $aCount++; $mark = 'A'; $cellClass = 'att-a'; }
                    elseif ($st === 'half_day') { $hCount++; $mark = 'H'; $cellClass = 'att-h'; }
                    elseif ($st === 'on_leave') { $mark = 'L'; $cellClass = 'att-l'; }
                    else { $mark = '—'; $cellClass = 'att-blank'; }
                ?>
                    <td class="att-cell <?= $cellClass ?>"
                        data-emp="<?= (int)$emp['id'] ?>"
                        data-date="<?= h($dateStr) ?>"
                        data-status="<?= h($st) ?>"
                        title="<?= h(date('j M', strtotime($dateStr))) ?> — <?= current_locale()==='am'?'ለመቀየር ይጫኑ':'Tap to change' ?>">
                        <?= $mark ?>
                    </td>
                <?php endfor; ?>
                <td class="att-sum-p" style="font-weight:bold;padding:6px"><?= $pCount ?></td>
                <td class="att-sum-a" style="font-weight:bold;padding:6px"><?= $aCount ?></td>
                <td class="att-sum-h" style="font-weight:bold;padding:6px"><?= $hCount ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="display:flex;gap:16px;margin-top:14px;font-size:12px;color:var(--text-muted);flex-wrap:wrap">
        <span class="att-legend att-p">P = <?= current_locale()==='am'?'ተገኝቷል':'Present' ?></span>
        <span class="att-legend att-a">A = <?= current_locale()==='am'?'ቀሪ':'Absent' ?></span>
        <span class="att-legend att-h">H = <?= current_locale()==='am'?'ግማሽ ቀን':'Half Day' ?></span>
        <span class="att-legend att-l">L = <?= current_locale()==='am'?'ፈቃድ':'On Leave' ?></span>
        <span class="att-legend att-blank">— = <?= current_locale()==='am'?'ያልተመዘገበ':'Unmarked' ?></span>
        <span style="margin-left:auto"><?= current_locale()==='am'?'💡 ሁኔታን ለመቀየር ማንኛውንም ካሬ ይጫኑ':'💡 Click/tap any square to cycle its status' ?></span>
    </div>
    <div id="attSaveToast" class="att-toast" hidden></div>
    <form id="attCsrfForm" style="display:none"><?= csrf_field() ?></form>
</div>

<script>
(function () {
    var table = document.querySelector('.att-matrix');
    if (!table) return;
    var csrfToken = document.querySelector('#attCsrfForm input[name="csrf_token"]').value;
    var order = ['', 'present', 'absent', 'half_day', 'on_leave'];
    var marks = { '': '—', present: 'P', absent: 'A', half_day: 'H', on_leave: 'L' };
    var classes = { '': 'att-blank', present: 'att-p', absent: 'att-a', half_day: 'att-h', on_leave: 'att-l' };

    function recount(row) {
        var p = 0, a = 0, h = 0;
        row.querySelectorAll('.att-cell').forEach(function (c) {
            if (c.dataset.status === 'present') p++;
            else if (c.dataset.status === 'absent') a++;
            else if (c.dataset.status === 'half_day') h++;
        });
        row.querySelector('.att-sum-p').textContent = p;
        row.querySelector('.att-sum-a').textContent = a;
        row.querySelector('.att-sum-h').textContent = h;
    }

    function toast(msg, isError) {
        var el = document.getElementById('attSaveToast');
        el.textContent = msg;
        el.className = 'att-toast' + (isError ? ' att-toast-error' : '');
        el.hidden = false;
        clearTimeout(el._t);
        el._t = setTimeout(function () { el.hidden = true; }, 1500);
    }

    table.addEventListener('click', function (e) {
        var cell = e.target.closest('.att-cell');
        if (!cell || cell.classList.contains('att-busy')) return;

        var current = cell.dataset.status || '';
        var next = order[(order.indexOf(current) + 1) % order.length];
        var prevMark = cell.textContent, prevClass = cell.className, prevStatus = current;

        cell.classList.add('att-busy');
        cell.textContent = marks[next];
        cell.className = 'att-cell att-busy ' + classes[next];

        var body = new URLSearchParams({
            csrf_token: csrfToken,
            employee_id: cell.dataset.emp,
            attendance_date: cell.dataset.date,
            status: next
        });

        fetch('/aleltu-eifms/modules/employees/attendance_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            cell.classList.remove('att-busy');
            if (!data.ok) throw new Error(data.error || 'failed');
            cell.dataset.status = next;
            recount(cell.closest('tr'));
            toast('<?= current_locale()==='am'?'✓ ተቀምጧል':'✓ Saved' ?>', false);
        })
        .catch(function () {
            // Roll back on failure so the register never shows an unsaved mark
            cell.classList.remove('att-busy');
            cell.textContent = prevMark;
            cell.className = prevClass;
            cell.dataset.status = prevStatus;
            toast('<?= current_locale()==='am'?'✗ አልተሳካም፣ እንደገና ይሞክሩ':'✗ Could not save, try again' ?>', true);
        });
    });
})();
</script>

<!-- ========================================== -->
<!-- VIEW 3: FULL VISUAL CALENDAR               -->
<!-- ========================================== -->
<?php else: ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <h3 style="margin:0">🗓️ <?= date('F Y', $firstOfMonth) ?> — <?= current_locale() === 'am' ? 'የወር የቀን መቁጠሪያ እይታ' : 'Full Month Attendance Calendar' ?></h3>
        <div style="display:flex;gap:6px">
            <a class="btn secondary" href="?view=calendar&date=<?= h($date) ?>&cal_month=<?= $prevMonth ?>&cal_year=<?= $prevYear ?>&dept_id=<?= $deptFilter ?>">&larr; <?= date('F Y', mktime(0,0,0,$prevMonth,1,$prevYear)) ?></a>
            <a class="btn secondary" href="?view=calendar&date=<?= h($date) ?>&cal_month=<?= $nextMonth ?>&cal_year=<?= $nextYear ?>&dept_id=<?= $deptFilter ?>"><?= date('F Y', mktime(0,0,0,$nextMonth,1,$nextYear)) ?> &rarr;</a>
        </div>
    </div>

    <table style="width:100%;border-collapse:collapse;text-align:center">
        <thead>
            <tr style="background:var(--bg)">
                <?php foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $dayName): ?>
                    <th style="padding:10px;width:14.28%"><?= current_locale()==='am'?t('day.'.$dayName, $dayName):$dayName ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $cell = 0;
            echo '<tr>';
            for ($i = 0; $i < $startWeekday; $i++) {
                echo '<td style="background:rgba(0,0,0,0.02);border:1px solid var(--border);height:90px"></td>';
                $cell++;
            }
            for ($d = 1; $d <= $daysInMonth; $d++) {
                if ($cell > 0 && $cell % 7 === 0) echo '</tr><tr>';
                $dateStr = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $d);
                $isSelected = ($dateStr === $date);
                $sum = $monthSummary[$d] ?? null;
                $p = (int)($sum['present'] ?? 0);
                $a = (int)($sum['absent'] ?? 0);
                $h = (int)($sum['half_day'] ?? 0);
                
                $cellBg = $isSelected ? 'rgba(46,125,50,0.08)' : '#fff';
                $border = $isSelected ? '2px solid var(--primary)' : '1px solid var(--border)';
                
                echo '<td style="background:' . $cellBg . ';border:' . $border . ';height:90px;vertical-align:top;padding:8px;text-align:left">';
                echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">';
                echo '<span style="font-size:16px;font-weight:bold;' . ($isSelected?'color:var(--primary)':'') . '">' . $d . '</span>';
                echo '<a href="?view=roster&date=' . $dateStr . '&cal_month=' . $calMonth . '&cal_year=' . $calYear . '&dept_id=' . $deptFilter . '" class="btn secondary" style="padding:1px 6px;font-size:10px">Open →</a>';
                echo '</div>';
                
                if ($p > 0 || $a > 0 || $h > 0) {
                    echo '<div style="font-size:11px;display:flex;flex-direction:column;gap:2px">';
                    if ($p > 0) echo '<span style="color:#2e7d32;font-weight:600">🟢 ' . $p . ' present</span>';
                    if ($a > 0) echo '<span style="color:#b3261e;font-weight:600">🔴 ' . $a . ' absent</span>';
                    if ($h > 0) echo '<span style="color:#e65100">🟡 ' . $h . ' half-day</span>';
                    echo '</div>';
                } else {
                    echo '<div style="font-size:11px;color:var(--text-muted);font-style:italic">No records</div>';
                }
                echo '</td>';
                $cell++;
            }
            while ($cell % 7 !== 0) {
                echo '<td style="background:rgba(0,0,0,0.02);border:1px solid var(--border);height:90px"></td>';
                $cell++;
            }
            echo '</tr>';
            ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
