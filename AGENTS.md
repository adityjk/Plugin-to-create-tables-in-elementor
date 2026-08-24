# WP Table Builder v2 — Agent Context

## Why v2 exists
v1 worked functionally (security-reviewed, feature-complete) but was
rebuilt twice after the same class of incident: a failed WordPress
"Update"/"Install" action deleted the plugin directory's contents while
lacking permission to remove the directory itself, and that happened
before work was committed. v2's job is the same feature set, built once,
under stricter process rules that prevent repeating that failure mode.

## Non-negotiable process rules
These exist because they were violated in v1 and caused real data loss.

1. **Commit after every file, not after every session.** Every time a
   file reaches a working state, stop and tell the user to run
   `git add . && git commit`. Do not batch multiple files into "I'll
   mention it at the end" — say it after each one.
2. **Never treat `wp-admin`'s Update/Install button as a safe test** until
   the working tree is committed. If asked to help with an update/install
   flow, check `git status` first; if there are uncommitted changes, say
   so and wait for a commit before proceeding.
3. **Permissions are the user's action, not silently assumed.** If a task
   might need `chmod`/`chown` on the host, tell the user the exact command
   to run themselves — do not assume permissions are already correct.
4. **Read before you write.** Before recreating or fixing any file, run
   `git status` / `git diff` / list the actual directory contents. Never
   assume a file's content from memory of an earlier session — verify
   what's on disk first.

## Stack
- Backend: PHP, OOP, no framework, standard WordPress plugin conventions.
- Frontend interactivity: vanilla JS (no build step, no JSX).
- Self-update: `plugin-update-checker`, pointed at GitHub Releases (see
  design.md "Update mechanism").
- Packaging: a script builds a distributable zip into `dist/` (`dist/` is
  gitignored — it is a build artifact, never committed, never assumed to
  exist).

## Code style (hard limits, not guidelines)
- **Max 80 characters per line.** Wrap, don't horizontal-scroll.
- **Max 500 lines per file, target 200–350.** If a file is trending past
  500, split it — that's a sign it has more than one responsibility.
- **No padding.** A file that naturally finishes at 40 lines stays at 40
  lines. Never add filler comments, restated docblocks, or artificial
  verbosity to hit a target.
- **Class names describe what the class does, in plain words.** No
  buzzwords, no vague suffixes like `Manager`, `Handler`, `Helper`,
  `Service`, `Engine` unless the word earns its place (e.g. `Repository`
  is acceptable because it names a specific, well-understood pattern —
  it's not filler). Prefer `WTB_Table_Storage` over
  `WTB_Table_Manager`; prefer `WTB_Csv_Export` and `WTB_Csv_Import` as
  two classes over one `WTB_Csv_Utility`.
- One class per file. File name matches class name
  (`class-table-storage.php` → `WTB_Table_Storage`).
- Comment *why*, not *what*. No comment on a line that reads itself.
- PHP 7.4-compatible syntax only — no `match`, no constructor property
  promotion, no enums, no readonly properties.

## File structure (target)
```
wp-table-builder/
├── wp-table-builder.php     # bootstrap only: constants, requires, hooks
├── uninstall.php             # DB cleanup — lives HERE, not at repo root
├── readme.txt
├── includes/
│   ├── class-activator.php
│   ├── class-sanitizer.php
│   ├── class-table-post-type.php
│   ├── class-table-storage.php   # all wtb_columns/wtb_rows DB access
│   ├── class-table-renderer.php  # markup generation only
│   ├── class-table-css.php       # per-table inline CSS generation
│   ├── class-shortcode.php
│   ├── class-form-shortcode.php
│   ├── class-block.php
│   ├── class-rest-routes.php     # route registration only
│   ├── class-rest-tables.php     # table CRUD endpoint handlers
│   ├── class-rest-submissions.php # public submit/data endpoint handlers
│   ├── class-csv-export.php
│   ├── class-csv-import.php
│   ├── class-elementor-widget.php
│   ├── class-elementor-dynamic-tag.php
│   ├── class-elementor-form-action.php
│   ├── class-elementor-form-hook.php
│   ├── class-admin-menu.php
│   ├── class-admin-table-list.php
│   ├── class-admin-table-editor.php
│   └── class-debug-log.php
├── assets/css/{admin,frontend}.css
└── assets/js/{admin-builder,frontend,block-editor}.js
```
This splits v1's `class-rest-controller.php` (one file doing routing +
table CRUD + submissions + CSV) and `class-render.php` (markup + CSS
generation together) into single-responsibility files that fit the
200–500 line budget without padding.

## Security baseline (carried over from v1, do not regress)
- All input through `WTB_Sanitizer` — no inline `sanitize_*` calls
  scattered elsewhere.
- All output escaped at render time (`esc_html`, `esc_attr`, `esc_url`,
  `wp_kses_post` for rich text).
- All DB queries via `$wpdb->prepare()` or `insert/update/delete` with
  explicit format arrays — including `LIMIT`/`OFFSET`, never
  string-interpolated even when the values are already `absint()`'d.
- All state-changing admin actions: `current_user_can('manage_options')`
  + nonce verification.
- CSV export neutralizes formula-injection characters (`=`, `+`, `-`,
  `@`, tab, CR) at the start of any cell, including header cells.
- Public endpoints (form submission) get per-IP transient rate limiting.
- No test/debug scripts anywhere in the plugin folder or repo root that
  could be servable if the repo root were ever deployed as webroot.

## Update mechanism
`plugin-update-checker`, configured to check GitHub Releases (not
WordPress.org). This requires no directory listing/submission — it's a
self-hosted check. See design.md "Update mechanism" for the full flow
and the two gotchas already hit in v1 (version must be bumped in three
places; the packaging script must not exclude `vendor/`).

## Environment (local dev)
- Docker Compose: WordPress + MariaDB + phpMyAdmin, plugin folder
  bind-mounted into the container.
- Container runs as `www-data` (UID 33); host files are owned by the
  host user. This mismatch is the root cause of every incident so far —
  see design.md "Known filesystem gotcha" for the full explanation and
  the standing mitigation.
- PHP CLI may not be available in the agent's shell — verify syntax by
  careful reading, note explicitly if `php -l` wasn't run.

## User context
Learning JS/TS, comfortable with it, but still building confidence in
backend/OOP concepts and async patterns. Prefer explicit code over
clever one-liners in PHP. Prefers being told the plan before large
code generation, not asked open-ended design questions — give a
concrete default and let them redirect, don't leave decisions open.