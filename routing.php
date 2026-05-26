<?php
/**
 * URL patterns → page files.
 * Patterns are regex; named groups (?<name>...) become $params['name'].
 * Routes are tested in order; first match wins.
 */

return [
    '#^/$#'                          => 'pages/home.php',
    '#^/(?<lang>th)$#'                => 'pages/home.php',
    '#^/dashboard(?:/(?<view>[\w-]+))?(?:/(?<action>[\w-]+))?$#' => 'dashboard/index.php',
    '#^/dashboard/api/(?<endpoint>[\w-]+)$#' => 'dashboard/api/router.php',
    '#^/health\.json$#'              => 'health.php',
];
