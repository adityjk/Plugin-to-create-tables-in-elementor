<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WTB_Debug_Logger {

    const OPTION_KEY  = 'wtb_debug_logs';
    const MODE_KEY    = 'wtb_debug_mode';
    const MAX_ENTRIES = 150;

    /**
     * Check if debug mode is enabled.
     */
    public static function is_enabled(): bool {
        return (bool) get_option( self::MODE_KEY, false );
    }

    /**
     * Toggle debug mode on/off.
     */
    public static function set_mode( bool $enabled ): void {
        update_option( self::MODE_KEY, $enabled ? 1 : 0, false );
    }

    /**
     * Write a log entry.
     *
     * @param string $message  Log message.
     * @param string $level    'INFO' | 'WARN' | 'ERROR'
     * @param array  $context  Optional key-value pairs appended to message.
     */
    public static function log( string $message, string $level = 'INFO', array $context = [] ): void {
        if ( ! self::is_enabled() ) return;

        $logs = self::get_raw();

        if ( ! empty( $context ) ) {
            $parts = [];
            foreach ( $context as $k => $v ) {
                $parts[] = $k . '=' . ( is_array( $v ) ? wp_json_encode( $v ) : $v );
            }
            $message .= ' | ' . implode( ' | ', $parts );
        }

        $logs[] = [
            'ts'      => current_time( 'mysql' ),
            'level'   => strtoupper( $level ),
            'message' => $message,
        ];

        // Keep only the latest MAX_ENTRIES entries
        if ( count( $logs ) > self::MAX_ENTRIES ) {
            $logs = array_slice( $logs, -self::MAX_ENTRIES );
        }

        update_option( self::OPTION_KEY, $logs, false );
    }

    /**
     * Get all log entries (newest first).
     */
    public static function get_all(): array {
        return array_reverse( self::get_raw() );
    }

    /**
     * Clear all log entries.
     */
    public static function clear(): void {
        delete_option( self::OPTION_KEY );
    }

    private static function get_raw(): array {
        $data = get_option( self::OPTION_KEY, [] );
        return is_array( $data ) ? $data : [];
    }
}
