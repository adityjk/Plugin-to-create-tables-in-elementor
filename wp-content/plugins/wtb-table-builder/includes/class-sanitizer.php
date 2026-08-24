<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Centralized input sanitization. Every piece of user-supplied data must go
 * through this class — no inline sanitize_* calls elsewhere in the plugin.
 */
class WTB_Sanitizer {

    public static function plain_text( string $value ): string {
        return sanitize_text_field( wp_unslash( $value ) );
    }

    public static function data_type( string $value ): string {
        $allowed = [ 'text', 'number', 'date', 'richtext', 'link', 'button', 'image', 'badge', 'rating', 'file' ];
        return in_array( $value, $allowed, true ) ? $value : 'text';
    }

    /**
     * Sanitize a single cell value according to its column type.
     * Rich text keeps safe HTML; URLs are stored raw-escaped; everything
     * else collapses to plain text.
     */
    public static function cell_value( string $value, string $data_type ): string {
        switch ( $data_type ) {
            case 'richtext':
                return wp_kses_post( wp_unslash( $value ) );

            case 'link':
            case 'image':
            case 'file':
                return esc_url_raw( wp_unslash( $value ) );

            case 'number':
            case 'rating':
                return is_numeric( $value ) ? strval( $value ) : '';

            default:
                return sanitize_text_field( wp_unslash( $value ) );
        }
    }

    /**
     * Normalize the per-table settings blob. Unknown keys are dropped and
     * every value is coerced to a safe default, so anything read back from
     * this array is safe to use without further checks.
     */
    public static function table_settings( array $raw ): array {
        return [
            'header_bg'              => sanitize_hex_color( $raw['header_bg'] ?? '#2271b1' ) ?? '#2271b1',
            'header_text'            => sanitize_hex_color( $raw['header_text'] ?? '#ffffff' ) ?? '#ffffff',
            'row_stripe_color'       => sanitize_hex_color( $raw['row_stripe_color'] ?? '#f5f5f5' ) ?? '#f5f5f5',
            'border_color'           => sanitize_hex_color( $raw['border_color'] ?? '#dddddd' ) ?? '#dddddd',
            'border_width'           => absint( $raw['border_width'] ?? 1 ),
            'border_radius'          => absint( $raw['border_radius'] ?? 8 ),
            'cell_padding'           => absint( $raw['cell_padding'] ?? 8 ),
            'width'                  => sanitize_text_field( $raw['width'] ?? '100%' ),
            'max_width'              => sanitize_text_field( $raw['max_width'] ?? '100%' ),
            'height'                 => sanitize_text_field( $raw['height'] ?? 'auto' ),
            'max_height'             => sanitize_text_field( $raw['max_height'] ?? 'none' ),
            'alignment'              => self::enum( $raw['alignment'] ?? 'center', [ 'left', 'center', 'right' ], 'center' ),
            'box_shadow'             => self::enum( $raw['box_shadow'] ?? 'sm', [ 'none', 'sm', 'md', 'lg' ], 'sm' ),
            'enable_search'          => (bool) ( $raw['enable_search'] ?? true ),
            'enable_sort'            => (bool) ( $raw['enable_sort'] ?? true ),
            'row_stripe'             => (bool) ( $raw['row_stripe'] ?? true ),
            'responsive_mode'        => self::enum( $raw['responsive_mode'] ?? 'scroll', [ 'scroll', 'collapse' ], 'scroll' ),
            'server_side_threshold'  => absint( $raw['server_side_threshold'] ?? 200 ),
            'show_file_preview'      => (bool) ( $raw['show_file_preview'] ?? true ),
            'enable_taxonomy_filter' => (bool) ( $raw['enable_taxonomy_filter'] ?? false ),
            'taxonomy_filter_column' => sanitize_text_field( $raw['taxonomy_filter_column'] ?? '' ),
            'enable_form_submission' => (bool) ( $raw['enable_form_submission'] ?? false ),
            'form_require_approval'  => (bool) ( $raw['form_require_approval'] ?? false ),
            'data_source'            => self::enum( $raw['data_source'] ?? 'manual', [ 'manual', 'wp_posts' ], 'manual' ),
            'post_type'              => sanitize_text_field( $raw['post_type'] ?? 'post' ),
            'posts_limit'            => absint( $raw['posts_limit'] ?? 10 ),
        ];
    }

    private static function enum( $value, array $allowed, string $default ): string {
        return in_array( $value, $allowed, true ) ? $value : $default;
    }
}
