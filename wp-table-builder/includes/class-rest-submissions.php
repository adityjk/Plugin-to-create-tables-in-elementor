<?php
/**
 * Public REST handlers: server-side DataTables data, visitor form
 * submissions, row-count polling.
 *
 * These routes are open by design, so the controls live here: nonce
 * verification and per-IP transient rate limiting on submit, and
 * only published rows ever leave through /data.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Rest_Submissions {

	/**
	 * Hard cap on page size so a hand-crafted request cannot ask for
	 * the whole table in one response.
	 */
	const MAX_PAGE_LENGTH = 1000;

	public static function data( $request ) {
		$post = self::table_post( $request['id'] );
		if ( ! $post ) {
			return self::not_found();
		}

		$table_id = (int) $post->ID;
		$columns  = WTB_Table_Storage::get_columns( $table_id );

		$search = self::search_term( $request->get_param( 'search' ) );
		$args   = [ 'status' => 'published' ];
		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		$rows = WTB_Table_Storage::get_rows( $table_id, $args );

		self::sort_rows( $rows, $columns, $request );

		$length = $request->get_param( 'length' );
		if ( '-1' === (string) $length ) {
			$page_length = null;
		} else {
			$page_length = min(
				self::MAX_PAGE_LENGTH,
				max( 1, absint( $length ) )
			);
		}

		$page = null === $page_length
			? $rows
			: array_slice( $rows, absint( $request->get_param( 'start' ) ), $page_length );

		$data = [];
		foreach ( $page as $row ) {
			$cells = [];
			foreach ( $columns as $column ) {
				$value   = isset( $row['cells_data'][ $column['id'] ] )
					? $row['cells_data'][ $column['id'] ]
					: '';
				$cells[] = WTB_Table_Renderer::cell( $value, $column );
			}
			$data[] = $cells;
		}

		return rest_ensure_response(
			[
				'draw'            => absint( $request->get_param( 'draw' ) ),
				'recordsTotal'    => WTB_Table_Storage::count_rows(
					$table_id,
					'published'
				),
				'recordsFiltered' => WTB_Table_Storage::count_rows(
					$table_id,
					'published',
					$search
				),
				'data'            => $data,
			]
		);
	}

	public static function submit( $request ) {
		$post     = self::table_post( $request['id'] );
		$settings = $post
			? WTB_Table_Post_Type::get_settings( $post->ID )
			: [];

		if (
			! $post
			|| empty( $settings['form']['enabled'] )
		) {
			return new WP_Error(
				'wtb_form_closed',
				__( 'This form is not accepting submissions.', 'wp-table-builder' ),
				[ 'status' => 404 ]
			);
		}

		$nonce = $request->get_param( 'wtb_form_nonce' );
		if ( ! wp_verify_nonce( (string) $nonce, 'wtb_form_submit' ) ) {
			return new WP_Error(
				'wtb_bad_nonce',
				__( 'Session expired. Reload the page.', 'wp-table-builder' ),
				[ 'status' => 403 ]
			);
		}

		if ( self::rate_limited( $settings ) ) {
			return new WP_Error(
				'wtb_rate_limited',
				__(
					'Too many submissions. Try again later.',
					'wp-table-builder'
				),
				[ 'status' => 429 ]
			);
		}

		$cells = $request->get_param( 'cells' );
		if ( ! is_array( $cells ) ) {
			return new WP_Error(
				'wtb_bad_cells',
				__( 'Missing form data.', 'wp-table-builder' ),
				[ 'status' => 400 ]
			);
		}

		$status = ! empty( $settings['form']['require_approval'] )
			? 'pending'
			: 'published';

		$row_id = WTB_Table_Storage::insert_row(
			$post->ID,
			$cells,
			$status
		);
		if ( ! $row_id ) {
			return new WP_Error(
				'wtb_save_failed',
				__( 'Could not save the submission.', 'wp-table-builder' ),
				[ 'status' => 500 ]
			);
		}

		WTB_Debug_Log::add(
			sprintf(
				'submit table=%d row=%d status=%s ip=%s',
				(int) $post->ID,
				$row_id,
				$status,
				self::client_ip()
			)
		);

		return rest_ensure_response(
			[ 'saved' => true, 'status' => $status ]
		);
	}

	public static function row_count( $request ) {
		$post = self::table_post( $request['id'] );
		if ( ! $post ) {
			return self::not_found();
		}

		return rest_ensure_response(
			[
				'count' => WTB_Table_Storage::count_rows(
					$post->ID,
					'published'
				),
			]
		);
	}

	/**
	 * Sort decoded rows in PHP: ordering by a value inside the JSON
	 * blob is not portable across MySQL/MariaDB versions, and the
	 * server-side threshold keeps row counts bounded anyway.
	 */
	private static function sort_rows( &$rows, $columns, $request ) {
		$order = $request->get_param( 'order' );
		$index = 0;
		if (
			is_array( $order )
			&& isset( $order[0]['column'] )
			&& isset( $columns[ absint( $order[0]['column'] ) ] )
		) {
			$index = absint( $order[0]['column'] );
		}

		$descending = is_array( $order )
			&& isset( $order[0]['dir'] )
			&& 'desc' === strtolower( (string) $order[0]['dir'] );

		$type      = $columns[ $index ]['data_type'];
		$column_id = $columns[ $index ]['id'];

		usort(
			$rows,
			function ( $a, $b ) use ( $column_id, $type ) {
				$av = isset( $a['cells_data'][ $column_id ] )
					? $a['cells_data'][ $column_id ] : '';
				$bv = isset( $b['cells_data'][ $column_id ] )
					? $b['cells_data'][ $column_id ] : '';

				if ( 'number' === $type ) {
					return (float) $av <=> (float) $bv;
				}
				return strcasecmp( (string) $av, (string) $bv );
			}
		);

		if ( $descending ) {
			$rows = array_reverse( $rows );
		}
	}

	private static function search_term( $search ) {
		if (
			is_array( $search )
			&& isset( $search['value'] )
		) {
			return WTB_Sanitizer::text( $search['value'] );
		}
		return '';
	}

	/**
	 * Fixed-window per-IP counter; the transient expires one hour
	 * after the first request in the window.
	 */
	private static function rate_limited( $settings ) {
		$ip = self::client_ip();
		if ( '' === $ip ) {
			return false;
		}

		$key   = 'wtb_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= (int) $settings['form']['rate_limit'] ) {
			return true;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return false;
	}

	private static function client_ip() {
		if ( ! isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}
		return WTB_Sanitizer::text(
			wp_unslash( $_SERVER['REMOTE_ADDR'] )
		);
	}

	private static function table_post( $table_id ) {
		$post = get_post( absint( $table_id ) );
		if (
			! $post
			|| WTB_Table_Post_Type::POST_TYPE !== $post->post_type
		) {
			return null;
		}
		return $post;
	}

	private static function not_found() {
		return new WP_Error(
			'wtb_table_not_found',
			__( 'Table not found.', 'wp-table-builder' ),
			[ 'status' => 404 ]
		);
	}
}
