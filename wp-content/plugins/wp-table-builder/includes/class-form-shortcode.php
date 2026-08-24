<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WTB_Form_Shortcode {

    public static function init() {
        add_shortcode( 'wtb_table_form', [ __CLASS__, 'render' ] );
    }

    public static function render( array $atts ): string {
        $table_id = absint( shortcode_atts( [ 'id' => 0 ], $atts, 'wtb_table_form' )['id'] );
        if ( ! $table_id ) return '';

        return WTB_Render::render_form( $table_id );
    }
}
