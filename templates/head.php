<?php
/** @var CMS $cms */
$siteName = $cms->setting('site_name') ?? 'My Site';
$title = ($pageTitle ?? '') ? "$pageTitle — $siteName" : $siteName;
$brandPrimary = $cms->setting('brand_primary') ?? '#0CC4B4';
?>
<!doctype html>
<html lang="<?= htmlspecialchars($cms->lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?></title>
<meta name="description" content="<?= htmlspecialchars($pageDescription ?? '') ?>">
<style><?= Theme::cssScaleFromHex($brandPrimary) ?></style>
<link rel="stylesheet" href="/styles/site.css">
<script src="/assets/js/theme-init.js"></script>
<script src="/assets/js/alpine.min.js" defer></script>
</head>
