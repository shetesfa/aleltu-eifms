<?php
/**
 * GET /api/v1/warehouse/stock — current inventory levels and reorder warnings
 */
require_once dirname(__DIR__, 3) . '/includes/functions.php';
$user = api_require_auth();
if (!has_permission('warehouse', 'view')) api_error('Forbidden', 403);

$stmt = db()->query("
    SELECT i.id,
           i.sku,
           i.sku AS item_code,
           i.name,
           i.name AS item_name,
           i.unit,
           i.unit AS unit_of_measure,
           i.product_class,
           COALESCE(r.minimum_threshold, 0) AS reorder_level,
           COALESCE(SUM(b.quantity_on_hand), 0) AS stock_on_hand,
           COALESCE(SUM(b.quantity_on_hand), 0) AS total_quantity
    FROM wh_stock_items i
    LEFT JOIN wh_stock_batches b ON b.stock_item_id = i.id
    LEFT JOIN wh_minimum_stock_rules r ON r.stock_item_id = i.id
    GROUP BY i.id, i.sku, i.name, i.unit, i.product_class, r.minimum_threshold
    ORDER BY i.name
");
$items = $stmt->fetchAll();

foreach ($items as &$item) {
    $qty = (float)($item['total_quantity'] ?? $item['stock_on_hand'] ?? 0);
    $reorder = (float)($item['reorder_level'] ?? 0);
    $item['is_low_stock'] = ($reorder > 0) && ($qty < $reorder);
}
unset($item);

api_json(['stock' => $items]);
