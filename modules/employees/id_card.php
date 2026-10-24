<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'hr_officer', 'field_worker', 'dairy_manager', 'poultry_manager', 'fish_manager', 'warehouse_keeper', 'finance_officer']);

$empId = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee_card'])) {
    csrf_verify();
    require_role(['super_admin', 'general_manager', 'hr_officer']);
    $targetId = (int)$_POST['employee_id'];
    $fullName = trim($_POST['full_name'] ?? '');
    $amharicName = trim($_POST['amharic_name'] ?? '');
    $jobTitle = trim($_POST['job_title'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $emergencyContact = trim($_POST['emergency_contact'] ?? '');
    $emergencyPhone = trim($_POST['emergency_contact_phone'] ?? '');

    if ($targetId > 0 && !empty($fullName)) {
        $stmt = db()->prepare(
            "UPDATE employees 
             SET full_name = ?, amharic_name = ?, job_title = ?, phone = ?, emergency_contact = ?, emergency_contact_phone = ? 
             WHERE id = ?"
        );
        $stmt->execute([$fullName, $amharicName, $jobTitle, $phone, $emergencyContact, $emergencyPhone ?: null, $targetId]);
        log_activity('employees', 'update', 'employees', $targetId, "Updated ID card profile for {$fullName}");
        flash(current_locale() === 'am' ? '✓ የመታወቂያ ካርድ መረጃ በተሳካ ሁኔታ ተስተካክሏል!' : 'ID card details updated successfully!');
    }
    header('Location: ' . BASE_URL . '/modules/employees/id_card.php?id=' . $targetId);
    exit;
}

$isHrOrManager = has_role(['super_admin', 'general_manager', 'hr_officer']);
if (!$isHrOrManager && current_user()) {
    // Regular employees are strictly locked to their own employee record
    $stmtChk = db()->prepare("SELECT id FROM employees WHERE user_id = ?");
    $stmtChk->execute([(int)current_user()['id']]);
    $chk = $stmtChk->fetchColumn();
    if ($chk) {
        $empId = (int) $chk;
    }
} elseif ($empId <= 0 && current_user()) {
    // If not specified, look up current logged in employee
    $stmtChk = db()->prepare("SELECT id FROM employees WHERE user_id = ?");
    $stmtChk->execute([(int)current_user()['id']]);
    $chk = $stmtChk->fetchColumn();
    if ($chk) {
        $empId = (int) $chk;
    }
}

$allEmployees = db()->query("SELECT id, full_name, employee_number, job_title FROM employees WHERE status != 'terminated' ORDER BY full_name")->fetchAll();

if ($empId <= 0 && !empty($allEmployees)) {
    $empId = (int)$allEmployees[0]['id'];
}

$stmtEmp = db()->prepare(
    "SELECT e.*, d.name AS dept_name
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.id = ?"
);
$stmtEmp->execute([$empId]);
$emp = $stmtEmp->fetch();

if (!$emp) {
    die("Employee record not found.");
}

require_once __DIR__ . '/../../includes/header.php';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$verifyUrl = $scheme . '://' . $host . BASE_URL . '/modules/employees/id_verify.php?code=' . urlencode($emp['employee_number']);

// Generate Fayda-style FAN format: e.g. FAN 9410 8241 1902
$fanNumber = !empty($emp['national_id']) ? $emp['national_id'] : 'FAN ' . rand(7000, 9999) . ' ' . rand(1000, 9999) . ' ' . str_pad((string)$emp['id'], 4, '0', STR_PAD_LEFT);
$amharicName = !empty($emp['amharic_name']) ? $emp['amharic_name'] : $emp['full_name'];
$rawHireDate = !empty($emp['hire_date']) ? $emp['hire_date'] : date('Y-m-d');
$issueDateEth = eth_date($rawHireDate);
$rawExpiryDate = date('Y-m-d', strtotime('+5 years', strtotime($rawHireDate)));
$expiryDateEth = eth_date($rawExpiryDate);
$emergencyName = !empty($emp['emergency_contact']) ? $emp['emergency_contact'] : 'ተጠሪ አልተጠቀሰም';
$emergencyPhone = !empty($emp['emergency_contact_phone']) ? $emp['emergency_contact_phone'] : (!empty($emp['phone_alt']) ? $emp['phone_alt'] : ($emp['phone'] ?: '+251 911 00 00 00'));


// Generate 100% Offline QR Code using includes/qrcode.php
$qrDataUri = '';
try {
    require_once __DIR__ . '/../../includes/qrcode.php';
    $qr = new QRCode($verifyUrl, ['w' => 100, 'h' => 100, 's' => 'qrh', 'fc' => '1e5631']);
    $img = $qr->render_image();
    if ($img) {
        ob_start();
        imagepng($img);
        $qrDataUri = 'data:image/png;base64,' . base64_encode(ob_get_clean());
        imagedestroy($img);
    }
} catch (Throwable) {
    $qrDataUri = '';
}
?>

<div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🪪 የኢትዮጵያ ፋይዳ ዲጂታል መታወቂያ ካርድ (Ethiopian Fayda-Style ID Card)</h2>
        <p class="text-muted" style="margin:0">ባለ 2 ገጽ አግድም መታወቂያ ካርድ (Horizontal 2-Sided ID with Verification QR Code)</p>
    </div>
    
    <!-- Quick Search / Switch Employee -->
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <?php if ($isHrOrManager): ?>
            <form method="get" style="display:flex;gap:6px;align-items:center;margin:0">
                <select name="id" onchange="this.form.submit()" style="padding:6px 10px;font-size:13px;border-radius:6px;min-width:220px">
                    <?php foreach ($allEmployees as $ae): ?>
                        <option value="<?= (int)$ae['id'] ?>" <?= $empId === (int)$ae['id'] ? 'selected' : '' ?>>
                            <?= h($ae['full_name']) ?> (<?= h($ae['employee_number']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <button class="btn secondary" onclick="document.getElementById('editModal').style.display='block'" style="padding:6px 12px">✏️ መረጃ አሻሽል</button>
            <a href="<?= BASE_URL ?>/modules/employees/photo_upload.php?id=<?= (int) $empId ?>" class="btn secondary" style="padding:6px 12px">📷 ፎቶ ቀይር</a>
        <?php endif; ?>
        <button class="btn" onclick="window.print()" style="padding:6px 16px;background:#1e5631;border-color:#1e5631">🖨️ መታወቂያውን አትም (Print)</button>
        <?php if ($isHrOrManager): ?>
            <a href="<?= BASE_URL ?>/modules/employees/list.php" class="btn secondary" style="padding:6px 12px">&larr; ወደ ሠራተኞች ዝርዝር</a>
        <?php endif; ?>
    </div>
</div>

<?php if (has_role(['super_admin', 'general_manager', 'hr_officer'])): ?>
<!-- HR Quick Edit Modal -->
<div id="editModal" class="no-print" style="display:none;position:fixed;z-index:2000;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);overflow-y:auto;padding:20px 10px;">
    <div style="background:var(--card);max-width:500px;margin:40px auto;padding:24px;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,0.2);border:1px solid var(--border)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h3 style="margin:0">✏️ የመታወቂያ ካርድ መረጃ ማስተካከያ</h3>
            <button type="button" onclick="document.getElementById('editModal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="update_employee_card" value="1">
            <input type="hidden" name="employee_id" value="<?= (int)$emp['id'] ?>">

            <div class="form-group" style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:700">ሙሉ ስም (እንግሊዝኛ) *</label>
                <input type="text" name="full_name" value="<?= h($emp['full_name']) ?>" required style="width:100%;padding:8px">
            </div>
            <div class="form-group" style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:700">ሙሉ ስም (በአማርኛ)</label>
                <input type="text" name="amharic_name" value="<?= h($emp['amharic_name'] ?? '') ?>" placeholder="ለምሳሌ፡ ተክሉ ጌታቸው" style="width:100%;padding:8px">
            </div>
            <div class="form-group" style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:700">የሥራ መደብ (Job Title)</label>
                <input type="text" name="job_title" value="<?= h($emp['job_title'] ?? '') ?>" style="width:100%;padding:8px">
            </div>
            <div class="form-group" style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:700">ስልክ ቁጥር</label>
                <input type="text" name="phone" value="<?= h($emp['phone'] ?? '') ?>" style="width:100%;padding:8px">
            </div>
            <div class="form-group" style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:700">የአደጋ ጊዜ ተጠሪ ስም</label>
                <input type="text" name="emergency_contact" value="<?= h($emp['emergency_contact'] ?? '') ?>" placeholder="ለምሳሌ፡ አቶ አበበ ደሳለኝ" style="width:100%;padding:8px">
            </div>
            <div class="form-group" style="margin-bottom:16px">
                <label style="font-size:12px;font-weight:700">የአደጋ ጊዜ ተጠሪ ስልክ ቁጥር</label>
                <input type="text" name="emergency_contact_phone" value="<?= h($emp['emergency_contact_phone'] ?? ($emp['phone_alt'] ?? '')) ?>" placeholder="09xxxxxxxx" style="width:100%;padding:8px">
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn secondary" onclick="document.getElementById('editModal').style.display='none'">ሰርዝ</button>
                <button type="submit" class="btn" style="background:#1e5631;border-color:#1e5631">✓ መረጃውን አዘምን</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<style>
/* Fayda ID Card Styling */
.fayda-container {
    display: flex;
    gap: 30px;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    padding: 20px 0;
}

.fayda-card {
    width: 500px;
    height: 310px;
    border-radius: 14px;
    background: #ffffff;
    border: 1.5px solid #c8d6cb;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
}

@media (max-width: 540px) {
    .fayda-card {
        width: 100% !important;
        max-width: 360px !important;
        height: auto !important;
    }
}

.fayda-card {
    overflow: hidden;
    position: relative;
    font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    color: #1a201c;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}

/* Security Ribbon Header */
.fayda-header-ribbon {
    height: 6px;
    background: linear-gradient(90deg, #078930 0%, #078930 33.3%, #fcd116 33.3%, #fcd116 66.6%, #da121a 66.6%, #da121a 100%);
}

.fayda-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px 6px 14px;
    background: linear-gradient(180deg, #f3f8f3 0%, #ffffff 100%);
    border-bottom: 1px solid #e0eae1;
}

.fayda-header-text {
    text-align: center;
    flex: 1;
}

.fayda-header-title-am {
    font-size: 11px;
    font-weight: 800;
    color: #1e5631;
    letter-spacing: 0.2px;
}

.fayda-header-title-en {
    font-size: 8.5px;
    font-weight: 700;
    color: #4a5c4e;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-top: 1px;
}

.fayda-header-sub {
    font-size: 9px;
    font-weight: 800;
    color: #b7791f;
    letter-spacing: 0.5px;
}

/* Front Card Body */
.fayda-body {
    display: flex;
    padding: 12px 14px;
    gap: 14px;
    flex: 1;
    position: relative;
    background: radial-gradient(circle at center, rgba(232, 245, 233, 0.4) 0%, #ffffff 70%);
}

/* Background Seal Watermark */
.fayda-watermark {
    position: absolute;
    top: 50%;
    left: 55%;
    transform: translate(-50%, -50%);
    font-size: 120px;
    opacity: 0.04;
    pointer-events: none;
    user-select: none;
}

.fayda-photo-box {
    width: 105px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.fayda-photo {
    width: 100px;
    height: 125px;
    border-radius: 8px;
    object-fit: cover;
    border: 2px solid #1e5631;
    box-shadow: 0 3px 8px rgba(0,0,0,0.15);
    background: #e8f5e9;
}

.fayda-photo-placeholder {
    width: 100px;
    height: 125px;
    border-radius: 8px;
    background: #e8f5e9;
    border: 2px solid #1e5631;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 52px;
    color: #1e5631;
}

.fayda-id-number-pill {
    margin-top: 8px;
    background: #1e5631;
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    padding: 3px 6px;
    border-radius: 4px;
    letter-spacing: 0.5px;
    text-align: center;
    width: 100%;
    box-sizing: border-box;
}

.fayda-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.fayda-name-am {
    font-size: 16px;
    font-weight: 800;
    color: #1e5631;
    line-height: 1.2;
}

.fayda-name-en {
    font-size: 13px;
    font-weight: 700;
    color: #2c3e50;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 6px;
}

.fayda-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px 10px;
    font-size: 10.5px;
    background: rgba(245, 248, 245, 0.75);
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid #e2ece3;
}

.fayda-grid-item {
    display: flex;
    flex-direction: column;
}

.fayda-grid-lbl {
    font-size: 8px;
    color: #607263;
    font-weight: 600;
    text-transform: uppercase;
}

.fayda-grid-val {
    font-weight: 700;
    color: #1c281e;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.fayda-fan-row {
    margin-top: 6px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px dashed #c0d1c3;
    padding-top: 4px;
}

.fayda-fan-code {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    font-weight: 800;
    color: #b7791f;
    letter-spacing: 1px;
}

/* Back Card Styling */
.fayda-back-body {
    display: flex;
    padding: 12px 14px;
    gap: 16px;
    flex: 1;
    align-items: center;
    background: #fafcfa;
}

.fayda-qr-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 8px;
    background: #fff;
    border: 1.5px solid #1e5631;
    border-radius: 10px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.06);
}

.fayda-back-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
}

.fayda-back-table {
    width: 100%;
    font-size: 10px;
    line-height: 1.5;
    border-collapse: collapse;
}

.fayda-back-table td {
    padding: 2px 4px;
    border-bottom: 1px solid #eef3ee;
}

.fayda-back-table td.lbl {
    color: #607263;
    font-weight: 600;
    width: 40%;
}

.fayda-back-table td.val {
    font-weight: 700;
    color: #1c281e;
}

.fayda-signature-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-top: 6px;
    padding-top: 4px;
}

.fayda-disclaimer {
    font-size: 7.5px;
    color: #728475;
    line-height: 1.25;
    margin-top: 4px;
    border-top: 1px solid #e2ece3;
    padding-top: 4px;
}

.fayda-barcode-strip {
    background: #1e5631;
    color: #fff;
    padding: 4px 10px;
    font-family: monospace;
    font-size: 9px;
    letter-spacing: 2px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Print Specific Rules */
@media print {
    .no-print {
        display: none !important;
    }
    body, html {
        background: #fff !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .fayda-container {
        display: flex !important;
        flex-direction: row !important;
        gap: 20px !important;
        padding: 10mm 5mm !important;
        justify-content: center !important;
        page-break-inside: avoid !important;
    }
    .fayda-card {
        box-shadow: none !important;
        border: 1.5px solid #000 !important;
        page-break-inside: avoid !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>

<div class="fayda-container">

    <!-- FRONT SIDE (የፊት ገጽ) -->
    <div>
        <p class="text-muted no-print" style="text-align:center;font-weight:700;margin-bottom:6px">የፊት ገጽ (FRONT SIDE)</p>
        <div class="fayda-card">
            <div class="fayda-header-ribbon"></div>
            
            <div class="fayda-header">
                <div style="font-size:22px;line-height:1">🐄</div>
                <div class="fayda-header-text">
                    <div class="fayda-header-title-am">ተክሉ ጌታቸው የከብት እርባታ እና የወተት ተዋጽኦ ማቀነባበሪያ ድርጅት</div>
                    <div class="fayda-header-title-en">TEKLU GETACHEW CATTLE BREEDING & DAIRY PROCESSING</div>
                </div>
                <div style="font-size:14px;font-weight:900;color:#1e5631">🇪🇹</div>
            </div>

            <div class="fayda-body">
                <div class="fayda-watermark">🐄</div>

                <!-- Photo Column -->
                <div class="fayda-photo-box">
                    <?php if (employee_photo_exists($emp['photo_path'] ?? null)): ?>
                        <img src="<?= h($emp['photo_path']) ?>" class="fayda-photo" alt="Photo">
                    <?php else: ?>
                        <div class="fayda-photo-placeholder">👤</div>
                    <?php endif; ?>
                    <div class="fayda-id-number-pill"><?= h($emp['employee_number']) ?></div>
                </div>

                <!-- Details Column -->
                <div class="fayda-details">
                    <div>
                        <div class="fayda-name-am"><?= h($amharicName) ?></div>
                        <div class="fayda-name-en"><?= h($emp['full_name']) ?></div>
                    </div>

                    <div class="fayda-grid">
                        <div class="fayda-grid-item">
                            <span class="fayda-grid-lbl">የሥራ ክፍል / Dept</span>
                            <span class="fayda-grid-val"><?= h($emp['dept_name'] ?: 'Operations') ?></span>
                        </div>
                        <div class="fayda-grid-item">
                            <span class="fayda-grid-lbl">የሥራ መደብ / Title</span>
                            <span class="fayda-grid-val"><?= h($emp['job_title'] ?: 'Staff Member') ?></span>
                        </div>
                        <div class="fayda-grid-item">
                            <span class="fayda-grid-lbl">የተሰጠበት / Issue Date</span>
                            <span class="fayda-grid-val"><?= h($issueDateEth) ?></span>
                        </div>
                        <div class="fayda-grid-item">
                            <span class="fayda-grid-lbl">የሚያበቃበት / Expiry</span>
                            <span class="fayda-grid-val" style="color:#b3261e"><?= h($expiryDateEth) ?></span>
                        </div>
                    </div>

                    <div class="fayda-fan-row">
                        <span style="font-size:9px;color:#607263;font-weight:700">ፋይዳ መለያ / FAN:</span>
                        <span class="fayda-fan-code"><?= h($fanNumber) ?></span>
                    </div>
                </div>
            </div>

            <div class="fayda-barcode-strip">
                <span>IDENTITY NO: <?= h($emp['employee_number']) ?></span>
                <span style="font-size:7.5px;letter-spacing:1px">ALELTU-EIFMS-SECURE-ID</span>
                <span>STATUS: ACTIVE</span>
            </div>
        </div>
    </div>

    <!-- BACK SIDE (የጀርባ ገጽ) -->
    <div>
        <p class="text-muted no-print" style="text-align:center;font-weight:700;margin-bottom:6px">የጀርባ ገጽ (BACK SIDE)</p>
        <div class="fayda-card">
            <div class="fayda-header-ribbon"></div>
            
            <div class="fayda-header">
                <div style="font-size:12px;font-weight:800;color:#1e5631">🔐 ይፋዊ ማረጋገጫና አደጋ ጊዜ መረጃ</div>
                <div style="font-size:9px;color:#607263;font-weight:700">VERIFICATION &amp; EMERGENCY INFO</div>
            </div>

            <div class="fayda-back-body">
                <!-- QR Box -->
                <div class="fayda-qr-box">
                    <?php if ($qrDataUri): ?>
                        <img src="<?= $qrDataUri ?>" alt="Verification QR Code" style="width:84px;height:84px;display:block">
                    <?php else: ?>
                        <div style="width:84px;height:84px;display:flex;align-items:center;justify-content:center;background:#eee;font-size:10px;color:#666">QR Code</div>
                    <?php endif; ?>
                    <span style="font-size:8px;font-weight:700;color:#1e5631;margin-top:4px">SCAN TO VERIFY</span>
                </div>

                <!-- Info Table -->
                <div class="fayda-back-info">
                    <table class="fayda-back-table">
                        <tr>
                            <td class="lbl">ስልክ (Phone):</td>
                            <td class="val"><?= h($emp['phone'] ?: '+251 911 00 00 00') ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">የአደጋ ጊዜ ተጠሪ ስም:</td>
                            <td class="val"><?= h($emergencyName) ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">የተጠሪው ስልክ ቁጥር:</td>
                            <td class="val"><?= h($emergencyPhone) ?></td>
                        </tr>
                        <tr>
                            <td class="lbl">የድርጅቱ አድራሻ:</td>
                            <td class="val">ኦሮሚያ፣ ሰሜን ሸዋ፣ አለልቱ</td>
                        </tr>
                    </table>

                    <div class="fayda-signature-row">
                        <div>
                            <div style="font-family:'Brush Script MT', cursive;font-size:16px;color:#1e5631">Teklu Getachew</div>
                            <div style="font-size:7.5px;color:#607263;border-top:1px solid #607263;padding-top:1px">ተክሉ ጌታቸው — ዋና ሥራ አስኪያጅ (General Manager)</div>
                        </div>
                        <div style="text-align:right">
                            <span style="font-size:8px;font-weight:800;color:#b7791f;border:1px solid #b7791f;padding:2px 5px;border-radius:3px">✓ VERIFIED</span>
                        </div>
                    </div>

                    <div class="fayda-disclaimer">
                        ይህ ካርድ የተክሉ ጌታቸው የከብት እርባታ እና የወተት ተዋጽኦ ማቀነባበሪያ ድርጅት ንብረት ነው። የተገኘ ሰው በአቅራቢያው ለሚገኝ ፖሊስ ጣቢያ ወይም ለድርጅቱ ኃላፊ ያስረክቡ።<br>
                        Property of Teklu Getachew Dairy Farm. If found, please return to Teklu Getachew Farm HR Office.
                    </div>
                </div>
            </div>

            <div class="fayda-barcode-strip" style="background:#1e5631">
                <span>IDENTITY NO: <?= h($emp['employee_number']) ?></span>
                <span style="font-size:7.5px;letter-spacing:1px">ALELTU-EIFMS-SECURE-ID</span>
                <span>STATUS: ACTIVE</span>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
