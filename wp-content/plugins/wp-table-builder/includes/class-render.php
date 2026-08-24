<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The single render engine. Shortcode, Gutenberg block, Elementor widget and
 * the public form shortcode all delegate here — rendering logic lives nowhere
 * else (DRY).
 */
class WTB_Render {

    /**
     * Render a full interactive table (DataTables markup + inline CSS).
     *
     * @param array $override_settings Optional per-embed overrides (Elementor
     *                                 widget controls take precedence over saved settings).
     */
    public static function render_table( int $table_id, array $override_settings = [] ): string {
        if ( ! WTB_Table_Repository::exists( $table_id ) ) return '';

        $settings = WTB_Table_Repository::get_settings( $table_id );
        if ( ! empty( $override_settings ) ) {
            $settings = array_merge( $settings, $override_settings );
        }

        $columns = WTB_Table_Repository::get_columns( $table_id );
        if ( empty( $columns ) ) {
            return '<p>' . esc_html__( 'Tabel tidak memiliki kolom.', 'wp-table-builder' ) . '</p>';
        }

        $rows        = [];
        $server_side = false;

        if ( $settings['data_source'] === 'wp_posts' ) {
            // Unlimited or very large limits are served via AJAX instead of
            // one giant WP_Query that could exhaust memory.
            $server_side = $settings['posts_limit'] === -1
                || $settings['posts_limit'] > $settings['server_side_threshold'];

            if ( ! $server_side ) {
                $rows = self::query_post_rows( $settings, $columns );
            }
        } else {
            $published   = WTB_Table_Repository::count_published_rows( $table_id );
            $server_side = $published > $settings['server_side_threshold'];

            if ( ! $server_side ) {
                $rows = WTB_Table_Repository::get_rows( $table_id, true );
            }
        }

        self::enqueue_frontend_assets();
        self::localize_table( $table_id, $settings, $columns, $server_side );

        ob_start();

        echo '<style>' . self::generate_table_css( $table_id, $settings ) . '</style>';
        echo '<div class="wtb-table-wrap wtb-wrap-' . esc_attr( $table_id ) . '" id="wtb-wrap-' . esc_attr( $table_id ) . '">';
        echo '<div class="wtb-table-scroll">';

        echo '<table class="wtb-table" id="wtb-table-' . esc_attr( $table_id ) . '"';
        echo ' data-table-id="' . esc_attr( $table_id ) . '"';
        echo ' data-server-side="' . ( $server_side ? '1' : '0' ) . '"';
        echo ' data-enable-search="' . ( $settings['enable_search'] ? '1' : '0' ) . '"';
        echo ' data-enable-sort="' . ( $settings['enable_sort'] ? '1' : '0' ) . '"';
        echo ' data-responsive="' . esc_attr( $settings['responsive_mode'] ) . '"';

        // Elementor pagination overrides (absent for shortcode/block embeds).
        foreach ( [ 'prev_text', 'next_text', 'prev_icon_html', 'next_icon_html', 'pagination_type' ] as $key ) {
            if ( isset( $settings[ $key ] ) && $settings[ $key ] !== '' ) {
                echo ' data-' . str_replace( '_', '-', $key ) . '="' . esc_attr( $settings[ $key ] ) . '"';
            }
        }

        echo ' data-show-file-preview="' . ( ! empty( $settings['show_file_preview'] ) ? '1' : '0' ) . '"';
        echo ' data-auto-refresh="' . ( ! empty( $settings['auto_refresh'] ?? true ) ? '1' : '0' ) . '"';
        echo ' data-auto-refresh-interval="' . esc_attr( (int) ( $settings['auto_refresh_interval'] ?? 5 ) ) . '"';
        echo '>';

        echo '<thead><tr>';
        foreach ( $columns as $col ) {
            self::render_column_header( $col, $settings, $rows, $server_side );
        }
        echo '</tr></thead>';

        echo '<tbody>';
        if ( ! $server_side ) {
            foreach ( $rows as $row ) {
                echo '<tr>';
                foreach ( $columns as $col ) {
                    $value = $row['cells_data'][ (string) $col['id'] ] ?? '';
                    echo '<td>' . self::render_cell_value( (string) $value, $col['data_type'], ! empty( $settings['show_file_preview'] ), $col ) . '</td>';
                }
                echo '</tr>';
            }
        }
        echo '</tbody>';

        echo '</table></div>';

        if ( ! empty( $settings['enable_form_submission'] ) ) {
            echo self::render_form( $table_id );
        }

        echo '</div>';

        return ob_get_clean();
    }

    /**
     * One <th>: label plus optional per-column filter control
     * (select dropdown or text input, Google-Sheets style).
     */
    private static function render_column_header( array $col, array $settings, array $rows, bool $server_side ): void {
        $filter_type = $col['filter_type'];
        $col_key     = (string) $col['id'];

        // Legacy fallback: the old "taxonomy filter" setting turns the chosen
        // column into a select filter even though filter_type was never saved.
        if ( $filter_type === '' && ! empty( $settings['enable_taxonomy_filter'] ) ) {
            $target = (string) ( $settings['taxonomy_filter_column'] ?? '' );
            if ( $target === '' || $target === $col_key ) {
                $filter_type = 'select';
            }
        }

        $th_class = $filter_type !== '' ? ' class="wtb-th-has-filter"' : '';
        echo '<th data-col-id="' . esc_attr( $col['id'] ) . '" data-type="' . esc_attr( $col['data_type'] ) . '"' . $th_class . '>';
        echo '<div class="wtb-th-inner">';
        echo '<span class="wtb-th-label-text">' . esc_html( $col['label'] ) . '</span>';

        if ( $filter_type === 'select' ) {
            echo '<div class="wtb-header-tax-wrap" onclick="event.stopPropagation();">';
            echo '<svg class="wtb-tax-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>';
            echo '<select class="wtb-header-tax-select wtb-header-filter wtb-filter-select" data-filter-col-id="' . esc_attr( $col['id'] ) . '" title="' . esc_attr__( 'Filter', 'wp-table-builder' ) . '">';
            echo '<option value="">' . esc_html__( 'Semua', 'wp-table-builder' ) . '</option>';

            // Client-side only: options are derived from actual cell values,
            // split on commas so "a, b" style cells match individual terms.
            if ( ! $server_side ) {
                $options = [];
                foreach ( $rows as $row ) {
                    $raw = trim( wp_strip_all_tags( (string) ( $row['cells_data'][ $col_key ] ?? '' ) ) );
                    foreach ( array_map( 'trim', explode( ',', $raw ) ) as $part ) {
                        if ( $part !== '' && ! in_array( $part, $options, true ) ) {
                            $options[] = $part;
                        }
                    }
                }
                sort( $options );
                foreach ( $options as $option ) {
                    echo '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
                }
            }

            echo '</select></div>';
        } elseif ( $filter_type === 'text' ) {
            echo '<div class="wtb-header-tax-wrap wtb-header-text-filter-wrap" onclick="event.stopPropagation();">';
            echo '<svg class="wtb-tax-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
            echo '<input type="text" class="wtb-header-filter wtb-filter-text" data-filter-col-id="' . esc_attr( $col['id'] ) . '" placeholder="' . esc_attr__( 'Cari...', 'wp-table-builder' ) . '">';
            echo '</div>';
        }

        echo '</div></th>';
    }

    /**
     * Map the current post in a WP_Query loop to cell values keyed by column id.
     * Shared by the client-side render path and the REST server-side endpoint.
     */
    public static function get_post_cell_values( array $columns ): array {
        $post_id = get_the_ID();
        $cells   = [];

        foreach ( $columns as $col ) {
            $field = $col['post_field'];
            $value = '';

            switch ( $field ) {
                case 'title':
                    $value = get_the_title();
                    break;
                case 'content':
                    $value = get_the_content();
                    break;
                case 'excerpt':
                    $value = get_the_excerpt();
                    break;
                case 'date':
                    $value = get_the_date();
                    break;
                case 'author':
                    $value = get_the_author();
                    break;
                case 'category':
                case 'tag':
                    $terms = get_the_terms( $post_id, $field === 'category' ? 'category' : 'post_tag' );
                    if ( $terms && ! is_wp_error( $terms ) ) {
                        $value = implode( ', ', wp_list_pluck( $terms, 'name' ) );
                    }
                    break;
                case 'thumbnail':
                    if ( has_post_thumbnail() ) {
                        // 'custom' sizes fall back to full; display size is CSS-driven.
                        $size  = $col['image_size'] === 'custom' ? 'full' : $col['image_size'];
                        $image = wp_get_attachment_image_src( get_post_thumbnail_id(), $size );
                        $value = $image ? $image[0] : '';
                    }
                    break;
            }

            $cells[ (string) $col['id'] ] = $value;
        }

        return $cells;
    }

    /** Client-side WP Posts source: run the query and build row arrays. */
    private static function query_post_rows( array $settings, array $columns ): array {
        $query = new WP_Query( [
            'post_type'      => $settings['post_type'],
            'posts_per_page' => $settings['posts_limit'],
            'post_status'    => 'publish',
        ] );

        $rows = [];
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $rows[] = [
                    'id'         => get_the_ID(),
                    'cells_data' => wp_json_encode( self::get_post_cell_values( $columns ) ),
                ];
            }
            wp_reset_postdata();
        }

        return $rows;
    }

    /**
     * Render the public visitor-submission form for a table.
     */
    public static function render_form( int $table_id ): string {
        if ( ! WTB_Table_Repository::exists( $table_id ) ) return '';

        $post    = get_post( $table_id );
        $columns = WTB_Table_Repository::get_columns( $table_id );
        if ( empty( $columns ) ) return '';

        self::enqueue_frontend_assets();

        ob_start();

        echo '<div class="wtb-form-container" id="wtb-form-container-' . esc_attr( $table_id ) . '">';
        echo '<div class="wtb-form-header">';
        echo '<h4 class="wtb-form-title">' . sprintf( esc_html__( 'Form Tambah Data — %s', 'wp-table-builder' ), esc_html( $post->post_title ) ) . '</h4>';
        echo '<p class="wtb-form-subtitle">' . esc_html__( 'Isi formulir di bawah ini untuk menambahkan data baru ke dalam tabel.', 'wp-table-builder' ) . '</p>';
        echo '</div>';

        echo '<form class="wtb-user-submit-form" data-table-id="' . esc_attr( $table_id ) . '" data-rest-url="' . esc_url_raw( rest_url( 'wtb/v1/tables/' . $table_id . '/submit' ) ) . '">';
        echo '<div class="wtb-form-response-msg" style="display:none;"></div>';

        // Honeypot: hidden from humans and screen-reader tab order, filled only by bots.
        echo '<input type="text" name="wtb_website_url" style="display:none !important;" tabindex="-1" autocomplete="off">';

        echo '<div class="wtb-form-grid">';
        foreach ( $columns as $col ) {
            $field_id = 'wtb-field-' . $table_id . '-' . $col['id'];
            $name     = 'cells_data[' . $col['id'] . ']';

            echo '<div class="wtb-form-field-group">';
            echo '<label class="wtb-form-label" for="' . esc_attr( $field_id ) . '">' . esc_html( $col['label'] ) . '</label>';

            switch ( $col['data_type'] ) {
                case 'number':
                case 'rating':
                    echo '<input type="number" class="wtb-form-input" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $col['label'] ) . '">';
                    break;
                case 'date':
                    echo '<input type="date" class="wtb-form-input" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '">';
                    break;
                case 'richtext':
                    echo '<textarea class="wtb-form-textarea" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" rows="3" placeholder="' . esc_attr( $col['label'] ) . '"></textarea>';
                    break;
                default:
                    echo '<input type="text" class="wtb-form-input" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $col['label'] ) . '">';
            }

            echo '</div>';
        }
        echo '</div>';

        echo '<div class="wtb-form-submit-btn-wrap">';
        echo '<button type="submit" class="wtb-form-btn-submit">';
        echo '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:middle;"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
        echo esc_html__( 'Kirim Data', 'wp-table-builder' );
        echo '</button></div>';

        echo '</form></div>';

        return ob_get_clean();
    }

    /**
     * HTML for one table cell, depending on the column's data type.
     */
    private static function render_cell_value( string $value, string $data_type, bool $show_preview, array $col ): string {
        if ( $value === '' ) return '—';

        switch ( $data_type ) {
            case 'richtext':
                return wp_kses_post( $value );

            case 'link':
                return '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener noreferrer" style="color:#4f46e5;text-decoration:underline;font-weight:500;">' . esc_html( $value ) . '</a>';

            case 'image':
                $style = '';
                if ( $col['image_size'] === 'custom' ) {
                    $w     = $col['image_custom_w'] ? (int) $col['image_custom_w'] . 'px' : 'auto';
                    $h     = $col['image_custom_h'] ? (int) $col['image_custom_h'] . 'px' : 'auto';
                    $style = ' style="width:' . esc_attr( $w ) . ';height:' . esc_attr( $h ) . ';max-width:none;max-height:none;"';
                }
                return '<img src="' . esc_url( $value ) . '" alt="" class="wtb-cell-img" loading="lazy"' . $style . '>';

            case 'button':
                return '<span class="wtb-cell-btn">' . esc_html( $value ) . '</span>';

            case 'badge':
                return '<span class="wtb-cell-badge">' . esc_html( $value ) . '</span>';

            case 'rating':
                $rating = min( 5, max( 0, (int) $value ) );
                $stars  = str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating );
                return '<span class="wtb-cell-rating" title="' . esc_attr( $rating . '/5' ) . '">' . $stars . '</span>';

            case 'file':
                $url      = esc_url( $value );
                $path     = wp_parse_url( $url, PHP_URL_PATH );
                $filename = $path ? basename( $path ) : __( 'Download File', 'wp-table-builder' );

                $html  = '<div class="wtb-cell-file-wrap">';
                $html .= '<a href="' . $url . '" class="wtb-cell-file"' . ( $show_preview ? ' data-preview="1"' : '' ) . ' target="_blank" download rel="noopener noreferrer" title="' . esc_attr( $filename ) . '">';
                $html .= '<svg class="wtb-file-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>';
                $html .= '<span>' . esc_html( $filename ) . '</span></a>';

                if ( $show_preview ) {
                    $html .= '<button type="button" class="wtb-btn-file-preview" data-file-url="' . $url . '" data-file-name="' . esc_attr( $filename ) . '" title="' . esc_attr__( 'Preview File', 'wp-table-builder' ) . '">';
                    $html .= '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
                    $html .= '</button>';
                }

                return $html . '</div>';

            default:
                return esc_html( $value );
        }
    }

    /**
     * Per-table inline CSS generated from sanitized settings.
     * box_shadow accepts a preset name OR a raw CSS value (Elementor overrides).
     */
    private static function generate_table_css( int $table_id, array $settings ): string {
        $wrap = '.wtb-wrap-' . $table_id;
        $tbl  = '#wtb-table-' . $table_id;

        $width      = esc_attr( $settings['width'] );
        $max_width  = esc_attr( $settings['max_width'] );
        $height     = esc_attr( $settings['height'] );
        $max_height = esc_attr( $settings['max_height'] );
        $radius     = absint( $settings['border_radius'] ) . 'px';
        $pad        = absint( $settings['cell_padding'] ) . 'px';
        $bw         = absint( $settings['border_width'] ) . 'px';
        $bc         = esc_attr( $settings['border_color'] );

        $margins = [
            'left'  => '12px auto 12px 0',
            'right' => '12px 0 12px auto',
        ];
        $margin = $margins[ $settings['alignment'] ] ?? '12px auto';

        $presets = [
            'none' => 'none',
            'sm'   => '0 1px 3px 0 rgba(0, 0, 0, 0.05)',
            'md'   => '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1)',
            'lg'   => '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1)',
        ];
        $shadow = $presets[ $settings['box_shadow'] ] ?? esc_attr( $settings['box_shadow'] );

        $overflow_y = ( $max_height !== 'none' && $max_height !== 'auto' ) ? 'overflow-y:auto;' : '';

        $css  = "$wrap { width:$width; max-width:$max_width; height:$height; max-height:$max_height; margin:$margin; border-radius:$radius; border:none; box-shadow:$shadow; overflow:hidden; $overflow_y } ";
        $css .= "$tbl { border-collapse:separate; border-spacing:0; width:100%; border-radius:$radius; overflow:hidden; border:$bw solid $bc; }";
        $css .= "$tbl th, $tbl td { padding:$pad; border-bottom:$bw solid $bc; border-right:$bw solid $bc; }";
        $css .= "$tbl th:last-child, $tbl td:last-child { border-right:none; }";
        $css .= "$tbl thead tr { background:" . esc_attr( $settings['header_bg'] ) . '; color:' . esc_attr( $settings['header_text'] ) . '; }';

        if ( ! empty( $settings['row_stripe'] ) ) {
            $css .= "$tbl tbody tr:nth-child(even) { background:" . esc_attr( $settings['row_stripe_color'] ) . '; }';
        }

        return $css;
    }

    /**
     * Enqueue DataTables + plugin frontend assets (once per page load).
     * Public so the Elementor preview iframe can reuse it.
     */
    public static function enqueue_frontend_assets(): void {
        static $done = false;
        if ( $done ) return;
        $done = true;

        wp_enqueue_style( 'datatables', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', [], '1.13.6' );
        wp_enqueue_script( 'datatables', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', [ 'jquery' ], '1.13.6', true );
        wp_enqueue_style( 'wtb-frontend', WTB_PLUGIN_URL . 'assets/css/frontend.css', [ 'datatables' ], WTB_VERSION );
        wp_enqueue_script( 'wtb-frontend', WTB_PLUGIN_URL . 'assets/js/frontend.js', [ 'jquery', 'datatables' ], WTB_VERSION, true );
    }

    /**
     * Per-table JS config consumed by frontend.js (object name WTB_Table_{id}).
     */
    private static function localize_table( int $table_id, array $settings, array $columns, bool $server_side ): void {
        wp_localize_script( 'wtb-frontend', 'WTB_Table_' . $table_id, [
            'tableId'             => $table_id,
            'serverSide'          => $server_side,
            'restUrl'             => esc_url_raw( rest_url( 'wtb/v1' ) ),
            'nonce'               => wp_create_nonce( 'wp_rest' ),
            'settings'            => $settings,
            'autoRefresh'         => ! empty( $settings['auto_refresh'] ?? true ),
            'autoRefreshInterval' => (int) ( $settings['auto_refresh_interval'] ?? 5 ),
            'columns'             => array_map( static fn( array $col ) => [
                'id'        => $col['id'],
                'label'     => $col['label'],
                'data_type' => $col['data_type'],
            ], $columns ),
        ] );
    }
}
