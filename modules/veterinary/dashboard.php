<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'veterinary_officer']);

// Handle quick check-in / administration from Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['administer_vaccine'])) {
    csrf_verify();
    try {
        $vacId = (int)$_POST['vaccine_id'];
        $batchNo = trim($_POST['batch_number'] ?? '');
        $dosage = trim($_POST['dosage'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $photoPath = save_uploaded_photo('vaccine_photo', 'vet');

        $stmt = db()->prepare(
            "UPDATE vet_vaccination_records 
             SET status = 'administered', administered_date = CURDATE(), 
                 batch_number = ?, dosage = ?, notes = ?, photo_path = COALESCE(?, photo_path), vet_id = ?
             WHERE id = ?"
        );
        $stmt->execute([$batchNo ?: null, $dosage ?: null, $notes ?: null, $photoPath, current_user()['id'], $vacId]);
        flash(current_locale() === 'am' ? '✓ ክትባቱ በተሳካ ሁኔታ ተሰጥቶ ተመዝግቧል!' : 'Vaccination successfully administered and recorded!');
        header('Location: /aleltu-eifms/modules/veterinary/dashboard.php');
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}

// Trigger intelligent reminder checks
$activeAlerts = vet_process_scheduled_reminders();
$dueVaccinations = vet_vaccinations_due(7);
$recentHealth = db()->query("SELECT * FROM vet_health_records ORDER BY id DESC LIMIT 10")->fetchAll();
$activeIncidents = db()->query("SELECT * FROM vet_disease_incidents WHERE quarantine_cleared_date IS NULL AND quarantine_flag = 1 ORDER BY id DESC")->fetchAll();
$milkAnomalies = smart_detect_milk_drop_anomalies();

require_once __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🩺 <?= current_locale() === 'am' ? 'የእንስሳት ሕክምና፣ ክትባትና ጤና ቁጥጥር' : 'Veterinary Care & Health Control' ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የላም፣ የዶሮ እና የዓሳ የተቀናጀ የጤና ምርመራ፣ የክትባት ሰዓት ቆጠራ ማንቂያ እና የበሽታ መቆጣጠሪያ' : 'Integrated health, smart vaccination countdown reminders, and disease monitoring' ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="/aleltu-eifms/modules/veterinary/health.php" class="btn">📋 <?= current_locale() === 'am' ? 'የጤና ምርመራ መዝግብ' : 'Record Health Visit' ?></a>
        <a href="/aleltu-eifms/modules/veterinary/vaccinations.php" class="btn secondary">💉 <?= current_locale() === 'am' ? 'የክትባት መርሃ-ግብር' : 'Vaccinations' ?></a>
        <a href="/aleltu-eifms/modules/veterinary/disease.php" class="btn secondary">⚠️ <?= current_locale() === 'am' ? 'የበሽታ ክስተቶች' : 'Disease Incidents' ?></a>
    </div>
</div>

<!-- 🚨 Urgent / Multi-Stage Vaccination Countdown Alert Banner -->
<?php if (!empty($activeAlerts)): ?>
<div class="card" style="margin-bottom:20px;border-left:5px solid #d32f2f;background:#fff8f8;padding:16px 20px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px">
        <div style="display:flex;align-items:center;gap:10px">
            <span style="font-size:24px">⏰</span>
            <div>
                <strong style="font-size:16px;color:#b3261e"><?= current_locale() === 'am' ? 'የክትባት አስታዋሽና የሰዓት ቆጠራ ማንቂያ (Vaccination Countdown Reminders)' : 'Urgent Vaccination Countdown Reminders' ?></strong>
                <div class="text-muted" style="font-size:12px"><?= current_locale() === 'am' ? 'ከ1 ቀን፣ 2 ሰዓት እና 1 ሰዓት በፊት በራስ-ሰር የሚሰጥ አስታዋሽ' : 'Multi-stage automated alerts (1 day, 2 hours, 1 hour)' ?></div>
            </div>
        </div>
        <span class="badge sold" style="font-size:12px;padding:5px 10px"><?= count($activeAlerts) ?> <?= current_locale() === 'am' ? 'ቀጠሮዎች' : 'Alerts' ?></span>
    </div>

    <div style="display:grid;gap:12px">
        <?php foreach ($activeAlerts as $alert): 
            $mins = $alert['diff_minutes'];
            $timeText = $mins > 60 ? floor($mins/60) . (current_locale() === 'am' ? ' ሰዓት ከ ' : 'h ') . ($mins%60) . (current_locale() === 'am' ? ' ደቂቃ' : 'm') : $mins . (current_locale() === 'am' ? ' ደቂቃ' : ' mins');
            $urgencyColor = $mins <= 60 ? '#b3261e' : ($mins <= 120 ? '#e65100' : '#0277bd');
        ?>
            <div style="background:#fff;border:1px solid #ffcdd2;border-radius:6px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
                <div>
                    <div style="font-weight:700;font-size:15px;color:#111">
                        💉 <?= h($alert['vaccine_name']) ?> &rarr; <span style="color:#006837"><?= h($alert['subject_label']) ?></span>
                    </div>
                    <div style="font-size:13px;color:#555;margin-top:3px">
                        <?= current_locale() === 'am' ? 'የተቀጠረበት ቀንና ሰዓት:' : 'Scheduled Date & Time:' ?> <strong><?= h($alert['scheduled_date']) ?> @ <?= h($alert['scheduled_time']) ?></strong>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <span style="font-size:13px;font-weight:700;color:<?= $urgencyColor ?>;background:#fce4ec;padding:4px 10px;border-radius:20px;border:1px solid #f8bbd0">
                        ⏳ <?= $mins > 0 ? (current_locale() === 'am' ? "{$timeText} ቀርቷል" : "in {$timeText}") : (current_locale() === 'am' ? 'አሁን የሚሰጥ' : 'Due Now') ?>
                    </span>
                    <button class="btn" onclick="openVaccineModal(<?= (int)$alert['id'] ?>, '<?= h(addslashes($alert['vaccine_name'])) ?>', '<?= h(addslashes($alert['subject_label'])) ?>')" style="padding:6px 14px;font-size:12px;background:#2e7d32;border-color:#2e7d32">
                        ✓ <?= current_locale() === 'am' ? 'ክትባት ጀምር / አረጋግጥ' : 'Start / Check-in' ?>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- 🧠 Smart Milk Drop Anomaly Alert -->
<?php if (!empty($milkAnomalies)): ?>
<div class="card" style="margin-bottom:20px;border-left:5px solid #e65100;background:#fffbf2;padding:14px 18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px">
        <div>
            <strong style="color:#e65100;font-size:15px">🧠 <?= current_locale() === 'am' ? 'ብልህ የእንስሳት ጤና ትንበያ፡ የወተት ምርት ቅነሳ ማንቂያ (Milk Drop Anomaly Alert)' : 'Smart Health Anomaly: Milk Yield Drop' ?></strong>
            <div class="text-muted" style="font-size:12px"><?= current_locale() === 'am' ? 'የወተት መጠናቸው ከባለፉት 7 ቀናት አማካይ ከ 20% በላይ የቀነሰባቸው ላሞች (ምርመራ ያስፈልጋቸዋል)' : 'Cows with sudden >20% yield drop compared to 7-day average' ?></div>
        </div>
        <span class="badge sold"><?= count($milkAnomalies) ?> <?= current_locale() === 'am' ? 'ላሞች' : 'cows' ?></span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:10px">
        <?php foreach ($milkAnomalies as $ma): ?>
            <div style="background:#fff;border:1px solid #ffe082;border-radius:4px;padding:10px 12px">
                <div style="font-weight:700">🐄 <?= h($ma['tag_number']) ?> <?= $ma['name'] ? '('.h($ma['name']).')' : '' ?></div>
                <div style="font-size:12px;color:#444;margin-top:2px">
                    <?= current_locale() === 'am' ? 'የ 7 ቀን አማካይ:' : '7-Day Avg:' ?> <strong><?= $ma['avg_7day'] ?> L</strong> | <?= current_locale() === 'am' ? 'የዛሬ:' : 'Latest:' ?> <strong><?= $ma['latest_yield'] ?> L</strong>
                </div>
                <div style="margin-top:6px;display:flex;justify-content:space-between;align-items:center">
                    <span class="badge sold" style="font-size:11px">-<?= $ma['drop_percent'] ?>% <?= current_locale() === 'am' ? 'ቅነሳ' : 'drop' ?></span>
                    <a href="/aleltu-eifms/modules/veterinary/health.php" class="btn secondary" style="padding:2px 8px;font-size:11px">🩺 <?= current_locale() === 'am' ? 'ምርመራ ጀምር' : 'Examine' ?></a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- KPI Row -->
<div class="kpi-row" style="margin-bottom:20px">
    <div class="kpi">
        <div class="label"><?= current_locale() === 'am' ? 'በ 7 ቀናት ውስጥ የሚሰጡ ክትባቶች' : 'Vaccinations Due (7 days)' ?></div>
        <div class="value"><?= count($dueVaccinations) ?></div>
    </div>
    <div class="kpi">
        <div class="label"><?= current_locale() === 'am' ? 'በለይቶ ማቆያ ውስጥ ያሉ (Quarantined)' : 'Active Quarantines' ?></div>
        <div class="value" style="color:<?= count($activeIncidents) > 0 ? '#b3261e' : 'inherit' ?>"><?= count($activeIncidents) ?></div>
    </div>
    <div class="kpi">
        <div class="label"><?= current_locale() === 'am' ? 'ጠቅላላ የተደረጉ የጤና ምርመራዎች' : 'Total Health Visits' ?></div>
        <div class="value" style="color:#2e7d32"><?= count($recentHealth) ?></div>
    </div>
</div>

<!-- Vaccinations Due Soon Table -->
<?php if (!empty($dueVaccinations)): ?>
<div class="card" style="margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <h3 style="margin:0">💉 <?= current_locale() === 'am' ? 'በቅርቡ የሚሰጡ ክትባቶች' : 'Vaccinations Due Soon' ?></h3>
        <a href="/aleltu-eifms/modules/veterinary/vaccinations.php" class="btn secondary" style="padding:4px 10px;font-size:12px"><?= current_locale() === 'am' ? 'ሁሉንም እይ' : 'View All' ?></a>
    </div>
    <table>
        <tr>
            <th><?= current_locale() === 'am' ? 'ተጠቃሚ / እንስሳ' : 'Subject' ?></th>
            <th><?= current_locale() === 'am' ? 'የክትባት ዓይነት' : 'Vaccine' ?></th>
            <th><?= current_locale() === 'am' ? 'የተመደበበት ቀንና ሰዓት' : 'Scheduled Date & Time' ?></th>
            <th><?= current_locale() === 'am' ? 'እርምጃ' : 'Action' ?></th>
        </tr>
        <?php foreach ($dueVaccinations as $v): ?>
            <tr>
                <td><strong><?= h(vet_subject_label($v['subject_type'], $v['subject_id'])) ?></strong></td>
                <td><?= h($v['vaccine_name']) ?></td>
                <td><?= h($v['scheduled_date']) ?> @ <?= h($v['scheduled_time'] ?? '09:00') ?></td>
                <td>
                    <button class="btn secondary" onclick="openVaccineModal(<?= (int)$v['id'] ?>, '<?= h(addslashes($v['vaccine_name'])) ?>', '<?= h(addslashes(vet_subject_label($v['subject_type'], $v['subject_id']))) ?>')" style="padding:3px 10px;font-size:11px">
                        ✓ <?= current_locale() === 'am' ? 'ክትባት ስጥ' : 'Administer' ?>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<!-- Recent Health Records with Photo Previews -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <h3 style="margin:0">📋 <?= current_locale() === 'am' ? 'የቅርብ ጊዜ የሕክምና መዝገቦች' : 'Recent Health Records' ?></h3>
        <a href="/aleltu-eifms/modules/veterinary/health.php" class="btn secondary" style="padding:4px 10px;font-size:12px"><?= current_locale() === 'am' ? 'አዲስ መዝግብ' : 'Record New' ?></a>
    </div>
    <table>
        <tr>
            <th><?= current_locale() === 'am' ? 'ፎቶ' : 'Photo' ?></th>
            <th><?= current_locale() === 'am' ? 'ተጠቃሚ / እንስሳ' : 'Subject' ?></th>
            <th><?= h(t('common.date', 'Date')) ?></th>
            <th><?= current_locale() === 'am' ? 'የታየ ምልክት / ሁኔታ' : 'Condition' ?></th>
            <th><?= current_locale() === 'am' ? 'የተሰጠ ሕክምና' : 'Treatment' ?></th>
        </tr>
        <?php foreach ($recentHealth as $r): ?>
            <tr>
                <td style="width:60px;text-align:center">
                    <?php if (!empty($r['photo_path'])): ?>
                        <a href="<?= h($r['photo_path']) ?>" target="_blank">
                            <img src="<?= h($r['photo_path']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px;border:1px solid #ccc" alt="Health Photo">
                        </a>
                    <?php else: ?>
                        <span class="text-muted" style="font-size:18px">🩺</span>
                    <?php endif; ?>
                </td>
                <td><strong><?= h(vet_subject_label($r['subject_type'], $r['subject_id'])) ?></strong></td>
                <td><?= h($r['record_date']) ?></td>
                <td><?= h($r['condition_name'] ?: '—') ?></td>
                <td><?= h($r['treatment'] ?: '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($recentHealth)): ?><tr><td colspan="5" class="text-muted"><?= h(t('common.no_records', 'No health records yet.')) ?></td></tr><?php endif; ?>
    </table>
</div>

<!-- Vaccine Check-in Modal -->
<div id="vaccineModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center">
    <div class="card" style="max-width:500px;width:90%;margin:auto;background:#fff;padding:24px;border-radius:8px">
        <h3 style="margin-top:0" id="modalTitle">💉 <?= current_locale() === 'am' ? 'ክትባት ስጥና አረጋግጥ' : 'Administer Vaccination' ?></h3>
        <p class="text-muted" id="modalSubtitle" style="font-size:13px"></p>

        <form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
            <input type="hidden" name="administer_vaccine" value="1">
            <input type="hidden" name="vaccine_id" id="modalVaccineId">

            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የክትባት ባች ቁጥር (Batch No)' : 'Vaccine Batch No' ?></label>
                <input type="text" name="batch_number" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ VAC-2026-B09' : 'e.g. VAC-2026-B09' ?>">
            </div>

            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'የተሰጠው መጠን / ዶዝ (Dosage)' : 'Dosage Administered' ?></label>
                <input type="text" name="dosage" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ 2ml ከቆዳ ስር' : 'e.g. 2ml subcutaneous' ?>">
            </div>

            <div class="form-group">
                <label>📷 <?= current_locale() === 'am' ? 'የክትባት ማረጋገጫ ፎቶ (Upload Photo)' : 'Proof Photo' ?></label>
                <input type="file" name="vaccine_photo" accept="image/*">
            </div>

            <div class="form-group">
                <label><?= current_locale() === 'am' ? 'ተጨማሪ ማስታወሻ (Notes)' : 'Notes' ?></label>
                <textarea name="notes" rows="2" placeholder="<?= current_locale() === 'am' ? 'የእንስሳው ምላሽ ወይም ተጨማሪ መረጃ...' : 'Observations...' ?>"></textarea>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
                <button type="button" class="btn secondary" onclick="closeVaccineModal()"><?= current_locale() === 'am' ? 'ሰርዝ' : 'Cancel' ?></button>
                <button type="submit" class="btn" style="background:#2e7d32;border-color:#2e7d32">✓ <?= current_locale() === 'am' ? 'ተከቷል ብለህ መዝግብ' : 'Confirm Administered' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function openVaccineModal(id, vaccine, subject) {
    document.getElementById('modalVaccineId').value = id;
    document.getElementById('modalTitle').innerText = '💉 ' + vaccine;
    document.getElementById('modalSubtitle').innerText = '<?= current_locale() === 'am' ? 'ለ:' : 'Subject:' ?> ' + subject;
    const modal = document.getElementById('vaccineModal');
    modal.style.display = 'flex';
}
function closeVaccineModal() {
    document.getElementById('vaccineModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
