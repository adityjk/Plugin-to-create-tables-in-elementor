<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WTB_Activator {

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql_columns = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wtb_columns (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            table_id    BIGINT(20) UNSIGNED NOT NULL,
            label       VARCHAR(255) NOT NULL DEFAULT '',
            data_type   VARCHAR(50)  NOT NULL DEFAULT 'text',
            settings    LONGTEXT,
            sort_order  INT          NOT NULL DEFAULT 0,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY table_id (table_id)
        ) $charset_collate;";

        $sql_rows = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wtb_rows (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            table_id    BIGINT(20) UNSIGNED NOT NULL,
            cells_data  LONGTEXT,
            sort_order  INT          NOT NULL DEFAULT 0,
            status      VARCHAR(20)  NOT NULL DEFAULT 'published',
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY table_id (table_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_columns );
        dbDelta( $sql_rows );

        update_option( 'wtb_db_version', WTB_VERSION );
    }
}
