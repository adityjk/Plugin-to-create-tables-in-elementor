<?php
/**
 * Elementor Pro form action: store a submission as one table row.
 *
 * Runs through the same persistence path as the public REST endpoint
 * - cells land in WTB_Table_Storage::insert_row(), which filters keys
 * against the table's live columns and sanitizes each value by type -
 * so Elementor cannot introduce a row shape the editor would not
 * produce. Loaded only while Elementor is active; the file is
 * required by the bootstrap behind elementor/loaded.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Elementor_Form_Action
	extends \ElementorPro\Modules\Forms\Classes\Action_Base {

	public function get_name() {
		return 'wtb_insert_row';
	}

	public function get_label() {
		return __(
			'WP Table Builder: Insert Row',
			'wp-table-builder'
		);
	}

	public function register_settings_controls( $widget ) {
		$widget->start_controls_section(
			'wtb_action_section',
			[
				'label'     => __( 'WP Table Builder', 'wp-table-builder' ),
				'condition' => [
					'submit_actions' => $this->get_name(),
				],
			]
		);

		$widget->add_control(
			'wtb_target_table',
			[
				'label'   => __( 'Target table', 'wp-table-builder' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $this->table_options(),
			]
		);

		// Form fields whose label equals a column label are matched
		// automatically. These pairs cover everything else, including
		// image/post columns where an admin deliberately forwards a
		// field (storage drops or casts unknown targets safely).
		$widget->add_control(
			'wtb_field_map',
			[
				'label'       => __(
					'Field-to-column overrides',
					'wp-table-builder'
				),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'title_field' => '{{{ wtb_form_label }}}',
				'fields'      => [
					[
						'name'    => 'wtb_form_label',
						'label'   => __( 'Form field label', 'wp-table-builder' ),
						'type'    => \Elementor\Controls_Manager::TEXT,
						'default' => '',
					],
					[
						'name'       => 'wtb_column_id',
						'label'      => __( 'Column ID', 'wp-table-builder' ),
						'type'       => \Elementor\Controls_Manager::TEXT,
						'default'    => '',
						'description' => __(
							'Numeric column ID from the table editor URL.',
							'wp-table-builder'
						),
					],
				],
			]
		);

		$widget->end_controls_section();
	}

	public function run( $record, $ajax_handler ) {
		unset( $ajax_handler );

		$settings = is_callable( [ $record, 'get_form_settings' ] )
			? $record->get_form_settings()
			: [];

		$table_id = absint(
			isset( $settings['wtb_target_table'] )
				? $settings['wtb_target_table'] : 0
		);

		if ( ! $table_id || ! $this->table_post( $table_id ) ) {
			$this->fail(
				$ajax_handler,
				__( 'No target table configured.', 'wp-table-builder' )
			);
			return;
		}

		$cells = $this->build_cells( $table_id, $record, $settings );
		if ( ! $cells ) {
			$this->fail(
				$ajax_handler,
				__(
					'Nothing submitted matched this table.',
					'wp-table-builder'
				)
			);
			return;
		}

		$form_settings = WTB_Table_Post_Type::get_settings( $table_id );
		$status        = ! empty( $form_settings['form']['require_approval'] )
			? 'pending'
			: 'published';

		$row_id = WTB_Table_Storage::insert_row( $table_id, $cells, $status );
		if ( ! $row_id ) {
			$this->fail(
				$ajax_handler,
				__( 'Could not save the submission.', 'wp-table-builder' )
			);
			return;
		}

		WTB_Debug_Log::add(
			sprintf(
				'elementor-form table=%d row=%d status=%s',
				$table_id,
				$row_id,
				$status
			)
		);

		$message = 'pending' === $status
			? __(
				'Thank you — your submission is awaiting review.',
				'wp-table-builder'
			)
			: __(
				'Thank you — your submission has been received.',
				'wp-table-builder'
			);
		$ajax_handler->add_success_message( $message );
	}

	/**
	 * Internal references should not travel into exported templates.
	 */
	public function on_export( $settings ) {
		unset(
			$settings['wtb_target_table'],
			$settings['wtb_field_map']
		);

		return $settings;
	}

	/**
	 * Submitted values keyed by field label, mapped onto columns.
	 * Auto-match follows the public form's rule (label equality,
	 * text-family columns only); admin overrides are applied last
	 * and may point anywhere - unknown ids are dropped downstream.
	 */
	private function build_cells( $table_id, $record, $settings ) {
		$columns = WTB_Table_Storage::get_columns( $table_id );
		if ( ! $columns ) {
			return [];
		}

		$values = [];
		foreach ( (array) $record->get_formatted_data() as $label => $val ) {
			$values[ (string) $label ] = (string) $val;
		}

		$cells = [];
		foreach ( $columns as $column ) {
			if (
				'' !== $column['label']
				&& isset( $values[ $column['label'] ] )
				&& in_array(
					$column['data_type'],
					WTB_Table_Renderer::FORM_TYPES,
					true
				)
			) {
				$cells[ $column['id'] ] = $values[ $column['label'] ];
			}
		}

		foreach ( self::overrides( $settings ) as $label => $column_id ) {
			if ( isset( $values[ $label ] ) ) {
				$cells[ $column_id ] = $values[ $label ];
			}
		}

		return $cells;
	}

	private static function overrides( $settings ) {
		$rows = isset( $settings['wtb_field_map'] )
			&& is_array( $settings['wtb_field_map'] )
			? $settings['wtb_field_map']
			: [];

		$pairs = [];
		foreach ( $rows as $row ) {
			$row       = is_array( $row ) ? $row : [];
			$label     = WTB_Sanitizer::text(
				isset( $row['wtb_form_label'] ) ? $row['wtb_form_label'] : ''
			);
			$column_id = absint(
				isset( $row['wtb_column_id'] ) ? $row['wtb_column_id'] : 0
			);

			if ( '' !== $label && $column_id ) {
				$pairs[ $label ] = $column_id;
			}
		}

		return $pairs;
	}

	private function table_post( $table_id ) {
		$post = get_post( $table_id );
		if (
			! $post
			|| WTB_Table_Post_Type::POST_TYPE !== $post->post_type
		) {
			return null;
		}
		return $post;
	}

	private function table_options() {
		$options = [ '' => __( '— Select —', 'wp-table-builder' ) ];

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

		foreach ( $posts as $post ) {
			$options[ $post->ID ] = $post->post_title;
		}

		return $options;
	}

	/**
	 * Errors surface to admins only; visitors get the generic
	 * failure notice Elementor shows for a failed submit.
	 */
	private function fail( $ajax_handler, $admin_message ) {
		if ( is_callable( [ $ajax_handler, 'add_admin_error_message' ] ) ) {
			$ajax_handler->add_admin_error_message( $admin_message );
		}
	}
}
