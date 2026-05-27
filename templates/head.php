<?php
/** @var CMS $cms */
$siteName = $cms->setting('site_name') ?? 'My Site';
$title = ($pageTitle ?? '') ? "$pageTitle — $siteName" : $siteName;
$brandPrimary = $cms->setting('brand_primary') ?? '#0CC4B4';
$description = $pageDescription ?? ($cms->str('site_tagline') ?? '');
$baseUrl = rtrim(defined('SITE_URL') ? SITE_URL : '', '/');
$canonical = $baseUrl . ($_SERVER['REQUEST_URI'] ?? '/');
$ogImage = $pageImage ?? ($cms->setting('logo') ?: '');
if ($ogImage !== '' && $ogImage[0] === '/') $ogImage = $baseUrl . $ogImage;
$ogType = $pageType ?? 'website';
?>
<!doctype html>
<html lang="<?= htmlspecialchars($cms->lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?></title>
<meta name="description" content="<?= htmlspecialchars($description) ?>">
<link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">
<meta property="og:title"       content="<?= htmlspecialchars($title) ?>">
<meta property="og:description" content="<?= htmlspecialchars($description) ?>">
<meta property="og:type"        content="<?= htmlspecialchars($ogType) ?>">
<meta property="og:url"         content="<?= htmlspecialchars($canonical) ?>">
<meta property="og:site_name"   content="<?= htmlspecialchars($siteName) ?>">
<?php if ($ogImage !== ''): ?>
<meta property="og:image"       content="<?= htmlspecialchars($ogImage) ?>">
<meta name="twitter:image"      content="<?= htmlspecialchars($ogImage) ?>">
<?php endif; ?>
<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="<?= htmlspecialchars($title) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($description) ?>">
<style><?= Theme::cssScaleFromHex($brandPrimary) ?></style>
<link rel="stylesheet" href="/styles/site.css">
<script src="/assets/js/theme-init.js"></script>
<script src="/assets/js/alpine.min.js" defer></script>
</head>
