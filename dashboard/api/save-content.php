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

$target = __DIR__ . '/../../data/content.json';
$current = json_decode(@file_get_contents($target), true);
if (!is_array($current)) { http_response_code(500); echo json_encode(['error' => 'content.json missing or corrupt']); exit; }

$meta    = $current['_meta']    ?? [];
$schemas = $current['_schemas'] ?? [];

$r = ContentValidator::validate($body, $schemas, $meta);
if (!$r['ok']) {
    http_response_code(422);
    echo json_encode(['error' => $r['errors'][0] ?? 'validation failed', 'errors' => $r['errors']]);
    exit;
}

$backupDir = __DIR__ . '/../../data/backups';
Backups::create($target, $backupDir);
Backups::rotate($backupDir, 'content', 30);

$json = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
$tmp = $target . '.tmp';
if (file_put_contents($tmp, $json) === false || !rename($tmp, $target)) {
    @unlink($tmp); http_response_code(500); echo json_encode(['error' => 'write failed']); exit;
}

Auth::recordRateLimit('save');
echo json_encode(['ok' => true, 'savedAt' => date('c')]);
