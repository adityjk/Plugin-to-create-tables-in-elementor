<?php
/**
 * Dynamic Gutenberg block "wtb/table".
 *
 * The editor stores nothing but the picked table id (see
 * assets/js/block-editor.js); all markup is produced server-side by
 * reusing WTB_Shortcode::render(), so shortcode and block output can
 * never drift apart.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Block {

	const NAME = 'wtb/table';

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ] );
	}

	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			self::NAME,
			[
				'api_version'     => 2,
				'editor_script'   => 'wtb-block-editor',
				'attributes'      => [
					'tableId' => [
						'type'    => 'integer',
						'default' => 0,
					],
				],
				'render_callback' => [ __CLASS__, 'render' ],
			]
		);
	}

	/**
	 * Empty output when no table is picked yet; the editor simply
	 * shows nothing until a selection is made.
	 */
	public static function render( $attributes ) {
		$table_id = absint(
			isset( $attributes['tableId'] ) ? $attributes['tableId'] : 0
		);

		if ( ! $table_id ) {
			return '';
		}

		return WTB_Shortcode::render( $table_id );
	}
}
