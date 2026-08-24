<?php
/**
 * Runs when the plugin is uninstalled via WordPress admin.
 * Removes all plugin data: custom tables, CPT posts, options.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wtb_columns" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wtb_rows" );

$post_ids = get_posts( [
    'post_type'      => 'wtb_table',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
] );

foreach ( $post_ids as $id ) {
    wp_delete_post( $id, true );
}

delete_option( 'wtb_db_version' );
delete_option( 'wtb_debug_logs' );
delete_option( 'wtb_debug_mode' );

// Per-user flash notices (transients expire on their own, but clean up anyway).
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wtb\_admin\_notice\_%'" );
