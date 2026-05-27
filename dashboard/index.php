<?php
require_once __DIR__ . '/../config.php';
spl_autoload_register(function ($c) { $p = __DIR__ . '/../class/' . str_replace('\\', '/', $c) . '.php'; if (is_file($p)) require $p; });

Auth::require();

$cms = $cms ?? new CMS();
$view = $params['view'] ?? 'content';
$allowed = ['content', 'settings', 'stats', 'media', 'backups', 'audit', 'system'];
if (!in_array($view, $allowed, true)) $view = 'content';

$csrf = Auth::csrfToken();

require __DIR__ . '/_shell-top.php';
require __DIR__ . "/views/{$view}.php";
require __DIR__ . '/_shell-bottom.php';
