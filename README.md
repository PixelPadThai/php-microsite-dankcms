# PHP Microsite Dankcms

A clone-and-go PHP template for small-to-medium websites. Vanilla CSS design system, Alpine.js, JSON content, password-gated admin dashboard. Zero Node required.

## Quick start

```bash
git clone https://github.com/PixelPadThai/php-microsite-dankcms.git my-site
cd my-site
cp config.example.php config.php

# Generate a password hash
php -r "echo password_hash('your-password', PASSWORD_DEFAULT) . PHP_EOL;"
# Paste it into config.php as ADMIN_PASSWORD_HASH

php -S localhost:8000 -t . index.php
```

Visit `http://localhost:8000/` for the site, `http://localhost:8000/dashboard/login` for the admin.

## What's in Phase 1

- Front controller + regex routing
- `CMS` facade backed by a Directus-shaped `JsonAdapter` (swap to Directus later via a config flag)
- Vanilla CSS design system with `@layer`, light/dark mode, design tokens
- Password-gated admin dashboard with session auth, CSRF, and per-IP rate-limiting
- Strings editor (multi-language translation editing) with auto-backup on save
- Backups view with one-click restore (safety-copies current state first)
- PHPUnit suite (`php vendor/phpunit.phar`)

## What's next

Phase 2: content editor (typed forms), settings editor, OKLCH theme generator, media library, SEO suite.
Phase 3: privacy-first stats, audit log view, system view, maintenance mode, validate script.
Phase 4: CLI scaffolders, first-run wizard, schema export, PWA, Directus adapter.

## Docs

- AI rules: `CLAUDE.md`, `AGENTS.md`
- Data: `docs/data-model.md`, `docs/data-api.md`
- CSS: `docs/css-system.md`
- Dashboard: `docs/dashboard.md`
- Recipes: `docs/recipes/`

## License

TBD.
