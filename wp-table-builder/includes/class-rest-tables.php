<?php
/**
 * Admin-side REST handlers: table CRUD, structure save, duplication.
 *
 * Every route calling these is already gated to manage_options in
 * WTB_Rest_Routes; mutating handlers re-check the capability anyway
 * so a routing mistake cannot expose a write path. Request data goes
 * through WTB_Sanitizer / the storage layer's own normalization.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Rest_Tables {

	public static function list_tables( $request ) {
		unset( $request );

		$posts = get_posts(
			[
				'post_type'        => WTB_Table_Post_Type::POST_TYPE,
				'numberposts'      => 200,
				'post_status'      => 'any',
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => true,
			]
		);

		$items = [];
		foreach ( $posts as $post ) {
			$items[] = [
				'id'             => (int) $post->ID,
				'title'          => $post->post_title,
				'rows_published' => WTB_Table_Storage::count_rows(
					$post->ID,
					'published'
				),
				'rows_pending'   => WTB_Table_Storage::count_rows(
					$post->ID,
					'pending'
				),
				'updated'        => mysql2date( 'c', $post->post_modified_gmt ),
			];
		}

		return rest_ensure_response( $items );
	}

	public static function create_table( $request ) {
		$guard = self::guard();
		if ( $guard ) {
			return $guard;
		}

		$title = WTB_Sanitizer::text( $request->get_param( 'title' ) );
		if ( '' === $title ) {
			$title = __( 'Untitled table', 'wp-table-builder' );
		}

		$post_id = wp_insert_post(
			[
				'post_type'   => WTB_Table_Post_Type::POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'publish',
			],
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		WTB_Table_Post_Type::save_settings( $post_id, [] );

		return rest_ensure_response( [ 'id' => (int) $post_id ] );
	}

	public static function get_table( $request ) {
		$post = self::table_post( $request['id'] );
		if ( ! $post ) {
			return self::not_found();
		}

		return rest_ensure_response(
			[
				'id'       => (int) $post->ID,
				'title'    => $post->post_title,
				'settings' => WTB_Table_Post_Type::get_settings( $post->ID ),
				'columns'  => WTB_Table_Storage::get_columns( $post->ID ),
				// Editor sees pending rows too; approval is managed here.
				'rows'     => WTB_Table_Storage::get_rows( $post->ID ),
			]
		);
	}

	/**
	 * Full-state save: title/settings optional, but columns AND rows
	 * must both be present because storage deletes anything missing
	 * from the payload — an accidental partial save must not wipe a
	 * table's content.
	 */
	public static function save_table( $request ) {
		$guard = self::guard();
		if ( $guard ) {
			return $guard;
		}

		$post = self::table_post( $request['id'] );
		if ( ! $post ) {
			return self::not_found();
		}

		$columns_raw = $request->get_param( 'columns' );
		$rows_raw    = $request->get_param( 'rows' );
		if ( ! is_array( $columns_raw ) || ! is_array( $rows_raw ) ) {
			return new WP_Error(
				'wtb_incomplete_save',
				__(
					'Save payload must include columns and rows.',
					'wp-table-builder'
				),
				[ 'status' => 400 ]
			);
		}

		$title = $request->get_param( 'title' );
		if ( null !== $title ) {
			wp_update_post(
				[
					'ID'         => $post->ID,
					'post_title' => WTB_Sanitizer::text( $title ),
				]
			);
		}

		$settings = $request->get_param( 'settings' );
		if ( null !== $settings ) {
			WTB_Table_Post_Type::save_settings( $post->ID, $settings );
		}

		$saved = WTB_Table_Storage::save_structure(
			$post->ID,
			$columns_raw,
			$rows_raw
		);

		return rest_ensure_response(
			[
				'id'       => $post->ID,
				'title'    => get_the_title( $post ),
				'settings' => WTB_Table_Post_Type::get_settings( $post->ID ),
				'columns'  => $saved['columns'],
				'rows'     => $saved['rows'],
			]
		);
	}

	public static function delete_table( $request ) {
		$guard = self::guard();
		if ( $guard ) {
			return $guard;
		}

		$post = self::table_post( $request['id'] );
		if ( ! $post ) {
			return self::not_found();
		}

		WTB_Table_Storage::delete_table( $post->ID );
		wp_delete_post( $post->ID, true );

		return rest_ensure_response( [ 'deleted' => true ] );
	}

	public static function duplicate_table( $request ) {
		$guard = self::guard();
		if ( $guard ) {
			return $guard;
		}

		$post = self::table_post( $request['id'] );
		if ( ! $post ) {
			return self::not_found();
		}

		$new_id = wp_insert_post(
			[
				'post_type'   => WTB_Table_Post_Type::POST_TYPE,
				'post_title'  => $post->post_title . ' (copy)',
				'post_status' => 'publish',
			],
			true
		);
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		WTB_Table_Post_Type::save_settings(
			$new_id,
			WTB_Table_Post_Type::get_settings( $post->ID )
		);
		WTB_Table_Storage::duplicate( $post->ID, $new_id );

		return rest_ensure_response( [ 'id' => (int) $new_id ] );
	}

	/**
	 * Second capability gate for state-changing handlers.
	 */
	private static function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'wtb_forbidden',
				__( 'Not allowed.', 'wp-table-builder' ),
				[ 'status' => 403 ]
			);
		}
		return null;
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
