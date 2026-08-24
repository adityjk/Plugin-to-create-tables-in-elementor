=== WP Table Builder ===
Contributors: adityjk
Tags: table, elementor, csv, data-table, gutenberg
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build data tables visually and embed them anywhere: shortcode,
Gutenberg block, or Elementor widget.

== Description ==

WP Table Builder adds a visual table builder to your admin and a
fast, searchable table to your pages.

* Columns of six data types: text, number, date, image, URL, and
  linked post fields.
* DataTables-powered frontend with search, column sorting, pagination,
  per-column select filters, and adjustable page length - each toggle
  configurable per table.
* Large tables switch to server-side processing automatically above a
  row threshold you choose, so pages stay light.
* Visitor submissions through [wtb_table_form] with optional approval
  queue, per-IP rate limiting, and nonce protection.
* CSV export of published rows (with spreadsheet formula-injection
  protection) and CSV import matched by column label.
* Elementor integration: a Data Table widget with style overrides, a
  "Table Cell" dynamic tag, and an Elementor Pro form action that
  stores submissions as rows.
* Duplicate and delete tables from the admin list; pending rows are
  approved one by one in the editor.

Tables render through one shared code path, so shortcode, block, and
widget output always match.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/ (or install the
   zip through Plugins > Add New > Upload).
2. Activate the plugin.
3. Open "WP Tables" in the admin menu and create a table.
4. Embed it with [wtb_table id="123"], the "WP Table" block, or the
   Elementor "Data Table" widget.

== Frequently Asked Questions ==

= When does a table become server-side? =

When its published row count exceeds the "server-side threshold" in
the table settings. Below it, all rows render into the page for
instant sorting; above it, rows load over AJAX as visitors page
through.

= Where do visitor submissions go? =

They land as pending rows in the same table (if approval is enabled)
and appear in the editor for review. Submissions are rate-limited per
IP address per hour.

= Can I import a spreadsheet? =

Yes - export first to get a template, then re-import files whose
header row matches your column labels exactly.

== Bundled Libraries ==

* DataTables (assets/vendor/) - MIT license.
* plugin-update-checker (vendor/) - MIT license, used for update
  notifications against GitHub Releases.

== Changelog ==

= 1.0.0 =
Initial release.
