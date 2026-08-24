<?php
/**
 * Imports an uploaded CSV into one table.
 *
 * Headers map to existing columns by exact label match; unknown
 * headers are ignored so extra spreadsheet columns do not block an
 * import. Cell sanitization happens downstream in
 * WTB_Table_Storage::insert_row(), which routes every value through
 * WTB_Sanitizer by column type — this class only reverses the
 * formula-injection quoting WTB_Csv_Export applied.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Csv_Import {

	public static function init() {
		add_action( 'admin_post_wtb_import_csv', [ __CLASS__, 'handle' ] );
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Not allowed.', 'wp-table-builder' ),
				403
			);
		}

		$table_id = absint( isset( $_POST['table'] ) ? $_POST['table'] : 0 );
		check_admin_referer( 'wtb_import_' . $table_id );

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

		$file = self::uploaded_file();

		$columns = WTB_Table_Storage::get_columns( $table_id );
		if ( ! $columns ) {
			wp_die(
				esc_html__( 'This table has no columns.', 'wp-table-builder' ),
				400
			);
		}

		list( $imported, $skipped ) = self::parse_and_insert(
			$file['tmp_name'],
			$table_id,
			$columns
		);

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=wtb-tables' );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'wtb_imported' => $imported,
					'wtb_skipped'  => $skipped,
				],
				$redirect
			)
		);
		exit;
	}

	private static function uploaded_file() {
		if (
			empty( $_FILES['wtb_csv'] )
			|| ! is_array( $_FILES['wtb_csv'] )
		) {
			wp_die(
				esc_html__( 'No file uploaded.', 'wp-table-builder' ),
				400
			);
		}

		$file = $_FILES['wtb_csv'];

		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			wp_die(
				esc_html__( 'The upload failed. Try again.', 'wp-table-builder' ),
				400
			);
		}
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			wp_die(
				esc_html__( 'Invalid upload.', 'wp-table-builder' ),
				400
			);
		}

		return $file;
	}

	private static function parse_and_insert( $path, $table_id, $columns ) {
		$handle = fopen( $path, 'r' );
		if ( ! $handle ) {
			wp_die(
				esc_html__( 'Could not read the file.', 'wp-table-builder' ),
				400
			);
		}

		$by_label = [];
		foreach ( $columns as $column ) {
			if ( '' !== $column['label'] ) {
				$by_label[ $column['label'] ] = $column;
			}
		}

		$header = fgetcsv( $handle, 0, ',', '"' );
		if ( ! is_array( $header ) ) {
			wp_die(
				esc_html__( 'The file is empty.', 'wp-table-builder' ),
				400
			);
		}

		if ( isset( $header[0] ) ) {
			$header[0] = preg_replace(
				'/^\xEF\xBB\xBF/',
				'',
				(string) $header[0]
			);
		}

		// CSV position -> column definition, for matched labels only.
		$map = [];
		foreach ( $header as $position => $label ) {
			$label = trim( (string) $label );
			if ( isset( $by_label[ $label ] ) ) {
				$map[ $position ] = $by_label[ $label ];
			}
		}

		if ( ! $map ) {
			fclose( $handle );
			wp_die(
				esc_html__(
					'No header matched this table\'s column labels.',
					'wp-table-builder'
				),
				400
			);
		}

		$imported = 0;
		$skipped  = 0;

		while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"' ) ) ) {
			if ( self::is_blank_row( $row ) ) {
				continue;
			}

			$cells = [];
			foreach ( $map as $position => $column ) {
				$raw = isset( $row[ $position ] ) ? $row[ $position ] : '';
				$cells[ $column['id'] ] = self::from_export( $raw );
			}

			$row_id = WTB_Table_Storage::insert_row(
				$table_id,
				$cells,
				'published'
			);

			if ( $row_id ) {
				$imported++;
			} else {
				$skipped++;
			}
		}

		fclose( $handle );

		return [ $imported, $skipped ];
	}

	/**
	 * Inverse of WTB_Csv_Export::safe_cell(): drop the quote it added
	 * in front of formula characters so exported files round-trip.
	 */
	private static function from_export( $value ) {
		$value  = (string) $value;
		$first  = substr( $value, 0, 1 );
		$second = substr( $value, 1, 1 );

		if (
			"'" === $first
			&& in_array( $second, [ '=', '+', '-', '@', "\t", "\r" ], true )
		) {
			return substr( $value, 1 );
		}

		return $value;
	}

	private static function is_blank_row( $row ) {
		foreach ( (array) $row as $value ) {
			if ( null !== $value && '' !== $value ) {
				return false;
			}
		}
		return true;
	}
}
