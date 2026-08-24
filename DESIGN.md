# WP Table Builder v2 — Design

## Scope
Same functional scope as the v1 build: visual table builder (CPT +
custom DB tables), admin editor, Shortcode/Gutenberg Block/Elementor
Widget embeds, DataTables.js frontend (search/sort/filter/pagination,
client- and server-side modes), CSV import/export, public form
submission with optional approval, Elementor dynamic tag + form action
integration, GitHub-based self-update.

## Data model
Unchanged from v1 — this part was never the source of bugs.

- **CPT `wtb_table`**: one post per table. `post_title` = table name.
  Post meta `_wtb_settings` = JSON blob of display/behavior settings
  (colors, padding, border, search/sort toggles, data source, etc),
  normalized through `WTB_Sanitizer::table_settings()` on every write so
  anything read back is safe to use without further checks.
- **`{prefix}wtb_columns`**: `id`, `table_id`, `label`, `data_type`,
  `settings` (JSON: post_field, image_size, filter_type, is_unique),
  `sort_order`.
- **`{prefix}wtb_rows`**: `id`, `table_id`, `cells_data` (JSON object
  keyed by column id), `sort_order`, `status` (`published`/`pending`).
  One row per record; cell values live in a JSON blob rather than a
  wide/sparse column set, avoiding schema migrations when column types
  change.

## Class responsibilities

### Storage layer
- **`WTB_Table_Storage`** — the only class that runs SQL against
  `wtb_columns`/`wtb_rows`. Exposes `get_columns()`, `get_rows()`,
  `save_structure()` (batch save from the editor, handles temp-key
  remapping for new columns), `duplicate()`, counts, and raw-cell access
  for CSV export. REST handlers and admin handlers both call into this
  — persistence logic exists exactly once.
- **`WTB_Sanitizer`** — the only class that sanitizes input. Every
  other class receiving user input calls into this rather than calling
  `sanitize_text_field` etc. directly.

### Rendering layer
- **`WTB_Table_Renderer`** — builds the `<table>` markup, cell HTML per
  data type, and the visitor-facing submission form markup. Takes
  already-sanitized data; does not touch the database.
- **`WTB_Table_Css`** — turns a settings array into a `<style>` block
  scoped to one table. Kept separate from the renderer because CSS
  generation has zero markup logic and was previously tangled into one
  600+ line file.

### Embeds (all three delegate to the renderer, never re-implement it)
- **`WTB_Shortcode`** — `[wtb_table id]`.
- **`WTB_Form_Shortcode`** — `[wtb_table_form id]`.
- **`WTB_Block`** — dynamic Gutenberg block `wtb/table`; editor stores
  only `tableId`, PHP renders server-side.

### REST layer
- **`WTB_Rest_Routes`** — route registration only (paths, methods,
  permission callbacks). No business logic.
- **`WTB_Rest_Tables`** — handlers for table CRUD + save + duplicate
  (admin-only routes).
- **`WTB_Rest_Submissions`** — handlers for the public routes: DataTables
  server-side `/data`, visitor `/submit`, `/row-count` polling. Kept
  separate from `WTB_Rest_Tables` because the security posture is
  different (public vs `manage_options`-gated) and mixing them in one
  file was a review hazard in v1.

### CSV
- **`WTB_Csv_Export`** — streams a table to CSV, neutralizing
  formula-injection characters on every cell including headers.
- **`WTB_Csv_Import`** — reads an uploaded CSV, maps headers to columns
  by label, sanitizes each cell by its column's data type.
  (Split from one v1 `WTB_CSV` god-class into two single-direction
  classes — export and import have no shared logic beyond both calling
  `WTB_Table_Storage`.)

### Elementor integration
- **`WTB_Elementor_Widget`** — content/style/pagination controls,
  delegates rendering to `WTB_Table_Renderer` with control overrides.
- **`WTB_Elementor_Dynamic_Tag`** — "Table Cell" dynamic tag: table +
  column + row number → plain-text cell value.
- **`WTB_Elementor_Form_Action`** — Elementor Pro form action, reuses
  `WTB_Rest_Submissions`' field-matching logic and
  `WTB_Table_Storage::insert_row()`.
- **`WTB_Elementor_Form_Hook`** — registers the action on
  `elementor_pro/forms/actions/register`. Split from the action class
  itself because the registration hook must only fire when Elementor
  Pro's classes exist, and mixing detection logic with the action's
  business logic was the source of the v1 "silent registration failure"
  bug (the require ran before Elementor had loaded, and PHP's
  `require_once` caching meant the class was never declared on
  subsequent loads).

### Admin UI
- **`WTB_Admin_Menu`** — registers the admin menu page(s) only.
- **`WTB_Admin_Table_List`** — renders the "all tables" list view.
- **`WTB_Admin_Table_Editor`** — renders the single-table editor view
  (the grid, settings panel skeleton, CSV import/export buttons, debug
  log viewer). JS (`admin-builder.js`) does the interactivity; this
  class only outputs the initial HTML shell and localizes REST config.
- **`WTB_Debug_Log`** — lightweight option-based logger for diagnosing
  form submissions, capped entry count.

## Known filesystem gotcha (read before touching install/update flows)

**The mechanism**: local dev runs WordPress in Docker with the plugin
folder bind-mounted from the host. The container's PHP process runs as
`www-data` (UID 33); files created on the host belong to the host user
(a different UID). When WordPress's plugin installer/updater runs, it:
1. Deletes the existing plugin directory's *contents*.
2. Attempts to remove the directory *itself*.
3. Extracts the new zip into a fresh directory.

If `www-data` has write access to the files inside the plugin directory
but not to the *parent* directory (`wp-content/plugins/`), step 1
succeeds, step 2 fails ("directory already exists and could not be
removed"), and step 3 never runs — leaving the plugin directory emptied
but not replaced. This happened twice in v1 and both times the loss
included uncommitted work.

**Standing mitigation for v2**: before testing ANY install/update flow
through wp-admin, both of these must be true, and the agent should
remind the user to verify them if it's about to suggest testing an
update:
```
sudo chmod -R a+w wp-content/plugins           # parent dir
sudo chmod -R a+w wp-content/plugins/wp-table-builder  # plugin dir
```
And the working tree must be committed first — see AGENTS.md rule 2.

## Update mechanism

`plugin-update-checker` (vendor/plugin-update-checker/), configured
against the project's GitHub repository, checks GitHub Releases instead
of WordPress.org — no plugin directory submission/review needed.

Flow:
1. Bump version in three places: plugin header `Version:`,
   `WTB_VERSION` constant, and the packaging script's version string.
2. Run the packaging script → produces `dist/wp-table-builder-X.Y.Z.zip`.
3. Commit and push source changes (the zip itself is never committed —
   `dist/` is gitignored).
4. Create a GitHub Release with a tag matching the version (`vX.Y.Z`),
   and manually attach the zip from step 2 as a release asset.
5. WordPress (via `plugin-update-checker`, triggered automatically on
   admin page loads / WP-Cron) queries the GitHub Releases API,
   compares the latest tag to the installed `WTB_VERSION`, and shows
   the standard "Update available" notice with a link to the attached
   zip when they differ.

The packaging script must never exclude `vendor/` — it is a runtime
dependency (`plugin-update-checker` itself lives there and is
`require`d directly), not a dev-only dependency. This was a real bug in
v1: an overly broad exclude pattern (`vendor`) matched
`vendor/plugin-update-checker/` and shipped a plugin that fataled on
its own updater.

## Testing checklist (manual, before every commit that touches
functionality)
1. Create/edit/save a table with 2–3 columns of different data types.
2. Frontend render via shortcode: search, sort, pagination.
3. Server-side DataTables path (if row count exceeds the threshold).
4. Form submission (`[wtb_table_form]`) + approval flow if enabled.
5. CSV export (check formula-injection neutralization) and import.
6. Elementor widget + dynamic tag, if Elementor is installed.
7. Elementor form action, if Elementor Pro is installed.