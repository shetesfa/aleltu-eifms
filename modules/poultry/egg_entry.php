<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'poultry_manager', 'field_worker', 'super_admin']);

$selectedDate = trim($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['record_egg_entry'])) {
            $flockId  = (int) $_POST['flock_id'];
            $date     = trim($_POST['collection_date'] ?? $selectedDate);
            $whole    = max(0, (int)$_POST['whole_count']);
            $cracked  = max(0, (int)($_POST['cracked_count'] ?? 0));
            $broken   = max(0, (int)($_POST['broken_count'] ?? 0));
            $round    = max(1, (int)($_POST['round_no'] ?? 1));
            $userId   = (int) current_user()['id'];

            if ($flockId <= 0) throw new RuntimeException('Please select a flock.');
            if ($whole + $cracked + $broken === 0) throw new RuntimeException('At least one egg count must be greater than zero.');

            // Check for duplicate entry for this flock/date/round
            $existing = db()->prepare(
                "SELECT id FROM poultry_egg_collections WHERE flock_id = ? AND collection_date = ? AND round_no = ?"
            );
            $existing->execute([$flockId, $date, $round]);
            if ($existing->fetch()) {
                throw new RuntimeException(
                    current_locale() === 'am'
                        ? "ይህ መንጋ ለዚህ ቀን እና ዙር #{$round} አስቀድሞ ተመዝግቧል።"
                        : "Egg entry already exists for this flock on {$date} round #{$round}."
                );
            }

            try {
                $stmt = db()->prepare(
                    "INSERT INTO poultry_egg_collections (flock_id, collection_date, round_no, whole_count, cracked_count, broken_count, recorded_by, transferred_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NULL)"
                );
                $stmt->execute([$flockId, $date, $round, $whole, $cracked, $broken, $userId]);
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    throw new RuntimeException(
                        current_locale() === 'am'
                            ? "ይህ መንጋ ለዚህ ቀን እና ዙር #{$round} አስቀድሞ ተመዝግቧል።"
                            : "Egg entry already exists for this flock on {$date} round #{$round}."
                    );
                }
                throw $e;
            }
            $collectionId = (int)db()->lastInsertId();
            $totalCollected = $whole + $cracked + $broken;
            log_activity('poultry', 'create', 'poultry_egg_collections', $collectionId, "Flock #{$flockId}: {$whole} whole, {$cracked} cracked, {$broken} broken (Total: {$totalCollected})");
            flash('✓ ' . (current_locale() === 'am' ? "የዕንቁላል ምዝገባ ተሳክቷል! ሙሉ፦ {$whole}፣ የተሰበረ፦ {$broken}" : "Egg collection recorded! Whole: {$whole}, Broken: {$broken}"));
            header('Location: ' . BASE_URL . '/modules/poultry/egg_entry.php?date=' . urlencode($date));
            exit;

        } elseif (isset($_POST['transfer_to_store'])) {
            // BR-P02 (Option B): Daily aggregated transfer to warehouse (matching Dairy flow)
            $transferDate = trim($_POST['transfer_date'] ?? $selectedDate);
            $warehouseId  = (int)($_POST['warehouse_id'] ?? 1);
            $notes        = trim($_POST['notes'] ?? '');

            $res = poultry_daily_egg_transfer($transferDate, $warehouseId, $notes);
            flash(
                current_locale() === 'am'
                    ? "✓ የዕለቱ {$res['total_whole']} ጤናማ ዕንቁላሎች በተሳካ ሁኔታ ወደ መጋዘን ተላልፈዋል! (የመላኪያ ኮድ፦ {$res['dispatch_code']})"
                    : "Daily eggs ({$res['total_whole']}) successfully transferred to Warehouse! Dispatch code: {$res['dispatch_code']}"
            );
            header('Location: ' . BASE_URL . '/modules/poultry/egg_entry.php?date=' . urlencode($transferDate));
            exit;
        }
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../../includes/header.php';

$flocks = db()->query(
    "SELECT f.*, ph.name AS house_name,
            (f.initial_population - f.cumulative_mortality - f.cumulative_culled_sold + f.cumulative_transfers_in) AS live_count
     FROM poultry_flocks f
     JOIN poultry_houses ph ON ph.id = f.poultry_house_id
     WHERE f.status = 'active' ORDER BY ph.name, f.breed"
)->fetchAll();

// Active Warehouses for transfer dropdown
$warehouses = db()->query("SELECT id, name FROM wh_warehouses ORDER BY id ASC")->fetchAll();

// Date status and entries
$eggStatus = poultry_daily_egg_status($selectedDate);

$dayEntries = db()->prepare(
    "SELECT e.*, f.breed, ph.name AS house_name, u.name AS recorder_name
     FROM poultry_egg_collections e
     JOIN poultry_flocks f ON f.id = e.flock_id
     JOIN poultry_houses ph ON ph.id = f.poultry_house_id
     LEFT JOIN users u ON u.id = e.recorded_by
     WHERE e.collection_date = ?
     ORDER BY e.id DESC"
);
$dayEntries->execute([$selectedDate]);
$dayEntries = $dayEntries->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🥚 <?= current_locale()==='am'?'የዕለቱ እንቁላል ምዝገባና ርክክብ':'Egg Collection & Daily Warehouse Transfer' ?></h2>
        <p class="text-muted" style="margin:0">
            <?= current_locale()==='am'
                ?'የእያንዳንዱ መንጋ እንቁላል ይመዘገባል፤ በቀኑ መጨረሻ የተጠቃለለው ምርት ወደ መጋዘን ይተላለፋል (BR-P02)'
                :'Record eggs per flock per session; transfer aggregated good eggs to Warehouse at end of day (BR-P02).' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <!-- Date Selector -->
        <form method="get" style="display:flex;gap:6px;align-items:center;margin:0">
            <label style="font-size:12px;font-weight:700"><?= current_locale()==='am'?'ቀን፦':'Date:' ?></label>
            <input type="date" name="date" value="<?= h($selectedDate) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()" style="padding:6px 10px;font-size:13px">
        </form>
        <a href="<?= BASE_URL ?>/modules/poultry/dashboard.php" class="btn secondary">← <?= current_locale()==='am'?'ዳሽቦርድ':'Dashboard' ?></a>
    </div>
</div>

<!-- Daily Summary KPIs -->
<div style="display:flex;gap:14px;margin-bottom:20px;flex-wrap:wrap">
    <div style="flex:1;min-width:180px;background:rgba(46,125,50,0.08);border-left:5px solid #2e7d32;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:14px">
        <div style="font-size:28px">🥚</div>
        <div>
            <div style="font-size:12px;color:#2e7d32;font-weight:700"><?= current_locale()==='am'?'የቀኑ ሙሉ (ጥሩ) እንቁላል':'Total Whole Eggs' ?></div>
            <div style="font-size:24px;font-weight:900;color:#1b5e20"><?= number_format($eggStatus['total_whole']) ?></div>
            <div style="font-size:11px;color:var(--text-muted)"><?= eth_date($selectedDate) ?></div>
        </div>
    </div>

    <div style="flex:1;min-width:180px;background:<?= $eggStatus['pending_whole'] > 0 ? 'rgba(230,81,0,0.1)' : 'rgba(0,0,0,0.03)' ?>;border-left:5px solid <?= $eggStatus['pending_whole'] > 0 ? '#e65100' : '#888' ?>;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:14px">
        <div style="font-size:28px">⏳</div>
        <div>
            <div style="font-size:12px;color:<?= $eggStatus['pending_whole'] > 0 ? '#e65100' : 'var(--text-muted)' ?>;font-weight:700"><?= current_locale()==='am'?'ያልተላለፈ (በመጠባበቅ ላይ)':'Pending Store Transfer' ?></div>
            <div style="font-size:24px;font-weight:900;color:<?= $eggStatus['pending_whole'] > 0 ? '#bf360c' : 'inherit' ?>"><?= number_format($eggStatus['pending_whole']) ?></div>
            <div style="font-size:11px;color:var(--text-muted)"><?= $eggStatus['pending_whole'] > 0 ? (current_locale()==='am'?'ርክክብ ይጠብቃል':'Awaiting transfer') : (current_locale()==='am'?'ምንም የለም':'None pending') ?></div>
        </div>
    </div>

    <div style="flex:1;min-width:180px;background:rgba(21,101,192,0.08);border-left:5px solid #1565c0;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:14px">
        <div style="font-size:28px">📦</div>
        <div>
            <div style="font-size:12px;color:#1565c0;font-weight:700"><?= current_locale()==='am'?'ወደ መጋዘን የተላለፈ':'Transferred to Warehouse' ?></div>
            <div style="font-size:24px;font-weight:900;color:#0d47a1"><?= number_format($eggStatus['transferred_whole']) ?></div>
            <div style="font-size:11px;color:var(--text-muted)"><?= current_locale()==='am'?'በክምችት የተረጋገጠ':'Verified in stock' ?></div>
        </div>
    </div>

    <div style="flex:1;min-width:180px;background:rgba(198,40,40,0.08);border-left:5px solid #c62828;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:14px">
        <div style="font-size:28px">💔</div>
        <div>
            <div style="font-size:12px;color:#c62828;font-weight:700"><?= current_locale()==='am'?'የተሰበረ (ኪሳራ)':'Broken (Loss)' ?></div>
            <div style="font-size:24px;font-weight:900;color:#b71c1c"><?= number_format($eggStatus['total_broken']) ?></div>
            <div style="font-size:11px;color:var(--text-muted)"><?= current_locale()==='am'?'የተወገደ ብክነት':'Discarded' ?></div>
        </div>
    </div>
</div>

<!-- TWO-COLUMN WORKFLOW: 1. Entry Form | 2. Warehouse Daily Transfer Action -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(360px, 1fr));gap:20px;margin-bottom:24px">
    
    <!-- Left: Entry Form -->
    <div class="card">
        <h3 style="margin-top:0;display:flex;align-items:center;gap:8px">
            <span>➕</span>
            <span><?= current_locale()==='am'?'የመንጋ እንቁላል ምዝገባ':'Log Flock Egg Collection' ?></span>
        </h3>
        <?php if (empty($flocks)): ?>
            <p class="text-muted">⚠️ <?= current_locale()==='am'?'ምንም ንቁ መንጋ አልተገኘም።':'No active flocks found.' ?></p>
        <?php else: ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="record_egg_entry" value="1">
            <input type="hidden" name="collection_date" value="<?= h($selectedDate) ?>">

            <div class="form-group">
                <label><?= current_locale()==='am'?'የዶሮ መንጋ ይምረጡ *':'Select Flock *' ?></label>
                <select name="flock_id" required style="width:100%">
                    <option value="">— <?= current_locale()==='am'?'መንጋ ይምረጡ':'Select Flock' ?> —</option>
                    <?php foreach ($flocks as $f): ?>
                    <option value="<?= (int)$f['id'] ?>">
                        <?= h($f['house_name']) ?> — <?= h($f['breed']) ?>
                        (<?= current_locale()==='am'?'ህያው:':'Live:' ?> <?= number_format($f['live_count']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label><?= current_locale()==='am'?'የምዝገባ ክፍለ-ጊዜ (ዙር) *':'Session / Round *' ?></label>
                    <select name="round_no" required>
                        <option value="1">🌅 <?= current_locale()==='am'?'ጧት (ዙር 1)':'Morning (Round 1)' ?></option>
                        <option value="2">☀️ <?= current_locale()==='am'?'ከሰዓት (ዙር 2)':'Afternoon (Round 2)' ?></option>
                        <option value="3">🌙 <?= current_locale()==='am'?'ማታ (ዙር 3)':'Evening (Round 3)' ?></option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:2">
                    <label style="color:#2e7d32;font-weight:700">🟢 <?= current_locale()==='am'?'ሙሉ (ጥሩ) እንቁላል *':'Whole Eggs *' ?></label>
                    <input type="number" name="whole_count" min="0" value="0" required style="font-size:20px;font-weight:700;text-align:center" oninput="updateTotal()">
                    <small class="text-muted"><?= current_locale()==='am'?'ወደ መጋዘን የሚተላለፍ':'Good eggs for warehouse transfer' ?></small>
                </div>
                <div class="form-group" style="flex:1">
                    <label style="color:#c62828;font-weight:700">🔴 <?= current_locale()==='am'?'የተሰበረ (ኪሳራ)':'Broken' ?></label>
                    <input type="number" name="broken_count" min="0" value="0" style="font-size:20px;font-weight:700;text-align:center" oninput="updateTotal()">
                    <small class="text-muted"><?= current_locale()==='am'?'ኪሳራ':'Loss' ?></small>
                </div>
            </div>

            <div style="background:var(--hover, rgba(0,0,0,0.03));border-radius:8px;padding:10px 14px;margin-bottom:14px;text-align:center;border:1px solid var(--border)">
                <div style="font-size:12px;color:var(--text-muted);font-weight:600"><?= current_locale()==='am'?'ተቀባይነት ያለው ጥሩ እንቁላል':'Usable Whole Eggs' ?></div>
                <div style="font-size:26px;font-weight:900;color:#2e7d32" id="totalDisplay">0</div>
            </div>

            <button class="btn" type="submit" style="width:100%;font-size:15px;padding:10px;background:#2e7d32;border-color:#2e7d32;color:#fff">
                ✓ <?= current_locale()==='am'?'የመንጋውን እንቁላል መዝግብ':'Save Flock Collection' ?>
            </button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Right: Daily Warehouse Transfer (Matching Dairy's Milking Close & Transfer) -->
    <div class="card" style="border:2px solid <?= $eggStatus['pending_whole'] > 0 ? '#2e7d32' : 'var(--border)' ?>;background:<?= $eggStatus['pending_whole'] > 0 ? 'rgba(46,125,50,0.03)' : 'inherit' ?>">
        <h3 style="margin-top:0;display:flex;align-items:center;gap:8px">
            <span>📦</span>
            <span><?= current_locale()==='am'?'የዕለቱ ምርት ወደ መጋዘን ርክክብ':'Daily Store Transfer (BR-P02)' ?></span>
        </h3>
        
        <?php if ($eggStatus['pending_whole'] > 0): ?>
            <div style="background:#e8f5e9;border-left:4px solid #2e7d32;padding:12px 14px;border-radius:4px;margin-bottom:16px;color:#1b5e20">
                <strong><?= current_locale()==='am'?'የርክክብ ዝግጅት፦':'Ready for Warehouse Transfer:' ?></strong>
                <p style="margin:4px 0 0 0;font-size:13px">
                    <?= current_locale()==='am'
                        ? 'በዚህ ቀን የተሰበሰበ <strong>' . number_format($eggStatus['pending_whole']) . '</strong> ሙሉ ዕንቁላል በመጠባበቅ ላይ ይገኛል። ርክክቡን ሲያረጋግጡ በቀጥታ ወደ ዋናው መጋዘን ክምችት ገቢ ይሆናል።'
                        : '<strong>' . number_format($eggStatus['pending_whole']) . '</strong> good whole eggs ready to transfer into warehouse inventory.' ?>
                </p>
            </div>

            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="transfer_to_store" value="1">
                <input type="hidden" name="transfer_date" value="<?= h($selectedDate) ?>">

                <div class="form-group">
                    <label><?= current_locale()==='am'?'ተቀባይ መጋዘን *':'Receiving Warehouse *' ?></label>
                    <select name="warehouse_id" required style="width:100%">
                        <?php foreach ($warehouses as $w): ?>
                        <option value="<?= (int)$w['id'] ?>"><?= h($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><?= current_locale()==='am'?'የማስተላለፊያ ማስታወሻ (አማራጭ)':'Transfer Notes (Optional)' ?></label>
                    <input type="text" name="notes" placeholder="<?= current_locale()==='am'?'ምሳሌ፦ የጧትና ከሰዓት የተጠቃለለ ምርት':'e.g. Full day collection' ?>">
                </div>

                <div style="background:var(--hover, rgba(0,0,0,0.03));border-radius:8px;padding:12px;margin-bottom:16px;text-align:center;border:1px solid var(--border)">
                    <div style="font-size:12px;color:var(--text-muted);font-weight:600"><?= current_locale()==='am'?'አሁን ወደ መጋዘን የሚተላለፈው ጠቅላላ ዕንቁላል':'Total Eggs to Transfer' ?></div>
                    <div style="font-size:32px;font-weight:900;color:#1b5e20"><?= number_format($eggStatus['pending_whole']) ?></div>
                </div>

                <button class="btn" type="submit" 
                        onclick="return confirm('<?= current_locale() === 'am' ? 'የዕለቱን ' . number_format($eggStatus['pending_whole']) . ' ሙሉ ዕንቁላሎች ወደ መጋዘን መላክ ይፈልጋሉ?' : 'Transfer ' . number_format($eggStatus['pending_whole']) . ' eggs to Warehouse?' ?>');"
                        style="width:100%;font-size:16px;padding:12px;background:#2e7d32;border-color:#2e7d32;color:#fff;font-weight:700">
                    📦 <?= current_locale()==='am'?'የዕለቱን ዕንቁላል ወደ መጋዘን አስረክብ':'Transfer Today\'s Eggs to Store' ?>
                </button>
            </form>

        <?php elseif ($eggStatus['is_fully_transferred']): ?>
            <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;padding:24px;text-align:center;color:#1b5e20">
                <div style="font-size:48px;margin-bottom:8px">✅</div>
                <h4 style="margin:0 0 6px 0;font-size:18px"><?= current_locale()==='am'?'ምርቱ ሙሉ በሙሉ ተላልፏል!':'All Eggs Transferred!' ?></h4>
                <p style="margin:0;font-size:13px;color:#2e7d32">
                    <?= current_locale()==='am'
                        ? 'የዚህ ቀን የተሰበሰበ <strong>' . number_format($eggStatus['transferred_whole']) . '</strong> ሙሉ ዕንቁላል ወደ መጋዘን ገቢ ሆኗል፤ ክምችቱም ተስተካክሏል።'
                        : 'All ' . number_format($eggStatus['transferred_whole']) . ' good eggs for this date have been received into Warehouse inventory.' ?>
                </p>
            </div>
        <?php else: ?>
            <div style="background:var(--hover, rgba(0,0,0,0.03));border-radius:8px;padding:30px 16px;text-align:center;color:var(--text-muted)">
                <div style="font-size:40px;margin-bottom:8px">ℹ️</div>
                <div style="font-size:14px;font-weight:600"><?= current_locale()==='am'?'ለዚህ ቀን የተመዘገበ ዕንቁላል የለም':'No eggs recorded for this date yet' ?></div>
                <div style="font-size:12px;margin-top:4px"><?= current_locale()==='am'?'በግራ በኩል ባለው ቅጽ የእንቁላል ምዝገባ ሲያደርጉ እዚህ ርክክብ ማድረግ ይችላሉ።':'Record flock collections on the left first, then transfer them here.' ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Collections Table for Selected Date -->
<?php if (!empty($dayEntries)): ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
        <h3 style="margin:0">📋 <?= current_locale()==='am'?'የቀኑ ዝርዝር ምዝገባዎች (' . eth_date($selectedDate) . ')':'Collections Log for ' . eth_date($selectedDate) ?></h3>
        <span class="text-muted" style="font-size:13px"><?= count($dayEntries) ?> <?= current_locale()==='am'?'ምዝገባዎች':'records' ?></span>
    </div>

    <table>
        <tr>
            <th><?= current_locale()==='am'?'ቤት / መንጋ':'House / Flock' ?></th>
            <th><?= current_locale()==='am'?'ክፍለ-ጊዜ':'Session' ?></th>
            <th style="color:#2e7d32"><?= current_locale()==='am'?'ሙሉ (ጥሩ)':'Whole' ?></th>
            <th style="color:#c62828"><?= current_locale()==='am'?'የተሰበረ (ኪሳራ)':'Broken' ?></th>
            <th><?= current_locale()==='am'?'የመጋዘን ርክክብ ሁኔታ':'Store Status' ?></th>
            <th><?= current_locale()==='am'?'መዝጋቢ':'Recorded By' ?></th>
        </tr>
        <?php foreach ($dayEntries as $e):
            $sessionLabel = match((int)$e['round_no']) {
                1 => (current_locale()==='am'?'🌅 ጧት':'🌅 Morning'),
                2 => (current_locale()==='am'?'☀️ ከሰዓት':'☀️ Afternoon'),
                3 => (current_locale()==='am'?'🌙 ማታ':'🌙 Evening'),
                default => '#' . (int)$e['round_no']
            };
            $isTransferred = !empty($e['transferred_at']);
        ?>
        <tr>
            <td><strong><?= h($e['house_name']) ?></strong> — <?= h($e['breed']) ?></td>
            <td><span class="badge active"><?= h($sessionLabel) ?></span></td>
            <td style="color:#2e7d32;font-weight:700"><?= number_format($e['whole_count']) ?></td>
            <td style="color:#c62828;font-weight:700"><?= number_format($e['broken_count']) ?></td>
            <td>
                <?php if ($isTransferred): ?>
                    <span class="badge approved" style="background:#e8f5e9;color:#2e7d32;font-weight:700;border:1px solid #a5d6a7">
                        ✓ <?= current_locale()==='am'?'ወደ መጋዘን ተላልፏል':'Transferred' ?>
                    </span>
                    <small class="text-muted" style="display:block;font-size:10px"><?= date('H:i', strtotime($e['transferred_at'])) ?></small>
                <?php else: ?>
                    <span class="badge pending" style="background:#fff3e0;color:#e65100;font-weight:700;border:1px solid #ffcc80">
                        ⏳ <?= current_locale()==='am'?'ርክክብ ይጠብቃል':'Pending Transfer' ?>
                    </span>
                <?php endif; ?>
            </td>
            <td class="text-muted" style="font-size:12px"><?= h($e['recorder_name'] ?: 'Staff') ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="border-top:2px solid var(--border);background:var(--hover, rgba(0,0,0,0.02))">
            <td colspan="2"><strong><?= current_locale()==='am'?'የቀኑ ጠቅላላ ድምር':'Daily Total' ?></strong></td>
            <td style="color:#2e7d32;font-weight:900;font-size:16px"><?= number_format($eggStatus['total_whole']) ?></td>
            <td style="color:#c62828;font-weight:900;font-size:16px"><?= number_format($eggStatus['total_broken']) ?></td>
            <td colspan="2">
                <strong>
                    <?= $eggStatus['pending_whole'] > 0 
                        ? '<span style="color:#e65100">⏳ ' . number_format($eggStatus['pending_whole']) . (current_locale()==='am'?' ያልተላለፈ':' pending') . '</span>'
                        : '<span style="color:#2e7d32">✓ ' . (current_locale()==='am'?'ሁሉም ተላልፏል':'All transferred') . '</span>' ?>
                </strong>
            </td>
        </tr>
    </table>
</div>
<?php endif; ?>

<script>
function updateTotal() {
    const w = parseInt(document.querySelector('[name=whole_count]').value) || 0;
    document.getElementById('totalDisplay').textContent = w.toLocaleString();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
