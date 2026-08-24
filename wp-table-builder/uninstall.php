<?php
/**
 * Uninstall cleanup. Lives inside the plugin folder so WordPress can
 * find it, and deliberately requires three local class files instead
 * of restating their constants: table names, post type slug, and log
 * option key each keep exactly one source of truth. All three are
 * inert declarations - nothing hooks in at load time.
 *
 * @package WTB
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

require_once __DIR__ . '/includes/class-table-storage.php';
require_once __DIR__ . '/includes/class-table-post-type.php';
require_once __DIR__ . '/includes/class-debug-log.php';

/*
 * Posts go first: forced deletion carries the _wtb_settings meta
 * with them. Trash and auto-draft are included because "any"
 * excludes them and a half-created draft would otherwise leak its
 * custom-table rows into the drops below with no post to own them.
 */
$wtb_ids = get_posts(
	[
		'post_type'        => WTB_Table_Post_Type::POST_TYPE,
		'post_status'      => [ 'any', 'trash', 'auto-draft' ],
		'numberposts'      => -1,
		'fields'           => 'ids',
		'suppress_filters' => true,
	]
);

foreach ( $wtb_ids as $wtb_id ) {
	wp_delete_post( absint( $wtb_id ), true );
}

$wtb_names = WTB_Table_Storage::table_names();

$wpdb->query( "DROP TABLE IF EXISTS {$wtb_names['columns']}" ); // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$wtb_names['rows']}" ); // phpcs:ignore

delete_option( 'wtb_db_version' );
WTB_Debug_Log::clear();

/*
 * Rate-limit counters self-expire within an hour; this merely tidies
 * the options table immediately. Sites with an external object cache
 * keep their copies until expiry, which the limit window makes
 * harmless.
 */
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\\_transient\\_wtb\\_rl\\_%'
	 OR option_name LIKE '\\_transient\\_timeout\\_wtb\\_rl\\_%'"
);
