<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * CSV export/import for table data.
 *
 * Security notes:
 * - Export neutralizes formula-injection characters at the start of every
 *   cell (=, +, -, @, tab, CR) so spreadsheet apps don't execute payloads.
 * - Import reads cell values through WTB_Sanitizer::cell_value by column type.
 */
class WTB_CSV {

    public static function export( int $table_id ): void {
        if ( ! WTB_Table_Repository::exists( $table_id ) ) {
            wp_die( esc_html__( 'Tabel tidak ditemukan.', 'wp-table-builder' ) );
        }

        $columns = WTB_Table_Repository::get_columns( $table_id );
        if ( empty( $columns ) ) {
            wp_die( esc_html__( 'Tabel kosong.', 'wp-table-builder' ) );
        }

        $rows = WTB_Table_Repository::get_raw_cells( $table_id );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="table-' . $table_id . '.csv"' );

        $output = fopen( 'php://output', 'w' );

        // UTF-8 BOM so Excel detects the encoding.
        fputs( $output, "\xEF\xBB\xBF" );

        fputcsv( $output, array_map(
            [ __CLASS__, 'safe_value' ],
            wp_list_pluck( $columns, 'label' )
        ) );

        foreach ( $rows as $cells_json ) {
            $cells    = $cells_json ? (array) json_decode( $cells_json, true ) : [];
            $data_row = [];
            foreach ( $columns as $col ) {
                $data_row[] = self::safe_value( (string) ( $cells[ (string) $col['id'] ] ?? '' ) );
            }
            fputcsv( $output, $data_row );
        }

        fclose( $output );
        exit;
    }

    /**
     * Prefix dangerous leading characters with an apostrophe so Excel /
     * Google Sheets treat the cell as text instead of a formula.
     */
    private static function safe_value( string $value ): string {
        if ( isset( $value[0] ) && in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * Import rows from an uploaded CSV. Headers are matched to existing
     * columns by label (case-insensitive), so column order may differ.
     */
    public static function import( int $table_id, WP_REST_Request $request ) {
        global $wpdb;

        if ( ! WTB_Table_Repository::exists( $table_id ) ) {
            return new WP_Error( 'not_found', __( 'Tabel tidak ditemukan.', 'wp-table-builder' ), [ 'status' => 404 ] );
        }

        $file = $request->get_file_params()['csv_file'] ?? null;
        if ( ! $file || $file['error'] !== UPLOAD_ERR_OK || ! is_readable( $file['tmp_name'] ) ) {
            return new WP_Error( 'upload_error', __( 'Gagal mengunggah file.', 'wp-table-builder' ), [ 'status' => 400 ] );
        }

        $handle = fopen( $file['tmp_name'], 'r' );
        if ( false === $handle ) {
            return new WP_Error( 'file_error', __( 'Gagal membuka file.', 'wp-table-builder' ), [ 'status' => 500 ] );
        }

        // Skip the UTF-8 BOM if present so the first header matches.
        if ( fread( $handle, 3 ) !== "\xEF\xBB\xBF" ) {
            rewind( $handle );
        }

        $header = fgetcsv( $handle );
        if ( ! $header ) {
            fclose( $handle );
            return new WP_Error( 'invalid_csv', __( 'CSV tidak memiliki header valid.', 'wp-table-builder' ), [ 'status' => 400 ] );
        }

        // Map CSV column index => table column (id + data type).
        $columns = WTB_Table_Repository::get_columns( $table_id );
        $col_map = [];
        foreach ( $header as $index => $label ) {
            $label = mb_strtolower( trim( (string) $label ) );
            foreach ( $columns as $col ) {
                if ( mb_strtolower( trim( $col['label'] ) ) === $label ) {
                    $col_map[ $index ] = [ 'id' => (string) $col['id'], 'type' => $col['data_type'] ];
                    break;
                }
            }
        }

        $inserted = 0;
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $cells_clean = [];
            foreach ( $row as $index => $value ) {
                if ( isset( $col_map[ $index ] ) ) {
                    $cells_clean[ $col_map[ $index ]['id'] ] = WTB_Sanitizer::cell_value( (string) $value, $col_map[ $index ]['type'] );
                }
            }

            if ( ! empty( $cells_clean ) && WTB_Table_Repository::insert_row( $table_id, $cells_clean ) ) {
                $inserted++;
            }
        }

        fclose( $handle );

        return rest_ensure_response( [ 'success' => true, 'imported' => $inserted ] );
    }
}
