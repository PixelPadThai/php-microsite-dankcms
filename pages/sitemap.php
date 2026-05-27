<?php
/** @var CMS $cms */
header('Content-Type: application/xml; charset=utf-8');

$routes  = require __DIR__ . '/../routing.php';
$baseUrl = rtrim(defined('SITE_URL') ? SITE_URL : 'http://localhost', '/');
$root    = dirname(__DIR__);

$urls = [];

foreach ($routes as $pattern => $file) {
    if (!preg_match('#^\#\^/([a-z0-9-]*)\$\#$#', $pattern, $m)) continue;
    $loc = '/' . $m[1];
    $absPath = $root . '/' . $file;
    $mtime = is_file($absPath) ? filemtime($absPath) : time();
    $urls[$loc] = ['loc' => $loc, 'lastmod' => $mtime];
}

$pages = $cms->collection('pages')->get();
foreach ($pages as $row) {
    if (empty($row['published'])) continue;
    $slug = (string)($row['slug'] ?? '');
    if ($slug === '') continue;
    $loc = $slug[0] === '/' ? $slug : ('/' . $slug);
    if (!isset($urls[$loc])) {
        $urls[$loc] = ['loc' => $loc, 'lastmod' => filemtime($root . '/data/content.json') ?: time()];
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($baseUrl . $u['loc'], ENT_XML1) . '</loc>' . "\n";
    echo '    <lastmod>' . date('c', $u['lastmod']) . '</lastmod>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>' . "\n";
