<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'dairy_manager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $photo = save_uploaded_photo('cow_photo', 'cows');
        $data = $_POST;
        if ($photo) {
            $data['photo_path'] = $photo;
        }
        cow_register($data);
        flash(current_locale() === 'am' ? '✓ ላሟ በተሳካ ሁኔታ ተመዝግባለች።' : 'Cow registered successfully.');
        header('Location: /aleltu-eifms/modules/dairy/cows.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';

$groups = db()->query("SELECT id, name FROM dairy_cow_groups ORDER BY name")->fetchAll();
?>
<h2 class="mt-0"><?= h(t('dairy.register_new', 'Register New Cow')) ?></h2>

<div class="card" style="max-width:640px">
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group"><label><?= h(t('dairy.tag_num', 'Tag Number')) ?> *</label><input type="text" name="tag_number" required placeholder="e.g. COW-006"></div>
            <div class="form-group"><label><?= h(t('common.name', 'Name')) ?></label><input type="text" name="name" placeholder="e.g. Bella"></div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label><?= h(t('dairy.sex', 'Sex')) ?> *</label>
                <select name="sex" required>
                    <option value="female"><?= h(t('dairy.female', 'Female')) ?></option>
                    <option value="male"><?= h(t('dairy.male', 'Male')) ?></option>
                </select>
            </div>
            <div class="form-group"><label><?= h(t('dairy.breed', 'Breed')) ?></label><input type="text" name="breed" placeholder="e.g. Holstein-Friesian"></div>
        </div>
        <div class="form-group" style="background:var(--bg);padding:12px;border-radius:6px;border:1px solid var(--border)">
            <label style="font-weight:bold;font-size:14px">📍 <?= current_locale() === 'am' ? 'የላሟ አመጣጥ / ምንጭ (Source) *' : 'Cow Source *' ?></label>
            <select name="source" id="cowSourceSelect" onchange="toggleCowSource()" required style="width:100%;font-size:14px;padding:8px">
                <option value="born_on_farm">🏠 <?= current_locale() === 'am' ? 'በእርሻው የተወለደች (Born on Farm)' : 'Born on Farm' ?></option>
                <option value="purchased">🛒 <?= current_locale() === 'am' ? 'የተገዛች / ከውጭ የመጣች (Purchased / Acquired)' : 'Purchased / Acquired' ?></option>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group" id="dobGroup">
                <label id="dobLabel"><?= h(t('dairy.dob', 'Date of Birth')) ?> *</label>
                <input type="date" name="date_of_birth" id="dobInput" required max="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group" id="acquiredGroup" style="display:none">
                <label><?= current_locale() === 'am' ? 'የተገዛችበት ቀን (Purchase / Acquired Date) *' : 'Purchase / Acquired Date *' ?></label>
                <input type="date" name="acquired_date" id="acquiredInput" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label><?= h(t('dairy.group', 'Group / Pen Location')) ?></label>
                <select name="cow_group_id">
                    <option value="">— none —</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= (int) $g['id'] ?>"><?= h($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>📷 <?= current_locale() === 'am' ? 'የላሟ ፎቶ (Cow Profile Photo)' : 'Cow Profile Photo' ?></label>
            <input type="file" name="cow_photo" accept="image/*">
        </div>

        <div style="display:flex;gap:10px;align-items:center;margin-top:16px">
            <button class="btn" type="submit">✓ <?= h(t('dairy.register_new', 'Register Cow')) ?></button>
            <a href="/aleltu-eifms/modules/dairy/cows.php" class="btn secondary"><?= h(t('common.cancel', 'Cancel')) ?></a>
        </div>
    </form>
</div>

<script>
function toggleCowSource() {
    const src = document.getElementById('cowSourceSelect').value;
    const dobGroup = document.getElementById('dobGroup');
    const dobLabel = document.getElementById('dobLabel');
    const dobInput = document.getElementById('dobInput');
    const acqGroup = document.getElementById('acquiredGroup');
    const acqInput = document.getElementById('acquiredInput');

    if (src === 'purchased') {
        acqGroup.style.display = 'block';
        acqInput.required = true;
        dobLabel.innerHTML = "<?= current_locale() === 'am' ? 'የትውልድ ቀን (ግምታዊ ከሆነ ይጥቀሱ)' : 'Date of Birth (Optional/Estimated)' ?>";
        dobInput.required = false;
    } else {
        acqGroup.style.display = 'none';
        acqInput.required = false;
        dobLabel.innerHTML = "<?= h(t('dairy.dob', 'Date of Birth')) ?> *";
        dobInput.required = true;
    }
}
toggleCowSource();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
