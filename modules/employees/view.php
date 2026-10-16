<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'hr_officer']);

$id = (int) ($_GET['id'] ?? 0);

$emp = $id > 0 ? db()->query("SELECT e.*, d.name AS department_name FROM employees e LEFT JOIN departments d ON d.id = e.department_id WHERE e.id = {$id}")->fetch() : null;
if (!$emp) {
    $firstId = db()->query("SELECT id FROM employees WHERE status != 'terminated' ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($firstId) {
        $id = (int)$firstId;
        $emp = db()->query("SELECT e.*, d.name AS department_name FROM employees e LEFT JOIN departments d ON d.id = e.department_id WHERE e.id = {$id}")->fetch();
    } else {
        flash('No employee records found.', 'error');
        header('Location: /aleltu-eifms/modules/employees/list.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['link_user'])) {
            $newUserId = $_POST['user_id'] !== '' ? (int) $_POST['user_id'] : null;
            employee_link_user($id, $newUserId);
            flash(t('ss.login_updated', 'Self-service login account updated.'));
        } elseif (isset($_POST['change_status'])) {
            $newStatus = $_POST['new_status'] ?? 'active';
            $reason = trim($_POST['status_reason'] ?? '');
            if (empty($reason)) {
                throw new InvalidArgumentException('A reason is mandatory for employment status changes.');
            }

            $oldStatus = $emp['status'];
            db()->beginTransaction();

            // Update employee status
            db()->prepare("UPDATE employees SET status = ? WHERE id = ?")->execute([$newStatus, $id]);

            // If employee has a linked user account, keep is_active in sync
            if ($emp['user_id']) {
                $userActive = ($newStatus === 'active') ? 1 : 0;
                db()->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$userActive, (int)$emp['user_id']]);
            }

            // Record status change log
            $stmt = db()->prepare("INSERT INTO emp_status_logs (employee_id, old_status, new_status, reason, changed_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id, $oldStatus, $newStatus, $reason, current_user()['id']]);

            db()->commit();
            log_activity('employees', 'update', 'employees', $id, "Changed employee status from {$oldStatus} to {$newStatus}. Reason: {$reason}");
            flash("Employment status updated to {$newStatus}.");
        }
        header("Location: /aleltu-eifms/modules/employees/view.php?id={$id}");
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

$linkedUser = null;
if ($emp['user_id']) {
    $linkedUser = db()->query("SELECT id, name, email, is_active FROM users WHERE id = " . (int) $emp['user_id'])->fetch();
}

$availableUsers = db()->query(
    "SELECT u.id, u.name, u.email FROM users u
     WHERE NOT EXISTS (SELECT 1 FROM employees e2 WHERE e2.user_id = u.id AND e2.id != {$id})
     ORDER BY u.name"
)->fetchAll();

$attendance = db()->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY attendance_date DESC LIMIT 14");
$attendance->execute([$id]);
$attendance = $attendance->fetchAll();

$leaves = db()->prepare("SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY start_date DESC LIMIT 10");
$leaves->execute([$id]);
$leaves = $leaves->fetchAll();

$tasks = db()->prepare("SELECT * FROM tasks WHERE assigned_to = ? ORDER BY due_date DESC LIMIT 10");
$tasks->execute([$id]);
$tasks = $tasks->fetchAll();

$statusLogs = db()->prepare("SELECT s.*, u.name AS changer_name FROM emp_status_logs s JOIN users u ON u.id = s.changed_by WHERE s.employee_id = ? ORDER BY s.created_at DESC");
$statusLogs->execute([$id]);
$statusLogs = $statusLogs->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px"><?= h($emp['full_name']) ?>
            <span class="badge <?= $emp['status'] === 'active' ? 'milking' : ($emp['status'] === 'on_leave' ? 'dry' : 'sold') ?>">
                <?= h(ucfirst(str_replace('_', ' ', $emp['status']))) ?>
            </span>
        </h2>
        <p class="text-muted" style="margin:0"><?= h($emp['employee_number']) ?> &bull; <?= h($emp['department_name'] ?: 'No Department') ?> &bull; <?= h($emp['job_title'] ?: 'No Title') ?></p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="/aleltu-eifms/modules/employees/photo_upload.php?id=<?= (int)$id ?>" class="btn secondary">📷 Photo</a>
        <a href="/aleltu-eifms/modules/employees/id_card.php?id=<?= (int)$id ?>" target="_blank" class="btn secondary">🆔 ID Card</a>
        <a href="/aleltu-eifms/modules/employees/list.php" class="btn secondary">&larr; Back to Directory</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
    <!-- Employment Status Management Card -->
    <div class="card">
        <h3>⚖️ Employment Status Management</h3>
        <p class="text-muted" style="font-size:13px">Change staff active status with mandatory documentation. Historical records will remain intact.</p>
        
        <form method="post" onsubmit="return confirm('Confirm employment status change?');"><?= csrf_field() ?>
            <input type="hidden" name="change_status" value="1">
            <div class="form-group">
                <label>New Employment Status *</label>
                <select name="new_status" required>
                    <option value="active" <?= $emp['status'] === 'active' ? 'selected' : '' ?>>Active (Working)</option>
                    <option value="on_leave" <?= $emp['status'] === 'on_leave' ? 'selected' : '' ?>>On Approved Leave</option>
                    <option value="terminated" <?= $emp['status'] === 'terminated' ? 'selected' : '' ?>>Terminated / Inactive (Not Working)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Reason for Status Change *</label>
                <select name="status_reason" required style="margin-bottom:8px">
                    <option value="Normal active duty">Normal active duty</option>
                    <option value="Annual / Medical leave">Annual / Medical leave</option>
                    <option value="Resigned">Resigned</option>
                    <option value="Contract ended">Contract ended</option>
                    <option value="Temporarily inactive">Temporarily inactive</option>
                    <option value="Disciplinary termination">Disciplinary termination</option>
                </select>
            </div>
            <button class="btn" type="submit">Update Employment Status</button>
        </form>
    </div>

    <!-- Self-Service Account Linkage -->
    <div class="card" style="<?= $linkedUser ? '' : 'border-left:4px solid #c62828' ?>">
        <h3>👤 Self-Service Login Linkage</h3>
        <?php if ($linkedUser): ?>
            <p>✅ Linked to login account: <strong><?= h($linkedUser['name']) ?></strong>
                <span class="text-muted" style="font-size:12px">(Email: <?= h($linkedUser['email']) ?>)</span>
                <?php if ($linkedUser['is_active'] == 0): ?>
                    <br><strong style="color:#b3261e">⚠️ Account is currently INACTIVE.</strong>
                <?php endif; ?>
            </p>
            <p class="text-muted" style="font-size:12px;margin-top:0">
                🔑 Login username: <code style="background:var(--bg);padding:2px 6px;border-radius:4px"><?= h($linkedUser['name']) ?></code>
            </p>
        <?php else: ?>
            <p class="text-muted">⚠️ Not linked to any login account yet — employee cannot access self-service portal.</p>
        <?php endif; ?>

        <form method="post" style="display:flex;gap:8px;margin-top:10px"><?= csrf_field() ?>
            <input type="hidden" name="link_user" value="1">
            <select name="user_id" style="flex:1">
                <option value="">— No login account —</option>
                <?php foreach ($availableUsers as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= $linkedUser && $linkedUser['id'] == $u['id'] ? 'selected' : '' ?>>
                        <?= h($u['name']) ?> <span style="color:#999">(<?= h($u['email']) ?>)</span>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn secondary" type="submit">Save Link</button>
        </form>
    </div>
</div>

<!-- Status Change Audit Trail -->
<?php if (!empty($statusLogs)): ?>
<div class="card" style="margin-bottom:20px">
    <h3 style="margin-top:0">📜 Employment Status Change History</h3>
    <table>
        <thead>
            <tr>
                <th>Date / Time</th>
                <th>Previous Status</th>
                <th>New Status</th>
                <th>Reason</th>
                <th>Changed By</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($statusLogs as $log): ?>
                <tr>
                    <td style="font-size:12px"><?= eth_datetime($log['created_at']) ?></td>
                    <td><span class="badge"><?= h($log['old_status']) ?></span></td>
                    <td><span class="badge active"><?= h($log['new_status']) ?></span></td>
                    <td><?= h($log['reason']) ?></td>
                    <td><strong><?= h($log['changer_name']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <!-- Recent Attendance -->
    <div class="card">
        <h3>Recent Attendance (14 Days)</h3>
        <table>
            <tr><th>Date</th><th>Status</th><th>Check-in</th></tr>
            <?php foreach ($attendance as $a): ?>
                <tr>
                    <td><?= eth_date($a['attendance_date']) ?></td>
                    <td><span class="badge <?= $a['status'] === 'present' ? 'milking' : 'sold' ?>"><?= h(ucfirst($a['status'])) ?></span></td>
                    <td><?= $a['check_in_at'] ? eth_time($a['check_in_at']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($attendance)): ?>
                <tr><td colspan="3" class="text-muted">No attendance logs.</td></tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Leave Requests -->
    <div class="card">
        <h3>Leave Requests</h3>
        <table>
            <tr><th>Dates</th><th>Reason</th><th>Status</th></tr>
            <?php foreach ($leaves as $l): ?>
                <tr>
                    <td><?= eth_date($l['start_date']) ?> → <?= eth_date($l['end_date']) ?></td>
                    <td><?= h($l['reason'] ?: '—') ?></td>
                    <td><span class="badge <?= $l['status'] === 'approved' ? 'milking' : 'dry' ?>"><?= h($l['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($leaves)): ?>
                <tr><td colspan="3" class="text-muted">No leave requests.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
