<?php
// Child process used by run_tests.php: renders one analytics page as a given role against the TEST database.
// usage: php render_page.php <page> <role> <querystring> [POST-querystring]
[$_, $page, $role, $qs] = $argv;
define('DB_HOST', getenv('AN_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', 'aleltu_analytics_test');
define('DB_USER', getenv('AN_DB_USER') ?: 'root');
define('DB_PASS', getenv('AN_DB_PASS') ?: '');
set_error_handler(fn($n, $s) => str_contains($s, 'already defined'));   // db.php tries to define the same constants
parse_str($qs, $_GET);
if (isset($argv[4])) { parse_str($argv[4], $_POST); $_SERVER['REQUEST_METHOD'] = 'POST'; } else { $_SERVER['REQUEST_METHOD'] = 'GET'; }
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = "/$page?$qs";
session_save_path(sys_get_temp_dir());
@session_start();
$_SESSION['user'] = ['id' => 1, 'role' => $role, 'name' => 'Test', 'locale' => 'en', 'theme' => 'light'];
$_SESSION['csrf_token'] = 'testtoken';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { $_POST['csrf_token'] ??= 'testtoken'; }
chdir(__DIR__ . '/../../modules/admin');
require __DIR__ . '/../../modules/admin/' . $page;
