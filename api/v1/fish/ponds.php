<?php
require_once __DIR__ . '/../../../includes/functions.php';
$user = api_require_auth();
if (!has_permission('fish','view')) api_error('Forbidden',403);
$stmt = db()->query("SELECT p.id, p.name, p.area_sq_m, p.status, COALESCE(SUM(s.stocking_quantity - s.cumulative_mortality),0) AS total_fish FROM fish_ponds p LEFT JOIN fish_stocks s ON s.pond_id=p.id AND s.status='active' GROUP BY p.id ORDER BY p.name");
api_json(['ponds'=>$stmt->fetchAll()]);
