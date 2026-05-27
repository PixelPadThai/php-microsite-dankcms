# PHP Microsite Dankcms — AI rules (Claude)

A forked PHP Microsite v2 template, AI-friendly, with JSON data + admin dashboard. The folder on disk is `PHP-MicroSite-Deluxe` (legacy name); the published name is **PHP Microsite Dankcms**.

## Stack

- PHP 8.1+ (front controller in `index.php`)
- Alpine.js 3.14.1 (self-hosted in `assets/js/alpine.min.js`)
- Vanilla CSS with `@layer` (see `styles/`)
- JSON data (default) or Directus (set `DATA_SOURCE` in `config.php`)
- PHPUnit 10 (phar at `vendor/phpunit.phar`, no Composer)
- No Node required

## Folder map

- `data/` — `strings.json` + `content.json` (source of truth). Backups, stats, cache, uploads, audit log are gitignored subfolders.
- `class/` — autoloaded PHP classes. `CMS` is the data facade; `Auth` handles session+CSRF+rate-limit; `Backups` handles save/rotate/restore. Adapters live in `class/adapters/`.
- `pages/` — public pages. One file per top-level route.
- `templates/` — `head.php`, `layout.php`, `footer.php` (public site).
- `dashboard/` — admin app (password-gated). `index.php` is the view router; `_shell-top.php` + `_shell-bottom.php` wrap the chrome; `views/*.php` are the views; `api/*.php` are POST endpoints.
- `styles/` — vanilla CSS, layered. `site.css` is the entry that imports tokens/base/layout/components/utilities. `dashboard.css` is dashboard-only.
- `assets/js/` — `alpine.min.js`, `theme-init.js` (pre-paint dark mode), `dashboard-init.js` (reads CSRF from meta), `dashboard.js` (Alpine components).
- `tests/` — PHPUnit suite. `bootstrap.php` registers the class autoloader + constants needed for tests.
- `docs/` — topic docs (`data-model.md`, `data-api.md`, `css-system.md`, `dashboard.md`). `docs/recipes/` — cookbooks. **`docs/superpowers/` is gitignored** (local-only spec/plan working notes).
- `!.Inspiration_*` — local design references, gitignored, not part of the template.

## Running

Dev server (use `index.php` as router so static + dynamic routes both work):

```bash
php -S localhost:8000 -t . index.php
```

Tests:

```bash
php vendor/phpunit.phar
```

## Hard rules

**DO:**
- Use `$cms->setting()`, `$cms->str()`, `$cms->collection()` for ALL data access. Never `json_decode()` in a page.
- Escape every output with `htmlspecialchars()`.
- Use design tokens (`var(--color-primary)`), never raw hex/rgb in component code.
- Wrap new CSS in the right `@layer`: tokens → base → layout → components → utils. Dashboard styles in `styles/dashboard.css` are loaded after `site.css`.
- Use Alpine for interactivity. No jQuery, no other JS frameworks.
- Self-host any JS dependency in `assets/js/` (CSP blocks CDNs).
- CSRF-check every POST endpoint with `Auth::checkCsrf()`. Rate-limit with `Auth::checkRateLimit()` + `recordRateLimit()`.

**DON'T:**
- Install npm packages — this is a no-build project.
- Write Tailwind utility classes (`bg-amber-500`, `flex-1`, etc.) — use vanilla CSS.
- Add inline `<script>` tags in templates — CSP `script-src 'self' 'unsafe-eval'` blocks them. Use `assets/js/*.js`. (Note: `<script type="application/json">` data blocks ARE allowed — browsers don't execute them.)
- Use inline event handlers (`onclick="..."`) — CSP blocks them. Use Alpine's `@click="..."` instead.
- Edit `data/*.json` by hand when the dashboard is running. Use the editor (it backs up).
- Suppress errors with `@` — handle them or let them surface in dev.

## Security defaults (already wired)

- CSP: `default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-eval'; font-src 'self'; connect-src 'self'; frame-ancestors 'none'`. `'unsafe-eval'` is required for Alpine expression evaluation.
- Headers: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`.
- Session: `MSD_SESS` cookie; HttpOnly, SameSite=Lax, Secure under HTTPS. Regenerated on login.
- CSRF: single-use 32-char hex tokens. Form: `<input name="_csrf">`. Fetch: `X-CSRF-Token` header. Token comes from `<meta name="csrf-token">` on dashboard pages and is exposed as `window.MSD.csrf`.
- Rate limit: login 5/15min/IP, save 60/min/IP. State in `data/.rate-limit.json` (gitignored).

## Common tasks → recipe

- Add a page: `docs/recipes/add-a-page.md`
- Add a collection: `docs/recipes/add-a-collection.md` (Phase 2)
- Add a language: `docs/recipes/add-a-language.md` (Phase 2)
- Theme the brand: `docs/recipes/theme-the-brand.md` (Phase 2)
- Switch to Directus: `docs/recipes/switch-to-directus.md` (Phase 4)

## Phase status

- **Phase 1 (foundation, current):** front controller + JsonAdapter + CMS facade + base CSS + templates + auth + dashboard shell + strings editor + backups view. DONE.
- **Phase 2 (content depth):** content editor (typed forms), settings editor, theme generator (OKLCH), media library, SEO.
- **Phase 3 (operational):** stats, audit view, system view, maintenance mode, auto-revert, `validate.php`.
- **Phase 4 (power tools):** CLI scaffolders, first-run wizard, schema export, PWA shell, Directus adapter, `migrate.php`.

## When stuck

- `docs/troubleshooting.md` — common errors with fixes (Phase 3)
- Run `php validate.php` (Phase 3) for a self-check
- Check `data/audit.jsonl` (Phase 3) to see what changed
