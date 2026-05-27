<?php
require_once __DIR__ . '/../config.php';
spl_autoload_register(function ($c) { $p = __DIR__ . '/../class/' . str_replace('\\', '/', $c) . '.php'; if (is_file($p)) require $p; });
Auth::logout();
header('Location: /dashboard/login');
