<?php
/**
 * Registers the wtb_table post type and owns its _wtb_settings meta.
 *
 * Tables are internal records, not publicly routable content: the
 * frontend renders them through shortcodes/blocks/widgets, and the
 * admin uses custom screens, so every visibility flag stays off.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Table_Post_Type {

	const POST_TYPE    = 'wtb_table';
	const SETTINGS_KEY = '_wtb_settings';

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_post_type' ] );
		add_action( 'init', [ __CLASS__, 'register_meta' ] );
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'       => [
					'name'          => __( 'Tables', 'wp-table-builder' ),
					'singular_name' => __( 'Table', 'wp-table-builder' ),
				],
				'public'       => false,
				'show_ui'      => false,
				'show_in_menu' => false,
				'show_in_rest' => false,
				'supports'     => [ 'title' ],
				'has_archive'  => false,
				'rewrite'      => false,
			]
		);
	}

	public static function register_meta() {
		register_post_meta(
			self::POST_TYPE,
			self::SETTINGS_KEY,
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				// Defense in depth: saves normally go through
				// save_settings(), but any stray update_post_meta()
				// still cannot store un-normalized settings.
				'sanitize_callback' => [ __CLASS__, 'normalize_stored' ],
			]
		);
	}

	/**
	 * Fully-populated settings for one table. Reads are re-normalized
	 * so callers always receive every key even if the meta was edited
	 * directly in the database or predates a schema addition.
	 */
	public static function get_settings( $post_id ) {
		$stored  = get_post_meta( absint( $post_id ), self::SETTINGS_KEY, true );
		$decoded = json_decode( (string) $stored, true );

		return WTB_Sanitizer::table_settings(
			is_array( $decoded ) ? $decoded : []
		);
	}

	/**
	 * Normalize then persist the settings blob.
	 */
	public static function save_settings( $post_id, $settings ) {
		return update_post_meta(
			absint( $post_id ),
			self::SETTINGS_KEY,
			wp_json_encode( WTB_Sanitizer::table_settings( $settings ) )
		);
	}

	/**
	 * Meta-level sanitize callback: accepts whatever was handed to
	 * update_post_meta(), tolerating both arrays and encoded JSON.
	 */
	public static function normalize_stored( $value ) {
		if ( is_array( $value ) ) {
			return wp_json_encode( WTB_Sanitizer::table_settings( $value ) );
		}

		$decoded = json_decode( (string) $value, true );

		return wp_json_encode(
			WTB_Sanitizer::table_settings(
				is_array( $decoded ) ? $decoded : []
			)
		);
	}
}
