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

        $columns = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, label, data_type, sort_order FROM {$wpdb->prefix}wtb_columns WHERE table_id = %d ORDER BY sort_order ASC",
                $table_id
            ),
            ARRAY_A
        );

        if ( empty( $columns ) ) return '<p>' . esc_html__( 'Tabel tidak memiliki kolom.', 'wp-table-builder' ) . '</p>';

        $row_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d",
                $table_id
            )
        );

        $server_side = $row_count > (int) $settings['server_side_threshold'];

        $rows = [];
        if ( ! $server_side ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, cells_data, sort_order FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d ORDER BY sort_order ASC",
                    $table_id
                ),
                ARRAY_A
            );
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
        if ( ! empty( $settings['next_icon_html'] ) ) echo ' data-next-icon-html="' . esc_attr( $settings['next_icon_html'] ) . '"';
        if ( isset( $settings['pagination_type'] ) ) echo ' data-pagination-type="' . esc_attr( $settings['pagination_type'] ) . '"';
        echo '>';

        echo '<thead><tr>';
        foreach ( $columns as $col ) {
            echo '<th data-col-id="' . esc_attr( $col['id'] ) . '" data-type="' . esc_attr( $col['data_type'] ) . '">';
            echo esc_html( $col['label'] );
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
                    echo '<td>' . self::render_cell_value( $value, $col['data_type'] ) . '</td>';
                }
                echo '</tr>';
            }
        }
        echo '</tbody>';

        echo '</table>';
        echo '</div>';
        echo '</div>';

        return ob_get_clean();
    }

    private static function render_cell_value( string $value, string $data_type ): string {
        if ( $value === '' ) return '—';

        switch ( $data_type ) {
            case 'richtext':
                return wp_kses_post( $value );

            case 'link':
                $url = esc_url( $value );
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" style="color: #4f46e5; text-decoration: underline; font-weight:500;">' . esc_html( $value ) . '</a>';

            case 'image':
                $url = esc_url( $value );
                return '<img src="' . $url . '" alt="" class="wtb-cell-img" loading="lazy">';

            case 'button':
                return '<span class="wtb-cell-btn">' . esc_html( $value ) . '</span>';

            case 'badge':
                return '<span class="wtb-cell-badge">' . esc_html( $value ) . '</span>';

            case 'rating':
                $rating = min( 5, max( 0, (int) $value ) );
                $stars  = str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating );
                return '<span class="wtb-cell-rating" title="' . esc_attr( $rating . '/5' ) . '">' . $stars . '</span>';

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
            'tableId'    => $table_id,
            'serverSide' => $server_side,
            'restUrl'    => esc_url_raw( rest_url( 'wtb/v1' ) ),
            'nonce'      => wp_create_nonce( 'wp_rest' ),
            'settings'   => $settings,
            'columns'    => array_map( function( $col ) {
                return [ 'id' => $col['id'], 'label' => $col['label'], 'data_type' => $col['data_type'] ];
            }, $columns ),
        ] );
    }
}
