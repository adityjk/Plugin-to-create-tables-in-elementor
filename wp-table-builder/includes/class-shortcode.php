<?php
/**
 * [wtb_table id="123"] embed.
 *
 * Fetches sanitized data and delegates all markup and CSS generation
 * to WTB_Table_Renderer / WTB_Table_Css. Other embeds (block,
 * Elementor) reuse render() so there is exactly one assembly path.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Shortcode {

	const TAG = 'wtb_table';

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
	 * Complete HTML for one table, or empty string when the id does
	 * not point at a wtb_table post with at least one column.
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

		// Above the threshold the grid is populated over REST instead
		// of printing every row into the page.
		$threshold   = (int) $settings['features']['server_side_threshold'];
		$server_side = $threshold > 0
			&& WTB_Table_Storage::count_rows( $table_id, 'published' )
				> $threshold;

		$rows = $server_side
			? []
			: WTB_Table_Storage::get_rows(
				$table_id,
				[ 'status' => 'published' ]
			);

		$columns = WTB_Table_Storage::get_columns( $table_id );
		if ( ! $columns ) {
			return '';
		}

		wp_enqueue_style( 'wtb-frontend' );
		wp_enqueue_script( 'wtb-frontend' );

		$html  = WTB_Table_Css::render( $table_id, $settings );
		$html .= WTB_Table_Renderer::render_table(
			$columns,
			$rows,
			$settings,
			[
				'table_id'    => $table_id,
				'server_side' => $server_side,
			]
		);

		return $html;
	}
}
