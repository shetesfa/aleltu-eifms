<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'warehouse_keeper', 'finance_officer', 'distribution_officer']);

$paymentId = (int)($_GET['payment_id'] ?? 0);
if ($paymentId <= 0) {
    die("ትክክለኛ የክፍያ መለያ አልተገኘም (Invalid Payment ID).");
}

$stmt = db()->prepare(
    "SELECT p.*, d.id AS dispatch_id, d.destination_pos_location, d.quantity_dispatched, d.unit_price, 
            d.total_price, d.amount_paid AS cumulative_paid, d.payment_status,
            i.name AS item_name, i.unit, u.name AS receiver_name
     FROM dist_dispatch_payments p
     JOIN dist_dispatches d ON d.id = p.dispatch_id
     JOIN wh_stock_items i ON i.id = d.stock_item_id
     JOIN users u ON u.id = p.received_by
     WHERE p.id = ?"
);
$stmt->execute([$paymentId]);
$pay = $stmt->fetch();

if (!$pay) {
    die("ይህ የክፍያ ደረሰኝ በሲስተሙ ውስጥ አልተገኘም (Receipt record not found).");
}

$remainingBalance = max(0, (float)$pay['total_price'] - (float)$pay['cumulative_paid']);
$receiptNumber = 'RCT-' . str_pad((string)$pay['id'], 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ደረሰኝ ቁጥር #<?= h($receiptNumber) ?> — አለልቱ ፋርም</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 30px 15px;
            color: #1a202c;
        }
        .receipt-container {
            max-width: 680px;
            margin: 0 auto;
            background: #ffffff;
            padding: 32px 36px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            position: relative;
        }
        .receipt-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #2e7d32;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .company-title h1 {
            font-size: 16px;
            font-weight: 800;
            color: #1e5631;
            margin: 0 0 4px;
        }
        .company-title h2 {
            font-size: 11px;
            color: #4a5568;
            margin: 0 0 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .company-title p {
            font-size: 10.5px;
            color: #718096;
            margin: 0;
        }
        .receipt-badge {
            text-align: right;
        }
        .voucher-title {
            font-size: 14px;
            font-weight: 800;
            background: #1e5631;
            color: #ffffff;
            padding: 4px 12px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 6px;
        }
        .receipt-no {
            font-family: monospace;
            font-size: 14px;
            font-weight: 700;
            color: #b7791f;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 20px;
            background: #f8fafc;
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #edf2f7;
            font-size: 13px;
        }
        .info-grid div span.lbl {
            color: #718096;
            font-size: 11px;
            display: block;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .info-grid div span.val {
            font-weight: 700;
            color: #2d3748;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .items-table th {
            background: #f1f5f9;
            color: #334155;
            font-weight: 700;
            padding: 9px 12px;
            text-align: left;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }
        .items-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 24px;
        }
        .totals-box {
            width: 280px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 12.5px;
        }
        .totals-row.highlight {
            border-top: 1.5px solid #2e7d32;
            border-bottom: 1.5px solid #2e7d32;
            padding: 8px 0;
            margin: 6px 0;
            font-size: 15px;
            font-weight: 800;
            color: #1e5631;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px dashed #cbd5e1;
        }
        .sig-block {
            text-align: center;
            width: 40%;
        }
        .sig-line {
            border-bottom: 1px solid #718096;
            margin-bottom: 6px;
            height: 30px;
        }
        .sig-label {
            font-size: 11px;
            color: #718096;
            font-weight: 600;
        }
        .receipt-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 10.5px;
            color: #a0aec0;
            border-top: 1px solid #f1f5f9;
            padding-top: 12px;
        }
        .action-bar {
            max-width: 680px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            background: #1e5631;
            color: #fff;
            padding: 8px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn.secondary {
            background: #e2e8f0;
            color: #334155;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .action-bar {
                display: none !important;
            }
            .receipt-container {
                box-shadow: none;
                border: 1px solid #ccc;
                padding: 20px;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="action-bar">
    <a href="<?= BASE_URL ?>/modules/distribution/dispatches.php" class="btn secondary">← ወደ ስርጭት ገጽ ተመለስ</a>
    <button class="btn" onclick="window.print()">🖨️ ደረሰኙን አትም (Print Receipt)</button>
</div>

<div class="receipt-container">
    
    <div class="receipt-header">
        <div class="company-title">
            <h1>🐄 ተክሉ ጌታቸው የከብት እርባታና የወተት ተዋጽኦ ማቀነባበሪያ ድርጅት</h1>
            <h2>Teklu Getachew Cattle Breeding &amp; Dairy Processing Farm</h2>
            <p>ኦሮሚያ፣ ሰሜን ሸዋ፣ አለልቱ | ስልክ፡ +251 911 22 33 44 | ኢሜይል፡ info@aleltufarm.com</p>
        </div>
        <div class="receipt-badge">
            <div class="voucher-title">ይፋዊ የገቢ ደረሰኝ</div>
            <div class="receipt-no"><?= h($receiptNumber) ?></div>
        </div>
    </div>

    <div class="info-grid">
        <div>
            <span class="lbl">የተከፈለበት ቀን እና ሰዓት፦</span>
            <span class="val">📅 <?= eth_date($pay['created_at']) ?> (⏰ <?= eth_time($pay['created_at']) ?>)</span>
        </div>
        <div>
            <span class="lbl">የስርጭት መለያ ቁጥር (Dispatch #)፦</span>
            <span class="val">#<?= (int)$pay['dispatch_id'] ?></span>
        </div>
        <div>
            <span class="lbl">የመዳረሻ ሱቅ / የከፋይ ስም፦</span>
            <span class="val"><?= h($pay['destination_pos_location']) ?></span>
        </div>
        <div>
            <span class="lbl">የክፍያ ዘዴ፦</span>
            <span class="val">
                <?php
                echo match($pay['payment_method']) {
                    'telebirr' => '📱 ቴሌብር (Telebirr)',
                    'bank'     => '🏦 በባንክ የተላለፈ (Bank Transfer)',
                    default    => '💵 ጥሬ ገንዘብ (Cash)'
                };
                ?>
            </span>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>የምርት ዓይነት (Item Description)</th>
                <th style="text-align:center">የተላከው መጠን</th>
                <th style="text-align:right">የአንዱ ዋጋ</th>
                <th style="text-align:right">ጠቅላላ ዋጋ</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong><?= h($pay['item_name']) ?></strong></td>
                <td style="text-align:center"><?= number_format($pay['quantity_dispatched'], 1) ?> <?= h($pay['unit']) ?></td>
                <td style="text-align:right"><?= number_format($pay['unit_price'], 2) ?> ETB</td>
                <td style="text-align:right"><strong><?= number_format($pay['total_price'], 2) ?> ETB</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="totals-section">
        <div class="totals-box">
            <div class="totals-row">
                <span>የምርቱ ጠቅላላ ዋጋ፦</span>
                <strong><?= number_format($pay['total_price'], 2) ?> ETB</strong>
            </div>
            <div class="totals-row highlight">
                <span>አሁን የተከፈለው መጠን፦</span>
                <strong><?= number_format($pay['amount'], 2) ?> ETB</strong>
            </div>
            <div class="totals-row">
                <span>ጠቅላላ የተከፈለ ሂሳብ፦</span>
                <span><?= number_format($pay['cumulative_paid'], 2) ?> ETB</span>
            </div>
            <div class="totals-row" style="color:<?= $remainingBalance > 0 ? '#b3261e' : '#2e7d32' ?>">
                <span>ቀሪ ያልተከፈለ ዕዳ፦</span>
                <strong><?= number_format($remainingBalance, 2) ?> ETB</strong>
            </div>
        </div>
    </div>

    <div class="signatures">
        <div class="sig-block">
            <div class="sig-line"></div>
            <div class="sig-label">ክፍያውን የተቀበለው ሠራተኛ (Receiver)</div>
            <div style="font-weight:700;font-size:12px;margin-top:2px"><?= h($pay['receiver_name']) ?></div>
        </div>
        <div class="sig-block">
            <div class="sig-line"></div>
            <div class="sig-label">የከፋዩ / የተረካቢው ፊርማ (Payer Signature)</div>
            <div style="font-weight:700;font-size:12px;margin-top:2px">ተረካቢ ኃላፊ</div>
        </div>
    </div>

    <div class="receipt-footer">
        ይህ ሰነድ በኮምፒውተር በራስ-ሰር የተዘጋጀ ይፋዊ የክፍያ ማረጋገጫ ደረሰኝ ነው። እናመሰግናለን!
    </div>

</div>

</body>
</html>
