<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WTB_Elementor_Form_Integration {

    public static function init() {
        // Intercept Elementor Form submission automatically
        add_action( 'elementor_pro/forms/new_record', [ __CLASS__, 'on_elementor_form_submit' ], 10, 2 );
        add_action( 'elementor/forms/new_record',     [ __CLASS__, 'on_elementor_form_submit' ], 10, 2 );
    }

    public static function on_elementor_form_submit( $record, $handler ) {
        global $wpdb;

        $form_settings = $record->get( 'form_settings' );
        $table_id      = isset( $form_settings['wtb_target_table_id'] ) ? absint( $form_settings['wtb_target_table_id'] ) : 0;

        // If no table ID is set in settings, try finding table ID from form name or custom meta
        if ( ! $table_id && isset( $form_settings['form_name'] ) ) {
            $form_name = sanitize_text_field( $form_settings['form_name'] );
            if ( preg_match( '/table[_\-\s]*(\d+)/i', $form_name, $m ) ) {
                $table_id = absint( $m[1] );
            }
        }

        if ( ! $table_id ) return;

        $post = get_post( $table_id );
        if ( ! $post || $post->post_type !== 'wtb_table' ) return;

        $columns = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, label, data_type FROM {$wpdb->prefix}wtb_columns WHERE table_id = %d ORDER BY sort_order ASC",
                $table_id
            ),
            ARRAY_A
        );

        if ( empty( $columns ) ) return;

        $raw_fields  = $record->get( 'fields' );
        $cells_clean = [];

        foreach ( $columns as $col ) {
            $col_id    = (string) $col['id'];
            $col_label = mb_strtolower( trim( $col['label'] ) );
            $found_val = '';

            foreach ( $raw_fields as $field_id => $field_data ) {
                $f_val   = $field_data['value'] ?? '';
                $f_title = mb_strtolower( trim( $field_data['title'] ?? $field_id ) );

                if ( is_array( $f_val ) ) {
                    $f_val = implode( ', ', $f_val );
                }

                if ( $col_id === (string) $field_id || $col_label === $f_title || $col_label === mb_strtolower( trim( $field_id ) ) ) {
                    $found_val = (string) $f_val;
                    break;
                }
            }

            $cells_clean[ $col_id ] = WTB_Sanitizer::cell_value( $found_val, $col['data_type'] );
        }

        $settings_raw = get_post_meta( $table_id, '_wtb_settings', true );
        $settings     = WTB_Sanitizer::table_settings(
            $settings_raw ? (array) json_decode( $settings_raw, true ) : []
        );

        $status = ! empty( $settings['form_require_approval'] ) ? 'pending' : 'published';

        $max_order = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT MAX(sort_order) FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d", $table_id )
        );

        $wpdb->insert(
            $wpdb->prefix . 'wtb_rows',
            [
                'table_id'   => $table_id,
                'cells_data' => wp_json_encode( $cells_clean ),
                'sort_order' => $max_order + 1,
                'status'     => $status,
            ],
            [ '%d', '%s', '%d', '%s' ]
        );
    }
}
