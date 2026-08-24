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
| 6 | Elementor | `includes/class-elementor-form-action.php` | — | next |
| 6 | Elementor | `includes/class-elementor-form-hook.php` | — | pending |
| 7 | admin | `includes/class-admin-menu.php` | — | pending |
| 7 | admin | `class-admin-table-list.php` | — | pending |
| 7 | admin | `class-admin-table-editor.php` | — | pending |
| 7 | admin | `class-debug-log.php` | — | pending |
| 8 | bootstrap | `wp-table-builder.php` | — | pending |
| 8 | bootstrap | `uninstall.php` | — | pending |
| 8 | bootstrap | `readme.txt` | — | pending |
| 9 | assets | `assets/css/admin.css`, `frontend.css` | — | pending |
| 9 | assets | `assets/js/admin-builder.js`, `frontend.js`, `block-editor.js` | — | pending |

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

## Open forward references (resolve by step 8/9)
- `wtb-frontend` / `wtb-block-editor` script/style handles: enqueued by
  embeds, registered later in bootstrap.
- `WTB_Debug_Log::add()` called in `WTB_Rest_Submissions::submit()`;
  class arrives in step 7.
- Bootstrap must gate Elementor requires on `elementor/loaded`
  (widget/tag/action/hook extend Elementor classes) and register the
  asset handles above.

## Remaining work, in detail

### Step 6 — Elementor (in progress)
- **`class-elementor-dynamic-tag.php`** (`WTB_Elementor_Dynamic_Tag`):
  extends `\Elementor\Core\DynamicTags\Tag`. Controls: table select,
  column select (populated from the chosen table's columns), row
  number. `get_value()` returns the plain-text cell value for
  table+column+row; unknown ids return ''. Registered on
  `elementor/dynamic_tags/register_tags`; file required only when
  Elementor is active. Categories: text/number/date/url cells only
  (image/post cells are IDs, not display text).
- **`class-elementor-form-action.php`** (`WTB_Elementor_Form_Action`):
  extends `\ElementorPro\Modules\Forms\Classes\Action_Base`.
  `register_settings_controls()` adds: target table select + optional
  field→column map. `run()` builds a cells array from submitted form
  fields and calls `WTB_Table_Storage::insert_row()` with pending/
  published per the table's require_approval setting — same path as
  REST submit, no duplicate persistence logic.
- **`class-elementor-form-hook.php`** (`WTB_Elementor_Form_Hook`):
  registers the action class on `elementor_pro/forms/actions/register`
  and self-guards with `class_exists( '\ElementorPro\Plugin' )` so
  Pro's absence is silent. This is the fix for v1's "silent
  registration failure" bug — detection lives here, business logic in
  the action class.

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

### Release prerequisites (after step 9)
- Acquire `vendor/plugin-update-checker/` (runtime dependency — the
  packaging script must never exclude it).
- Packaging script producing `dist/wp-table-builder-X.Y.Z.zip`
  (dist/ gitignored, zip never committed).
- Version bump checklist: plugin header, `WTB_VERSION`, packaging
  script — three places.
- Run DESIGN.md manual testing checklist before first release.

### Open decision (default set, redirect if you disagree)
- DataTables source: bundle `jquery.dataTables.min.js/.css` under
  `assets/vendor/` (no CDN dependency, works offline) — default is
  bundling since there is no build step.
