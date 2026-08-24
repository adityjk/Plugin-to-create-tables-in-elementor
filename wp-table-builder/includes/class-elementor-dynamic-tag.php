<?php
/**
 * "Table Cell" Elementor dynamic tag.
 *
 * Resolves table + column + row number to one plain-text cell value.
 * Only text-family columns are offered as targets - image and post
 * columns store object IDs that mean nothing outside the renderer.
 * Loaded only while Elementor is active; the bootstrap gates this
 * require behind elementor/loaded.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Elementor_Dynamic_Tag extends \Elementor\Core\DynamicTags\Tag {

	/**
	 * Column types whose stored values are display text.
	 */
	const VALUE_TYPES = [ 'text', 'number', 'date', 'url' ];

	public static function init() {
		add_action(
			'elementor/dynamic_tags/register_tags',
			[ __CLASS__, 'register_group_and_tag' ]
		);
	}

	public static function register_group_and_tag( $tags_manager ) {
		$tags_manager->register_group(
			'wtb',
			[ 'title' => __( 'WP Table Builder', 'wp-table-builder' ) ]
		);
		$tags_manager->register_tag( self::class );
	}

	public function get_name() {
		return 'wtb_table_cell';
	}

	public function get_title() {
		return __( 'Table Cell', 'wp-table-builder' );
	}

	public function get_group() {
		return 'wtb';
	}

	public function get_categories() {
		return [
			\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::NUMBER_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::URL_CATEGORY,
		];
	}

	protected function register_controls() {
		$this->add_control(
			'table_id',
			[
				'label'   => __( 'Table', 'wp-table-builder' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $this->table_options(),
			]
		);

		// Elementor builds editor panels client-side, so these
		// options cannot be rebuilt when the table control changes -
		// that would need custom AJAX wiring in the panel. Every
		// eligible column of every table is listed instead, labelled
		// with its table; get_value() returns '' unless the pick
		// matches the selected table, so a stale or mismatched pair
		// can never resolve to another table's data.
		$this->add_control(
			'column_ref',
			[
				'label'       => __( 'Column', 'wp-table-builder' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '',
				'options'     => $this->column_options(),
				'description' => __(
					'Choose a column belonging to the selected table.',
					'wp-table-builder'
				),
			]
		);

		$this->add_control(
			'row_number',
			[
				'label'   => __( 'Row number', 'wp-table-builder' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 1,
				'min'     => 1,
			]
		);
	}

	/**
	 * Plain-text cell value, or '' for anything unknown, out of
	 * range, or mismatched with the selected table.
	 *
	 * Published rows only: tags render on public pages, so pending
	 * submissions must not leak through them.
	 */
	public function get_value( array $options = [] ) {
		unset( $options );

		$settings = $this->get_settings_for_display();

		$table_id = absint(
			isset( $settings['table_id'] ) ? $settings['table_id'] : 0
		);

		$ref = self::parse_column_ref(
			isset( $settings['column_ref'] ) ? $settings['column_ref'] : ''
		);

		if (
			! $table_id
			|| ! $ref['column']
			|| $ref['table'] !== $table_id
		) {
			return '';
		}

		$rows       = self::published_rows( $table_id );
		$row_number = absint(
			isset( $settings['row_number'] ) ? $settings['row_number'] : 1
		);
		$index      = max( 1, $row_number ) - 1;

		if ( ! isset( $rows[ $index ]['cells_data'][ $ref['column'] ] ) ) {
			return '';
		}

		return (string) $rows[ $index ]['cells_data'][ $ref['column'] ];
	}

	private static function parse_column_ref( $value ) {
		$out = [ 'table' => 0, 'column' => 0 ];

		if ( preg_match( '/^(\d+):(\d+)$/', (string) $value, $match ) ) {
			$out['table']  = absint( $match[1] );
			$out['column'] = absint( $match[2] );
		}

		return $out;
	}

	/**
	 * One storage fetch per table per request even when several tag
	 * instances resolve on the same page render.
	 */
	private static function published_rows( $table_id ) {
		static $cache = [];

		if ( ! isset( $cache[ $table_id ] ) ) {
			$cache[ $table_id ] = WTB_Table_Storage::get_rows(
				$table_id,
				[ 'status' => 'published' ]
			);
		}

		return $cache[ $table_id ];
	}

	private function tables() {
		return get_posts(
			[
				'post_type'        => WTB_Table_Post_Type::POST_TYPE,
				'numberposts'      => 200,
				'post_status'      => 'publish',
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			]
		);
	}

	private function table_options() {
		$options = [ '' => __( '— Select —', 'wp-table-builder' ) ];

		foreach ( $this->tables() as $post ) {
			$options[ $post->ID ] = $post->post_title;
		}

		return $options;
	}

	/**
	 * Keys are "{table_id}:{column_id}" so the value alone carries
	 * both halves of what get_value() must verify.
	 */
	private function column_options() {
		$options = [ '' => __( '— Select —', 'wp-table-builder' ) ];

		foreach ( $this->tables() as $post ) {
			foreach (
				WTB_Table_Storage::get_columns( $post->ID )
				as $column
			) {
				if (
					! in_array(
						$column['data_type'],
						self::VALUE_TYPES,
						true
					)
				) {
					continue;
				}

				$key                = $post->ID . ':' . $column['id'];
				$options[ $key ]    = $post->post_title . ' › '
					. $column['label'];
			}
		}

		return $options;
	}
}
