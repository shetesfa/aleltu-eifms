<?php
/**
 * GET /api/v1/poultry/flocks — list poultry flocks and populations
 */
require_once dirname(__DIR__, 3) . '/includes/functions.php';
$user = api_require_auth();
if (!has_permission('poultry', 'view')) api_error('Forbidden', 403);

$stmt = db()->query("
    SELECT f.id,
           CONCAT('FLOCK-', f.id) AS flock_code,
           f.breed,
           f.initial_population AS opening_count,
           COALESCE(f.current_count, (f.initial_population - f.cumulative_mortality - f.cumulative_culled_sold + f.cumulative_transfers_in)) AS current_count,
           (f.initial_population - f.cumulative_mortality - f.cumulative_culled_sold + f.cumulative_transfers_in) AS live_population,
           f.stocking_date AS start_date,
           f.status,
           h.name AS house
    FROM poultry_flocks f
    JOIN poultry_houses h ON h.id = f.poultry_house_id
    ORDER BY f.status, f.stocking_date DESC
");
api_json(['flocks' => $stmt->fetchAll()]);
