<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WTB_Elementor_Dynamic_Tag extends \Elementor\Core\DynamicTags\Tag {

    public function get_name() {
        return 'wtb-table-form-tag';
    }

    public function get_title() {
        return esc_html__( 'WP Table Builder', 'wtb-table-builder' );
    }

    public function get_group() {
        return 'post'; // Using 'post' group so it appears in standard Elementor dropdowns
    }

    public function get_categories() {
        return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
    }

    protected function register_controls() {
        // Query to get all WTB Tables
        $tables = get_posts([
            'post_type'      => 'wtb_table',
            'posts_per_page' => -1,
            'post_status'    => 'any'
        ]);

        $options = [
            '' => esc_html__( '-- Pilih Tabel --', 'wtb-table-builder' )
        ];

        foreach ( $tables as $table ) {
            $options[ $table->ID ] = $table->post_title . ' (ID: ' . $table->ID . ')';
        }

        $this->add_control(
            'table_id',
            [
                'label'   => esc_html__( 'Pilih Tabel', 'wtb-table-builder' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'options' => $options,
                'default' => '',
            ]
        );
    }

    public function render() {
        $table_id = $this->get_settings( 'table_id' );

        if ( ! empty( $table_id ) ) {
            // Echo the format expected by our Elementor Form integration logic: "Table {ID}"
            echo 'Table ' . absint( $table_id );
        }
    }
}
