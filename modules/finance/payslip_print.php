<?php
/**
 * Payslip Print View — clean printable layout
 * Accessible to: the employee themselves OR HR/Finance/GM
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$slipId = (int)($_GET['id'] ?? 0);
$slip = null;
if ($slipId > 0) {
    $stmt = db()->prepare(
        "SELECT p.*, e.full_name, e.employee_number, e.department_id, e.job_title, e.base_salary,
                r.period_start, r.period_end, r.status AS run_status,
                u.name AS approved_by_name
         FROM fin_payslips p
         JOIN employees e ON e.id = p.employee_id
         JOIN fin_payroll_runs r ON r.id = p.payroll_run_id
         LEFT JOIN users u ON u.id = r.processed_by
         WHERE p.id = ?"
    );
    $stmt->execute([$slipId]);
    $slip = $stmt->fetch();
}

if (!$slip) {
    $firstId = db()->query("SELECT id FROM fin_payslips ORDER BY id DESC LIMIT 1")->fetchColumn();
    if ($firstId) {
        $slipId = (int)$firstId;
        $stmt = db()->prepare(
            "SELECT p.*, e.full_name, e.employee_number, e.department_id, e.job_title, e.base_salary,
                    r.period_start, r.period_end, r.status AS run_status,
                    u.name AS approved_by_name
             FROM fin_payslips p
             JOIN employees e ON e.id = p.employee_id
             JOIN fin_payroll_runs r ON r.id = p.payroll_run_id
             LEFT JOIN users u ON u.id = r.processed_by
             WHERE p.id = ?"
        );
        $stmt->execute([$slipId]);
        $slip = $stmt->fetch();
    } else {
        flash('No payslips generated yet.', 'error');
        header('Location: /aleltu-eifms/modules/finance/payroll.php');
        exit;
    }
}

// Access control: self, HR, Finance, GM, super_admin
$me    = current_user();
$myEmp = db()->prepare("SELECT id FROM employees WHERE user_id = ?");
$myEmp->execute([$me['id']]);
$myEmp = $myEmp->fetch();
$isSelf = $myEmp && (int)$myEmp['id'] === (int)$slip['employee_id'];
$isStaff = in_array($me['role'] ?? '', ['super_admin','general_manager','finance_officer','hr_officer']);

if (!$isSelf && !$isStaff) {
    die('Access denied.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payslip — <?= h($slip['full_name']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Arial', sans-serif; font-size: 13px; color: #111; background: #fff; padding: 40px; }
.header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #2e7d32; padding-bottom: 16px; margin-bottom: 24px; }
.company-name { font-size: 20px; font-weight: bold; color: #2e7d32; }
.company-sub { font-size: 12px; color: #666; }
.payslip-title { font-size: 16px; font-weight: bold; text-align: right; }
.period-badge { background: #2e7d32; color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 12px; display: inline-block; margin-top: 4px; }
.section { margin-bottom: 20px; }
.section h3 { font-size: 12px; text-transform: uppercase; color: #666; letter-spacing: 1px; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 4px; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
table { width: 100%; border-collapse: collapse; }
td, th { padding: 7px 10px; border-bottom: 1px solid #eee; text-align: left; }
th { background: #f5f5f5; font-weight: 600; font-size: 12px; }
.total-row td { font-weight: bold; background: #e8f5e9; }
.paid-row td { color: #1565c0; }
.remaining-row td { color: #b3261e; font-weight: bold; }
.footer { margin-top: 40px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
.sig-line { border-top: 1px solid #999; padding-top: 6px; text-align: center; font-size: 11px; color: #666; }
@media print {
    body { padding: 20px; }
    .no-print { display: none; }
    @page { margin: 1cm; }
}
</style>
</head>
<body>

<div class="no-print" style="margin-bottom:20px">
    <button onclick="window.print()" style="padding:8px 18px;background:#2e7d32;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px">🖨 <?= current_locale() === 'am' ? 'የደመወዝ ወረቀት አትም (Print)' : 'Print Payslip' ?></button>
    <a href="javascript:history.back()" style="margin-left:12px;color:#666">← <?= h(t('common.back', 'Back')) ?></a>
</div>

<div class="header">
    <div>
        <div class="company-name">🌿 <?= h(t('company.name')) ?></div>
        <div class="company-sub"><?= h(t('company.tagline')) ?></div>
        <div class="company-sub"><?= current_locale() === 'am' ? 'አለልቱ፣ ኢትዮጵያ' : 'Aleltu, Ethiopia' ?></div>
    </div>
    <div>
        <div class="payslip-title"><?= current_locale() === 'am' ? 'የደመወዝ ክፍያ ማረጋገጫ ወረቀት' : 'PAYSLIP' ?></div>
        <div class="period-badge"><?= h("{$slip['period_start']} → {$slip['period_end']}") ?></div>
        <div style="font-size:11px;color:#666;margin-top:4px"><?= current_locale() === 'am' ? 'ሰነድ ቁጥር #' : 'Slip #' ?><?= h($slipId) ?></div>
    </div>
</div>

<div class="grid-2">
    <div class="section">
        <h3><?= current_locale() === 'am' ? 'የሰራተኛው መረጃ' : 'Employee Details' ?></h3>
        <table>
            <tr><td><strong><?= h(t('emp.full_name', 'Name')) ?></strong></td><td><?= h($slip['full_name']) ?></td></tr>
            <tr><td><strong><?= current_locale() === 'am' ? 'የሰራተኛ መለያ #' : 'Employee No.' ?></strong></td><td><?= h($slip['employee_number']) ?></td></tr>
            <tr><td><strong><?= h(t('emp.dept', 'Department')) ?></strong></td><td><?= h($slip['department_id'] ? 'Dept #'.$slip['department_id'] : '—') ?></td></tr>
            <tr><td><strong><?= h(t('emp.position', 'Position')) ?></strong></td><td><?= h($slip['job_title'] ?: '—') ?></td></tr>
        </table>
    </div>
    <div class="section">
        <h3><?= current_locale() === 'am' ? 'የክፍያው ጊዜ' : 'Pay Period' ?></h3>
        <table>
            <tr><td><strong><?= h(t('common.from', 'From')) ?></strong></td><td><?= h($slip['period_start']) ?></td></tr>
            <tr><td><strong><?= h(t('common.to', 'To')) ?></strong></td><td><?= h($slip['period_end']) ?></td></tr>
            <tr><td><strong><?= current_locale() === 'am' ? 'የክፍያው ሁኔታ' : 'Payroll Status' ?></strong></td><td><?= h($slip['run_status'] === 'finalized' ? (current_locale() === 'am' ? 'የጸደቀ' : 'Finalized') : ucfirst($slip['run_status'])) ?></td></tr>
            <tr><td><strong><?= current_locale() === 'am' ? 'ያጸደቀው ኃላፊ' : 'Approved By' ?></strong></td><td><?= h($slip['approved_by_name'] ?: '—') ?></td></tr>
        </table>
    </div>
</div>

<div class="section">
    <h3><?= current_locale() === 'am' ? 'ገቢ እና ተቀናሽ ዝርዝር' : 'Earnings & Deductions' ?></h3>
    <table>
        <thead><tr><th><?= current_locale() === 'am' ? 'ዝርዝር መግለጫ' : 'Description' ?></th><th style="text-align:right"><?= current_locale() === 'am' ? 'መጠን (ብር)' : 'Amount (ETB)' ?></th></tr></thead>
        <tbody>
            <tr><td><?= h(t('payroll.base_salary', 'Base Monthly Salary')) ?></td><td style="text-align:right"><?= number_format($slip['base_salary'] ?? 0, 2) ?></td></tr>
            <tr><td><?= h(t('payroll.daily_rate', 'Daily Rate')) ?></td><td style="text-align:right"><?= number_format($slip['daily_rate'] ?? 0, 2) ?></td></tr>
            <tr><td><?= current_locale() === 'am' ? 'የሰራበት ቀናት' : 'Days Present / Worked' ?></td><td style="text-align:right"><?= (int)$slip['days_present'] ?> <?= current_locale() === 'am' ? 'ቀናት' : 'day(s)' ?></td></tr>
            <?php if (!empty($slip['allowances'])): ?>
            <tr><td><?= current_locale() === 'am' ? 'አበል' : 'Allowances' ?></td><td style="text-align:right"><?= number_format($slip['allowances'] ?? 0, 2) ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($slip['deductions']) && $slip['deductions'] > 0): ?>
            <tr><td style="color:#b3261e"><?= current_locale() === 'am' ? 'ተቀናሽ' : 'Deductions' ?></td><td style="text-align:right;color:#b3261e">-<?= number_format($slip['deductions'] ?? 0, 2) ?></td></tr>
            <?php endif; ?>
            <tr class="total-row"><td><?= h(t('payroll.net_pay', 'NET PAY')) ?></td><td style="text-align:right"><?= number_format($slip['net_pay'], 2) ?> <?= h(t('common.etb', 'ETB')) ?></td></tr>
            <tr class="paid-row"><td><?= h(t('payroll.total_paid', 'Total Paid')) ?></td><td style="text-align:right"><?= number_format($slip['total_paid'], 2) ?> <?= h(t('common.etb', 'ETB')) ?></td></tr>
            <?php $rem = (float)$slip['net_pay'] - (float)$slip['total_paid']; ?>
            <?php if ($rem > 0): ?>
            <tr class="remaining-row"><td><?= h(t('payroll.remaining', 'Remaining Balance')) ?></td><td style="text-align:right"><?= number_format($rem, 2) ?> <?= h(t('common.etb', 'ETB')) ?></td></tr>
            <?php else: ?>
            <tr><td style="color:#2e7d32;font-weight:bold">✅ <?= current_locale() === 'am' ? 'ሙሉ በሙሉ ተከፍሏል' : 'Fully Paid' ?></td><td></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="footer">
    <div class="sig-line"><?= current_locale() === 'am' ? 'የሰራተኛው ፊርማ' : 'Employee Signature' ?></div>
    <div class="sig-line"><?= current_locale() === 'am' ? 'የሰው ኃይል አስተዳደር (HR)' : 'HR Officer' ?></div>
    <div class="sig-line"><?= current_locale() === 'am' ? 'የፋይናንስ ኃላፊ / ፈቃጅ' : 'Finance Officer / Authorised' ?></div>
</div>

<div style="margin-top:30px;font-size:10px;color:#999;text-align:center">
    <?= current_locale() === 'am' ? 'በአለልቱ የተቀናጀ እርሻ አመራር ስርዓት የተዘጋጀ' : 'Generated by Aleltu EIFMS' ?> &bull; <?= date('d M Y, H:i') ?> &bull; <?= current_locale() === 'am' ? 'ይህ ሰነድ በኮምፒውተር የወጣ ህጋዊ የደመወዝ ሰነድ ነው።' : 'This is a system-generated document.' ?>
</div>

</body>
</html>
