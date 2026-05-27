# Data model

Two JSON files live in `data/`:

- `data/strings.json` — translations
- `data/content.json` — meta, schemas, settings, collections

Both are Directus-shaped so the same code paths can run against Directus later (Phase 4) without page-level changes.

## `data/strings.json`

Flat `key → { lang: value }` map.

```json
{
  "home_title":     { "en": "Welcome",        "th": "ยินดีต้อนรับ" },
  "site_tagline":   { "en": "A small site.",  "th": "เว็บไซต์ขนาดเล็ก" },
  "nav_home":       { "en": "Home",           "th": "หน้าแรก" }
}
```

- Keys are `snake_case`, ASCII only.
- The editor groups by prefix (the substring before the first `_`) — `home_*` keys cluster in a `home` section.
- Keys for disabled languages may exist but are hidden in the editor.

## `data/content.json`

```json
{
  "_meta": {
    "schema_version": 1,
    "languages": [
      { "code": "en", "label": "English", "enabled": true,  "default": true  },
      { "code": "th", "label": "ไทย",     "enabled": false, "default": false }
    ]
  },
  "_schemas": {
    "settings": { "site_name": { "type": "string", "required": true }, ... },
    "pages":    { "id": { "type": "string", "primary": true }, "slug": { ... }, ... }
  },
  "settings": {
    "site_name": "My Site",
    "brand_primary": "#0CC4B4",
    "social": { "facebook": "", "instagram": "" }
  },
  "collections": {
    "pages": [
      { "id": "home", "slug": "/", "title_key": "home_title", "published": true }
    ]
  }
}
```

### Field types (used by the typed form generator in Phase 2)

| Type | Editor input |
|---|---|
| `string` | text input |
| `text` | textarea |
| `string_ref` | dropdown of `strings.json` keys (auto-completes; create-on-the-fly button) |
| `number` | number input |
| `boolean` | toggle |
| `image` | media-library picker |
| `color` | color picker |
| `email`, `url`, `tel` | typed inputs |
| `select` | dropdown from `options: []` |
| `object` | nested form from `fields` |
| `array` | repeater of `item: { type }` |
| `reference` | select another collection record |

## Mutation rules

- Page code **never** touches `json_decode()` directly — always go through the `CMS` facade.
- Dashboard saves backup before write, then write atomically (`tmp + rename`).
- Editing `data/*.json` by hand while the dashboard is running is unsafe (writes can interleave).
