# WP Table Builder v2 — Build Progress

Tracking doc only; disk state + git history are always authoritative.
Plugin code lives in `wp-table-builder/`, docs at repo root.

## Ground rules in force
- Commit after every file (user runs git). Max 80 cols, max 500 lines
  (target 200–350), no padding. PHP 7.4 syntax only (`php -l` runs on
  8.5, so 7.4 compat is verified by reading).
- Never suggest wp-admin Install/Update testing until working tree is
  committed AND both `chmod a+w` commands from DESIGN.md are applied.
- All input via `WTB_Sanitizer`; all output escaped at render; all SQL
  through `WTB_Table_Storage` with prepared LIMIT/OFFSET.

## Status

| # | Layer | File | Lines | State |
|---|-------|------|-------|-------|
| 0 | repo | `.gitignore` | 1 | done |
| 1 | storage | `includes/class-sanitizer.php` | 244 | done |
| 1 | storage | `includes/class-table-storage.php` | 462 | done |
| 1 | storage | `includes/class-activator.php` | 63 | done |
| 1 | storage | `includes/class-table-post-type.php` | 103 | done |
| 2 | render | `includes/class-table-renderer.php` | 213 | done |
| 2 | render | `includes/class-table-css.php` | 99 | done |
| 3 | embeds | `includes/class-shortcode.php` | 86 | done |
| 3 | embeds | `includes/class-form-shortcode.php` | 67 | done |
| 3 | embeds | `includes/class-block.php` | 61 | done |
| 4 | REST | `includes/class-rest-routes.php` | 97 | done |
| 4 | REST | `includes/class-rest-tables.php` | 242 | done |
| 4 | REST | `includes/class-rest-submissions.php` | 282 | done |
| 5 | CSV | `includes/class-csv-export.php` | 131 | done |
| 5 | CSV | `includes/class-csv-import.php` | 220 | done |
| 6 | Elementor | `includes/class-elementor-widget.php` | 241 | done |
| 6 | Elementor | `includes/class-elementor-dynamic-tag.php` | 225 | done |
| 6 | Elementor | `includes/class-elementor-form-action.php` | 276 | done |
| 6 | Elementor | `includes/class-elementor-form-hook.php` | 42 | done |
| 7 | admin | `includes/class-admin-menu.php` | 146 | done |
| 7 | admin | `includes/class-admin-table-list.php` | 116 | done |
| 7 | admin | `includes/class-admin-table-editor.php` | 156 | done |
| 7 | admin | `includes/class-debug-log.php` | 98 | done |
| 8 | bootstrap | `wp-table-builder.php` | 215 | done |
| 8 | bootstrap | `uninstall.php` | 60 | done |
| 8 | bootstrap | `readme.txt` | 77 | done |
| 9 | assets | `assets/css/frontend.css` | 105 | done |
| 9 | assets | `assets/css/admin.css` | 183 | done |
| 9 | assets | `assets/js/frontend.js` | 256 | done |
| 9 | assets | `assets/js/block-editor.js` | 147 | done |
| 9 | assets | `assets/js/admin-base.js` | 257 | done |
| 9 | assets | `assets/js/admin-cells.js` | 161 | done |
| 9 | assets | `assets/js/admin-settings-panel.js` | 184 | done |
| 9 | assets | `assets/js/admin-builder.js` | 445 | done |
| 10 | release | `vendor/plugin-update-checker/` | v4.13 | done |
| 10 | release | `release.sh` (repo root) | 63 | done |

Note: admin JS was planned as one `admin-builder.js` but the first
draft hit 885 lines (limit 500), so it was split into four layered
scripts sharing the `WTB_ADMIN` namespace; bootstrap registers all
four handles and only enqueues `wtb-admin-builder` (deps cascade).

## Decisions locked in so far
- Data types: `text, number, date, image, url, post`; image/post cells
  store IDs. Form exposes text/number/date/url only.
- Settings blob: colors(6) / layout(padding,border) / features(search,
  sorting, pagination, page_length=10 default, server_side_threshold)
  / data_source(manual|submissions) / form(enabled, require_approval,
  rate_limit=20/IP/hour).
- `row_status()` fails closed to `pending`; DB column default also
  `pending`.
- `save_structure()` = full-state sync (missing rows/columns deleted);
  REST `save_table` 400s if columns+rows not both present.
- Server-side mode when published count > threshold; sorting of JSON
  cells happens in PHP (portable across MySQL/MariaDB).
- CSV export via `admin_post_wtb_export_csv`, published rows only,
  formula-char quoting except pure numbers; import reverses quote.
- Admin menu slug: **`wtb-tables`** (referenced by CSV import fallback).
- Dynamic tag column picker lists ALL tables' text-family columns as
  `table_id:column_id` keys (Elementor panels render client-side, no
  server rebuild); `get_value()` fails closed on table/pick mismatch.
- Sanitizer gained a `FILTER_TYPES` const so the editor's JS gets the
  same filter whitelist PHP validates against; column() behavior is
  unchanged.
- Admin JS contract: shared REST root + nonce live on the `.wtb-admin`
  wrapper as data attrs; per-view config lives in data-config JSON;
  buttons carry data-wtb-action/data-id/data-confirm.
- Renderer forms carry `data-rest-base` (standalone form shortcode has
  no table wrap to read it from). frontend.js polls only when a config
  carries pollSeconds — no renderer emits it yet.
- Admin JS layers: `admin-base.js` owns the `.wtb-admin` contract and
  exposes `WTB_ADMIN` (request/coreUrl/errorText/editUrl, tag/button/
  select/checkbox/numberInput factories, moveItem, attachment/post
  lookups with caching, shared media frame); `admin-cells.js` exposes
  `WTB_CELLS` — pure value→widget factories per data type; `admin-
  settings-panel.js` exposes `WTB_SETTINGS_PANEL.render(host,
  settings)` bound by path to the sanitizer's settings shape;
  `admin-builder.js` owns state + views. New column ids are `tmpN`
  temp keys; save response replaces working state wholesale.
- `coreUrl()` handles both pretty (`/wp-json/wtb/v1`) and plain
  (`?rest_route=`) REST roots for the /wp/v2 media+post lookups.
- plugin-update-checker v4.13 vendored under `vendor/plugin-update-
  checker/` (pruned dev-only files: examples/, README.md, composer.json);
  v4 chosen deliberately to match the bootstrap's `Puc_v4_Factory`.
- `release.sh X.Y.Z`: refuses to build if the updater vendor file is
  missing (encodes the v1 "shipped without vendor/" bug as a gate),
  zips with `wp-table-builder/` as top-level dir, excludes only OS cruft.

## Open forward references (resolved)
- `wtb-frontend` / `wtb-block-editor` handles: registered in the
  bootstrap (`wtb-datatables` vendor pair + `wtb-admin` admin pair).
  Admin menu now enqueues by handle only.
- Elementor gating done: widget/tag require behind elementor/loaded
  (either load order); form-hook loads unconditionally and self-guards.
- Update checker wired to github.com/adityjk/Plugin-to-create-tables-
  in-elementor, branch v2-development, behind file_exists + class_exists.

## Remaining work, in detail

### Step 6 — Elementor (done)
All three classes written; specs above superseded by the code itself.
Dynamic-tag column picker and form-action override map notes are in
"Decisions locked in so far".

### Step 7 — Admin UI
- **`class-admin-menu.php`** (`WTB_Admin_Menu`): `add_menu_page` with
  slug **`wtb-tables`** (already referenced by CSV import fallback),
  `manage_options` capability. Renders list view by default; editor
  reached via `page=wtb-tables&table={id}` query arg. Enqueues admin
  assets only on our own screens.
- **`class-admin-table-list.php`** (`WTB_Admin_Table_List`): all-tables
  view — title, published/pending counts, updated date; create button,
  edit link, duplicate/delete actions via REST (X-WP-Nonce). Escapes
  everything at output.
- **`class-admin-table-editor.php`** (`WTB_Admin_Table_Editor`): HTML
  shell only (grid container, settings panel skeleton, CSV import/
  export buttons with their admin-post nonces, debug log viewer).
  Localizes config to JS: REST root, `wp_create_nonce('wp_rest')`,
  table id, data-type whitelist. All interactivity lives in
  admin-builder.js.
- **`class-debug-log.php`** (`WTB_Debug_Log`): option-based logger,
  capped entry count (~100), static `add( $message )`, fetch/clear for
  the viewer. Resolves the forward reference from REST submissions.

### Step 8 — Bootstrap (written last, verified against real files)
- **`wp-table-builder.php`**: plugin header (`Version:` — one of THREE
  places version lives), `WTB_VERSION` const, requires + `::init()`
  wiring for every class above, `register_activation_hook` →
  `WTB_Activator::activate`, asset registration:
  - register `wtb-frontend` style/script (+ DataTables library — see
    open decision below), `wtb-block-editor`, `wtb-admin-*` handles;
  - gate Elementor file requires on `did_action('elementor/loaded')`;
  - init plugin-update-checker against GitHub Releases (must not
    exclude `vendor/` when packaging).
- **`uninstall.php`** (inside plugin folder): guarded by
  `WP_UNINSTALL_PLUGIN`; deletes wtb_table posts + `_wtb_settings`
  meta, drops both custom tables via `WTB_Table_Storage::table_names()`,
  removes `wtb_db_version`, debug-log option, rate-limit transients.
- **`readme.txt`**: standard WordPress readme.

### Step 9 — Assets
- **`assets/css/frontend.css`**: structural table styles shared by all
  tables (border-collapse, wrapper, form field styling); per-table
  visual settings already come from WTB_Table_Css inline blocks.
- **`assets/js/frontend.js`**: reads `data-wtb-config` JSON; client-
  side mode = plain DataTables init honoring search/sort/pagination
  toggles + pageLength; server-side mode = ajax to `{restBase}/data/
  {id}` mapping draw/start/length/search/order; builds select filters
  for columns marked `data-filter="select"`; intercepts `.wtb-form`
  submits → POST `{restBase}/submit/{id}` with cells[] + nonce, shows
  pending-vs-published confirmation; optional row-count polling.
- **`assets/js/block-editor.js`**: registers `wtb/table` block
  (attributes: tableId only), inspector select populated from REST
  tables list, `save()` returns null (server-rendered).
- **`assets/css/admin.css`** + **`assets/js/admin-builder.js`**:
  builder grid — add/remove/reorder columns & rows, temp-key ids for
  new columns (storage remaps them), per-data-type cell editors
  (media picker for image, post search for post), settings panel
  binding, full-state save payload, approval status toggles, CSV
  buttons, log viewer.

### Release prerequisites
- ~~Acquire `vendor/plugin-update-checker/`~~ (done — v4.13, see decisions).
- ~~Packaging script producing `dist/wp-table-builder-X.Y.Z.zip`~~
  (done — `release.sh` at repo root; dist/ gitignored).
- Version bump checklist: plugin header, `WTB_VERSION`, packaging
  script — three places.
- Run DESIGN.md manual testing checklist before first release.

## Status summary
All build steps (0–10) are done. What remains is manual testing in the
Docker environment per DESIGN.md's checklist — and per AGENTS.md rule 2,
only after the working tree is committed AND both `chmod a+w` commands
from DESIGN.md are applied.

### Open decision (RESOLVED)
- DataTables 1.13.8 bundled under `assets/vendor/` (js+css, no image
  deps — 1.13.x draws sort icons in CSS). Matches the versions the
  bootstrap registers.
