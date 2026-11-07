<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'veterinary_officer', 'dairy_manager', 'poultry_manager', 'fish_manager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $photoPath = save_uploaded_photo('clinical_photo', 'vet');
        $medItemId = !empty($_POST['medication_stock_item_id']) ? (int)$_POST['medication_stock_item_id'] : null;
        $dosageQty = !empty($_POST['medication_quantity']) ? (float)$_POST['medication_quantity'] : 0.0;
        $notes = trim($_POST['notes'] ?? '');

        vet_treatment_administer(
            $_POST['subject_type'],
            (int) $_POST['subject_id'],
            $_POST['record_date'],
            $_POST['condition_name'] ?? '',
            $_POST['treatment'] ?? '',
            $medItemId,
            $dosageQty,
            $photoPath,
            $notes ?: null
        );

        flash(current_locale() === 'am' ? '✓ የጤና እና የህክምና መዝገብ በተሳካ ሁኔታ ተመዝግቧል። የመድኃኒት ክምችት ከመጋዘን ተቀንሷል።' : 'Health record saved successfully. Medication stock deducted.');
        header('Location: ' . BASE_URL . '/modules/veterinary/health.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';

$subjectType = $_GET['subject_type'] ?? 'dairy_cow';
$subjects = vet_subject_options($subjectType);

// Fetch veterinary medications from stock
$medications = db()->query("
    SELECT id, sku, name, unit 
    FROM wh_stock_items 
    WHERE sku LIKE 'MED%' OR name LIKE '%መድኃኒት%' OR name LIKE '%ክትባት%' OR partition_code = 'materials_supplies'
    ORDER BY name ASC
")->fetchAll();

$records = db()->query("
    SELECT r.*, vt.medication_stock_item_id, vt.medication_quantity, vt.treatment_cost,
           si.name AS medication_name, si.unit AS medication_unit
    FROM vet_health_records r
    LEFT JOIN vet_treatments vt ON vt.subject_type = r.subject_type 
                                AND vt.subject_id = r.subject_id 
                                AND vt.record_date = r.record_date
    LEFT JOIN wh_stock_items si ON si.id = vt.medication_stock_item_id
    ORDER BY r.id DESC 
    LIMIT 40
")->fetchAll();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🩺 <?= current_locale() === 'am' ? 'የእንስሳት ጤና እና ክሊኒካል ምርመራ መዝገብ' : 'Health & Clinical Records' ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የሕመም ሁኔታ፣ የመድኃኒት አቅርቦት ከጎተራ መቀነስ፣ የህክምና ወጪ እና የክሊኒካል ፎቶ ማያያዣ' : 'Record diagnosis, administer medication from warehouse stock, track costs, and attach clinical observation photos.' ?></p>
    </div>
    <a href="<?= BASE_URL ?>/modules/veterinary/dashboard.php" class="btn secondary">&larr; <?= current_locale() === 'am' ? 'ወደ ጤና ዳሽቦርድ ተመለስ' : 'Back to Dashboard' ?></a>
</div>

<div class="card" style="max-width:680px;margin-bottom:24px">
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
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የተሰጠ ህክምናና መመሪያ (Treatment & Prescription)' : 'Treatment Prescribed' ?></label><textarea name="treatment" rows="2" placeholder="<?= current_locale() === 'am' ? 'የተሰጠ መድሃኒት፣ የአሰጣጥ ዘዴ እና የድህረ ህክምና ክትትል...' : 'Medication, administration route, and follow-up instructions...' ?>"></textarea></div>

        <!-- MEDICATION FROM WAREHOUSE -->
        <div style="background:var(--hover);padding:12px;border-radius:6px;margin-bottom:14px;border-left:3px solid var(--primary)">
            <h4 style="margin:0 0 10px;font-size:14px">💊 <?= current_locale() === 'am' ? 'የመድኃኒት አቅርቦት ከማዕከላዊ መጋዘን (አማራጭ)' : 'Medication from Warehouse (Optional)' ?></h4>
            <div class="form-row" style="display:grid;grid-template-columns:2fr 1fr;gap:10px">
                <div class="form-group" style="margin:0">
                    <label style="font-size:12px"><?= current_locale() === 'am' ? 'የመድኃኒት ዕቃ' : 'Medication Item' ?></label>
                    <select name="medication_stock_item_id">
                        <option value=""><?= current_locale() === 'am' ? '-- መድኃኒት አልተሰጠም / በውጭ የተገዛ --' : '-- None / External --' ?></option>
                        <?php foreach ($medications as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= h($m['name']) ?> (<?= h($m['sku']) ?> - <?= h($m['unit']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label style="font-size:12px"><?= current_locale() === 'am' ? 'የተሰጠ መጠን (ብዛት)' : 'Dosage Quantity' ?></label>
                    <input type="number" step="0.01" min="0" name="medication_quantity" placeholder="1.00">
                </div>
            </div>
            <div style="font-size:11px;color:#666;margin-top:6px">
                <?= current_locale() === 'am' ? 'የተመረጠውን መድኃኒት ከመጋዘን FIFO ባች ላይ በራስ-ሰር ይቀንሳል እንዲሁም የህክምና ወጪውን ያሰላል።' : 'Automatically issues from warehouse FIFO stock and computes treatment expense.' ?>
            </div>
        </div>

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
                    <th><?= current_locale() === 'am' ? 'የተሰጠ መድኃኒት' : 'Medication' ?></th>
                    <th><?= current_locale() === 'am' ? 'የህክምና ወጪ' : 'Cost' ?></th>
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
                        <td>
                            <?php if (!empty($r['medication_name'])): ?>
                                <span class="badge" style="background:#e8f5e9;color:#2e7d32">
                                    <?= h($r['medication_name']) ?> (<?= number_format((float)$r['medication_quantity'], 1) ?> <?= h($r['medication_unit'] ?? '') ?>)
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:700;color:var(--primary)">
                            <?= number_format((float)($r['treatment_cost'] ?? $r['cost'] ?? 0), 2) ?> ETB
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($records)): ?><tr><td colspan="7" class="text-muted"><?= h(t('common.no_records', 'No records yet.')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>