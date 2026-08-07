<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WTB_Shortcode {

    public static function init() {
        add_shortcode( 'wtb_table', [ __CLASS__, 'render' ] );
    }

    public static function render( $atts = [] ): string {
        $atts = shortcode_atts( [
            'id'          => 0,
            'source'      => 'static',
            'post_type'   => 'post',
            'taxonomy'    => 'category',
            'terms'       => '',
            'operator'    => 'IN',
            'limit'       => 10,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'show_filter' => 'no',
            'show_img'    => 'yes',
            'show_cats'   => 'yes',
            'show_excerpt'=> 'yes',
            'btn_text'    => 'Detail',
        ], (array) $atts, 'wtb_table' );

        $source = sanitize_text_field( $atts['source'] );
        if ( $source === 'dynamic' ) {
            $query_args = [
                'post_type'      => $atts['post_type'],
                'taxonomy'       => $atts['taxonomy'],
                'terms'          => $atts['terms'],
                'operator'       => $atts['operator'],
                'posts_per_page' => (int) $atts['limit'],
                'orderby'        => $atts['orderby'],
                'order'          => $atts['order'],
            ];

            $display_options = [
                'show_img'            => ( $atts['show_img'] === 'yes' ),
                'show_title'          => true,
                'show_cats'           => ( $atts['show_cats'] === 'yes' ),
                'show_excerpt'        => ( $atts['show_excerpt'] === 'yes' ),
                'show_date'           => true,
                'show_btn'            => true,
                'btn_text'            => $atts['btn_text'],
                'show_tax_filter_bar' => ( $atts['show_filter'] === 'yes' ),
                'tax_filter_label_all'=> 'Semua',
            ];

            return WTB_Render::render_dynamic_table( $query_args, $display_options );
        }

        $table_id = absint( $atts['id'] );
        if ( ! $table_id ) return '';

        return WTB_Render::render_table( $table_id );
    }
}
