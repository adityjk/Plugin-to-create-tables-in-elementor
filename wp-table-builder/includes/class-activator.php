<?php
/**
 * Activation tasks: create the plugin's custom tables.
 *
 * Runs on register_activation_hook (wired up in the bootstrap). Uses
 * WTB_Table_Storage::table_names() so the schema and every query
 * against these tables share one source of truth.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Activator {

	/**
	 * Bumped when a release changes the schema. Reactivation reruns
	 * dbDelta harmlessly, and future migration code can compare this
	 * against the stored wtb_db_version option.
	 */
	const DB_VERSION = '1.0';

	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$names   = WTB_Table_Storage::table_names();
		$charset = $wpdb->get_charset_collate();

		// dbDelta format rules apply: each field on its own line,
		// two spaces after PRIMARY KEY, KEY declarations named.
		$columns_sql = "CREATE TABLE {$names['columns']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			table_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			label TEXT NOT NULL,
			data_type VARCHAR(20) NOT NULL DEFAULT 'text',
			settings LONGTEXT NULL,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY table_sort (table_id,sort_order)
		) $charset;";

		// Rows default to pending so an unexpected insert path can
		// never publish content by accident.
		$rows_sql = "CREATE TABLE {$names['rows']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			table_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			cells_data LONGTEXT NULL,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			PRIMARY KEY  (id),
			KEY table_sort (table_id,sort_order),
			KEY table_status (table_id,status)
		) $charset;";

		dbDelta( [ $columns_sql, $rows_sql ] );

		update_option( 'wtb_db_version', self::DB_VERSION );
	}
}
