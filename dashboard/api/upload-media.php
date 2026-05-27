<?php
require_once __DIR__ . '/../../config.php';
spl_autoload_register(function ($c) { $p = __DIR__ . '/../../class/' . str_replace('\\', '/', $c) . '.php'; if (is_file($p)) require $p; });

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST only']); exit; }
if (!Auth::checkCsrf($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
    http_response_code(403); echo json_encode(['error' => 'CSRF']); exit;
}
if (!Auth::checkRateLimit('media-upload', 30, 60)) {
    http_response_code(429); echo json_encode(['error' => 'rate-limited']); exit;
}

$file = $_FILES['file'] ?? null;
if (!is_array($file)) { http_response_code(400); echo json_encode(['error' => 'no file']); exit; }

$media = new Media(
    __DIR__ . '/../../data/uploads',
    __DIR__ . '/../../data/content.json',
    __DIR__ . '/../../data/backups'
);
$result = $media->upload($file);
if (!$result['ok']) { http_response_code(422); echo json_encode($result); exit; }

Auth::recordRateLimit('media-upload');
echo json_encode($result);
