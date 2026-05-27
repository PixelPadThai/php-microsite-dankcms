<?php
require_once __DIR__ . '/../../config.php';
spl_autoload_register(function ($c) { $p = __DIR__ . '/../../class/' . str_replace('\\', '/', $c) . '.php'; if (is_file($p)) require $p; });

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: no-store');

if (!Auth::isLoggedIn()) { http_response_code(401); exit('/* unauthorized */'); }

$hex = isset($_GET['hex']) ? (string)$_GET['hex'] : '';
echo Theme::cssScaleFromHex($hex);
