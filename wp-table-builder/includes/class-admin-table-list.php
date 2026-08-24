<?php
/**
 * All-tables list view.
 *
 * Server-rendered read-only markup; every state change (create,
 * duplicate, delete) goes through the admin REST routes by
 * admin-builder.js using the X-WP-Nonce from the page wrapper. The
 * view therefore needs no forms or nonces of its own - only escaped
 * output and data attributes describing what each control does.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Admin_Table_List {

	public static function render() {
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

		echo '<h1 class="wp-heading-inline">'
			. esc_html__( 'Tables', 'wp-table-builder' ) . '</h1>';

		echo '<button type="button" class="page-title-action"'
			. ' data-wtb-action="create">'
			. esc_html__( 'New Table', 'wp-table-builder' )
			. '</button><hr class="wp-header-end">';

		if ( ! $posts ) {
			echo '<p>' . esc_html__(
				'No tables yet. Create your first one above.',
				'wp-table-builder'
			) . '</p>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		echo '<th class="column-primary">'
			. esc_html__( 'Title', 'wp-table-builder' ) . '</th>';
		echo '<th>' . esc_html__( 'Published rows', 'wp-table-builder' ) . '</th>';
		echo '<th>' . esc_html__( 'Pending rows', 'wp-table-builder' ) . '</th>';
		echo '<th>' . esc_html__( 'Updated', 'wp-table-builder' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'wp-table-builder' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $posts as $post ) {
			self::row( $post );
		}

		echo '</tbody></table>';
	}

	private static function row( $post ) {
		$table_id = (int) $post->ID;
		$edit_url = self::edit_url( $table_id );

		$title = '' !== $post->post_title
			? $post->post_title
			: __( '(no title)', 'wp-table-builder' );

		echo '<tr>';

		echo '<td class="column-primary"><a class="row-title" href="'
			. esc_url( $edit_url ) . '">' . esc_html( $title )
			. '</a></td>';

		echo '<td>' . absint(
			WTB_Table_Storage::count_rows( $table_id, 'published' )
		) . '</td>';

		echo '<td>' . absint(
			WTB_Table_Storage::count_rows( $table_id, 'pending' )
		) . '</td>';

		echo '<td>' . esc_html(
			mysql2date( get_option( 'date_format' ), $post->post_modified )
		) . '</td>';

		echo '<td class="wtb-row-actions">';

		echo '<a href="' . esc_url( $edit_url ) . '">'
			. esc_html__( 'Edit', 'wp-table-builder' ) . '</a>';

		echo '<button type="button" class="button-link"'
			. ' data-wtb-action="duplicate" data-id="' . $table_id . '">'
			. esc_html__( 'Duplicate', 'wp-table-builder' ) . '</button>';

		echo '<button type="button" class="button-link"'
			. ' data-wtb-action="delete" data-id="' . $table_id . '"'
			. ' data-confirm="' . esc_attr__(
				'Delete this table and all of its rows?',
				'wp-table-builder'
			) . '">'
			. esc_html__( 'Delete', 'wp-table-builder' ) . '</button>';

		echo '</td></tr>';
	}

	private static function edit_url( $table_id ) {
		return admin_url(
			'admin.php?page=' . WTB_Admin_Menu::SLUG
			. '&table=' . absint( $table_id )
		);
	}
}
