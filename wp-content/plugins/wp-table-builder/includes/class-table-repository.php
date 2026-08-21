<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Single access point for the wtb_columns / wtb_rows custom tables.
 *
 * Both the REST controller and the admin-page handlers call into this class,
 * so table persistence logic exists exactly once. All queries use $wpdb
 * prepare()/insert()/update()/delete() with explicit format arrays.
 */
class WTB_Table_Repository {

    /**
     * True when the ID is an existing wtb_table post.
     */
    public static function exists( int $table_id ): bool {
        $post = get_post( $table_id );
        return $post && $post->post_type === 'wtb_table';
    }

    public static function get_settings( int $table_id ): array {
        $raw = get_post_meta( $table_id, '_wtb_settings', true );
        return WTB_Sanitizer::table_settings(
            $raw ? (array) json_decode( $raw, true ) : []
        );
    }

    public static function save_settings( int $table_id, array $settings ): void {
        update_post_meta( $table_id, '_wtb_settings', wp_json_encode( $settings ) );
    }

    /**
     * Columns for a table, ordered. Per-column settings JSON is decoded and
     * merged into the array (keys: post_field, image_size, image_custom_w/h,
     * filter_type, is_unique) so callers never parse JSON themselves.
     */
    public static function get_columns( int $table_id ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, label, data_type, settings, sort_order
                 FROM {$wpdb->prefix}wtb_columns
                 WHERE table_id = %d
                 ORDER BY sort_order ASC",
                $table_id
            ),
            ARRAY_A
        );

        return array_map( [ __CLASS__, 'hydrate_column' ], $rows ?: [] );
    }

    private static function hydrate_column( array $col ): array {
        $settings = ! empty( $col['settings'] ) ? (array) json_decode( $col['settings'], true ) : [];

        return [
            'id'             => (int) $col['id'],
            'label'          => (string) $col['label'],
            'data_type'      => (string) $col['data_type'],
            'sort_order'     => (int) $col['sort_order'],
            'post_field'     => sanitize_text_field( $settings['post_field'] ?? '' ),
            'image_size'     => sanitize_text_field( $settings['image_size'] ?? 'thumbnail' ),
            'image_custom_w' => absint( $settings['image_custom_w'] ?? 100 ),
            'image_custom_h' => absint( $settings['image_custom_h'] ?? 100 ),
            'filter_type'    => sanitize_text_field( $settings['filter_type'] ?? '' ),
            'is_unique'      => ! empty( $settings['is_unique'] ),
        ];
    }

    /**
     * Rows for a table, ordered. cells_data JSON is decoded to an array.
     * When $published_only, rows with empty/legacy status count as published.
     */
    public static function get_rows( int $table_id, bool $published_only = false ): array {
        global $wpdb;

        $sql = "SELECT id, cells_data, sort_order, status
                FROM {$wpdb->prefix}wtb_rows
                WHERE table_id = %d";
        if ( $published_only ) {
            $sql .= " AND (status = 'published' OR status IS NULL OR status = '')";
        }
        $sql .= ' ORDER BY sort_order ASC';

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $table_id ), ARRAY_A );

        return array_map( static function ( array $row ) {
            return [
                'id'         => (int) $row['id'],
                'cells_data' => ! empty( $row['cells_data'] ) ? (array) json_decode( $row['cells_data'], true ) : [],
                'sort_order' => (int) $row['sort_order'],
                'status'     => (string) ( $row['status'] ?? '' ),
            ];
        }, $rows ?: [] );
    }

    /** Raw cells_data strings keyed by row id — used by CSV export. */
    public static function get_raw_cells( int $table_id, bool $published_only = false ): array {
        global $wpdb;

        $sql = "SELECT id, cells_data FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d";
        if ( $published_only ) {
            $sql .= " AND (status = 'published' OR status IS NULL OR status = '')";
        }
        $sql .= ' ORDER BY sort_order ASC';

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $table_id ), ARRAY_A );

        return array_map( static fn( $r ) => (string) $r['cells_data'], $rows ?: [] );
    }

    public static function count_columns( int $table_id ): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wtb_columns WHERE table_id = %d", $table_id )
        );
    }

    public static function count_rows( int $table_id ): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d", $table_id )
        );
    }

    public static function count_published_rows( int $table_id ): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wtb_rows
                 WHERE table_id = %d AND (status = 'published' OR status IS NULL OR status = '')",
                $table_id
            )
        );
    }

    public static function next_sort_order( int $table_id ): int {
        global $wpdb;
        return 1 + (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT MAX(sort_order) FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d", $table_id )
        );
    }

    /**
     * Insert one row. $cells must already be sanitized (WTB_Sanitizer::cell_value).
     *
     * @return int|false New row ID or false on failure.
     */
    public static function insert_row( int $table_id, array $cells, string $status = 'published' ) {
        global $wpdb;

        $ok = $wpdb->insert(
            $wpdb->prefix . 'wtb_rows',
            [
                'table_id'   => $table_id,
                'cells_data' => wp_json_encode( $cells ),
                'sort_order' => self::next_sort_order( $table_id ),
                'status'     => $status,
            ],
            [ '%d', '%s', '%d', '%s' ]
        );

        return $ok ? (int) $wpdb->insert_id : false;
    }

    /**
     * Persist a full column+row payload from the builder editor ("Save").
     * Columns/rows missing from the payload are deleted; new entries are
     * inserted; client-side temp keys are remapped to real DB ids before
     * cell values are sanitized by their column's data type.
     */
    public static function save_structure( int $table_id, array $columns, array $rows ): void {
        global $wpdb;

        $temp_key_to_id = self::save_columns( $table_id, $columns );
        self::save_rows( $table_id, $rows, $columns, $temp_key_to_id );
    }

    /**
     * @return array<string,int> temp_key => new column id (for cell remapping).
     */
    private static function save_columns( int $table_id, array $columns ): array {
        global $wpdb;

        $temp_key_to_id = [];
        $keep_ids       = [];

        foreach ( array_values( $columns ) as $index => $col ) {
            $col_id       = absint( $col['id'] ?? 0 );
            $temp_key     = sanitize_text_field( $col['temp_key'] ?? '' );
            $label        = WTB_Sanitizer::plain_text( $col['label'] ?? '' );
            $data_type    = WTB_Sanitizer::data_type( $col['data_type'] ?? 'text' );
            $settings_json = wp_json_encode( [
                'post_field'     => sanitize_text_field( $col['post_field'] ?? '' ),
                'image_size'     => sanitize_text_field( $col['image_size'] ?? 'thumbnail' ),
                'image_custom_w' => absint( $col['image_custom_w'] ?? 100 ),
                'image_custom_h' => absint( $col['image_custom_h'] ?? 100 ),
                'filter_type'    => sanitize_text_field( $col['filter_type'] ?? '' ),
                'is_unique'      => ! empty( $col['is_unique'] ),
            ] );

            if ( $col_id > 0 ) {
                $wpdb->update(
                    $wpdb->prefix . 'wtb_columns',
                    [
                        'label'      => $label,
                        'data_type'  => $data_type,
                        'settings'   => $settings_json,
                        'sort_order' => $index,
                    ],
                    [ 'id' => $col_id, 'table_id' => $table_id ],
                    [ '%s', '%s', '%s', '%d' ],
                    [ '%d', '%d' ]
                );
                $keep_ids[] = $col_id;
            } else {
                $wpdb->insert(
                    $wpdb->prefix . 'wtb_columns',
                    [
                        'table_id'   => $table_id,
                        'label'      => $label,
                        'data_type'  => $data_type,
                        'settings'   => $settings_json,
                        'sort_order' => $index,
                    ],
                    [ '%d', '%s', '%s', '%s', '%d' ]
                );
                $new_id     = (int) $wpdb->insert_id;
                $keep_ids[] = $new_id;
                if ( $temp_key !== '' ) {
                    $temp_key_to_id[ $temp_key ] = $new_id;
                }
            }
        }

        // Delete columns removed in the editor (scoped to this table).
        if ( ! empty( $keep_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $keep_ids ), '%d' ) );
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}wtb_columns
                     WHERE table_id = %d AND id NOT IN ($placeholders)",
                    array_merge( [ $table_id ], $keep_ids )
                )
            );
        } else {
            $wpdb->delete( $wpdb->prefix . 'wtb_columns', [ 'table_id' => $table_id ], [ '%d' ] );
        }

        return $temp_key_to_id;
    }

    private static function save_rows( int $table_id, array $rows, array $columns, array $temp_key_to_id ): void {
        global $wpdb;

        // Cell keys may arrive as real column ids or as temp keys of brand-new
        // columns — build a lookup of final key => data type for sanitizing.
        $type_by_key = [];
        foreach ( $columns as $col ) {
            $type = WTB_Sanitizer::data_type( $col['data_type'] ?? 'text' );
            $id   = absint( $col['id'] ?? 0 );
            if ( $id > 0 ) {
                $type_by_key[ (string) $id ] = $type;
            }
            $temp_key = sanitize_text_field( $col['temp_key'] ?? '' );
            if ( $temp_key !== '' && isset( $temp_key_to_id[ $temp_key ] ) ) {
                $type_by_key[ (string) $temp_key_to_id[ $temp_key ] ] = $type;
            }
        }

        $keep_ids = [];

        foreach ( array_values( $rows ) as $index => $row ) {
            $row_id = absint( $row['id'] ?? 0 );

            $cells_clean = [];
            foreach ( (array) ( $row['cells_data'] ?? [] ) as $key => $value ) {
                $real_key = isset( $temp_key_to_id[ $key ] ) ? (string) $temp_key_to_id[ $key ] : (string) $key;
                $cells_clean[ $real_key ] = WTB_Sanitizer::cell_value( (string) $value, $type_by_key[ $real_key ] ?? 'text' );
            }

            $cells_json = wp_json_encode( $cells_clean );

            if ( $row_id > 0 ) {
                $wpdb->update(
                    $wpdb->prefix . 'wtb_rows',
                    [ 'cells_data' => $cells_json, 'sort_order' => $index ],
                    [ 'id' => $row_id, 'table_id' => $table_id ],
                    [ '%s', '%d' ],
                    [ '%d', '%d' ]
                );
                $keep_ids[] = $row_id;
            } else {
                $wpdb->insert(
                    $wpdb->prefix . 'wtb_rows',
                    [
                        'table_id'   => $table_id,
                        'cells_data' => $cells_json,
                        'sort_order' => $index,
                    ],
                    [ '%d', '%s', '%d' ]
                );
                $keep_ids[] = (int) $wpdb->insert_id;
            }
        }

        if ( ! empty( $keep_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $keep_ids ), '%d' ) );
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}wtb_rows
                     WHERE table_id = %d AND id NOT IN ($placeholders)",
                    array_merge( [ $table_id ], $keep_ids )
                )
            );
        } else {
            $wpdb->delete( $wpdb->prefix . 'wtb_rows', [ 'table_id' => $table_id ], [ '%d' ] );
        }
    }

    /**
     * Deep-copy columns + rows + settings meta to a new wtb_table post.
     * Unlike ad-hoc copies, this carries column settings JSON and row status.
     *
     * @return int|WP_Error New table post ID.
     */
    public static function duplicate( int $source_id, string $new_title ) {
        global $wpdb;

        $new_id = wp_insert_post( [
            'post_title'  => $new_title,
            'post_type'   => 'wtb_table',
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $new_id ) || ! $new_id ) {
            return new WP_Error( 'duplicate_failed', __( 'Gagal menduplikat tabel.', 'wp-table-builder' ), [ 'status' => 500 ] );
        }

        $settings_raw = get_post_meta( $source_id, '_wtb_settings', true );
        if ( $settings_raw ) {
            add_post_meta( $new_id, '_wtb_settings', $settings_raw, true );
        }

        $columns = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT label, data_type, settings, sort_order
                 FROM {$wpdb->prefix}wtb_columns
                 WHERE table_id = %d ORDER BY sort_order ASC",
                $source_id
            ),
            ARRAY_A
        );

        foreach ( $columns ?: [] as $col ) {
            $wpdb->insert(
                $wpdb->prefix . 'wtb_columns',
                [
                    'table_id'   => $new_id,
                    'label'      => $col['label'],
                    'data_type'  => $col['data_type'],
                    'settings'   => $col['settings'],
                    'sort_order' => (int) $col['sort_order'],
                ],
                [ '%d', '%s', '%s', '%s', '%d' ]
            );
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT cells_data, sort_order, status
                 FROM {$wpdb->prefix}wtb_rows
                 WHERE table_id = %d ORDER BY sort_order ASC",
                $source_id
            ),
            ARRAY_A
        );

        foreach ( $rows ?: [] as $row ) {
            $wpdb->insert(
                $wpdb->prefix . 'wtb_rows',
                [
                    'table_id'   => $new_id,
                    'cells_data' => $row['cells_data'],
                    'sort_order' => (int) $row['sort_order'],
                    'status'     => $row['status'],
                ],
                [ '%d', '%s', '%d', '%s' ]
            );
        }

        return $new_id;
    }

    /** Delete all columns + rows belonging to a table (not the post itself). */
    public static function delete_all_data( int $table_id ): void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'wtb_columns', [ 'table_id' => $table_id ], [ '%d' ] );
        $wpdb->delete( $wpdb->prefix . 'wtb_rows', [ 'table_id' => $table_id ], [ '%d' ] );
    }
}
