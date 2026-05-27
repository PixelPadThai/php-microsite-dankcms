# CSS system

Vanilla CSS, layered. No build step required. No Tailwind.

## Layer order

`styles/site.css` is the entry. It declares the cascade order and imports each layer:

```css
@layer tokens, base, layout, components, utils;

@import "./tokens.css"     layer(tokens);
@import "./base.css"       layer(base);
@import "./layout.css"     layer(layout);
@import "./components.css" layer(components);
@import "./utilities.css"  layer(utils);
```

When you add new CSS, put it in the layer matching its purpose:

- **tokens** — CSS custom properties (colors, spacing, radii, motion). No selectors with side effects.
- **base** — element resets, body/html typography.
- **layout** — page-level structure (`.container`, `.app-shell`, `.app-main`).
- **components** — reusable primitives (`.btn`, `.card`, `.field`, `.modal-*`, `.toast`, `.badge`).
- **utils** — `u-*` single-purpose helpers (flex, gap, truncate, text size, muted).

Dashboard styles live in `styles/dashboard.css` and load after `site.css` on dashboard pages.

## Tokens

All color and spacing decisions go through tokens declared in `styles/tokens.css`:

```css
var(--color-primary)         /* brand */
var(--color-bg-page) / -bg-card
var(--color-fg-1) / -fg-2 / -fg-3
var(--color-border) / -border-soft
var(--color-success) / -warning / -danger / -info

var(--space-1)  /* 0.25rem */ ... var(--space-12)  /* 3rem */

var(--radius-sm) / --radius / --radius-lg / --radius-xl / --radius-full

var(--shadow-sm) / --shadow / --shadow-lg

var(--ease) / --dur-fast / --dur / --dur-slow
```

**Never** write raw `#xxxxxx`, `rgb(...)`, or pixel values for spacing in component code. Use tokens.

## Dark mode

Tokens have light and dark variants. `html.dark` flips them. `color-scheme: light dark` ensures native form controls follow.

The user's preference is stored in `localStorage.msd_theme = 'dark' | 'light'`. A small synchronous script at `assets/js/theme-init.js` reads this and applies `.dark` before paint, avoiding flash-of-light. The dark toggle lives in the public-site header (`templates/layout.php`) as an Alpine button.

## Theme generation (Phase 2)

`Theme::cssScaleFromHex($brand)` will convert a single brand hex to an OKLCH lightness scale (`--color-primary-50` … `--color-primary-900`) and inline it in `<head>`. Until Phase 2 ships, the scale is hardcoded in `tokens.css`.

## Components

Brand-agnostic primitives only. If you find yourself writing a one-off class for a single component, prefer composing existing utilities and adding only the specific styles in a new layer-bound rule.

## Browser support

Chromium (last 2), Firefox (last 2), Safari 15.4+, Edge. No IE. OKLCH is the floor.
