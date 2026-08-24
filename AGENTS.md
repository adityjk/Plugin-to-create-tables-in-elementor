# WP Table Builder — Agent Context

## Project
WordPress plugin: visual table builder (like ACF, but for tables). Admin
builds tables via a PHP-rendered builder UI; tables are inserted into pages
via Shortcode, Gutenberg Block, or Elementor Widget. Frontend uses
DataTables.js for search/sort/filter/pagination.

## Stack
- Backend: raw PHP, OOP, no framework (standard WordPress plugin conventions)
- Frontend interactivity: vanilla JS (no build step, no JSX yet — intentional,
  see "Current phase" below)
- Self-update: custom updater (`includes/class-updater.php`), queries the
  GitHub Releases API and injects updates into WP's native update system.
  No third-party update library — do not reintroduce PUC/vendor libs.
- Packaging: `package.py` builds a distributable zip into `dist/`

## Environment
- Local dev via Docker Compose (`docker-compose.yml`): WordPress + MariaDB +
  phpMyAdmin. WordPress on http://localhost:8080, phpMyAdmin on :8081.
- Plugin folder is bind-mounted from host into the container at
  `wp-content/plugins/wp-table-builder/`.
- **Known permission gotcha**: container runs as `www-data` (UID 33), host
  files are owned by the host user (UID 1000). WordPress core update/install
  routines need write access to `wp-content/plugins/` (the parent dir, not
  just the plugin dir) or they fail with "files could not be copied" or
  "directory already exists and could not be removed" — the latter can
  **silently wipe the plugin directory's contents** if only the inner dir is
  writable but not the parent. Always ensure both
  `wp-content/plugins/` and `wp-content/plugins/wp-table-builder/` are
  writable (`chmod a+w`) before testing install/update flows.
- PHP CLI is not available in this environment (`php -l` will fail) — verify
  syntax by careful reading, not by shelling out to php.

## File structure (target)
```
wp-table-builder/
├── wp-table-builder.php        # bootstrap, requirement checks, hooks
├── uninstall.php                # DB cleanup on uninstall
├── readme.txt
├── includes/
│   ├── class-activator.php      # dbDelta table creation
│   ├── class-sanitizer.php      # centralized input sanitization
│   ├── class-post-type.php      # CPT wtb_table registration
│   ├── class-render.php         # single render engine, used by shortcode/
│   │                             # block/Elementor (DRY — don't duplicate)
│   ├── class-shortcode.php
│   ├── class-block.php
│   ├── class-elementor-widget.php
│   ├── class-rest-controller.php
│   └── class-admin-page.php
├── assets/css/{admin,frontend}.css
└── assets/js/{admin-builder,frontend,block-editor}.js
```

## Coding conventions (requested by user)
- Prioritize readability over line count padding — do NOT inflate files to
  hit an artificial line target. A short, single-purpose file (e.g.
  class-shortcode.php) is correct if its job is small.
- Keep functions short and single-purpose; extract shared logic instead of
  duplicating it across files (e.g. rendering logic belongs in
  class-render.php only, never re-implemented in shortcode/block/widget).
- Comment *why*, not *what*, for non-obvious logic. Avoid comment noise on
  self-explanatory lines.
- User is comfortable reading JS/TS but still learning backend/OOP concepts
  and async patterns — prefer clear, explicit code over clever one-liners,
  especially in PHP.

## Security baseline (established via prior review — keep these invariants)
- All user input sanitized via centralized `WTB_Sanitizer` methods — never
  inline `sanitize_text_field` calls scattered across files.
- All output escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` for
  rich text) at render time, not at storage time.
- All DB queries via `$wpdb->prepare()` or `$wpdb->insert/update/delete` with
  explicit format arrays — no raw string interpolation into SQL.
- All state-changing admin actions gated by `current_user_can('manage_options')`
  and nonce verification (`check_admin_referer` / REST nonce).
- CSV export must neutralize formula-injection characters (`=`, `+`, `-`,
  `@`, tab, CR) at the start of any cell value before `fputcsv`.
- Public-facing endpoints (e.g. anonymous form submission) need rate
  limiting (transient-based per-IP throttle is the established pattern).
- No test/debug scripts (`test-*.php`) should live outside
  `wp-content/plugins/wp-table-builder/` — anything in the repo root is
  servable if the repo root is ever deployed as webroot.

## Packaging gotcha
`package.py` must NOT exclude `vendor/` — `vendor/plugin-update-checker/` is
a hard runtime dependency (`require`d directly in wp-table-builder.php).
Only exclude `.git`, `node_modules`, test files. Always verify the built zip
file count includes the full vendor tree before distributing.

> NOTE: vendor/PUC was removed in favor of the custom updater; `package.py`
> excludes `vendor/` again. If a vendored dependency is ever reintroduced,
> update this section and package.py together.

## Version bumps
Every release needs the version updated in TWO places (package.py auto-detects
the header, so the zip filename follows automatically):
1. `Version:` in the plugin header comment (wp-table-builder.php)
2. `WTB_VERSION` constant (wp-table-builder.php)

## Git workflow
User had two incidents of losing uncommitted work (once to a bad `mv`, once
to a failed WP install that wiped the plugin directory). Commit after every
meaningful, verified-working change — don't let multiple fixes pile up
uncommitted. Always confirm the working tree is clean/expected via
`git status` before and after risky operations (permission changes,
install/update testing, file moves).