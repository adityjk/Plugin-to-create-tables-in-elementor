<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Ensure Elementor Pro is active before defining the class
add_action( 'elementor_pro/forms/actions/register', function ( $action_registrar ) {
    if ( ! class_exists( '\ElementorPro\Modules\Forms\Classes\Action_Base' ) ) {
        return;
    }

    class WTB_Elementor_Form_Action extends \ElementorPro\Modules\Forms\Classes\Action_Base {

        public function get_name() {
            return 'wtb_table_action';
        }

        public function get_label() {
            return esc_html__( 'WP Table Builder', 'wp-table-builder' );
        }

        public function register_settings_section( $widget ) {
            $widget->start_controls_section(
                'section_wtb_table_action',
                [
                    'label' => esc_html__( 'WP Table Builder', 'wp-table-builder' ),
                    'condition' => [
                        'submit_actions' => $this->get_name(),
                    ],
                ]
            );

            $tables = get_posts([
                'post_type'      => 'wtb_table',
                'posts_per_page' => -1,
                'post_status'    => 'any'
            ]);

            $options = [
                '' => esc_html__( '-- Pilih Tabel --', 'wp-table-builder' )
            ];

            foreach ( $tables as $table ) {
                $options[ $table->ID ] = $table->post_title . ' (ID: ' . $table->ID . ')';
            }

            $widget->add_control(
                'wtb_target_table_id',
                [
                    'label'   => esc_html__( 'Target Table', 'wp-table-builder' ),
                    'type'    => \Elementor\Controls_Manager::SELECT,
                    'options' => $options,
                    'default' => '',
                    'description' => esc_html__( 'Pilih tabel untuk menyimpan data dari form ini.', 'wp-table-builder' ),
                ]
            );

            $widget->end_controls_section();
        }

        public function on_export( $element ) {}

        public function run( $record, $ajax_handler ) {
            // Memanggil logika utama yang sudah kita buat sebelumnya
            WTB_Elementor_Form_Integration::on_elementor_form_submit( $record, $ajax_handler );
        }
    }

    $action_registrar->register( new WTB_Elementor_Form_Action() );
} );
