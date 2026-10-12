<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'finance_officer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['finalize_run'])) {
            payroll_finalize((int) $_POST['finalize_run']);
            flash('የደመወዝ ክፍያ ዙሩ በሰራተኞች የመገኘት መዝገብ መሠረት ጸድቋል (Payroll finalized based on attendance).');
        } elseif (isset($_POST['record_payment'])) {
            $payslipId = (int)$_POST['payslip_id'];
            $amount = (float)$_POST['amount_paid'];
            $method = $_POST['payment_method'] ?? 'Cash';
            $notes = trim($_POST['notes'] ?? '');
            
            payroll_record_payment($payslipId, $amount, $method, $notes, current_user()['id']);
            flash('የደመወዝ ክፍያ በተሳካ ሁኔታ ተመዝግቧል (Salary payment transaction recorded).');
        } else {
            payroll_run_create($_POST['period_start'], $_POST['period_end']);
            flash('አዲስ የደመወዝ ክፍያ ዙር ተፈጥሯል (Payroll run created).');
        }
        header('Location: /aleltu-eifms/modules/finance/payroll.php');
        exit;
    } catch (Throwable $e) {
        flash('ስህተት፦ ' . $e->getMessage(), 'error');
    }
}
if (($_GET['export'] ?? '') === 'csv' && isset($_GET['run_id'])) {
    require_once __DIR__ . '/../../includes/export.php';
    $stmt = db()->prepare(
        "SELECT e.full_name, e.employee_number, p.base_salary, p.days_present, p.net_pay
         FROM fin_payslips p JOIN employees e ON e.id = p.employee_id WHERE p.payroll_run_id = ?"
    );
    $stmt->execute([(int) $_GET['run_id']]);
    export_csv($stmt->fetchAll(), 'payroll_run_' . (int) $_GET['run_id'] . '.csv');
}

require_once __DIR__ . '/../../includes/header.php';

// Fetch all runs
$allRuns = db()->query("SELECT * FROM fin_payroll_runs ORDER BY id DESC")->fetchAll();

// Month / Run Filter
$selectedRunId = isset($_GET['run_id']) && $_GET['run_id'] !== 'all' ? (int)$_GET['run_id'] : 0;
if ($selectedRunId > 0) {
    $runs = array_filter($allRuns, fn($r) => (int)$r['id'] === $selectedRunId);
} else {
    $runs = $allRuns;
}
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">💼 <?= h(t('nav.payroll', 'የደመወዝ ክፍያ አስተዳደር')) ?></h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' ? 'የደመወዝ ክፍያ ስሌት የሚዘጋጀው እና የሚጸድቀው የሰራተኞች የወሩ የሙሉ ጊዜ የገቢ መዝገብ (Attendance) ከተረጋገጠ በኋላ ነው።' : 'A payroll run can only be finalized once every active employee has attendance recorded for the whole period.' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="/aleltu-eifms/modules/finance/dashboard.php" class="btn secondary">← <?= current_locale()==='am'?'ወደ ፋይናንስ ማዕከል':'Finance Dashboard' ?></a>
    </div>
</div>

<!-- Month / Run Filter Tabs -->
<?php if (!empty($allRuns)): ?>
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
    <span style="font-weight:600;font-size:14px">📅 <?= current_locale() === 'am' ? 'የክፍለ-ጊዜ ማጣሪያ፦' : 'Filter Period:' ?></span>
    <a href="?run_id=all" class="btn <?= $selectedRunId === 0 ? '' : 'secondary' ?>" style="padding:4px 12px;font-size:13px">
        <?= current_locale() === 'am' ? 'ሁሉም ዙሮች (' . count($allRuns) . ')' : 'All Runs (' . count($allRuns) . ')' ?>
    </a>
    <?php foreach ($allRuns as $r): ?>
        <?php
        $ethPeriod = eth_date($r['period_start']) . ' — ' . eth_date($r['period_end']);
        $isActiveTab = ($selectedRunId === (int)$r['id']);
        ?>
        <a href="?run_id=<?= (int)$r['id'] ?>" class="btn <?= $isActiveTab ? '' : 'secondary' ?>" style="padding:4px 12px;font-size:13px">
            <?= h($ethPeriod) ?> (<?= $r['status'] === 'finalized' ? '✓' : '...' ?>)
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Create New Payroll Run -->
<div class="card" style="max-width:550px;margin-bottom:20px">
    <h3 style="margin-top:0">➕ <?= current_locale() === 'am' ? 'አዲስ የደመወዝ ክፍያ ዙር ጀምር' : 'New Payroll Run' ?></h3>
    <form method="post"><?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የክፍለ-ጊዜ መጀመሪያ ቀን *' : 'Period Start *' ?></label>
                <input type="date" name="period_start" required>
            </div>
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የክፍለ-ጊዜ ማብቂያ ቀን *' : 'Period End *' ?></label>
                <input type="date" name="period_end" required>
            </div>
        </div>
        <button class="btn" type="submit">📅 <?= current_locale() === 'am' ? 'የደመወዝ ዙር ፍጠር' : 'Create Run' ?></button>
    </form>
</div>

<!-- Payroll Runs and Details -->
<div class="card">
    <h3 style="margin-top:0">📋 <?= current_locale() === 'am' ? 'የደመወዝ ክፍያ ዙሮች እና የክፍያ ሰነዶች' : 'Payroll Runs' ?></h3>
    <?php if (empty($runs)): ?>
        <p class="text-muted"><?= h(t('common.no_records', 'ምንም የተመዘገበ የደመወዝ ዙር የለም።')) ?></p>
    <?php else: ?>
        <?php foreach ($runs as $run): ?>
            <?php
            $payslipsStmt = db()->prepare("SELECT p.*, e.full_name, e.employee_number FROM fin_payslips p JOIN employees e ON e.id = p.employee_id WHERE p.payroll_run_id = ?");
            $payslipsStmt->execute([$run['id']]);
            $payslips = $payslipsStmt->fetchAll();
            $canFinalize = payroll_can_finalize($run['period_start'], $run['period_end']);
            $stText = $run['status'] === 'finalized' ? (current_locale() === 'am' ? 'የጸደቀ / ክፍያ የጀመረ' : 'Finalized') : (current_locale() === 'am' ? 'በዝግጅት ላይ (ረቂቅ)' : 'Draft');
            
            // Totals
            $totalNet = array_sum(array_column($payslips, 'net_pay'));
            $totalPaid = array_sum(array_column($payslips, 'total_paid'));
            $totalRemaining = max(0, $totalNet - $totalPaid);
            ?>
            <div style="border:1px solid #dfe5dd;border-radius:8px;padding:16px;margin-bottom:20px;background:var(--card)">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;border-bottom:1px solid #dfe5dd;padding-bottom:10px">
                    <div>
                        <span style="font-size:16px;font-weight:700">
                            📅 <?= eth_date($run['period_start']) ?> <?= current_locale() === 'am' ? 'እስከ' : 'to' ?> <?= eth_date($run['period_end']) ?>
                        </span>
                        <span class="badge <?= $run['status']==='finalized'?'active':'dry' ?>" style="margin-left:8px"><?= h($stText) ?></span>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px">
                            (<?= h($run['period_start']) ?> to <?= h($run['period_end']) ?>)
                        </div>
                    </div>
                    <div>
                        <?php if ($run['status'] !== 'finalized'): ?>
                            <?php if ($canFinalize): ?>
                                <form method="post" style="display:inline"><?= csrf_field() ?>
                                    <input type="hidden" name="finalize_run" value="<?= (int) $run['id'] ?>">
                                    <button class="btn" type="submit" style="padding:6px 14px" onclick="return confirm('<?= current_locale() === 'am' ? 'ደመወዙን አጽድቀህ የክፍያ ሰነዶችን ማመንጨት ትፈልጋለህ?' : 'Finalize payroll and generate payslips for all active employees?' ?>');">
                                        ✓ <?= h(t('payroll.finalize', 'ደመወዙን አጽድቅ (Finalize)')) ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge dry">⏳ <?= current_locale() === 'am' ? 'ሁሉም ሰራተኞች የመገኘት መዝገብ (Attendance) ገና አላጠናቀቁም' : 'Incomplete attendance' ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="?export=csv&run_id=<?= (int) $run['id'] ?>" class="btn secondary" style="padding:5px 12px;font-size:13px">
                                ⬇️ <?= h(t('common.export_csv', 'በ CSV አውርድ')) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Run KPIs -->
                <?php if (!empty($payslips)): ?>
                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:14px">
                    <div style="flex:1;min-width:140px;background:var(--hover);padding:10px 14px;border-radius:6px">
                        <div style="font-size:12px;color:var(--text-muted)"><?= current_locale() === 'am' ? 'ጠቅላላ የተጣራ ደመወዝ' : 'Total Net Pay' ?></div>
                        <div style="font-size:16px;font-weight:700"><?= number_format($totalNet, 2) ?> ETB</div>
                    </div>
                    <div style="flex:1;min-width:140px;background:var(--hover);padding:10px 14px;border-radius:6px">
                        <div style="font-size:12px;color:#2e7d32"><?= current_locale() === 'am' ? 'የተከፈለ' : 'Total Paid' ?></div>
                        <div style="font-size:16px;font-weight:700;color:#2e7d32"><?= number_format($totalPaid, 2) ?> ETB</div>
                    </div>
                    <div style="flex:1;min-width:140px;background:var(--hover);padding:10px 14px;border-radius:6px">
                        <div style="font-size:12px;color:#c62828"><?= current_locale() === 'am' ? 'ቀሪ ያልተከፈለ' : 'Remaining' ?></div>
                        <div style="font-size:16px;font-weight:700;color:#c62828"><?= number_format($totalRemaining, 2) ?> ETB</div>
                    </div>
                </div>

                <div style="overflow-x:auto">
                    <table>
                        <thead>
                            <tr>
                                <th><?= current_locale() === 'am' ? 'ሰራተኛ' : 'Employee' ?></th>
                                <th><?= current_locale() === 'am' ? 'ወርሃዊ መነሻ' : 'Base Salary' ?></th>
                                <th><?= current_locale() === 'am' ? 'የቀን ተመን' : 'Daily Rate' ?></th>
                                <th><?= current_locale() === 'am' ? 'የተገኙበት ቀን' : 'Present Days' ?></th>
                                <th><?= current_locale() === 'am' ? 'የተጣራ ደመወዝ' : 'Net Pay' ?></th>
                                <th><?= current_locale() === 'am' ? 'የተከፈለ' : 'Paid' ?></th>
                                <th><?= current_locale() === 'am' ? 'ቀሪ' : 'Remaining' ?></th>
                                <th><?= current_locale() === 'am' ? 'ክፍያ መዝግብ' : 'Record Payment' ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payslips as $p): ?>
                                <?php $remaining = (float)$p['net_pay'] - (float)$p['total_paid']; ?>
                                <tr>
                                    <td>
                                        <strong><?= h($p['full_name']) ?></strong><br>
                                        <small class="text-muted"><?= h($p['employee_number'] ?? '') ?></small>
                                    </td>
                                    <td><?= number_format($p['base_salary'], 2) ?> ETB</td>
                                    <td><?= number_format($p['daily_rate'], 2) ?> ETB</td>
                                    <td><?= (int)$p['days_present'] ?> <?= current_locale() === 'am' ? 'ቀናት' : 'Days' ?></td>
                                    <td><strong><?= number_format($p['net_pay'], 2) ?> ETB</strong></td>
                                    <td><span class="badge milking"><?= number_format($p['total_paid'], 2) ?> ETB</span></td>
                                    <td>
                                        <?php if ($remaining > 0): ?>
                                            <span class="badge sold"><?= number_format($remaining, 2) ?> ETB</span>
                                        <?php else: ?>
                                            <span class="badge milking">✓ <?= current_locale() === 'am' ? '100% ተከፍሏል' : 'Paid' ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($remaining > 0 && $run['status'] === 'finalized'): ?>
                                            <form method="post" style="display:flex;gap:4px;flex-wrap:wrap;align-items:center"><?= csrf_field() ?>
                                                <input type="hidden" name="payslip_id" value="<?= $p['id'] ?>">
                                                <input type="hidden" name="record_payment" value="1">
                                                <input type="number" step="0.01" name="amount_paid" value="<?= $remaining ?>" required style="width:85px;padding:3px 6px;font-size:12px">
                                                <select name="payment_method" style="width:80px;padding:3px;font-size:12px">
                                                    <option value="Cash"><?= current_locale() === 'am' ? 'ጥሬ' : 'Cash' ?></option>
                                                    <option value="Bank Transfer"><?= current_locale() === 'am' ? 'ባንክ' : 'Bank' ?></option>
                                                </select>
                                                <button class="btn secondary" type="submit" style="padding:3px 8px;font-size:12px"><?= current_locale() === 'am' ? 'ክፈል' : 'Pay' ?></button>
                                            </form>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

