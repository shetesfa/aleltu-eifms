<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'veterinary_officer', 'dairy_manager', 'poultry_manager', 'fish_manager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['clear_id'])) {
            vet_clear_quarantine((int) $_POST['clear_id']);
            flash(current_locale() === 'am' ? '✓ የማቆያ ክልከላ ተነስቷል።' : 'Quarantine cleared.');
        } else {
            $photoPath = save_uploaded_photo('disease_photo', 'vet');
            vet_record_disease_incident(
                $_POST['subject_type'], (int) $_POST['subject_id'], $_POST['disease_name'],
                $_POST['detected_date'], isset($_POST['quarantine_flag']), $_POST['notes'] ?? '', $photoPath
            );
            flash(current_locale() === 'am' ? '✓ የበሽታ ክስተት መዝገብ በተሳካ ሁኔታ ተመዝግቧል።' : 'Disease incident recorded.');
        }
        header('Location: /aleltu-eifms/modules/veterinary/disease.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';

$subjectType = $_GET['subject_type'] ?? 'poultry_flock';
$subjects = vet_subject_options($subjectType);
$incidents = db()->query("SELECT * FROM vet_disease_incidents ORDER BY id DESC LIMIT 40")->fetchAll();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🦠 <?= current_locale() === 'am' ? 'የእንስሳት በሽታ ክስተቶች እና ማቆያ (Quarantine)' : 'Disease Incidents & Quarantine' ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የተከሰቱ የበሽታ ምልክቶች መዝገብ፣ የማቆያ ክልከላ እና የክሊኒካል ፎቶ ማያያዣ' : "Quarantining a poultry flock's incident also quarantines its house, blocking bird transfers out." ?></p>
    </div>
    <a href="/aleltu-eifms/modules/veterinary/dashboard.php" class="btn secondary">&larr; <?= current_locale() === 'am' ? 'ወደ ጤና ዳሽቦርድ ተመለስ' : 'Back to Dashboard' ?></a>
</div>

<div class="card" style="max-width:620px;margin-bottom:24px">
    <h3 style="margin-top:0">⚠️ <?= current_locale() === 'am' ? 'አዲስ የበሽታ ክስተት መመዝገቢያ' : 'Record Disease Incident' ?></h3>
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
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የተጠቃው እንስሳ / መንጋ *' : 'Subject *' ?></label>
            <select name="subject_id" required><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"><?= h($s['label']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የበሽታው ስም *' : 'Disease *' ?></label><input type="text" name="disease_name" required placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ ኒውካስል፣ ጎንደር፣ ተቅማጥ' : 'e.g. Newcastle disease, Bloat' ?>"></div>
        <div class="form-group"><label><?= current_locale() === 'am' ? 'የታወቀበት ቀን *' : 'Detected Date *' ?></label><input type="date" name="detected_date" value="<?= date('Y-m-d') ?>" required></div>
        
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="quarantine_flag" value="1" style="width:auto"> 
                <strong><?= current_locale() === 'am' ? '🔒 የማቆያ ክልከላ ይደረግበት (Quarantine)' : 'Quarantine (blocks transfers)' ?></strong>
            </label>
        </div>

        <div class="form-group">
            <label>📷 <?= current_locale() === 'am' ? 'የበሽታ ምልክት ፎቶ (Disease Photo Evidence)' : 'Photo Evidence' ?></label>
            <input type="file" name="disease_photo" accept="image/*">
        </div>

        <div class="form-group"><label><?= h(t('common.notes', 'Notes')) ?></label><textarea name="notes" rows="2" placeholder="<?= current_locale() === 'am' ? 'የበሽታው ምልክቶችና የተወሰደ እርምጃ...' : 'Observations and measures taken...' ?>"></textarea></div>
        <button class="btn" type="submit" <?= empty($subjects)?'disabled':'' ?>>✓ <?= current_locale() === 'am' ? 'የበሽታውን ክስተት መዝግብ' : 'Record Incident' ?></button>
    </form>
</div>

<div class="card">
    <h3 style="margin-top:0">📜 <?= current_locale() === 'am' ? 'ሁሉም የበሽታ ክስተቶች መዝገብ' : 'All Incidents' ?></h3>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th><?= current_locale() === 'am' ? 'ፎቶ' : 'Photo' ?></th>
                    <th><?= current_locale() === 'am' ? 'እንስሳ / መንጋ' : 'Subject' ?></th>
                    <th><?= current_locale() === 'am' ? 'የበሽታው ስም' : 'Disease' ?></th>
                    <th><?= current_locale() === 'am' ? 'የታወቀበት ቀን (ኢትዮጵያ)' : 'Detected' ?></th>
                    <th><?= current_locale() === 'am' ? 'የማቆያ ሁኔታ' : 'Quarantine' ?></th>
                    <th><?= h(t('common.actions', 'Action')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incidents as $i): ?>
                    <tr>
                        <td style="width:60px;text-align:center">
                            <?php if (!empty($i['photo_path'])): ?>
                                <a href="<?= h($i['photo_path']) ?>" target="_blank">
                                    <img src="<?= h($i['photo_path']) ?>" style="width:45px;height:45px;object-fit:cover;border-radius:4px;border:1px solid #ccc" alt="Disease Photo">
                                </a>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:16px">🦠</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= h(vet_subject_label($i['subject_type'], $i['subject_id'])) ?></strong></td>
                        <td><strong style="color:#b3261e"><?= h($i['disease_name']) ?></strong></td>
                        <td style="white-space:nowrap">📅 <?= eth_date($i['detected_date']) ?></td>
                        <td>
                            <?php if ($i['quarantine_flag']): ?>
                                <?= $i['quarantine_cleared_date'] ? '<span class="badge active">' . (current_locale() === 'am' ? '✓ ተነስቷል (' . eth_date($i['quarantine_cleared_date']) . ')' : 'Cleared ' . h($i['quarantine_cleared_date'])) . '</span>' : '<span class="badge sold">' . (current_locale() === 'am' ? '🔒 በማቆያ ላይ' : 'Active Quarantine') . '</span>' ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <?php if ($i['quarantine_flag'] && !$i['quarantine_cleared_date']): ?>
                                <form method="post"><?= csrf_field() ?><input type="hidden" name="clear_id" value="<?= (int) $i['id'] ?>"><button class="btn secondary" type="submit" style="padding:4px 10px;white-space:nowrap">✓ <?= current_locale() === 'am' ? 'ማቆያውን አንሳ' : 'Clear Quarantine' ?></button></form>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($incidents)): ?><tr><td colspan="6" class="text-muted"><?= h(t('common.no_records', 'No incidents recorded yet.')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
