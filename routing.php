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
    '#^/dashboard/api/save-strings$#'        => 'dashboard/api/save-strings.php',
    '#^/dashboard/api/restore-backup$#'      => 'dashboard/api/restore-backup.php',
    '#^/dashboard(?:/(?<view>[\w-]+))?$#'    => 'dashboard/index.php',
    '#^/health\.json$#'              => 'health.php',
];
