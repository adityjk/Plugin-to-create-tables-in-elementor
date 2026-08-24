<?php
/**
 * Admin menu entry point and per-screen asset loading.
 *
 * One top-level page (slug wtb-tables - referenced elsewhere by the
 * CSV import fallback URL, do not rename casually). The page body is
 * delegated: no query arg renders the all-tables list, table={id}
 * renders the single-table editor. Assets are enqueued only against
 * this page's exact hook suffix so other admin screens stay clean.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Admin_Menu {

	const SLUG = 'wtb-tables';

	/**
	 * Hook suffix returned by add_menu_page; identifies "our" screens
	 * during admin_enqueue_scripts regardless of menu position.
	 */
	private static $suffix = '';

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
	}

	public static function register_menu() {
		self::$suffix = add_menu_page(
			__( 'Tables', 'wp-table-builder' ),
			__( 'WP Tables', 'wp-table-builder' ),
			'manage_options',
			self::SLUG,
			[ __CLASS__, 'render' ],
			'dashicons-grid-view'
		);
	}

	/**
	 * Shared wrapper: everything the builder JavaScript needs before
	 * it knows which view it is on travels as data attributes, the
	 * same contract the frontend markup uses.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Not allowed.', 'wp-table-builder' ),
				403
			);
		}

		echo '<div class="wrap wtb-admin" data-rest="'
			. esc_url( rest_url( 'wtb/v1' ) )
			. '" data-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) )
			. '">';

		self::import_notice();

		$table_id = absint(
			isset( $_GET['table'] ) ? $_GET['table'] : 0
		);

		if ( $table_id && self::valid_table( $table_id ) ) {
			WTB_Admin_Table_Editor::render( $table_id );
		} else {
			WTB_Admin_Table_List::render();
		}

		echo '</div>';
	}

	public static function enqueue( $hook_suffix ) {
		if ( self::$suffix !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'wtb-admin' );
		wp_enqueue_script( 'wtb-admin-builder' );

		// The media picker only exists in the builder grid.
		if ( isset( $_GET['table'] ) && absint( $_GET['table'] ) ) {
			wp_enqueue_media();
		}
	}

	/**
	 * CSV import redirects back with row counts in the query string;
	 * report them once, wherever the admin landed.
	 */
	private static function import_notice() {
		if (
			! isset( $_GET['wtb_imported'] )
			&& ! isset( $_GET['wtb_skipped'] )
		) {
			return;
		}

		$imported = absint(
			isset( $_GET['wtb_imported'] ) ? $_GET['wtb_imported'] : 0
		);
		$skipped  = absint(
			isset( $_GET['wtb_skipped'] ) ? $_GET['wtb_skipped'] : 0
		);

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: rows imported, 2: rows skipped. */
					__(
						'Import finished: %1$d rows added, %2$d skipped.',
						'wp-table-builder'
					),
					$imported,
					$skipped
				)
			)
		);
	}

	private static function valid_table( $table_id ) {
		$post = get_post( $table_id );

		return (bool) (
			$post
			&& WTB_Table_Post_Type::POST_TYPE === $post->post_type
		);
	}
}
