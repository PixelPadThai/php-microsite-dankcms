<?php
require_once __DIR__ . '/../../config.php';
spl_autoload_register(function ($c) { $p = __DIR__ . '/../../class/' . str_replace('\\', '/', $c) . '.php'; if (is_file($p)) require $p; });

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST only']); exit; }
if (!Auth::checkCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) { http_response_code(403); echo json_encode(['error' => 'CSRF']); exit; }
if (!Auth::checkRateLimit('save', 60, 60)) { http_response_code(429); echo json_encode(['error' => 'rate-limited']); exit; }

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) { http_response_code(400); echo json_encode(['error' => 'invalid JSON']); exit; }

foreach ($body as $k => $entry) {
    if (!is_array($entry)) { http_response_code(422); echo json_encode(['error' => "Bad entry: $k"]); exit; }
    foreach ($entry as $lang => $val) {
        if (!is_string($val)) { http_response_code(422); echo json_encode(['error' => "Non-string at $k.$lang"]); exit; }
    }
}

$target = __DIR__ . '/../../data/strings.json';
$backupDir = __DIR__ . '/../../data/backups';
Backups::create($target, $backupDir);
Backups::rotate($backupDir, 'strings', 30);

$json = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
$tmp = $target . '.tmp';
if (file_put_contents($tmp, $json) === false || !rename($tmp, $target)) {
    @unlink($tmp); http_response_code(500); echo json_encode(['error' => 'write failed']); exit;
}

Auth::recordRateLimit('save');
echo json_encode(['ok' => true, 'savedAt' => date('c')]);
