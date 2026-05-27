<?php
$dir = __DIR__ . '/../../data/backups';
$strings = Backups::list($dir, 'strings');
$content = Backups::list($dir, 'content');
?>
<header class="dash-page-header"><h2>Backups</h2></header>
<p class="u-muted u-mb-4">Last 30 of each are kept. Restoring also backs up the current file first.</p>

<div x-data="backupsView()">
  <div class="card u-mb-4">
    <h3>strings.json</h3>
    <?php if (!$strings): ?>
      <p class="u-muted">No backups yet.</p>
    <?php else: ?>
      <ul class="backups-list">
      <?php foreach ($strings as $b): ?>
        <li class="backups-list__row">
          <span><?= htmlspecialchars($b['name']) ?> <span class="u-muted u-text-xs">· <?= number_format($b['size'] / 1024, 1) ?> KB</span></span>
          <button class="btn btn-sm btn-ghost" @click="restore('<?= htmlspecialchars($b['name']) ?>')">Restore</button>
        </li>
      <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>content.json</h3>
    <?php if (!$content): ?>
      <p class="u-muted">No backups yet.</p>
    <?php else: ?>
      <ul class="backups-list">
      <?php foreach ($content as $b): ?>
        <li class="backups-list__row">
          <span><?= htmlspecialchars($b['name']) ?> <span class="u-muted u-text-xs">· <?= number_format($b['size'] / 1024, 1) ?> KB</span></span>
          <button class="btn btn-sm btn-ghost" @click="restore('<?= htmlspecialchars($b['name']) ?>')">Restore</button>
        </li>
      <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div x-show="toast" x-transition class="toast" :class="toastClass" x-text="toast" x-cloak></div>
</div>
