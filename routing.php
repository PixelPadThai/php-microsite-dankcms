<?php
/**
 * URL patterns → page files.
 * Patterns are regex; named groups (?<name>...) become $params['name'].
 * Routes are tested in order; first match wins.
 */

return [
    '#^/$#'                          => 'pages/home.php',
    '#^/(?<lang>th)$#'                => 'pages/home.php',
    '#^/dashboard/logout$#'                  => 'dashboard/logout.php',
    '#^/dashboard/login$#'                   => 'dashboard/login.php',
    '#^/dashboard/api/(?<endpoint>[\w-]+)$#' => 'dashboard/api/router.php',
    '#^/dashboard(?:/(?<view>[\w-]+))?$#'    => 'dashboard/index.php',
    '#^/health\.json$#'              => 'health.php',
];
