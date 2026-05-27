<?php
require_once __DIR__ . '/../../config.php';
spl_autoload_register(function ($c) { $p = __DIR__ . '/../../class/' . str_replace('\\', '/', $c) . '.php'; if (is_file($p)) require $p; });

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!Auth::isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit; }

$media = new Media(
    __DIR__ . '/../../data/uploads',
    __DIR__ . '/../../data/content.json',
    __DIR__ . '/../../data/backups'
);
echo json_encode(['ok' => true, 'items' => $media->list()]);
