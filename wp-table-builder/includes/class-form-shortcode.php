<?php
/**
 * [wtb_table_form id="123"] embed.
 *
 * Renders the visitor submission form for one table via
 * WTB_Table_Renderer::render_form(); frontend.js intercepts submits
 * and posts them to the public REST endpoint.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Form_Shortcode {

	const TAG = 'wtb_table_form';

	public static function init() {
		add_shortcode( self::TAG, [ __CLASS__, 'handle' ] );
	}

	public static function handle( $atts ) {
		$atts = shortcode_atts(
			[ 'id' => 0 ],
			$atts,
			self::TAG
		);

		return self::render( absint( $atts['id'] ) );
	}

	/**
	 * Empty string unless the table exists, has columns, and has its
	 * form toggle enabled in settings.
	 */
	public static function render( $table_id ) {
		$table_id = absint( $table_id );
		if ( ! $table_id ) {
			return '';
		}

		$post = get_post( $table_id );
		if ( ! $post || WTB_Table_Post_Type::POST_TYPE !== $post->post_type ) {
			return '';
		}

		$settings = WTB_Table_Post_Type::get_settings( $table_id );
		if ( empty( $settings['form']['enabled'] ) ) {
			return '';
		}

		$columns = WTB_Table_Storage::get_columns( $table_id );
		if ( ! $columns ) {
			return '';
		}

		wp_enqueue_style( 'wtb-frontend' );
		wp_enqueue_script( 'wtb-frontend' );

		return WTB_Table_Renderer::render_form(
			$columns,
			[ 'table_id' => $table_id ]
		);
	}
}
