<?php
/**
 * GET  /api/v1/dairy/cows       — list cows (paginated)
 * GET  /api/v1/dairy/cows?id=X  — single cow detail
 */
require_once __DIR__ . '/../../../includes/functions.php';
$user = api_require_auth();
if (!has_permission('dairy','view')) api_error('Forbidden',403);

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $cow = db()->query("SELECT * FROM dairy_cows WHERE id={$id}")->fetch();
    if (!$cow) api_error('Cow not found', 404);
    // Last 5 yields
    $yields = db()->prepare("SELECT e.yield_liters, s.session_date, s.session_type FROM dairy_milk_yield_entries e JOIN dairy_milking_sessions s ON s.id=e.milking_session_id WHERE e.cow_id=? ORDER BY s.session_date DESC LIMIT 5");
    $yields->execute([$id]); $cow['recent_yields'] = $yields->fetchAll();
    api_json($cow);
}

$page   = max(1,(int)($_GET['page'] ?? 1));
$limit  = min(100,(int)($_GET['limit'] ?? 20));
$offset = ($page-1)*$limit;
$status = in_array($_GET['status'] ?? '', ['active','pregnant','dry','deceased','sold']) ? $_GET['status'] : null;

$where = $status ? "WHERE lifecycle_status=?" : "";
$params = $status ? [$status] : [];
$total = (int)db()->prepare("SELECT COUNT(*) FROM dairy_cows {$where}")->execute($params) ?
    db()->prepare("SELECT COUNT(*) FROM dairy_cows {$where}")->execute($params) : 0;
$cnt = db()->prepare("SELECT COUNT(*) FROM dairy_cows {$where}"); $cnt->execute($params);
$total = (int)$cnt->fetchColumn();

$stmt = db()->prepare("SELECT id,tag_number,name,breed,sex,lifecycle_status,date_of_birth FROM dairy_cows {$where} ORDER BY tag_number LIMIT {$limit} OFFSET {$offset}");
$stmt->execute($params);

api_json(['total'=>$total,'page'=>$page,'limit'=>$limit,'cows'=>$stmt->fetchAll()]);
