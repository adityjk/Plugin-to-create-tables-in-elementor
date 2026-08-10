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

        register_rest_route( self::NAMESPACE, '/tables/(?P<id>\d+)/submit', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'submit_form_data' ],
            'permission_callback' => '__return_true',
            'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
        ] );

        register_rest_route( self::NAMESPACE, '/tables/(?P<id>\d+)/export_csv', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'export_csv' ],
            'permission_callback' => [ __CLASS__, 'admin_permission' ],
            'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
        ] );

        register_rest_route( self::NAMESPACE, '/tables/(?P<id>\d+)/import_csv', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'import_csv' ],
            'permission_callback' => [ __CLASS__, 'admin_permission' ],
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

        $columns_raw = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, label, data_type, settings, sort_order FROM {$wpdb->prefix}wtb_columns WHERE table_id = %d ORDER BY sort_order ASC",
                $table_id
            ),
            ARRAY_A
        );

        $columns = array_map( function( $col ) {
            $col['id']         = (int) $col['id'];
            $col['sort_order'] = (int) $col['sort_order'];
            $settings          = $col['settings'] ? (array) json_decode( $col['settings'], true ) : [];
            $col['post_field']     = sanitize_text_field( $settings['post_field'] ?? '' );
            $col['image_size']     = sanitize_text_field( $settings['image_size'] ?? 'thumbnail' );
            $col['image_custom_w'] = absint( $settings['image_custom_w'] ?? 100 );
            $col['image_custom_h'] = absint( $settings['image_custom_h'] ?? 100 );
            unset($col['settings']);
            return $col;
        }, $columns_raw );

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
            $col_settings = [
                'post_field'     => sanitize_text_field( $col['post_field'] ?? '' ),
                'image_size'     => sanitize_text_field( $col['image_size'] ?? 'thumbnail' ),
                'image_custom_w' => absint( $col['image_custom_w'] ?? 100 ),
                'image_custom_h' => absint( $col['image_custom_h'] ?? 100 ),
            ];
            $settings_json = wp_json_encode( $col_settings );

            if ( $col_id > 0 ) {
                $wpdb->update(
                    $wpdb->prefix . 'wtb_columns',
                    [ 'label' => $label, 'data_type' => $data_type, 'settings' => $settings_json, 'sort_order' => $index ],
                    [ 'id' => $col_id, 'table_id' => $table_id ],
                    [ '%s', '%s', '%s', '%d' ],
                    [ '%d', '%d' ]
                );
                $keep_column_ids[] = $col_id;
            } else {
                $wpdb->insert(
                    $wpdb->prefix . 'wtb_columns',
                    [ 'table_id' => $table_id, 'label' => $label, 'data_type' => $data_type, 'settings' => $settings_json, 'sort_order' => $index ],
                    [ '%d', '%s', '%s', '%s', '%d' ]
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

        $draw    = absint( $request->get_param( 'draw' )   ?? 1 );
        $start   = absint( $request->get_param( 'start' )  ?? 0 );
        $length  = absint( $request->get_param( 'length' ) ?? 10 );
        $search  = sanitize_text_field( $request->get_param( 'search' )['value'] ?? ( $request->get_param( 'search' ) ?? '' ) );
        $req_cols = $request->get_param( 'columns' ) ?: [];

        $settings_raw = get_post_meta( $table_id, '_wtb_settings', true );
        $settings     = WTB_Sanitizer::table_settings(
            $settings_raw ? (array) json_decode( $settings_raw, true ) : []
        );

        $data_source = $settings['data_source'] ?? 'manual';
        $data = [];
        $total = 0;
        $filtered = 0;

        $columns = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, label, data_type, settings FROM {$wpdb->prefix}wtb_columns WHERE table_id = %d ORDER BY sort_order ASC", $table_id
        ), ARRAY_A );

        if ( $data_source === 'wp_posts' ) {
            $post_type = $settings['post_type'] ?? 'post';
            
            $query_args = [
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => $length,
                'offset'         => $start,
                's'              => $search,
            ];

            // Advanced Column Filter for WP Posts (Taxonomy only for now due to WP_Query limits without meta)
            $tax_query = [];
            foreach ( $req_cols as $index => $col_req ) {
                if ( ! empty( $col_req['search']['value'] ) && isset( $columns[$index] ) ) {
                    $search_val = sanitize_text_field( $col_req['search']['value'] );
                    $col_data = $columns[$index];
                    $c_settings = $col_data['settings'] ? json_decode( $col_data['settings'], true ) : [];
                    $pf = $c_settings['post_field'] ?? '';
                    
                    if ( $pf === 'category' || $pf === 'tag' ) {
                        $tax = $pf === 'category' ? 'category' : 'post_tag';
                        $tax_query[] = [
                            'taxonomy' => $tax,
                            'field'    => 'name',
                            'terms'    => explode( ',', $search_val ),
                        ];
                    } elseif ( $pf === 'title' ) {
                        // Title search is tricky to stack with global 's', we'll rely on global 's' for title
                    }
                }
            }
            if ( ! empty( $tax_query ) ) {
                $tax_query['relation'] = 'AND';
                $query_args['tax_query'] = $tax_query;
            }

            $wp_query = new WP_Query( $query_args );
            
            $total_query = new WP_Query([
                'post_type' => $post_type,
                'post_status' => 'publish',
                'fields' => 'ids',
                'posts_per_page' => -1
            ]);
            $total = $total_query->found_posts;
            $filtered = $wp_query->found_posts;

            if ( $wp_query->have_posts() ) {
                while ( $wp_query->have_posts() ) {
                    $wp_query->the_post();
                    $post_id = get_the_ID();
                    $cells_data = [];
                    foreach ( $columns as $col ) {
                        $cid = (string) $col['id'];
                        $val = '';
                        $c_settings = $col['settings'] ? json_decode( $col['settings'], true ) : [];
                        $pf  = $c_settings['post_field'] ?? '';

                        if ( $pf === 'title' ) $val = get_the_title();
                        elseif ( $pf === 'content' ) $val = get_the_content();
                        elseif ( $pf === 'excerpt' ) $val = get_the_excerpt();
                        elseif ( $pf === 'date' ) $val = get_the_date();
                        elseif ( $pf === 'author' ) $val = get_the_author();
                        elseif ( $pf === 'category' || $pf === 'tag' ) {
                            $tax = $pf === 'category' ? 'category' : 'post_tag';
                            $terms = get_the_terms( $post_id, $tax );
                            if ( $terms && ! is_wp_error( $terms ) ) {
                                $names = wp_list_pluck( $terms, 'name' );
                                $val = implode( ', ', $names );
                            }
                        } elseif ( $pf === 'thumbnail' ) {
                            if ( has_post_thumbnail() ) {
                                $isize = $c_settings['image_size'] ?? 'thumbnail';
                                $img = wp_get_attachment_image_src( get_post_thumbnail_id(), $isize === 'custom' ? 'full' : $isize );
                                $val = $img ? $img[0] : '';
                            }
                        }
                        $cells_data[ $cid ] = $val;
                    }
                    $data[] = $cells_data;
                }
                wp_reset_postdata();
            }

        } else {
            // Manual Data Source
            $total = (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d AND (status = 'published' OR status IS NULL OR status = '')", $table_id )
            );

            $where = $wpdb->prepare( "WHERE table_id = %d AND (status = 'published' OR status IS NULL OR status = '')", $table_id );

            // Global Search
            if ( $search ) {
                $like = '%' . $wpdb->esc_like( $search ) . '%';
                $where .= $wpdb->prepare( ' AND cells_data LIKE %s', $like );
            }

            // Column-specific advanced search
            foreach ( $req_cols as $index => $col_req ) {
                if ( ! empty( $col_req['search']['value'] ) && isset( $columns[$index] ) ) {
                    $search_val = sanitize_text_field( $col_req['search']['value'] );
                    $col_id = $columns[$index]['id'];
                    $like = '%' . $wpdb->esc_like( $search_val ) . '%';
                    
                    // Use JSON_EXTRACT to search specific column value
                    // MySQL 5.7+ required. If not available, fallback to basic LIKE.
                    $where .= $wpdb->prepare( " AND JSON_UNQUOTE(JSON_EXTRACT(cells_data, '$.\"%d\"')) LIKE %s", $col_id, $like );
                }
            }

            $filtered = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wtb_rows $where" );

            $rows_raw = $wpdb->get_results(
                "SELECT cells_data FROM {$wpdb->prefix}wtb_rows $where ORDER BY sort_order ASC LIMIT $length OFFSET $start", ARRAY_A
            );

            $data = array_map( function( $row ) {
                return $row['cells_data'] ? (array) json_decode( $row['cells_data'], true ) : [];
            }, $rows_raw );
        }

        return rest_ensure_response( [
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ] );
    }

    public static function submit_form_data( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        global $wpdb;

        $table_id = (int) $request->get_param( 'id' );
        $post     = get_post( $table_id );

        if ( ! $post || $post->post_type !== 'wtb_table' ) {
            return new WP_Error( 'not_found', __( 'Tabel tidak ditemukan.', 'wp-table-builder' ), [ 'status' => 404 ] );
        }

        $params = $request->get_params();
        
        // Anti-spam Honeypot Check
        if ( ! empty( $params['wtb_website_url'] ) ) {
            return new WP_Error( 'spam_detected', __( 'Spam terdeteksi.', 'wp-table-builder' ), [ 'status' => 403 ] );
        }

        $settings_raw = get_post_meta( $table_id, '_wtb_settings', true );
        $settings     = WTB_Sanitizer::table_settings(
            $settings_raw ? (array) json_decode( $settings_raw, true ) : []
        );

        if ( empty( $settings['enable_form_submission'] ) ) {
            return new WP_Error( 'form_disabled', __( 'Pengisian form untuk tabel ini tidak diaktifkan.', 'wp-table-builder' ), [ 'status' => 403 ] );
        }

        $columns = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, label, data_type FROM {$wpdb->prefix}wtb_columns WHERE table_id = %d ORDER BY sort_order ASC",
                $table_id
            ),
            ARRAY_A
        );

        if ( empty( $columns ) ) {
            return new WP_Error( 'invalid_table', __( 'Tabel belum memiliki kolom.', 'wp-table-builder' ), [ 'status' => 400 ] );
        }

        $params    = $request->get_params();
        $submitted = [];

        if ( isset( $params['cells_data'] ) && is_array( $params['cells_data'] ) ) {
            $submitted = $params['cells_data'];
        } elseif ( isset( $params['fields'] ) && is_array( $params['fields'] ) ) {
            // Elementor Form Webhook JSON structure
            foreach ( $params['fields'] as $field_key => $field_data ) {
                $val   = is_array( $field_data ) ? ( $field_data['value'] ?? '' ) : $field_data;
                $label = is_array( $field_data ) ? ( $field_data['title'] ?? $field_key ) : $field_key;

                if ( is_array( $val ) ) {
                    $val = implode( ', ', $val );
                }

                foreach ( $columns as $col ) {
                    $col_id    = (string) $col['id'];
                    $col_label = mb_strtolower( trim( $col['label'] ) );
                    $field_lbl = mb_strtolower( trim( (string) $label ) );
                    $field_id  = mb_strtolower( trim( (string) $field_key ) );

                    if ( $col_id === (string) $field_key || $col_label === $field_lbl || $col_label === $field_id ) {
                        $submitted[ $col_id ] = (string) $val;
                    }
                }
            }
        } else {
            // Fallback for custom API / Webhook form parameter mapping
            foreach ( $columns as $col ) {
                $col_id    = (string) $col['id'];
                $col_label = mb_strtolower( trim( $col['label'] ) );

                if ( isset( $params[ $col_id ] ) ) {
                    $submitted[ $col_id ] = is_array( $params[ $col_id ] ) ? implode( ', ', $params[ $col_id ] ) : (string) $params[ $col_id ];
                } else {
                    foreach ( $params as $pk => $pv ) {
                        if ( mb_strtolower( trim( $pk ) ) === $col_label ) {
                            $submitted[ $col_id ] = is_array( $pv ) ? implode( ', ', $pv ) : (string) $pv;
                            break;
                        }
                    }
                }
            }
        }

        $cells_clean = [];
        foreach ( $columns as $col ) {
            $key      = (string) $col['id'];
            $raw_val  = isset( $submitted[ $key ] ) ? (string) $submitted[ $key ] : '';
            $cells_clean[ $key ] = WTB_Sanitizer::cell_value( $raw_val, $col['data_type'] );
        }

        $max_order = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT MAX(sort_order) FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d", $table_id )
        );

        $status = ! empty( $settings['form_require_approval'] ) ? 'pending' : 'published';

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'wtb_rows',
            [
                'table_id'   => $table_id,
                'cells_data' => wp_json_encode( $cells_clean ),
                'sort_order' => $max_order + 1,
                'status'     => $status,
            ],
            [ '%d', '%s', '%d', '%s' ]
        );

        if ( false === $inserted ) {
            return new WP_Error( 'db_error', __( 'Gagal menyimpan data ke database.', 'wp-table-builder' ), [ 'status' => 500 ] );
        }

        $message = $status === 'pending'
            ? __( 'Terima kasih! Data Anda telah terkirim dan menunggu persetujuan admin.', 'wp-table-builder' )
            : __( 'Berhasil! Data Anda telah ditambahkan ke tabel.', 'wp-table-builder' );

        return rest_ensure_response( [
            'success' => true,
            'message' => $message,
            'status'  => $status,
        ] );
    }

    public static function export_csv( WP_REST_Request $request ) {
        global $wpdb;
        $table_id = (int) $request->get_param( 'id' );
        $post     = get_post( $table_id );

        if ( ! $post || $post->post_type !== 'wtb_table' ) {
            wp_die( 'Tabel tidak ditemukan.' );
        }

        $columns = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, label, data_type FROM {$wpdb->prefix}wtb_columns WHERE table_id = %d ORDER BY sort_order ASC", $table_id
        ), ARRAY_A );

        if ( empty( $columns ) ) {
            wp_die( 'Tabel kosong.' );
        }

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT cells_data FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d ORDER BY sort_order ASC", $table_id
        ), ARRAY_A );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="table-' . $table_id . '.csv"' );
        
        $output = fopen( 'php://output', 'w' );
        fputs( $output, "\xEF\xBB\xBF" ); // BOM for UTF-8 Excel compatibility
        
        $header_row = [];
        foreach ( $columns as $col ) {
            $header_row[] = $col['label'];
        }
        fputcsv( $output, $header_row );

        foreach ( $rows as $row ) {
            $cells = $row['cells_data'] ? (array) json_decode( $row['cells_data'], true ) : [];
            $data_row = [];
            foreach ( $columns as $col ) {
                $data_row[] = isset( $cells[ $col['id'] ] ) ? $cells[ $col['id'] ] : '';
            }
            fputcsv( $output, $data_row );
        }
        
        fclose( $output );
        exit;
    }

    public static function import_csv( WP_REST_Request $request ) {
        global $wpdb;
        $table_id = (int) $request->get_param( 'id' );

        $files = $request->get_file_params();
        if ( empty( $files['csv_file'] ) || $files['csv_file']['error'] !== UPLOAD_ERR_OK ) {
            return new WP_Error( 'upload_error', 'Gagal mengunggah file.', [ 'status' => 400 ] );
        }

        $file_path = $files['csv_file']['tmp_name'];
        if ( ! is_readable( $file_path ) ) {
            return new WP_Error( 'file_error', 'File tidak dapat dibaca.', [ 'status' => 400 ] );
        }

        $handle = fopen( $file_path, 'r' );
        if ( false === $handle ) {
            return new WP_Error( 'file_error', 'Gagal membuka file.', [ 'status' => 500 ] );
        }

        // BOM removal
        $bom = fread( $handle, 3 );
        if ( $bom !== "\xEF\xBB\xBF" ) {
            rewind( $handle );
        }

        $header = fgetcsv( $handle );
        if ( ! $header ) {
            fclose( $handle );
            return new WP_Error( 'invalid_csv', 'CSV tidak memiliki header valid.', [ 'status' => 400 ] );
        }

        $columns = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, label, data_type FROM {$wpdb->prefix}wtb_columns WHERE table_id = %d ORDER BY sort_order ASC", $table_id
        ), ARRAY_A );
        
        $col_map = []; // Maps header index to col_id
        foreach ( $header as $index => $col_label ) {
            $col_label = trim( $col_label );
            foreach ( $columns as $col ) {
                if ( mb_strtolower( trim( $col['label'] ) ) === mb_strtolower( $col_label ) ) {
                    $col_map[ $index ] = [
                        'id' => $col['id'],
                        'type' => $col['data_type']
                    ];
                    break;
                }
            }
        }

        $max_order = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(sort_order) FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d", $table_id ) );

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $cells_clean = [];
            foreach ( $row as $index => $value ) {
                if ( isset( $col_map[ $index ] ) ) {
                    $cid = $col_map[ $index ]['id'];
                    $ctype = $col_map[ $index ]['type'];
                    $cells_clean[ $cid ] = WTB_Sanitizer::cell_value( $value, $ctype );
                }
            }
            if ( ! empty( $cells_clean ) ) {
                $max_order++;
                $wpdb->insert( $wpdb->prefix . 'wtb_rows', [
                    'table_id'   => $table_id,
                    'cells_data' => wp_json_encode( $cells_clean ),
                    'sort_order' => $max_order,
                    'status'     => 'published'
                ], [ '%d', '%s', '%d', '%s' ] );
            }
        }
        
        fclose( $handle );
        return rest_ensure_response( [ 'success' => true ] );
    }
}
