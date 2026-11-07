<?php
/**
 * GET  /api/v1/dairy/cows       — list cows (paginated)
 * GET  /api/v1/dairy/cows?id=X  — single cow detail
 */
require_once dirname(__DIR__, 3) . '/includes/functions.php';
$user = api_require_auth();
if (!has_permission('dairy', 'view')) api_error('Forbidden', 403);

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $cow = db()->query("SELECT * FROM dairy_cows WHERE id = {$id}")->fetch();
    if (!$cow) api_error('Cow not found', 404);
    // Last 5 yields
    $yields = db()->prepare("
        SELECT e.yield_liters, s.session_date, s.session_type 
        FROM dairy_milk_yield_entries e 
        JOIN dairy_milking_sessions s ON s.id = e.milking_session_id 
        WHERE e.cow_id = ? 
        ORDER BY s.session_date DESC 
        LIMIT 5
    ");
    $yields->execute([$id]);
    $cow['recent_yields'] = $yields->fetchAll();
    api_json($cow);
}

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = min(100, (int)($_GET['limit'] ?? 20));
$offset = ($page - 1) * $limit;
$validStatuses = ['registered', 'calf', 'heifer', 'milking', 'dry', 'pregnant', 'sold', 'deceased', 'active'];
$statusParam = $_GET['status'] ?? '';
$status = in_array($statusParam, $validStatuses, true) ? $statusParam : null;

if ($status === 'active') {
    $where = "WHERE lifecycle_status NOT IN ('sold', 'deceased')";
    $params = [];
} elseif ($status !== null) {
    $where = "WHERE lifecycle_status = ?";
    $params = [$status];
} else {
    $where = "";
    $params = [];
}

$cnt = db()->prepare("SELECT COUNT(*) FROM dairy_cows {$where}");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();

$stmt = db()->prepare("
    SELECT id, tag_number, name, breed, sex, lifecycle_status, date_of_birth, acquired_date, purchase_price, source 
    FROM dairy_cows {$where} 
    ORDER BY tag_number 
    LIMIT {$limit} OFFSET {$offset}
");
$stmt->execute($params);

api_json([
    'total' => $total,
    'page'  => $page,
    'limit' => $limit,
    'cows'  => $stmt->fetchAll(),
]);
