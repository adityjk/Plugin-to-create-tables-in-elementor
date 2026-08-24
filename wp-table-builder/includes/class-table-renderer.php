<?php
/**
 * Builds visitor-facing HTML: the table grid and the submission form.
 *
 * Inputs are the arrays shaped by WTB_Table_Storage and
 * WTB_Sanitizer; this class never queries the database. All output
 * escaping happens here, once, at the last moment before HTML leaves
 * the plugin.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Table_Renderer {

	/**
	 * Column types exposed on the public submission form. Image and
	 * post columns are editorial (they reference media/posts by ID)
	 * and cannot be filled meaningfully by a visitor.
	 */
	const FORM_TYPES = [ 'text', 'number', 'date', 'url' ];

	/**
	 * Full table markup. Options: table_id (int), server_side (bool,
	 * caller compares row count against the threshold), submit handled
	 * elsewhere. When server_side is set, rows render via AJAX and the
	 * body is left empty for frontend.js to populate.
	 */
	public static function render_table( $columns, $rows, $settings, $options = [] ) {
		$config = [
			'search'     => (bool) $settings['features']['search'],
			'sorting'    => (bool) $settings['features']['sorting'],
			'pagination' => (bool) $settings['features']['pagination'],
			'pageLength' => (int) $settings['features']['page_length'],
			'serverSide' => ! empty( $options['server_side'] ),
			'restBase'   => esc_url_raw( rest_url( 'wtb/v1' ) ),
		];

		$html = '<div class="wtb-table-wrap" data-wtb-config="'
			. esc_attr( wp_json_encode( $config ) ) . '"';
		if ( isset( $options['table_id'] ) ) {
			$html .= ' data-table-id="' . absint( $options['table_id'] ) . '"';
		}
		$html .= '>';

		$html .= '<table class="wtb-table"><thead><tr>';
		foreach ( $columns as $column ) {
			$html .= self::header_cell( $column );
		}
		$html .= '</tr></thead><tbody>';

		if ( empty( $config['serverSide'] ) ) {
			foreach ( $rows as $row ) {
				$html .= '<tr data-row-id="' . absint( $row['id'] ) . '">';
				foreach ( $columns as $column ) {
					$value  = isset( $row['cells_data'][ $column['id'] ] )
						? $row['cells_data'][ $column['id'] ]
						: '';
					$html  .= '<td>' . self::cell( $value, $column ) . '</td>';
				}
				$html .= '</tr>';
			}
		}

		$html .= '</tbody></table></div>';

		return $html;
	}

	/**
	 * Visitor submission form. Posts cells[col_id] pairs plus a nonce;
	 * frontend.js intercepts the submit and calls the REST endpoint.
	 */
	public static function render_form( $columns, $options = [] ) {
		$table_id = absint( isset( $options['table_id'] ) ? $options['table_id'] : 0 );
		if ( ! $table_id ) {
			return '';
		}

		$submit_text = isset( $options['submit_text'] )
			? WTB_Sanitizer::text( $options['submit_text'] )
			: __( 'Submit', 'wp-table-builder' );

		$html = '<form class="wtb-form" method="post" data-table-id="'
			. $table_id . '"'
			// Forms can render standalone (no table wrap nearby), so
			// they carry their own REST base for frontend.js.
			. ' data-rest-base="' . esc_url_raw( rest_url( 'wtb/v1' ) )
			. '">';
		$html .= '<input type="hidden" name="wtb_form_nonce" value="'
			. esc_attr( wp_create_nonce( 'wtb_form_submit' ) ) . '">';

		foreach ( $columns as $column ) {
			if ( in_array( $column['data_type'], self::FORM_TYPES, true ) ) {
				$html .= self::form_field( $column );
			}
		}

		$html .= '<p class="wtb-form-actions"><button type="submit"'
			. ' class="wtb-form-submit">' . esc_html( $submit_text )
			. '</button></p>';
		$html .= '</form>';

		return $html;
	}

	private static function header_cell( $column ) {
		$attrs = ' data-type="' . esc_attr( $column['data_type'] ) . '"';

		if ( 'select' === $column['settings']['filter_type'] ) {
			$attrs .= ' data-filter="select"';
		}
		if ( ! empty( $column['settings']['is_unique'] ) ) {
			$attrs .= ' data-unique="1"';
		}

		return '<th' . $attrs . '>' . esc_html( $column['label'] ) . '</th>';
	}

	/**
	 * One cell's inner HTML, dispatched by column type. Text-family
	 * values were stored through sanitize_text_field and only need
	 * entity encoding here. Public because the server-side REST
	 * endpoint reuses it to build JSON cell payloads.
	 */
	public static function cell( $value, $column ) {
		switch ( $column['data_type'] ) {
			case 'image':
				return self::image_cell( $value, $column );
			case 'url':
				return self::url_cell( $value );
			case 'post':
				return self::post_cell( $value, $column );
			default:
				return esc_html( $value );
		}
	}

	private static function image_cell( $value, $column ) {
		$attachment_id = absint( $value );
		if ( ! $attachment_id ) {
			return '';
		}

		return wp_get_attachment_image(
			$attachment_id,
			$column['settings']['image_size']
		);
	}

	private static function url_cell( $value ) {
		$href = esc_url( (string) $value );
		if ( '' === $href ) {
			return '';
		}

		return '<a href="' . $href . '">' . esc_html( (string) $value ) . '</a>';
	}

	/**
	 * Pulls a field off the referenced post. Rich fields go through
	 * wp_kses_post because posts legitimately contain allowed markup.
	 */
	private static function post_cell( $value, $column ) {
		$post = get_post( absint( $value ) );
		if ( ! $post ) {
			return '';
		}

		switch ( $column['settings']['post_field'] ) {
			case 'title':
				return esc_html( get_the_title( $post ) );
			case 'excerpt':
				return wp_kses_post( get_the_excerpt( $post ) );
			case 'content':
				return wp_kses_post( $post->post_content );
			case 'date':
				return esc_html( get_the_date( '', $post ) );
			case 'author':
				return esc_html(
					get_the_author_meta( 'display_name', $post->post_author )
				);
			case 'featured_image_url':
				$thumb_id = (int) get_post_thumbnail_id( $post );
				return $thumb_id
					? wp_get_attachment_image(
						$thumb_id,
						$column['settings']['image_size']
					)
					: '';
			default:
				return '';
		}
	}

	private static function form_field( $column ) {
		$html_types = [
			'text'   => 'text',
			'number' => 'number',
			'date'   => 'date',
			'url'    => 'url',
		];

		$field_id = 'wtb_field_' . $column['id'];

		return '<p class="wtb-form-field">'
			. '<label for="' . esc_attr( $field_id ) . '">'
			. esc_html( $column['label'] ) . '</label>'
			. '<input type="' . esc_attr( $html_types[ $column['data_type'] ] )
			. '" id="' . esc_attr( $field_id ) . '"'
			. ' name="cells[' . esc_attr( $column['id'] ) . ']">'
			. '</p>';
	}
}
