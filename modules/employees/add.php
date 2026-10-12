<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'hr_officer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        employee_register($_POST);
        flash('Employee registered.');
        header('Location: /aleltu-eifms/modules/employees/list.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';

$departments = db()->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
?>
<?php
$autoEmpNum = employee_generate_number();
?>
<h2 class="mt-0">Register Employee</h2>
<div class="card" style="max-width:600px">
    <form method="post"><?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group"><label>Employee Number (Auto-Generated)</label><input type="text" name="employee_number" value="<?= h($autoEmpNum) ?>" readonly style="background:var(--bg)"></div>
            <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" required placeholder="e.g. Abebe Bikila"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Department</label>
                <select name="department_id">
                    <option value="">— none —</option>
                    <?php foreach ($departments as $d): ?><option value="<?= (int) $d['id'] ?>"><?= h($d['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Job Title</label><input type="text" name="job_title"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Phone</label><input type="text" name="phone"></div>
            <div class="form-group"><label>Hire Date</label><input type="date" name="hire_date"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Base Salary (ETB)</label><input type="number" step="0.01" name="base_salary"></div>
            <div class="form-group"><label style="margin-top:22px"><input type="checkbox" name="is_driver" value="1" style="width:auto"> Is a Driver</label></div>
        </div>
        <button class="btn" type="submit">Register</button>
        <a href="/aleltu-eifms/modules/employees/list.php" class="btn secondary">Cancel</a>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
