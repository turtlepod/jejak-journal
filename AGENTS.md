# Jejak Journal — AI Agent Quick Reference

## Identity
- **Purpose**: Private monthly journal with highlights, todos, and daily notes
- **Namespace**: `JejakJournal`
- **PHP**: 8.2+
- **Text domain**: `jejak-journal`

## Constants
| Constant | File | Meaning |
|----------|------|---------|
| `JEJAK_JOURNAL_VERSION` | jejak-journal.php | Version string |
| `JEJAK_JOURNAL_PLUGIN_FILE` | jejak-journal.php | Main plugin file path |
| `JEJAK_JOURNAL_PLUGIN_DIR` | jejak-journal.php | Plugin dir |
| `JEJAK_JOURNAL_PLUGIN_URL` | jejak-journal.php | Plugin URL |

## File Map
```
jejak-journal.php              Entry; constants; loads core
includes/
  core.php                     Bootstrap; activation; enqueue
  class-jejak-db.php           DB schema + CRUD + permissions
  rest.php                     REST routes
  shortcodes.php               [jejak-journal] shortcode
  admin.php                    Settings page (role selection)
templates/
  journal.php                  Main journal template (header, sections, toast)
assets/
  js/front.js                  Vanilla JS: load, render, auto-save, icon picker
  css/front.css                All frontend styles
```

## Data Model
Single custom table: `{prefix}_jejak_journal_entries`
| Field | Type | Notes |
|-------|------|-------|
| id | BIGINT UNSIGNED | PK, auto-increment |
| user_id | BIGINT UNSIGNED | FK to wp_users |
| year | INT | 4-digit year |
| month | TINYINT | 1-12 |
| slug | VARCHAR(50) | e.g. "jj-june-2026" |
| highlights | LONGTEXT | JSON array of {icon, text} |
| todos | LONGTEXT | JSON array of {text, done} |
| journal_notes | LONGTEXT | JSON array of {day, date, day_name, notes} |
| created_at / updated_at | DATETIME | Timestamps |

Unique key: (user_id, year, month)

## REST API (`/wp-json/jejak-journal/v1`)
| Route | Methods | Purpose |
|-------|---------|---------|
| `/entry/{year}/{month}` | GET, POST | Get/create entry |
| `/entry/{id}/highlights` | PUT | Update highlights |
| `/entry/{id}/todos` | PUT | Update todos |
| `/entry/{id}/notes` | PUT | Update journal notes |

## Shortcodes
`[jejak-journal]` — Full journal interface. Requires logged-in user with allowed role.

## Options
- `jejak_journal_roles` — Array of role slugs allowed to journal (default: `['administrator']`)
- `jejak_journal_db_version` — Internal schema version

## JS Architecture
Vanilla JS, no jQuery. IIFE module in `assets/js/front.js`.
- `window.jejakJournalData` — Localized config (restUrl, restNonce, i18n, icons)
- API: `apiFetch()` helper uses fetch with nonce
- Sections: highlights (icon+text, icon picker modal), todos (custom checkboxes, contenteditable), journal (table with auto-resize textareas)
- Auto-save: debounced PUT requests on input/change, toast notification
- Icon picker: modal with grid of emoji icons, search filter

## Build
No build step currently. Assets loaded directly from `assets/` directory.
