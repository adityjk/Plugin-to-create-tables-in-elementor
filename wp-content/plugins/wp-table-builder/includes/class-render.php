<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WTB_Render {

    public static function render_table( int $table_id, array $override_settings = [] ): string {
        global $wpdb;

        $post = get_post( $table_id );
        if ( ! $post || $post->post_type !== 'wtb_table' ) return '';

        $settings_raw = get_post_meta( $table_id, '_wtb_settings', true );
        $settings     = WTB_Sanitizer::table_settings(
            $settings_raw ? (array) json_decode( $settings_raw, true ) : []
        );

        if ( ! empty( $override_settings ) ) {
            $settings = array_merge( $settings, $override_settings );
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
            $col_settings      = $col['settings'] ? (array) json_decode( $col['settings'], true ) : [];
            $col['post_field']     = sanitize_text_field( $col_settings['post_field'] ?? '' );
            $col['image_size']     = sanitize_text_field( $col_settings['image_size'] ?? 'thumbnail' );
            $col['image_custom_w'] = absint( $col_settings['image_custom_w'] ?? 100 );
            $col['image_custom_h'] = absint( $col_settings['image_custom_h'] ?? 100 );
            $col['filter_type']    = sanitize_text_field( $col_settings['filter_type'] ?? '' );
            unset($col['settings']);
            return $col;
        }, $columns_raw );

        if ( empty( $columns ) ) return '<p>' . esc_html__( 'Tabel tidak memiliki kolom.', 'wp-table-builder' ) . '</p>';

        $data_source = $settings['data_source'] ?? 'manual';
        $rows = [];
        $server_side = false;

        if ( $data_source === 'wp_posts' ) {
            $post_type = $settings['post_type'] ?? 'post';
            $limit     = (int) ($settings['posts_limit'] ?? 10);
            
            // Server side mode for WP posts if limit is high or -1
            $server_side = ( $limit === -1 || $limit > (int) $settings['server_side_threshold'] );
            
            if ( ! $server_side ) {
                $query_args = [
                    'post_type'      => $post_type,
                    'posts_per_page' => $limit,
                    'post_status'    => 'publish',
                ];
                
                $wp_query = new WP_Query( $query_args );
                $row_index = 0;
                
                if ( $wp_query->have_posts() ) {
                while ( $wp_query->have_posts() ) {
                    $wp_query->the_post();
                    $post_id = get_the_ID();
                    $cells_data = [];
                    
                    foreach ( $columns as $col ) {
                        $cid = (string) $col['id'];
                        $val = '';
                        $pf  = $col['post_field'];
                        
                        if ( $pf === 'title' ) {
                            $val = get_the_title();
                        } elseif ( $pf === 'content' ) {
                            $val = get_the_content();
                        } elseif ( $pf === 'excerpt' ) {
                            $val = get_the_excerpt();
                        } elseif ( $pf === 'date' ) {
                            $val = get_the_date();
                        } elseif ( $pf === 'author' ) {
                            $val = get_the_author();
                        } elseif ( $pf === 'category' || $pf === 'tag' ) {
                            $tax = $pf === 'category' ? 'category' : 'post_tag';
                            // If custom post type, we might need to get all its taxonomies, but stick to standard for now or get_the_terms
                            $terms = get_the_terms( $post_id, $tax );
                            if ( $terms && ! is_wp_error( $terms ) ) {
                                $names = wp_list_pluck( $terms, 'name' );
                                $val = implode( ', ', $names );
                            }
                        } elseif ( $pf === 'thumbnail' ) {
                            if ( has_post_thumbnail() ) {
                                if ( $col['image_size'] === 'custom' ) {
                                    $img = wp_get_attachment_image_src( get_post_thumbnail_id(), 'full' );
                                    $val = $img ? $img[0] : '';
                                    // Custom resizing on frontend via inline styles or width/height attributes
                                } else {
                                    $img = wp_get_attachment_image_src( get_post_thumbnail_id(), $col['image_size'] );
                                    $val = $img ? $img[0] : '';
                                }
                            }
                        }
                        $cells_data[ $cid ] = $val;
                    }
                    
                    $rows[] = [
                        'id'         => $post_id,
                        'cells_data' => wp_json_encode( $cells_data ),
                        'sort_order' => $row_index,
                    ];
                    $row_index++;
                }
                }
                wp_reset_postdata();
            }
        } else {
            $row_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d AND (status = 'published' OR status IS NULL OR status = '')",
                    $table_id
                )
            );

            $server_side = $row_count > (int) $settings['server_side_threshold'];

            if ( ! $server_side ) {
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, cells_data, sort_order FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d AND (status = 'published' OR status IS NULL OR status = '') ORDER BY sort_order ASC",
                        $table_id
                    ),
                    ARRAY_A
                );
            }
        }

        self::enqueue_frontend_assets( $table_id, $settings, $columns, $server_side );

        $css = self::generate_table_css( $table_id, $settings );

        ob_start();
        echo '<style>' . $css . '</style>';
        echo '<div class="wtb-table-wrap wtb-wrap-' . esc_attr( $table_id ) . '" id="wtb-wrap-' . esc_attr( $table_id ) . '">';

        echo '<div class="wtb-table-scroll">';
        echo '<table class="wtb-table" id="wtb-table-' . esc_attr( $table_id ) . '"';
        echo ' data-table-id="' . esc_attr( $table_id ) . '"';
        echo ' data-server-side="' . ( $server_side ? '1' : '0' ) . '"';
        echo ' data-enable-search="' . ( $settings['enable_search'] ? '1' : '0' ) . '"';
        echo ' data-enable-sort="' . ( $settings['enable_sort'] ? '1' : '0' ) . '"';
        echo ' data-responsive="' . esc_attr( $settings['responsive_mode'] ) . '"';
        if ( isset( $settings['prev_text'] ) )       echo ' data-prev-text="' . esc_attr( $settings['prev_text'] ) . '"';
        if ( isset( $settings['next_text'] ) )       echo ' data-next-text="' . esc_attr( $settings['next_text'] ) . '"';
        if ( ! empty( $settings['prev_icon_html'] ) ) echo ' data-prev-icon-html="' . esc_attr( $settings['prev_icon_html'] ) . '"';
        if ( isset( $settings['pagination_type'] ) ) echo ' data-pagination-type="' . esc_attr( $settings['pagination_type'] ) . '"';
        $show_file_preview = ! empty( $settings['show_file_preview'] );
        echo ' data-show-file-preview="' . ( $show_file_preview ? '1' : '0' ) . '"';
        $auto_refresh = isset( $settings['auto_refresh'] ) ? (bool) $settings['auto_refresh'] : true;
        $auto_refresh_interval = isset( $settings['auto_refresh_interval'] ) ? (int) $settings['auto_refresh_interval'] : 5;
        echo ' data-auto-refresh="' . ( $auto_refresh ? '1' : '0' ) . '"';
        echo ' data-auto-refresh-interval="' . esc_attr( $auto_refresh_interval ) . '"';
        echo '>';

        echo '<thead><tr>';
        $target_col_id = (string) ( $settings['taxonomy_filter_column'] ?? '' );
        foreach ( $columns as $col ) {
            $col_id_str = (string) $col['id'];
            $filter_type = $col['filter_type'] ?? '';
            
            // Legacy fallback for taxonomy filter
            $is_tax_col = ! empty( $settings['enable_taxonomy_filter'] ) &&
                          ( $target_col_id === '' || $target_col_id === $col_id_str );
            
            if ( $is_tax_col && $filter_type === '' ) {
                $filter_type = 'select';
            }

            echo '<th data-col-id="' . esc_attr( $col['id'] ) . '" data-type="' . esc_attr( $col['data_type'] ) . '" class="' . ( $filter_type !== '' ? 'wtb-th-has-filter' : '' ) . '">';
            echo '<div class="wtb-th-inner">';
            echo '<span class="wtb-th-label-text">' . esc_html( $col['label'] ) . '</span>';

            if ( $filter_type === 'select' ) {
                echo '<div class="wtb-header-tax-wrap" onclick="event.stopPropagation();">';
                // Google Sheets style Filter Icon
                echo '<svg class="wtb-tax-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>';
                echo '<select class="wtb-header-tax-select wtb-header-filter wtb-filter-select" data-filter-col-id="' . esc_attr( $col['id'] ) . '" title="' . esc_attr__( 'Filter', 'wp-table-builder' ) . '">';
                echo '<option value="">' . esc_html__( 'Semua', 'wp-table-builder' ) . '</option>';

                if ( ! $server_side ) {
                    $categories = [];
                    foreach ( $rows as $row ) {
                        $cells = $row['cells_data'] ? (array) json_decode( $row['cells_data'], true ) : [];
                        $val_raw = trim( wp_strip_all_tags( $cells[ $col_id_str ] ?? '' ) );
                        if ( $val_raw !== '' ) {
                            $parts = array_map('trim', explode(',', $val_raw));
                            foreach ($parts as $p) {
                                if ( $p !== '' && ! in_array( $p, $categories, true ) ) {
                                    $categories[] = $p;
                                }
                            }
                        }
                    }
                    sort( $categories );
                    foreach ( $categories as $cat ) {
                        echo '<option value="' . esc_attr( $cat ) . '">' . esc_html( $cat ) . '</option>';
                    }
                }
                echo '</select>';
                echo '</div>';
            } elseif ( $filter_type === 'text' ) {
                echo '<div class="wtb-header-tax-wrap wtb-header-text-filter-wrap" onclick="event.stopPropagation();">';
                echo '<svg class="wtb-tax-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
                echo '<input type="text" class="wtb-header-filter wtb-filter-text" data-filter-col-id="' . esc_attr( $col['id'] ) . '" placeholder="' . esc_attr__( 'Cari...', 'wp-table-builder' ) . '">';
                echo '</div>';
            }

            echo '</div>';
            echo '</th>';
        }
        echo '</tr></thead>';

        echo '<tbody>';
        if ( ! $server_side ) {
            foreach ( $rows as $row ) {
                $cells = $row['cells_data'] ? (array) json_decode( $row['cells_data'], true ) : [];
                echo '<tr>';
                foreach ( $columns as $col ) {
                    $key   = (string) $col['id'];
                    $value = $cells[ $key ] ?? '';
                    echo '<td>' . self::render_cell_value( $value, $col['data_type'], $show_file_preview, $col ) . '</td>';
                }
                echo '</tr>';
            }
        }
        echo '</tbody>';

        echo '</table>';
        echo '</div>';

        if ( ! empty( $settings['enable_form_submission'] ) ) {
            echo self::render_form( $table_id );
        }

        echo '</div>';

        return ob_get_clean();
    }

    public static function render_form( int $table_id ): string {
        global $wpdb;

        $post = get_post( $table_id );
        if ( ! $post || $post->post_type !== 'wtb_table' ) return '';

        $settings_raw = get_post_meta( $table_id, '_wtb_settings', true );
        $settings     = WTB_Sanitizer::table_settings(
            $settings_raw ? (array) json_decode( $settings_raw, true ) : []
        );

        $columns = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, label, data_type FROM {$wpdb->prefix}wtb_columns WHERE table_id = %d ORDER BY sort_order ASC",
                $table_id
            ),
            ARRAY_A
        );

        if ( empty( $columns ) ) return '';

        self::enqueue_frontend_assets( $table_id, $settings, $columns, false );

        ob_start();
        echo '<div class="wtb-form-container" id="wtb-form-container-' . esc_attr( $table_id ) . '">';
        echo '<div class="wtb-form-header">';
        echo '<h4 class="wtb-form-title">' . sprintf( esc_html__( 'Form Tambah Data — %s', 'wp-table-builder' ), esc_html( $post->post_title ) ) . '</h4>';
        echo '<p class="wtb-form-subtitle">' . esc_html__( 'Isi formulir di bawah ini untuk menambahkan data baru ke dalam tabel.', 'wp-table-builder' ) . '</p>';
        echo '</div>';
        echo '<form class="wtb-user-submit-form" data-table-id="' . esc_attr( $table_id ) . '" data-rest-url="' . esc_url_raw( rest_url( 'wtb/v1/tables/' . $table_id . '/submit' ) ) . '">';
        echo '<div class="wtb-form-response-msg" style="display:none;"></div>';
        echo '<input type="text" name="wtb_website_url" style="display:none !important;" tabindex="-1" autocomplete="off">'; // Anti-spam Honeypot

        echo '<div class="wtb-form-grid">';
        foreach ( $columns as $col ) {
            $col_id    = (string) $col['id'];
            $label     = esc_html( $col['label'] );
            $data_type = $col['data_type'];

            echo '<div class="wtb-form-field-group">';
            echo '<label class="wtb-form-label" for="wtb-field-' . esc_attr( $table_id . '-' . $col_id ) . '">' . $label . '</label>';

            switch ( $data_type ) {
                case 'number':
                case 'rating':
                    echo '<input type="number" class="wtb-form-input" id="wtb-field-' . esc_attr( $table_id . '-' . $col_id ) . '" name="cells_data[' . esc_attr( $col_id ) . ']" placeholder="' . esc_attr( $label ) . '">';
                    break;
                case 'date':
                    echo '<input type="date" class="wtb-form-input" id="wtb-field-' . esc_attr( $table_id . '-' . $col_id ) . '" name="cells_data[' . esc_attr( $col_id ) . ']">';
                    break;
                case 'richtext':
                    echo '<textarea class="wtb-form-textarea" id="wtb-field-' . esc_attr( $table_id . '-' . $col_id ) . '" name="cells_data[' . esc_attr( $col_id ) . ']" rows="3" placeholder="' . esc_attr( $label ) . '"></textarea>';
                    break;
                default:
                    echo '<input type="text" class="wtb-form-input" id="wtb-field-' . esc_attr( $table_id . '-' . $col_id ) . '" name="cells_data[' . esc_attr( $col_id ) . ']" placeholder="' . esc_attr( $label ) . '">';
                    break;
            }
            echo '</div>';
        }
        echo '</div>';

        echo '<div class="wtb-form-submit-btn-wrap">';
        echo '<button type="submit" class="wtb-form-btn-submit">';
        echo '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:middle;"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
        echo esc_html__( 'Kirim Data', 'wp-table-builder' );
        echo '</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';

        return ob_get_clean();
    }

    private static function render_cell_value( string $value, string $data_type, bool $show_preview = true, array $col = [] ): string {
        if ( $value === '' ) return '—';

        switch ( $data_type ) {
            case 'richtext':
                return wp_kses_post( $value );

            case 'link':
                $url = esc_url( $value );
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" style="color: #4f46e5; text-decoration: underline; font-weight:500;">' . esc_html( $value ) . '</a>';

            case 'image':
                $url = esc_url( $value );
                $style = '';
                if ( ! empty( $col['image_size'] ) && $col['image_size'] === 'custom' ) {
                    $w = ! empty( $col['image_custom_w'] ) ? (int) $col['image_custom_w'] . 'px' : 'auto';
                    $h = ! empty( $col['image_custom_h'] ) ? (int) $col['image_custom_h'] . 'px' : 'auto';
                    $style = ' style="width:' . $w . '; height:' . $h . '; max-width:none; max-height:none;"';
                }
                return '<img src="' . $url . '" alt="" class="wtb-cell-img" loading="lazy"' . $style . '>';

            case 'button':
                return '<span class="wtb-cell-btn">' . esc_html( $value ) . '</span>';

            case 'badge':
                return '<span class="wtb-cell-badge">' . esc_html( $value ) . '</span>';

            case 'rating':
                $rating = min( 5, max( 0, (int) $value ) );
                $stars  = str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating );
                return '<span class="wtb-cell-rating" title="' . esc_attr( $rating . '/5' ) . '">' . $stars . '</span>';

            case 'file':
                $url = esc_url( $value );
                if ( empty( $url ) ) return '—';
                $path     = wp_parse_url( $url, PHP_URL_PATH );
                $filename = $path ? basename( $path ) : __( 'Download File', 'wp-table-builder' );
                $preview_attr = $show_preview ? ' data-preview="1"' : '';

                $html  = '<div class="wtb-cell-file-wrap">';
                $html .= '<a href="' . $url . '" class="wtb-cell-file"' . $preview_attr . ' target="_blank" download rel="noopener noreferrer" title="' . esc_attr( $filename ) . '">';
                $html .= '<svg class="wtb-file-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>';
                $html .= '<span>' . esc_html( $filename ) . '</span>';
                $html .= '</a>';
                if ( $show_preview ) {
                    $html .= '<button type="button" class="wtb-btn-file-preview" data-file-url="' . $url . '" data-file-name="' . esc_attr( $filename ) . '" title="' . esc_attr__( 'Preview File', 'wp-table-builder' ) . '">';
                    $html .= '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
                    $html .= '</button>';
                }
                $html .= '</div>';
                return $html;

            default:
                return esc_html( $value );
        }
    }

    private static function generate_table_css( int $table_id, array $settings ): string {
        $wrap_id      = '.wtb-wrap-' . $table_id;
        $table_id_css = '#wtb-table-' . $table_id;

        $width      = esc_attr( $settings['width'] ?? '100%' );
        $max_width  = esc_attr( $settings['max_width'] ?? '100%' );
        $height     = esc_attr( $settings['height'] ?? 'auto' );
        $max_height = esc_attr( $settings['max_height'] ?? 'none' );
        $align      = esc_attr( $settings['alignment'] ?? 'center' );
        $radius     = absint( $settings['border_radius'] ?? 8 ) . 'px';
        $pad        = absint( $settings['cell_padding'] ?? 8 ) . 'px';
        $bw         = absint( $settings['border_width'] ?? 1 ) . 'px';
        $bc         = esc_attr( $settings['border_color'] ?? '#dddddd' );

        $margin = '12px auto';
        if ( $align === 'left' ) {
            $margin = '12px auto 12px 0';
        } elseif ( $align === 'right' ) {
            $margin = '12px 0 12px auto';
        }

        // box_shadow can be a preset name OR a full CSS string (from Elementor)
        $shadow_raw = $settings['box_shadow'] ?? 'sm';
        $preset_map = [
            'none' => 'none',
            'sm'   => '0 1px 3px 0 rgba(0, 0, 0, 0.05)',
            'md'   => '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1)',
            'lg'   => '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1)',
        ];
        $box_shadow_val = isset( $preset_map[ $shadow_raw ] ) ? $preset_map[ $shadow_raw ] : esc_attr( $shadow_raw );

        $overflow_y = ( $max_height !== 'none' && $max_height !== 'auto' ) ? 'overflow-y: auto;' : '';

        $css = "$wrap_id { ";
        $css .= "width: $width; ";
        $css .= "max-width: $max_width; ";
        $css .= "height: $height; ";
        $css .= "max-height: $max_height; ";
        $css .= "margin: $margin; ";
        $css .= "border-radius: $radius; ";
        $css .= "border: none; ";
        $css .= "box-shadow: $box_shadow_val; ";
        $css .= "overflow: hidden; ";
        $css .= $overflow_y;
        $css .= " } ";

        $css .= "$table_id_css { border-collapse: separate; border-spacing: 0; width: 100%; border-radius: $radius; overflow: hidden; border: $bw solid $bc; }";
        $css .= "$table_id_css th, $table_id_css td { padding: $pad; border-bottom: $bw solid $bc; border-right: $bw solid $bc; }";
        $css .= "$table_id_css th:last-child, $table_id_css td:last-child { border-right: none; }";
        $css .= "$table_id_css thead tr { background: " . esc_attr( $settings['header_bg'] ) . "; color: " . esc_attr( $settings['header_text'] ) . "; }";

        if ( ! empty( $settings['row_stripe'] ) ) {
            $css .= "$table_id_css tbody tr:nth-child(even) { background: " . esc_attr( $settings['row_stripe_color'] ) . "; }";
        }

        return $css;
    }


    private static function enqueue_frontend_assets( int $table_id, array $settings, array $columns, bool $server_side ) {
        static $enqueued = false;

        if ( ! $enqueued ) {
            wp_enqueue_style(
                'datatables',
                'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css',
                [],
                '1.13.6'
            );
            wp_enqueue_script(
                'datatables',
                'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js',
                [ 'jquery' ],
                '1.13.6',
                true
            );
            wp_enqueue_style(
                'wtb-frontend',
                WTB_PLUGIN_URL . 'assets/css/frontend.css',
                [ 'datatables' ],
                WTB_VERSION
            );
            wp_enqueue_script(
                'wtb-frontend',
                WTB_PLUGIN_URL . 'assets/js/frontend.js',
                [ 'jquery', 'datatables' ],
                WTB_VERSION,
                true
            );
            $enqueued = true;
        }

        wp_localize_script( 'wtb-frontend', 'WTB_Table_' . $table_id, [
            'tableId'             => $table_id,
            'serverSide'          => $server_side,
            'restUrl'             => esc_url_raw( rest_url( 'wtb/v1' ) ),
            'nonce'               => wp_create_nonce( 'wp_rest' ),
            'settings'            => $settings,
            'autoRefresh'         => isset( $settings['auto_refresh'] ) ? (bool) $settings['auto_refresh'] : true,
            'autoRefreshInterval' => isset( $settings['auto_refresh_interval'] ) ? (int) $settings['auto_refresh_interval'] : 5,
            'columns'             => array_map( function( $col ) {
                return [ 'id' => $col['id'], 'label' => $col['label'], 'data_type' => $col['data_type'] ];
            }, $columns ),
        ] );
    }
}
