<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'finance_officer']);

$id = (int)($_GET['id'] ?? 0);
$chequeStmt = db()->prepare(
    "SELECT c.*, b.bank_name AS org_bank_name, b.account_number AS org_account_number,
            cb.bank_name AS cleared_bank_name, cb.account_number AS cleared_account_number,
            u.name AS recorded_by_name
     FROM fin_cheques c
     LEFT JOIN fin_bank_accounts b ON b.id = c.bank_account_id
     LEFT JOIN fin_bank_accounts cb ON cb.id = c.cleared_bank_account_id
     LEFT JOIN users u ON u.id = c.recorded_by
     WHERE c.id = ?"
);
$chequeStmt->execute([$id]);
$cheque = $chequeStmt->fetch();

if (!$cheque) {
    die("Cheque record not found.");
}

$companyName = setting_get('company_name') ?? 'አለልቱ የተቀናጀ እርሻና የወተት ማቀነባበሪያ';
$isIssued = ($cheque['cheque_type'] === 'issued');
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title><?= $isIssued ? 'የቼክ ክፍያ ማዘዣ ቫውቸር' : 'የቼክ ገቢ ደረሰኝ ቫውቸር' ?> - #<?= h($cheque['cheque_number']) ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
            color: #222;
        }
        .voucher-box {
            max-width: 750px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border: 2px solid #333;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #222;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .header h2 { margin: 0 0 4px 0; font-size: 20px; }
        .header h3 { margin: 0 0 6px 0; font-size: 16px; color: <?= $isIssued ? '#c62828' : '#2e7d32' ?>; text-transform: uppercase; }
        .header p { margin: 0; font-size: 13px; color: #555; }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
            font-size: 13.5px;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 4px;
        }
        .info-item strong { color: #111; }
        .amount-box {
            background: #f8f9fa;
            border: 2px dashed #444;
            padding: 14px 20px;
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 16px;
        }
        .amount-num {
            font-size: 22px;
            font-weight: 800;
            color: <?= $isIssued ? '#c62828' : '#2e7d32' ?>;
        }
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-top: 50px;
            text-align: center;
            font-size: 12.5px;
        }
        .sig-line {
            border-top: 1px solid #333;
            padding-top: 6px;
            margin-top: 40px;
        }
        .status-stamp {
            position: absolute;
            top: 25px;
            right: 30px;
            border: 2px solid <?= $cheque['status'] === 'cleared' ? '#2e7d32' : ($cheque['status'] === 'bounced' ? '#c62828' : '#e65100') ?>;
            color: <?= $cheque['status'] === 'cleared' ? '#2e7d32' : ($cheque['status'] === 'bounced' ? '#c62828' : '#e65100') ?>;
            padding: 4px 12px;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 12px;
            transform: rotate(-5deg);
        }
        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .voucher-box { box-shadow: none; border: 2px solid #000; padding: 25px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" style="padding: 10px 24px; font-size: 15px; font-weight: 700; background: #1565c0; color: #fff; border: none; border-radius: 6px; cursor: pointer;">
        🖨️ ይህንን ቫውቸር አትም (Print Voucher)
    </button>
    <a href="<?= BASE_URL ?>/modules/finance/cheques.php" style="margin-left: 12px; padding: 10px 18px; font-size: 14px; background: #eee; color: #333; text-decoration: none; border-radius: 6px;">
        &larr; ተመለስ
    </a>
</div>

<div class="voucher-box">
    <div class="status-stamp">
        <?= strtoupper($cheque['status']) ?>
    </div>

    <div class="header">
        <h2><?= h($companyName) ?></h2>
        <h3><?= $isIssued ? '📑 የቼክ ክፍያ ማዘዣ ቫውቸር (Cheque Payment Voucher)' : '📥 የቼክ ገቢ ደረሰኝ ቫውቸር (Cheque Receipt Voucher)' ?></h3>
        <p>ቀን (በኢትዮጵያ አቆጣጠር)፦ <strong><?= eth_date($cheque['issue_date']) ?></strong> (<?= date('d/m/Y', strtotime($cheque['issue_date'])) ?> እ.ኤ.አ)</p>
    </div>

    <div class="info-grid">
        <div class="info-item">
            <span>የቼክ ቁጥር (Cheque #)፦</span>
            <strong>#<?= h($cheque['cheque_number']) ?></strong>
        </div>
        <div class="info-item">
            <span>የባንክ ስም (Bank)፦</span>
            <strong><?= h($cheque['bank_name']) ?></strong>
        </div>
        <div class="info-item">
            <span><?= $isIssued ? 'የተሰጠለት አካል (Payee)፦' : 'ቼኩን የሰጠን አካል (Drawer)፦' ?></span>
            <strong><?= h($cheque['payee_or_drawer']) ?></strong>
        </div>
        <div class="info-item">
            <span>የሚመነዘርበት ቀን (Due Date)፦</span>
            <strong><?= eth_date($cheque['due_date']) ?></strong>
        </div>
        <?php if ($cheque['bank_account_id'] && !empty($cheque['org_account_number'])): ?>
        <div class="info-item">
            <span>የድርጅቱ ሒሳብ ቁጥር፦</span>
            <strong><?= h($cheque['org_bank_name']) ?> - <?= h($cheque['org_account_number']) ?></strong>
        </div>
        <?php endif; ?>
        <div class="info-item">
            <span>ተዛማጅ የስራ ክፍል፦</span>
            <strong><?= ucfirst(h($cheque['related_module'] ?? 'Finance')) ?></strong>
        </div>
    </div>

    <div class="amount-box">
        <div>
            <div>የገንዘብ መጠን በብር (Amount in ETB)፦</div>
            <small style="color:#555">የተፈቀደ ይፋዊ የቼክ ክፍያ</small>
        </div>
        <div class="amount-num">
            <?= number_format((float)$cheque['amount'], 2) ?> ETB
        </div>
    </div>

    <?php if (!empty($cheque['notes'])): ?>
    <div style="background:#fafafa; border:1px solid #eee; padding:10px; font-size:13px; margin-bottom:20px; border-radius:4px">
        <strong>ማስታወሻ / ምክንያት፦</strong> <?= h($cheque['notes']) ?>
    </div>
    <?php endif; ?>

    <?php if ($cheque['status'] === 'cleared'): ?>
    <div style="background:#e8f5e9; border:1px solid #c8e6c9; padding:8px 12px; font-size:12px; color:#2e7d32; border-radius:4px; margin-bottom:15px">
        ✓ ይህ ቼክ በ <strong><?= eth_date($cheque['cleared_date']) ?></strong> በ <strong><?= h($cheque['cleared_bank_name']) ?> (<?= h($cheque['cleared_account_number']) ?>)</strong> በኩል ተመንዝሮ ሂሳቡ ገቢ/ወጪ ሆኗል።
    </div>
    <?php endif; ?>

    <div class="signatures">
        <div>
            <div>ያዘጋጀው (Prepared By)</div>
            <div style="font-weight:700"><?= h($cheque['recorded_by_name'] ?? 'Finance Officer') ?></div>
            <div class="sig-line">ፊርማ / Signature</div>
        </div>
        <div>
            <div>ያረጋገጠው (Audited By)</div>
            <div style="font-weight:700">ዋና የሂሳብ ሹም</div>
            <div class="sig-line">ፊርማ / Signature</div>
        </div>
        <div>
            <div>ያጸደቀው (Approved By)</div>
            <div style="font-weight:700">ስራ አስኪያጅ (GM)</div>
            <div class="sig-line">ፊርማ / Signature</div>
        </div>
    </div>
</div>

</body>
</html>
