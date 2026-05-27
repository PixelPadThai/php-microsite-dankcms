# Recipe: Add a page

1. **Create the page file** at `pages/about.php`:

   ```php
   <?php
   $pageTitle = 'About';
   require __DIR__ . '/../templates/head.php';
   require __DIR__ . '/../templates/layout.php';
   ?>
   <h1><?= htmlspecialchars($cms->str('about_title', null, 'About')) ?></h1>
   <p><?= htmlspecialchars($cms->str('about_body', null, 'Tell your story.')) ?></p>
   <?php
   require __DIR__ . '/../templates/footer.php';
   ```

2. **Register the route** in `routing.php` (add BEFORE the dashboard catch-all, AFTER the home route):

   ```php
   '#^/about$#' => 'pages/about.php',
   ```

3. **Add the strings** in the dashboard Content view (Strings tab):
   - `about_title` = `"About"`
   - `about_body` = `"…"`

4. **(Optional)** Add a nav link to `templates/layout.php` near the existing `nav_home` link.

5. **Test**: visit `http://localhost:8000/about`.

## Notes

- Page code never reads JSON directly. All data goes through `$cms->str()` / `$cms->setting()` / `$cms->collection()`.
- `$pageTitle` and `$pageDescription` set the `<title>` and meta description in `templates/head.php`.
- Escape every output with `htmlspecialchars()`.
- For dynamic routes (e.g. `/blog/{slug}`), use a regex with named groups: `'#^/blog/(?<slug>[\w-]+)$#'`. The match populates `$params['slug']` in scope when the page runs.
