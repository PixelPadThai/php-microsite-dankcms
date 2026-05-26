<?php
spl_autoload_register(function (string $class): void {
    $path = dirname(__DIR__) . '/class/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($path)) require_once $path;
});
define('DATA_DIR', dirname(__DIR__) . '/tests/fixtures');
define('ADMIN_PASSWORD_HASH', password_hash('test', PASSWORD_DEFAULT));
define('ADMIN_IP_ALLOWLIST', []);
