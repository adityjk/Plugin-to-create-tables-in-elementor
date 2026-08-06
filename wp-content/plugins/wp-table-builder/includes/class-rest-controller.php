<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WTB_Rest_Controller {

    const NAMESPACE = 'wtb/v1';

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes() {
        register_rest_route( self::NAMESPACE, '/tables', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'list_tables' ],
                'permission_callback' => [ __CLASS__, 'admin_permission' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'create_table' ],
                'permission_callback' => [ __CLASS__, 'admin_permission' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/tables/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_table' ],
                'permission_callback' => [ __CLASS__, 'admin_permission' ],
                'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ __CLASS__, 'delete_table' ],
                'permission_callback' => [ __CLASS__, 'admin_permission' ],
                'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/tables/(?P<id>\d+)/save', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'save_table' ],
            'permission_callback' => [ __CLASS__, 'admin_permission' ],
            'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
        ] );

        register_rest_route( self::NAMESPACE, '/tables/(?P<id>\d+)/duplicate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'duplicate_table' ],
            'permission_callback' => [ __CLASS__, 'admin_permission' ],
            'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
        ] );

        register_rest_route( self::NAMESPACE, '/tables/(?P<id>\d+)/data', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_server_side_data' ],
            'permission_callback' => '__return_true',
            'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
        ] );
    }

    public static function admin_permission(): bool {
        return current_user_can( 'manage_options' );
    }

    public static function list_tables( WP_REST_Request $request ): WP_REST_Response {
        $tables = get_posts( [
            'post_type'      => 'wtb_table',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $data = array_map( function( $t ) {
            return [ 'id' => $t->ID, 'title' => $t->post_title ];
        }, $tables );

        return rest_ensure_response( $data );
    }

    public static function get_table( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        global $wpdb;

        $table_id = (int) $request->get_param( 'id' );
        $post     = get_post( $table_id );

        if ( ! $post || $post->post_type !== 'wtb_table' ) {
            return new WP_Error( 'not_found', __( 'Tabel tidak ditemukan.', 'wp-table-builder' ), [ 'status' => 404 ] );
        }

        $columns = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, label, data_type, sort_order FROM {$wpdb->prefix}wtb_columns WHERE table_id = %d ORDER BY sort_order ASC",
                $table_id
            ),
            ARRAY_A
        );

        $rows_raw = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, cells_data, sort_order FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d ORDER BY sort_order ASC",
                $table_id
            ),
            ARRAY_A
        );

        $rows = array_map( function( $row ) {
            $row['cells_data'] = $row['cells_data']
                ? (array) json_decode( $row['cells_data'], true )
                : [];
            $row['id']         = (int) $row['id'];
            $row['sort_order'] = (int) $row['sort_order'];
            return $row;
        }, $rows_raw );

        $settings_raw = get_post_meta( $table_id, '_wtb_settings', true );
        $settings     = WTB_Sanitizer::table_settings(
            $settings_raw ? (array) json_decode( $settings_raw, true ) : []
        );

        return rest_ensure_response( [
            'id'       => $table_id,
            'title'    => $post->post_title,
            'columns'  => $columns,
            'rows'     => $rows,
            'settings' => $settings,
        ] );
    }

    public static function save_table( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        global $wpdb;

        $table_id = (int) $request->get_param( 'id' );
        $post     = get_post( $table_id );

        if ( ! $post || $post->post_type !== 'wtb_table' ) {
            return new WP_Error( 'not_found', __( 'Tabel tidak ditemukan.', 'wp-table-builder' ), [ 'status' => 404 ] );
        }

        $body     = $request->get_json_params();
        $title    = WTB_Sanitizer::plain_text( $body['title']    ?? '' );
        $settings = WTB_Sanitizer::table_settings( (array) ( $body['settings'] ?? [] ) );
        $columns  = (array) ( $body['columns'] ?? [] );
        $rows     = (array) ( $body['rows']    ?? [] );

        if ( empty( $title ) ) {
            return new WP_Error( 'invalid_title', __( 'Nama tabel tidak boleh kosong.', 'wp-table-builder' ), [ 'status' => 400 ] );
        }

        wp_update_post( [ 'ID' => $table_id, 'post_title' => $title ] );

        update_post_meta( $table_id, '_wtb_settings', wp_json_encode( $settings ) );

        $temp_key_to_id  = [];
        $keep_column_ids = [];
        foreach ( $columns as $index => $col ) {
            $col_id    = absint( $col['id']         ?? 0 );
            $temp_key  = sanitize_text_field( $col['temp_key'] ?? '' );
            $label     = WTB_Sanitizer::plain_text( $col['label']    ?? '' );
            $data_type = WTB_Sanitizer::data_type( $col['data_type'] ?? 'text' );

            if ( $col_id > 0 ) {
                $wpdb->update(
                    $wpdb->prefix . 'wtb_columns',
                    [ 'label' => $label, 'data_type' => $data_type, 'sort_order' => $index ],
                    [ 'id' => $col_id, 'table_id' => $table_id ],
                    [ '%s', '%s', '%d' ],
                    [ '%d', '%d' ]
                );
                $keep_column_ids[] = $col_id;
            } else {
                $wpdb->insert(
                    $wpdb->prefix . 'wtb_columns',
                    [ 'table_id' => $table_id, 'label' => $label, 'data_type' => $data_type, 'sort_order' => $index ],
                    [ '%d', '%s', '%s', '%d' ]
                );
                $new_id = (int) $wpdb->insert_id;
                $keep_column_ids[] = $new_id;
                if ( $temp_key ) {
                    $temp_key_to_id[ $temp_key ] = $new_id;
                }
            }
        }

        if ( ! empty( $keep_column_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $keep_column_ids ), '%d' ) );
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}wtb_columns WHERE table_id = %d AND id NOT IN ($placeholders)",
                    array_merge( [ $table_id ], $keep_column_ids )
                )
            );
        } else {
            $wpdb->delete( $wpdb->prefix . 'wtb_columns', [ 'table_id' => $table_id ], [ '%d' ] );
        }

        $keep_row_ids = [];
        foreach ( $rows as $index => $row ) {
            $row_id     = absint( $row['id']         ?? 0 );
            $cells_raw  = (array) ( $row['cells_data'] ?? [] );

            $cells_clean = [];
            foreach ( $cells_raw as $key => $value ) {
                $real_key = isset( $temp_key_to_id[ $key ] ) ? (string) $temp_key_to_id[ $key ] : $key;
                $col_type = 'text';
                foreach ( $columns as $c ) {
                    $cid = absint( $c['id'] ?? 0 );
                    if ( ($cid > 0 && (string) $cid === $real_key) || (isset($c['temp_key']) && isset($temp_key_to_id[$c['temp_key']]) && (string)$temp_key_to_id[$c['temp_key']] === $real_key) ) {
                        $col_type = WTB_Sanitizer::data_type( $c['data_type'] ?? 'text' );
                        break;
                    }
                }
                $cells_clean[ $real_key ] = WTB_Sanitizer::cell_value( (string) $value, $col_type );
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
                $keep_row_ids[] = $row_id;
            } else {
                $wpdb->insert(
                    $wpdb->prefix . 'wtb_rows',
                    [ 'table_id' => $table_id, 'cells_data' => $cells_json, 'sort_order' => $index ],
                    [ '%d', '%s', '%d' ]
                );
                $keep_row_ids[] = (int) $wpdb->insert_id;
            }
        }

        if ( ! empty( $keep_row_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $keep_row_ids ), '%d' ) );
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d AND id NOT IN ($placeholders)",
                    array_merge( [ $table_id ], $keep_row_ids )
                )
            );
        } else {
            $wpdb->delete( $wpdb->prefix . 'wtb_rows', [ 'table_id' => $table_id ], [ '%d' ] );
        }

        return rest_ensure_response( [ 'success' => true, 'table_id' => $table_id ] );
    }

    public static function create_table( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $body  = $request->get_json_params();
        $title = WTB_Sanitizer::plain_text( $body['title'] ?? '' );

        if ( empty( $title ) ) {
            return new WP_Error( 'invalid_title', __( 'Nama tabel tidak boleh kosong.', 'wp-table-builder' ), [ 'status' => 400 ] );
        }

        $table_id = wp_insert_post( [
            'post_title'  => $title,
            'post_type'   => 'wtb_table',
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $table_id ) ) {
            return new WP_Error( 'create_failed', __( 'Gagal membuat tabel.', 'wp-table-builder' ), [ 'status' => 500 ] );
        }

        $default_settings = WTB_Sanitizer::table_settings( [] );
        update_post_meta( $table_id, '_wtb_settings', wp_json_encode( $default_settings ) );

        return rest_ensure_response( [ 'success' => true, 'table_id' => $table_id ] );
    }

    public static function delete_table( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        global $wpdb;

        $table_id = (int) $request->get_param( 'id' );
        $post     = get_post( $table_id );

        if ( ! $post || $post->post_type !== 'wtb_table' ) {
            return new WP_Error( 'not_found', __( 'Tabel tidak ditemukan.', 'wp-table-builder' ), [ 'status' => 404 ] );
        }

        wp_delete_post( $table_id, true );
        $wpdb->delete( $wpdb->prefix . 'wtb_columns', [ 'table_id' => $table_id ], [ '%d' ] );
        $wpdb->delete( $wpdb->prefix . 'wtb_rows',    [ 'table_id' => $table_id ], [ '%d' ] );

        return rest_ensure_response( [ 'success' => true ] );
    }

    public static function duplicate_table( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        global $wpdb;

        $table_id = (int) $request->get_param( 'id' );
        $post     = get_post( $table_id );

        if ( ! $post || $post->post_type !== 'wtb_table' ) {
            return new WP_Error( 'not_found', __( 'Tabel tidak ditemukan.', 'wp-table-builder' ), [ 'status' => 404 ] );
        }

        $new_id = wp_insert_post( [
            'post_title'  => $post->post_title . ' (Copy)',
            'post_type'   => 'wtb_table',
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $new_id ) ) {
            return new WP_Error( 'duplicate_failed', __( 'Gagal menduplikat tabel.', 'wp-table-builder' ), [ 'status' => 500 ] );
        }

        $settings_raw = get_post_meta( $table_id, '_wtb_settings', true );
        if ( $settings_raw ) update_post_meta( $new_id, '_wtb_settings', $settings_raw );

        $columns = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT label, data_type, sort_order FROM {$wpdb->prefix}wtb_columns WHERE table_id = %d ORDER BY sort_order ASC",
                $table_id
            ),
            ARRAY_A
        );

        foreach ( $columns as $col ) {
            $wpdb->insert(
                $wpdb->prefix . 'wtb_columns',
                [ 'table_id' => $new_id, 'label' => $col['label'], 'data_type' => $col['data_type'], 'sort_order' => $col['sort_order'] ],
                [ '%d', '%s', '%s', '%d' ]
            );
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT cells_data, sort_order FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d ORDER BY sort_order ASC",
                $table_id
            ),
            ARRAY_A
        );

        foreach ( $rows as $row ) {
            $wpdb->insert(
                $wpdb->prefix . 'wtb_rows',
                [ 'table_id' => $new_id, 'cells_data' => $row['cells_data'], 'sort_order' => $row['sort_order'] ],
                [ '%d', '%s', '%d' ]
            );
        }

        return rest_ensure_response( [ 'success' => true, 'new_table_id' => $new_id ] );
    }

    public static function get_server_side_data( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        global $wpdb;

        $table_id = (int) $request->get_param( 'id' );
        $post     = get_post( $table_id );

        if ( ! $post || $post->post_type !== 'wtb_table' ) {
            return new WP_Error( 'not_found', __( 'Tabel tidak ditemukan.', 'wp-table-builder' ), [ 'status' => 404 ] );
        }

        $draw   = absint( $request->get_param( 'draw' )   ?? 1 );
        $start  = absint( $request->get_param( 'start' )  ?? 0 );
        $length = absint( $request->get_param( 'length' ) ?? 10 );
        $search = sanitize_text_field( $request->get_param( 'search' ) ?? '' );

        $total = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d", $table_id )
        );

        $where    = $wpdb->prepare( 'WHERE table_id = %d', $table_id );
        $filtered = $total;

        if ( $search ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where   .= $wpdb->prepare( ' AND cells_data LIKE %s', $like );
            $filtered = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wtb_rows $where" );
        }

        $rows_raw = $wpdb->get_results(
            "SELECT cells_data FROM {$wpdb->prefix}wtb_rows $where ORDER BY sort_order ASC LIMIT $length OFFSET $start",
            ARRAY_A
        );

        $data = array_map( function( $row ) {
            return $row['cells_data'] ? (array) json_decode( $row['cells_data'], true ) : [];
        }, $rows_raw );

        return rest_ensure_response( [
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ] );
    }
}
