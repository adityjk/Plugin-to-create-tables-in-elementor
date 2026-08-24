<?php
/**
 * Streams one table's published rows as a CSV download.
 *
 * Runs through admin-post.php (not REST) because it is a browser
 * download: capability + nonce are checked here, then rows stream
 * straight to php://output in batches so memory stays flat.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Csv_Export {

	const BATCH_SIZE = 500;

	public static function init() {
		add_action( 'admin_post_wtb_export_csv', [ __CLASS__, 'handle' ] );
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Not allowed.', 'wp-table-builder' ),
				403
			);
		}

		$table_id = absint( isset( $_GET['table'] ) ? $_GET['table'] : 0 );
		check_admin_referer( 'wtb_export_' . $table_id );

		$post = get_post( $table_id );
		if (
			! $post
			|| WTB_Table_Post_Type::POST_TYPE !== $post->post_type
		) {
			wp_die(
				esc_html__( 'Table not found.', 'wp-table-builder' ),
				404
			);
		}

		$columns = WTB_Table_Storage::get_columns( $table_id );
		if ( ! $columns ) {
			wp_die(
				esc_html__( 'This table has no columns.', 'wp-table-builder' ),
				400
			);
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header(
			'Content-Disposition: attachment; filename=wtb-table-'
			. $table_id . '.csv'
		);

		$stream = fopen( 'php://output', 'w' );
		// UTF-8 BOM so spreadsheet apps detect the encoding instead of
		// assuming a legacy codepage.
		fwrite( $stream, "\xEF\xBB\xBF" );

		fputcsv(
			$stream,
			array_map(
				[ __CLASS__, 'safe_cell' ],
				wp_list_pluck( $columns, 'label' )
			)
		);

		$offset = 0;
		while ( true ) {
			$rows = WTB_Table_Storage::get_rows(
				$table_id,
				[
					'status' => 'published',
					'limit'  => self::BATCH_SIZE,
					'offset' => $offset,
				]
			);

			foreach ( $rows as $row ) {
				fputcsv( $stream, self::row_values( $row, $columns ) );
			}

			if ( count( $rows ) < self::BATCH_SIZE ) {
				break;
			}
			$offset += self::BATCH_SIZE;
		}

		fclose( $stream );
		exit;
	}

	private static function row_values( $row, $columns ) {
		$values = [];
		foreach ( $columns as $column ) {
			$values[] = isset( $row['cells_data'][ $column['id'] ] )
				? self::safe_cell( $row['cells_data'][ $column['id'] ] )
				: '';
		}
		return $values;
	}

	/**
	 * Prefix anything a spreadsheet could interpret as a formula.
	 * Pure numbers are exempt: "-5" is data, not an attack, and
	 * quoting it would corrupt every negative number on re-import.
	 */
	public static function safe_cell( $value ) {
		$value = (string) $value;
		if ( '' === $value || is_numeric( $value ) ) {
			return $value;
		}

		$first = substr( $value, 0, 1 );
		if (
			in_array( $first, [ '=', '+', '-', '@' ], true )
			|| "\t" === $first
			|| "\r" === $first
		) {
			return "'" . $value;
		}

		return $value;
	}
}
