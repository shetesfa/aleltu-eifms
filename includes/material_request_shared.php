<?php
/**
 * Shared page for the per-department material request screens (Dairy / Poultry / Fish).
 * Features:
 *   1. Select catalog item OR enter custom requested item (e.g. የከብቶች መኖ).
 *   2. Real workflow status timeline for every request.
 *   3. Direct receipt confirmation [✓ Confirm Received] to complete the loop.
 */

function render_dept_material_request_page(string $roleName, string $deptCode, string $pageTitleKey, string $pageTitleFallback): void
{
    require_once __DIR__ . '/functions.php';
    require_once __DIR__ . '/auth.php';
    require_role(['general_manager', $roleName]);

    $dept = db()->prepare("SELECT id, name FROM departments WHERE code = ?");
    $dept->execute([$deptCode]);
    $dept = $dept->fetch();
    if (!$dept) {
        throw new RuntimeException("Department '{$deptCode}' is not set up yet.");
    }
    $deptId = (int) $dept['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        try {
            if (isset($_POST['create_request'])) {
                $itemType = $_POST['item_selection_type'] ?? 'catalog';
                $itemId = ($itemType === 'catalog') ? (int) ($_POST['stock_item_id'] ?? 0) : 0;
                $customItemName = ($itemType === 'custom') ? trim($_POST['custom_item_name'] ?? '') : '';
                $qty = (float) $_POST['requested_quantity'];
                $note = trim($_POST['note'] ?? '');

                if ($qty <= 0) {
                    throw new InvalidArgumentException('Quantity must be greater than zero.');
                }
                if ($itemType === 'custom' && $customItemName === '') {
                    throw new InvalidArgumentException('Please enter the name of the custom material you need.');
                }

                dept_material_request_create($deptId, $itemId, $qty, current_user()['id'], $customItemName, $note);
                flash(t('reqs.submitted', 'Material request sent to Storekeeper for stock check.'));
            } elseif (isset($_POST['confirm_received'])) {
                dept_material_request_confirm_received((int) $_POST['request_id'], current_user()['id']);
                flash('✓ Physical receipt confirmed. Material request is now marked as COMPLETED.');
            }
            header("Location: {$_SERVER['PHP_SELF']}");
            exit;
        } catch (Throwable $e) {
            flash('Error: ' . $e->getMessage(), 'error');
        }
    }

    require_once __DIR__ . '/header.php';

    // Filter catalog items relevant to this department
    $classFilter = match($deptCode) {
        'DAIRY'   => "('feed', 'medicine', 'general', 'equipment', 'milk')",
        'POULTRY' => "('feed', 'medicine', 'general', 'equipment')",
        'FISH'    => "('feed', 'general', 'equipment', 'medicine')",
        default   => "('feed', 'medicine', 'general', 'equipment', 'spare_parts')"
    };

    $rawItems = db()->query(
        "SELECT id, name, sku, unit, product_class, department_scope 
         FROM wh_stock_items 
         WHERE product_class IN {$classFilter}
         ORDER BY name"
    )->fetchAll();

    // Strict contextual filter by department
    $items = array_values(array_filter($rawItems, function($i) use ($deptCode) {
        $n = $i['name'];
        if ($deptCode === 'DAIRY') {
            if (str_contains($n, 'ዶሮ') || str_contains($n, 'ዓሳ') || str_contains($n, 'ዕንቁላል') || str_contains($n, 'እንቁላል')) return false;
        } elseif ($deptCode === 'POULTRY') {
            if (str_contains($n, 'ላም') || str_contains($n, 'ጥጃ') || str_contains($n, 'ከብት') || str_contains($n, 'ዓሳ') || str_contains($n, 'ወተት')) return false;
        } elseif ($deptCode === 'FISH') {
            if (str_contains($n, 'ላም') || str_contains($n, 'ጥጃ') || str_contains($n, 'ዶሮ') || str_contains($n, 'ወተት') || str_contains($n, 'ዕንቁላል')) return false;
        }
        return true;
    }));

    // Requests with full tracking
    $stmt = db()->prepare(
        "SELECT r.*, i.name AS catalog_name, i.unit, u.name AS requester_name,
                pr.status AS po_req_status, po.status AS po_status, po.supplier_name
         FROM dept_material_requests r
         JOIN wh_stock_items i ON i.id = r.stock_item_id
         JOIN users u ON u.id = r.requested_by
         LEFT JOIN fin_purchase_requests pr ON pr.id = r.purchase_request_id
         LEFT JOIN fin_purchase_orders po ON po.purchase_request_id = pr.id
         WHERE r.department_id = ?
         ORDER BY r.created_at DESC LIMIT 30"
    );
    $stmt->execute([$deptId]);
    $requests = $stmt->fetchAll();
    ?>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
        <div>
            <h2 class="mt-0" style="margin-bottom:4px">📦 <?= h(t($pageTitleKey, $pageTitleFallback)) ?></h2>
            <p class="text-muted" style="margin:0"><?= current_locale() === 'am' ? 'የከብት፣ የዶሮ፣ የዓሳ መኖ ወይም ሌሎች አስፈላጊ ቁሳቁሶችን ከመጋዘን ለመጠየቅ እና የጥያቄዎን ሁኔታ ደረጃ በደረጃ ለመከታተል ይጠቀሙ።' : 'Request cattle feed, poultry feeds, fish supplies, or custom items directly from Store.' ?></p>
        </div>
    </div>

    <!-- Request Form -->
    <div class="card" style="max-width:620px;margin-bottom:24px">
        <h3>➕ <?= h(t('reqs.new_request', 'Submit New Material Request')) ?></h3>
        <form method="post" id="matReqForm"><?= csrf_field() ?>
            <input type="hidden" name="create_request" value="1">
            
            <!-- Single intelligent item field (§22): search/select an existing
                 catalog item, or just type a new one if it's not listed.
                 Posts the same fields the backend already expects
                 (item_selection_type / stock_item_id / custom_item_name) —
                 only the picker itself changed, not what gets submitted. -->
            <input type="hidden" name="item_selection_type" id="itemSelectionType" value="custom">
            <input type="hidden" name="stock_item_id" id="stockItemIdHidden" value="">
            <div class="form-group">
                <label>🔍 <?= current_locale() === 'am' ? 'ዕቃ ፈልግ / ምረጥ *' : 'Search or Select Item *' ?></label>
                <input type="text" id="itemSearchInput" name="custom_item_name" list="catalogDatalist" autocomplete="off" required
                       placeholder="<?= current_locale() === 'am' ? 'የሚፈልጉትን የዕቃ ስም ይምረጡ ወይም እዚህ ይጻፉ...' : 'Select from list or type item name...' ?>">
                <datalist id="catalogDatalist">
                    <?php foreach ($items as $i): ?>
                        <option value="<?= h($i['name']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <p class="text-muted" style="font-size:12px;margin:4px 0 0">
                    <?= current_locale() === 'am'
                        ? '➕ ከዝርዝሩ ውስጥ ከሌለ፣ የሚፈልጉትን የዕቃ ስም ብቻ ይጻፉ — በቀጥታ እንደ አዲስ ጥያቄ ይላካል።'
                        : '➕ Not in the list? Just type the item name — it will be sent as a new item request.' ?>
                </p>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><?= h(t('reqs.quantity', 'Requested Quantity (KG / Units)')) ?> *</label>
                    <input type="number" step="0.1" min="0.1" name="requested_quantity" required placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ 500' : 'e.g. 500' ?>">
                </div>
                <div class="form-group">
                    <label><?= h(t('common.notes', 'Reason / Purpose')) ?></label>
                    <input type="text" name="note" placeholder="<?= current_locale() === 'am' ? 'ለምሳሌ፡ የወሩ መደበኛ ፍጆታ' : 'e.g. Monthly cattle feed requirement' ?>">
                </div>
            </div>

            <button class="btn" type="submit"><?= h(t('reqs.send_request', 'Send Request to Store')) ?></button>
        </form>
    </div>

    <!-- Request History & Real Timeline -->
    <div class="card">
        <h3 style="margin-top:0">📋 <?= current_locale() === 'am' ? 'የቀረቡ የዕቃ ጥያቄዎች ታሪክ እና የሂደት ሁኔታ' : 'My Material Requests History & Timeline' ?></h3>
        
        <?php if (empty($requests)): ?>
            <p class="text-muted">📦 <?= current_locale() === 'am' ? 'እስካሁን የቀረበ የዕቃ ጥያቄ የለም።' : 'No material requests yet. Create a request above when your department needs materials.' ?></p>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:18px">
                <?php foreach ($requests as $idx => $r):
                    $seqNo = count($requests) - $idx;
                    $displayName = $r['custom_item_name'] ?: $r['catalog_name'];
                    $isCompleted = ($r['status'] === 'completed' || $r['received_confirmed_at']);
                    $canConfirm = !$isCompleted && in_array($r['status'], ['sent_to_department', 'fulfilled', 'partially_issued'], true) && (float) $r['issued_quantity'] > 0;
                    
                    // Determine timeline steps
                    $step1_req = true;
                    $step2_store = in_array($r['status'], ['partially_issued', 'fulfilled', 'shortage_purchase_raised', 'sent_to_department', 'completed'], true);
                    $hasShortage = in_array($r['status'], ['partially_issued', 'shortage_purchase_raised'], true) || (float) $r['shortage_quantity'] > 0;
                    $step3_finance = !empty($r['purchase_request_id']) || $hasShortage;
                    $step4_purchased = in_array($r['po_status'] ?? '', ['approved', 'sent', 'sent_to_warehouse', 'received'], true);
                    $step5_wh_rcv = in_array($r['po_status'] ?? '', ['sent_to_warehouse', 'received'], true);
                    $step6_sent_dept = in_array($r['status'], ['sent_to_department', 'completed', 'fulfilled'], true) || (float) $r['issued_quantity'] > 0;
                    $step7_done = $isCompleted;
                ?>
                    <div style="border:1px solid var(--border);border-radius:8px;padding:16px;background:var(--card)">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;margin-bottom:12px">
                            <div>
                                <span style="font-weight:700;font-size:16px"><?= current_locale() === 'am' ? 'የዕቃ ጥያቄ #' : 'Material Request #' ?><?= $seqNo ?> (<?= h($deptCode) ?>-<?= str_pad((string)$r['id'], 3, '0', STR_PAD_LEFT) ?>): <?= h($displayName) ?></span>
                                <span class="text-muted" style="font-size:13px;margin-left:8px">— <?= h(eth_datetime($r['created_at'])) ?></span>
                                <?php if ($r['note']): ?>
                                    <div style="font-size:13px;color:var(--text-muted);margin-top:2px"><?= current_locale() === 'am' ? 'ምክንያት: ' : 'Reason: ' ?><em><?= h($r['note']) ?></em></div>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex;gap:8px;align-items:center">
                                <span style="font-size:14px;font-weight:600"><?= current_locale() === 'am' ? 'የተጠየቀ: ' : 'Requested: ' ?><?= number_format($r['requested_quantity'], 1) ?> <?= h($r['unit']) ?></span>
                                <?php if ((float)$r['issued_quantity'] > 0): ?>
                                    <span class="badge milking"><?= current_locale() === 'am' ? 'የተሰጠ: ' : 'Issued: ' ?><?= number_format($r['issued_quantity'], 1) ?> <?= h($r['unit']) ?></span>
                                <?php endif; ?>
                                <?php if ((float)$r['shortage_quantity'] > 0): ?>
                                    <span class="badge sold"><?= current_locale() === 'am' ? 'እጥረት: ' : 'Shortage: ' ?><?= number_format($r['shortage_quantity'], 1) ?> <?= h($r['unit']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Visual Progress Timeline -->
                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:8px;background:var(--bg);padding:10px;border-radius:6px;margin-bottom:12px;font-size:12px">
                            <div style="color:<?= $step1_req ? '#2e7d32' : 'var(--text-muted)' ?>">
                                <strong><?= $step1_req ? '✓' : '○' ?> 1. <?= current_locale() === 'am' ? 'ጥያቄ ቀርቧል' : 'Request Created' ?></strong>
                                <div><?= number_format($r['requested_quantity'], 1) ?> <?= h($r['unit']) ?></div>
                            </div>
                            <div style="color:<?= $step2_store ? '#2e7d32' : 'var(--text-muted)' ?>">
                                <strong><?= $step2_store ? '✓' : '○' ?> 2. <?= current_locale() === 'am' ? 'መጋዘን ታይቷል' : 'Store Checked' ?></strong>
                                <div><?= $step2_store ? (current_locale() === 'am' ? 'ክምችት ተገምግሟል' : 'Stock evaluated') : (current_locale() === 'am' ? 'ምርመራ ይጠብቃል' : 'Pending check') ?></div>
                            </div>
                            <?php if ($hasShortage): ?>
                                <div style="color:<?= $step3_finance ? '#e65100' : 'var(--text-muted)' ?>">
                                    <strong><?= $step3_finance ? '⚠️' : '○' ?> 3. <?= current_locale() === 'am' ? 'ወደ ፋይናንስ ተልኳል' : 'Sent to Finance' ?></strong>
                                    <div><?= current_locale() === 'am' ? 'እጥረት: ' : 'Short: ' ?><?= number_format($r['shortage_quantity'], 1) ?> <?= h($r['unit']) ?></div>
                                </div>
                                <div style="color:<?= $step4_purchased ? '#2e7d32' : ($step3_finance ? '#e65100' : 'var(--text-muted)') ?>">
                                    <strong><?= $step4_purchased ? '✓' : '⏳' ?> 4. <?= current_locale() === 'am' ? 'የግዢ ሂደት (PO)' : 'Finance PO' ?></strong>
                                    <div><?= $step4_purchased ? (current_locale() === 'am' ? 'ተገዝቷል' : 'Purchased') : (current_locale() === 'am' ? 'በግዢ ላይ' : 'In Purchasing') ?></div>
                                </div>
                            <?php endif; ?>
                            <div style="color:<?= $step6_sent_dept ? '#2e7d32' : 'var(--text-muted)' ?>">
                                <strong><?= $step6_sent_dept ? '🚚' : '○' ?> <?= $hasShortage ? '5' : '3' ?>. <?= current_locale() === 'am' ? 'ወደ ክፍሉ ተልኳል' : 'Sent to Dept' ?></strong>
                                <div><?= (float)$r['issued_quantity'] > 0 ? number_format($r['issued_quantity'], 1).' '.$r['unit'] : (current_locale() === 'am' ? 'በመጠባበቅ ላይ' : 'Pending') ?></div>
                            </div>
                            <div style="color:<?= $step7_done ? '#2e7d32' : ($canConfirm ? '#d32f2f' : 'var(--text-muted)') ?>">
                                <strong><?= $step7_done ? '✓' : ($canConfirm ? '🔴' : '○') ?> <?= $hasShortage ? '6' : '4' ?>. <?= current_locale() === 'am' ? 'ርክክብ ማረጋገጥ' : 'Confirmation' ?></strong>
                                <div><?= $step7_done ? (current_locale() === 'am' ? 'ተጠናቋል' : 'COMPLETED') : ($canConfirm ? (current_locale() === 'am' ? 'ማረጋገጫ ይጠበቃል' : 'Action Required') : (current_locale() === 'am' ? 'በመጠባበቅ ላይ' : 'Pending')) ?></div>
                            </div>
                        </div>

                        <!-- Confirmation Action -->
                        <?php if ($canConfirm): ?>
                            <div style="background:#e8f5e9;border-left:4px solid #2e7d32;padding:10px 14px;border-radius:4px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                                <div>
                                    <strong style="color:#1b5e20">📦 <?= current_locale() === 'am' ? 'ዕቃው ከመጋዘን ተልኳል (' . number_format($r['issued_quantity'], 1) . ' ' . h($r['unit']) . ')' : 'Material has been dispatched by Warehouse (' . number_format($r['issued_quantity'], 1) . ' ' . h($r['unit']) . ')' ?></strong>
                                    <div style="font-size:12px;color:#2e7d32"><?= current_locale() === 'am' ? 'እባክዎ ዕቃው በክፍልዎ መድረሱን በአካል ያረጋግጡና "ተረክቤያለሁ" የሚለውን ይጫኑ።' : 'Please verify physical receipt of goods at your unit and click confirm below.' ?></div>
                                </div>
                                <form method="post" onsubmit="return confirm('<?= current_locale() === 'am' ? 'ዕቃውን በክፍልዎ በአካል መቀበልዎን ያረጋግጣሉ?' : 'Confirm you have physically received this material at your department?' ?>');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="confirm_received" value="1">
                                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                                    <button class="btn" type="submit" style="background:#2e7d32;border-color:#2e7d32">✓ <?= current_locale() === 'am' ? 'ተረክቤያለሁ (አረጋግጥ)' : 'CONFIRM RECEIVED' ?></button>
                                </form>
                            </div>
                        <?php elseif ($isCompleted): ?>
                            <div style="font-size:12px;color:#2e7d32;font-weight:600">
                                ✓ <?= current_locale() === 'am' ? 'ርክክቡ የተረጋገጠበት ቀን: ' : 'Confirmed received on ' ?><?= h($r['received_confirmed_at']) ?>. (<?= current_locale() === 'am' ? 'ሁኔታ: ተጠናቋል' : 'Status: COMPLETED' ?>)
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
    const catalogItemMap = <?= json_encode(array_combine(
        array_map(fn($i) => mb_strtolower(trim($i['name'])), $items),
        array_map(fn($i) => (int) $i['id'], $items)
    ), JSON_UNESCAPED_UNICODE) ?>;

    function syncItemSelection() {
        const typed = document.getElementById('itemSearchInput').value.trim();
        const match = catalogItemMap[typed.toLowerCase()];
        document.getElementById('itemSelectionType').value = match ? 'catalog' : 'custom';
        document.getElementById('stockItemIdHidden').value = match || '';
    }
    document.getElementById('itemSearchInput').addEventListener('input', syncItemSelection);
    document.getElementById('matReqForm').addEventListener('submit', syncItemSelection);
    </script>

    <?php
    require_once __DIR__ . '/footer.php';
}
