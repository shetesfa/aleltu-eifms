<?php
require_once __DIR__ . '/../../../includes/functions.php';
$user = api_require_auth();
if (!has_permission('warehouse','view')) api_error('Forbidden',403);
$stmt = db()->query("SELECT i.id,i.name,i.sku,i.unit,i.reorder_level,COALESCE(SUM(b.quantity_remaining),0) AS stock_on_hand FROM wh_stock_items i LEFT JOIN wh_stock_batches b ON b.stock_item_id=i.id GROUP BY i.id ORDER BY i.name");
$items = $stmt->fetchAll();
foreach ($items as &$item) { $item['is_low_stock'] = (float)$item['stock_on_hand'] < (float)$item['reorder_level']; }
api_json(['stock'=>$items]);
