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
    '#^/dashboard/api/save-content$#'        => 'dashboard/api/save-content.php',
    '#^/dashboard/api/preview-theme$#'       => 'dashboard/api/preview-theme.php',
    '#^/dashboard/api/manage-languages$#'    => 'dashboard/api/manage-languages.php',
    '#^/dashboard/api/restore-backup$#'      => 'dashboard/api/restore-backup.php',
    '#^/dashboard(?:/(?<view>[\w-]+))?$#'    => 'dashboard/index.php',
    '#^/health\.json$#'              => 'health.php',
];
