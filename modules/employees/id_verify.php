<?php
require_once __DIR__ . '/../../includes/functions.php';
// Public page — reached by scanning the QR code on a printed ID card.
// Deliberately does not require login, but only exposes non-sensitive
// identity info (name, photo, department, phone) — never salary, address, etc.

$code = trim($_GET['code'] ?? '');
$emp = null;
if ($code !== '') {
    $stmt = db()->prepare(
        "SELECT e.id, e.full_name, e.amharic_name, e.job_title, e.employee_number, e.phone, e.photo_path, e.status, e.hire_date, d.name AS dept_name
         FROM employees e LEFT JOIN departments d ON d.id = e.department_id
         WHERE e.employee_number = ? OR e.id = ?"
    );
    $stmt->execute([$code, (int)$code]);
    $emp = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Employee Verification — Aleltu Farm</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }
        .card {
            background: #ffffff;
            border-radius: 20px;
            max-width: 380px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 16px 40px rgba(30, 86, 49, 0.18);
            text-align: center;
            border: 1px solid rgba(46, 125, 50, 0.15);
        }
        .banner {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: #ffffff;
            padding: 24px 18px 56px;
            position: relative;
        }
        .banner-logo {
            font-size: 28px;
            margin-bottom: 6px;
            display: inline-block;
        }
        .banner-title {
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin: 0;
            color: #e8f5e9;
        }
        .banner-sub {
            font-size: 12px;
            opacity: 0.9;
            margin-top: 3px;
        }
        .photo-wrap {
            position: relative;
            margin: -55px auto 14px;
            width: 120px;
            height: 120px;
        }
        .photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ffffff;
            box-shadow: 0 8px 24px rgba(0,0,0,0.18);
            display: block;
            background: #ffffff;
        }
        .photo-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid #ffffff;
            box-shadow: 0 8px 24px rgba(0,0,0,0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 52px;
            background: #e8f5e9;
            color: #2e7d32;
        }
        .body {
            padding: 0 24px 28px;
        }
        .emp-name-am {
            font-size: 19px;
            font-weight: 800;
            color: #1e5631;
            margin: 0 0 2px;
        }
        .emp-name-en {
            font-size: 15px;
            font-weight: 600;
            color: #4b5563;
            margin: 0 0 10px;
        }
        .id-badge {
            display: inline-block;
            background: #f1f8e9;
            color: #2e7d32;
            font-family: monospace;
            font-weight: 800;
            font-size: 13px;
            padding: 3px 12px;
            border-radius: 12px;
            border: 1px solid #c8e6c9;
            margin-bottom: 16px;
        }
        .info-grid {
            background: #f9fbf9;
            border-radius: 14px;
            padding: 16px;
            border: 1px solid #e2ece2;
            text-align: left;
            font-size: 13.5px;
            line-height: 1.8;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            border-bottom: 1px dashed #e8eee8;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-lbl {
            color: #6b7280;
            font-weight: 500;
        }
        .info-val {
            color: #1f2937;
            font-weight: 700;
            text-align: right;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #e8f5e9;
            color: #1b5e20;
            font-weight: 800;
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 13.5px;
            border: 1px solid #a5d6a7;
        }
        .status-pill.inactive {
            background: #ffebee;
            color: #c62828;
            border-color: #ef9a9a;
        }
        .footer-note {
            margin-top: 20px;
            font-size: 11px;
            color: #9ca3af;
            border-top: 1px solid #f0f0f0;
            padding-top: 12px;
        }
    </style>
</head>
<body>

<div class="card">
    <div class="banner">
        <div class="banner-logo">🌾</div>
        <div class="banner-title">የአለልቱ የተቀናጀ እርሻ</div>
        <div class="banner-sub">ALELTU INTEGRATED FARM &bull; DIGITAL ID VERIFY</div>
    </div>

    <?php if ($emp): ?>
        <div class="photo-wrap">
            <?php if (employee_photo_exists($emp['photo_path'] ?? null)): ?>
                <img class="photo" src="<?= h($emp['photo_path']) ?>?v=<?= time() ?>" alt="<?= h($emp['full_name']) ?>">
            <?php else: ?>
                <div class="photo-placeholder">👤</div>
            <?php endif; ?>
        </div>

        <div class="body">
            <?php if (!empty($emp['amharic_name'])): ?>
                <h2 class="emp-name-am"><?= h($emp['amharic_name']) ?></h2>
            <?php endif; ?>
            <div class="emp-name-en"><?= h($emp['full_name']) ?></div>
            <div class="id-badge"><?= h($emp['employee_number']) ?></div>

            <div class="info-grid">
                <div class="info-row">
                    <span class="info-lbl">የሥራ ክፍል (Dept):</span>
                    <span class="info-val"><?= h($emp['dept_name'] ?: 'Operations') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-lbl">የሥራ መደብ (Title):</span>
                    <span class="info-val"><?= h($emp['job_title'] ?: 'Staff Member') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-lbl">ስልክ (Phone):</span>
                    <span class="info-val"><?= h($emp['phone'] ?: '—') ?></span>
                </div>
                <?php if (!empty($emp['hire_date'])): ?>
                <div class="info-row">
                    <span class="info-lbl">የቅጥር ቀን (Hire Date):</span>
                    <span class="info-val"><?= h($emp['hire_date']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($emp['status'] === 'active'): ?>
                <div class="status-pill">
                    <span>✓</span> ሕጋዊ የድርጅቱ ሠራተኛ (Verified Active)
                </div>
            <?php else: ?>
                <div class="status-pill inactive">
                    <span>⚠</span> ሁኔታ፡ <?= h(ucfirst($emp['status'])) ?> (Not Active)
                </div>
            <?php endif; ?>

            <div class="footer-note">
                🔒 የተረጋገጠ ዲጂታል ማንነት &bull; Aleltu Farm EIFMS
            </div>
        </div>
    <?php else: ?>
        <div class="body" style="padding-top:40px">
            <div style="font-size:48px;margin-bottom:12px">⚠️</div>
            <h3 style="color:#c62828;margin:0 0 8px">ምንም መረጃ አልተገኘም</h3>
            <p style="color:#6b7280;font-size:14px;margin:0 0 20px">No matching employee record found for code: <strong><?= h($code) ?></strong></p>
            <div class="footer-note">
                Aleltu Integrated Farm Verification Portal
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

