<?php
/**
 * Single input-sanitization entry point for the plugin.
 *
 * Every class passes raw user input through this class; sanitize_*
 * functions are not called anywhere else. Values returned here are
 * safe to store; output escaping stays the renderer's job because the
 * correct escaping depends on markup context, not on the data itself.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Sanitizer {

	/**
	 * Canonical column data types. Storage and rendering both branch
	 * on these, so anything outside the list falls back to text.
	 */
	const DATA_TYPES = [ 'text', 'number', 'date', 'image', 'url', 'post' ];

	/**
	 * Post fields a "post"-type column is allowed to pull from.
	 */
	const POST_FIELDS = [
		'title',
		'excerpt',
		'content',
		'date',
		'author',
		'featured_image_url',
	];

	/**
	 * Image sizes an "image"-type column may request at render time.
	 */
	const IMAGE_SIZES = [ 'thumbnail', 'medium', 'medium_large', 'large', 'full' ];

	/**
	 * Frontend filter widgets a column header can request.
	 */
	const FILTER_TYPES = [ 'none', 'select' ];

	/**
	 * Plain text: tags stripped, whitespace trimmed.
	 */
	public static function text( $value ) {
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Lowercase identifier: a-z, 0-9, underscore. Used for column ids
	 * and enum-like keys where anything else means invalid input.
	 */
	public static function key( $value ) {
		return preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $value ) );
	}

	/**
	 * Boolean that understands the string forms REST bodies and form
	 * posts actually produce ("", "0", "false"), not just PHP casting.
	 */
	public static function bool( $value ) {
		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			return ! in_array( $value, [ '', '0', 'false', 'off', 'no' ], true );
		}
		return (bool) $value;
	}

	/**
	 * Non-negative integer.
	 */
	public static function int( $value ) {
		return absint( $value );
	}

	/**
	 * Hex color, or empty string when the input is not one. Kept local
	 * instead of calling sanitize_hex_color() so validity never depends
	 * on which core files happen to be loaded.
	 */
	public static function color( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value ) ) {
			return $value;
		}
		return '';
	}

	/**
	 * Normalized _wtb_settings blob. Every key is whitelisted, so
	 * settings read back from meta are safe to use without further
	 * checks and unknown keys sent by a client are dropped here.
	 */
	public static function table_settings( $raw ) {
		$raw      = is_array( $raw ) ? $raw : [];
		$colors   = is_array( $raw['colors'] ?? null ) ? $raw['colors'] : [];
		$layout   = is_array( $raw['layout'] ?? null ) ? $raw['layout'] : [];
		$features = is_array( $raw['features'] ?? null ) ? $raw['features'] : [];
		$form     = is_array( $raw['form'] ?? null ) ? $raw['form'] : [];

		$source  = self::key( self::field( $raw, 'data_source' ) );
		$sources = [ 'manual', 'submissions' ];

		$page_length = self::int( self::field( $features, 'page_length' ) );
		$rate_limit  = self::int( self::field( $form, 'rate_limit' ) );

		return [
			'colors' => [
				'header_background' => self::color( self::field( $colors, 'header_background' ) ),
				'header_text'       => self::color( self::field( $colors, 'header_text' ) ),
				'body_text'         => self::color( self::field( $colors, 'body_text' ) ),
				'border'            => self::color( self::field( $colors, 'border' ) ),
				'row_even'          => self::color( self::field( $colors, 'row_even' ) ),
				'row_odd'           => self::color( self::field( $colors, 'row_odd' ) ),
			],
			'layout' => [
				'cell_padding' => self::int( self::field( $layout, 'cell_padding' ) ),
				'border_width' => self::int( self::field( $layout, 'border_width' ) ),
			],
			'features' => [
				'search'                => self::bool( self::field( $features, 'search' ) ),
				'sorting'               => self::bool( self::field( $features, 'sorting' ) ),
				'pagination'            => self::bool( self::field( $features, 'pagination' ) ),
				'page_length'           => $page_length > 0 ? $page_length : 10,
				'server_side_threshold' => self::int( self::field( $features, 'server_side_threshold' ) ),
			],
			'data_source' => in_array( $source, $sources, true ) ? $source : 'manual',
			'form'        => [
				'enabled'          => self::bool( self::field( $form, 'enabled' ) ),
				'require_approval' => self::bool( self::field( $form, 'require_approval' ) ),
				// Submissions per IP per hour. Zero would lock the form
				// shut, so the effective floor is the default below.
				'rate_limit'       => $rate_limit > 0 ? $rate_limit : 20,
			],
		];
	}

	/**
	 * One column definition. Unknown data types fall back to text and
	 * unknown sub-settings fall back to inert values, so a malformed
	 * column can never introduce a type the renderer does not know.
	 */
	public static function column( $raw ) {
		$raw      = is_array( $raw ) ? $raw : [];
		$settings = is_array( $raw['settings'] ?? null ) ? $raw['settings'] : [];

		$type = self::key( self::field( $raw, 'data_type' ) );
		if ( ! in_array( $type, self::DATA_TYPES, true ) ) {
			$type = 'text';
		}

		$post_field = self::key( self::field( $settings, 'post_field' ) );
		if ( ! in_array( $post_field, self::POST_FIELDS, true ) ) {
			$post_field = '';
		}

		$image_size = self::key( self::field( $settings, 'image_size' ) );
		if ( ! in_array( $image_size, self::IMAGE_SIZES, true ) ) {
			$image_size = 'thumbnail';
		}

		$filter = self::key( self::field( $settings, 'filter_type' ) );
		if ( ! in_array( $filter, self::FILTER_TYPES, true ) ) {
			$filter = 'none';
		}

		return [
			'id'         => self::key( self::field( $raw, 'id' ) ),
			'label'      => self::text( self::field( $raw, 'label' ) ),
			'data_type'  => $type,
			'settings'   => [
				'post_field'  => $post_field,
				'image_size'  => $image_size,
				'filter_type' => $filter,
				'is_unique'   => self::bool( self::field( $settings, 'is_unique' ) ),
			],
			'sort_order' => self::int( self::field( $raw, 'sort_order' ) ),
		];
	}

	/**
	 * Batch form of column() for the editor's save payload.
	 */
	public static function columns( $raw ) {
		$out = [];
		foreach ( (array) $raw as $column ) {
			$out[] = self::column( $column );
		}
		return $out;
	}

	/**
	 * One cell value, sanitized according to its column's data type.
	 * Image and post cells store object IDs; dates are stored as
	 * submitted text because display formatting happens at render.
	 */
	public static function cell( $value, $data_type ) {
		if ( 'number' === $data_type ) {
			$number = trim( (string) $value );
			return is_numeric( $number ) ? $number : '';
		}
		if ( 'image' === $data_type || 'post' === $data_type ) {
			return (string) absint( $value );
		}
		if ( 'url' === $data_type ) {
			return esc_url_raw( (string) $value );
		}
		return self::text( $value );
	}

	/**
	 * A row's cells keyed by column id. Keys absent from the supplied
	 * column list are dropped, so the stored JSON blob can never grow
	 * keys the table's schema does not know about.
	 */
	public static function row_cells( $raw, $columns ) {
		$raw = is_array( $raw ) ? $raw : [];
		$out = [];
		foreach ( $columns as $column ) {
			$id = $column['id'];
			if ( '' === $id || ! array_key_exists( $id, $raw ) ) {
				continue;
			}
			$out[ $id ] = self::cell( $raw[ $id ], $column['data_type'] );
		}
		return $out;
	}

	/**
	 * Row publication status. Fails closed: only an exact "published"
	 * publishes, anything unrecognized stays pending for review.
	 */
	public static function row_status( $value ) {
		return 'published' === self::key( $value ) ? 'published' : 'pending';
	}

	/**
	 * Tolerant array access for partially-submitted payloads; every
	 * caller wants a scalar, so a missing key reads as empty string.
	 */
	private static function field( $array, $key ) {
		return isset( $array[ $key ] ) ? $array[ $key ] : '';
	}
}
