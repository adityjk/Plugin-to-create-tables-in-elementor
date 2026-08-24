<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * REST API: namespace wtb/v1.
 *
 * This class handles routing and request/response shaping only — persistence
 * goes through WTB_Table_Repository and CSV transfer through WTB_CSV.
 */
class WTB_Rest_Controller {

    const NAMESPACE = 'wtb/v1';

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes() {
        $id_arg = [ 'id' => [ 'sanitize_callback' => 'absint' ] ];

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
                'args'                => $id_arg,
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ __CLASS__, 'delete_table' ],
                'permission_callback' => [ __CLASS__, 'admin_permission' ],
                'args'                => $id_arg,
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/tables/(?P<id>\d+)/save', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'save_table' ],
            'permission_callback' => [ __CLASS__, 'admin_permission' ],
            'args'                => $id_arg,
        ] );

        register_rest_route( self::NAMESPACE, '/tables/(?P<id>\d+)/duplicate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'duplicate_table' ],
            'permission_callback' => [ __CLASS__, 'admin_permission' ],
            'args'                => $id_arg,
        ] );

        // Public: DataTables server-side processing.
        register_rest_route( self::NAMESPACE, '/tables/(?P<id>\d+)/data', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_server_side_data' ],
            'permission_callback' => '__return_true',
            'args'                => $id_arg,
        ] );

        // Public: visitor form submission / Elementor webhook target.
        register_rest_route( self::NAMESPACE, '/tables/(?P<id>\d+)/submit', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'submit_form_data' ],
            'permission_callback' => '__return_true',
            'args'                => $id_arg,
        ] );

        register_rest_route( self::NAMESPACE, '/tables/(?P<id>\d+)/export_csv', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'export_csv' ],
            'permission_callback' => [ __CLASS__, 'admin_permission' ],
            'args'                => $id_arg,
        ] );

        register_rest_route( self::NAMESPACE, '/tables/(?P<id>\d+)/import_csv', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'import_csv' ],
            'permission_callback' => [ __CLASS__, 'admin_permission' ],
            'args'                => $id_arg,
        ] );

        // Public: lightweight polling endpoint for frontend auto-refresh.
        register_rest_route( self::NAMESPACE, '/tables/(?P<id>\d+)/row-count', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_row_count' ],
            'permission_callback' => '__return_true',
            'args'                => $id_arg,
        ] );
    }

    public static function admin_permission(): bool {
        return current_user_can( 'manage_options' );
    }

    /**
     * Standard not-found guard shared by all single-table endpoints.
     */
    private static function not_found(): WP_Error {
        return new WP_Error( 'not_found', __( 'Tabel tidak ditemukan.', 'wtb-table-builder' ), [ 'status' => 404 ] );
    }

    public static function list_tables(): WP_REST_Response {
        $tables = get_posts( [
            'post_type'      => 'wtb_table',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        return rest_ensure_response( array_map( static fn( WP_Post $t ) => [
            'id'    => $t->ID,
            'title' => $t->post_title,
        ], $tables ) );
    }

    public static function create_table( WP_REST_Request $request ) {
        $title = WTB_Sanitizer::plain_text( $request->get_json_params()['title'] ?? '' );

        if ( $title === '' ) {
            return new WP_Error( 'invalid_title', __( 'Nama tabel tidak boleh kosong.', 'wtb-table-builder' ), [ 'status' => 400 ] );
        }

        $table_id = wp_insert_post( [
            'post_title'  => $title,
            'post_type'   => 'wtb_table',
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $table_id ) || ! $table_id ) {
            return new WP_Error( 'create_failed', __( 'Gagal membuat tabel.', 'wtb-table-builder' ), [ 'status' => 500 ] );
        }

        WTB_Table_Repository::save_settings( $table_id, WTB_Sanitizer::table_settings( [] ) );

        return rest_ensure_response( [ 'success' => true, 'table_id' => (int) $table_id ] );
    }

    public static function get_table( WP_REST_Request $request ) {
        $table_id = (int) $request->get_param( 'id' );
        if ( ! WTB_Table_Repository::exists( $table_id ) ) {
            return self::not_found();
        }

        $post = get_post( $table_id );

        return rest_ensure_response( [
            'id'       => $table_id,
            'title'    => $post->post_title,
            'columns'  => WTB_Table_Repository::get_columns( $table_id ),
            'rows'     => WTB_Table_Repository::get_rows( $table_id ),
            'settings' => WTB_Table_Repository::get_settings( $table_id ),
        ] );
    }

    public static function save_table( WP_REST_Request $request ) {
        global $wpdb;

        $table_id = (int) $request->get_param( 'id' );
        if ( ! WTB_Table_Repository::exists( $table_id ) ) {
            return self::not_found();
        }

        $body  = $request->get_json_params();
        $title = WTB_Sanitizer::plain_text( $body['title'] ?? '' );

        if ( $title === '' ) {
            return new WP_Error( 'invalid_title', __( 'Nama tabel tidak boleh kosong.', 'wtb-table-builder' ), [ 'status' => 400 ] );
        }

        wp_update_post( [ 'ID' => $table_id, 'post_title' => $title ] );
        WTB_Table_Repository::save_settings( $table_id, WTB_Sanitizer::table_settings( (array) ( $body['settings'] ?? [] ) ) );
        WTB_Table_Repository::save_structure(
            $table_id,
            (array) ( $body['columns'] ?? [] ),
            (array) ( $body['rows'] ?? [] )
        );

        return rest_ensure_response( [ 'success' => true, 'table_id' => $table_id ] );
    }

    public static function delete_table( WP_REST_Request $request ) {
        $table_id = (int) $request->get_param( 'id' );
        if ( ! WTB_Table_Repository::exists( $table_id ) ) {
            return self::not_found();
        }

        wp_delete_post( $table_id, true );
        WTB_Table_Repository::delete_all_data( $table_id );

        return rest_ensure_response( [ 'success' => true ] );
    }

    public static function duplicate_table( WP_REST_Request $request ) {
        $table_id = (int) $request->get_param( 'id' );
        if ( ! WTB_Table_Repository::exists( $table_id ) ) {
            return self::not_found();
        }

        $new_id = WTB_Table_Repository::duplicate(
            $table_id,
            get_post( $table_id )->post_title . ' (Copy)'
        );

        if ( is_wp_error( $new_id ) ) {
            return $new_id;
        }

        return rest_ensure_response( [ 'success' => true, 'new_table_id' => $new_id ] );
    }

    public static function get_row_count( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response( [
            'count' => WTB_Table_Repository::count_published_rows( (int) $request['id'] ),
        ], 200 );
    }

    /**
     * DataTables server-side processing for both data sources.
     * Returns draw/recordsTotal/recordsFiltered/data per the DataTables protocol.
     */
    public static function get_server_side_data( WP_REST_Request $request ) {
        global $wpdb;

        $table_id = (int) $request->get_param( 'id' );
        if ( ! WTB_Table_Repository::exists( $table_id ) ) {
            return self::not_found();
        }

        $draw     = absint( $request->get_param( 'draw' ) ?? 1 );
        $start    = absint( $request->get_param( 'start' ) ?? 0 );
        $length   = absint( $request->get_param( 'length' ) ?? 10 );
        $search   = sanitize_text_field( $request->get_param( 'search' )['value'] ?? '' );
        $req_cols = (array) ( $request->get_param( 'columns' ) ?: [] );

        $settings = WTB_Table_Repository::get_settings( $table_id );
        $columns  = WTB_Table_Repository::get_columns( $table_id );

        if ( $settings['data_source'] === 'wp_posts' ) {
            return rest_ensure_response( self::server_side_posts_data(
                $settings, $columns, $draw, $start, $length, $search, $req_cols
            ) );
        }

        // Manual source: filter directly on the JSON blob.
        $where  = "WHERE table_id = %d AND (status = 'published' OR status IS NULL OR status = '')";
        $params = [ $table_id ];

        if ( $search !== '' ) {
            $where   .= ' AND cells_data LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        // Per-column filters arrive indexed by column position (DataTables API).
        foreach ( $req_cols as $index => $col_req ) {
            $col_search = trim( sanitize_text_field( $col_req['search']['value'] ?? '' ) );
            if ( $col_search === '' || ! isset( $columns[ $index ] ) ) continue;

            $where   .= ' AND JSON_UNQUOTE(JSON_EXTRACT(cells_data, %s)) LIKE %s';
            $params[] = '$."' . $columns[ $index ]['id'] . '"';
            $params[] = '%' . $wpdb->esc_like( $col_search ) . '%';
        }

        $filtered = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wtb_rows $where", $params )
        );

        $rows_raw = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT cells_data FROM {$wpdb->prefix}wtb_rows $where ORDER BY sort_order ASC LIMIT %d OFFSET %d",
                array_merge( $params, [ $length, $start ] )
            ),
            ARRAY_A
        );

        return rest_ensure_response( [
            'draw'            => $draw,
            'recordsTotal'    => WTB_Table_Repository::count_published_rows( $table_id ),
            'recordsFiltered' => $filtered,
            'data'            => array_map( static fn( $r ) =>
                ! empty( $r['cells_data'] ) ? (array) json_decode( $r['cells_data'], true ) : [],
                $rows_raw ?: []
            ),
        ] );
    }

    /** Server-side slice of a WP Posts source, incl. taxonomy column filters. */
    private static function server_side_posts_data( array $settings, array $columns, int $draw, int $start, int $length, string $search, array $req_cols ): array {
        $query_args = [
            'post_type'      => $settings['post_type'],
            'post_status'    => 'publish',
            'posts_per_page' => $length,
            'offset'         => $start,
            's'              => $search,
        ];

        // Column filters map to tax_query when the column shows terms.
        $tax_query = [];
        foreach ( $req_cols as $index => $col_req ) {
            $col_search = trim( sanitize_text_field( $col_req['search']['value'] ?? '' ) );
            if ( $col_search === '' || ! isset( $columns[ $index ] ) ) continue;

            $field = $columns[ $index ]['post_field'];
            if ( $field === 'category' || $field === 'tag' ) {
                $tax_query[] = [
                    'taxonomy' => $field === 'category' ? 'category' : 'post_tag',
                    'field'    => 'name',
                    'terms'    => array_map( 'trim', explode( ',', $col_search ) ),
                ];
            }
        }
        if ( ! empty( $tax_query ) ) {
            $tax_query['relation']       = 'AND';
            $query_args['tax_query']     = $tax_query;
        }

        $wp_query = new WP_Query( $query_args );

        $total_query = new WP_Query( [
            'post_type'      => $settings['post_type'],
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => -1,
        ] );

        $data = [];
        if ( $wp_query->have_posts() ) {
            while ( $wp_query->have_posts() ) {
                $wp_query->the_post();
                $data[] = WTB_Render::get_post_cell_values( $columns );
            }
            wp_reset_postdata();
        }

        return [
            'draw'            => $draw,
            'recordsTotal'    => (int) $total_query->found_posts,
            'recordsFiltered' => (int) $wp_query->found_posts,
            'data'            => $data,
        ];
    }

    /**
     * Public form submission. Accepts three payload shapes:
     *  - cells_data[col_id]           (built-in frontend form)
     *  - fields[{key: {value,title}}] (Elementor webhook JSON)
     *  - flat params matched by label (custom API integrations)
     */
    public static function submit_form_data( WP_REST_Request $request ) {
        global $wpdb;

        $table_id = (int) $request->get_param( 'id' );
        if ( ! WTB_Table_Repository::exists( $table_id ) ) {
            return self::not_found();
        }

        $params = $request->get_params();

        // Honeypot: any value means a bot filled the hidden field.
        if ( ! empty( $params['wtb_website_url'] ) ) {
            return new WP_Error( 'spam_detected', __( 'Spam terdeteksi.', 'wtb-table-builder' ), [ 'status' => 403 ] );
        }

        // Per-IP throttle: one submission per table every 30 seconds.
        $ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $rate_key = 'wtb_rl_' . md5( $ip . '|' . $table_id );

        if ( get_transient( $rate_key ) ) {
            return new WP_Error(
                'rate_limited',
                __( 'Terlalu banyak pengiriman. Silakan coba lagi nanti.', 'wtb-table-builder' ),
                [ 'status' => 429 ]
            );
        }

        $settings = WTB_Table_Repository::get_settings( $table_id );
        if ( empty( $settings['enable_form_submission'] ) ) {
            return new WP_Error( 'form_disabled', __( 'Pengisian form untuk tabel ini tidak diaktifkan.', 'wtb-table-builder' ), [ 'status' => 403 ] );
        }

        $columns = WTB_Table_Repository::get_columns( $table_id );
        if ( empty( $columns ) ) {
            return new WP_Error( 'invalid_table', __( 'Tabel belum memiliki kolom.', 'wtb-table-builder' ), [ 'status' => 400 ] );
        }

        $submitted = self::extract_submitted_cells( $params, $columns );

        $cells_clean = [];
        foreach ( $columns as $col ) {
            $raw                 = (string) ( $submitted[ (string) $col['id'] ] ?? '' );
            $cells_clean[ (string) $col['id'] ] = WTB_Sanitizer::cell_value( $raw, $col['data_type'] );
        }

        $status = ! empty( $settings['form_require_approval'] ) ? 'pending' : 'published';
        $row_id = WTB_Table_Repository::insert_row( $table_id, $cells_clean, $status );

        if ( false === $row_id ) {
            return new WP_Error( 'db_error', __( 'Gagal menyimpan data ke database.', 'wtb-table-builder' ), [ 'status' => 500 ] );
        }

        set_transient( $rate_key, 1, 30 );

        return rest_ensure_response( [
            'success' => true,
            'message' => $status === 'pending'
                ? __( 'Terima kasih! Data Anda telah terkirim dan menunggu persetujuan admin.', 'wtb-table-builder' )
                : __( 'Berhasil! Data Anda telah ditambahkan ke tabel.', 'wtb-table-builder' ),
            'status'  => $status,
        ] );
    }

    /**
     * Normalize any supported payload shape into col_id => raw string.
     *
     * @param array $columns Hydrated column rows (id, label).
     */
    private static function extract_submitted_cells( array $params, array $columns ): array {
        $submitted = [];

        // Shape 1: built-in form posts cells_data[col_id].
        if ( isset( $params['cells_data'] ) && is_array( $params['cells_data'] ) ) {
            foreach ( $params['cells_data'] as $col_id => $value ) {
                $submitted[ (string) $col_id ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
            }
            return $submitted;
        }

        // Shape 2: Elementor webhook {fields: {key: {value, title}}}.
        if ( isset( $params['fields'] ) && is_array( $params['fields'] ) ) {
            foreach ( $params['fields'] as $field_key => $field_data ) {
                if ( ! is_array( $field_data ) ) {
                    $field_data = [ 'value' => $field_data, 'title' => $field_key ];
                }

                $value = $field_data['value'] ?? '';
                $label = $field_data['title'] ?? $field_key;
                if ( is_array( $value ) ) {
                    $value = implode( ', ', $value );
                }

                $matched = self::match_column( (string) $field_key, (string) $label, $columns );
                if ( $matched !== null ) {
                    $submitted[ $matched ] = (string) $value;
                }
            }
            return $submitted;
        }

        // Shape 3: flat params — match by column id or by label text.
        foreach ( $columns as $col ) {
            $col_key = (string) $col['id'];
            if ( isset( $params[ $col_key ] ) ) {
                $submitted[ $col_key ] = is_array( $params[ $col_key ] ) ? implode( ', ', $params[ $col_key ] ) : (string) $params[ $col_key ];
                continue;
            }

            foreach ( $params as $param_key => $param_value ) {
                if ( mb_strtolower( trim( (string) $param_key ) ) === mb_strtolower( trim( $col['label'] ) ) ) {
                    $submitted[ $col_key ] = is_array( $param_value ) ? implode( ', ', $param_value ) : (string) $param_value;
                    break;
                }
            }
        }

        return $submitted;
    }

    /** Find a column id by field key or label (case-insensitive). */
    private static function match_column( string $field_key, string $field_label, array $columns ): ?string {
        $needle_key   = mb_strtolower( trim( $field_key ) );
        $needle_label = mb_strtolower( trim( $field_label ) );

        foreach ( $columns as $col ) {
            $col_key   = (string) $col['id'];
            $col_label = mb_strtolower( trim( $col['label'] ) );

            if ( $col_key === $needle_key || $col_label === $needle_label || $col_label === $needle_key ) {
                return $col_key;
            }
        }

        return null;
    }

    public static function export_csv( WP_REST_Request $request ): void {
        WTB_CSV::export( (int) $request->get_param( 'id' ) );
    }

    public static function import_csv( WP_REST_Request $request ) {
        return WTB_CSV::import( (int) $request->get_param( 'id' ), $request );
    }
}
