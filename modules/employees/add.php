<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'hr_officer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $newId = employee_register($_POST);
        flash('አዲስ ሰራተኛ በተሳካ ሁኔታ ተመዝግቧል (Employee registered successfully).');
        header('Location: ' . BASE_URL . '/modules/employees/view.php?id=' . $newId);
        exit;
    } catch (Throwable $e) {
        flash('ስህተት፦ ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';

$departments = db()->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
$autoEmpNum = employee_generate_number();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">👤 <?= current_locale() === 'am' ? 'አዲስ ሰራተኛ መመዝገቢያ' : 'Register New Employee' ?></h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale() === 'am' ? 'የሰራተኛውን ሙሉ ዝርዝር፣ የስራ አይነት እና የክፍያ ሁኔታ ያስገቡ።' : 'Enter employee profile details, employment type, and salary.' ?>
        </p>
    </div>
    <a href="<?= BASE_URL ?>/modules/employees/list.php" class="btn secondary">← <?= current_locale() === 'am' ? 'ወደ ሰራተኞች ዝርዝር' : 'Back to Employees' ?></a>
</div>

<div class="card" style="max-width:700px;margin-bottom:24px">
    <form method="post"><?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የሰራተኛ መለያ ቁጥር (ራስ-ሰር)' : 'Employee Number (Auto)' ?></label>
                <input type="text" name="employee_number" value="<?= h($autoEmpNum) ?>" readonly style="background:var(--hover);font-weight:700">
            </div>
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የቅጥር አይነት *' : 'Employment Type *' ?></label>
                <select name="employment_type" required>
                    <option value="permanent"><?= current_locale() === 'am' ? 'ቋሚ ሰራተኛ (ጡረታ ተጠቃሚ 7%+11%)' : 'Permanent' ?></option>
                    <option value="contract"><?= current_locale() === 'am' ? 'ኮንትራት ሰራተኛ' : 'Contract' ?></option>
                    <option value="daily"><?= current_locale() === 'am' ? 'የቀን ሰራተኛ' : 'Daily' ?></option>
                    <option value="temporary"><?= current_locale() === 'am' ? 'ጊዜያዊ ሰራተኛ' : 'Temporary' ?></option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'ሙሉ ስም (በእንግሊዝኛ) *' : 'Full Name (English) *' ?></label>
                <input type="text" name="full_name" required placeholder="e.g. Abebe Bikila">
            </div>
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'ሙሉ ስም (በአማርኛ)' : 'Full Name (Amharic)' ?></label>
                <input type="text" name="amharic_name" placeholder="ለምሳሌ፡ አበበ ቢቂላ">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የስራ ክፍል (ዲፓርትመንት)' : 'Department' ?></label>
                <select name="department_id">
                    <option value="">— <?= current_locale() === 'am' ? 'ያልተመደበ' : 'None' ?> —</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int) $d['id'] ?>"><?= h($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የስራ መደብ / ኃላፊነት' : 'Job Title' ?></label>
                <input type="text" name="job_title" placeholder="e.g. Dairy Attendant">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'ስልክ ቁጥር' : 'Phone' ?></label>
                <input type="tel" name="phone" placeholder="09xxxxxxxx">
            </div>
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የተቀጠሩበት ቀን' : 'Hire Date' ?></label>
                <input type="date" name="hire_date" value="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'መሰረታዊ ወርሃዊ ደመወዝ (ETB)' : 'Base Salary (ETB)' ?></label>
                <input type="number" step="0.01" name="base_salary" placeholder="0.00">
            </div>
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'ብሔራዊ መታወቂያ / ፋይዳ (National ID)' : 'National ID' ?></label>
                <input type="text" name="national_id" placeholder="FAN-xxxx">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የአደጋ ጊዜ ተጠሪ ሙሉ ስም' : 'Emergency Contact Name' ?></label>
                <input type="text" name="emergency_contact" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ አቶ አበበ ደሳለኝ' : 'e.g. Abebe Desalegn' ?>">
            </div>
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የአደጋ ጊዜ ተጠሪ ስልክ ቁጥር' : 'Emergency Contact Phone' ?></label>
                <input type="tel" name="emergency_contact_phone" placeholder="09xxxxxxxx">
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:16px">
            <button class="btn" type="submit">💾 <?= current_locale() === 'am' ? 'ሰራተኛውን መዝግብ' : 'Register' ?></button>
            <a href="<?= BASE_URL ?>/modules/employees/list.php" class="btn secondary"><?= current_locale() === 'am' ? 'ሰርዝ' : 'Cancel' ?></a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

