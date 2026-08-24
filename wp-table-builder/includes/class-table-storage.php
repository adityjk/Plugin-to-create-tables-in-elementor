<?php
/**
 * All database access for wtb_columns and wtb_rows lives here.
 *
 * No other class touches $wpdb for these tables. Every write path
 * re-normalizes its payload through WTB_Sanitizer, so a caller that
 * forgets to sanitize cannot introduce unsafe data; reads return
 * plain arrays shaped like the sanitizer's output.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Table_Storage {

	/**
	 * Table names derived from the install prefix. WTB_Activator uses
	 * the same source so the schema and the queries can never drift.
	 */
	public static function table_names() {
		global $wpdb;

		return [
			'columns' => $wpdb->prefix . 'wtb_columns',
			'rows'    => $wpdb->prefix . 'wtb_rows',
		];
	}

	/**
	 * Columns for one table, ordered for display. Settings come back
	 * decoded; ids are the auto-increment ints cells_data is keyed by.
	 */
	public static function get_columns( $table_id ) {
		global $wpdb;
		$names = self::table_names();

		$sql = "SELECT id, label, data_type, settings, sort_order
			FROM {$names['columns']}
			WHERE table_id = %d
			ORDER BY sort_order ASC, id ASC";

		$results = $wpdb->get_results(
			$wpdb->prepare( $sql, absint( $table_id ) ),
			ARRAY_A
		);

		$out = [];
		foreach ( (array) $results as $row ) {
			$out[] = [
				'id'         => (int) $row['id'],
				'label'      => (string) $row['label'],
				'data_type'  => (string) $row['data_type'],
				'settings'   => self::decode_json( $row['settings'] ),
				'sort_order' => (int) $row['sort_order'],
			];
		}
		return $out;
	}

	/**
	 * Rows for one table. Optional args: status (published|pending,
	 * omitted = all), search (substring match across the cells JSON),
	 * limit and offset (both go through prepare as %d).
	 */
	public static function get_rows( $table_id, $args = [] ) {
		global $wpdb;
		$names = self::table_names();

		$where  = [ 'table_id = %d' ];
		$params = [ absint( $table_id ) ];

		if ( ! empty( $args['status'] ) ) {
			$status   = WTB_Sanitizer::key( $args['status'] );
			$where[]  = 'status = %s';
			$params[] = in_array( $status, [ 'published', 'pending' ], true )
				? $status
				: 'published';
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like(
				WTB_Sanitizer::text( $args['search'] )
			) . '%';
			$where[]  = 'cells_data LIKE %s';
			$params[] = $like;
		}

		$sql = 'SELECT id, cells_data, sort_order, status'
			. ' FROM ' . $names['rows']
			. ' WHERE ' . implode( ' AND ', $where )
			. ' ORDER BY sort_order ASC, id ASC';

		if ( isset( $args['limit'] ) ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = absint( $args['limit'] );
			$params[] = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
		}

		$results = $wpdb->get_results( $wpdb->prepare( ...$params ), ARRAY_A );

		$out = [];
		foreach ( (array) $results as $row ) {
			$out[] = [
				'id'         => (int) $row['id'],
				'cells_data' => self::decode_json( $row['cells_data'] ),
				'sort_order' => (int) $row['sort_order'],
				'status'     => (string) $row['status'],
			];
		}
		return $out;
	}

	/**
	 * Row count, optionally restricted to one status and/or the same
	 * substring search get_rows() applies, so server-side pagination
	 * totals always agree with the rows actually returned.
	 */
	public static function count_rows( $table_id, $status = '', $search = '' ) {
		global $wpdb;
		$names  = self::table_names();
		$where  = [ 'table_id = %d' ];
		$params = [ absint( $table_id ) ];

		$status = WTB_Sanitizer::key( $status );
		if ( in_array( $status, [ 'published', 'pending' ], true ) ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}
		if ( '' !== (string) $search ) {
			$like     = '%' . $wpdb->esc_like(
				WTB_Sanitizer::text( $search )
			) . '%';
			$where[]  = 'cells_data LIKE %s';
			$params[] = $like;
		}

		$sql = 'SELECT COUNT(*) FROM ' . $names['rows']
			. ' WHERE ' . implode( ' AND ', $where );

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
	}

	/**
	 * Full-state sync from the editor: the payload is the complete
	 * desired set of columns and rows, anything absent from it is
	 * deleted. Column ids that are not existing ids for this table are
	 * treated as temp keys for new columns; they are inserted, and
	 * every row cell keyed by a temp key is remapped to the real id.
	 *
	 * Returns the saved state (fresh columns and rows) so the REST
	 * response can hand the editor canonical ids immediately.
	 */
	public static function save_structure( $table_id, $columns_raw, $rows_raw ) {
		global $wpdb;
		$table_id = absint( $table_id );
		if ( ! $table_id ) {
			return false;
		}
		$names = self::table_names();

		$existing = [];
		foreach ( self::get_columns( $table_id ) as $column ) {
			$existing[ (string) $column['id'] ] = true;
		}

		$kept_ids = [];
		$remap    = [];
		$order    = 0;

		foreach ( WTB_Sanitizer::columns( $columns_raw ) as $column ) {
			$key                 = (string) $column['id'];
			$column['sort_order'] = $order++;

			if ( '' === $key || ! isset( $existing[ $key ] ) ) {
				$new_id        = self::insert_column( $table_id, $column );
				$remap[ $key ] = $new_id;
				$kept_ids[]    = $new_id;
				continue;
			}

			$wpdb->update(
				$names['columns'],
				[
					'label'      => $column['label'],
					'data_type'  => $column['data_type'],
					'settings'   => wp_json_encode( $column['settings'] ),
					'sort_order' => $column['sort_order'],
				],
				[ 'id' => (int) $key, 'table_id' => $table_id ],
				[ '%s', '%s', '%s', '%d' ],
				[ '%d', '%d' ]
			);
			$kept_ids[] = (int) $key;
		}

		self::delete_missing_columns( $table_id, $kept_ids );
		self::sync_rows( $table_id, $rows_raw, $remap );

		return [
			'columns' => self::get_columns( $table_id ),
			'rows'    => self::get_rows( $table_id ),
		];
	}

	/**
	 * Append one row. Used by visitor submissions and the Elementor
	 * form action; cells are filtered against the table's current
	 * columns, so unknown or missing keys cannot be smuggled in.
	 *
	 * Returns the new row id, or 0 when the table does not exist.
	 */
	public static function insert_row( $table_id, $cells_raw, $status = 'pending' ) {
		global $wpdb;
		$table_id = absint( $table_id );
		if ( ! $table_id ) {
			return 0;
		}
		$names = self::table_names();

		$columns = self::get_columns( $table_id );
		if ( ! $columns ) {
			return 0;
		}

		$wpdb->insert(
			$names['rows'],
			[
				'table_id'   => $table_id,
				'cells_data' => wp_json_encode(
					WTB_Sanitizer::row_cells( $cells_raw, $columns )
				),
				'sort_order' => self::next_sort_order( $table_id ),
				'status'     => WTB_Sanitizer::row_status( $status ),
			],
			[ '%d', '%s', '%d', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Approval flow: flip one row between pending and published.
	 */
	public static function set_row_status( $row_id, $status ) {
		global $wpdb;
		$row_id = absint( $row_id );
		if ( ! $row_id ) {
			return false;
		}

		return false !== $wpdb->update(
			self::table_names()['rows'],
			[ 'status' => WTB_Sanitizer::row_status( $status ) ],
			[ 'id' => $row_id ],
			[ '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Copy all columns and rows from one table to another (the CPT
	 * itself is duplicated by the caller). Cell keys follow the id map
	 * built while copying columns; unmapped keys are dropped.
	 */
	public static function duplicate( $source_table_id, $target_table_id ) {
		$source_table_id = absint( $source_table_id );
		$target_table_id = absint( $target_table_id );
		if ( ! $source_table_id || ! $target_table_id ) {
			return false;
		}

		$id_map = [];
		foreach ( self::get_columns( $source_table_id ) as $column ) {
			$id_map[ (string) $column['id'] ] = self::insert_column(
				$target_table_id,
				$column
			);
		}

		foreach ( self::get_rows( $source_table_id ) as $row ) {
			$cells = [];
			foreach ( $row['cells_data'] as $key => $value ) {
				$string_key = (string) $key;
				if ( isset( $id_map[ $string_key ] ) ) {
					$cells[ $id_map[ $string_key ] ] = $value;
				}
			}
			self::insert_row( $target_table_id, $cells, $row['status'] );
		}
		return true;
	}

	/**
	 * Remove a table's columns and rows. The CPT deletion happens in
	 * the caller; this cleans the custom-table side.
	 */
	public static function delete_table( $table_id ) {
		global $wpdb;
		$table_id = absint( $table_id );
		if ( ! $table_id ) {
			return false;
		}
		$names = self::table_names();

		$wpdb->delete( $names['columns'], [ 'table_id' => $table_id ], [ '%d' ] );
		$wpdb->delete( $names['rows'], [ 'table_id' => $table_id ], [ '%d' ] );
		return true;
	}

	/**
	 * Insert one already-sanitized column, returning its new id.
	 */
	private static function insert_column( $table_id, $column ) {
		global $wpdb;
		$names = self::table_names();

		$wpdb->insert(
			$names['columns'],
			[
				'table_id'   => $table_id,
				'label'      => $column['label'],
				'data_type'  => $column['data_type'],
				'settings'   => wp_json_encode( $column['settings'] ),
				'sort_order' => $column['sort_order'],
			],
			[ '%d', '%s', '%s', '%s', '%d' ]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete columns of this table whose ids are not in the kept list
	 * (the editor payload defines what survives).
	 */
	private static function delete_missing_columns( $table_id, $kept_ids ) {
		global $wpdb;
		$names = self::table_names();

		$current = [];
		foreach ( self::get_columns( $table_id ) as $column ) {
			$current[] = (int) $column['id'];
		}

		$remove = array_diff( $current, array_map( 'absint', $kept_ids ) );
		foreach ( $remove as $column_id ) {
			$wpdb->delete(
				$names['columns'],
				[ 'id' => $column_id, 'table_id' => $table_id ],
				[ '%d', '%d' ]
			);
		}
	}

	/**
	 * Rewrite the row set to match the payload. Removed-column keys
	 * disappear here because row_cells() only keeps current column
	 * ids; temp keys are translated through the remap first.
	 */
	private static function sync_rows( $table_id, $rows_raw, $remap ) {
		global $wpdb;
		$names   = self::table_names();
		$columns = self::get_columns( $table_id );
		$kept    = [];

		foreach ( (array) $rows_raw as $index => $row_raw ) {
			$row_raw = is_array( $row_raw ) ? $row_raw : [];
			$cells   = self::remap_cell_keys(
				isset( $row_raw['cells_data'] ) ? $row_raw['cells_data'] : [],
				$remap
			);
			$cells  = WTB_Sanitizer::row_cells( $cells, $columns );
			$status = WTB_Sanitizer::row_status(
				isset( $row_raw['status'] ) ? $row_raw['status'] : ''
			);
			$row_id = absint( isset( $row_raw['id'] ) ? $row_raw['id'] : 0 );

			$data   = [
				'cells_data' => wp_json_encode( $cells ),
				'sort_order' => $index,
				'status'     => $status,
			];
			$format = [ '%s', '%d', '%s' ];

			if ( $row_id ) {
				$wpdb->update(
					$names['rows'],
					$data,
					[ 'id' => $row_id, 'table_id' => $table_id ],
					$format,
					[ '%d', '%d' ]
				);
				$kept[] = $row_id;
				continue;
			}

			$wpdb->insert(
				$names['rows'],
				array_merge( [ 'table_id' => $table_id ], $data ),
				array_merge( [ '%d' ], $format )
			);
			$kept[] = (int) $wpdb->insert_id;
		}

		if ( $kept ) {
			$placeholders = implode( ', ', array_fill( 0, count( $kept ), '%d' ) );
			$wpdb->query( $wpdb->prepare(
				'DELETE FROM ' . $names['rows']
				. ' WHERE table_id = %d AND id NOT IN (' . $placeholders . ')',
				absint( $table_id ),
				...$kept
			) );
			return;
		}

		$wpdb->delete( $names['rows'], [ 'table_id' => $table_id ], [ '%d' ] );
	}

	/**
	 * Translate temp column keys to freshly assigned real ids.
	 */
	private static function remap_cell_keys( $cells, $remap ) {
		$cells = is_array( $cells ) ? $cells : [];
		if ( ! $remap ) {
			return $cells;
		}

		$out = [];
		foreach ( $cells as $key => $value ) {
			$key = (string) $key;
			if ( isset( $remap[ $key ] ) ) {
				$key = (string) $remap[ $key ];
			}
			$out[ $key ] = $value;
		}
		return $out;
	}

	/**
	 * Next append position for a table's rows.
	 */
	private static function next_sort_order( $table_id ) {
		global $wpdb;
		$names = self::table_names();

		$sql = 'SELECT COALESCE( MAX( sort_order ), -1 ) + 1'
			. ' FROM ' . $names['rows']
			. ' WHERE table_id = %d';

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, absint( $table_id ) ) );
	}

	/**
	 * JSON decode that always yields an array, never null/scalars.
	 */
	private static function decode_json( $value ) {
		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? $decoded : [];
	}
}
