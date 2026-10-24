<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'dairy_manager']);

$id = (int) ($_GET['id'] ?? 0);
$stmtCow = db()->prepare("SELECT * FROM dairy_cows WHERE id = ?");
$stmtCow->execute([$id]);
$cow = $id > 0 ? $stmtCow->fetch() : null;
if (!$cow) {
    $firstId = db()->query("SELECT id FROM dairy_cows ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($firstId) {
        $id = (int)$firstId;
        $stmtCow->execute([$id]);
        $cow = $stmtCow->fetch();
    } else {
        flash('No cow records found.', 'error');
        header('Location: ' . BASE_URL . '/modules/dairy/cows.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload_cow_photo'])) {
        csrf_verify();
        try {
            $photo = save_uploaded_photo('cow_photo', 'cows');
            if ($photo) {
                db()->prepare("UPDATE dairy_cows SET photo_path = ? WHERE id = ?")->execute([$photo, $id]);
                flash(current_locale() === 'am' ? '✓ የላሟ ፎቶ በተሳካ ሁኔታ ተጭኗል!' : 'Cow photo uploaded successfully!');
            }
            header("Location: " . BASE_URL . "/modules/dairy/cow_view.php?id={$id}");
            exit;
        } catch (Throwable $e) {
            flash('Error: ' . $e->getMessage(), 'error');
        }
    }

    if (isset($_POST['exit_type'])) {
        csrf_verify();
        try {
            cow_record_exit($id, $_POST);
            flash('Exit recorded — cow lifecycle closed.');
            header("Location: " . BASE_URL . "/modules/dairy/cow_view.php?id={$id}");
            exit;
        } catch (Throwable $e) {
            flash('Error: ' . $e->getMessage(), 'error');
        }
    }

    if (isset($_POST['record_breeding'])) {
        csrf_verify();
        try {
            $expected = $_POST['expected_calving_date'] ?: null;
            cow_record_breeding($id, $_POST['method'], $_POST['sire_reference'] ?: null, $_POST['breeding_date'], $expected);
            flash('Breeding attempt registered successfully.');
            header("Location: " . BASE_URL . "/modules/dairy/cow_view.php?id={$id}");
            exit;
        } catch (Throwable $e) {
            flash('Error: ' . $e->getMessage(), 'error');
        }
    }

    if (isset($_POST['record_calving'])) {
        csrf_verify();
        try {
            $calfData = null;
            if ($_POST['outcome'] === 'live_birth') {
                $calfData = [
                    'tag_number' => trim($_POST['calf_tag_number'] ?? ''),
                    'name' => trim($_POST['calf_name'] ?? ''),
                    'sex' => $_POST['calf_sex'] ?? 'female',
                    'breed' => trim($_POST['calf_breed'] ?? '')
                ];
                if (empty($calfData['tag_number'])) {
                    throw new RuntimeException("Calf Tag Number is required for live births.");
                }
            }
            cow_record_calving((int)$_POST['breeding_record_id'], $id, $_POST['calving_date'], $_POST['outcome'], $_POST['notes'] ?: null, $calfData);
            flash('Calving recorded successfully.');
            header("Location: " . BASE_URL . "/modules/dairy/cow_view.php?id={$id}");
            exit;
        } catch (Throwable $e) {
            flash('Error: ' . $e->getMessage(), 'error');
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';

$yields = db()->prepare(
    "SELECT e.yield_liters, e.created_at, s.session_date, s.session_type
     FROM dairy_milk_yield_entries e JOIN dairy_milking_sessions s ON s.id = e.milking_session_id
     WHERE e.cow_id = ? ORDER BY s.session_date DESC LIMIT 10"
);
$yields->execute([$id]);
$yields = $yields->fetchAll();

$stmtBreeding = db()->prepare(
    "SELECT b.*, u.name AS worker_name 
     FROM dairy_breeding_records b 
     JOIN users u ON u.id = b.recorded_by 
     WHERE b.cow_id = ? 
     ORDER BY b.breeding_date DESC"
);
$stmtBreeding->execute([$id]);
$breedingHistory = $stmtBreeding->fetchAll();

$stmtCalving = db()->prepare(
    "SELECT c.*, calf.tag_number AS calf_tag 
     FROM dairy_calving_records c 
     LEFT JOIN dairy_cows calf ON calf.id = c.calf_id 
     WHERE c.dam_id = ? 
     ORDER BY c.calving_date DESC"
);
$stmtCalving->execute([$id]);
$calvingHistory = $stmtCalving->fetchAll();

$active = cow_is_active($cow);
$ourBulls = db()->query("SELECT id, tag_number, name, breed FROM dairy_cows WHERE sex = 'male' AND lifecycle_status NOT IN ('sold','deceased') ORDER BY tag_number")->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <h2 class="mt-0" style="margin:0"><?= h(t('dairy.cow_profile', 'Cow Profile')) ?>: <?= h($cow['tag_number']) ?> <?= $cow['name'] ? '— ' . h($cow['name']) : '' ?> <span class="badge <?= h($cow['lifecycle_status']) ?>"><?= h(t('dairy.lifecycle_' . $cow['lifecycle_status'], $cow['lifecycle_status'])) ?></span></h2>
    <a href="<?= BASE_URL ?>/modules/dairy/cows.php" class="btn secondary">&larr; <?= h(t('common.back', 'Back to Cow List')) ?></a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
    <!-- Left Column: Details & Yields -->
    <div>
        <div class="card">
            <div style="display:flex;gap:16px;align-items:center;margin-bottom:14px">
                <div style="width:90px;height:90px;border-radius:8px;border:2px solid #ccc;overflow:hidden;background:#f5f5f5;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <?php if (!empty($cow['photo_path'])): ?>
                        <img src="<?= h($cow['photo_path']) ?>" style="width:100%;height:100%;object-fit:cover" alt="Cow Photo">
                    <?php else: ?>
                        <span style="font-size:36px">🐄</span>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 style="margin:0 0 4px 0"><?= h($cow['tag_number']) ?> <?= $cow['name'] ? '('.h($cow['name']).')' : '' ?></h3>
                    <form method="post" enctype="multipart/form-data" style="display:flex;gap:6px;align-items:center"><?= csrf_field() ?>
                        <input type="hidden" name="upload_cow_photo" value="1">
                        <label class="btn secondary" style="padding:3px 8px;font-size:11px;cursor:pointer">
                            📷 <?= current_locale() === 'am' ? 'ፎቶ ቀይር / ጫን' : 'Upload Photo' ?>
                            <input type="file" name="cow_photo" accept="image/*" onchange="this.form.submit()" style="display:none">
                        </label>
                    </form>
                </div>
            </div>
            <table>
                <tr><td><?= h(t('common.name', 'Name')) ?></td><td><?= h($cow['name'] ?: '—') ?></td></tr>
                <tr><td><?= h(t('dairy.sex', 'Sex')) ?></td><td><?= h($cow['sex'] === 'female' ? t('dairy.female', 'Female') : t('dairy.male', 'Male')) ?></td></tr>
                <tr><td><?= h(t('dairy.breed', 'Breed')) ?></td><td><?= h($cow['breed'] ?: '—') ?></td></tr>
                <tr><td><?= h(t('dairy.dob', 'Date of Birth')) ?></td><td><?= h($cow['date_of_birth'] ? eth_date($cow['date_of_birth']) : '—') ?></td></tr>
                <tr><td><?= h(t('dairy.source', 'Source')) ?></td><td><?= h($cow['source'] === 'born_on_farm' ? t('dairy.born_on_farm', 'Born on Farm') : ($cow['source'] === 'purchased' ? t('dairy.purchased', 'Purchased') : '—')) ?></td></tr>
            </table>
            <div style="display:flex;gap:6px;margin-top:12px;flex-wrap:wrap">
                <a href="<?= BASE_URL ?>/modules/dairy/cow_history.php?id=<?= $id ?>&tab=finances"    class="btn" style="padding:4px 10px;font-size:12px;background:#1565c0;border-color:#1565c0">💰 <?= current_locale() === 'am' ? 'የፋይናንስና ትርፍ ታሪክ (P&L)' : 'Financial P&L' ?></a>
                <a href="<?= BASE_URL ?>/modules/dairy/cow_history.php?id=<?= $id ?>&tab=health"      class="btn secondary" style="padding:4px 10px;font-size:12px">🩺 <?= h(t('nav.health_records', 'Health Records')) ?></a>
                <a href="<?= BASE_URL ?>/modules/dairy/cow_history.php?id=<?= $id ?>&tab=vaccination" class="btn secondary" style="padding:4px 10px;font-size:12px">💉 <?= h(t('nav.vaccinations', 'Vaccinations')) ?></a>
                <?php if ($cow['sex'] === 'female'): ?>
                <a href="<?= BASE_URL ?>/modules/dairy/cow_history.php?id=<?= $id ?>&tab=breeding"    class="btn secondary" style="padding:4px 10px;font-size:12px">🐄 <?= current_locale() === 'am' ? 'የማዳቀል ታሪክ' : 'Breeding' ?></a>
                <a href="<?= BASE_URL ?>/modules/dairy/cow_history.php?id=<?= $id ?>&tab=calving"     class="btn secondary" style="padding:4px 10px;font-size:12px">🐣 <?= current_locale() === 'am' ? 'የመውለድ ታሪክ' : 'Calving' ?></a>
                <?php endif; ?>
            </div>
        </div>

        <?php $cowFinQuick = cow_financial_summary($id); ?>
        <div class="card" style="border-left:4px solid <?= $cowFinQuick['net_profit'] >= 0 ? '#2e7d32' : '#c62828' ?>;padding:12px 16px;margin-bottom:20px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <strong style="font-size:13px">💰 <?= current_locale() === 'am' ? 'የዚህች ላም የትርፋማነት ማጠቃለያ' : 'Cow Profitability Summary' ?></strong>
                <span class="badge" style="background:<?= $cowFinQuick['net_profit'] >= 0 ? '#e8f5e9;color:#2e7d32' : '#ffebee;color:#c62828' ?>">
                    <?= $cowFinQuick['net_profit'] >= 0 ? '✓ አትራፊ' : '⚠️ ኪሳራ' ?> (ROI: <?= $cowFinQuick['roi_percent'] ?>%)
                </span>
            </div>
            <div style="display:flex;justify-content:space-between;gap:10px;font-size:12px;margin-bottom:6px;flex-wrap:wrap">
                <span>🥛 የወተት ገቢ፦ <strong><?= number_format($cowFinQuick['total_milk_revenue'], 0) ?> ETB</strong></span>
                <span>💸 የወጣ ወጪ፦ <strong><?= number_format($cowFinQuick['total_expenses'], 0) ?> ETB</strong></span>
                <span>📈 የተጣራ፦ <strong style="color:<?= $cowFinQuick['net_profit'] >= 0 ? '#2e7d32' : '#c62828' ?>"><?= number_format($cowFinQuick['net_profit'], 0) ?> ETB</strong></span>
            </div>
            <a href="<?= BASE_URL ?>/modules/dairy/cow_history.php?id=<?= $id ?>&tab=finances" style="font-size:11.5px;color:var(--primary);text-decoration:underline">
                → <?= current_locale() === 'am' ? 'ሙሉ የገቢ፣ የመኖና የህክምና ወጪ ዝርዝር ተመልከት' : 'View full financial trend & cost breakdown' ?>
            </a>
        </div>

        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <h3 style="margin:0"><?= h(t('dairy.milking_entry', 'Recent Milk Yield')) ?></h3>
                <a href="<?= BASE_URL ?>/modules/dairy/cow_milk_history.php?id=<?= $id ?>" class="btn secondary" style="padding:4px 10px;font-size:12px"><?= h(t('common.view_all', 'View All')) ?> &rarr;</a>
            </div>
            <table>
                <tr>
                    <th><?= h(t('common.date', 'Date')) ?></th>
                    <th><?= current_locale() === 'am' ? 'ክፍለ ጊዜ' : 'Session' ?></th>
                    <th><?= h(t('dairy.total_liters', 'Liters')) ?></th>
                </tr>
                <?php foreach ($yields as $y): 
                    $sessName = match($y['session_type']) {
                        'morning' => (current_locale() === 'am' ? 'የጠዋት' : 'Morning'),
                        'afternoon' => (current_locale() === 'am' ? 'የከሰዓት' : 'Afternoon'),
                        'evening' => (current_locale() === 'am' ? 'የምሽት' : 'Evening'),
                        default => ucfirst($y['session_type'])
                    };
                ?>
                    <tr><td><?= h(eth_date($y['session_date'])) ?></td><td><?= h($sessName) ?></td><td><?= h($y['yield_liters']) ?> L</td></tr>
                <?php endforeach; ?>
                <?php if (empty($yields)): ?><tr><td colspan="3" class="text-muted"><?= h(t('common.no_records', 'No milking records yet.')) ?></td></tr><?php endif; ?>
            </table>
        </div>
    </div>

    <!-- Right Column: Breeding & Calving History -->
    <div>
        <?php if ($cow['sex'] === 'female'): ?>
            <!-- Breeding History Card -->
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                    <h3 style="margin:0"><?= current_locale() === 'am' ? 'የማዳቀል ታሪክ' : 'Breeding History' ?></h3>
                    <a href="<?= BASE_URL ?>/modules/dairy/cow_history.php?id=<?= $id ?>&tab=breeding" class="btn secondary" style="padding:4px 10px;font-size:12px"><?= h(t('common.view_all', 'View All')) ?> &rarr;</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th><?= current_locale() === 'am' ? 'የተዳቀለበት ቀን' : 'Breeding Date' ?></th>
                            <th><?= current_locale() === 'am' ? 'ዘዴ' : 'Method' ?></th>
                            <th><?= current_locale() === 'am' ? 'የኮርማ ቁጥር' : 'Sire Ref' ?></th>
                            <th><?= current_locale() === 'am' ? 'የሚጠበቅ የመውለጃ ቀን' : 'Exp Calving' ?></th>
                            <th><?= current_locale() === 'am' ? 'የመዘገበው' : 'Recorded By' ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($breedingHistory as $b): ?>
                            <tr>
                                <td><?= h(eth_date($b['breeding_date'])) ?></td>
                                <td><span class="badge"><?= $b['method'] === 'artificial_insemination' ? (current_locale() === 'am' ? 'ሰው ሰራሽ' : 'AI') : (current_locale() === 'am' ? 'ተፈጥሯዊ' : 'Natural') ?></span></td>
                                <td><?= h($b['sire_reference'] ?: '—') ?></td>
                                <td><?= h($b['expected_calving_date'] ? eth_date($b['expected_calving_date']) : '—') ?></td>
                                <td><?= h($b['worker_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($breedingHistory)): ?>
                            <tr><td colspan="5" class="text-muted"><?= h(t('common.no_records', 'No breeding records registered.')) ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Calving History Card -->
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                    <h3 style="margin:0"><?= current_locale() === 'am' ? 'የመውለድ ታሪክ' : 'Calving History' ?></h3>
                    <a href="<?= BASE_URL ?>/modules/dairy/cow_history.php?id=<?= $id ?>&tab=calving" class="btn secondary" style="padding:4px 10px;font-size:12px"><?= h(t('common.view_all', 'View All')) ?> &rarr;</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th><?= current_locale() === 'am' ? 'የተወለደበት ቀን' : 'Calving Date' ?></th>
                            <th><?= current_locale() === 'am' ? 'ውጤት' : 'Outcome' ?></th>
                            <th><?= current_locale() === 'am' ? 'የጥጃው ታግ' : 'Calf Tag' ?></th>
                            <th><?= h(t('common.notes', 'Notes')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calvingHistory as $c): 
                            $outText = match($c['outcome']) {
                                'live_birth' => (current_locale() === 'am' ? 'በህይወት ተወልዷል' : 'Live Birth'),
                                'stillbirth' => (current_locale() === 'am' ? 'ሞቶ ተወልዷል' : 'Stillbirth'),
                                default => (current_locale() === 'am' ? 'ችግር አጋጥሟል' : 'Complication')
                            };
                        ?>
                            <tr>
                                <td><?= h(eth_date($c['calving_date'])) ?></td>
                                <td><span class="badge <?= $c['outcome'] === 'live_birth' ? 'milking' : 'sold' ?>"><?= h($outText) ?></span></td>
                                <td>
                                    <?php if ($c['calf_id'] && $c['calf_tag']): ?>
                                        <a href="<?= BASE_URL ?>/modules/dairy/cow_view.php?id=<?= $c['calf_id'] ?>"><code><?= h($c['calf_tag']) ?></code></a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= h($c['notes'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($calvingHistory)): ?>
                            <tr><td colspan="4" class="text-muted"><?= h(t('common.no_records', 'No calving records registered.')) ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Forms Section -->
<?php if ($active): ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
        <?php if ($cow['sex'] === 'female'): ?>
            <!-- Add Breeding Record -->
            <div class="card">
                <h3><?= current_locale() === 'am' ? 'የማዳቀል ሙከራ መዝግብ' : 'Record Breeding Attempt' ?></h3>
                <form method="post" id="breedingForm">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label><?= current_locale() === 'am' ? 'የማዳቀል ዘዴ *' : 'Breeding Method *' ?></label>
                        <select name="method" required>
                            <option value="natural"><?= current_locale() === 'am' ? 'ተፈጥሯዊ ማዳቀል (በበሬ)' : 'Natural Mating' ?></option>
                            <option value="artificial_insemination"><?= current_locale() === 'am' ? 'ሰው ሰራሽ ማዳቀል (AI)' : 'Artificial Insemination' ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><?= current_locale() === 'am' ? 'የበሬው ምንጭ *' : 'Sire Source *' ?></label>
                        <select name="sire_source" id="sire_source_select" onchange="toggleSireSource(this.value)">
                            <option value="internal"><?= current_locale() === 'am' ? 'የእኛ እርሻ በሬ (ከእርሻችን ዝርዝር)' : 'Our Farm Bull' ?></option>
                            <option value="external"><?= current_locale() === 'am' ? 'የውጭ በሬ / የውጭ ዘር' : 'External Bull / Semen' ?></option>
                        </select>
                    </div>

                    <!-- Internal Bull Dropdown -->
                    <div class="form-group" id="internal_bull_group">
                        <label><?= current_locale() === 'am' ? 'የእኛን በሬ ይምረጡ' : 'Select Our Bull' ?></label>
                        <select id="internal_bull_ref">
                            <option value="">— <?= current_locale() === 'am' ? 'በሬ ይምረጡ' : 'Select' ?> —</option>
                            <?php foreach ($ourBulls as $b): ?>
                                <option value="ኮርማ #<?= h($b['tag_number']) ?> (<?= h($b['name'] ?: $b['breed']) ?>)">
                                    <?= h($b['tag_number']) ?> — <?= h($b['name'] ?: 'ያልተሰየመ') ?> (<?= h($b['breed'] ?: 'ዝርያ') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- External Bull Input -->
                    <div id="external_bull_group" style="display:none">
                        <div class="form-group">
                            <label><?= current_locale() === 'am' ? 'የውጭ በሬ መለያ / ስም' : 'External Bull Tag / Name' ?></label>
                            <input type="text" id="external_bull_ref" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ የደብረዘይት ኮርማ DZ-402' : 'e.g. Bull DZ-402' ?>">
                        </div>
                        <div class="form-group">
                            <label><?= current_locale() === 'am' ? 'የበሬው ዝርያ (አማራጭ)' : 'Sire Breed (Optional)' ?></label>
                            <input type="text" id="external_sire_breed" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ ቦራን፣ ሆልስታይን' : 'e.g. Boran, Holstein' ?>">
                        </div>
                    </div>
                    <input type="hidden" name="sire_reference" id="sire_reference_hidden" value="">

                    <div class="form-row">
                        <div class="form-group">
                            <label><?= current_locale() === 'am' ? 'የተዳቀለበት ቀን *' : 'Breeding Date *' ?></label>
                            <input type="date" name="breeding_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?= current_locale() === 'am' ? 'የሚጠበቅ የመውለጃ ቀን (አማራጭ)' : 'Expected Calving Date (Optional)' ?></label>
                            <input type="date" name="expected_calving_date">
                        </div>
                    </div>
                    <button class="btn" type="submit" name="record_breeding" value="1" onclick="prepareBreedingSubmit()"><?= current_locale() === 'am' ? 'ማዳቀል መዝግብ' : 'Record Breeding' ?></button>
                </form>
            </div>

            <!-- Add Calving Record (only if there are breeding attempts or pregnant) -->
            <div class="card">
                <h3><?= current_locale() === 'am' ? 'የመውለድ መዝገብ አስገባ' : 'Record Calving / Gestation Exit' ?></h3>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label><?= current_locale() === 'am' ? 'የተያያዘ የማዳቀል መዝገብ *' : 'Link to Breeding Record *' ?></label>
                        <select name="breeding_record_id" required>
                            <?php foreach ($breedingHistory as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= h(eth_date($b['breeding_date'])) ?> (<?= current_locale() === 'am' ? 'ኮርማ' : 'Sire' ?>: <?= h($b['sire_reference'] ?: 'unknown') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label><?= current_locale() === 'am' ? 'የተወለደበት ቀን *' : 'Calving Date *' ?></label>
                            <input type="date" name="calving_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?= current_locale() === 'am' ? 'የመውለድ ውጤት *' : 'Outcome *' ?></label>
                            <select name="outcome" id="calving_outcome" required onchange="toggleCalfFields(this.value)">
                                <option value="live_birth"><?= current_locale() === 'am' ? 'በህይወት ተወልዷል' : 'Live Birth' ?></option>
                                <option value="stillbirth"><?= current_locale() === 'am' ? 'ሞቶ ተወልዷል' : 'Stillbirth' ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Calf Details (Only for Live Births) -->
                    <div id="calf_details_form">
                        <div class="form-row">
                            <div class="form-group">
                                <label><?= current_locale() === 'am' ? 'የጥጃው ታግ ቁጥር *' : 'Calf Tag Number *' ?></label>
                                <input type="text" name="calf_tag_number" id="calf_tag_number" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ AF-2001' : 'e.g. AF-2001' ?>">
                            </div>
                            <div class="form-group">
                                <label><?= current_locale() === 'am' ? 'የጥጃው ስም' : 'Calf Name' ?></label>
                                <input type="text" name="calf_name" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ ዴይዚ ጁኒየር' : 'e.g. Daisy Junior' ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><?= current_locale() === 'am' ? 'የጥጃው ጾታ *' : 'Calf Sex *' ?></label>
                                <select name="calf_sex">
                                    <option value="female"><?= current_locale() === 'am' ? 'ጊደር (ሴት ጥጃ)' : 'Female (Heifer)' ?></option>
                                    <option value="male"><?= current_locale() === 'am' ? 'ወይፈን (ወንድ ጥጃ)' : 'Male (Bull Calf)' ?></option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><?= current_locale() === 'am' ? 'የጥጃው ዝርያ (አማራጭ)' : 'Calf Breed (Optional)' ?></label>
                                <input type="text" name="calf_breed" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ ቦራን፣ ሆልስታይን' : 'e.g. Holstein' ?>" value="<?= h($cow['breed']) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?= h(t('common.notes', 'Notes / Observations')) ?></label>
                        <textarea name="notes" rows="2" placeholder="<?= current_locale() === 'am' ? 'የመውለድ ሁኔታዎችን ወይም የተደረገ ድጋፍን ይግለጹ' : 'Describe calving details or assistance required.' ?>"></textarea>
                    </div>
                    
                    <button class="btn" type="submit" name="record_calving" value="1"><?= current_locale() === 'am' ? 'መውለድን መዝግብ' : 'Record Calving' ?></button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Record Exit (Death / Sale) -->
        <div class="card">
            <h3><?= current_locale() === 'am' ? 'ከእርሻው መውጣትን መዝግብ (ሽያጭ / ሞት)' : 'Record Exit (Death / Sale)' ?></h3>
            <p class="text-muted"><?= current_locale() === 'am' ? 'የላሟን ንቁ ቆይታ በቋሚነት ይዘጋል። ያለፈው ታሪክ ሳይጠፋ ተቀምጦ ይቆያል።' : "Permanently closes the cow's active lifecycle. History is preserved." ?></p>
            <form method="post"><?= csrf_field() ?>
                <div class="form-group">
                    <label><?= current_locale() === 'am' ? 'የመውጫ ዓይነት *' : 'Exit Type *' ?></label>
                    <select name="exit_type" id="exit_type" required onchange="toggleExitFields(this.value)">
                        <option value="">— <?= h(t('common.select', 'select')) ?> —</option>
                        <option value="sale"><?= current_locale() === 'am' ? 'ሽያጭ (Sale)' : 'Sale' ?></option>
                        <option value="death"><?= current_locale() === 'am' ? 'ሞት (Death)' : 'Death' ?></option>
                    </select>
                </div>
                <div class="form-group"><label><?= current_locale() === 'am' ? 'የወጣበት ቀን *' : 'Exit Date *' ?></label><input type="date" name="exit_date" value="<?= date('Y-m-d') ?>" required></div>
                <div id="sale_fields" style="display:none">
                    <div class="form-group">
                        <label><?= current_locale() === 'am' ? 'የገዥው ስም' : 'Buyer Name' ?></label>
                        <input type="text" name="buyer_name" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ አቶ በቀለ' : 'e.g. Ato Bekele' ?>">
                    </div>
                    <div class="form-group">
                        <label><?= current_locale() === 'am' ? 'የተሸጠበት ዋጋ (ብር) *' : 'Sale Price (ETB) *' ?></label>
                        <input type="number" step="0.01" name="sale_price" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label style="color:#2e7d32;font-weight:700">💰 <?= current_locale() === 'am' ? 'ገንዘቡ የት ገባ? (የክፍያ መቀበያ ዘዴ) *' : 'Payment Deposited To *' ?></label>
                        <select name="payment_method" id="exit_payment_method" onchange="toggleSplitFields(this.value)">
                            <option value="bank">🏦 ኦሮሚያ ባንክ (Oromia Bank)</option>
                            <option value="cash">💵 ጥሬ ገንዘብ በካዝና (Cash on Hand)</option>
                            <option value="telebirr">📱 ቴሌብር (Telebirr)</option>
                            <option value="split">⚖️ በከፊል በባንክ በከፊል በጥሬ ገንዘብ (Split)</option>
                        </select>
                    </div>
                    <div id="split_fields" style="display:none;background:var(--bg);padding:10px;border-radius:6px;margin-bottom:10px">
                        <div class="form-group">
                            <label><?= current_locale() === 'am' ? 'በጥሬ ገንዘብ የተከፈለው መጠን (ብር)' : 'Cash Amount (ETB)' ?></label>
                            <input type="number" step="0.01" name="split_cash_amount" placeholder="<?= current_locale() === 'am' ? 'ቀሪው ወደ ኦሮሚያ ባንክ ይገባል' : 'Remainder to Bank' ?>">
                        </div>
                    </div>
                </div>
                <div id="death_fields" style="display:none">
                    <div class="form-group"><label><?= current_locale() === 'am' ? 'የሞት ምክንያት *' : 'Cause of Death *' ?></label><input type="text" name="cause_of_death" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ ድንገተኛ በሽታ፣ አደጋ' : 'e.g. Illness, accident' ?>"></div>
                </div>
                <div class="form-group"><label><?= h(t('common.notes', 'Notes')) ?></label><textarea name="notes" rows="2"></textarea></div>
                <button class="btn danger" type="submit" onclick="return confirm('<?= current_locale() === 'am' ? 'ይህ መረጃ የላሟን ንቁ ቆይታ በቋሚነት ይዘጋል። እርግጠኛ ነዎት?' : 'This permanently closes the cow lifecycle. Continue?' ?>');"><?= current_locale() === 'am' ? 'መውጣትን መዝግብ' : 'Record Exit' ?></button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="card"><p class="text-muted"><?= current_locale() === 'am' ? 'የዚህች ላም ንቁ ቆይታ ተዘግቷል (' . h(t('dairy.lifecycle_' . $cow['lifecycle_status'], $cow['lifecycle_status'])) . ')። ተጨማሪ ተግባር መመዝገብ አይቻልም።' : 'This cow\'s lifecycle is closed (' . h($cow['lifecycle_status']) . '). No further operational entries are possible (BR-D01).' ?></p></div>
<?php endif; ?>

<!-- Deceased Cows Quick History -->
<?php
$deceasedList = db()->query(
    "SELECT c.id, c.tag_number, c.name, c.breed, e.exit_date, e.cause_of_death, e.notes 
     FROM dairy_cows c 
     JOIN dairy_cow_exit_records e ON e.cow_id = c.id 
     WHERE e.exit_type = 'death' 
     ORDER BY e.exit_date DESC LIMIT 5"
)->fetchAll();
?>
<?php if (!empty($deceasedList)): ?>
<div class="card" style="margin-top:20px;border-top:3px solid #b3261e">
    <h3 style="margin-top:0">⚰️ <?= current_locale() === 'am' ? 'የቅርብ ጊዜ የሞቱ ከብቶች ታሪክ' : 'Recent Mortality Records' ?></h3>
    <table>
        <thead>
            <tr>
                <th><?= current_locale() === 'am' ? 'የታግ ቁጥር' : 'Tag #' ?></th>
                <th><?= h(t('common.name', 'Name')) ?></th>
                <th><?= current_locale() === 'am' ? 'የሞተበት ቀን' : 'Death Date' ?></th>
                <th><?= current_locale() === 'am' ? 'የሞት ምክንያት' : 'Cause of Death' ?></th>
                <th><?= h(t('common.notes', 'Notes')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($deceasedList as $d): ?>
                <tr>
                    <td><strong><?= h($d['tag_number']) ?></strong></td>
                    <td><?= h($d['name'] ?: '—') ?></td>
                    <td><?= h(eth_date($d['exit_date'])) ?></td>
                    <td><span class="badge sold"><?= h($d['cause_of_death'] ?: 'ያልተገለጸ') ?></span></td>
                    <td class="text-muted"><?= h($d['notes'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
function toggleSireSource(val) {
    document.getElementById('internal_bull_group').style.display = val === 'internal' ? 'block' : 'none';
    document.getElementById('external_bull_group').style.display = val === 'external' ? 'block' : 'none';
}

function prepareBreedingSubmit() {
    const src = document.getElementById('sire_source_select')?.value;
    const hidden = document.getElementById('sire_reference_hidden');
    if (src === 'internal') {
        hidden.value = document.getElementById('internal_bull_ref')?.value || '';
    } else {
        const ref = document.getElementById('external_bull_ref')?.value || '';
        const breed = document.getElementById('external_sire_breed')?.value || '';
        hidden.value = breed ? (ref + ' [' + breed + ']') : ref;
    }
}

function toggleExitFields(val) {
    document.getElementById('sale_fields').style.display = val === 'sale' ? 'block' : 'none';
    document.getElementById('death_fields').style.display = val === 'death' ? 'block' : 'none';
}

function toggleSplitFields(val) {
    const splitDiv = document.getElementById('split_fields');
    if (splitDiv) splitDiv.style.display = val === 'split' ? 'block' : 'none';
}

function toggleCalfFields(val) {
    const fields = document.getElementById('calf_details_form');
    const tagInput = document.getElementById('calf_tag_number');
    if (val === 'live_birth') {
        fields.style.display = 'block';
        if (tagInput) tagInput.required = true;
    } else {
        fields.style.display = 'none';
        if (tagInput) {
            tagInput.required = false;
            tagInput.value = '';
        }
    }
}
toggleCalfFields(document.getElementById('calving_outcome')?.value || 'live_birth');
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
