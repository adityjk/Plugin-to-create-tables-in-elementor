<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WTB_Elementor_Form_Integration {

    public static function init() {
        add_action( 'elementor_pro/forms/new_record', [ __CLASS__, 'on_elementor_form_submit' ], 10, 2 );
        add_action( 'elementor/forms/new_record',     [ __CLASS__, 'on_elementor_form_submit' ], 10, 2 );
    }

    public static function on_elementor_form_submit( $record, $handler ) {
        global $wpdb;

        $form_settings = $record->get( 'form_settings' );
        $form_name_raw = $form_settings['form_name'] ?? '(tidak ada)';

        WTB_Debug_Logger::log( 'Form submit diterima', 'INFO', [
            'form_name_raw' => $form_name_raw,
            'hook'          => current_filter(),
        ] );

        // Priority 1: Custom Form Action sets wtb_target_table_id directly
        $table_id = isset( $form_settings['wtb_target_table_id'] ) ? absint( $form_settings['wtb_target_table_id'] ) : 0;

        if ( $table_id ) {
            WTB_Debug_Logger::log( 'table_id dari Custom Action', 'INFO', [ 'table_id' => $table_id ] );
        }

        // Priority 2: Parse dari Form Name
        if ( ! $table_id && isset( $form_settings['form_name'] ) ) {
            $raw = $form_settings['form_name'];

            if ( preg_match( '/\btable[\s_\-]*(\d+)\b/i', $raw, $m ) ) {
                $table_id = absint( $m[1] );
                WTB_Debug_Logger::log( 'table_id dari form_name (pola teks)', 'INFO', [ 'table_id' => $table_id, 'match' => $m[0] ] );
            } elseif ( preg_match( '/^\s*(\d+)\s*$/', $raw, $m ) ) {
                $table_id = absint( $m[1] );
                WTB_Debug_Logger::log( 'table_id dari form_name (angka saja)', 'INFO', [ 'table_id' => $table_id ] );
            } elseif ( preg_match( '/%22table_id%22%3A(?:%22)?(\d+)(?:%22)?/i', $raw, $m ) ) {
                $table_id = absint( $m[1] );
                WTB_Debug_Logger::log( 'table_id dari dynamic tag (URL-encoded)', 'INFO', [ 'table_id' => $table_id ] );
            } elseif ( preg_match( '/"table_id"\s*:\s*"?(\d+)"?/i', $raw, $m ) ) {
                $table_id = absint( $m[1] );
                WTB_Debug_Logger::log( 'table_id dari dynamic tag (JSON)', 'INFO', [ 'table_id' => $table_id ] );
            } elseif ( preg_match( '/\[elementor-tag[^\]]+settings=["\']([^"\']+)["\']/', $raw, $m ) ) {
                $decoded = urldecode( $m[1] );
                if ( preg_match( '/"table_id"\s*:\s*"?(\d+)"?/i', $decoded, $m2 ) ) {
                    $table_id = absint( $m2[1] );
                    WTB_Debug_Logger::log( 'table_id dari elementor shortcode tag', 'INFO', [ 'table_id' => $table_id ] );
                }
            }
        }

        if ( ! $table_id ) {
            WTB_Debug_Logger::log(
                'GAGAL: table_id tidak ditemukan - data TIDAK disimpan',
                'ERROR',
                [
                    'form_name_raw' => $form_name_raw,
                    'petunjuk'      => 'Gunakan Custom Action "WP Table Builder" di form, ATAU set Form Name = "Table {ID}"',
                ]
            );
            return;
        }

        $post = get_post( $table_id );
        if ( ! $post || $post->post_type !== 'wtb_table' ) {
            WTB_Debug_Logger::log( 'GAGAL: table_id tidak valid atau bukan wtb_table', 'ERROR', [
                'table_id'  => $table_id,
                'post_type' => $post ? $post->post_type : 'post tidak ada',
            ] );
            return;
        }

        $columns = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, label, data_type, settings FROM {$wpdb->prefix}wtb_columns WHERE table_id = %d ORDER BY sort_order ASC",
                $table_id
            ),
            ARRAY_A
        );

        if ( empty( $columns ) ) {
            WTB_Debug_Logger::log( 'GAGAL: Tidak ada kolom di tabel ini', 'ERROR', [ 'table_id' => $table_id ] );
            return;
        }

        // Parse is_unique from each column's settings JSON
        foreach ( $columns as &$col ) {
            $col_settings  = $col['settings'] ? (array) json_decode( $col['settings'], true ) : [];
            $col['is_unique'] = ! empty( $col_settings['is_unique'] );
        }
        unset( $col );

        WTB_Debug_Logger::log( 'Kolom tabel ditemukan', 'INFO', [
            'table_id' => $table_id,
            'jumlah'   => count( $columns ),
            'labels'   => implode( ', ', array_column( $columns, 'label' ) ),
        ] );

        $raw_fields    = $record->get( 'fields' );
        $cells_clean   = [];
        $matched       = [];
        $not_matched   = [];

        $field_summary = [];
        foreach ( $raw_fields as $fid => $fd ) {
            if ( is_object( $fd ) ) $fd = (array) $fd;
            $val = $fd['value'] ?? '';
            $field_summary[] = $fid . ':' . ( $fd['title'] ?? $fid ) . '="' . ( is_array( $val ) ? implode( ',', $val ) : $val ) . '"';
        }
        WTB_Debug_Logger::log( 'Field form masuk', 'INFO', [ 'fields' => implode( ' | ', $field_summary ) ] );

        foreach ( $columns as $col ) {
            $col_id    = (string) $col['id'];
            $col_label = mb_strtolower( trim( $col['label'] ) );
            $found_val = '';
            $found     = false;

            foreach ( $raw_fields as $field_id => $field_data ) {
                if ( is_object( $field_data ) ) {
                    $field_data = (array) $field_data;
                }

                $f_val   = $field_data['value'] ?? '';
                $f_label = mb_strtolower( trim( (string) ( $field_data['title'] ?? $field_data['label'] ?? $field_id ) ) );
                $f_id    = mb_strtolower( trim( (string) $field_id ) );

                if ( is_array( $f_val ) ) {
                    $f_val = implode( ', ', $f_val );
                }

                if ( $col_id === (string) $field_id || $col_label === $f_label || $col_label === $f_id ) {
                    $found_val = (string) $f_val;
                    $found     = true;
                    $matched[] = '"' . $col['label'] . '" <- field "' . $field_id . '" nilai="' . $found_val . '"';
                    break;
                }
            }

            if ( ! $found ) {
                $not_matched[] = '"' . $col['label'] . '"';
            }

            $cells_clean[ $col_id ] = WTB_Sanitizer::cell_value( $found_val, $col['data_type'] );
        }

        if ( ! empty( $matched ) ) {
            WTB_Debug_Logger::log( 'Field berhasil di-mapping', 'INFO', [ 'mapping' => implode( ' | ', $matched ) ] );
        }
        if ( ! empty( $not_matched ) ) {
            WTB_Debug_Logger::log( 'Kolom TIDAK match ke field manapun (nilai akan kosong)', 'WARN', [ 'kolom' => implode( ', ', $not_matched ) ] );
        }

        // --- Unique Field Validation ---
        foreach ( $columns as $col ) {
            if ( empty( $col['is_unique'] ) ) continue;

            $col_id    = (string) $col['id'];
            $val       = $cells_clean[ $col_id ] ?? '';

            if ( $val === '' ) continue; // kosong tidak dicek

            // Cari apakah nilai sudah ada di baris published manapun di tabel ini
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}wtb_rows
                 WHERE table_id = %d
                   AND status = 'published'
                   AND JSON_UNQUOTE(JSON_EXTRACT(cells_data, %s)) = %s
                 LIMIT 1",
                $table_id,
                '$.' . $col_id,
                (string) $val
            ) );

            if ( $existing ) {
                WTB_Debug_Logger::log(
                    'DITOLAK: Nilai duplikat pada kolom unique — data TIDAK disimpan',
                    'ERROR',
                    [
                        'kolom'    => $col['label'],
                        'col_id'   => $col_id,
                        'nilai'    => $val,
                        'table_id' => $table_id,
                    ]
                );
                return;
            }
        }

        $settings_raw = get_post_meta( $table_id, '_wtb_settings', true );
        $settings     = WTB_Sanitizer::table_settings(
            $settings_raw ? (array) json_decode( $settings_raw, true ) : []
        );

        $status    = ! empty( $settings['form_require_approval'] ) ? 'pending' : 'published';
        $max_order = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT MAX(sort_order) FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d", $table_id )
        );

        $result = $wpdb->insert(
            $wpdb->prefix . 'wtb_rows',
            [
                'table_id'   => $table_id,
                'cells_data' => wp_json_encode( $cells_clean ),
                'sort_order' => $max_order + 1,
                'status'     => $status,
            ],
            [ '%d', '%s', '%d', '%s' ]
        );

        if ( $result !== false ) {
            WTB_Debug_Logger::log( 'Data berhasil disimpan ke tabel', 'INFO', [
                'table_id' => $table_id,
                'row_id'   => $wpdb->insert_id,
                'status'   => $status,
                'cells'    => wp_json_encode( $cells_clean ),
            ] );
        } else {
            WTB_Debug_Logger::log( 'GAGAL insert ke database', 'ERROR', [
                'table_id'   => $table_id,
                'db_error'   => $wpdb->last_error,
                'last_query' => $wpdb->last_query,
            ] );
        }
    }
}
