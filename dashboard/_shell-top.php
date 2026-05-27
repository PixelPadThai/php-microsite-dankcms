<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
<title>Dashboard · <?= htmlspecialchars($cms->setting('site_name') ?? '') ?></title>
<link rel="stylesheet" href="/styles/site.css">
<link rel="stylesheet" href="/styles/dashboard.css">
<script src="/assets/js/alpine.min.js" defer></script>
<script src="/assets/js/dashboard-init.js"></script>
<script src="/assets/js/dashboard.js" defer></script>
<script src="/assets/js/typedForm.js" defer></script>
<script src="/assets/js/contentEditor.js" defer></script>
</head>
<body class="dash-body" x-data="{}">
<aside class="dash-sidebar">
  <h1 class="dash-sidebar__title"><?= htmlspecialchars($cms->setting('site_name') ?? 'Admin') ?></h1>
  <nav class="dash-sidebar__nav">
    <?php foreach (['content' => 'Content','settings' => 'Settings','stats' => 'Stats','media' => 'Media','backups' => 'Backups','audit' => 'Audit','system' => 'System'] as $key => $label): ?>
      <a href="/dashboard/<?= $key ?>" class="dash-nav-item<?= $view === $key ? ' is-active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
    <a href="/dashboard/logout" class="dash-nav-item">Logout</a>
  </nav>
</aside>
<main class="dash-main">
