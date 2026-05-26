  </div>
</main>
<footer style="border-top:1px solid var(--color-border);padding-block:var(--space-6)">
  <div class="container u-text-sm u-muted">
    © <?= date('Y') ?> <?= htmlspecialchars($cms->setting('site_name') ?? 'My Site') ?> · <?= htmlspecialchars($cms->str('site_tagline')) ?>
  </div>
</footer>
</body>
</html>
