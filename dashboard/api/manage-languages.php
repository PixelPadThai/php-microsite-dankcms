<?php
require_once __DIR__ . '/../../config.php';
spl_autoload_register(function ($c) { $p = __DIR__ . '/../../class/' . str_replace('\\', '/', $c) . '.php'; if (is_file($p)) require $p; });

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST only']); exit; }
if (!Auth::checkCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) { http_response_code(403); echo json_encode(['error' => 'CSRF']); exit; }
if (!Auth::checkRateLimit('save', 60, 60)) { http_response_code(429); echo json_encode(['error' => 'rate-limited']); exit; }

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || !isset($body['languages']) || !is_array($body['languages'])) {
    http_response_code(400); echo json_encode(['error' => 'expected { languages: [...] }']); exit;
}

$normalized = [];
$seenCodes  = [];
$defaultSet = false;
foreach ($body['languages'] as $lang) {
    if (!is_array($lang)) continue;
    $code  = strtolower(trim((string)($lang['code'] ?? '')));
    $label = trim((string)($lang['label'] ?? $code));
    if ($code === '' || !preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $code)) {
        http_response_code(422); echo json_encode(['error' => "Invalid language code: $code"]); exit;
    }
    if (isset($seenCodes[$code])) {
        http_response_code(422); echo json_encode(['error' => "Duplicate language code: $code"]); exit;
    }
    $seenCodes[$code] = true;
    $isDefault = !empty($lang['default']);
    $enabled   = $isDefault ? true : !empty($lang['enabled']);
    if ($isDefault) {
        if ($defaultSet) { http_response_code(422); echo json_encode(['error' => 'only one default language']); exit; }
        $defaultSet = true;
    }
    $normalized[] = ['code' => $code, 'label' => $label, 'enabled' => $enabled, 'default' => $isDefault];
}
if (!$defaultSet) { http_response_code(422); echo json_encode(['error' => 'one language must be default']); exit; }

$contentPath = __DIR__ . '/../../data/content.json';
$stringsPath = __DIR__ . '/../../data/strings.json';
$backupDir   = __DIR__ . '/../../data/backups';

$content = json_decode(@file_get_contents($contentPath), true);
$strings = json_decode(@file_get_contents($stringsPath), true);
if (!is_array($content) || !is_array($strings)) {
    http_response_code(500); echo json_encode(['error' => 'data files missing or corrupt']); exit;
}

$content['_meta']['languages'] = $normalized;

$keepCodes = array_flip(array_column($normalized, 'code'));
foreach ($strings as $key => $entry) {
    if (!is_array($entry)) continue;
    foreach (array_keys($entry) as $lang) {
        if (!isset($keepCodes[$lang])) unset($strings[$key][$lang]);
    }
    foreach ($keepCodes as $lang => $_) {
        if (!isset($strings[$key][$lang])) $strings[$key][$lang] = '';
    }
}

Backups::create($contentPath, $backupDir); Backups::rotate($backupDir, 'content', 30);
Backups::create($stringsPath, $backupDir); Backups::rotate($backupDir, 'strings', 30);

$writeAtomic = function (string $path, $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    $tmp  = $path . '.tmp';
    if (file_put_contents($tmp, $json) === false || !rename($tmp, $path)) { @unlink($tmp); return false; }
    return true;
};

if (!$writeAtomic($contentPath, $content) || !$writeAtomic($stringsPath, $strings)) {
    http_response_code(500); echo json_encode(['error' => 'write failed']); exit;
}

Auth::recordRateLimit('save');
echo json_encode(['ok' => true, 'languages' => $normalized]);
