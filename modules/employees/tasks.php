<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'hr_officer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['complete_task_id'])) {
            task_complete((int) $_POST['complete_task_id']);
            flash('Task marked complete.');
        } else {
            task_assign((int) $_POST['employee_id'], $_POST['title'], $_POST['description'] ?? '', $_POST['module'] ?? '', $_POST['due_date'] ?? '', current_user()['id']);
            flash('Task assigned.');
        }
        header('Location: /aleltu-eifms/modules/employees/tasks.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';

$employees = db()->query("SELECT id, employee_number, full_name FROM employees WHERE status = 'active' ORDER BY full_name")->fetchAll();
$tasks = db()->query(
    "SELECT t.*, e.full_name, e.employee_number FROM tasks t JOIN employees e ON e.id = t.assigned_to ORDER BY t.id DESC LIMIT 30"
)->fetchAll();
?>
<h2 class="mt-0">📋 <?= h(t('nav.tasks', 'Daily Task Assignment')) ?></h2>

<div class="card" style="max-width:500px">
    <h3><?= current_locale() === 'am' ? 'አዲስ የስራ ተግባር መመደቢያ' : 'Assign Task' ?></h3>
    <form method="post"><?= csrf_field() ?>
        <div class="form-group"><label><?= h(t('common.name', 'Employee')) ?> *</label>
            <select name="employee_id" required>
                <?php foreach ($employees as $e): ?><option value="<?= (int) $e['id'] ?>"><?= h($e['employee_number']) ?> — <?= h($e['full_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የተግባሩ ርዕስ *' : 'Title *' ?></label><input type="text" name="title" required placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ የላሞች ማደሪያ ማጽዳት' : 'e.g. Clean cow sheds' ?>"></div>
        <div class="form-group"><label><?= current_locale() === 'am' ? 'ዝርዝር ማብራሪያ' : 'Description' ?></label><textarea name="description" rows="2"></textarea></div>
        <div class="form-row">
            <div class="form-group"><label><?= current_locale() === 'am' ? 'የስራ ክፍል' : 'Module (context)' ?></label>
                <select name="module">
                    <option value="">— <?= current_locale() === 'am' ? 'አጠቃላይ' : 'general' ?> —</option>
                    <option value="dairy"><?= current_locale() === 'am' ? 'የወተት ክፍል' : 'Dairy' ?></option>
                    <option value="poultry"><?= current_locale() === 'am' ? 'የዶሮ ክፍል' : 'Poultry' ?></option>
                    <option value="fish"><?= current_locale() === 'am' ? 'የዓሳ ክፍል' : 'Fish' ?></option>
                    <option value="warehouse"><?= current_locale() === 'am' ? 'መጋዘን' : 'Warehouse' ?></option>
                </select>
            </div>
            <div class="form-group"><label><?= current_locale() === 'am' ? 'የማጠናቀቂያ ቀን' : 'Due Date' ?></label><input type="date" name="due_date"></div>
        </div>
        <button class="btn" type="submit"><?= current_locale() === 'am' ? 'ተግባሩን መድብ' : 'Assign Task' ?></button>
    </form>
</div>

<div class="card">
    <h3 style="margin-top:0">📋 <?= current_locale() === 'am' ? 'የተመደቡ ተግባራት ዝርዝር' : 'All Tasks' ?></h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= h(t('common.name', 'Employee')) ?></th>
                    <th><?= current_locale() === 'am' ? 'የተግባሩ ርዕስ' : 'Title' ?></th>
                    <th><?= current_locale() === 'am' ? 'የስራ ክፍል' : 'Module' ?></th>
                    <th><?= current_locale() === 'am' ? 'የማጠናቀቂያ ቀን (ኢትዮጵያ)' : 'Due' ?></th>
                    <th><?= h(t('common.status', 'Status')) ?></th>
                    <th><?= h(t('common.actions', 'Action')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $t): 
                    $stText = $t['status'] === 'completed' ? (current_locale() === 'am' ? 'ተጠናቋል' : 'Completed') : (current_locale() === 'am' ? 'በሂደት ላይ' : 'Pending');
                ?>
                    <tr>
                        <td><strong><?= h($t['full_name']) ?></strong> (<code><?= h($t['employee_number']) ?></code>)</td>
                        <td><?= h($t['title']) ?></td>
                        <td><?= h($t['module'] ?: '—') ?></td>
                        <td style="white-space:nowrap"><?= $t['due_date'] ? '📅 ' . eth_date($t['due_date']) : '—' ?></td>
                        <td><span class="badge <?= $t['status']==='completed'?'active':'dry' ?>"><?= h($stText) ?></span></td>
                        <td>
                            <?php if ($t['status'] !== 'completed'): ?>
                                <form method="post"><?= csrf_field() ?>
                                    <input type="hidden" name="complete_task_id" value="<?= (int) $t['id'] ?>">
                                    <button class="btn secondary" type="submit" style="padding:4px 10px;white-space:nowrap">✓ <?= current_locale() === 'am' ? 'ተጠናቋል' : 'Mark Complete' ?></button>
                                </form>
                            <?php else: ?>✓ <?= current_locale() === 'am' ? 'ተጠናቋል' : 'Done' ?><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($tasks)): ?><tr><td colspan="6" class="text-muted"><?= h(t('common.no_records', 'No tasks yet.')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
