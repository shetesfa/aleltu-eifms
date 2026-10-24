<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'general_manager', 'poultry_manager', 'field_worker']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_eggs'])) {
    csrf_verify();
    try {
        poultry_egg_collection_record(
            (int) $_POST['flock_id'],
            $_POST['collection_date'],
            (int) $_POST['round_no'],
            (int) $_POST['whole_count'],
            (int) ($_POST['cracked_count'] ?: 0),
            (int) ($_POST['broken_count'] ?: 0)
        );
        flash(t('poultry.eggs_logged', 'Egg collection logged.'));
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
    header('Location: ' . BASE_URL . '/modules/poultry/flock_view.php?id=' . (int) $_POST['flock_id']);
    exit;
}

$flockId = (int)($_GET['id'] ?? 0);
$flock = $flockId > 0 ? db()->query(
    "SELECT f.*, h.name AS house_name, h.capacity, h.quarantined
     FROM poultry_flocks f 
     JOIN poultry_houses h ON h.id = f.poultry_house_id 
     WHERE f.id = {$flockId}"
)->fetch() : null;

if (!$flock) {
    $firstId = db()->query("SELECT id FROM poultry_flocks WHERE status = 'active' ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($firstId) {
        $flockId = (int)$firstId;
        $flock = db()->query(
            "SELECT f.*, h.name AS house_name, h.capacity, h.quarantined
             FROM poultry_flocks f 
             JOIN poultry_houses h ON h.id = f.poultry_house_id 
             WHERE f.id = {$flockId}"
        )->fetch();
    } else {
        flash('No flock records found.', 'error');
        header('Location: ' . BASE_URL . '/modules/poultry/flocks.php');
        exit;
    }
}

$livePop = flock_live_population((int)$flock['id']);

// Egg Collections History
$eggLogs = db()->query(
    "SELECT c.*, u.name AS worker_name
     FROM poultry_egg_collections c
     JOIN users u ON u.id = c.recorded_by
     WHERE c.flock_id = {$flockId}
     ORDER BY c.collection_date DESC, c.round_no ASC LIMIT 20"
)->fetchAll();

// Mortality Logs History
$mortalityLogs = db()->query(
    "SELECT m.*, u.name AS worker_name
     FROM poultry_mortality_logs m
     JOIN users u ON u.id = m.recorded_by
     WHERE m.flock_id = {$flockId}
     ORDER BY m.log_date DESC LIMIT 20"
)->fetchAll();

// Vaccinations History (Cross-unit polymorphic lookup)
$vaccinations = db()->query(
    "SELECT * FROM vet_vaccination_records 
     WHERE subject_type = 'poultry_flock' AND subject_id = {$flockId}
     ORDER BY scheduled_date DESC"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h2 class="mt-0">🐓 <?= current_locale() === 'am' ? 'የዶሮ መንጋ ዝርዝር መረጃ' : 'Flock File' ?> — <?= current_locale() === 'am' ? 'ቤት' : 'House' ?>: <?= h($flock['house_name']) ?> (<?= h($flock['breed']) ?>)</h2>
    <a href="<?= BASE_URL ?>/modules/poultry/flocks.php" class="btn secondary">&larr; <?= current_locale() === 'am' ? 'ወደ መንጋዎች ዝርዝር ተመለስ' : 'Back to Flocks' ?></a>
</div>

<div class="kpi-row">
    <div class="kpi">
        <div class="label"><?= h(t('poultry.opening_pop', 'Initial Stocking')) ?></div>
        <div class="value"><?= number_format($flock['initial_population']) ?> <?= current_locale() === 'am' ? 'ዶሮዎች' : 'Birds' ?></div>
    </div>
    <div class="kpi">
        <div class="label"><?= h(t('poultry.live_population', 'Live Population')) ?></div>
        <div class="value"><?= number_format($livePop) ?> <?= current_locale() === 'am' ? 'ዶሮዎች' : 'Birds' ?></div>
    </div>
    <div class="kpi">
        <div class="label"><?= h(t('poultry.mortality', 'Cumulative Mortality')) ?></div>
        <div class="value"><?= number_format($flock['cumulative_mortality']) ?> <?= current_locale() === 'am' ? 'ዶሮዎች' : 'Birds' ?></div>
    </div>
    <div class="kpi">
        <div class="label"><?= current_locale() === 'am' ? 'የዶሮ ማደሪያው ሁኔታ' : 'House Status' ?></div>
        <div class="value">
            <?php if ($flock['quarantined']): ?>
                <span class="badge sold"><?= h(t('poultry.quarantined', 'Quarantined')) ?></span>
            <?php else: ?>
                <span class="badge milking"><?= current_locale() === 'am' ? 'መደበኛ / ንቁ' : 'Normal' ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
    <!-- Left Column: Egg Collections -->
    <div>
        <div class="card">
            <h3><?= h(t('poultry.log_eggs', 'Log Egg Collection')) ?></h3>
            <form method="post"><?= csrf_field() ?>
                <input type="hidden" name="log_eggs" value="1">
                <input type="hidden" name="flock_id" value="<?= (int) $flockId ?>">
                <div class="form-row">
                    <div class="form-group"><label><?= h(t('common.date', 'Date')) ?> *</label><input type="date" name="collection_date" value="<?= date('Y-m-d') ?>" required></div>
                    <input type="hidden" name="round_no" value="1">
                </div>
                <div class="form-row">
                    <div class="form-group"><label><?= h(t('poultry.whole', 'Whole Eggs')) ?> *</label><input type="number" name="whole_count" min="0" required></div>
                    <input type="hidden" name="cracked_count" value="0">
                    <div class="form-group"><label><?= current_locale() === 'am' ? 'የተሰበረ' : 'Broken' ?></label><input type="number" name="broken_count" min="0" value="0"></div>
                </div>
                <button class="btn" type="submit"><?= h(t('common.save', 'Save')) ?></button>
            </form>
        </div>

        <div class="card">
            <h3><?= current_locale() === 'am' ? 'የእንቁላል ስብሰባ መዝገቦች' : 'Egg Production Records' ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?= h(t('common.date', 'Date')) ?></th>
                        <th><?= h(t('poultry.round', 'Round')) ?></th>
                        <th><?= h(t('poultry.whole', 'Whole')) ?></th>
                        <th><?= current_locale() === 'am' ? 'የተሰበረ' : 'Broken' ?></th>
                        <th><?= current_locale() === 'am' ? 'የመዘገበው' : 'Recorded By' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eggLogs as $e): ?>
                        <tr>
                            <td><?= h(eth_date($e['collection_date'])) ?></td>
                            <td>#<?= (int)$e['round_no'] ?></td>
                            <td><strong style="color:var(--primary)"><?= number_format($e['whole_count']) ?></strong></td>
                            <td><span class="badge sold"><?= number_format($e['broken_count']) ?></span></td>
                            <td><?= h($e['worker_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($eggLogs)): ?>
                        <tr><td colspan="5" class="text-muted"><?= h(t('common.no_records', 'No egg collection entries logged yet.')) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Column: Mortality & Health/Vaccination -->
    <div>
        <div class="card">
            <h3><?= current_locale() === 'am' ? 'የዶሮዎች ሞት መዝገብ' : 'Bird Mortality Logs' ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?= h(t('common.date', 'Date')) ?></th>
                        <th><?= h(t('common.quantity', 'Quantity')) ?></th>
                        <th><?= current_locale() === 'am' ? 'የመዘገበው' : 'Recorded By' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mortalityLogs as $m): ?>
                        <tr>
                            <td><?= h(eth_date($m['log_date'])) ?></td>
                            <td><strong class="badge sold"><?= number_format($m['count']) ?> <?= current_locale() === 'am' ? 'ዶሮዎች' : 'birds' ?></strong></td>
                            <td><?= h($m['worker_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($mortalityLogs)): ?>
                        <tr><td colspan="3" class="text-muted"><?= h(t('common.no_records', 'No mortality events recorded.')) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3><?= current_locale() === 'am' ? 'የክትባት ፕሮግራም እና ታሪክ' : 'Vaccination Schedule' ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?= current_locale() === 'am' ? 'ክትባት' : 'Vaccine' ?></th>
                        <th><?= current_locale() === 'am' ? 'የተያዘለት ቀን' : 'Scheduled Date' ?></th>
                        <th><?= current_locale() === 'am' ? 'የተሰጠበት ቀን' : 'Administered Date' ?></th>
                        <th><?= h(t('common.status', 'Status')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vaccinations as $v): ?>
                        <tr>
                            <td><strong><?= h($v['vaccine_name']) ?></strong></td>
                            <td><?= h(eth_date($v['scheduled_date'])) ?></td>
                            <td><?= h($v['administered_date'] ? eth_date($v['administered_date']) : '—') ?></td>
                            <td><span class="badge <?= $v['status'] === 'administered' ? 'milking' : 'dry' ?>"><?= $v['status'] === 'administered' ? (current_locale() === 'am' ? 'ተሰጥቷል' : 'Administered') : (current_locale() === 'am' ? 'የተያዘለት' : 'Scheduled') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($vaccinations)): ?>
                        <tr><td colspan="4" class="text-muted"><?= h(t('common.no_records', 'No scheduled vaccinations for this flock.')) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
