<?php
require_once __DIR__ . '/../../config.php';
spl_autoload_register(function ($c) { $p = __DIR__ . '/../../class/' . str_replace('\\', '/', $c) . '.php'; if (is_file($p)) require $p; });

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST only']); exit; }
if (!Auth::checkCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) { http_response_code(403); echo json_encode(['error' => 'CSRF']); exit; }

$body = json_decode(file_get_contents('php://input'), true);
$name = $body['name'] ?? '';
if (!preg_match('/^(strings|content)-\d{4}-\d{2}-\d{2}_\d{6}\.json$/', $name)) {
    http_response_code(400); echo json_encode(['error' => 'bad name']); exit;
}
$prefix = explode('-', $name)[0];
$ok = Backups::restore(__DIR__ . '/../../data/backups', $name, __DIR__ . "/../../data/{$prefix}.json");
echo json_encode($ok ? ['ok' => true] : ['error' => 'restore failed']);
