<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['general_manager', 'dairy_manager', 'field_worker']);

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
$type = $_GET['type'] ?? (date('H') < 12 ? 'morning' : 'evening');
if (!in_array($type, ['morning', 'evening'], true)) $type = 'morning';

// Calendar month being displayed (defaults to the month of the selected date).
$calMonth = (int) ($_GET['cal_month'] ?? date('n', strtotime($date)));
$calYear = (int) ($_GET['cal_year'] ?? date('Y', strtotime($date)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (isset($_POST['close_session'])) {
            $sessType = $_POST['close_session_type'] ?? $type;
            $sessionId = milking_open_session($date, $sessType);
            milking_close_and_transfer($sessionId, 1, 1);
            flash(current_locale() === 'am' ? '✓ የወተት ምርቱ በተሳካ ሁኔታ ወደ መጋዘን ተልኳል!' : 'Session closed and transferred to Warehouse.');
        } else {
            // Guard against future dates
            if ($date > date('Y-m-d')) {
                throw new InvalidArgumentException(current_locale() === 'am' ? 'የወደፊት ቀን ወተት መመዝገብ አይቻልም።' : 'Cannot record milk for future dates.');
            }
            $sessionId = milking_open_session($date, $type);
            milking_record_yield($sessionId, (int) $_POST['cow_id'], (float) $_POST['yield_liters'], $_POST['observation'] ?? '');
            
            // Audit log if date is backdated (past day)
            if ($date < date('Y-m-d')) {
                log_activity('dairy', 'audit', 'dairy_milk_yield_entries', (int)$_POST['cow_id'], "Backdated milk entry for {$date} submitted on " . date('Y-m-d H:i:s') . " by user #" . current_user()['id']);
            }
            flash(t('dairy.yield_saved', 'Yield recorded.'));
        }
        header("Location: /aleltu-eifms/modules/dairy/milking.php?date={$date}&type={$type}");
        exit;
    } catch (Throwable $e) {
        flash('Error: ' . $e->getMessage(), 'error');
    }
}
require_once __DIR__ . '/../../includes/header.php';

$morningSessionId = milking_open_session($date, 'morning');
$eveningSessionId = milking_open_session($date, 'evening');

$morningClosed = (bool) db()->query("SELECT closed_at FROM dairy_milking_sessions WHERE id = {$morningSessionId}")->fetchColumn();
$eveningClosed = (bool) db()->query("SELECT closed_at FROM dairy_milking_sessions WHERE id = {$eveningSessionId}")->fetchColumn();

$activeSessionId = ($type === 'morning') ? $morningSessionId : $eveningSessionId;
$activeClosed = ($type === 'morning') ? $morningClosed : $eveningClosed;

$dayTotals = milking_day_totals($date);

$cows = db()->query("SELECT id, tag_number, name FROM dairy_cows WHERE lifecycle_status NOT IN ('sold','deceased') ORDER BY tag_number")->fetchAll();

$morningEntries = db()->prepare(
    "SELECT e.*, c.tag_number, c.name FROM dairy_milk_yield_entries e JOIN dairy_cows c ON c.id = e.cow_id WHERE e.milking_session_id = ? ORDER BY c.tag_number"
);
$morningEntries->execute([$morningSessionId]);
$morningEntries = $morningEntries->fetchAll();

$eveningEntries = db()->prepare(
    "SELECT e.*, c.tag_number, c.name FROM dairy_milk_yield_entries e JOIN dairy_cows c ON c.id = e.cow_id WHERE e.milking_session_id = ? ORDER BY c.tag_number"
);
$eveningEntries->execute([$eveningSessionId]);
$eveningEntries = $eveningEntries->fetchAll();

$entries = ($type === 'morning') ? $morningEntries : $eveningEntries;

$markedDays = milking_month_marked_days($calYear, $calMonth);
$recentDays = milking_recent_days(7); // Exact 1 week in correct order

$firstOfMonth = mktime(0, 0, 0, $calMonth, 1, $calYear);
$daysInMonth = (int) date('t', $firstOfMonth);
$startWeekday = (int) date('w', $firstOfMonth); // 0=Sun
$prevMonth = $calMonth - 1; $prevYear = $calYear; if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $calMonth + 1; $nextYear = $calYear; if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
?>
<h2 class="mt-0"><?= h(t('dairy.milking_entry', 'Milking Entry')) ?></h2>

<div style="display:grid;grid-template-columns:340px 1fr;gap:20px;align-items:start">

    <!-- Calendar -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <a class="btn secondary" style="padding:3px 10px" href="?date=<?= h($date) ?>&type=<?= h($type) ?>&cal_month=<?= $prevMonth ?>&cal_year=<?= $prevYear ?>">&larr;</a>
            <strong><?php $ethParts = EthiopianDate::fromGregorian((int)date('Y', $firstOfMonth), (int)date('n', $firstOfMonth), 15); echo EthiopianDate::$months[$ethParts[1]] . ' ' . $ethParts[0] . ' ዓ.ም'; ?></strong>
            <a class="btn secondary" style="padding:3px 10px" href="?date=<?= h($date) ?>&type=<?= h($type) ?>&cal_month=<?= $nextMonth ?>&cal_year=<?= $nextYear ?>">&rarr;</a>
        </div>
        <table style="width:100%;text-align:center">
            <tr>
                <?php foreach (['እሁ','ሰኞ','ማክ','ረቡ','ሐሙ','አር','ቅዳ'] as $wd): ?><th style="padding:4px;font-size:12px"><?= $wd ?></th><?php endforeach; ?>
            </tr>
            <?php
            $cell = 0;
            echo '<tr>';
            for ($i = 0; $i < $startWeekday; $i++) { echo '<td></td>'; $cell++; }
            for ($d = 1; $d <= $daysInMonth; $d++) {
                if ($cell > 0 && $cell % 7 === 0) echo '</tr><tr>';
                $dateStr = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $d);
                $isSelected = $dateStr === $date;
                $hasData = in_array($d, $markedDays, true);
                $bg = $isSelected ? 'var(--primary)' : ($hasData ? 'rgba(46,125,50,0.15)' : 'transparent');
                $color = $isSelected ? '#fff' : 'inherit';
                echo '<td style="padding:2px"><a href="?date=' . $dateStr . '&type=' . h($type) . '&cal_month=' . $calMonth . '&cal_year=' . $calYear . '" '
                   . 'style="display:block;padding:6px 0;border-radius:6px;background:' . $bg . ';color:' . $color . ';text-decoration:none;font-size:13px">' . $d . '</a></td>';
                $cell++;
            }
            echo '</tr>';
            ?>
        </table>
        <p class="text-muted" style="font-size:12px;margin-top:8px"><?= h(t('dairy.calendar_hint', 'Shaded days have milking entries. Click any day to open it.')) ?></p>

        <div style="margin-top:14px">
            <label style="font-size:13px"><?= current_locale() === 'am' ? 'ቀን ይምረጡ (ያለፉ ቀናት ይቻላል)' : 'Select Date (Past Allowed)' ?></label>
            <input type="date" onchange="location = '?date=' + this.value + '&type=<?= h($type) ?>'" value="<?= h($date) ?>" max="<?= date('Y-m-d') ?>" style="width:100%">
            <p class="text-muted" style="font-size:11px;margin:4px 0 0">
                <?= current_locale() === 'am' ? 'ℹ️ የተመረጠው ቀን፡ <strong>' . h(eth_date($date, 'long')) . '</strong>' : '' ?>
            </p>
        </div>
    </div>

    <!-- Right column: totals + entry + history -->
    <div>
        <div class="kpi-row" style="margin-bottom:16px">
            <div class="kpi"><div class="label">🌅 <?= h(t('dairy.morning', 'Morning')) ?></div><div class="value"><?= number_format($dayTotals['morning']['total'], 1) ?> L</div></div>
            <div class="kpi"><div class="label">🌙 <?= h(t('dairy.evening', 'Evening')) ?></div><div class="value"><?= number_format($dayTotals['evening']['total'], 1) ?> L</div></div>
            <div class="kpi" style="border:2px solid var(--primary)"><div class="label">🥛 <?= h(t('dairy.day_total', 'Day Total')) ?></div><div class="value"><strong><?= number_format($dayTotals['combined'], 1) ?> L</strong></div></div>
        </div>

        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px">
                <h3 style="margin:0">📅 <?= h(eth_date($date, 'long')) ?></h3>
                <div style="display:flex;gap:6px">
                    <a class="btn <?= $type==='morning'?'':'secondary' ?>" style="padding:6px 14px;font-size:13px;font-weight:700" href="?date=<?= h($date) ?>&type=morning">
                        🌅 <?= current_locale() === 'am' ? 'የጠዋት ወተት' : 'Morning' ?> (<?= count($morningEntries) ?>)
                    </a>
                    <a class="btn <?= $type==='evening'?'':'secondary' ?>" style="padding:6px 14px;font-size:13px;font-weight:700" href="?date=<?= h($date) ?>&type=evening">
                        🌙 <?= current_locale() === 'am' ? 'የማታ ወተት' : 'Evening' ?> (<?= count($eveningEntries) ?>)
                    </a>
                </div>
            </div>

            <!-- Notice when viewing evening and morning already has data -->
            <?php if ($type === 'evening' && !empty($morningEntries)): ?>
                <div style="background:#e8f5e9;border-left:4px solid #2e7d32;border-radius:6px;padding:8px 12px;margin-bottom:12px;font-size:13px">
                    ✓ <?= current_locale() === 'am' 
                        ? 'የዛሬ <strong>የጠዋት ወተት</strong> በድምሩ <strong>' . number_format($dayTotals['morning']['total'], 1) . ' ሊትር</strong> ተመዝግቧል! የማታውን ወተት ከስር ማስገባት ይችላሉ።' 
                        : 'Morning milking registered: ' . number_format($dayTotals['morning']['total'], 1) . ' L.' ?>
                </div>
            <?php elseif ($type === 'morning' && !empty($eveningEntries)): ?>
                <div style="background:#e8f5e9;border-left:4px solid #2e7d32;border-radius:6px;padding:8px 12px;margin-bottom:12px;font-size:13px">
                    ✓ <?= current_locale() === 'am' 
                        ? 'የዛሬ <strong>የማታ ወተት</strong> በድምሩ <strong>' . number_format($dayTotals['evening']['total'], 1) . ' ሊትር</strong> ተመዝግቧል!' 
                        : 'Evening milking registered: ' . number_format($dayTotals['evening']['total'], 1) . ' L.' ?>
                </div>
            <?php endif; ?>

            <?php if (!$activeClosed): ?>
            <form method="post" style="margin-top:14px;max-width:480px"><?= csrf_field() ?>
                <div class="form-group"><label><?= h(t('dairy.cow_tag', 'Cow')) ?> *</label>
                    <select name="cow_id" required style="font-size:15px;padding:8px">
                        <?php foreach ($cows as $c): ?><option value="<?= (int) $c['id'] ?>"><?= h($c['tag_number']) ?> <?= $c['name'] ? '('.h($c['name']).')' : '' ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= h(t('dairy.yield_liters', 'Yield (liters)')) ?> *</label>
                    <input type="number" step="0.1" name="yield_liters" required placeholder="0.0" style="font-size:18px;font-weight:700">
                </div>
                <div class="form-group"><label><?= current_locale() === 'am' ? 'ምልከታ / ማስታወሻ (አማራጭ)' : 'Observation (Optional)' ?></label><input type="text" name="observation" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ መደበኛ ወይም ያልተለመደ ቅነሳ' : 'e.g. normal or abnormal yield' ?>"></div>
                <button class="btn" type="submit" style="padding:10px 20px;font-size:14px">✓ <?= h(t('common.save', 'Save Yield')) ?></button>
            </form>
            <?php endif; ?>

            <table style="margin-top:16px">
                <thead>
                    <tr>
                        <th><?= h(t('dairy.cow_tag', 'Cow')) ?></th>
                        <th><?= h(t('dairy.yield_liters', 'Liters')) ?></th>
                        <th><?= current_locale() === 'am' ? 'ማስታወሻ' : 'Note' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $e): ?>
                        <tr><td><strong><?= h($e['tag_number']) ?></strong> <?= $e['name'] ? '('.h($e['name']).')' : '' ?></td><td><strong style="color:var(--primary)"><?= number_format((float)$e['yield_liters'], 1) ?> L</strong></td><td><?= h($e['observation'] ?: '—') ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (empty($entries)): ?><tr><td colspan="3" class="text-muted"><?= current_locale() === 'am' ? 'ለዚህ ክፍለ ጊዜ እስካሁን የተመዘገበ ወተት የለም።' : 'No entries yet for this session.' ?></td></tr><?php endif; ?>
                </tbody>
            </table>

            <?php if (!$activeClosed && !empty($entries)): ?>
                <form method="post" style="margin-top:14px"><?= csrf_field() ?>
                    <input type="hidden" name="close_session" value="1">
                    <input type="hidden" name="close_session_type" value="<?= h($type) ?>">
                    <button class="btn secondary" type="submit" onclick="return confirm('<?= current_locale() === 'am' ? 'ይህንን ክፍለ ጊዜ ወደ መጋዘን መላክ ይፈልጋሉ?' : 'Send total to Warehouse?' ?>');" style="background:#2e7d32;color:#fff;border-color:#2e7d32">
                        📦 <?= current_locale() === 'am' ? 'ወደመጋዘን ላክ' : 'Send to Warehouse' ?>
                    </button>
                </form>
            <?php elseif ($activeClosed): ?>
                <p class="text-muted" style="margin-top:14px;color:#2e7d32;font-weight:600">✓ <?= current_locale() === 'am' ? 'ክፍለጊዜው ተዘግቷል — ጠቅላላ ምርቱ ወደ ወተት መጋዘን ተላልፏል።' : 'Session closed — transferred to Milk Warehouse.' ?></p>
            <?php endif; ?>
        </div>

        <div class="card" style="margin-top:20px">
            <h3>📊 <?= current_locale() === 'am' ? 'የቅርብ ጊዜ ቀናት የወተት ምርት (ያለፈው 1 ሳምንት)' : 'Recent Days (Past 1 Week)' ?></h3>
            <table>
                <thead>
                    <tr>
                        <th><?= current_locale() === 'am' ? 'ቀን (በኢትዮጵያ አቆጣጠር)' : 'Date (ET)' ?></th>
                        <th><?= h(t('dairy.morning', 'Morning')) ?></th>
                        <th><?= h(t('dairy.evening', 'Evening')) ?></th>
                        <th><?= h(t('dairy.day_total', 'Total')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentDays as $rd):
                        $rowTotal = (float) $rd['morning_total'] + (float) $rd['evening_total'];
                    ?>
                        <tr>
                            <td><strong><?= h(eth_date($rd['session_date'])) ?></strong></td>
                            <td><?= number_format((float) $rd['morning_total'], 1) ?> L</td>
                            <td><?= number_format((float) $rd['evening_total'], 1) ?> L</td>
                            <td><strong style="color:var(--primary)"><?= number_format($rowTotal, 1) ?> L</strong></td>
                            <td><a class="btn secondary" style="padding:2px 8px;font-size:11px" href="?date=<?= h($rd['session_date']) ?>&type=morning"><?= h(t('common.view_all', 'View')) ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentDays)): ?><tr><td colspan="5" class="text-muted"><?= h(t('common.no_records', 'No records found.')) ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@media (max-width: 860px) {
    div[style*="grid-template-columns:340px 1fr"] {
        display: flex !important;
        flex-direction: column !important;
    }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
