<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WTB_Shortcode {

    public static function init() {
        add_shortcode( 'wtb_table', [ __CLASS__, 'render' ] );
    }

    public static function render( array $atts ): string {
        $atts     = shortcode_atts( [ 'id' => 0 ], $atts, 'wtb_table' );
        $table_id = absint( $atts['id'] );

        if ( ! $table_id ) return '';

        return WTB_Render::render_table( $table_id );
    }
}
