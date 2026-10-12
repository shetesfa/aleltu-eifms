<?php
/** POST /api/v1/auth/logout — invalidate token */
require_once __DIR__ . '/../../../includes/functions.php';
$user = api_require_auth();
db()->prepare("UPDATE users SET api_token = NULL WHERE id = ?")->execute([$user['id']]);
api_json(['message' => 'Logged out successfully']);
