<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Lightweight option-based logger for diagnosing form submissions.
 * Off by default; capped at MAX_ENTRIES so the option row stays small.
 */
class WTB_Debug_Logger {

    const OPTION_KEY  = 'wtb_debug_logs';
    const MODE_KEY    = 'wtb_debug_mode';
    const MAX_ENTRIES = 150;

    public static function is_enabled(): bool {
        return (bool) get_option( self::MODE_KEY, false );
    }

    public static function set_mode( bool $enabled ): void {
        update_option( self::MODE_KEY, $enabled ? 1 : 0, false );
    }

    /**
     * @param string $level   'INFO' | 'WARN' | 'ERROR'
     * @param array  $context Optional key-value pairs appended to the message.
     */
    public static function log( string $message, string $level = 'INFO', array $context = [] ): void {
        if ( ! self::is_enabled() ) return;

        if ( ! empty( $context ) ) {
            $parts = [];
            foreach ( $context as $key => $value ) {
                $parts[] = $key . '=' . ( is_array( $value ) ? wp_json_encode( $value ) : $value );
            }
            $message .= ' | ' . implode( ' | ', $parts );
        }

        $logs   = self::get_raw();
        $logs[] = [
            'ts'      => current_time( 'mysql' ),
            'level'   => strtoupper( $level ),
            'message' => $message,
        ];

        // Keep only the newest entries.
        if ( count( $logs ) > self::MAX_ENTRIES ) {
            $logs = array_slice( $logs, -self::MAX_ENTRIES );
        }

        update_option( self::OPTION_KEY, $logs, false );
    }

    /** Newest first. */
    public static function get_all(): array {
        return array_reverse( self::get_raw() );
    }

    public static function clear(): void {
        delete_option( self::OPTION_KEY );
    }

    private static function get_raw(): array {
        $data = get_option( self::OPTION_KEY, [] );
        return is_array( $data ) ? $data : [];
    }
}
