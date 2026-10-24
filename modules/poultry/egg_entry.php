<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'poultry_manager', 'field_worker']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $flockId  = (int) $_POST['flock_id'];
        $date     = trim($_POST['collection_date'] ?? date('Y-m-d'));
        $whole    = max(0, (int)$_POST['whole_count']);
        $cracked  = max(0, (int)$_POST['cracked_count']);
        $broken   = max(0, (int)$_POST['broken_count']);
        $round    = max(1, (int)($_POST['round_no'] ?? 1));
        $userId   = (int) current_user()['id'];

        if ($flockId <= 0) throw new RuntimeException('Please select a flock.');
        if ($whole + $cracked + $broken === 0) throw new RuntimeException('At least one egg count must be greater than zero.');

        // Check for duplicate entry (fast-path UX feedback only — the
        // authoritative guard against a race between two near-simultaneous
        // submissions is the UNIQUE constraint added in
        // migration_v7_egg_collection_unique.sql, caught below).
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
                "INSERT INTO poultry_egg_collections (flock_id, collection_date, round_no, whole_count, cracked_count, broken_count, recorded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$flockId, $date, $round, $whole, $cracked, $broken, $userId]);
        } catch (PDOException $e) {
            // 23000 = integrity constraint violation, i.e. the unique key
            // rejected a second near-simultaneous entry for the same
            // flock/date/round.
            if ($e->getCode() === '23000') {
                throw new RuntimeException(
                    current_locale() === 'am'
                        ? "ይህ መንጋ ለዚህ ቀን እና ዙር #{$round} አስቀድሞ ተመዝግቧል።"
                        : "Egg entry already exists for this flock on {$date} round #{$round}."
                );
            }
            throw $e;
        }
        $total = max(0, $whole - $broken);
        log_activity('poultry', 'create', 'poultry_egg_collections', (int)db()->lastInsertId(), "Flock #{$flockId}: {$total} eggs (whole:{$whole}, cracked:{$cracked}, broken:{$broken})");
        flash('✓ ' . (current_locale() === 'am' ? "የዛሬ እንቁላል ({$total}) ተመዝግቧል!" : "Egg collection recorded! Total: {$total} eggs."));
        header('Location: ' . BASE_URL . '/modules/poultry/egg_entry.php');
        exit;
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

// Today's entries
$todayEntries = db()->query(
    "SELECT e.*, f.breed, ph.name AS house_name
     FROM poultry_egg_collections e
     JOIN poultry_flocks f ON f.id = e.flock_id
     JOIN poultry_houses ph ON ph.id = f.poultry_house_id
     WHERE e.collection_date = CURDATE()
     ORDER BY e.id DESC"
)->fetchAll();

$todayTotal = array_sum(array_map(fn($e) => (int)$e['whole_count'], $todayEntries));
$todayBroken = array_sum(array_map(fn($e) => (int)$e['broken_count'], $todayEntries));
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 class="mt-0" style="margin-bottom:4px">🥚 <?= current_locale()==='am'?'የዕለቱ እንቁላል ምዝገባ':'Today\'s Egg Collection Entry' ?></h2>
        <p class="text-muted" style="margin:0"><?= current_locale()==='am'?'የእያንዳንዱ መንጋ ሙሉ (ጥሩ) እንቁላል እና የተሰበረ (ኪሳራ) መመዝገቢያ':'Record whole eggs and broken eggs per flock per session.' ?></p>
    </div>
    <a href="<?= BASE_URL ?>/modules/poultry/dashboard.php" class="btn secondary">← <?= current_locale()==='am'?'ወደ ዶሮ እርባታ ዳሽቦርድ':'Poultry Dashboard' ?></a>
</div>

<?php if ($todayTotal > 0 || $todayBroken > 0): ?>
<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap">
    <div style="flex:1;min-width:200px;background:rgba(46,125,50,0.1);border-left:5px solid #2e7d32;border-radius:8px;padding:14px 18px;display:flex;align-items:center;gap:16px">
        <div style="font-size:32px">🥚</div>
        <div>
            <div style="font-size:13px;color:#2e7d32;font-weight:700"><?= current_locale()==='am'?'የዛሬ ሙሉ (ጥሩ) እንቁላል':'Today\'s Whole Eggs' ?></div>
            <div style="font-size:28px;font-weight:900;color:#1b5e20"><?= number_format($todayTotal) ?></div>
        </div>
    </div>
    <?php if ($todayBroken > 0): ?>
    <div style="flex:1;min-width:200px;background:rgba(198,40,40,0.1);border-left:5px solid #c62828;border-radius:8px;padding:14px 18px;display:flex;align-items:center;gap:16px">
        <div style="font-size:32px">💔</div>
        <div>
            <div style="font-size:13px;color:#c62828;font-weight:700"><?= current_locale()==='am'?'የተሰበረ (ኪሳራ) እንቁላል':'Broken Eggs (Loss)' ?></div>
            <div style="font-size:28px;font-weight:900;color:#b71c1c"><?= number_format($todayBroken) ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Entry Form -->
<div class="card" style="max-width:600px;margin-bottom:24px">
    <h3 style="margin-top:0">➕ <?= current_locale()==='am'?'አዲስ የእንቁላል ምዝገባ':'New Egg Entry' ?></h3>
    <?php if (empty($flocks)): ?>
        <p class="text-muted">⚠️ <?= current_locale()==='am'?'ምንም ንቁ መንጋ አልተመዘገበም።':'No active flocks found.' ?></p>
    <?php else: ?>
    <form method="post">
        <?= csrf_field() ?>

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

        <input type="hidden" name="round_no" value="1">
        <div class="form-group">
            <label><?= current_locale()==='am'?'ቀን (የኢትዮጵያ ቀን: ' . eth_date(date('Y-m-d')) . ') *':'Collection Date *' ?></label>
            <input type="date" name="collection_date" value="<?= date('Y-m-d') ?>" required max="<?= date('Y-m-d') ?>">
        </div>

        <div class="form-row">
            <div class="form-group" style="flex:2">
                <label style="color:#2e7d32;font-weight:700">🟢 <?= current_locale()==='am'?'ሙሉ (ጥሩ) እንቁላል *':'Whole Eggs *' ?></label>
                <input type="number" name="whole_count" min="0" value="0" required style="font-size:20px;font-weight:700;text-align:center" oninput="updateTotal()">
                <small class="text-muted"><?= current_locale()==='am'?'ለሽያጭና ለምርት የሚቆጠር':'Counted for production and sale' ?></small>
            </div>
            <input type="hidden" name="cracked_count" value="0">
            <div class="form-group" style="flex:1">
                <label style="color:#c62828;font-weight:700">🔴 <?= current_locale()==='am'?'የተሰበረ (ኪሳራ)':'Broken (Loss)' ?></label>
                <input type="number" name="broken_count" min="0" value="0" style="font-size:20px;font-weight:700;text-align:center" oninput="updateTotal()">
                <small class="text-muted"><?= current_locale()==='am'?'ከጠቅላላው አይደመርም':'Excluded from total' ?></small>
            </div>
        </div>

        <div style="background:var(--hover, rgba(0,0,0,0.03));border-radius:8px;padding:12px 16px;margin-bottom:16px;text-align:center;border:1px solid var(--border)">
            <div style="font-size:13px;color:var(--text-muted);font-weight:600"><?= current_locale()==='am'?'ጠቅላላ ተቀባይነት ያለው እንቁላል':'Total Usable Eggs' ?></div>
            <div style="font-size:32px;font-weight:900;color:#2e7d32" id="totalDisplay">0</div>
        </div>

        <button class="btn" type="submit" style="width:100%;font-size:16px;padding:12px;background:#2e7d32;border-color:#2e7d32">
            ✓ <?= current_locale()==='am'?'የዕለቱን እንቁላል መዝግብ':'Save Egg Collection' ?>
        </button>
    </form>
    <?php endif; ?>
</div>

<!-- Today's Entries -->
<?php if (!empty($todayEntries)): ?>
<div class="card">
    <h3 style="margin-top:0">📋 <?= current_locale()==='am'?'የዛሬ ምዝገባዎች (' . eth_date(date('Y-m-d')) . ')':'Today\'s Entries' ?></h3>
    <table>
        <tr>
            <th><?= current_locale()==='am'?'ቤት / መንጋ':'House / Flock' ?></th>
            <th><?= current_locale()==='am'?'ክፍለ-ጊዜ':'Session' ?></th>
            <th style="color:#2e7d32"><?= current_locale()==='am'?'ሙሉ (ጥሩ)':'Whole' ?></th>
            <th style="color:#c62828"><?= current_locale()==='am'?'የተሰበረ (ኪሳራ)':'Broken' ?></th>
            <th><?= current_locale()==='am'?'ጠቅላላ ጥሩ':'Total Usable' ?></th>
        </tr>
        <?php foreach ($todayEntries as $e):
            $sessionLabel = match((int)$e['round_no']) {
                1 => (current_locale()==='am'?'ጧት':'Morning'),
                2 => (current_locale()==='am'?'ከሰዓት':'Afternoon'),
                3 => (current_locale()==='am'?'ማታ':'Evening'),
                default => '#' . (int)$e['round_no']
            };
        ?>
        <tr>
            <td><strong><?= h($e['house_name']) ?></strong> — <?= h($e['breed']) ?></td>
            <td><span class="badge active"><?= h($sessionLabel) ?></span></td>
            <td style="color:#2e7d32;font-weight:700"><?= number_format($e['whole_count']) ?></td>
            <td style="color:#c62828;font-weight:700"><?= number_format($e['broken_count']) ?></td>
            <td><strong style="color:#2e7d32;font-size:15px"><?= number_format($e['whole_count']) ?></strong></td>
        </tr>
        <?php endforeach; ?>
        <tr style="border-top:2px solid var(--border)">
            <td colspan="2"><strong><?= current_locale()==='am'?'የዛሬ ጠቅላላ':'Daily Total' ?></strong></td>
            <td style="color:#2e7d32;font-weight:900"><?= number_format($todayTotal) ?></td>
            <td style="color:#c62828;font-weight:900"><?= number_format($todayBroken) ?></td>
            <td><strong style="font-size:18px;color:#2e7d32"><?= number_format($todayTotal) ?></strong></td>
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
