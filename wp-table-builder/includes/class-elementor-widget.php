<?php
/**
 * Elementor widget wrapping one table.
 *
 * Loaded only when Elementor is active (the bootstrap gates the
 * require on the elementor/loaded action). All markup comes from
 * WTB_Table_Renderer / WTB_Table_Css; this class contributes the
 * editor controls and merges their values over the table's own
 * settings as render-time overrides.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'wtb_table';
	}

	public function get_title() {
		return __( 'Data Table', 'wp-table-builder' );
	}

	public function get_icon() {
		return 'eicon-table';
	}

	public function get_categories() {
		return [ 'general' ];
	}

	public function get_keywords() {
		return [ 'table', 'data', 'rows' ];
	}

	protected function register_controls() {
		$this->start_controls_section(
			'wtb_content',
			[
				'label' => __( 'Table', 'wp-table-builder' ),
			]
		);

		$this->add_control(
			'table_id',
			[
				'label'   => __( 'Table', 'wp-table-builder' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $this->table_options(),
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'wtb_style',
			[
				'label' => __( 'Style overrides', 'wp-table-builder' ),
			]
		);

		foreach (
			[
				'header_background' => __( 'Header background', 'wp-table-builder' ),
				'header_text'       => __( 'Header text', 'wp-table-builder' ),
				'body_text'         => __( 'Body text', 'wp-table-builder' ),
				'border_color'      => __( 'Border', 'wp-table-builder' ),
			]
			as $key => $label
		) {
			$this->add_control(
				'color_' . $key,
				[
					'label' => $label,
					'type'  => \Elementor\Controls_Manager::COLOR,
					'default' => '',
				]
			);
		}

		$this->add_control(
			'cell_padding',
			[
				'label'   => __( 'Cell padding (px)', 'wp-table-builder' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => '',
				'min'     => 0,
				'max'     => 50,
			]
		);

		foreach (
			[
				'search'     => __( 'Search box', 'wp-table-builder' ),
				'sorting'    => __( 'Column sorting', 'wp-table-builder' ),
				'pagination' => __( 'Pagination', 'wp-table-builder' ),
			]
			as $key => $label
		) {
			$this->add_control(
				'feature_' . $key,
				[
					'label'   => $label,
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => '',
					// Empty means inherit the table's own setting.
					'options' => [
						''    => __( 'Inherit', 'wp-table-builder' ),
						'yes' => __( 'On', 'wp-table-builder' ),
						'no'  => __( 'Off', 'wp-table-builder' ),
					],
				]
			);
		}

		$this->end_controls_section();
	}

	protected function render() {
		$widget = $this->get_settings_for_display();
		$table_id = absint( isset( $widget['table_id'] ) ? $widget['table_id'] : 0 );

		if ( ! $table_id ) {
			echo esc_html__(
				'Select a table to display.',
				'wp-table-builder'
			);
			return;
		}

		$post = get_post( $table_id );
		if (
			! $post
			|| WTB_Table_Post_Type::POST_TYPE !== $post->post_type
		) {
			return;
		}

		$settings = $this->merged_settings(
			WTB_Table_Post_Type::get_settings( $table_id )
		);

		$threshold   = (int) $settings['features']['server_side_threshold'];
		$server_side = $threshold > 0
			&& WTB_Table_Storage::count_rows( $table_id, 'published' )
				> $threshold;

		$rows = $server_side
			? []
			: WTB_Table_Storage::get_rows(
				$table_id,
				[ 'status' => 'published' ]
			);

		$columns = WTB_Table_Storage::get_columns( $table_id );
		if ( ! $columns ) {
			return;
		}

		wp_enqueue_style( 'wtb-frontend' );
		wp_enqueue_script( 'wtb-frontend' );

		// phpcs:ignore WordPress.Security.EscapeOutput -- both methods escape internally.
		echo WTB_Table_Css::render( $table_id, $settings );
		// phpcs:ignore WordPress.Security.EscapeOutput -- escapes internally.
		echo WTB_Table_Renderer::render_table(
			$columns,
			$rows,
			$settings,
			[
				'table_id'    => $table_id,
				'server_side' => $server_side,
			]
		);
	}

	/**
	 * Apply non-empty control values on top of the stored settings so
	 * the CSS generator sees one uniform array.
	 */
	private function merged_settings( $settings ) {
		$widget = $this->get_settings_for_display();

		$color_map = [
			'color_header_background' => 'header_background',
			'color_header_text'       => 'header_text',
			'color_body_text'         => 'body_text',
			'color_border_color'      => 'border',
		];
		foreach ( $color_map as $control => $key ) {
			$value = WTB_Sanitizer::color(
				isset( $widget[ $control ] ) ? $widget[ $control ] : ''
			);
			if ( '' !== $value ) {
				$settings['colors'][ $key ] = $value;
			}
		}

		$padding = WTB_Sanitizer::int(
			isset( $widget['cell_padding'] ) ? $widget['cell_padding'] : ''
		);
		if ( $padding > 0 ) {
			$settings['layout']['cell_padding'] = $padding;
		}

		foreach ( [ 'search', 'sorting', 'pagination' ] as $key ) {
			$control = 'feature_' . $key;
			if ( empty( $widget[ $control ] ) ) {
				continue;
			}
			$settings['features'][ $key ] =
				'yes' === $widget[ $control ];
		}

		return $settings;
	}

	private function table_options() {
		$posts = get_posts(
			[
				'post_type'        => WTB_Table_Post_Type::POST_TYPE,
				'numberposts'      => 200,
				'post_status'      => 'publish',
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			]
		);

		$options = [ '' => __( '— Select —', 'wp-table-builder' ) ];
		foreach ( $posts as $post ) {
			$options[ $post->ID ] = $post->post_title;
		}
		return $options;
	}
}
