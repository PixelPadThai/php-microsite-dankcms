# Dashboard

Password-gated admin at `/dashboard/*`. The shell renders for any logged-in view; each view file lives at `dashboard/views/<name>.php`.

## Auth flow

- `/dashboard/login` — password form, CSRF-protected, rate-limited (5 attempts / 15 min / IP).
- `/dashboard/logout` — destroys the session, bounces back to login.
- Any other `/dashboard/<view>` 302s to login when no session.
- Successful login regenerates the session ID and lands on `/dashboard/content`.

## Views (Phase 1)

| Path | Status |
|---|---|
| `/dashboard/content` | **Strings editor** + Content placeholder (Phase 2) |
| `/dashboard/backups` | **Backups list + restore** |
| `/dashboard/settings` | Phase 2 placeholder |
| `/dashboard/stats` | Phase 3 placeholder |
| `/dashboard/media` | Phase 2 placeholder |
| `/dashboard/audit` | Phase 3 placeholder |
| `/dashboard/system` | Phase 3 placeholder |

## Strings editor (Phase 1)

- Sticky toolbar: search, language tabs (enabled languages only), unsaved-count badge, last-saved timestamp, Save button.
- Sections derived from the substring before the first `_` in each key.
- Per-row textarea per enabled language. Editing marks the row "dirty" (warning border). Reverting to the original clears dirty.
- Save POSTs the full strings JSON to `/dashboard/api/save-strings`. Server:
  1. CSRF + rate-limit check
  2. Validates payload shape `{ key: { lang: "string" } }`
  3. `Backups::create()` → `Backups::rotate(..., 30)`
  4. Atomic write (`tmp + rename`)
  5. Returns `{ ok: true, savedAt }`
- On success: dirty state clears, toast appears, last-saved updates.

## Backups view (Phase 1)

- Lists `strings-*.json` and `content-*.json` backups in `data/backups/`, newest first, with filename + size.
- Click **Restore** → confirmation → POSTs to `/dashboard/api/restore-backup`. The endpoint:
  1. Validates the filename matches `^(strings|content)-YYYY-MM-DD_HHMMSS\.json$` (path-traversal guard)
  2. Takes a safety-copy of the current file via `Backups::create()`
  3. Copies the backup over the live file
  4. Returns `{ ok: true }` and the page reloads

## API endpoints

All under `/dashboard/api/<name>`:

- All require `Auth::isLoggedIn()` (return 401 JSON when missing — not a 302, since these are API calls).
- All require `X-CSRF-Token` header matching a token issued via `Auth::csrfToken()` (single-use).
- POST only (others return 405).
- `Content-Type: application/json` response.

| Endpoint | Purpose |
|---|---|
| `/dashboard/api/save-strings` | Replace `data/strings.json` with the POSTed JSON. |
| `/dashboard/api/restore-backup` | Restore a named backup over its live file. |

The CSRF token is rendered as `<meta name="csrf-token" content="…">` in `dashboard/_shell-top.php` and exposed to JS as `window.MSD.csrf` by `assets/js/dashboard-init.js`.

## Alpine components

All editor logic lives in `assets/js/dashboard.js` and registers via `alpine:init`. Each view that needs JS uses `x-data="<componentName>()"` where the function returns the Alpine data object.

Existing components:

- `stringsEditor()` — the strings editor on `/dashboard/content`.
- `backupsView()` — the backups list on `/dashboard/backups`.
