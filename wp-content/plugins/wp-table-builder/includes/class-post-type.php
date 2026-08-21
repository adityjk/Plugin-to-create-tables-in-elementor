<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Internal non-public CPT: stores each table's title + _wtb_settings meta.
 * Column/row data lives in the custom tables (see WTB_Table_Repository).
 */
class WTB_Post_Type {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register' ] );
    }

    public static function register() {
        register_post_type( 'wtb_table', [
            'labels'       => [
                'name'          => __( 'Tables', 'wp-table-builder' ),
                'singular_name' => __( 'Table',  'wp-table-builder' ),
            ],
            'public'       => false,
            'show_ui'      => false,
            'show_in_rest' => false,
            'supports'     => [ 'title' ],
            'has_archive'  => false,
        ] );
    }
}
