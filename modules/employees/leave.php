<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'hr_officer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['decision'])) {
            require_permission('employees', 'approve');
            $decision = $_POST['decision'];
            $leaveId  = (int)$_POST['leave_id'];
            leave_decide($leaveId, $decision);
            log_activity('employees', $decision, 'leave_requests', $leaveId, "Leave {$decision} by " . (current_user()['name'] ?? 'system'));
            flash('Leave request ' . $decision . 'd.');
        } else {
            require_permission('employees', 'create');
            $newId = leave_request((int)$_POST['employee_id'], $_POST['start_date'], $_POST['end_date'], $_POST['reason'] ?? '');
            log_activity('employees', 'create', 'leave_requests', (int)db()->lastInsertId(), "Leave {$_POST['start_date']} to {$_POST['end_date']}");
            // Notify HR
            notify_role('hr_officer', 'New Leave Request', 'A new leave request has been submitted and requires approval.', BASE_URL . '/modules/employees/leave.php');
            flash('Leave requested.');
        }
        header('Location: ' . BASE_URL . '/modules/employees/leave.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';

$employees = db()->query("SELECT id, employee_number, full_name FROM employees WHERE status = 'active' ORDER BY full_name")->fetchAll();
$leaves = db()->query(
    "SELECT l.*, e.full_name, e.employee_number FROM leave_requests l JOIN employees e ON e.id = l.employee_id ORDER BY l.id DESC LIMIT 30"
)->fetchAll();
?>
<h2 class="mt-0">🏖️ <?= h(t('nav.leave_requests', 'Leave Requests')) ?></h2>
<p class="text-muted"><?= current_locale() === 'am' ? 'የሰራተኞች የፈቃድ ጥያቄ ማቅረቢያ፣ መመርመሪያ እና ማጽደቂያ። በአንድ ወቅት የተደራረበ ፈቃድ በሲስተሙ አውቶማቲክ ይከለከላል።' : 'Overlapping already-approved leave for the same employee is blocked automatically.' ?></p>

<div class="card" style="max-width:500px">
    <h3><?= current_locale() === 'am' ? 'አዲስ የፈቃድ ጥያቄ አቅርብ' : 'New Leave Request' ?></h3>
    <form method="post"><?= csrf_field() ?>
        <div class="form-group"><label><?= h(t('common.name', 'Employee')) ?> *</label>
            <select name="employee_id" required>
                <?php foreach ($employees as $e): ?><option value="<?= (int) $e['id'] ?>"><?= h($e['employee_number']) ?> — <?= h($e['full_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <div class="form-group"><label><?= current_locale() === 'am' ? 'የመጀመሪያ ቀን *' : 'Start Date *' ?></label><input type="date" name="start_date" required></div>
            <div class="form-group"><label><?= current_locale() === 'am' ? 'የማብቂያ ቀን *' : 'End Date *' ?></label><input type="date" name="end_date" required></div>
        </div>
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የፈቃድ ምክንያት' : 'Reason' ?></label><input type="text" name="reason" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ የዓመት ፈቃድ ወይም የህክምና' : 'e.g. Annual leave or medical' ?>"></div>
        <button class="btn" type="submit"><?= current_locale() === 'am' ? 'ጥያቄውን አስገባ' : 'Submit Request' ?></button>
    </form>
</div>

<div class="card">
    <h3 style="margin-top:0">📋 <?= current_locale() === 'am' ? 'ሁሉም የፈቃድ ጥያቄዎች' : 'All Leave Requests' ?></h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= h(t('common.name', 'Employee')) ?></th>
                    <th><?= current_locale() === 'am' ? 'የፈቃድ ቀናት (ኢትዮጵያ)' : 'Dates' ?></th>
                    <th><?= current_locale() === 'am' ? 'ምክንያት' : 'Reason' ?></th>
                    <th><?= h(t('common.status', 'Status')) ?></th>
                    <th><?= h(t('common.actions', 'Decide')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaves as $l): 
                    $stText = match($l['status']) {
                        'approved' => (current_locale() === 'am' ? 'ተፈቅዷል' : 'Approved'),
                        'rejected' => (current_locale() === 'am' ? 'ተቀባይነት አላገኘም' : 'Rejected'),
                        default => (current_locale() === 'am' ? 'በመጠባበቅ ላይ' : 'Pending')
                    };
                ?>
                    <tr>
                        <td><strong><?= h($l['full_name']) ?></strong> (<code><?= h($l['employee_number']) ?></code>)</td>
                        <td style="white-space:nowrap">📅 <?= eth_date($l['start_date']) ?> → <?= eth_date($l['end_date']) ?></td>
                        <td><?= h($l['reason'] ?: '—') ?></td>
                        <td><span class="badge <?= $l['status']==='approved'?'active':($l['status']==='rejected'?'sold':'dry') ?>"><?= h($stText) ?></span></td>
                        <td>
                            <?php if ($l['status'] === 'pending'): ?>
                                <form method="post" style="display:inline"><?= csrf_field() ?>
                                    <input type="hidden" name="leave_id" value="<?= (int) $l['id'] ?>">
                                    <input type="hidden" name="decision" value="approve">
                                    <button class="btn secondary" type="submit" style="padding:4px 10px;white-space:nowrap">✓ <?= current_locale() === 'am' ? 'ፍቀድ' : 'Approve' ?></button>
                                </form>
                                <form method="post" style="display:inline"><?= csrf_field() ?>
                                    <input type="hidden" name="leave_id" value="<?= (int) $l['id'] ?>">
                                    <input type="hidden" name="decision" value="reject">
                                    <button class="btn danger" type="submit" style="padding:4px 10px;white-space:nowrap">✕ <?= current_locale() === 'am' ? 'ከልክል' : 'Reject' ?></button>
                                </form>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($leaves)): ?><tr><td colspan="5" class="text-muted"><?= h(t('common.no_records', 'No leave requests yet.')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
