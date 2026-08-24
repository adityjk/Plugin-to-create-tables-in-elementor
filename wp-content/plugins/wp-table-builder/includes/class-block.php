<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gutenberg block "wtb/table". The block itself is dynamic: the editor only
 * stores tableId; all output is produced server-side by WTB_Render.
 */
class WTB_Block {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_block' ] );
    }

    public static function register_block() {
        if ( ! function_exists( 'register_block_type' ) ) return;

        wp_register_script(
            'wtb-block-editor',
            WTB_PLUGIN_URL . 'assets/js/block-editor.js',
            [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-api-fetch' ],
            WTB_VERSION,
            true
        );

        register_block_type( 'wtb/table', [
            'editor_script'   => 'wtb-block-editor',
            'attributes'      => [
                'tableId' => [ 'type' => 'number', 'default' => 0 ],
            ],
            'render_callback' => [ __CLASS__, 'render_block' ],
        ] );
    }

    public static function render_block( array $atts ): string {
        $table_id = absint( $atts['tableId'] ?? 0 );
        if ( ! $table_id ) return '';

        return WTB_Render::render_table( $table_id );
    }
}
