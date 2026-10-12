<?php
/**
 * GET  /api/v1/dairy/milking            — list recent sessions
 * POST /api/v1/dairy/milking            — record milking yield
 * Body: { "cow_id": 1, "session_date": "2024-01-15", "session_type": "morning", "yield_liters": 8.5 }
 */
require_once __DIR__ . '/../../../includes/functions.php';
$user = api_require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!has_permission('dairy','view')) api_error('Forbidden',403);
    $limit = min(50,(int)($_GET['limit'] ?? 20));
    $stmt = db()->prepare("SELECT e.id, c.tag_number, c.name AS cow_name, e.yield_liters, s.session_date, s.session_type FROM dairy_milk_yield_entries e JOIN dairy_cows c ON c.id=e.cow_id JOIN dairy_milking_sessions s ON s.id=e.milking_session_id ORDER BY s.session_date DESC, e.id DESC LIMIT ?");
    $stmt->bindValue(1,$limit,PDO::PARAM_INT); $stmt->execute();
    api_json(['entries'=>$stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission('dairy','create')) api_error('Forbidden',403);
    $body = json_decode(file_get_contents('php://input'),true) ?? [];
    $cowId       = (int)($body['cow_id'] ?? 0);
    $date        = $body['session_date'] ?? date('Y-m-d');
    $sessionType = $body['session_type'] ?? 'morning';
    $yield       = (float)($body['yield_liters'] ?? 0);

    if (!$cowId || $yield <= 0) api_error('cow_id and yield_liters > 0 are required');
    if (!in_array($sessionType,['morning','evening','midday'])) api_error('session_type must be morning/evening/midday');

    $cow = db()->query("SELECT id,lifecycle_status FROM dairy_cows WHERE id={$cowId}")->fetch();
    if (!$cow) api_error('Cow not found',404);
    if (!in_array($cow['lifecycle_status'],['active','lactating','pregnant'])) api_error('Cow is not active/lactating');

    // Get or create session
    $sess = db()->prepare("SELECT id FROM dairy_milking_sessions WHERE session_date=? AND session_type=?");
    $sess->execute([$date,$sessionType]);
    $session = $sess->fetch();
    if (!$session) {
        $ins = db()->prepare("INSERT INTO dairy_milking_sessions (session_date,session_type,recorded_by) VALUES (?,?,?)");
        $ins->execute([$date,$sessionType,$user['id']]);
        $sessionId = (int)db()->lastInsertId();
    } else {
        $sessionId = (int)$session['id'];
    }
    $ins2 = db()->prepare("INSERT INTO dairy_milk_yield_entries (milking_session_id,cow_id,yield_liters,recorded_by) VALUES (?,?,?,?)");
    $ins2->execute([$sessionId,$cowId,$yield,$user['id']]);
    $entryId = (int)db()->lastInsertId();
    log_activity('dairy','create','dairy_milk_yield_entries',$entryId,"API: yield {$yield}L cow#{$cowId}");
    api_json(['message'=>'Milking yield recorded','entry_id'=>$entryId,'session_id'=>$sessionId],201);
}

api_error('Method not allowed',405);
