<?php
declare(strict_types=1);

/**
 * Front controller — all requests route through here.
 * Serves static files in PHP's built-in dev server, then dispatches to a page.
 */

if (PHP_SAPI === 'cli-server') {
    $urlPath    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $staticFile = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $urlPath);
    if (is_file($staticFile)) {
        return false;
    }
}

require_once __DIR__ . '/config.php';

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'none'");

spl_autoload_register(function (string $class): void {
    $path = __DIR__ . '/class/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($path)) require_once $path;
});

$cms = new CMS();

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestUri = '/' . trim($requestUri, '/');
if ($requestUri !== '/') $requestUri = rtrim($requestUri, '/');

$routes = require __DIR__ . '/routing.php';
$matched = null; $params = [];

foreach ($routes as $pattern => $file) {
    if (preg_match($pattern, $requestUri, $m)) {
        $matched = $file;
        foreach ($m as $k => $v) if (is_string($k)) $params[$k] = $v;
        break;
    }
}

if (!$matched) {
    http_response_code(404);
    require __DIR__ . '/pages/404.php';
    exit;
}

if (!empty($params['lang'])) $cms->setLang($params['lang']);

require __DIR__ . '/' . $matched;
