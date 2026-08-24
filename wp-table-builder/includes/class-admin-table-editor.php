<?php
/**
 * Single-table editor view: an HTML shell only.
 *
 * Every interactive piece - grid, settings inputs, save - is built
 * and driven by assets/js/admin-builder.js. This class prints the
 * containers, the pieces that must be server-authoritative (CSV
 * nonces, the debug log), and one JSON config blob carrying the
 * whitelists the JS needs to render editors per data type. Values in
 * the blob come from WTB_Sanitizer constants so JS can never offer a
 * type storage would reject.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Admin_Table_Editor {

	public static function render( $table_id ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Not allowed.', 'wp-table-builder' ),
				403
			);
		}

		$table_id = absint( $table_id );

		echo '<a href="' . esc_url( self::list_url() ) . '">&larr; '
			. esc_html__( 'All tables', 'wp-table-builder' ) . '</a>';

		echo '<h1 class="wp-heading-inline">'
			. esc_html( self::table_title( $table_id ) ) . '</h1>';

		echo '<button type="button" class="page-title-action"'
			. ' data-wtb-action="save">'
			. esc_html__( 'Save', 'wp-table-builder' ) . '</button>'
			. ' <span id="wtb-save-status" aria-live="polite"></span>';

		echo '<hr class="wp-header-end">';

		echo '<div id="wtb-editor" data-config="' . esc_attr(
			wp_json_encode(
				[
					'tableId'     => $table_id,
					'dataTypes'   => WTB_Sanitizer::DATA_TYPES,
					'imageSizes'  => WTB_Sanitizer::IMAGE_SIZES,
					'postFields'  => WTB_Sanitizer::POST_FIELDS,
					'filterTypes' => WTB_Sanitizer::FILTER_TYPES,
				]
			)
		) . '">';

		echo '<div class="wtb-grid-wrap"><div id="wtb-grid"></div></div>';
		echo '<div class="wtb-panel"><h2>'
			. esc_html__( 'Settings', 'wp-table-builder' )
			. '</h2><div id="wtb-settings"></div></div>';

		self::csv_box( $table_id );
		self::log_viewer();

		echo '</div>';
	}

	private static function table_title( $table_id ) {
		$title = get_the_title( $table_id );

		return '' !== $title
			? $title
			: __( '(no title)', 'wp-table-builder' );
	}

	private static function list_url() {
		return admin_url( 'admin.php?page=' . WTB_Admin_Menu::SLUG );
	}

	/**
	 * CSV moves through admin-post.php, not REST, so its nonces are
	 * minted here rather than trusted to JavaScript state.
	 */
	private static function csv_box( $table_id ) {
		$export = wp_nonce_url(
			admin_url(
				'admin-post.php?action=wtb_export_csv&table='
				. $table_id
			),
			'wtb_export_' . $table_id
		);

		echo '<div class="wtb-csv-box"><h2>'
			. esc_html__( 'CSV', 'wp-table-builder' ) . '</h2>';

		echo '<p><a class="button" href="' . esc_url( $export ) . '">'
			. esc_html__(
				'Export published rows',
				'wp-table-builder'
			) . '</a></p>';

		echo '<form method="post" enctype="multipart/form-data"'
			. ' action="' . esc_url( admin_url( 'admin-post.php' ) )
			. '">';
		echo '<input type="hidden" name="table" value="' . $table_id . '">';
		wp_nonce_field( 'wtb_import_' . $table_id );
		echo '<p><input type="file" name="wtb_csv" accept=".csv"> '
			. '<button type="submit" class="button">'
			. esc_html__( 'Import rows', 'wp-table-builder' )
			. '</button></p>';
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Read-only tail of the page; clearing is a nonce'd link handled
	 * by WTB_Debug_Log's admin-post route.
	 */
	private static function log_viewer() {
		$entries = WTB_Debug_Log::all();

		echo '<details class="wtb-log-viewer"><summary>'
			. esc_html__( 'Debug log', 'wp-table-builder' )
			. ' (' . count( $entries ) . ')</summary>';

		if ( ! $entries ) {
			echo '<p>' . esc_html__(
				'No entries yet.',
				'wp-table-builder'
			) . '</p></details>';
			return;
		}

		echo '<pre class="wtb-log-entries">';
		foreach ( $entries as $entry ) {
			$when    = isset( $entry['time'] ) ? absint( $entry['time'] ) : 0;
			$message = isset( $entry['message'] ) ? (string) $entry['message'] : '';

			echo esc_html(
				date_i18n( 'Y-m-d H:i:s', $when ) . '  ' . $message
			) . "\n";
		}
		echo '</pre>';

		echo '<p><a class="button-link" href="'
			. esc_url( WTB_Debug_Log::clear_url() ) . '"'
			. ' data-confirm="' . esc_attr__(
				'Clear the debug log?',
				'wp-table-builder'
			) . '">' . esc_html__( 'Clear log', 'wp-table-builder' )
			. '</a></p>';

		echo '</details>';
	}
}
