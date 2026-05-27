<?php
require_once __DIR__ . '/../../config.php';
spl_autoload_register(function ($c) { $p = __DIR__ . '/../../class/' . str_replace('\\', '/', $c) . '.php'; if (is_file($p)) require $p; });

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST only']); exit; }
if (!Auth::checkCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    http_response_code(403); echo json_encode(['error' => 'CSRF']); exit;
}
if (!Auth::checkRateLimit('save', 60, 60)) {
    http_response_code(429); echo json_encode(['error' => 'rate-limited']); exit;
}

$body  = json_decode(file_get_contents('php://input'), true) ?: [];
$name  = (string)($body['name']  ?? '');
$force = (bool)  ($body['force'] ?? false);
if ($name === '') { http_response_code(400); echo json_encode(['error' => 'missing name']); exit; }

$media = new Media(
    __DIR__ . '/../../data/uploads',
    __DIR__ . '/../../data/content.json',
    __DIR__ . '/../../data/backups'
);
$result = $media->delete($name, $force);
if (!$result['ok']) { http_response_code(409); echo json_encode($result); exit; }

Auth::recordRateLimit('save');
echo json_encode($result);
