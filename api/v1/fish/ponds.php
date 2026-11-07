<?php
/**
 * GET /api/v1/fish/ponds — list fish ponds and active stock counts
 */
require_once dirname(__DIR__, 3) . '/includes/functions.php';
$user = api_require_auth();
if (!has_permission('fish', 'view')) api_error('Forbidden', 403);

$stmt = db()->query("
    SELECT p.id,
           p.name,
           p.area_sq_m,
           p.status,
           COALESCE(SUM(s.stocking_quantity - s.cumulative_mortality), 0) AS total_fish
    FROM fish_ponds p
    LEFT JOIN fish_stocks s ON s.pond_id = p.id AND s.status = 'active'
    GROUP BY p.id, p.name, p.area_sq_m, p.status
    ORDER BY p.name
");
api_json(['ponds' => $stmt->fetchAll()]);
