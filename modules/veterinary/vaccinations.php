<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'veterinary_officer', 'dairy_manager', 'poultry_manager', 'fish_manager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['administer_id'])) {
            $vacId = (int) $_POST['administer_id'];
            $batchNo = trim($_POST['batch_number'] ?? '');
            $dosage = trim($_POST['dosage'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $photoPath = save_uploaded_photo('administer_photo', 'vet');

            $stmt = db()->prepare(
                "UPDATE vet_vaccination_records 
                 SET status = 'administered', administered_date = CURDATE(), 
                     batch_number = COALESCE(NULLIF(?, ''), batch_number), 
                     dosage = COALESCE(NULLIF(?, ''), dosage), 
                     notes = COALESCE(NULLIF(?, ''), notes), 
                     photo_path = COALESCE(?, photo_path), 
                     vet_id = ? 
                 WHERE id = ?"
            );
            $stmt->execute([$batchNo, $dosage, $notes, $photoPath, current_user()['id'], $vacId]);
            flash(current_locale() === 'am' ? '✓ ክትባቱ በተሳካ ሁኔታ ተሰጥቶ ተመዝግቧል።' : 'Vaccination marked as administered.');
        } else {
            $sType = $_POST['subject_type'];
            $sId = (int) $_POST['subject_id'];
            $vName = trim($_POST['vaccine_name']);
            $sDate = $_POST['scheduled_date'];
            $sTime = $_POST['scheduled_time'] ?: '09:00:00';
            $batchNo = trim($_POST['batch_number'] ?? '');
            $dosage = trim($_POST['dosage'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $photoPath = save_uploaded_photo('vaccine_photo', 'vet');

            $stmt = db()->prepare(
                "INSERT INTO vet_vaccination_records 
                 (subject_type, subject_id, vaccine_name, scheduled_date, scheduled_time, batch_number, dosage, notes, photo_path, status, vet_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)"
            );
            $stmt->execute([$sType, $sId, $vName, $sDate, $sTime, $batchNo ?: null, $dosage ?: null, $notes ?: null, $photoPath, current_user()['id']]);
            flash(current_locale() === 'am' ? '✓ የክትባት ቀጠሮ በተሳካ ሁኔታ ተመዝግቧል (የ1 ቀን፣ 2 ሰዓት እና 1 ሰዓት አስታዋሽ ተዘጋጅቷል)።' : 'Vaccination scheduled with 1-day, 2-hr, 1-hr countdown reminders.');
        }
        header('Location: ' . BASE_URL . '/modules/veterinary/vaccinations.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';

$subjectType = $_GET['subject_type'] ?? 'dairy_cow';
$subjects = vet_subject_options($subjectType);
$records = db()->query("SELECT * FROM vet_vaccination_records ORDER BY scheduled_date DESC, scheduled_time ASC LIMIT 40")->fetchAll();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">💉 <?= current_locale() === 'am' ? 'የእንስሳት ክትባት መርሃ-ግብርና መዝገብ' : 'Vaccination Schedule & Records' ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የቀጠሮ ሰዓት ማስገቢያ፣ የክትባት ፎቶ ማያያዣ እና በሰዓት የተደገፈ አስታዋሽ ስርዓት' : 'Time-based scheduling with automated countdown alerts and photo attachments' ?></p>
    </div>
    <a href="<?= BASE_URL ?>/modules/veterinary/dashboard.php" class="btn secondary">&larr; <?= current_locale() === 'am' ? 'ወደ ጤና ዳሽቦርድ ተመለስ' : 'Back to Dashboard' ?></a>
</div>

<div class="card" style="max-width:640px;margin-bottom:24px">
    <h3 style="margin-top:0">💉 <?= current_locale() === 'am' ? 'አዲስ የክትባት ቀጠሮ መመዝገቢያ' : 'Schedule New Vaccination' ?></h3>
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
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የሚከተበው እንስሳ / መንጋ *' : 'Subject *' ?></label>
            <select name="subject_id" required><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"><?= h($s['label']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የክትባቱ ስም *' : 'Vaccine Name *' ?></label><input type="text" name="vaccine_name" required placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ የጉሮሮ በሽታ ክትባት (Anthrax)' : 'e.g. Anthrax Vaccine' ?>"></div>
        
        <div class="form-row">
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የሚሰጥበት ቀን *' : 'Scheduled Date *' ?></label>
                <input type="date" name="scheduled_date" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የሚሰጥበት ሰዓት *' : 'Scheduled Time *' ?></label>
                <input type="time" name="scheduled_time" required value="09:00">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የክትባት ባች ቁጥር (Batch No)' : 'Batch Number' ?></label>
                <input type="text" name="batch_number" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ VAC-2026-01' : 'e.g. VAC-2026-01' ?>">
            </div>
            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የሚሰጠው መጠን / ዶዝ (Dosage)' : 'Dosage' ?></label>
                <input type="text" name="dosage" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ 2ml ከቆዳ ስር' : 'e.g. 2ml Subcutaneous' ?>">
            </div>
        </div>

        <div class="form-group">
            <label>📷 <?= current_locale() === 'am' ? 'የክትባት ማረጋገጫ / መለያ ፎቶ (Photo Upload)' : 'Vaccine/Animal Photo' ?></label>
            <input type="file" name="vaccine_photo" accept="image/*">
        </div>

        <div class="form-group">
            <label><?= current_locale() === 'am' ? 'ተጨማሪ ማስታወሻ (Notes)' : 'Notes' ?></label>
            <textarea name="notes" rows="2" placeholder="<?= current_locale() === 'am' ? 'የክትባት ቅድመ ሁኔታዎች ወይም መመሪያ...' : 'Notes or instructions...' ?>"></textarea>
        </div>

        <button class="btn" type="submit" <?= empty($subjects)?'disabled':'' ?>>✓ <?= current_locale() === 'am' ? 'ክትባቱን በሰዓት መድብ' : 'Schedule Vaccination' ?></button>
    </form>
</div>

<div class="card">
    <h3 style="margin-top:0">📜 <?= current_locale() === 'am' ? 'ሁሉም የክትባት መዝገቦች' : 'All Vaccination Records' ?></h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= current_locale() === 'am' ? 'ፎቶ' : 'Photo' ?></th>
                    <th><?= current_locale() === 'am' ? 'እንስሳ / መንጋ' : 'Subject' ?></th>
                    <th><?= current_locale() === 'am' ? 'የክትባቱ ስም' : 'Vaccine' ?></th>
                    <th><?= current_locale() === 'am' ? 'የተመደበበት ቀንና ሰዓት (ኢትዮጵያ)' : 'Scheduled' ?></th>
                    <th><?= current_locale() === 'am' ? 'ባች / ዶዝ' : 'Batch / Dose' ?></th>
                    <th><?= h(t('common.status', 'Status')) ?></th>
                    <th><?= h(t('common.actions', 'Action')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): 
                    $stText = match($r['status']) {
                        'administered' => (current_locale() === 'am' ? '✓ ተሰጥቷል' : 'Administered'),
                        'missed' => (current_locale() === 'am' ? '⚠️ አልፏል' : 'Missed'),
                        default => (current_locale() === 'am' ? '⏳ የተያዘለት' : 'Scheduled')
                    };
                ?>
                    <tr>
                        <td style="width:60px;text-align:center">
                            <?php if (!empty($r['photo_path'])): ?>
                                <a href="<?= h($r['photo_path']) ?>" target="_blank">
                                    <img src="<?= h($r['photo_path']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px;border:1px solid #ccc" alt="Vaccine Photo">
                                </a>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:16px">💉</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= h(vet_subject_label($r['subject_type'], $r['subject_id'])) ?></strong></td>
                        <td><strong><?= h($r['vaccine_name']) ?></strong></td>
                        <td style="white-space:nowrap">
                            📅 <?= eth_date($r['scheduled_date']) ?><br>
                            <small class="text-muted">⏰ <?= eth_time($r['scheduled_time'] ?? '09:00:00') ?></small>
                        </td>
                        <td style="font-size:12px">
                            <?= h($r['batch_number'] ?: '—') ?>
                            <?= $r['dosage'] ? '<br><small class="text-muted">' . h($r['dosage']) . '</small>' : '' ?>
                        </td>
                        <td><span class="badge <?= $r['status']==='administered'?'milking':($r['status']==='missed'?'sold':'dry') ?>"><?= h($stText) ?></span></td>
                        <td>
                            <?php if ($r['status'] === 'scheduled'): ?>
                                <form method="post" enctype="multipart/form-data" style="display:flex;gap:6px;align-items:center"><?= csrf_field() ?>
                                    <input type="hidden" name="administer_id" value="<?= (int) $r['id'] ?>">
                                    <button class="btn secondary" type="submit" style="padding:4px 10px;font-size:12px;background:#2e7d32;color:#fff;border-color:#2e7d32;white-space:nowrap">✓ <?= current_locale() === 'am' ? 'ተከቷል' : 'Administer' ?></button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:12px;white-space:nowrap"><?= $r['administered_date'] ? '📅 ' . eth_date($r['administered_date']) : '—' ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($records)): ?><tr><td colspan="7" class="text-muted"><?= h(t('common.no_records', 'No vaccinations scheduled yet.')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
