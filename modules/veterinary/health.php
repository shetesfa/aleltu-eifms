<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'veterinary_officer', 'dairy_manager', 'poultry_manager', 'fish_manager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $photoPath = save_uploaded_photo('clinical_photo', 'vet');
        vet_record_health($_POST['subject_type'], (int) $_POST['subject_id'], $_POST['record_date'], $_POST['condition_name'] ?? '', $_POST['treatment'] ?? '', $photoPath);
        flash(current_locale() === 'am' ? '✓ የጤና እና የህክምና መዝገብ በተሳካ ሁኔታ ተመዝግቧል።' : 'Health record saved successfully.');
        header('Location: /aleltu-eifms/modules/veterinary/health.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';

$subjectType = $_GET['subject_type'] ?? 'dairy_cow';
$subjects = vet_subject_options($subjectType);
$records = db()->query("SELECT * FROM vet_health_records ORDER BY id DESC LIMIT 40")->fetchAll();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🩺 <?= current_locale() === 'am' ? 'የእንስሳት ጤና እና ክሊኒካል ምርመራ መዝገብ' : 'Health & Clinical Records' ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የሕመም ሁኔታ፣ የተሰጠ መድኃኒትና መጠን እንዲሁም የክሊኒካል ፎቶ ማያያዣ' : 'Record diagnosis, treatment dosage, and attach clinical observation photos' ?></p>
    </div>
    <a href="/aleltu-eifms/modules/veterinary/dashboard.php" class="btn secondary">&larr; <?= current_locale() === 'am' ? 'ወደ ጤና ዳሽቦርድ ተመለስ' : 'Back to Dashboard' ?></a>
</div>

<div class="card" style="max-width:620px;margin-bottom:24px">
    <h3 style="margin-top:0">📋 <?= current_locale() === 'am' ? 'አዲስ የእንስሳት ምርመራና ህክምና መዝግብ' : 'Record a Health Visit' ?></h3>
    <form method="get" style="margin-bottom:14px">
        <div class="form-group" style="margin:0"><label><?= current_locale() === 'am' ? 'የእንስሳት ዓይነት' : 'Subject Type' ?></label>
            <select name="subject_type" onchange="this.form.submit()">
                <?php foreach (VET_SUBJECT_TYPES as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $subjectType===$key?'selected':'' ?>><?= current_locale() === 'am' ? ($key === 'dairy_cow' ? '🐄 የወተት ላም / ጥጃ' : ($key === 'poultry_flock' ? '🐔 የዶሮ መንጋ' : '🐟 የዓሳ ኩሬ')) : h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
        <input type="hidden" name="subject_type" value="<?= h($subjectType) ?>">
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የታከመው እንስሳ / መንጋ *' : 'Subject *' ?></label>
            <select name="subject_id" required>
                <?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"><?= h($s['label']) ?></option><?php endforeach; ?>
            </select>
            <?php if (empty($subjects)): ?><p class="text-muted"><?= current_locale() === 'am' ? 'በዚህ ምድብ ውስጥ ንቁ እንስሳት አልተገኙም።' : 'No active subjects of this type yet.' ?></p><?php endif; ?>
        </div>
        <div class="form-group"><label><?= h(t('common.date', 'Date')) ?> *</label><input type="date" name="record_date" value="<?= date('Y-m-d') ?>" required></div>
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የታየበት የበሽታ ሁኔታ / ምልክት (Condition)' : 'Condition Diagnosed' ?></label><input type="text" name="condition_name" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ የጡት ማበጥ (Mastitis)፣ ጉንፋን' : 'e.g. Mastitis, Bloat, Cough' ?>"></div>
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የተሰጠ ህክምናና መድኃኒት (Treatment & Dosage)' : 'Treatment Prescribed' ?></label><textarea name="treatment" rows="2" placeholder="<?= current_locale() === 'am' ? 'የተሰጠ መድሃኒት፣ መጠን እና የአሰጣጥ ዘዴ...' : 'Medication, dosage and instructions...' ?>"></textarea></div>
        <div class="form-group">
            <label>📷 <?= current_locale() === 'am' ? 'የሕመም / ምርመራ ፎቶ አያይዝ (Clinical Photo)' : 'Attach Clinical Photo' ?></label>
            <input type="file" name="clinical_photo" accept="image/*">
        </div>
        <button class="btn" type="submit" <?= empty($subjects)?'disabled':'' ?>>✓ <?= current_locale() === 'am' ? 'የህክምና መዝገብ አስቀምጥ' : 'Save Health Record' ?></button>
    </form>
</div>

<div class="card">
    <h3 style="margin-top:0">📜 <?= current_locale() === 'am' ? 'የቅርብ ጊዜ የህክምና መዝገቦች' : 'Recent Health Records' ?></h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= current_locale() === 'am' ? 'ፎቶ' : 'Photo' ?></th>
                    <th><?= current_locale() === 'am' ? 'እንስሳ / መንጋ' : 'Subject' ?></th>
                    <th><?= current_locale() === 'am' ? 'ቀን (ኢትዮጵያ)' : 'Date' ?></th>
                    <th><?= current_locale() === 'am' ? 'የበሽታ ሁኔታ' : 'Condition' ?></th>
                    <th><?= current_locale() === 'am' ? 'የተሰጠ ህክምና' : 'Treatment' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                    <tr>
                        <td style="width:60px;text-align:center">
                            <?php if (!empty($r['photo_path'])): ?>
                                <a href="<?= h($r['photo_path']) ?>" target="_blank">
                                    <img src="<?= h($r['photo_path']) ?>" style="width:45px;height:45px;object-fit:cover;border-radius:4px;border:1px solid #ccc" alt="Clinical Photo">
                                </a>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:16px">🩺</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= h(vet_subject_label($r['subject_type'], $r['subject_id'])) ?></strong></td>
                        <td style="white-space:nowrap">📅 <?= eth_date($r['record_date']) ?></td>
                        <td><strong style="color:#b3261e"><?= h($r['condition_name'] ?: '—') ?></strong></td>
                        <td><?= h($r['treatment'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($records)): ?><tr><td colspan="5" class="text-muted"><?= h(t('common.no_records', 'No records yet.')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
