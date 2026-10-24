<?php
/**
 * Aleltu EIFMS — API v1 Router
 * Handles: OPTIONS preflight, routing, 404
 */

// CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../includes/functions.php';

// Simple router: map method+path to handler files
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Normalize: strip base path and /api/v1
$path = preg_replace('#^(' . preg_quote(BASE_URL, '#') . ')?/api/v1#', '', $uri);
$path = rtrim($path, '/') ?: '/';

// Route map
$routes = [
    'POST /auth/login'      => __DIR__ . '/auth/login.php',
    'POST /auth/logout'     => __DIR__ . '/auth/logout.php',
    'GET /auth/me'          => __DIR__ . '/auth/me.php',
    'GET /dashboard'        => __DIR__ . '/dashboard/index.php',
    'GET /dairy/cows'       => __DIR__ . '/dairy/cows.php',
    'POST /dairy/milking'   => __DIR__ . '/dairy/milking.php',
    'GET /dairy/milking'    => __DIR__ . '/dairy/milking.php',
    'GET /poultry/flocks'   => __DIR__ . '/poultry/flocks.php',
    'GET /fish/ponds'       => __DIR__ . '/fish/ponds.php',
    'GET /warehouse/stock'  => __DIR__ . '/warehouse/stock.php',
    'GET /employees'        => __DIR__ . '/employees/index.php',
    'POST /employees/attendance' => __DIR__ . '/employees/attendance.php',
    'GET /notifications'    => __DIR__ . '/notifications/index.php',
];

$key = "{$method} {$path}";
if (isset($routes[$key]) && file_exists($routes[$key])) {
    require $routes[$key];
} else {
    http_response_code(404);
    echo json_encode([
        'error'     => 'Not Found',
        'path'      => $path,
        'method'    => $method,
        'available' => array_keys($routes),
    ], JSON_PRETTY_PRINT);
}
