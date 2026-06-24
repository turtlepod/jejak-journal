=== Jejak Journal ===
Contributors: turtlepod
Tags: journal, diary, private, notes
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 0.1.1-alpha
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A private monthly journal WordPress plugin with highlights, todos, and daily notes.

== Description ==

Jejak Journal is a self-hosted WordPress plugin for keeping a private monthly journal. Each user gets their own journal entries organized by month.

Features:
* Monthly journal entries (one per user per month)
* Highlights section — track accomplishments with icons
* To-Do list — monthly task tracking
* Daily notes — journal table with day, date, and notes
* Auto-save — no save button needed
* Vanilla JavaScript — no jQuery dependency
* REST API — headless ready
* Multi-role support — control which user roles can journal

== Installation ==

1. Upload the plugin to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Jejak Journal > Settings to configure allowed roles
4. Use the `[jejak-journal]` shortcode on any page

== Shortcodes ==

`[jejak-journal]` — Renders the full journal interface (highlights, todos, daily notes).

== REST API ==

| Route | Method | Description |
|-------|--------|-------------|
| `/jejak-journal/v1/entry/{year}/{month}` | GET | Get or create journal entry |
| `/jejak-journal/v1/entry/{year}/{month}` | POST | Create journal entry |
| `/jejak-journal/v1/entry/{id}/highlights` | PUT | Update highlights |
| `/jejak-journal/v1/entry/{id}/todos` | PUT | Update todos |
| `/jejak-journal/v1/entry/{id}/notes` | PUT | Update journal notes |

== Changelog ==

= 0.1.0-alpha =
* Initial release
* Custom DB table for journal entries
* Highlights with icon picker
* To-Dos with custom checkboxes
* Journal daily notes table
* Auto-save on changes
* REST API
* Role-based access settings
