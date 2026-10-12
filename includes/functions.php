<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pos.php';
require_once __DIR__ . '/ethiopian_calendar.php';

function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function flash(string $msg, string $type = 'success'): void { $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type]; }
function get_flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

/* ===================== DAIRY (SAD section 5.1) ===================== */

const COW_TERMINAL_STATUSES = ['sold', 'deceased'];

// BR-D01: a cow in a terminal state cannot receive new operational entries.
function cow_is_active(array $cow): bool
{
    return !in_array($cow['lifecycle_status'], COW_TERMINAL_STATUSES, true);
}

function cow_register(array $data): int
{
    // Backend enforcement of §23's cow-source rule — the form in
    // cow_add.php already does this via JS (toggling the `required`
    // attribute based on the source dropdown), but that is trivially
    // bypassed (disabled JS, direct POST) and the brief is explicit
    // elsewhere that frontend-only enforcement of a business rule is not
    // sufficient. Re-validated here so the rule holds regardless of how
    // the request was made:
    //   - born_on_farm -> date_of_birth is required
    //   - purchased    -> acquired_date is required; date_of_birth stays
    //     optional/estimated, never forced (brief: "Do not force unknown
    //     birth dates.")
    if (empty(trim($data['tag_number'] ?? ''))) {
        throw new InvalidArgumentException('Tag number is required.');
    }
    if (empty($data['sex']) || !in_array($data['sex'], ['female', 'male'], true)) {
        throw new InvalidArgumentException('Sex is required.');
    }
    $source = $data['source'] ?? null;
    if ($source === 'born_on_farm' && empty($data['date_of_birth'])) {
        throw new InvalidArgumentException('Date of birth is required for a cow born on the farm.');
    }
    if ($source === 'purchased' && empty($data['acquired_date'])) {
        throw new InvalidArgumentException('Purchase/acquired date is required for a purchased cow.');
    }

    $stmt = db()->prepare(
        "INSERT INTO dairy_cows (tag_number, name, photo_path, sex, date_of_birth, breed, dam_id, sire_id, cow_group_id, lifecycle_status, acquired_date, source)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'registered', ?, ?)"
    );
    $stmt->execute([
        $data['tag_number'], $data['name'] ?? null ?: null, $data['photo_path'] ?? null ?: null, $data['sex'], $data['date_of_birth'] ?? null ?: null,
        $data['breed'] ?? null ?: null, $data['dam_id'] ?? null ?: null, $data['sire_id'] ?? null ?: null,
        $data['cow_group_id'] ?? null ?: null, $data['acquired_date'] ?? null ?: null, $data['source'] ?? null ?: null,
    ]);
    $id = (int) db()->lastInsertId();
    log_activity('dairy', 'create', 'dairy_cows', $id, "Registered cow {$data['tag_number']}");
    return $id;
}

// FR-D11 / BR-D01: closes the cow's lifecycle permanently. History preserved, never deleted.
function cow_record_exit(int $cowId, array $data): void
{
    $cow = db()->query("SELECT * FROM dairy_cows WHERE id = " . (int) $cowId)->fetch();
    if (!$cow || !cow_is_active($cow)) {
        throw new RuntimeException("Cow is already {$cow['lifecycle_status']} and cannot be exited again (BR-D01).");
    }

    db()->beginTransaction();
    try {
        $stmt = db()->prepare(
            "INSERT INTO dairy_cow_exit_records (cow_id, exit_type, exit_date, cause_of_death, buyer_name, sale_price, notes, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $cowId, $data['exit_type'], $data['exit_date'], $data['cause_of_death'] ?: null,
            $data['buyer_name'] ?: null, $data['sale_price'] ?: null, $data['notes'] ?: null,
            current_user()['id'],
        ]);

        $newStatus = $data['exit_type'] === 'death' ? 'deceased' : 'sold';
        db()->prepare("UPDATE dairy_cows SET lifecycle_status = ? WHERE id = ?")->execute([$newStatus, $cowId]);

        // Auto-book sale revenue to Finance treasury
        if ($data['exit_type'] === 'sale' && !empty($data['sale_price']) && (float)$data['sale_price'] > 0) {
            $salePrice = (float)$data['sale_price'];
            $method = $data['payment_method'] ?? 'bank';
            $buyer = $data['buyer_name'] ?: 'Buyer';
            $desc = "የከብት ሽያጭ ገቢ: ላም ታግ #{$cow['tag_number']} ለ {$buyer}";

            if ($method === 'split') {
                $cashPart = (float)($data['split_cash_amount'] ?? ($salePrice / 2));
                $bankPart = max(0, $salePrice - $cashPart);
                if ($cashPart > 0) {
                    finance_record_transaction('inflow', $cashPart, 'cash', 'dairy', 'livestock_sale', 'dairy_cow_exit_records', $cowId, $desc . ' (በጥሬ ገንዘብ)');
                }
                if ($bankPart > 0) {
                    finance_record_transaction('inflow', $bankPart, 'bank', 'dairy', 'livestock_sale', 'dairy_cow_exit_records', $cowId, $desc . ' (በኦሮሚያ ባንክ)');
                }
            } else {
                finance_record_transaction('inflow', $salePrice, $method, 'dairy', 'livestock_sale', 'dairy_cow_exit_records', $cowId, $desc);
            }
        }

        db()->commit();
        log_activity('dairy', 'update', 'dairy_cows', $cowId, "Exit recorded ({$data['exit_type']})");
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

// FR-D02/FR-D03: opens (or reuses) today's session and rolls up totals automatically.
function milking_open_session(string $date, string $type): int
{
    $stmt = db()->prepare("SELECT id FROM dairy_milking_sessions WHERE session_date = ? AND session_type = ?");
    $stmt->execute([$date, $type]);
    $row = $stmt->fetch();
    if ($row) {
        return (int) $row['id'];
    }
    $stmt = db()->prepare("INSERT INTO dairy_milking_sessions (session_date, session_type, recorded_by) VALUES (?, ?, ?)");
    $stmt->execute([$date, $type, current_user()['id']]);
    return (int) db()->lastInsertId();
}

function cow_record_breeding(int $cowId, string $method, ?string $sireReference, string $breedingDate, ?string $expectedCalvingDate = null): int
{
    $cow = db()->query("SELECT * FROM dairy_cows WHERE id = " . (int) $cowId)->fetch();
    if (!$cow || !cow_is_active($cow)) {
        throw new RuntimeException("Cow is not active and cannot be bred (BR-D01).");
    }

    if (!$expectedCalvingDate) {
        // Average gestation period for cows is 283 days
        $expectedCalvingDate = date('Y-m-d', strtotime($breedingDate . ' + 283 days'));
    }

    db()->beginTransaction();
    try {
        $stmt = db()->prepare(
            "INSERT INTO dairy_breeding_records (cow_id, method, sire_reference, breeding_date, expected_calving_date, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$cowId, $method, $sireReference, $breedingDate, $expectedCalvingDate, current_user()['id']]);
        $id = (int) db()->lastInsertId();

        // Update mother's status to 'pregnant'
        db()->prepare("UPDATE dairy_cows SET lifecycle_status = 'pregnant' WHERE id = ?")->execute([$cowId]);

        db()->commit();
        log_activity('dairy', 'create', 'dairy_breeding_records', $id, "Recorded breeding for cow ID {$cowId}");
        return $id;
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

function cow_record_calving(int $breedingRecordId, int $damId, string $calvingDate, string $outcome, ?string $notes = null, ?array $calfData = null): int
{
    $cow = db()->query("SELECT * FROM dairy_cows WHERE id = " . (int) $damId)->fetch();
    if (!$cow || !cow_is_active($cow)) {
        throw new RuntimeException("Dam is not active and cannot record calving (BR-D01).");
    }

    db()->beginTransaction();
    try {
        $calfId = null;
        if ($outcome === 'live_birth' && !empty($calfData['tag_number'])) {
            // Register the calf automatically
            $stmt = db()->prepare(
                "INSERT INTO dairy_cows (tag_number, name, sex, date_of_birth, breed, dam_id, lifecycle_status, source)
                 VALUES (?, ?, ?, ?, ?, ?, 'calf', 'born_on_farm')"
            );
            $stmt->execute([
                $calfData['tag_number'],
                $calfData['name'] ?? null ?: null,
                $calfData['sex'] ?? 'female',
                $calvingDate,
                $calfData['breed'] ?? $cow['breed'] ?? null ?: null,
                $damId
            ]);
            $calfId = (int) db()->lastInsertId();
            log_activity('dairy', 'create', 'dairy_cows', $calfId, "Registered calf {$calfData['tag_number']} from cow ID {$damId}");
        }

        $stmt = db()->prepare(
            "INSERT INTO dairy_calving_records (breeding_record_id, dam_id, calf_id, calving_date, outcome, notes)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$breedingRecordId, $damId, $calfId, $calvingDate, $outcome, $notes]);
        $calvingId = (int) db()->lastInsertId();

        // Reset mother's status back to milking
        db()->prepare("UPDATE dairy_cows SET lifecycle_status = 'milking' WHERE id = ?")->execute([$damId]);

        db()->commit();
        log_activity('dairy', 'create', 'dairy_calving_records', $calvingId, "Recorded calving for dam ID {$damId}");
        return $calvingId;
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}


function milking_record_yield(int $sessionId, int $cowId, float $liters, string $observation = ''): void
{
    $cow = db()->query("SELECT * FROM dairy_cows WHERE id = " . (int) $cowId)->fetch();
    if (!$cow || !cow_is_active($cow)) {
        throw new RuntimeException("Cow {$cow['tag_number']} is {$cow['lifecycle_status']} and cannot be milked (BR-D01).");
    }

    $userId = (function_exists('current_user') && current_user()) ? (int)current_user()['id'] : 1;
    $stmt = db()->prepare(
        "INSERT INTO dairy_milk_yield_entries (milking_session_id, cow_id, yield_liters, observation, flagged_abnormal, recorded_by)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE yield_liters = VALUES(yield_liters), observation = VALUES(observation), flagged_abnormal = VALUES(flagged_abnormal)"
    );
    $stmt->execute([$sessionId, $cowId, $liters, $observation ?: null, $observation ? 1 : 0, $userId]);
    log_activity('dairy', 'create', 'dairy_milk_yield_entries', $cowId, "Milk yield {$liters}L recorded");
}

function milking_session_total(int $sessionId): float
{
    return (float) db()->query("SELECT COALESCE(SUM(yield_liters),0) FROM dairy_milk_yield_entries WHERE milking_session_id = " . (int) $sessionId)->fetchColumn();
}

/**
 * Closes the session and pushes the aggregated total into the Milk
 * warehouse as one Receiving transaction (SAD section 2.1 step 3).
 * Dairy never touches wh_stock_batches directly — only through stock_receive().
 */
function milking_close_and_transfer(int $sessionId, int $warehouseId, int $milkItemId): void
{
    $total = milking_session_total($sessionId);
    $session = db()->query("SELECT * FROM dairy_milking_sessions WHERE id = " . (int)$sessionId)->fetch();
    $sessionType = $session['session_type'] ?? 'milking';
    $sessionDate = $session['session_date'] ?? date('Y-m-d');

    if ($total > 0) {
        // Automatically create incoming intake for Storekeeper in Partition 1 (Finished Products)
        $dispatchCode = 'MILK-' . $sessionId . '-' . date('Ymd') . '-' . rand(1000, 9999);
        $userId = (function_exists('current_user') && current_user()) ? (int)current_user()['id'] : 1;
        
        $stmt = db()->prepare(
            "INSERT INTO wh_store_receiving
             (dispatch_code, source_type, source_reference, warehouse_id, stock_item_id, sent_quantity, sent_by, status)
             VALUES (?, 'dairy', ?, ?, ?, ?, ?, 'pending_receipt')"
        );
        $stmt->execute([
            $dispatchCode,
            "Daily Milking ({$sessionType} session, {$sessionDate})",
            $warehouseId,
            $milkItemId,
            $total,
            $userId
        ]);

        notify_targeted(
            'warehouse',
            "Daily milk yield from Dairy ({$total} Liters, {$sessionType} session) sent to Store Partition 1 for physical verification.",
            'high', null, 8, null, '/aleltu-eifms/modules/warehouse/receiving.php'
        );
    }
    db()->prepare("UPDATE dairy_milking_sessions SET closed_at = NOW() WHERE id = ?")->execute([$sessionId]);
}

// Morning + evening totals for one calendar day (used by the milking
// calendar view so both sessions are visible together, not one at a time).
function milking_day_totals(string $date): array
{
    $rows = db()->prepare(
        "SELECT s.session_type, s.id AS session_id, s.closed_at,
                COALESCE(SUM(e.yield_liters), 0) AS total
         FROM dairy_milking_sessions s
         LEFT JOIN dairy_milk_yield_entries e ON e.milking_session_id = s.id
         WHERE s.session_date = ?
         GROUP BY s.id, s.session_type, s.closed_at"
    );
    $rows->execute([$date]);
    $rows = $rows->fetchAll();

    $result = [
        'morning' => ['session_id' => null, 'total' => 0.0, 'closed' => false],
        'evening' => ['session_id' => null, 'total' => 0.0, 'closed' => false],
    ];
    foreach ($rows as $r) {
        $result[$r['session_type']] = [
            'session_id' => (int) $r['session_id'],
            'total' => (float) $r['total'],
            'closed' => (bool) $r['closed_at'],
        ];
    }
    $result['combined'] = $result['morning']['total'] + $result['evening']['total'];
    return $result;
}

// One row per day with a recorded session, for the history list under the calendar.
function milking_recent_days(int $limit = 30): array
{
    $stmt = db()->prepare(
        "SELECT session_date,
                SUM(CASE WHEN session_type = 'morning' THEN daily_total ELSE 0 END) AS morning_total,
                SUM(CASE WHEN session_type = 'evening' THEN daily_total ELSE 0 END) AS evening_total
         FROM (
            SELECT s.session_date, s.session_type, COALESCE(SUM(e.yield_liters), 0) AS daily_total
            FROM dairy_milking_sessions s
            LEFT JOIN dairy_milk_yield_entries e ON e.milking_session_id = s.id
            GROUP BY s.id
         ) x
         GROUP BY session_date
         ORDER BY session_date DESC
         LIMIT ?"
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Which days in a given month have at least one milking entry — used to mark
// the calendar grid so the user can see productive days at a glance.
function milking_month_marked_days(int $year, int $month): array
{
    $stmt = db()->prepare(
        "SELECT DISTINCT DAY(s.session_date) AS d
         FROM dairy_milking_sessions s
         JOIN dairy_milk_yield_entries e ON e.milking_session_id = s.id
         WHERE YEAR(s.session_date) = ? AND MONTH(s.session_date) = ?"
    );
    $stmt->execute([$year, $month]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/* ===================== WAREHOUSE (SAD section 5.5) =====================
 * Warehouse is the single choke point for everything physical (SAD 10.1) —
 * every module calls stock_receive()/stock_issue(), never writes to
 * wh_stock_batches directly. This is what makes Current Stock trustworthy. */

function stock_current(int $warehouseId, int $stockItemId): float
{
    $stmt = db()->prepare("SELECT COALESCE(SUM(quantity_on_hand),0) FROM wh_stock_batches WHERE warehouse_id = ? AND stock_item_id = ?");
    $stmt->execute([$warehouseId, $stockItemId]);
    return (float) $stmt->fetchColumn();
}

function stock_receive(int $warehouseId, int $stockItemId, float $quantity, string $sourceType, ?string $expiryDate = null, string $batchSource = 'internal', float $unitCost = 0.0): void
{
    if ($quantity <= 0) {
        throw new InvalidArgumentException('Receipt quantity must be positive.');
    }
    $ownsTx = false;
    if (!db()->inTransaction()) {
        db()->beginTransaction();
        $ownsTx = true;
    }
    try {
        $partitionCode = db()->query("SELECT partition_code FROM wh_stock_items WHERE id = " . (int)$stockItemId)->fetchColumn() ?: 'finished_products';
        $batchNo = 'BAT-' . strtoupper(substr($partitionCode, 0, 3)) . '-' . date('Ymd') . '-' . rand(100, 999);

        // unit_cost enables inventory valuation: finance_inventory_value() reads quantity_on_hand × unit_cost
        $stmt = db()->prepare(
            "INSERT INTO wh_stock_batches (warehouse_id, partition_code, stock_item_id, batch_number, quantity_on_hand, unit_cost, received_date, expiry_date, source)
             VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?)"
        );
        $stmt->execute([$warehouseId, $partitionCode, $stockItemId, $batchNo, $quantity, $unitCost > 0 ? $unitCost : null, $expiryDate, $batchSource]);
        $batchId = (int) db()->lastInsertId();

        $userId = (function_exists('current_user') && current_user()) ? (int)current_user()['id'] : 1;
        $stmt = db()->prepare(
            "INSERT INTO wh_stock_receipts (warehouse_id, stock_item_id, stock_batch_id, quantity, source_type, received_by, received_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$warehouseId, $stockItemId, $batchId, $quantity, $sourceType, $userId]);

        if ($ownsTx && db()->inTransaction()) {
            db()->commit();
        }
        log_activity('warehouse', 'create', 'wh_stock_receipts', $batchId, "Received {$quantity} @ unit_cost {$unitCost} ETB into warehouse {$warehouseId} partition {$partitionCode}");
    } catch (Throwable $e) {
        if ($ownsTx && db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
}

// FR-WH07 FEFO-aware, BR-W02: refuses to take stock negative.
function stock_issue(int $warehouseId, int $stockItemId, float $quantity, string $destinationType): void
{
    $available = stock_current($warehouseId, $stockItemId);
    if ($quantity > $available) {
        throw new RuntimeException("Cannot issue {$quantity}; only {$available} available (BR-W02).");
    }

    $ownsTx = false;
    if (!db()->inTransaction()) {
        db()->beginTransaction();
        $ownsTx = true;
    }
    try {
        $stmt = db()->prepare(
            "SELECT id, quantity_on_hand FROM wh_stock_batches
             WHERE warehouse_id = ? AND stock_item_id = ? AND quantity_on_hand > 0
             ORDER BY (expiry_date IS NULL), expiry_date ASC
             FOR UPDATE"
        );
        $stmt->execute([$warehouseId, $stockItemId]);
        $batches = $stmt->fetchAll();

        $remaining = $quantity;
        $lastBatchId = null;
        foreach ($batches as $batch) {
            if ($remaining <= 0) break;
            $take = min($remaining, (float) $batch['quantity_on_hand']);
            db()->prepare("UPDATE wh_stock_batches SET quantity_on_hand = quantity_on_hand - ? WHERE id = ?")
                ->execute([$take, $batch['id']]);
            $remaining -= $take;
            $lastBatchId = $batch['id'];
        }

        $userId = (function_exists('current_user') && current_user()) ? (int)current_user()['id'] : 1;
        $stmt = db()->prepare(
            "INSERT INTO wh_stock_issues (warehouse_id, stock_item_id, stock_batch_id, quantity, destination_type, issued_by, issued_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$warehouseId, $stockItemId, $lastBatchId, $quantity, $destinationType, $userId]);
        $issueId = (int) db()->lastInsertId();

        if ($ownsTx && db()->inTransaction()) {
            db()->commit();
        }
        check_minimum_stock($warehouseId, $stockItemId);
        log_activity('warehouse', 'create', 'wh_stock_issues', $issueId, "Issued {$quantity} from warehouse {$warehouseId}");
    } catch (Throwable $e) {
        if ($ownsTx && db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
}

// BR-W03: fires once per breach, resets once replenished.
function check_minimum_stock(int $warehouseId, int $stockItemId): void
{
    $stmt = db()->prepare("SELECT * FROM wh_minimum_stock_rules WHERE warehouse_id = ? AND stock_item_id = ?");
    $stmt->execute([$warehouseId, $stockItemId]);
    $rule = $stmt->fetch();
    if (!$rule) return;

    $current = stock_current($warehouseId, $stockItemId);
    $breached = $current < (float) $rule['minimum_threshold'];

    if ($breached && !$rule['last_alert_sent_at']) {
        db()->prepare("UPDATE wh_minimum_stock_rules SET last_alert_sent_at = NOW() WHERE id = ?")->execute([$rule['id']]);
        $itemName = db()->query("SELECT name FROM wh_stock_items WHERE id = " . (int) $stockItemId)->fetchColumn();
        db()->prepare("INSERT INTO notifications (module, priority, message) VALUES ('warehouse', 'medium', ?)")
            ->execute(["Low stock: {$itemName} below minimum threshold."]);
    } elseif (!$breached && $rule['last_alert_sent_at']) {
        db()->prepare("UPDATE wh_minimum_stock_rules SET last_alert_sent_at = NULL WHERE id = ?")->execute([$rule['id']]);
    }
}

/* ===================== POULTRY (SAD section 5.2) ===================== */

// BR-P01: live population, recalculated from cumulative counters.
function flock_live_population(int $flockId): int
{
    $flock = db()->query("SELECT * FROM poultry_flocks WHERE id = " . (int)$flockId)->fetch();
    return (int) $flock['initial_population'] - (int) $flock['cumulative_mortality']
        - (int) $flock['cumulative_culled_sold'] + (int) $flock['cumulative_transfers_in'];
}

function poultry_record_mortality(int $flockId, string $date, int $count, string $cause): void
{
    if ($count <= 0) {
        throw new InvalidArgumentException('Mortality count must be positive.');
    }

    db()->beginTransaction();
    try {
        // Lock the flock row before computing live population (same
        // rationale as the other fixes in this pass): reading live
        // population outside a lock lets two concurrent mortality entries
        // both pass the "count <= live population" check against the same
        // stale figure and together record more deaths than birds that
        // existed — the exact outcome §25 says must never happen.
        $flock = db()->prepare("SELECT * FROM poultry_flocks WHERE id = ? FOR UPDATE");
        $flock->execute([$flockId]);
        $flock = $flock->fetch();
        if (!$flock) {
            throw new RuntimeException('Flock not found.');
        }
        $live = (int) $flock['initial_population'] - (int) $flock['cumulative_mortality']
            - (int) $flock['cumulative_culled_sold'] + (int) $flock['cumulative_transfers_in'];
        if ($count > $live) {
            throw new RuntimeException("Cannot record {$count} mortality — only {$live} live birds in this flock.");
        }

        db()->prepare("INSERT INTO poultry_mortality_logs (flock_id, log_date, count, cause_code, recorded_by) VALUES (?, ?, ?, ?, ?)")
            ->execute([$flockId, $date, $count, $cause, current_user()['id']]);
        db()->prepare("UPDATE poultry_flocks SET cumulative_mortality = cumulative_mortality + ? WHERE id = ?")
            ->execute([$count, $flockId]);
        db()->commit();
        log_activity('poultry', 'create', 'poultry_mortality_logs', $flockId, "{$count} mortality logged ({$cause})");
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

// Creates a physical house/coop that flocks are later stocked into.
function poultry_house_create(string $name, int $capacity): int
{
    if ($capacity <= 0) {
        throw new InvalidArgumentException('Capacity must be greater than zero.');
    }
    $stmt = db()->prepare("INSERT INTO poultry_houses (name, capacity, quarantined) VALUES (?, ?, 0)");
    $stmt->execute([$name, $capacity]);
    $id = (int) db()->lastInsertId();
    log_activity('poultry', 'create', 'poultry_houses', $id, "House '{$name}' created (capacity {$capacity})");
    return $id;
}

// Stocks a new flock into a house. Refuses to overfill the house (BR-P style guard).
function poultry_flock_create(int $houseId, string $breed, string $stockingDate, int $initialPopulation): int
{
    if ($initialPopulation <= 0) {
        throw new InvalidArgumentException('Initial population must be greater than zero.');
    }
    $house = db()->query("SELECT * FROM poultry_houses WHERE id = " . (int) $houseId)->fetch();
    if (!$house) {
        throw new RuntimeException('House not found.');
    }

    $existing = db()->prepare(
        "SELECT COALESCE(SUM(initial_population - cumulative_mortality - cumulative_culled_sold + cumulative_transfers_in), 0)
         FROM poultry_flocks WHERE poultry_house_id = ? AND status = 'active'"
    );
    $existing->execute([$houseId]);
    $currentBirds = (int) $existing->fetchColumn();

    if ($currentBirds + $initialPopulation > (int) $house['capacity']) {
        throw new RuntimeException("House capacity is {$house['capacity']}; it already holds {$currentBirds} birds.");
    }

    $stmt = db()->prepare(
        "INSERT INTO poultry_flocks (poultry_house_id, breed, stocking_date, initial_population, status)
         VALUES (?, ?, ?, ?, 'active')"
    );
    $stmt->execute([$houseId, $breed, $stockingDate, $initialPopulation]);
    $id = (int) db()->lastInsertId();
    log_activity('poultry', 'create', 'poultry_flocks', $id, "Flock stocked: {$breed}, {$initialPopulation} birds into house #{$houseId}");
    return $id;
}

// Records one egg-collection round for a flock (BR-P02 style: whole/cracked/broken).
function poultry_egg_collection_record(int $flockId, string $date, int $round, int $whole, int $cracked, int $broken): int
{
    if ($whole < 0 || $cracked < 0 || $broken < 0) {
        throw new InvalidArgumentException('Egg counts cannot be negative.');
    }
    $userId = (function_exists('current_user') && current_user()) ? (int)current_user()['id'] : 1;
    $stmt = db()->prepare(
        "INSERT INTO poultry_egg_collections (flock_id, collection_date, round_no, whole_count, cracked_count, broken_count, recorded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$flockId, $date, $round, $whole, $cracked, $broken, $userId]);
    $id = (int) db()->lastInsertId();
    log_activity('poultry', 'create', 'poultry_egg_collections', $id, "Egg round #{$round} logged: {$whole} whole, {$cracked} cracked, {$broken} broken");

    // Automatically create incoming intake for Storekeeper in Partition 1 (Finished Products)
    if ($whole > 0) {
        $eggItem = db()->query("SELECT id FROM wh_stock_items WHERE sku = 'EGG-TABLE-01' OR product_class = 'general' AND name LIKE '%Egg%' LIMIT 1")->fetch();
        $eggItemId = $eggItem ? (int)$eggItem['id'] : 2;
        $dispatchCode = 'EGG-' . $id . '-' . date('Ymd') . '-' . rand(1000, 9999);
        $dbStmt = db()->prepare(
            "INSERT INTO wh_store_receiving
             (dispatch_code, source_type, source_reference, warehouse_id, stock_item_id, sent_quantity, sent_by, status)
             VALUES (?, 'poultry', ?, 1, ?, ?, ?, 'pending_receipt')"
        );
        $dbStmt->execute([
            $dispatchCode,
            "Daily Eggs (Flock #{$flockId}, Round #{$round}, {$date})",
            $eggItemId,
            $whole,
            $userId
        ]);

        notify_targeted(
            'warehouse',
            "Daily egg collection ({$whole} whole eggs, Round #{$round}) sent to Store Partition 1 for physical verification.",
            'medium', null, 8, null, '/aleltu-eifms/modules/warehouse/receiving.php'
        );
    }

    return $id;
}

/* ===================== EMPLOYEE MANAGEMENT (SAD section 5.8) ===================== */

function employee_register(array $data): int
{
    $stmt = db()->prepare(
        "INSERT INTO employees (department_id, employee_number, full_name, job_title, phone, hire_date, base_salary, is_driver, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')"
    );
    $stmt->execute([
        $data['department_id'] ?: null, $data['employee_number'], $data['full_name'],
        $data['job_title'] ?: null, $data['phone'] ?: null, $data['hire_date'] ?: null,
        $data['base_salary'] ?: null, isset($data['is_driver']) ? 1 : 0,
    ]);
    $id = (int) db()->lastInsertId();
    log_activity('employee', 'create', 'employees', $id, "Registered employee {$data['employee_number']}");
    return $id;
}

// FR-EM02: daily attendance capture. One row per employee per day (UNIQUE key
// in schema.sql enforces this — re-marking the same day updates it instead).
function attendance_mark(int $employeeId, string $date, string $status): void
{
    $stmt = db()->prepare(
        "INSERT INTO attendance (employee_id, attendance_date, check_in_at, status)
         VALUES (?, ?, NOW(), ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status)"
    );
    $stmt->execute([$employeeId, $date, $status]);
    log_activity('employee', 'create', 'attendance', $employeeId, "Attendance marked {$status} for {$date}");
}

// Per-day present/absent/on_leave/half_day counts for a month, so the
// attendance calendar can shade each day (all present = green, any
// absence = amber) instead of a plain filtered list.
function attendance_month_summary(int $year, int $month): array
{
    $stmt = db()->prepare(
        "SELECT DAY(attendance_date) AS d,
                SUM(status = 'present') AS present,
                SUM(status = 'absent') AS absent,
                SUM(status = 'half_day') AS half_day,
                SUM(status = 'on_leave') AS on_leave
         FROM attendance
         WHERE YEAR(attendance_date) = ? AND MONTH(attendance_date) = ?
         GROUP BY DAY(attendance_date)"
    );
    $stmt->execute([$year, $month]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[(int) $r['d']] = $r;
    }
    return $out;
}

// BR-E01: a leave request overlapping already-approved leave for the same
// employee is blocked.
function leave_has_overlap(int $employeeId, string $start, string $end): bool
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM leave_requests
         WHERE employee_id = ? AND status IN ('manager_approved','approved')
         AND start_date <= ? AND end_date >= ?"
    );
    $stmt->execute([$employeeId, $end, $start]);
    return (int) $stmt->fetchColumn() > 0;
}

function leave_request(int $employeeId, string $start, string $end, string $reason): int
{
    if (leave_has_overlap($employeeId, $start, $end)) {
        throw new RuntimeException('This employee already has approved leave overlapping these dates (BR-E01).');
    }
    $stmt = db()->prepare("INSERT INTO leave_requests (employee_id, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->execute([$employeeId, $start, $end, $reason ?: null]);
    $id = (int) db()->lastInsertId();
    log_activity('employee', 'create', 'leave_requests', $id, "Leave requested {$start} to {$end}");
    return $id;
}

// SAD section 7: Manager -> HR approval chain, simplified here to a
// single-step approve/reject (any super_admin/hr_officer can approve).
function leave_decide(int $leaveId, string $decision): void
{
    $status = $decision === 'approve' ? 'approved' : 'rejected';
    db()->prepare("UPDATE leave_requests SET status = ? WHERE id = ?")->execute([$status, $leaveId]);
    log_activity('employee', 'update', 'leave_requests', $leaveId, "Leave {$status}");
}

function task_assign(int $employeeId, string $title, string $description, ?string $module, ?string $dueDate, int $assignedBy): int
{
    $stmt = db()->prepare(
        "INSERT INTO tasks (title, description, assigned_to, assigned_by, module, due_date, status)
         VALUES (?, ?, ?, ?, ?, ?, 'pending')"
    );
    $stmt->execute([$title, $description ?: null, $employeeId, $assignedBy, $module ?: null, $dueDate ?: null]);
    $id = (int) db()->lastInsertId();
    log_activity('employee', 'create', 'tasks', $id, "Task '{$title}' assigned");
    return $id;
}

function task_complete(int $taskId): void
{
    db()->prepare("UPDATE tasks SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$taskId]);
    log_activity('employee', 'update', 'tasks', $taskId, 'Task marked complete');
}

// FR-EM05: attendance rate + task completion rate for a period.
function employee_performance(int $employeeId, string $start, string $end): array
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS total, SUM(status='present') AS present FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?"
    );
    $stmt->execute([$employeeId, $start, $end]);
    $att = $stmt->fetch();
    $attendanceRate = $att['total'] > 0 ? round(($att['present'] / $att['total']) * 100, 1) : null;

    $stmt = db()->prepare(
        "SELECT COUNT(*) AS total, SUM(status='completed') AS completed FROM tasks WHERE assigned_to = ? AND due_date BETWEEN ? AND ?"
    );
    $stmt->execute([$employeeId, $start, $end]);
    $task = $stmt->fetch();
    $taskRate = $task['total'] > 0 ? round(($task['completed'] / $task['total']) * 100, 1) : null;

    return ['attendance_rate' => $attendanceRate, 'task_completion_rate' => $taskRate];
}

/* ===================== FINANCE (SAD section 5.7) ===================== */

const FINANCE_UNIT_TAGS = ['dairy', 'poultry', 'fish', 'feed_production', 'warehouse', 'distribution', 'general_overhead'];

// FR-FN01: every transaction carries a unit tag (BR-FN01).
function finance_record_transaction(string $direction, float $amount, string $unitTag, string $category, string $date, string $description, ?string $sourceTable = null, ?int $sourceId = null, ?int $bankAccountId = null, string $paymentMethod = 'bank'): int
{
    if ($bankAccountId === null) {
        $accType = match($paymentMethod) {
            'cash' => 'cash',
            'telebirr', 'mobile' => 'mobile',
            default => 'bank'
        };
        $accId = (int)(db()->query("SELECT id FROM fin_bank_accounts WHERE account_type='{$accType}' ORDER BY id ASC LIMIT 1")->fetchColumn());
        if ($accId > 0) {
            $bankAccountId = $accId;
        }
    }
    $stmt = db()->prepare(
        "INSERT INTO fin_transactions (bank_account_id, direction, amount, payment_method, unit_tag, category, source_table, source_id, transaction_date, description, recorded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $userId = (function_exists('current_user') && current_user()) ? (int)current_user()['id'] : 1;
    $stmt->execute([$bankAccountId, $direction, $amount, $paymentMethod, $unitTag, $category, $sourceTable, $sourceId, $date, $description, $userId]);
    return (int) db()->lastInsertId();
}

// Balance broken down by Bank / Cash / Mobile, using the account_type
// column added in migration_v6. Each named account is calculated as:
// opening_balance + inflows - outflows for transactions linked to that account.
function finance_balance_by_method(): array
{
    // Get all accounts with their types and opening balances
    $accounts = db()->query(
        "SELECT id, account_type, opening_balance, account_name, bank_name FROM fin_bank_accounts ORDER BY id"
    )->fetchAll();

    $result = ['bank' => 0.0, 'cash' => 0.0, 'mobile' => 0.0, '_accounts' => []];

    foreach ($accounts as $acc) {
        $opening = (float) $acc['opening_balance'];
        // Sum flows linked to this specific bank account
        $stmt = db()->prepare(
            "SELECT direction, COALESCE(SUM(amount),0) AS total
             FROM fin_transactions WHERE bank_account_id = ? GROUP BY direction"
        );
        $stmt->execute([$acc['id']]);
        $flows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $balance = $opening + (float)($flows['inflow'] ?? 0) - (float)($flows['outflow'] ?? 0);
        $type = $acc['account_type'] ?: 'bank';

        // Also add unlinked transactions to the primary bank account
        if ($acc['id'] === (int)(db()->query("SELECT MIN(id) FROM fin_bank_accounts WHERE account_type='bank'")->fetchColumn())) {
            $unlinked = db()->query(
                "SELECT direction, COALESCE(SUM(amount),0) AS total
                 FROM fin_transactions WHERE bank_account_id IS NULL GROUP BY direction"
            )->fetchAll(PDO::FETCH_KEY_PAIR);
            $balance += (float)($unlinked['inflow'] ?? 0) - (float)($unlinked['outflow'] ?? 0);
        }

        $result[$type] = ($result[$type] ?? 0.0) + $balance;
        $result['_accounts'][] = [
            'id'      => $acc['id'],
            'name'    => $acc['bank_name'] ?: $acc['account_name'],
            'type'    => $type,
            'balance' => $balance,
        ];
    }
    return $result;
}

// Full transaction ledger, newest first, with optional filters — powers
// the drill-down when someone clicks the balance card on the dashboard.
function finance_transactions_list(?string $unitTag = null, ?string $paymentMethod = null, int $limit = 100): array
{
    $where = ['1=1'];
    $params = [];
    if ($unitTag) { $where[] = 't.unit_tag = ?'; $params[] = $unitTag; }
    if ($paymentMethod) { $where[] = 't.payment_method = ?'; $params[] = $paymentMethod; }
    $sql = "SELECT t.*, u.name AS recorded_by_name FROM fin_transactions t
            LEFT JOIN users u ON u.id = t.recorded_by
            WHERE " . implode(' AND ', $where) . "
            ORDER BY t.transaction_date DESC, t.id DESC LIMIT " . (int) $limit;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Records a payment (full or partial) against a Purchase Order. Goods can
// be received from Store before the supplier is fully paid — this lets
// Finance pay in installments until amount_paid reaches total_amount.
function po_record_payment(int $poId, float $amount, string $paymentMethod, ?string $notes): void
{
    if ($amount <= 0) {
        throw new InvalidArgumentException('Payment amount must be greater than zero.');
    }

    db()->beginTransaction();
    try {
        // Lock the PO row before validating remaining balance (see the same
        // fix and rationale in dispatch_record_payment()) so two concurrent
        // payments against the same PO can't both pass validation and together
        // overpay the supplier.
        $po = db()->prepare("SELECT * FROM fin_purchase_orders WHERE id = ? FOR UPDATE");
        $po->execute([$poId]);
        $po = $po->fetch();
        if (!$po) {
            throw new RuntimeException('Purchase order not found.');
        }
        if ($po['status'] !== 'received') {
            throw new RuntimeException('ክፍያ ከመፈጸሙ በፊት እቃው በመጋዘን ኃላፊው በአካል መረከቡ መረጋገጥ አለበት (Goods must be physically received and verified by Storekeeper before payment can be issued).');
        }
        $remaining = (float) $po['total_amount'] - (float) $po['amount_paid'];
        if ($amount > $remaining + 0.01) {
            throw new RuntimeException("Payment of {$amount} exceeds the remaining balance of {$remaining} ETB.");
        }

        $newPaid = (float) $po['amount_paid'] + $amount;
        $status = $newPaid >= (float) $po['total_amount'] - 0.01 ? 'paid' : 'partial';
        db()->prepare("UPDATE fin_purchase_orders SET amount_paid = ?, payment_status = ? WHERE id = ?")
            ->execute([$newPaid, $status, $poId]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    $desc = "Payment to {$po['supplier_name']} for PO#{$poId}" . ($notes ? " — {$notes}" : '');
    finance_record_transaction('outflow', $amount, 'warehouse', 'purchase', date('Y-m-d'), $desc, 'fin_purchase_orders', $poId, null, $paymentMethod);
    log_activity('finance', 'update', 'fin_purchase_orders', $poId, "Payment of {$amount} ETB recorded ({$paymentMethod})");
}

function finance_cash_position(): array
{
    $opening = (float) db()->query("SELECT COALESCE(SUM(opening_balance),0) FROM fin_bank_accounts")->fetchColumn();
    $inflow = (float) db()->query("SELECT COALESCE(SUM(amount),0) FROM fin_transactions WHERE direction='inflow'")->fetchColumn();
    $outflow = (float) db()->query("SELECT COALESCE(SUM(amount),0) FROM fin_transactions WHERE direction='outflow'")->fetchColumn();
    return ['opening' => $opening, 'inflow' => $inflow, 'outflow' => $outflow, 'balance' => $opening + $inflow - $outflow];
}

/**
 * Returns total outstanding receivables = unpaid amounts on dispatches.
 * Groups by driver/destination for drill-down. Revenue ≠ Cash received.
 */
function finance_receivables_summary(): array
{
    $total = (float) db()->query(
        "SELECT COALESCE(SUM(total_price - amount_paid), 0) FROM dist_dispatches WHERE payment_status != 'paid'"
    )->fetchColumn();

    $byDriver = db()->query(
        "SELECT d.driver_name, v.plate_number,
                COUNT(*) AS dispatch_count,
                COALESCE(SUM(d.total_price),0) AS total_value,
                COALESCE(SUM(d.amount_paid),0) AS paid,
                COALESCE(SUM(d.total_price - d.amount_paid),0) AS outstanding,
                d.payment_status
         FROM dist_dispatches d
         JOIN dist_vehicles v ON v.id = d.vehicle_id
         WHERE d.payment_status != 'paid'
         GROUP BY d.driver_name, v.plate_number, d.payment_status
         ORDER BY outstanding DESC"
    )->fetchAll();

    return ['total_receivable' => $total, 'by_driver' => $byDriver];
}

/**
 * Returns total supplier payables = unpaid amounts on purchase orders.
 */
function finance_payables_summary(): array
{
    $total = (float) db()->query(
        "SELECT COALESCE(SUM(total_amount - amount_paid), 0)
         FROM fin_purchase_orders
         WHERE payment_status != 'paid' AND status NOT IN ('draft','cancelled')"
    )->fetchColumn();

    $bySupplier = db()->query(
        "SELECT po.supplier_name,
                COUNT(*) AS po_count,
                COALESCE(SUM(po.total_amount),0) AS total_ordered,
                COALESCE(SUM(po.amount_paid),0) AS paid,
                COALESCE(SUM(po.total_amount - po.amount_paid),0) AS outstanding
         FROM fin_purchase_orders po
         WHERE po.payment_status != 'paid' AND po.status NOT IN ('draft','cancelled')
         GROUP BY po.supplier_name
         ORDER BY outstanding DESC"
    )->fetchAll();

    return ['total_payable' => $total, 'by_supplier' => $bySupplier];
}

/**
 * Returns total inventory value = SUM(quantity_on_hand × unit_cost) across all batches.
 * Requires unit_cost to be populated in wh_stock_batches (migration_v6).
 */
function finance_inventory_value(): array
{
    $total = (float) db()->query(
        "SELECT COALESCE(SUM(b.quantity_on_hand * b.unit_cost), 0)
         FROM wh_stock_batches b
         WHERE b.quantity_on_hand > 0"
    )->fetchColumn();

    $byItem = db()->query(
        "SELECT i.name, i.unit, i.partition_code,
                COALESCE(SUM(b.quantity_on_hand),0) AS qty,
                COALESCE(AVG(NULLIF(b.unit_cost,0)),0) AS avg_cost,
                COALESCE(SUM(b.quantity_on_hand * b.unit_cost),0) AS total_value
         FROM wh_stock_items i
         LEFT JOIN wh_stock_batches b ON b.stock_item_id = i.id AND b.quantity_on_hand > 0
         GROUP BY i.id, i.name, i.unit, i.partition_code
         HAVING qty > 0
         ORDER BY total_value DESC"
    )->fetchAll();

    return ['total_inventory_value' => $total, 'by_item' => $byItem];
}

/**
 * Returns operational cost breakdown for a department unit_tag over a date range.
 * Combines: approved expenses + stock issues (valued at unit_cost) + payroll payments.
 */
function finance_dept_costs(string $unitTag, string $dateFrom, string $dateTo): array
{
    // 1. Approved expenses for this unit
    $expenseStmt = db()->prepare(
        "SELECT category, COALESCE(SUM(amount),0) AS total
         FROM fin_expenses
         WHERE unit_tag = ? AND status = 'approved'
           AND expense_date BETWEEN ? AND ?
         GROUP BY category ORDER BY total DESC"
    );
    $expenseStmt->execute([$unitTag, $dateFrom, $dateTo]);
    $expenses = $expenseStmt->fetchAll();
    $expenseTotal = array_sum(array_column($expenses, 'total'));

    // 2. Finance transactions (outflow) tagged to this unit.
    //    'expense' rows are excluded: they are the exact same money already
    //    counted above via fin_expenses (expense_approve() writes both a
    //    fin_expenses.status='approved' row AND a mirroring fin_transactions
    //    row so the cash balance reflects the outflow — counting both here
    //    would double the department's cost). 'purchase' rows are excluded
    //    too: paying a supplier increases inventory value, it is not itself
    //    an operational cost — only the later consumption (step 3) is.
    $txStmt = db()->prepare(
        "SELECT category, COALESCE(SUM(amount),0) AS total
         FROM fin_transactions
         WHERE unit_tag = ? AND direction = 'outflow'
           AND category NOT IN ('expense', 'purchase')
           AND transaction_date BETWEEN ? AND ?
         GROUP BY category ORDER BY total DESC"
    );
    $txStmt->execute([$unitTag, $dateFrom, $dateTo]);
    $transactions = $txStmt->fetchAll();
    $txTotal = array_sum(array_column($transactions, 'total'));

    // 3. Stock issues (material consumption) valued at unit_cost.
    //    destination_type is written as the requesting department's own
    //    business_unit / unit_tag (see dept_material_request_fulfill() and
    //    feed_distribution_request_fulfill()), so it matches $unitTag
    //    directly — no remapping needed.
    $issueStmt = db()->prepare(
        "SELECT i.name AS item_name, i.unit,
                COALESCE(SUM(iss.quantity),0) AS qty_issued,
                COALESCE(AVG(NULLIF(b.unit_cost,0)),0) AS unit_cost,
                COALESCE(SUM(iss.quantity * COALESCE(b.unit_cost,0)),0) AS consumption_value
         FROM wh_stock_issues iss
         JOIN wh_stock_items i ON i.id = iss.stock_item_id
         LEFT JOIN wh_stock_batches b ON b.id = iss.stock_batch_id
         WHERE iss.destination_type = ?
           AND DATE(iss.issued_at) BETWEEN ? AND ?
         GROUP BY i.id, i.name, i.unit
         ORDER BY consumption_value DESC"
    );
    $issueStmt->execute([$unitTag, $dateFrom, $dateTo]);
    $stockIssues = $issueStmt->fetchAll();
    $issueTotal = array_sum(array_column($stockIssues, 'consumption_value'));

    return [
        'unit_tag'       => $unitTag,
        'date_from'      => $dateFrom,
        'date_to'        => $dateTo,
        'expense_total'  => (float)$expenseTotal,
        'tx_total'       => (float)$txTotal,
        'issue_total'    => (float)$issueTotal,
        'grand_total'    => (float)$expenseTotal + (float)$txTotal + (float)$issueTotal,
        'expenses'       => $expenses,
        'transactions'   => $transactions,
        'stock_issues'   => $stockIssues,
    ];
}

/**
 * Returns total dispatched value (revenue) vs cash actually collected.
 * Revenue ≠ Cash — this distinction must be shown clearly on Finance dashboard.
 */
function finance_revenue_vs_collected(?string $dateFrom = null, ?string $dateTo = null): array
{
    $where = '1=1';
    $params = [];
    if ($dateFrom) { $where .= ' AND DATE(dispatched_at) >= ?'; $params[] = $dateFrom; }
    if ($dateTo)   { $where .= ' AND DATE(dispatched_at) <= ?'; $params[] = $dateTo; }

    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(total_price),0) AS total_revenue,
                COALESCE(SUM(amount_paid),0) AS cash_collected,
                COALESCE(SUM(total_price - amount_paid),0) AS outstanding
         FROM dist_dispatches WHERE {$where}"
    );
    $stmt->execute($params);
    return $stmt->fetch();
}



function expense_create(array $data): int
{
    $bankAccountId = !empty($data['bank_account_id']) ? (int)$data['bank_account_id'] : null;
    $withdrawalType = !empty($data['withdrawal_type']) ? trim($data['withdrawal_type']) : 'operating_expense';
    $recipientName = !empty($data['recipient_name']) ? trim($data['recipient_name']) : null;

    $stmt = db()->prepare(
        "INSERT INTO fin_expenses (unit_tag, category, amount, bank_account_id, withdrawal_type, expense_date, description, recipient_name, requested_by, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
    );
    $stmt->execute([
        $data['unit_tag'],
        $data['category'],
        (float)$data['amount'],
        $bankAccountId,
        $withdrawalType,
        $data['expense_date'],
        $data['description'] ?: null,
        $recipientName,
        current_user()['id']
    ]);
    $id = (int) db()->lastInsertId();
    log_activity('finance', 'create', 'fin_expenses', $id, "Expense/Withdrawal requested: {$withdrawalType} / {$data['category']} {$data['amount']} ETB");
    return $id;
}

// §7 approval workflow: approving an expense also books it as a cash outflow.
function expense_approve(int $expenseId): void
{
    db()->beginTransaction();
    try {
        $expense = db()->prepare("SELECT * FROM fin_expenses WHERE id = ? FOR UPDATE");
        $expense->execute([$expenseId]);
        $expense = $expense->fetch();
        if (!$expense || $expense['status'] !== 'pending') {
            throw new RuntimeException('Only a pending expense can be approved.');
        }

        $updated = db()->prepare("UPDATE fin_expenses SET status = 'approved', approved_by = ? WHERE id = ? AND status = 'pending'");
        $updated->execute([current_user()['id'], $expenseId]);
        if ($updated->rowCount() === 0) {
            throw new RuntimeException('Only a pending expense can be approved.');
        }

        $bankAccountId = $expense['bank_account_id'] ? (int)$expense['bank_account_id'] : null;
        $category = !empty($expense['withdrawal_type']) && $expense['withdrawal_type'] !== 'operating_expense'
            ? 'withdrawal_' . $expense['withdrawal_type']
            : 'expense';

        $desc = ($expense['description'] ?: $expense['category']);
        if (!empty($expense['recipient_name'])) {
            $desc .= " (ተቀባይ/አላማ: {$expense['recipient_name']})";
        }

        $paymentMethod = 'bank';
        if ($bankAccountId) {
            $accType = db()->query("SELECT account_type FROM fin_bank_accounts WHERE id = {$bankAccountId}")->fetchColumn();
            if ($accType === 'cash') $paymentMethod = 'cash';
            elseif ($accType === 'mobile') $paymentMethod = 'mobile';
        }

        finance_record_transaction(
            'outflow', (float) $expense['amount'], $expense['unit_tag'], $category,
            $expense['expense_date'], $desc,
            'fin_expenses', $expenseId, $bankAccountId, $paymentMethod
        );
        db()->commit();
        log_activity('finance', 'update', 'fin_expenses', $expenseId, 'Expense/Withdrawal approved and booked to cash flow');
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

function expense_reject(int $expenseId): void
{
    db()->prepare("UPDATE fin_expenses SET status = 'rejected', approved_by = ? WHERE id = ? AND status = 'pending'")
        ->execute([current_user()['id'], $expenseId]);
    log_activity('finance', 'update', 'fin_expenses', $expenseId, 'Expense rejected');
}

/**
 * FR-FN05: Purchase Request -> Purchase Order -> Goods Receipt -> stock.
 * Goods receipt is implemented by simply calling stock_receive() (the same
 * Warehouse function Dairy/Poultry/Fish use) — Finance never writes to
 * warehouse tables directly, same "single choke point" rule as everywhere else.
 */
function purchase_request_create(array $data): int
{
    $stmt = db()->prepare(
        "INSERT INTO fin_purchase_requests (stock_item_id, quantity, justification, requested_by, status)
         VALUES (?, ?, ?, ?, 'pending')"
    );
    $stmt->execute([$data['stock_item_id'], $data['quantity'], $data['justification'] ?: null, current_user()['id']]);
    $id = (int) db()->lastInsertId();
    log_activity('finance', 'create', 'fin_purchase_requests', $id, 'Purchase request raised');
    return $id;
}

// BR-FN02: above threshold requires approval before it can be "sent" — this
// simplified flow creates the PO directly in 'pending_approval' or 'approved'
// depending on the configurable threshold in `settings`.
function purchase_order_create_from_request(int $requestId, string $supplierName, float $unitPrice): int
{
    $pr = db()->query("SELECT * FROM fin_purchase_requests WHERE id = " . (int) $requestId)->fetch();
    if (!$pr || $pr['status'] !== 'pending') {
        throw new RuntimeException('Purchase request is not pending.');
    }

    $threshold = (float) (setting_get('po_approval_threshold') ?? 10000);
    $total = $unitPrice * (float) $pr['quantity'];
    $status = $total > $threshold ? 'pending_approval' : 'approved';
    $matReqId = !empty($pr['material_request_id']) ? (int)$pr['material_request_id'] : null;

    db()->beginTransaction();
    try {
        $stmt = db()->prepare(
            "INSERT INTO fin_purchase_orders (purchase_request_id, material_request_id, supplier_name, stock_item_id, quantity, unit_price, total_amount, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$requestId, $matReqId, $supplierName, $pr['stock_item_id'], $pr['quantity'], $unitPrice, $total, $status, current_user()['id']]);
        $poId = (int) db()->lastInsertId();

        db()->prepare("UPDATE fin_purchase_requests SET status = 'converted_to_po' WHERE id = ?")->execute([$requestId]);
        db()->commit();
        log_activity('finance', 'create', 'fin_purchase_orders', $poId, "PO created for {$supplierName}, total {$total} ETB (Status: {$status})");
        return $poId;
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

function purchase_order_approve(int $poId): void
{
    db()->prepare("UPDATE fin_purchase_orders SET status = 'approved', approved_by = ? WHERE id = ? AND status = 'pending_approval'")
        ->execute([current_user()['id'], $poId]);
    log_activity('finance', 'update', 'fin_purchase_orders', $poId, 'PO approved (BR-FN02)');
}

// Goods receipt: sends PO to Warehouse for physical check and receipt
function purchase_order_receive(int $poId, int $warehouseId): void
{
    $po = db()->query("SELECT * FROM fin_purchase_orders WHERE id = " . (int) $poId)->fetch();
    if (!$po || !in_array($po['status'], ['approved', 'sent'], true)) {
        throw new RuntimeException('Purchase order must be approved before receiving goods.');
    }

    db()->beginTransaction();
    try {
        // Create pending Store receiving record for Storekeeper to physically confirm
        $dispatchCode = 'PO-' . $poId . '-' . date('Ymd');
        $matReqId = !empty($po['material_request_id']) ? (int)$po['material_request_id'] : null;

        $stmt = db()->prepare(
            "INSERT INTO wh_store_receiving
             (dispatch_code, source_type, source_reference, material_request_id, warehouse_id, stock_item_id, sent_quantity, sent_by, status)
             VALUES (?, 'purchase', ?, ?, ?, ?, ?, ?, 'pending_receipt')"
        );
        $stmt->execute([
            $dispatchCode, 'PO#' . $poId . ' ' . ($po['supplier_name'] ?? ''),
            $matReqId, $warehouseId, (int) $po['stock_item_id'], (float) $po['quantity'],
            current_user()['id']
        ]);

        // Mark PO as pending_receipt / sent_to_warehouse
        db()->prepare("UPDATE fin_purchase_orders SET status = 'pending_receipt' WHERE id = ?")
            ->execute([$poId]);

        // Notify storekeeper
        $itemName = db()->query("SELECT name FROM wh_stock_items WHERE id = " . (int)$po['stock_item_id'])->fetchColumn() ?: "Item";
        notify_targeted(
            'warehouse',
            "Purchased goods from {$po['supplier_name']} (PO#{$poId}, {$po['quantity']} units of {$itemName}) sent to Store for physical check.",
            'high', null, 8, null, '/aleltu-eifms/modules/warehouse/receiving.php'
        );

        db()->commit();
        log_activity('finance', 'update', 'fin_purchase_orders', $poId,
            "Purchase marked purchased & sent to Warehouse receiving — PO#{$poId} from {$po['supplier_name']}");
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

function budget_variance(array $budget): array
{
    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM fin_transactions WHERE unit_tag = ? AND direction = 'outflow' AND transaction_date BETWEEN ? AND ?"
    );
    $stmt->execute([$budget['unit_tag'], $budget['period_start'], $budget['period_end']]);
    $actual = (float) $stmt->fetchColumn();
    $planned = (float) $budget['planned_amount'];
    return ['actual' => $actual, 'variance' => $planned - $actual, 'percent_used' => $planned > 0 ? round(($actual / $planned) * 100, 1) : 0];
}

// BR-FN03: payroll cannot be finalized until attendance for the period is
// fully present (locked). This simplified version just checks that every
// active employee has at least one attendance row somewhere in the period.
function payroll_can_finalize(string $start, string $end): bool
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM employees e
         WHERE e.status = 'active' AND NOT EXISTS (
            SELECT 1 FROM attendance a WHERE a.employee_id = e.id AND a.attendance_date BETWEEN ? AND ?
         )"
    );
    $stmt->execute([$start, $end]);
    return (int) $stmt->fetchColumn() === 0;
}

function payroll_run_create(string $start, string $end): int
{
    $stmt = db()->prepare("INSERT INTO fin_payroll_runs (period_start, period_end, status, processed_by) VALUES (?, ?, 'draft', ?)");
    $stmt->execute([$start, $end, current_user()['id']]);
    $id = (int) db()->lastInsertId();
    log_activity('finance', 'create', 'fin_payroll_runs', $id, "Payroll run created {$start} to {$end}");
    return $id;
}

function payroll_finalize(int $runId): void
{
    $run = db()->query("SELECT * FROM fin_payroll_runs WHERE id = " . (int) $runId)->fetch();
    if (!$run) throw new RuntimeException('Payroll run not found.');

    if (!payroll_can_finalize($run['period_start'], $run['period_end'])) {
        throw new RuntimeException('Not every active employee has attendance recorded for this period yet (BR-FN03).');
    }

    db()->beginTransaction();
    try {
        $employees = db()->query("SELECT * FROM employees WHERE status = 'active'")->fetchAll();
        foreach ($employees as $emp) {
            $stmt = db()->prepare("SELECT COUNT(*) FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ? AND status IN ('present', 'half_day')");
            $stmt->execute([$emp['id'], $run['period_start'], $run['period_end']]);
            $daysPresent = (int) $stmt->fetchColumn();
            
            $monthlyBase = (float) ($emp['base_salary'] ?? 0);
            $dailyRate = round($monthlyBase / 26, 2);
            $netPay = round($dailyRate * $daysPresent, 2);

            db()->prepare(
                "INSERT INTO fin_payslips (payroll_run_id, employee_id, base_salary, daily_rate, days_present, net_pay, total_paid) VALUES (?, ?, ?, ?, ?, ?, 0)"
            )->execute([$runId, $emp['id'], $monthlyBase, $dailyRate, $daysPresent, $netPay]);
        }
        db()->prepare("UPDATE fin_payroll_runs SET status = 'finalized', finalized_at = NOW() WHERE id = ?")->execute([$runId]);
        db()->commit();
        log_activity('finance', 'update', 'fin_payroll_runs', $runId, 'Payroll finalized (BR-FN03 satisfied)');
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

function setting_get(string $key): ?string
{
    $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val === false ? null : $val;
}

function setting_set(string $key, string $value): void
{
    db()->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
        ->execute([$key, $value]);
}

/* ===================== FISH FARM (SAD section 5.3) ===================== */

function pond_register(string $name, ?float $areaSqM): int
{
    $stmt = db()->prepare("INSERT INTO fish_ponds (name, area_sq_m, status) VALUES (?, ?, 'empty')");
    $stmt->execute([$name, $areaSqM ?: null]);
    $id = (int) db()->lastInsertId();
    log_activity('fish', 'create', 'fish_ponds', $id, "Pond '{$name}' registered");
    return $id;
}

function fish_stock_pond(int $pondId, string $species, string $date, int $quantity, ?float $density): int
{
    db()->beginTransaction();
    try {
        $stmt = db()->prepare(
            "INSERT INTO fish_stocks (pond_id, species, stocking_date, stocking_quantity, stocking_density_per_sq_m, status)
             VALUES (?, ?, ?, ?, ?, 'active')"
        );
        $stmt->execute([$pondId, $species, $date, $quantity, $density ?: null]);
        $id = (int) db()->lastInsertId();
        db()->prepare("UPDATE fish_ponds SET status = 'stocked' WHERE id = ?")->execute([$pondId]);
        db()->commit();
        log_activity('fish', 'create', 'fish_stocks', $id, "Stocked {$quantity} {$species}");
        return $id;
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

// BR-F01: a reading outside the safe range for the species auto-flags —
// this is the one domain where a passive log isn't good enough; it raises
// a High-priority notification, not just a record.
function fish_water_quality_thresholds(): array
{
    // [min, max] safe ranges — tilapia-typical defaults, adjustable later via `settings`.
    return [
        'ph' => [6.5, 9.0],
        'dissolved_oxygen_mg_l' => [4.0, 100],
        'temperature_celsius' => [20.0, 32.0],
    ];
}

function fish_record_water_quality(int $pondId, array $data): void
{
    $t = fish_water_quality_thresholds();
    $ph = $data['ph'] !== '' ? (float) $data['ph'] : null;
    $do = $data['dissolved_oxygen_mg_l'] !== '' ? (float) $data['dissolved_oxygen_mg_l'] : null;
    $temp = $data['temperature_celsius'] !== '' ? (float) $data['temperature_celsius'] : null;

    $withinRange = true;
    if ($ph !== null && ($ph < $t['ph'][0] || $ph > $t['ph'][1])) $withinRange = false;
    if ($do !== null && $do < $t['dissolved_oxygen_mg_l'][0]) $withinRange = false;
    if ($temp !== null && ($temp < $t['temperature_celsius'][0] || $temp > $t['temperature_celsius'][1])) $withinRange = false;

    $stmt = db()->prepare(
        "INSERT INTO fish_water_quality_readings (pond_id, reading_at, ph, dissolved_oxygen_mg_l, temperature_celsius, within_safe_range, recorded_by)
         VALUES (?, NOW(), ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$pondId, $ph, $do, $temp, $withinRange ? 1 : 0, current_user()['id']]);
    log_activity('fish', 'create', 'fish_water_quality_readings', $pondId, $withinRange ? 'Water quality reading recorded (within range)' : 'Water quality OUT OF RANGE');

    if (!$withinRange) {
        $pondName = db()->query("SELECT name FROM fish_ponds WHERE id = " . (int) $pondId)->fetchColumn();
        db()->prepare("INSERT INTO notifications (module, priority, message) VALUES ('fish', 'high', ?)")
            ->execute(["Water quality out of safe range in pond: {$pondName}"]);
    }
}

function fish_log_feeding(int $fishStockId, float $quantityKg, string $feedType): void
{
    $stmt = db()->prepare(
        "INSERT INTO fish_feeding_logs (fish_stock_id, scheduled_at, completed_at, quantity_kg, feed_type, status)
         VALUES (?, NOW(), NOW(), ?, ?, 'completed')"
    );
    $stmt->execute([$fishStockId, $quantityKg, $feedType ?: null]);
    log_activity('fish', 'create', 'fish_feeding_logs', $fishStockId, "Fed {$quantityKg}kg {$feedType}");
}

function fish_record_growth_sample(int $fishStockId, string $date, int $sampleSize, float $avgWeightG): void
{
    $stmt = db()->prepare("INSERT INTO fish_growth_samples (fish_stock_id, sample_date, sample_size, average_weight_g) VALUES (?, ?, ?, ?)");
    $stmt->execute([$fishStockId, $date, $sampleSize, $avgWeightG]);
    log_activity('fish', 'create', 'fish_growth_samples', $fishStockId, "Growth sample: {$avgWeightG}g avg over {$sampleSize} fish");
}

function fish_record_mortality(int $fishStockId, string $date, int $count, string $cause): void
{
    if ($count <= 0) throw new InvalidArgumentException('Mortality count must be greater than zero.');

    db()->beginTransaction();
    try {
        // Lock the stock row before validating against live count (same
        // rationale as poultry_record_mortality() and the payment/expense
        // fixes elsewhere in this pass): checking outside a lock lets two
        // concurrent mortality entries both pass validation against the
        // same stale live count and together exceed it.
        $stock = db()->prepare("SELECT stocking_quantity, cumulative_mortality FROM fish_stocks WHERE id = ? FOR UPDATE");
        $stock->execute([$fishStockId]);
        $s = $stock->fetch();
        if ($s) {
            $live = (int)$s['stocking_quantity'] - (int)$s['cumulative_mortality'];
            if ($count > $live) {
                throw new RuntimeException(
                    current_locale() === 'am'
                        ? "ሊመዘገብ የሚፈለገው ሞት ({$count}) ካሉት ህያው ዓሳዎች ({$live}) በዛ። እባክዎ ቁጥሩን ያረጋግጡ።"
                        : "Cannot record {$count} fish mortality — only {$live} live fish in this stock."
                );
            }
        }

        db()->prepare("INSERT INTO fish_mortality_logs (fish_stock_id, log_date, count, suspected_cause, recorded_by) VALUES (?, ?, ?, ?, ?)")
            ->execute([$fishStockId, $date, $count, $cause ?: null, current_user()['id']]);
        db()->prepare("UPDATE fish_stocks SET cumulative_mortality = cumulative_mortality + ? WHERE id = ?")
            ->execute([$count, $fishStockId]);
        db()->commit();
        log_activity('fish', 'create', 'fish_mortality_logs', $fishStockId, "{$count} mortality logged ({$cause})");
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}


// Estimated live biomass = live count x latest average sample weight —
// used to sanity-check a harvest doesn't exceed what's actually in the pond.
function fish_estimated_biomass_kg(array $stock): float
{
    $stmt = db()->prepare("SELECT average_weight_g FROM fish_growth_samples WHERE fish_stock_id = ? ORDER BY sample_date DESC LIMIT 1");
    $stmt->execute([$stock['id']]);
    $avgWeightG = (float) ($stmt->fetchColumn() ?: 0);
    $liveCount = (int) $stock['stocking_quantity'] - (int) $stock['cumulative_mortality'];
    return round(($avgWeightG / 1000) * max(0, $liveCount), 2);
}

// FR-FS06: harvest event moves stock into Warehouse as sellable stock, via
// the same stock_receive() every other module uses — Fish never writes to
// warehouse tables directly. BR-F02: quantity can't exceed estimated biomass.
// Records a harvest. "Warehouse" is resolved automatically from the item's
// product class (single-store setup) — the caller no longer picks it.
// "Full harvest" (closing the pond) is a checkbox on the form, not a
// grade/type dropdown; grade is not tracked anymore per client request.
function fish_harvest(int $fishStockId, string $date, float $quantityKg, bool $isFinalHarvest, int $stockItemId): void
{
    db()->beginTransaction();
    try {
        // Lock the stock row before checking against estimated biomass (same
        // rationale as the mortality fixes above) so two concurrent harvest
        // entries can't both pass the "doesn't exceed biomass" check against
        // the same stale figure — §31's "❌ impossible harvest".
        $stock = db()->prepare("SELECT * FROM fish_stocks WHERE id = ? FOR UPDATE");
        $stock->execute([$fishStockId]);
        $stock = $stock->fetch();
        if (!$stock) throw new RuntimeException('Fish stock not found.');

        $biomass = fish_estimated_biomass_kg($stock);
        if ($biomass > 0 && $quantityKg > $biomass) {
            throw new RuntimeException("Harvest of {$quantityKg}kg exceeds estimated live biomass of {$biomass}kg (BR-F02).");
        }

        $type = $isFinalHarvest ? 'full' : 'partial';

        db()->prepare(
            "INSERT INTO fish_harvest_records (fish_stock_id, harvest_date, quantity_kg, grade, type, recorded_by) VALUES (?, ?, ?, NULL, ?, ?)"
        )->execute([$fishStockId, $date, $quantityKg, $type, current_user()['id']]);

        db()->prepare("UPDATE fish_stocks SET cumulative_harvested_kg = cumulative_harvested_kg + ? WHERE id = ?")
            ->execute([$quantityKg, $fishStockId]);

        if ($type === 'full') {
            db()->prepare("UPDATE fish_stocks SET status = 'closed' WHERE id = ?")->execute([$fishStockId]);
            db()->prepare("UPDATE fish_ponds SET status = 'empty' WHERE id = ?")->execute([$stock['pond_id']]);
        } else {
            db()->prepare("UPDATE fish_ponds SET status = 'partially_harvested' WHERE id = ?")->execute([$stock['pond_id']]);
        }

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    // Outside the transaction: stock_receive() manages its own (PDO doesn't nest).
    $warehouseId = resolve_warehouse_for_item($stockItemId);
    stock_receive($warehouseId, $stockItemId, $quantityKg, 'fish');
    log_activity('fish', 'create', 'fish_harvest_records', $fishStockId, "Harvested {$quantityKg}kg, moved to warehouse");
}

/* ===================== FEED PRODUCTION (SAD section 5.4) ===================== */

function feed_plot_register(string $name, ?float $areaHectares): int
{
    $stmt = db()->prepare("INSERT INTO feed_plots (name, area_hectares) VALUES (?, ?)");
    $stmt->execute([$name, $areaHectares ?: null]);
    $id = (int) db()->lastInsertId();
    log_activity('feed_production', 'create', 'feed_plots', $id, "Plot '{$name}' registered");
    return $id;
}

function feed_plant(int $plotId, string $cropType, string $plantingDate, ?string $expectedHarvest): int
{
    $stmt = db()->prepare(
        "INSERT INTO feed_planting_records (plot_id, crop_type, planting_date, expected_harvest_date, recorded_by, status)
         VALUES (?, ?, ?, ?, ?, 'growing')"
    );
    $stmt->execute([$plotId, $cropType, $plantingDate, $expectedHarvest ?: null, current_user()['id']]);
    $id = (int) db()->lastInsertId();
    log_activity('feed_production', 'create', 'feed_planting_records', $id, "Planted {$cropType}");
    return $id;
}

// §27: resolves the correct wh_stock_items row for a harvested crop by its
// crop_type name, so modules/feed_production/distribution.php no longer
// needs a user-facing "As Stock Item" dropdown asking the Feed Production
// Manager to manually pick which catalog entry their own harvest
// corresponds to. Reuses the same case-insensitive-name-match-or-auto-register
// pattern already established in dept_material_request_create() for custom
// item names, rather than introducing a second, different mechanism.
function feed_resolve_stock_item_for_crop(string $cropType): int
{
    $cropType = trim($cropType);
    $existing = db()->prepare("SELECT id FROM wh_stock_items WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $existing->execute([$cropType]);
    $id = $existing->fetchColumn();
    if ($id) {
        return (int) $id;
    }

    $sku = 'CROP-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $cropType), 0, 5)) . '-' . rand(100, 999);
    db()->prepare("INSERT INTO wh_stock_items (sku, name, product_class, unit, is_perishable) VALUES (?, ?, 'feed', 'KG', 0)")
        ->execute([$sku, $cropType]);
    return (int) db()->lastInsertId();
}

// FR-FP02: harvest yield creates a Feed Warehouse receipt tagged
// "internal_grown" (BR-FP01) — via the same stock_receive() every other
// module uses, never a direct warehouse table write.
// Records a harvest. Per BR-FP02, this does NOT touch warehouse stock —
// harvested crop only becomes real inventory once it's actually sent to
// Store (see distribution.php / store_send_to_warehouse). Previously this
// function credited stock immediately AND distribution.php let you send
// the same batch again, silently double-counting feed stock.
function feed_harvest(int $plantingRecordId, string $harvestDate, float $yieldKg): void
{
    db()->beginTransaction();
    try {
        db()->prepare(
            "INSERT INTO feed_harvest_records (planting_record_id, harvest_date, yield_kg, recorded_by) VALUES (?, ?, ?, ?)"
        )->execute([$plantingRecordId, $harvestDate, $yieldKg, current_user()['id']]);

        db()->prepare("UPDATE feed_planting_records SET status = 'harvested' WHERE id = ?")->execute([$plantingRecordId]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    log_activity('feed_production', 'create', 'feed_harvest_records', $plantingRecordId, "Harvested {$yieldKg}kg — awaiting transfer to Store");
}

/**
 * FR-FP04/FR-FP05, BR-FP02: fulfils an internal distribution request from
 * current Feed Warehouse stock. If stock can't fully cover it, the
 * shortfall is never silently dropped — it's flagged
 * "partially_fulfilled_backordered", and optionally raises a Finance
 * Purchase Request for the gap (soft-linked; works even if the Finance
 * module tables aren't installed).
 */
function feed_distribution_request_create(string $unit, float $quantityKg): int
{
    $stmt = db()->prepare(
        "INSERT INTO feed_distribution_requests (requesting_unit, quantity_requested_kg, requested_by, status) VALUES (?, ?, ?, 'pending')"
    );
    $stmt->execute([$unit, $quantityKg, current_user()['id']]);
    $id = (int) db()->lastInsertId();
    log_activity('feed_production', 'create', 'feed_distribution_requests', $id, "{$unit} requested {$quantityKg}kg feed");
    return $id;
}

function feed_distribution_request_fulfill(int $requestId, int $warehouseId, int $stockItemId): void
{
    $request = db()->query("SELECT * FROM feed_distribution_requests WHERE id = " . (int) $requestId)->fetch();
    if (!$request || $request['status'] !== 'pending') {
        throw new RuntimeException('This request is not pending.');
    }

    $available = stock_current($warehouseId, $stockItemId);
    $needed = (float) $request['quantity_requested_kg'];
    $toIssue = min($available, $needed);
    $shortfall = $needed - $toIssue;

    if ($toIssue > 0) {
        stock_issue($warehouseId, $stockItemId, $toIssue, $request['requesting_unit']);
    }

    if ($shortfall <= 0) {
        db()->prepare("UPDATE feed_distribution_requests SET quantity_fulfilled_kg = ?, status = 'fulfilled' WHERE id = ?")
            ->execute([$toIssue, $requestId]);
        log_activity('feed_production', 'update', 'feed_distribution_requests', $requestId, "Fully fulfilled: {$toIssue}kg issued to {$request['requesting_unit']}");
        return;
    }

    // BR-FP02: shortfall never silently dropped — try to raise a Finance
    // Purchase Request for the gap; degrade gracefully if Finance isn't installed.
    $purchaseRequestId = null;
    try {
        $stmt = db()->prepare(
            "INSERT INTO fin_purchase_requests (stock_item_id, quantity, justification, requested_by, status) VALUES (?, ?, ?, ?, 'pending')"
        );
        $stmt->execute([$stockItemId, $shortfall, "Feed shortfall for {$request['requesting_unit']} distribution request #{$requestId}", current_user()['id']]);
        $purchaseRequestId = (int) db()->lastInsertId();
    } catch (Throwable $e) {
        // Finance module's fin_purchase_requests table isn't installed yet — that's fine,
        // the shortfall is still visible and explicit on the distribution request itself.
    }

    $status = $purchaseRequestId ? 'triggered_purchase' : 'partially_fulfilled_backordered';
    db()->prepare("UPDATE feed_distribution_requests SET quantity_fulfilled_kg = ?, status = ?, purchase_request_id = ? WHERE id = ?")
        ->execute([$toIssue, $status, $purchaseRequestId, $requestId]);
    log_activity('feed_production', 'update', 'feed_distribution_requests', $requestId, "Partial: {$toIssue}kg issued, {$shortfall}kg shortfall ({$status})");
}

/* ===================== VETERINARY (cross-unit) ===================== */

const VET_SUBJECT_TYPES = ['dairy_cow' => 'Dairy Cow', 'poultry_flock' => 'Poultry Flock', 'fish_stock' => 'Fish Stock'];

// Resolves a polymorphic subject to a human label, e.g. "Cow AF-1001" or
// "Poultry House 3 flock". Used everywhere a vet record needs to display
// what it's actually about.
function vet_subject_label(string $type, int $id): string
{
    switch ($type) {
        case 'dairy_cow':
            $tag = db()->query("SELECT tag_number FROM dairy_cows WHERE id = {$id}")->fetchColumn();
            return $tag ? "Cow {$tag}" : "Cow #{$id} (not found)";
        case 'poultry_flock':
            $row = db()->query("SELECT h.name FROM poultry_flocks f JOIN poultry_houses h ON h.id = f.poultry_house_id WHERE f.id = {$id}")->fetch();
            return $row ? "Flock in {$row['name']}" : "Flock #{$id} (not found)";
        case 'fish_stock':
            $row = db()->query("SELECT p.name, s.species FROM fish_stocks s JOIN fish_ponds p ON p.id = s.pond_id WHERE s.id = {$id}")->fetch();
            return $row ? "{$row['species']} in {$row['name']}" : "Fish stock #{$id} (not found)";
        default:
            return "Unknown subject";
    }
}

function vet_subject_options(string $type): array
{
    switch ($type) {
        case 'dairy_cow':
            return db()->query("SELECT id, tag_number AS label FROM dairy_cows WHERE lifecycle_status NOT IN ('sold','deceased') ORDER BY tag_number")->fetchAll();
        case 'poultry_flock':
            return db()->query(
                "SELECT f.id, CONCAT(h.name, ' - ', f.breed) AS label FROM poultry_flocks f JOIN poultry_houses h ON h.id = f.poultry_house_id WHERE f.status = 'active' ORDER BY h.name"
            )->fetchAll();
        case 'fish_stock':
            return db()->query(
                "SELECT s.id, CONCAT(p.name, ' - ', s.species) AS label FROM fish_stocks s JOIN fish_ponds p ON p.id = s.pond_id WHERE s.status = 'active' ORDER BY p.name"
            )->fetchAll();
        default:
            return [];
    }
}

function vet_record_health(string $subjectType, int $subjectId, string $date, string $condition, string $treatment, ?string $photoPath = null): int
{
    $stmt = db()->prepare(
        "INSERT INTO vet_health_records (subject_type, subject_id, record_date, condition_name, treatment, photo_path, vet_id) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$subjectType, $subjectId, $date, $condition ?: null, $treatment ?: null, $photoPath ?: null, current_user()['id']]);
    $id = (int) db()->lastInsertId();
    log_activity('veterinary', 'create', 'vet_health_records', $id, 'Health record: ' . vet_subject_label($subjectType, $subjectId));

    // Cross-unit side effect: mark the cow as "sick" so Dairy's own views
    // reflect it too (BR: overlay flag, any stage — SAD section 6.1).
    if ($subjectType === 'dairy_cow') {
        db()->prepare("UPDATE dairy_cows SET is_sick = 1 WHERE id = ?")->execute([$subjectId]);
    }
    return $id;
}

function vet_schedule_vaccination(string $subjectType, int $subjectId, string $vaccineName, string $scheduledDate, ?string $scheduledTime = '09:00:00', ?string $photoPath = null): int
{
    $stmt = db()->prepare(
        "INSERT INTO vet_vaccination_records (subject_type, subject_id, vaccine_name, scheduled_date, scheduled_time, photo_path, status) VALUES (?, ?, ?, ?, ?, ?, 'scheduled')"
    );
    $stmt->execute([$subjectType, $subjectId, $vaccineName, $scheduledDate, $scheduledTime ?: '09:00:00', $photoPath ?: null]);
    $id = (int) db()->lastInsertId();
    log_activity('veterinary', 'create', 'vet_vaccination_records', $id, "Vaccination scheduled: {$vaccineName} for " . vet_subject_label($subjectType, $subjectId));
    return $id;
}

function vet_administer_vaccination(int $recordId): void
{
    db()->prepare("UPDATE vet_vaccination_records SET status = 'administered', administered_date = CURDATE(), vet_id = ? WHERE id = ?")
        ->execute([current_user()['id'], $recordId]);
    log_activity('veterinary', 'update', 'vet_vaccination_records', $recordId, 'Vaccination administered');
}

// SAD section 9: "vaccination due within N days" notification trigger,
// runnable on demand from the Veterinary dashboard (a real cron/scheduled
// task is a Settings/ops concern outside this file's scope).
function vet_vaccinations_due(int $withinDays = 7): array
{
    $stmt = db()->prepare(
        "SELECT * FROM vet_vaccination_records WHERE status = 'scheduled' AND scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY) ORDER BY scheduled_date, scheduled_time"
    );
    $stmt->execute([$withinDays]);
    return $stmt->fetchAll();
}

// BR-P02: a disease incident on a poultry flock can quarantine the whole
// house, which then blocks bird transfers out of it (enforced here, and
// checked again wherever a transfer would happen).
function vet_record_disease_incident(string $subjectType, int $subjectId, string $diseaseName, string $date, bool $quarantine, string $notes, ?string $photoPath = null): int
{
    db()->beginTransaction();
    try {
        $stmt = db()->prepare(
            "INSERT INTO vet_disease_incidents (subject_type, subject_id, disease_name, detected_date, quarantine_flag, notes, photo_path, vet_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$subjectType, $subjectId, $diseaseName, $date, $quarantine ? 1 : 0, $notes ?: null, $photoPath ?: null, current_user()['id']]);
        $id = (int) db()->lastInsertId();

        if ($quarantine && $subjectType === 'poultry_flock') {
            $houseId = db()->query("SELECT poultry_house_id FROM poultry_flocks WHERE id = {$subjectId}")->fetchColumn();
            if ($houseId) {
                db()->prepare("UPDATE poultry_houses SET quarantined = 1 WHERE id = ?")->execute([$houseId]);
            }
        }
        db()->commit();
        log_activity('veterinary', 'create', 'vet_disease_incidents', $id, "Disease incident: {$diseaseName}" . ($quarantine ? ' (quarantine applied, BR-P02)' : ''));
        return $id;
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

function vet_clear_quarantine(int $incidentId): void
{
    $incident = db()->query("SELECT * FROM vet_disease_incidents WHERE id = " . (int) $incidentId)->fetch();
    if (!$incident) throw new RuntimeException('Incident not found.');

    db()->beginTransaction();
    try {
        db()->prepare("UPDATE vet_disease_incidents SET quarantine_cleared_date = CURDATE() WHERE id = ?")->execute([$incidentId]);

        if ($incident['subject_type'] === 'poultry_flock') {
            $houseId = db()->query("SELECT poultry_house_id FROM poultry_flocks WHERE id = " . (int) $incident['subject_id'])->fetchColumn();
            if ($houseId) {
                db()->prepare("UPDATE poultry_houses SET quarantined = 0 WHERE id = ?")->execute([$houseId]);
            }
        }
        db()->commit();
        log_activity('veterinary', 'update', 'vet_disease_incidents', $incidentId, 'Quarantine cleared (BR-P02)');
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

/* ===================== DISTRIBUTION (SAD section 5.6) ===================== */

function vehicle_register(string $plate, ?string $type, ?float $capacityKg, ?string $driverName = null, ?string $driverPhone = null): int
{
    $plate = trim($plate);
    if (empty($plate)) {
        throw new InvalidArgumentException('Plate number is required.');
    }
    $userId = (function_exists('current_user') && current_user()) ? (int)current_user()['id'] : 1;
    $stmt = db()->prepare(
        "INSERT INTO dist_vehicles (plate_number, vehicle_type, driver_name, driver_phone, capacity_kg, status) 
         VALUES (?, ?, ?, ?, ?, 'available')"
    );
    $stmt->execute([$plate, $type ?: null, $driverName ?: null, $driverPhone ?: null, $capacityKg ?: null]);
    $id = (int) db()->lastInsertId();
    log_activity('distribution', 'create', 'dist_vehicles', $id, "Vehicle {$plate} registered (Driver: {$driverName})");
    return $id;
}

/**
 * Creates a vehicle dispatch for selling/delivering farm products.
 * Deducts stock from warehouse 1 and calculates total sales value.
 */
function dispatch_create(int $vehicleId, ?string $driverName, string $destination, int $warehouseId, int $stockItemId, float $quantity, float $unitPrice = 0.00): int
{
    if ($quantity <= 0) {
        throw new InvalidArgumentException('Dispatch quantity must be greater than zero.');
    }

    $totalPrice = $quantity * max(0, $unitPrice);

    // Deduct stock from warehouse. Outside the transaction below: stock_issue()
    // manages its own transaction/row-locking internally (PDO doesn't nest
    // transactions) — same established pattern as fish_harvest()'s call to
    // stock_receive(). Accepted trade-off: if stock_issue() succeeds but the
    // dist_dispatches insert below then fails, stock is deducted with no
    // dispatch record — an orphaned movement rather than a duplicate/negative
    // one. Changing this would mean either nested-transaction emulation
    // (savepoints) or reordering + compensating logic, which is an
    // architecture change, not a local fix, so it was not made in this pass.
    stock_issue($warehouseId, $stockItemId, $quantity, 'distribution');

    $ownsTx = false;
    if (!db()->inTransaction()) {
        db()->beginTransaction();
        $ownsTx = true;
    }
    try {
        $userId = (function_exists('current_user') && current_user()) ? (int)current_user()['id'] : 1;
        $stmt = db()->prepare(
            "INSERT INTO dist_dispatches 
             (vehicle_id, driver_name, destination_pos_location, warehouse_id, stock_item_id, quantity_dispatched, unit_price, total_price, amount_paid, payment_status, planned_dispatch_at, dispatched_at, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0.00, 'unpaid', NOW(), NOW(), 'dispatched', ?)"
        );
        $stmt->execute([$vehicleId, $driverName ?: null, $destination, $warehouseId, $stockItemId, $quantity, $unitPrice, $totalPrice, $userId]);
        $id = (int) db()->lastInsertId();

        db()->prepare("UPDATE dist_vehicles SET status = 'in_use' WHERE id = ?")->execute([$vehicleId]);

        if ($ownsTx && db()->inTransaction()) {
            db()->commit();
        }
        log_activity('distribution', 'create', 'dist_dispatches', $id, "Dispatched {$quantity} units to {$destination} (Total: {$totalPrice} ETB)");
        return $id;
    } catch (Throwable $e) {
        if ($ownsTx && db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
}

/**
 * Record a full or partial payment from driver / customer for dispatched goods.
 */
function dispatch_record_payment(int $dispatchId, float $amount, string $paymentMethod = 'cash', ?string $refNo = null, ?string $notes = null, int $receivedBy = 1): void
{
    if ($amount <= 0) {
        throw new InvalidArgumentException('Payment amount must be greater than zero.');
    }

    $ownsTx = false;
    if (!db()->inTransaction()) {
        db()->beginTransaction();
        $ownsTx = true;
    }
    try {
        // Lock the dispatch row before reading its paid/total amounts (BR-FN-lock):
        // reading and validating remaining balance outside a lock lets two
        // concurrent payments (e.g. a double-submit) both pass the "does not
        // exceed remaining balance" check against the same stale figure and
        // together overpay the dispatch.
        $disp = db()->prepare(
            "SELECT d.*, i.name AS item_name FROM dist_dispatches d
             JOIN wh_stock_items i ON i.id = d.stock_item_id
             WHERE d.id = ? FOR UPDATE"
        );
        $disp->execute([$dispatchId]);
        $disp = $disp->fetch();
        if (!$disp) {
            throw new RuntimeException('Dispatch record not found.');
        }

        $currentPaid = (float) $disp['amount_paid'];
        $totalPrice  = (float) $disp['total_price'];
        $remaining   = max(0, $totalPrice - $currentPaid);

        if ($amount > $remaining + 0.01) {
            throw new RuntimeException("Payment of {$amount} ETB exceeds remaining balance of {$remaining} ETB.");
        }

        $newPaid = $currentPaid + $amount;
        $newStatus = ($newPaid >= $totalPrice - 0.01) ? 'paid' : 'partial';

        // Update dispatch payment state
        db()->prepare("UPDATE dist_dispatches SET amount_paid = ?, payment_status = ?, payment_notes = ? WHERE id = ?")
            ->execute([$newPaid, $newStatus, $notes ?: null, $dispatchId]);

        // Insert into dist_dispatch_payments audit log
        $stmt = db()->prepare(
            "INSERT INTO dist_dispatch_payments (dispatch_id, amount, payment_method, reference_no, notes, received_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$dispatchId, $amount, $paymentMethod, $refNo ?: null, $notes ?: null, $receivedBy]);

        // Book cash/bank inflow in finance transactions
        finance_record_transaction(
            'inflow',
            $amount,
            'distribution',
            'product_sales',
            date('Y-m-d'),
            "Sales revenue for Dispatch #{$dispatchId} ({$disp['item_name']})",
            'dist_dispatches',
            $dispatchId,
            null,
            $paymentMethod
        );

        if ($ownsTx && db()->inTransaction()) {
            db()->commit();
        }
        log_activity('finance', 'create', 'dist_dispatch_payments', $dispatchId, "Recorded {$amount} ETB payment for dispatch #{$dispatchId} (Status: {$newStatus})");
    } catch (Throwable $e) {
        if ($ownsTx && db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
}

function dispatch_confirm_delivery(int $dispatchId, float $deliveredQuantity): void
{
    $dispatch = db()->query("SELECT * FROM dist_dispatches WHERE id = " . (int) $dispatchId)->fetch();
    if (!$dispatch || $dispatch['status'] !== 'dispatched') {
        throw new RuntimeException('This dispatch is not awaiting delivery confirmation.');
    }

    $variance = abs($deliveredQuantity - (float) $dispatch['quantity_dispatched']) > 0.01;
    $status = $variance ? 'partially_delivered' : 'delivered';

    $ownsTx = false;
    if (!db()->inTransaction()) {
        db()->beginTransaction();
        $ownsTx = true;
    }
    try {
        db()->prepare(
            "UPDATE dist_dispatches SET quantity_delivered = ?, variance_flagged = ?, status = ?, confirmed_at = NOW() WHERE id = ?"
        )->execute([$deliveredQuantity, $variance ? 1 : 0, $status, $dispatchId]);

        db()->prepare("UPDATE dist_vehicles SET status = 'available' WHERE id = ?")->execute([$dispatch['vehicle_id']]);

        if ($variance) {
            db()->prepare("INSERT INTO notifications (module, priority, message) VALUES ('distribution', 'high', ?)")
                ->execute(["Delivery variance on dispatch #{$dispatchId}: {$dispatch['quantity_dispatched']} dispatched, {$deliveredQuantity} delivered."]);
        }
        if ($ownsTx && db()->inTransaction()) {
            db()->commit();
        }
        log_activity('distribution', 'update', 'dist_dispatches', $dispatchId, "Delivery confirmed: {$deliveredQuantity} (dispatched {$dispatch['quantity_dispatched']})");
    } catch (Throwable $e) {
        if ($ownsTx && db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
}

/**
 * BR-DI02: a failed delivery requires an explicit stock-return entry — it
 * is never silently reversed. This is why it calls stock_receive() rather
 * than just decrementing a counter.
 */
function dispatch_mark_failed(int $dispatchId): void
{
    $dispatch = db()->query("SELECT * FROM dist_dispatches WHERE id = " . (int) $dispatchId)->fetch();
    if (!$dispatch || $dispatch['status'] !== 'dispatched') {
        throw new RuntimeException('This dispatch cannot be marked failed from its current status.');
    }

    db()->prepare("UPDATE dist_dispatches SET status = 'failed', confirmed_at = NOW() WHERE id = ?")->execute([$dispatchId]);
    db()->prepare("UPDATE dist_vehicles SET status = 'available' WHERE id = ?")->execute([$dispatch['vehicle_id']]);

    // Explicit stock-return entry — the whole dispatched quantity comes back.
    stock_receive((int) $dispatch['warehouse_id'], (int) $dispatch['stock_item_id'], (float) $dispatch['quantity_dispatched'], 'distribution_return');

    log_activity('distribution', 'update', 'dist_dispatches', $dispatchId, "Delivery failed — {$dispatch['quantity_dispatched']} returned to warehouse (BR-DI02)");
}

/**
 * SAD section 16.4: the single, narrow, outbound-only integration point
 * with the external Aleltu POS System. AF-EIFMS pushes confirmed delivery
 * data; it never reads sales, pricing, or customer data back. No live POS
 * endpoint is configured in this environment (see config/pos.php), so this
 * safely no-ops rather than failing the delivery confirmation.
 */
function dispatch_push_to_pos(int $dispatchId, float $deliveredQuantity, string $destination): void
{
    $webhookUrl = defined('POS_WEBHOOK_URL') ? POS_WEBHOOK_URL : '';
    if ($webhookUrl === '') {
        return; // not configured — safe no-op, matches the SAD's documented boundary
    }
    // A real integration would POST here. Left unimplemented deliberately:
    // wiring this to a live POS endpoint is a one-time configuration step,
    // not a code change, once Aleltu's POS team confirms the endpoint.
}

/* ===================== NOTIFICATIONS ===================== */

function notifications_unread_count(): int
{
    $user   = current_user();
    if (!$user) return 0;
    $userId = (int) $user['id'];
    $roleId = (int) ($user['role_id'] ?? 0);
    $isBroadcastRole = in_array($user['role'] ?? '', ['super_admin', 'general_manager'], true);

    // Use notification_reads junction table for per-user tracking (migration_v6)
    // A notification is "unread for this user" if no row exists in notification_reads for this user
    if ($isBroadcastRole) {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM notifications n
             WHERE NOT EXISTS (
                 SELECT 1 FROM notification_reads nr WHERE nr.notification_id = n.id AND nr.user_id = ?
             )"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM notifications n
         WHERE (
             n.target_user_id = ?
             OR n.target_role_id = ?
             OR (n.target_user_id IS NULL AND n.target_role_id IS NULL AND n.target_department_id IS NULL)
         )
         AND NOT EXISTS (
             SELECT 1 FROM notification_reads nr WHERE nr.notification_id = n.id AND nr.user_id = ?
         )"
    );
    $stmt->execute([$userId, $roleId, $userId]);
    return (int) $stmt->fetchColumn();
}

function notifications_recent(int $limit = 5): array
{
    $user   = current_user();
    if (!$user) return [];
    $userId = (int) $user['id'];
    $roleId = (int) ($user['role_id'] ?? 0);
    $isBroadcastRole = in_array($user['role'] ?? '', ['super_admin', 'general_manager'], true);

    if ($isBroadcastRole) {
        $stmt = db()->prepare("SELECT * FROM notifications ORDER BY created_at DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    $stmt = db()->prepare(
        "SELECT * FROM notifications
         WHERE target_user_id = ?
            OR target_role_id = ?
            OR (target_user_id IS NULL AND target_role_id IS NULL AND target_department_id IS NULL)
         ORDER BY created_at DESC LIMIT ?"
    );
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $roleId, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit,  PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function notification_mark_read(int $id): void
{
    // Route through the per-user notification_reads table, not the shared
    // notifications.is_read column. This function is not currently called
    // anywhere, but it is the obviously-named entry point a future
    // "mark this one notification read" button would reach for — leaving it
    // writing a shared column would silently reintroduce exactly the bug
    // the master prompt's §20/Test 7 warns against (one user's read state
    // marking a notification read for every other user targeted by it).
    $user = current_user();
    if (!$user) return;
    try {
        db()->prepare("INSERT IGNORE INTO notification_reads (notification_id, user_id) VALUES (?, ?)")
            ->execute([$id, (int)$user['id']]);
    } catch (Throwable) {}
}

function notifications_mark_all_read(): void
{
    // FIXED: scope to current user only — never mark other users' notifications
    $user = current_user();
    if (!$user) return;
    notifications_mark_all_read_for_user((int)$user['id'], (int)($user['role_id'] ?? 0));
}

function notifications_mark_all_read_for_user(int $userId, int $roleId): void
{
    // Mark all notifications targeted at this user as read in notification_reads
    try {
        db()->prepare(
            "INSERT IGNORE INTO notification_reads (notification_id, user_id)
             SELECT n.id, ?
             FROM notifications n
             WHERE (
                 n.target_user_id = ?
                 OR n.target_role_id = ?
                 OR (n.target_user_id IS NULL AND n.target_role_id IS NULL AND n.target_department_id IS NULL)
             )
             AND NOT EXISTS (
                 SELECT 1 FROM notification_reads nr WHERE nr.notification_id = n.id AND nr.user_id = ?
             )"
        )->execute([$userId, $userId, $roleId, $userId]);
    } catch (Throwable) {}
}

/* ===================== LOCALIZATION (Amharic / English) ===================== */

const SUPPORTED_LOCALES = ['en' => 'English', 'am' => 'አማርኛ (Amharic)'];

function current_locale(): string
{
    $user = current_user();
    $locale = $user['locale'] ?? ($_SESSION['guest_locale'] ?? 'en');
    return array_key_exists($locale, SUPPORTED_LOCALES) ? $locale : 'en';
}

/**
 * Translates a key for the shared chrome (nav, common buttons/labels).
 * Falls back to English if the key is missing in the active locale, and
 * falls back to the raw key itself if it's missing everywhere — so a
 * partial translation file never breaks a page, it just shows English
 * (or the key name) for whatever hasn't been translated yet.
 */
function t(string $key, ?string $fallback = null): string
{
    static $cache = [];
    $locale = current_locale();

    if (!isset($cache[$locale])) {
        $path = __DIR__ . "/lang/{$locale}.php";
        $cache[$locale] = file_exists($path) ? require $path : [];
    }
    if (!isset($cache['en'])) {
        $pathEn = __DIR__ . '/lang/en.php';
        $cache['en'] = file_exists($pathEn) ? require $pathEn : [];
    }

    return $cache[$locale][$key] ?? $cache['en'][$key] ?? $fallback ?? $key;
}

function set_user_locale(int $userId, string $locale): void
{
    if (!array_key_exists($locale, SUPPORTED_LOCALES)) {
        throw new InvalidArgumentException('Unsupported locale.');
    }
    db()->prepare("UPDATE users SET locale = ? WHERE id = ?")->execute([$locale, $userId]);
    // Keep the current session in sync immediately, without requiring re-login.
    if (isset($_SESSION['user']) && $_SESSION['user']['id'] === $userId) {
        $_SESSION['user']['locale'] = $locale;
    }
}

/* ===================== THEME (Light / Dark — FR-X05) ===================== */

function current_theme(): string
{
    $user = current_user();
    $theme = $user['theme'] ?? ($_SESSION['guest_theme'] ?? 'light');
    return $theme === 'dark' ? 'dark' : 'light';
}

function set_user_theme(int $userId, string $theme): void
{
    $theme = $theme === 'dark' ? 'dark' : 'light';
    db()->prepare("UPDATE users SET theme = ? WHERE id = ?")->execute([$theme, $userId]);
    if (isset($_SESSION['user']) && $_SESSION['user']['id'] === $userId) {
        $_SESSION['user']['theme'] = $theme;
    }
}

/* ===================== DEMO DATA SEEDER (admin-only, idempotent) ===================== */

/**
 * Generates realistic sample data across every module so a new install
 * isn't an empty shell. Safe to run more than once — everything checks
 * for existing records first and skips what's already there, so re-running
 * just fills in whatever's still missing rather than creating duplicates.
 *
 * Returns a log of what it did, so the admin page can show a summary.
 */
function seed_demo_data(): array
{
    $log = [];

    // ---------- Demo user accounts, one per role ----------
    $demoUsers = [
        ['manager@aleltufarm.com', 'Almaz Girma', 'general_manager', 'MGMT'],
        ['dairy@aleltufarm.com', 'Tesfaye Bekele', 'dairy_manager', 'DAIRY'],
        ['poultry@aleltufarm.com', 'Hana Worku', 'poultry_manager', 'MGMT'],
        ['fish@aleltufarm.com', 'Dawit Alemu', 'fish_manager', 'MGMT'],
        ['feed@aleltufarm.com', 'Selamawit Tadesse', 'feed_production_manager', 'MGMT'],
        ['vet@aleltufarm.com', 'Dr. Yonas Girma', 'veterinary_officer', 'MGMT'],
        ['warehouse@aleltufarm.com', 'Meron Assefa', 'warehouse_keeper', 'WH'],
        ['distribution@aleltufarm.com', 'Getachew Alemu', 'distribution_officer', 'WH'],
        ['finance@aleltufarm.com', 'Rahel Tesfaye', 'finance_officer', 'MGMT'],
        ['hr@aleltufarm.com', 'Bethlehem Kebede', 'hr_officer', 'MGMT'],
        ['milker@aleltufarm.com', 'Abebe Chala', 'field_worker', 'DAIRY'],
    ];
    $demoPassword = password_hash('Demo@2026', PASSWORD_DEFAULT);
    $created = 0;
    foreach ($demoUsers as [$email, $name, $roleName, $deptCode]) {
        $exists = db()->query("SELECT id FROM users WHERE email = " . db()->quote($email))->fetchColumn();
        if ($exists) continue;
        $roleId = db()->query("SELECT id FROM roles WHERE name = " . db()->quote($roleName))->fetchColumn();
        $deptId = db()->query("SELECT id FROM departments WHERE code = " . db()->quote($deptCode))->fetchColumn();
        db()->prepare("INSERT INTO users (name, email, password, role_id, department_id) VALUES (?, ?, ?, ?, ?)")
            ->execute([$name, $email, $demoPassword, $roleId, $deptId ?: null]);
        $created++;
    }
    $log[] = "Users: created {$created} demo accounts (all use password: Demo@2026)";

    // ---------- Dairy: cow groups + cows ----------
    $groupId = db()->query("SELECT id FROM dairy_cow_groups WHERE name = 'Milking Herd A'")->fetchColumn();
    if (!$groupId) {
        db()->exec("INSERT INTO dairy_cow_groups (name, pen_location) VALUES ('Milking Herd A', 'North Pen')");
        $groupId = db()->lastInsertId();
    }
    $cowSeed = [
        ['AF-1001', 'Bella', 'Holstein'], ['AF-1002', 'Tigist', 'Jersey'], ['AF-1003', 'Zeritu', 'Holstein'],
        ['AF-1004', 'Almaz', 'Jersey'], ['AF-1005', 'Sara', 'Holstein'],
    ];
    $cowsCreated = 0;
    $cowIds = [];
    foreach ($cowSeed as [$tag, $name, $breed]) {
        $id = db()->query("SELECT id FROM dairy_cows WHERE tag_number = " . db()->quote($tag))->fetchColumn();
        if (!$id) {
            db()->prepare("INSERT INTO dairy_cows (tag_number, name, sex, breed, cow_group_id, lifecycle_status, source) VALUES (?, ?, 'female', ?, ?, 'milking', 'born_on_farm')")
                ->execute([$tag, $name, $breed, $groupId]);
            $id = db()->lastInsertId();
            $cowsCreated++;
        }
        $cowIds[] = $id;
    }
    $log[] = "Dairy: {$cowsCreated} cows registered (5 total, group 'Milking Herd A')";

    // Milking entries for the last 5 days, so charts/reports have something to show.
    $sessionsCreated = 0;
    for ($i = 4; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        foreach (['morning', 'evening'] as $type) {
            $sessionId = db()->query("SELECT id FROM dairy_milking_sessions WHERE session_date = " . db()->quote($date) . " AND session_type = " . db()->quote($type))->fetchColumn();
            if (!$sessionId) {
                db()->prepare("INSERT INTO dairy_milking_sessions (session_date, session_type, recorded_by) VALUES (?, ?, 1)")->execute([$date, $type]);
                $sessionId = db()->lastInsertId();
                foreach ($cowIds as $cowId) {
                    $liters = round(mt_rand(80, 160) / 10, 1); // 8.0 - 16.0 L, realistic per-cow range
                    db()->prepare("INSERT INTO dairy_milk_yield_entries (milking_session_id, cow_id, yield_liters, recorded_by) VALUES (?, ?, ?, 1)")
                        ->execute([$sessionId, $cowId, $liters]);
                }
                $sessionsCreated++;
            }
        }
    }
    $log[] = "Dairy: {$sessionsCreated} milking sessions created across the last 5 days";

    // ---------- Poultry: houses + flock + egg collections + one mortality entry ----------
    $houseId = db()->query("SELECT id FROM poultry_houses WHERE name = 'House 1'")->fetchColumn();
    if (!$houseId) {
        db()->exec("INSERT INTO poultry_houses (name, capacity) VALUES ('House 1', 2000)");
        $houseId = db()->lastInsertId();
    }
    $flockId = db()->query("SELECT id FROM poultry_flocks WHERE poultry_house_id = {$houseId} AND status = 'active'")->fetchColumn();
    if (!$flockId) {
        db()->prepare("INSERT INTO poultry_flocks (poultry_house_id, breed, stocking_date, initial_population, status) VALUES (?, 'Layer Hybrid', DATE_SUB(CURDATE(), INTERVAL 90 DAY), 1800, 'active')")
            ->execute([$houseId]);
        $flockId = db()->lastInsertId();
    }
    $eggsCreated = 0;
    for ($i = 4; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $exists = db()->query("SELECT id FROM poultry_egg_collections WHERE flock_id = {$flockId} AND collection_date = " . db()->quote($date))->fetchColumn();
        if (!$exists) {
            db()->prepare("INSERT INTO poultry_egg_collections (flock_id, collection_date, whole_count, cracked_count, broken_count, recorded_by) VALUES (?, ?, ?, ?, ?, 1)")
                ->execute([$flockId, $date, mt_rand(1400, 1600), mt_rand(10, 30), mt_rand(5, 15)]);
            $eggsCreated++;
        }
    }
    $log[] = "Poultry: 1 house, 1 active flock (1,800 birds), {$eggsCreated} days of egg collection";

    // ---------- Fish: pond + stock ----------
    $pondId = db()->query("SELECT id FROM fish_ponds WHERE name = 'Pond A'")->fetchColumn();
    if (!$pondId) {
        db()->exec("INSERT INTO fish_ponds (name, area_sq_m, status) VALUES ('Pond A', 300, 'stocked')");
        $pondId = db()->lastInsertId();
        db()->prepare("INSERT INTO fish_stocks (pond_id, species, stocking_date, stocking_quantity, stocking_density_per_sq_m, status) VALUES (?, 'Nile Tilapia', DATE_SUB(CURDATE(), INTERVAL 60 DAY), 1500, 5, 'active')")
            ->execute([$pondId]);
        $log[] = "Fish: 1 pond stocked with 1,500 Nile Tilapia (60 days ago)";
    } else {
        $log[] = "Fish: Pond A already exists, skipped";
    }

    // ---------- Feed Production: a plot ----------
    $plotId = db()->query("SELECT id FROM feed_plots WHERE name = 'North Field'")->fetchColumn();
    if (!$plotId) {
        db()->exec("INSERT INTO feed_plots (name, area_hectares) VALUES ('North Field', 8)");
        $log[] = "Feed Production: 1 plot registered (North Field, 8 ha)";
    } else {
        $log[] = "Feed Production: North Field already exists, skipped";
    }

    // ---------- Warehouse: real stock so the dashboard/reports aren't empty ----------
    $milkWh = db()->query("SELECT id FROM wh_warehouses WHERE name = 'Milk Cold Store'")->fetchColumn();
    $milkItem = db()->query("SELECT id FROM wh_stock_items WHERE sku = 'MILK-RAW'")->fetchColumn();
    $feedWh = db()->query("SELECT id FROM wh_warehouses WHERE name = 'Feed Store'")->fetchColumn();
    $feedItem = db()->query("SELECT id FROM wh_stock_items WHERE sku = 'FEED-LAYER'")->fetchColumn();

    $existingFeedStock = stock_current((int) $feedWh, (int) $feedItem);
    if ($existingFeedStock < 100) {
        stock_receive((int) $feedWh, (int) $feedItem, 500, 'purchase_order', null, 'externally_purchased');
        $log[] = 'Warehouse: received 500kg Layer Mash into Feed Store';
    }
    // Deliberately set a minimum-stock rule the current level is BELOW, so the
    // notification bell has something real to show right after seeding.
    $ruleExists = db()->query("SELECT id FROM wh_minimum_stock_rules WHERE warehouse_id = {$feedWh} AND stock_item_id = {$feedItem}")->fetchColumn();
    if (!$ruleExists) {
        db()->prepare("INSERT INTO wh_minimum_stock_rules (warehouse_id, stock_item_id, minimum_threshold) VALUES (?, ?, 1000)")
            ->execute([$feedWh, $feedItem]);
        // Trigger the check now so the alert actually fires immediately, not on the next stock movement.
        check_minimum_stock((int) $feedWh, (int) $feedItem);
        $log[] = 'Warehouse: set a minimum-stock rule (1000kg) that current stock is below — triggered a real low-stock notification';
    }

    // ---------- Employees (incl. a driver) ----------
    $mgmtDept = db()->query("SELECT id FROM departments WHERE code = 'MGMT'")->fetchColumn();
    $employeeSeed = [
        ['EMP-101', 'Abebe Chala', 'Milker', false],
        ['EMP-102', 'Getachew Alemu', 'Driver', true],
        ['EMP-103', 'Meron Assefa', 'Warehouse Assistant', false],
    ];
    $empCreated = 0;
    foreach ($employeeSeed as [$num, $name, $title, $isDriver]) {
        $exists = db()->query("SELECT id FROM employees WHERE employee_number = " . db()->quote($num))->fetchColumn();
        if (!$exists) {
            db()->prepare("INSERT INTO employees (department_id, employee_number, full_name, job_title, base_salary, is_driver, status) VALUES (?, ?, ?, ?, ?, ?, 'active')")
                ->execute([$mgmtDept, $num, $name, $title, mt_rand(4500, 7000), $isDriver ? 1 : 0]);
            $empCreated++;
        }
    }
    $log[] = "Employees: {$empCreated} employees registered (including 1 driver for Distribution)";

    // ---------- Distribution: a vehicle ----------
    $vehicleExists = db()->query("SELECT id FROM dist_vehicles WHERE plate_number = 'AA-12345'")->fetchColumn();
    if (!$vehicleExists) {
        db()->exec("INSERT INTO dist_vehicles (plate_number, vehicle_type, capacity_kg, status) VALUES ('AA-12345', 'Refrigerated Truck', 2000, 'available')");
        $log[] = 'Distribution: 1 vehicle registered';
    }

    // ---------- Finance: opening cash flow entries + a sample expense ----------
    $txExists = (int) db()->query("SELECT COUNT(*) FROM fin_transactions")->fetchColumn();
    if ($txExists === 0) {
        for ($i = 5; $i >= 0; $i--) {
            $date = date('Y-m-01', strtotime("-{$i} months"));
            db()->prepare("INSERT INTO fin_transactions (direction, amount, unit_tag, category, transaction_date, description, recorded_by) VALUES ('inflow', ?, 'general_overhead', 'sale_settlement', ?, 'Monthly POS settlement', 1)")
                ->execute([mt_rand(40000, 70000), $date]);
            db()->prepare("INSERT INTO fin_transactions (direction, amount, unit_tag, category, transaction_date, description, recorded_by) VALUES ('outflow', ?, 'general_overhead', 'expense', ?, 'Monthly operating costs', 1)")
                ->execute([mt_rand(15000, 30000), $date]);
        }
        $log[] = 'Finance: 6 months of sample cash flow entries created (for the Reports chart)';
    }
    $expenseExists = (int) db()->query("SELECT COUNT(*) FROM fin_expenses")->fetchColumn();
    if ($expenseExists === 0) {
        $sampleExpenses = [
            ['dairy', 'Veterinary supplies', 3200], ['warehouse', 'Fuel', 1800],
            ['general_overhead', 'Repairs', 950], ['poultry', 'Feed additives', 2100],
        ];
        foreach ($sampleExpenses as [$unit, $category, $amount]) {
            db()->prepare("INSERT INTO fin_expenses (unit_tag, category, amount, expense_date, requested_by, status) VALUES (?, ?, ?, CURDATE(), 1, 'approved')")
                ->execute([$unit, $category, $amount]);
        }
        $log[] = 'Finance: 4 sample approved expenses created (for the Reports chart)';
    }

    return $log;
}

/* ===================== ENTERPRISE CORE SERVICES ===================== */

// Auto-generated Employee Number (TG001, TG002, ...)
function employee_generate_number(): string
{
    $maxNum = db()->query("SELECT MAX(CAST(SUBSTRING(employee_number, 3) AS UNSIGNED)) FROM employees WHERE employee_number LIKE 'TG%'")->fetchColumn();
    $next = ($maxNum ? (int)$maxNum : 0) + 1;
    return sprintf("TG%03d", $next);
}

// Scoped Targeted Notifications with real link_url
function notify_targeted(string $module, string $message, string $priority = 'medium', ?int $userId = null, ?int $roleId = null, ?int $deptId = null, string $linkUrl = ''): void
{
    try {
        $stmt = db()->prepare(
            "INSERT INTO notifications (module, priority, message, link_url, target_user_id, target_role_id, target_department_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$module, $priority, $message, $linkUrl ?: null, $userId, $roleId, $deptId]);
    } catch (Throwable) {
        // Never crash the main action over a notification failure
    }
}

// Farm -> Store Receiving Workflow
function store_send_to_warehouse(string $sourceType, ?string $sourceRef, int $warehouseId, int $stockItemId, float $sentQty, int $sentBy, ?int $materialRequestId = null): string
{
    $prefix = match(strtolower($sourceType)) {
        'dairy' => 'RCV-ወተት',
        'poultry' => 'RCV-እንቁላል',
        'fish' => 'RCV-ዓሳ',
        'feed_production' => 'RCV-መኖ',
        'purchase', 'purchase_order' => 'RCV-ግዢ',
        default => 'RCV-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $sourceType), 0, 4))
    };
    $dispatchCode = $prefix . '-' . date('ymd') . '-' . rand(100, 999);
    $stmt = db()->prepare(
        "INSERT INTO wh_store_receiving (dispatch_code, source_type, source_reference, material_request_id, warehouse_id, stock_item_id, sent_quantity, sent_by, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_receipt')"
    );
    $stmt->execute([$dispatchCode, $sourceType, $sourceRef, $materialRequestId, $warehouseId, $stockItemId, $sentQty, $sentBy]);
    
    // Notify Storekeeper role (role_id 8 = warehouse_keeper)
    notify_targeted('warehouse', "አዲስ ገቢ ምርት/ዕቃ ደርሷል፡ {$dispatchCode} ({$sentQty} መጠን) ማረጋገጫ ይጠብቃል።", 'high', null, 8, null, '/aleltu-eifms/modules/warehouse/receiving.php');
    
    return $dispatchCode;
}

function store_confirm_receipt(int $receivingId, float $receivedQty, ?string $varianceReason, int $receivedBy, string $postAction = 'store'): void
{
    if ($receivedQty <= 0) {
        throw new InvalidArgumentException("Received quantity must be greater than zero.");
    }

    $ownsTx = false;
    if (!db()->inTransaction()) {
        db()->beginTransaction();
        $ownsTx = true;
    }
    try {
        // Lock the receiving row and re-check status inside the transaction
        // (same rationale as the other fixes in this pass): a double-click or
        // two staff confirming the same shipment concurrently could otherwise
        // both pass the pending_receipt check before either commits, and both
        // call stock_receive() — a duplicate stock movement for one physical
        // delivery.
        $row = db()->prepare(
            "SELECT r.*, i.name AS item_name FROM wh_store_receiving r
             JOIN wh_stock_items i ON i.id = r.stock_item_id
             WHERE r.id = ? FOR UPDATE"
        );
        $row->execute([$receivingId]);
        $row = $row->fetch();
        if (!$row || $row['status'] !== 'pending_receipt') {
            throw new RuntimeException("Receiving record not found or already processed.");
        }

        if ($receivedQty != $row['sent_quantity'] && empty(trim($varianceReason ?? ''))) {
            throw new InvalidArgumentException("A reason is mandatory when received quantity ({$receivedQty}) differs from sent quantity ({$row['sent_quantity']}).");
        }

        $status = ($postAction === 'send_to_dept' && !empty($row['material_request_id'])) ? 'sent_to_department' : 'stored';

        $stmt = db()->prepare(
            "UPDATE wh_store_receiving 
             SET received_quantity = ?, variance_reason = ?, status = ?, received_by = ?, received_at = NOW() 
             WHERE id = ? AND status = 'pending_receipt'"
        );
        $stmt->execute([$receivedQty, $varianceReason ?: null, $status, $receivedBy, $receivingId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException("Receiving record not found or already processed.");
        }
        
        // Derive unit_cost from the linked PO so inventory valuation works.
        // PO total_price / quantity = cost per unit. Falls back to 0 if not a purchase.
        $unitCost = 0.0;
        if (!empty($row['source_reference']) && preg_match('/PO#(\d+)/', $row['source_reference'], $m)) {
            $po = db()->query("SELECT total_price, quantity FROM fin_purchase_orders WHERE id = " . (int)$m[1])->fetch();
            if ($po && (float)$po['quantity'] > 0) {
                $unitCost = round((float)$po['total_price'] / (float)$po['quantity'], 4);
            }
        }

        // Add into warehouse stock ledger with exact received quantity and unit cost
        stock_receive($row['warehouse_id'], $row['stock_item_id'], $receivedQty, $row['source_type'] ?: 'purchase', null, 'receipt', $unitCost);

        // If this receipt came from a Finance Purchase Order, update the PO status
        if (!empty($row['source_reference']) && preg_match('/PO#(\d+)/', $row['source_reference'], $matches)) {
            $poId = (int) $matches[1];
            db()->prepare("UPDATE fin_purchase_orders SET status = 'received' WHERE id = ?")->execute([$poId]);
        }

        // If linked to an original Department Material Request
        if (!empty($row['material_request_id'])) {
            $matReqId = (int) $row['material_request_id'];
            if ($postAction === 'send_to_dept') {
                db()->prepare("UPDATE dept_material_requests SET issued_quantity = issued_quantity + ?, status = 'sent_to_department' WHERE id = ?")
                    ->execute([$receivedQty, $matReqId]);
                
                // Get requesting department to send alert
                $matReq = db()->query("SELECT d.name AS dept_name, d.id AS dept_id FROM dept_material_requests mr JOIN departments d ON d.id = mr.department_id WHERE mr.id = {$matReqId}")->fetch();
                if ($matReq) {
                    $deptName = strtolower($matReq['dept_name'] ?? '');
                    $deptUrl = '/aleltu-eifms/modules/dairy/material_request.php';
                    if (str_contains($deptName, 'poultry') || str_contains($deptName, 'ዶሮ')) {
                        $deptUrl = '/aleltu-eifms/modules/poultry/material_request.php';
                    } elseif (str_contains($deptName, 'fish') || str_contains($deptName, 'ዓሳ') || str_contains($deptName, 'ዓሣ')) {
                        $deptUrl = '/aleltu-eifms/modules/fish/material_request.php';
                    }
                    notify_targeted('warehouse', "መጋዘን {$receivedQty} {$row['item_name']} ለክፍልዎ ልኳል። እባክዎ ርክክቡን ያረጋግጡ።", 'high', null, null, (int)$matReq['dept_id'], $deptUrl);
                }
            }
        }
        
        if ($ownsTx && db()->inTransaction()) {
            db()->commit();
        }
        log_activity('warehouse', 'update', 'wh_store_receiving', $receivingId, "Confirmed receipt {$row['dispatch_code']}: sent {$row['sent_quantity']}, received {$receivedQty}, post-action: {$postAction}");
    } catch (Throwable $e) {
        if ($ownsTx && db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
}

// Department Material Requisition Workflow
function dept_material_request_create(int $deptId, int $stockItemId, float $requestedQty, int $requestedBy, ?string $customItemName = null, ?string $note = null): int
{
    if ($requestedQty <= 0) {
        throw new InvalidArgumentException('Requested quantity must be greater than zero.');
    }

    // Handle custom item entry
    $customNameTrim = trim($customItemName ?? '');
    if ($stockItemId <= 0 && $customNameTrim !== '') {
        // Check if item with this name already exists in wh_stock_items
        $existing = db()->prepare("SELECT id FROM wh_stock_items WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $existing->execute([$customNameTrim]);
        $existingId = $existing->fetchColumn();
        if ($existingId) {
            $stockItemId = (int) $existingId;
        } else {
            // Auto-register in stock catalog
            $cleanSku = 'REQ-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $customNameTrim), 0, 5)) . '-' . rand(100, 999);
            $pClass = 'feed';
            $insItem = db()->prepare("INSERT INTO wh_stock_items (sku, name, product_class, unit, is_perishable) VALUES (?, ?, ?, 'KG', 0)");
            $insItem->execute([$cleanSku, $customNameTrim, $pClass]);
            $stockItemId = (int) db()->lastInsertId();
        }
    }

    if ($stockItemId <= 0) {
        throw new InvalidArgumentException('Please select an item or enter a custom item name.');
    }

    $stmt = db()->prepare(
        "INSERT INTO dept_material_requests (department_id, stock_item_id, custom_item_name, requested_quantity, note, requested_by, status)
         VALUES (?, ?, ?, ?, ?, ?, 'pending')"
    );
    $stmt->execute([$deptId, $stockItemId, $customNameTrim ?: null, $requestedQty, $note, $requestedBy]);
    $reqId = (int) db()->lastInsertId();
    
    // Look up item name for notification
    $itemName = db()->query("SELECT name FROM wh_stock_items WHERE id = {$stockItemId}")->fetchColumn() ?: "Item #{$stockItemId}";
    
    notify_targeted('warehouse', "New material requisition #{$reqId} for {$itemName} ({$requestedQty} units).", 'medium', null, 8, null, '/aleltu-eifms/modules/warehouse/requisitions.php');
    log_activity('warehouse', 'create', 'dept_material_requests', $reqId, "New material request #{$reqId} ({$requestedQty} units of {$itemName})");

    return $reqId;
}

function dept_material_request_fulfill(int $requestId, int $warehouseId, int $fulfilledBy): void
{
    $req = db()->query(
        "SELECT r.*, i.name AS item_name, d.name AS dept_name, d.business_unit AS dept_unit_tag
         FROM dept_material_requests r 
         JOIN wh_stock_items i ON i.id = r.stock_item_id
         JOIN departments d ON d.id = r.department_id
         WHERE r.id = " . (int)$requestId
    )->fetch();

    if (!$req || in_array($req['status'], ['completed', 'fulfilled', 'sent_to_department'], true)) {
        throw new RuntimeException("Request not found or already processed.");
    }

    // The requesting department's business_unit (dairy | poultry | fish | warehouse | ...)
    // is used both as the stock-issue destination_type (so finance_dept_costs can
    // attribute consumption to the correct department) and as the notification
    // module/link, so notifications route back to the department that actually asked.
    $deptUnitTag = $req['dept_unit_tag'] ?: 'general_overhead';
    $deptRequestLink = "/aleltu-eifms/modules/{$deptUnitTag}/material_request.php";
    
    // Check available stock in warehouse
    $availStmt = db()->prepare("SELECT COALESCE(SUM(quantity_on_hand), 0) FROM wh_stock_batches WHERE warehouse_id = ? AND stock_item_id = ? AND quantity_on_hand > 0");
    $availStmt->execute([$warehouseId, $req['stock_item_id']]);
    $availableStock = (float)$availStmt->fetchColumn();
    
    $ownsTx = false;
    if (!db()->inTransaction()) {
        db()->beginTransaction();
        $ownsTx = true;
    }
    try {
        $requested = (float)$req['requested_quantity'];
        
        if ($availableStock >= $requested) {
            // Case A: Full stock available -> Issue stock & dispatch to department
            stock_issue($warehouseId, $req['stock_item_id'], $requested, $deptUnitTag);
            
            db()->prepare("UPDATE dept_material_requests SET issued_quantity = ?, shortage_quantity = 0, status = 'sent_to_department', fulfilled_by = ? WHERE id = ?")
                ->execute([$requested, $fulfilledBy, $requestId]);
            
            notify_targeted($deptUnitTag, "Store has issued full {$requested} units of {$req['item_name']}. Please confirm physical receipt.", 'high', null, null, (int)$req['department_id'], $deptRequestLink);
            log_activity('warehouse', 'update', 'dept_material_requests', $requestId, "Fulfilled material request #{$requestId} ({$requested} units issued and sent to department)");
        } elseif ($availableStock > 0) {
            // Case B: Partial stock available -> Issue available + raise linked Purchase Request for shortage
            $issued = $availableStock;
            $shortage = $requested - $issued;
            
            stock_issue($warehouseId, $req['stock_item_id'], $issued, $deptUnitTag);
            
            // Raise linked Purchase Request in Finance
            $poReqStmt = db()->prepare("INSERT INTO fin_purchase_requests (material_request_id, stock_item_id, quantity, justification, requested_by, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $poReqStmt->execute([$requestId, $req['stock_item_id'], $shortage, "Shortage from {$req['dept_name']} material requisition #{$requestId}", $fulfilledBy]);
            $poReqId = (int)db()->lastInsertId();
            
            db()->prepare("UPDATE dept_material_requests SET issued_quantity = ?, shortage_quantity = ?, status = 'shortage_purchase_raised', purchase_request_id = ?, fulfilled_by = ? WHERE id = ?")
                ->execute([$issued, $shortage, $poReqId, $fulfilledBy, $requestId]);
            
            notify_targeted('finance', "Shortage purchase required: {$shortage} units of {$req['item_name']} for {$req['dept_name']}.", 'high', null, 10, null, '/aleltu-eifms/modules/finance/purchasing.php');
            notify_targeted($deptUnitTag, "Store issued partial stock ({$issued} units). Shortage of {$shortage} units forwarded to Finance for purchase.", 'medium', null, null, (int)$req['department_id'], $deptRequestLink);
            log_activity('warehouse', 'update', 'dept_material_requests', $requestId, "Partially fulfilled #{$requestId}: issued {$issued}, shortage {$shortage} forwarded to Finance as PR #{$poReqId}");
        } else {
            // Case C: No stock available -> Forward entire quantity to Finance for purchase
            $shortage = $requested;
            $poReqStmt = db()->prepare("INSERT INTO fin_purchase_requests (material_request_id, stock_item_id, quantity, justification, requested_by, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $poReqStmt->execute([$requestId, $req['stock_item_id'], $shortage, "Out of stock: {$req['dept_name']} material requisition #{$requestId}", $fulfilledBy]);
            $poReqId = (int)db()->lastInsertId();
            
            db()->prepare("UPDATE dept_material_requests SET issued_quantity = 0, shortage_quantity = ?, status = 'shortage_purchase_raised', purchase_request_id = ?, fulfilled_by = ? WHERE id = ?")
                ->execute([$shortage, $poReqId, $fulfilledBy, $requestId]);
            
            notify_targeted('finance', "Out of stock purchase required: {$shortage} units of {$req['item_name']} for {$req['dept_name']}.", 'high', null, 10, null, '/aleltu-eifms/modules/finance/purchasing.php');
            notify_targeted($deptUnitTag, "Item is out of stock. Full request ({$shortage} units) forwarded to Finance for purchase.", 'medium', null, null, (int)$req['department_id'], $deptRequestLink);
            log_activity('warehouse', 'update', 'dept_material_requests', $requestId, "Out of stock for #{$requestId}: forwarded full {$shortage} units to Finance as PR #{$poReqId}");
        }
        
        if ($ownsTx && db()->inTransaction()) {
            db()->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTx && db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
}

// Department confirms it physically received the goods
function dept_material_request_confirm_received(int $requestId, int $confirmedBy): void
{
    $ownsTx = false;
    if (!db()->inTransaction()) {
        db()->beginTransaction();
        $ownsTx = true;
    }
    try {
        $req = db()->prepare("SELECT r.*, i.name AS item_name FROM dept_material_requests r JOIN wh_stock_items i ON i.id = r.stock_item_id WHERE r.id = ? FOR UPDATE");
        $req->execute([$requestId]);
        $req = $req->fetch();
        if (!$req) {
            throw new RuntimeException("Request not found.");
        }
        if ($req['status'] === 'completed' || $req['received_confirmed_at']) {
            throw new RuntimeException("Already confirmed received.");
        }

        $updated = db()->prepare("UPDATE dept_material_requests SET status = 'completed', received_confirmed_at = NOW(), received_confirmed_by = ? WHERE id = ? AND status != 'completed'");
        $updated->execute([$confirmedBy, $requestId]);
        if ($updated->rowCount() === 0) {
            throw new RuntimeException("Already confirmed received.");
        }

        notify_targeted('warehouse', "Department confirmed receipt of {$req['item_name']} (Request #{$requestId}).", 'medium', null, 8, null, '/aleltu-eifms/modules/warehouse/requisitions.php');
        log_activity('warehouse', 'update', 'dept_material_requests', $requestId, "Department confirmed physical receipt of request #{$requestId}. Marked as COMPLETED.");
        if ($ownsTx && db()->inTransaction()) {
            db()->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTx && db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
}

// Controlled Stock Adjustment with Audit
function stock_adjust(int $warehouseId, int $stockItemId, string $type, float $quantity, string $reason, int $userId): void
{
    if ($quantity <= 0) {
        throw new InvalidArgumentException("Adjustment quantity must be greater than zero.");
    }
    if (empty(trim($reason))) {
        throw new InvalidArgumentException("A valid reason is required for stock adjustments.");
    }
    $ownsTx = false;
    if (!db()->inTransaction()) {
        db()->beginTransaction();
        $ownsTx = true;
    }
    try {
        if ($type === 'decrease') {
            $current = stock_current($warehouseId, $stockItemId);
            if ($quantity > $current) {
                throw new RuntimeException("Cannot reduce {$quantity} units; only {$current} units currently available in warehouse.");
            }
            stock_issue($warehouseId, $stockItemId, $quantity, 'stock_adjustment');
        } else {
            stock_receive($warehouseId, $stockItemId, $quantity, 'stock_adjustment', null, 'adjustment');
        }
        $stmt = db()->prepare("INSERT INTO wh_stock_adjustments (warehouse_id, stock_item_id, adjustment_type, quantity, reason, adjusted_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$warehouseId, $stockItemId, $type, $quantity, $reason, $userId]);
        $adjId = (int) db()->lastInsertId();
        if ($ownsTx && db()->inTransaction()) {
            db()->commit();
        }
        log_activity('warehouse', 'update', 'wh_stock_adjustments', $adjId, "Stock adjustment ({$type} {$quantity}) for item #{$stockItemId}. Reason: {$reason}");
    } catch (Throwable $e) {
        if ($ownsTx && db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
}

// Resolves which physical warehouse holds a given stock item's product class,
// so department/store screens never need a manual "choose warehouse" dropdown.
function resolve_warehouse_for_item(int $stockItemId = 0): int
{
    // The farm operates 1 unified Central Main Warehouse
    return 1;
}

// Looks up a role's id by name (avoids hardcoded role-id numbers scattered in code).
function role_id_by_name(string $roleName): ?int
{
    static $cache = [];
    if (!array_key_exists($roleName, $cache)) {
        $stmt = db()->prepare("SELECT id FROM roles WHERE name = ?");
        $stmt->execute([$roleName]);
        $id = $stmt->fetchColumn();
        $cache[$roleName] = $id !== false ? (int) $id : null;
    }
    return $cache[$roleName];
}

// Saves a cropped ID photo (sent from the browser as a base64 PNG data URL)
// to disk and records the path on the employee. Old photo file is removed.
// Links (or unlinks) an existing login account to an employee record, so
// that employee can use the Self-Service portal. Without this, Self-Service
// had no way to ever work for anyone — "Contact HR" pointed nowhere.
function employee_link_user(int $employeeId, ?int $userId): void
{
    if ($userId) {
        $alreadyLinked = db()->prepare("SELECT id, full_name FROM employees WHERE user_id = ? AND id != ?");
        $alreadyLinked->execute([$userId, $employeeId]);
        $conflict = $alreadyLinked->fetch();
        if ($conflict) {
            throw new RuntimeException("That login is already linked to {$conflict['full_name']}.");
        }
    }
    db()->prepare("UPDATE employees SET user_id = ? WHERE id = ?")->execute([$userId ?: null, $employeeId]);
    log_activity('employee', 'update', 'employees', $employeeId, $userId ? "Linked to login account #{$userId}" : 'Login account unlinked');
}

function employee_photo_exists(?string $photoPath): bool
{
    if (empty($photoPath)) {
        return false;
    }
    $cleanPath = preg_replace('#^/?aleltu-eifms/#', '', $photoPath);
    $diskPath = dirname(__DIR__) . '/' . ltrim($cleanPath, '/');
    return is_file($diskPath);
}

function employee_save_photo(int $employeeId, string $base64DataUrl): string
{
    if (!preg_match('/^data:image\/([a-zA-Z0-9]+);base64,(.+)$/', $base64DataUrl, $m)) {
        throw new InvalidArgumentException('Expected a valid image data URL.');
    }
    $bytes = base64_decode($m[2], true);
    if ($bytes === false || strlen($bytes) < 20) {
        throw new InvalidArgumentException('Could not decode image data.');
    }
    if (strlen($bytes) > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Image is too large (max 5MB).');
    }

    $old = db()->query("SELECT photo_path FROM employees WHERE id = " . (int) $employeeId)->fetchColumn();

    $dir = dirname(__DIR__) . '/assets/uploads/employee_photos';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $filename = 'emp_' . $employeeId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.png';
    file_put_contents($dir . '/' . $filename, $bytes);

    if ($old) {
        $cleanOld = preg_replace('#^/?aleltu-eifms/#', '', $old);
        $oldFile = dirname(__DIR__) . '/' . ltrim($cleanOld, '/');
        if (is_file($oldFile) && realpath($oldFile) !== realpath($dir . '/' . $filename)) {
            @unlink($oldFile);
        }
    }

    $relPath = '/aleltu-eifms/assets/uploads/employee_photos/' . $filename;
    db()->prepare("UPDATE employees SET photo_path = ? WHERE id = ?")->execute([$relPath, $employeeId]);
    log_activity('employee', 'update', 'employees', $employeeId, 'ID photo updated');
    return $relPath;
}

function employee_save_photo_file(int $employeeId, array $file): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('File upload failed with error code: ' . $file['error']);
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Image is too large (max 5MB).');
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        throw new InvalidArgumentException('Uploaded file is not a valid image.');
    }

    $mime = $imageInfo['mime'];
    $srcImg = null;
    switch ($mime) {
        case 'image/jpeg':
            $srcImg = @imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $srcImg = @imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/webp':
            $srcImg = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : null;
            break;
        case 'image/gif':
            $srcImg = @imagecreatefromgif($file['tmp_name']);
            break;
        default:
            throw new InvalidArgumentException('Unsupported image format (' . $mime . '). Please upload JPG, PNG, or WEBP.');
    }

    if (!$srcImg) {
        throw new InvalidArgumentException('Could not process the uploaded image.');
    }

    $origW = imagesx($srcImg);
    $origH = imagesy($srcImg);
    $targetSize = 400;

    // Center crop to square
    $minDim = min($origW, $origH);
    $cropX = (int)(($origW - $minDim) / 2);
    $cropY = (int)(($origH - $minDim) / 2);

    $destImg = imagecreatetruecolor($targetSize, $targetSize);
    imagealphablending($destImg, false);
    imagesavealpha($destImg, true);

    imagecopyresampled($destImg, $srcImg, 0, 0, $cropX, $cropY, $targetSize, $targetSize, $minDim, $minDim);

    $old = db()->query("SELECT photo_path FROM employees WHERE id = " . (int) $employeeId)->fetchColumn();

    $dir = dirname(__DIR__) . '/assets/uploads/employee_photos';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $filename = 'emp_' . $employeeId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.png';
    $targetPath = $dir . '/' . $filename;

    imagepng($destImg, $targetPath, 8);
    imagedestroy($destImg);
    imagedestroy($srcImg);

    if ($old) {
        $cleanOld = preg_replace('#^/?aleltu-eifms/#', '', $old);
        $oldFile = dirname(__DIR__) . '/' . ltrim($cleanOld, '/');
        if (is_file($oldFile) && realpath($oldFile) !== realpath($targetPath)) {
            @unlink($oldFile);
        }
    }

    $relPath = '/aleltu-eifms/assets/uploads/employee_photos/' . $filename;
    db()->prepare("UPDATE employees SET photo_path = ? WHERE id = ?")->execute([$relPath, $employeeId]);
    log_activity('employee', 'update', 'employees', $employeeId, 'ID photo uploaded from file');
    return $relPath;
}

// Payroll Partial Payment Recording
function payroll_record_payment(int $payslipId, float $amount, string $paymentMethod, ?string $notes, int $recordedBy): void
{
    $payslip = db()->query("SELECT * FROM fin_payslips WHERE id = " . (int)$payslipId)->fetch();
    if (!$payslip) {
        throw new RuntimeException("Payslip not found.");
    }
    
    db()->beginTransaction();
    try {
        $stmt = db()->prepare("INSERT INTO fin_payroll_payments (payslip_id, amount_paid, payment_date, payment_method, notes, recorded_by) VALUES (?, ?, CURDATE(), ?, ?, ?)");
        $stmt->execute([$payslipId, $amount, $paymentMethod, $notes ?: null, $recordedBy]);
        
        // Update total paid on payslip
        db()->prepare("UPDATE fin_payslips SET total_paid = total_paid + ? WHERE id = ?")->execute([$amount, $payslipId]);
        
        // Record outflow transaction in finance
        $emp = db()->query("SELECT full_name, employee_number FROM employees WHERE id = " . (int)$payslip['employee_id'])->fetch();
        $empName = $emp ? "{$emp['full_name']} ({$emp['employee_number']})" : "Employee #{$payslip['employee_id']}";
        
        db()->prepare("INSERT INTO fin_transactions (direction, amount, unit_tag, category, source_table, source_id, transaction_date, description, recorded_by) VALUES ('outflow', ?, 'general_overhead', 'payroll_payment', 'fin_payslips', ?, CURDATE(), ?, ?)")
            ->execute([$amount, $payslipId, "Salary payment for {$empName}", $recordedBy]);
            
        db()->commit();
        log_activity('finance', 'create', 'fin_payroll_payments', $payslipId, "Recorded payroll payment of {$amount} ETB for {$empName}");
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

// Granular RBAC Permissions Check
function has_permission(string $module, string $actionCode): bool
{
    $user = current_user();
    if (!$user) return false;

    // Super admin bypasses all permission checks
    $role = $user['role'] ?? $user['role_name'] ?? '';
    if ($role === 'super_admin') return true;

    // General manager gets all non-admin permissions
    if ($role === 'general_manager' && $module !== 'admin') return true;

    $roleId = (int)($user['role_id'] ?? 0);
    if ($roleId === 0) return false;

    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM role_permissions rp
         JOIN permissions p ON p.id = rp.permission_id
         WHERE rp.role_id = ? AND p.module = ? AND p.action_code = ?"
    );
    $stmt->execute([$roleId, $module, $actionCode]);
    return ((int) $stmt->fetchColumn()) > 0;
}

function require_permission(string $module, string $actionCode): void
{
    if (!has_permission($module, $actionCode)) {
        // JSON response for API calls
        if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden', 'detail' => "{$module}.{$actionCode} permission required"]);
            exit;
        }
        flash("Access denied: you need {$module}.{$actionCode} permission.", 'error');
        header('Location: /aleltu-eifms/index.php');
        exit;
    }
}

/* ===================== API HELPERS ===================== */

function api_json(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function api_error(string $message, int $status = 400): never
{
    api_json(['error' => $message, 'status' => $status], $status);
}

function api_require_auth(): array
{
    // Bearer token auth: token stored in users.api_token
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(\S+)$/i', $auth, $m)) {
        // Fallback: session auth (for same-origin requests)
        if (session_status() === PHP_SESSION_NONE) session_start();
        require_once __DIR__ . '/auth.php';
        $user = current_user();
        if (!$user) api_error('Unauthorized — provide Bearer token or session', 401);
        return $user;
    }
    $token = $m[1];
    $stmt = db()->prepare("SELECT u.*, r.name AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.api_token = ? AND u.is_active = 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) api_error('Invalid or expired token', 401);
    return $user;
}

// ─── Notify a whole role group ─────────────────────────────────────────────
/**
 * Send a notification to every user with a given role slug.
 * Looks up role_id from the roles table, then inserts one targeted notification row.
 */
function notify_role(string $roleSlug, string $title, string $message, string $url = ''): void
{
    try {
        $role = db()->prepare("SELECT id FROM roles WHERE name = ?");
        $role->execute([$roleSlug]);
        $row = $role->fetch();
        if (!$row) return; // role doesn't exist, silently skip
        $roleId = (int)$row['id'];
        $stmt = db()->prepare(
            "INSERT INTO notifications (module, priority, message, link_url, target_role_id)
             VALUES ('system', 'medium', ?, ?, ?)"
        );
        $msg = trim("$title: $message");
        $stmt->execute([$msg, $url, $roleId]);
    } catch (Throwable) {
        // Never crash the main action over a notification failure
    }
}

// ─── Audit Log ─────────────────────────────────────────────────────────────
/**
 * Record an audit trail entry.
 * Table: audit_log (id, user_id, module, action, table_name, record_id, details, ip, created_at)
 * If the table doesn't exist yet, silently skips — never crashes the caller.
 */
function log_activity(string $module, string $action, string $tableName, int $recordId = 0, string $details = ''): void
{
    try {
        $userId = 0;
        if (session_status() !== PHP_SESSION_NONE || isset($_SESSION['user_id'])) {
            $userId = (int)($_SESSION['user_id'] ?? 0);
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $stmt = db()->prepare(
            "INSERT INTO audit_log (user_id, module, action, table_name, record_id, details, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$userId, $module, $action, $tableName, $recordId, $details, $ip]);
    } catch (Throwable) {
        // Table may not exist or session may not be active — never crash the caller
    }
}

// ─── Retrieve recent audit log entries ─────────────────────────────────────
function audit_log_get(int $limit = 50, string $module = ''): array
{
    try {
        if ($module) {
            $s = db()->prepare("SELECT a.*, u.name AS user_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.module = ? ORDER BY a.id DESC LIMIT ?");
            $s->bindValue(1, $module);
            $s->bindValue(2, $limit, PDO::PARAM_INT);
            $s->execute();
        } else {
            $s = db()->prepare("SELECT a.*, u.name AS user_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT ?");
            $s->bindValue(1, $limit, PDO::PARAM_INT);
            $s->execute();
        }
        return $s->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

// ─── Warehouse 3-Partition & Photo & Smart Farm Helpers ─────────────────────────

function stock_partition_label(string $code): string
{
    return match($code) {
        'finished_products'  => current_locale() === 'am' ? 'ክፍል 1፡ የተጠናቀቁ ምርቶች (Finished Products)' : 'Partition 1: Finished Products',
        'feed_nutrition'     => current_locale() === 'am' ? 'ክፍል 2፡ የእንስሳትና ዓሳ መኖዎች (Feeds & Nutrition)' : 'Partition 2: Feeds & Nutrition',
        'materials_supplies' => current_locale() === 'am' ? 'ክፍል 3፡ ዕቃዎችና የግንባታ ቁሳቁስ (Materials & Supplies)' : 'Partition 3: Materials & Supplies',
        default              => ucfirst(str_replace('_', ' ', $code))
    };
}

function save_uploaded_photo(string $inputName, string $subfolder = 'general'): ?string
{
    if (empty($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $file = $_FILES[$inputName];
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed, true)) {
        throw new InvalidArgumentException(current_locale() === 'am' ? 'እባክዎ ትክክለኛ የምስል ፋይል ብቻ ይጫኑ (JPG, PNG, WebP)።' : 'Please upload a valid image file (JPG, PNG, WebP).');
    }

    $ext = match($mime) {
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => 'jpg'
    };

    $appDir = dirname(__DIR__);
    $targetDir = $appDir . '/uploads/' . trim($subfolder, '/');
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = uniqid('img_', true) . '.' . $ext;
    $targetPath = $targetDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Failed to save uploaded image file.');
    }

    return '/aleltu-eifms/uploads/' . trim($subfolder, '/') . '/' . $fileName;
}

function vet_process_scheduled_reminders(): array
{
    $now = time();
    $scheduled = db()->query(
        "SELECT v.*, 
                CONCAT(v.scheduled_date, ' ', COALESCE(v.scheduled_time, '09:00:00')) AS target_datetime
         FROM vet_vaccination_records v
         WHERE v.status = 'scheduled'
         ORDER BY v.scheduled_date ASC, v.scheduled_time ASC"
    )->fetchAll();

    $alerts = [];
    foreach ($scheduled as $v) {
        $targetTs = strtotime($v['target_datetime']);
        $diffSeconds = $targetTs - $now;
        $subjectLabel = vet_subject_label($v['subject_type'], (int)$v['subject_id']);

        // 1. Alert for 1 Day before (<= 24h & > 2h)
        if ($diffSeconds <= 86400 && $diffSeconds > 7200 && empty($v['reminder_1day_sent'])) {
            $msg = (current_locale() === 'am' ? "የነገ የክትባት አስታዋሽ፡ ለ{$subjectLabel} የሚሰጥ የ{$v['vaccine_name']} ክትባት በ{$v['scheduled_time']} ሰዓት ተይዟል።" : "Upcoming Vaccination Tomorrow: {$v['vaccine_name']} for {$subjectLabel} at {$v['scheduled_time']}.");
            notify_targeted('veterinary', $msg, 'high', null, 10, null, '/aleltu-eifms/modules/veterinary/dashboard.php');
            db()->prepare("UPDATE vet_vaccination_records SET reminder_1day_sent = 1 WHERE id = ?")->execute([$v['id']]);
        }

        // 2. Alert for 2 Hours before (<= 2h & > 1h)
        if ($diffSeconds <= 7200 && $diffSeconds > 3600 && empty($v['reminder_2hr_sent'])) {
            $msg = (current_locale() === 'am' ? "⏰ የ2 ሰዓት ቀሪ ማንቂያ፡ ለ{$subjectLabel} የሚሰጥ የ{$v['vaccine_name']} ክትባት ሊጀመር 2 ሰዓት ብቻ ቀርቷል። እባክዎ ይዘጋጁ!" : "⏰ 2-Hour Alert: Vaccination ({$v['vaccine_name']}) for {$subjectLabel} starts in 2 hours!");
            // Priority is 'high', not 'urgent': the notifications.priority
            // column is ENUM('low','medium','high') and no UI anywhere
            // renders an 'urgent' badge. Under strict SQL mode an invalid
            // ENUM value rejects the INSERT outright; notify_targeted()
            // swallows that failure silently (by design, so a notification
            // problem never blocks the main action) — so this 2-hour
            // pre-vaccination reminder was being silently dropped every
            // time, while reminder_2hr_sent was still marked sent
            // immediately after, permanently losing that specific alert.
            notify_targeted('veterinary', $msg, 'high', null, 10, null, '/aleltu-eifms/modules/veterinary/dashboard.php');
            db()->prepare("UPDATE vet_vaccination_records SET reminder_2hr_sent = 1 WHERE id = ?")->execute([$v['id']]);
        }

        // 3. Alert for 1 Hour before (<= 1h)
        if ($diffSeconds <= 3600 && $diffSeconds > 0 && empty($v['reminder_1hr_sent'])) {
            $msg = (current_locale() === 'am' ? "🚨 የ1 ሰዓት አስቸኳይ ማንቂያ፡ ለ{$subjectLabel} የሚሰጥ የ{$v['vaccine_name']} ክትባት አሁን 1 ሰዓት ብቻ ቀርቷል። ክትባቱን ለመጀመር እዚህ ይጫኑ!" : "🚨 1-Hour Urgent Reminder: Vaccination ({$v['vaccine_name']}) for {$subjectLabel} in 1 hour. Click to check-in!");
            // Same fix as the 2-hour reminder above — 'urgent' is not a
            // valid priority value; this was silently dropping the most
            // time-critical vaccination reminder of the three.
            notify_targeted('veterinary', $msg, 'high', null, 10, null, '/aleltu-eifms/modules/veterinary/dashboard.php');
            db()->prepare("UPDATE vet_vaccination_records SET reminder_1hr_sent = 1 WHERE id = ?")->execute([$v['id']]);
        }

        // Active upcoming within 24h or today
        if ($diffSeconds > -86400 && $diffSeconds <= 86400) {
            $alerts[] = [
                'id' => $v['id'],
                'subject_type' => $v['subject_type'],
                'subject_id' => $v['subject_id'],
                'subject_label' => $subjectLabel,
                'vaccine_name' => $v['vaccine_name'],
                'scheduled_date' => $v['scheduled_date'],
                'scheduled_time' => $v['scheduled_time'] ?: '09:00',
                'target_datetime' => $v['target_datetime'],
                'diff_seconds' => $diffSeconds,
                'diff_minutes' => round($diffSeconds / 60),
                'status' => $v['status'],
                'batch_number' => $v['batch_number'] ?? '',
                'dosage' => $v['dosage'] ?? '',
                'photo_path' => $v['photo_path'] ?? ''
            ];
        }
    }

    return $alerts;
}

function smart_get_feed_depletion_forecast(): array
{
    $feeds = db()->query(
        "SELECT i.id, i.sku, i.name, i.unit,
                COALESCE(SUM(b.quantity_on_hand), 0) AS total_kg
         FROM wh_stock_items i
         LEFT JOIN wh_stock_batches b ON b.stock_item_id = i.id AND b.quantity_on_hand > 0
         WHERE i.partition_code = 'feed_nutrition' OR i.product_class = 'feed'
         GROUP BY i.id"
    )->fetchAll();

    $cowCount = (int)db()->query("SELECT COUNT(*) FROM dairy_cows WHERE lifecycle_status NOT IN ('sold','deceased')")->fetchColumn();
    $henCount = (int)db()->query("SELECT COALESCE(SUM(initial_population - cumulative_mortality - cumulative_culled_sold), 0) FROM poultry_flocks WHERE status = 'active'")->fetchColumn();
    $fishCount = (int)db()->query("SELECT COALESCE(SUM(stocking_quantity - cumulative_mortality), 0) FROM fish_stocks WHERE status = 'active'")->fetchColumn();

    $forecast = [];
    foreach ($feeds as $f) {
        $sku = $f['sku'];
        $dailyBurn = 1.0;
        if (str_contains($sku, 'LAYER')) {
            $dailyBurn = max(5.0, round($henCount * 0.125, 1));
        } elseif (str_contains($sku, 'DAIRY')) {
            $dailyBurn = max(5.0, round($cowCount * 4.0, 1));
        } elseif (str_contains($sku, 'CALF')) {
            $dailyBurn = max(2.0, 6.0);
        } elseif (str_contains($sku, 'FISH')) {
            $dailyBurn = max(2.0, round($fishCount * 0.005, 1));
        } elseif (str_contains($sku, 'ALFALFA')) {
            $dailyBurn = max(1.0, 3.0);
        }

        $stock = (float)$f['total_kg'];

        // Check for manual override from admin/storekeeper
        $override = db()->query("SELECT daily_burn_override FROM wh_minimum_stock_rules WHERE stock_item_id = " . (int)$f['id'] . " AND warehouse_id = 1 LIMIT 1")->fetchColumn();
        $isManual = false;
        if ($override !== false && $override !== null && (float)$override > 0) {
            $dailyBurn = (float)$override;
            $isManual = true;
        }

        $daysLeft = $dailyBurn > 0 ? (int)floor($stock / $dailyBurn) : 999;

        $urgency = 'ok';
        if ($daysLeft <= 7) $urgency = 'critical';
        elseif ($daysLeft <= 20) $urgency = 'warning';

        $forecast[] = [
            'item_id' => $f['id'],
            'name' => $f['name'],
            'unit' => $f['unit'],
            'stock' => $stock,
            'daily_burn' => $dailyBurn,
            'days_left' => $daysLeft,
            'urgency' => $urgency,
            'is_manual' => $isManual
        ];
    }

    return $forecast;
}

function smart_detect_milk_drop_anomalies(): array
{
    $cows = db()->query("SELECT id, tag_number, name FROM dairy_cows WHERE lifecycle_status = 'milking'")->fetchAll();
    $anomalies = [];
    foreach ($cows as $c) {
        $avg = (float)db()->query("SELECT AVG(yield_liters) FROM dairy_milk_yield_entries WHERE cow_id = {$c['id']} AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $latest = (float)db()->query("SELECT yield_liters FROM dairy_milk_yield_entries WHERE cow_id = {$c['id']} ORDER BY id DESC LIMIT 1")->fetchColumn();
        if ($avg > 5.0 && $latest > 0) {
            $dropPct = round((($avg - $latest) / $avg) * 100, 1);
            if ($dropPct >= 20.0) {
                $anomalies[] = [
                    'cow_id' => $c['id'],
                    'tag_number' => $c['tag_number'],
                    'name' => $c['name'],
                    'avg_7day' => round($avg, 1),
                    'latest_yield' => round($latest, 1),
                    'drop_percent' => $dropPct
                ];
            }
        }
    }
    return $anomalies;
}

function smart_get_poultry_laying_efficiency(): array
{
    $flock = db()->query("SELECT * FROM poultry_flocks WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$flock) {
        return ['live_hens' => 0, 'today_eggs' => 0, 'rate_percent' => 0.0, 'status' => 'inactive'];
    }
    $live = (int)$flock['initial_population'] - (int)$flock['cumulative_mortality'] - (int)$flock['cumulative_culled_sold'];
    $today = date('Y-m-d');
    $todayEggs = (int)db()->query("SELECT COALESCE(SUM(whole_count), 0) FROM poultry_egg_collections WHERE flock_id = {$flock['id']} AND collection_date = '{$today}'")->fetchColumn();
    $rate = $live > 0 ? round(($todayEggs / $live) * 100, 1) : 0.0;

    return [
        'flock_id' => $flock['id'],
        'breed' => $flock['breed'],
        'live_hens' => $live,
        'today_eggs' => $todayEggs,
        'rate_percent' => $rate,
        'status' => $rate >= 80 ? 'optimal' : ($rate >= 60 ? 'moderate' : 'low')
    ];
}

