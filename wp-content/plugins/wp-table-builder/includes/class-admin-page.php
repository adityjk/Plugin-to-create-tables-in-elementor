<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WTB_Admin_Page {

    public static function init() {
        add_action( 'admin_menu',                        [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts',             [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'admin_post_wtb_create_table',       [ __CLASS__, 'handle_create_table' ] );
        add_action( 'admin_post_wtb_delete_table',       [ __CLASS__, 'handle_delete_table' ] );
        add_action( 'admin_post_wtb_duplicate_table',    [ __CLASS__, 'handle_duplicate_table' ] );
        add_action( 'admin_post_wtb_clear_log',          [ __CLASS__, 'handle_clear_log' ] );
        add_action( 'admin_post_wtb_toggle_debug',       [ __CLASS__, 'handle_toggle_debug' ] );
    }

    public static function register_menu() {
        add_menu_page(
            __( 'Table Builder', 'wp-table-builder' ),
            __( 'Table Builder', 'wp-table-builder' ),
            'manage_options',
            'wtb-tables',
            [ __CLASS__, 'render_tables_page' ],
            'dashicons-editor-table',
            30
        );

        add_submenu_page(
            'wtb-tables',
            __( 'Semua Tabel', 'wp-table-builder' ),
            __( 'Semua Tabel', 'wp-table-builder' ),
            'manage_options',
            'wtb-tables',
            [ __CLASS__, 'render_tables_page' ]
        );

        add_submenu_page(
            'wtb-tables',
            __( 'Buat Tabel Baru', 'wp-table-builder' ),
            __( '+ Buat Tabel Baru', 'wp-table-builder' ),
            'manage_options',
            'wtb-new-table',
            [ __CLASS__, 'render_new_table_page' ]
        );

        add_submenu_page(
            'wtb-tables',
            __( 'Form Submission Log', 'wp-table-builder' ),
            __( '🪵 Form Log', 'wp-table-builder' ),
            'manage_options',
            'wtb-form-log',
            [ __CLASS__, 'render_form_log_page' ]
        );
    }

    public static function enqueue_assets( string $hook ) {
        if ( ! self::is_wtb_admin_page( $hook ) ) return;

        wp_enqueue_style( 'wtb-admin', WTB_PLUGIN_URL . 'assets/css/admin.css', [], WTB_VERSION );

        $action   = sanitize_text_field( $_GET['action']   ?? '' );
        $table_id = absint( $_GET['table_id'] ?? 0 );

        if ( $action === 'edit' && $table_id > 0 ) {
            wp_enqueue_media();

            wp_enqueue_script(
                'wtb-admin-builder',
                WTB_PLUGIN_URL . 'assets/js/admin-builder.js',
                [],
                WTB_VERSION,
                true
            );

            wp_localize_script( 'wtb-admin-builder', 'WTB_Admin_Config', [
                'restUrl' => esc_url_raw( rest_url( 'wtb/v1' ) ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
                'tableId' => $table_id,
                'strings' => [
                    'save_success'   => __( 'Tabel berhasil disimpan!', 'wp-table-builder' ),
                    'save_error'     => __( 'Gagal menyimpan tabel.', 'wp-table-builder' ),
                    'saving'         => __( 'Menyimpan...', 'wp-table-builder' ),
                    'save_btn_label' => __( 'Simpan Tabel', 'wp-table-builder' ),
                    'confirm_delete' => __( 'Hapus baris ini?', 'wp-table-builder' ),
                    'new_col_label'  => __( 'Kolom Baru', 'wp-table-builder' ),
                    'loading'        => __( 'Memuat data tabel...', 'wp-table-builder' ),
                ],
            ] );
        }
    }

    private static function is_wtb_admin_page( string $hook ): bool {
        $wtb_hooks = [
            'toplevel_page_wtb-tables',
            'table-builder_page_wtb-new-table',
            'table-builder_page_wtb-form-log',
        ];
        return in_array( $hook, $wtb_hooks, true );
    }

    /* ---------------------------------------------------------------
     * Form Submission Log Page
     * ------------------------------------------------------------- */

    public static function handle_clear_log() {
        check_admin_referer( 'wtb_clear_log' );
        if ( current_user_can( 'manage_options' ) ) {
            WTB_Debug_Logger::clear();
        }
        wp_safe_redirect( admin_url( 'admin.php?page=wtb-form-log&cleared=1' ) );
        exit;
    }

    public static function handle_toggle_debug() {
        check_admin_referer( 'wtb_toggle_debug' );
        if ( current_user_can( 'manage_options' ) ) {
            $current = WTB_Debug_Logger::is_enabled();
            WTB_Debug_Logger::set_mode( ! $current );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=wtb-form-log' ) );
        exit;
    }

    public static function render_form_log_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Akses ditolak.', 'wp-table-builder' ) );
        }

        $debug_on = WTB_Debug_Logger::is_enabled();
        $logs     = WTB_Debug_Logger::get_all();
        $cleared  = isset( $_GET['cleared'] );
        ?>
        <div class="wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">
            <h1 style="display:flex;align-items:center;gap:10px">
                🪵 Form Submission Log
                <span style="font-size:13px;font-weight:400;color:#666;margin-left:4px">
                    (<?php echo count( $logs ); ?> entri)
                </span>
            </h1>

            <?php if ( $cleared ): ?>
            <div class="notice notice-success is-dismissible"><p>Log berhasil dihapus.</p></div>
            <?php endif; ?>

            <!-- Controls -->
            <div style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap">

                <!-- Toggle Debug Mode -->
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'wtb_toggle_debug' ); ?>
                    <input type="hidden" name="action" value="wtb_toggle_debug">
                    <button type="submit" class="button <?php echo $debug_on ? 'button-primary' : 'button-secondary'; ?>">
                        <?php echo $debug_on ? '🟢 Debug ON — Klik untuk Matikan' : '⚫ Debug OFF — Klik untuk Aktifkan'; ?>
                    </button>
                </form>

                <!-- Clear Log -->
                <?php if ( ! empty( $logs ) ): ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Hapus semua log?')">
                    <?php wp_nonce_field( 'wtb_clear_log' ); ?>
                    <input type="hidden" name="action" value="wtb_clear_log">
                    <button type="submit" class="button button-secondary" style="color:#c0392b">🗑 Hapus Semua Log</button>
                </form>
                <?php endif; ?>

                <button onclick="location.reload()" class="button button-secondary">🔄 Refresh</button>
            </div>

            <?php if ( ! $debug_on ): ?>
            <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:14px 18px;margin-bottom:16px">
                <strong>⚠️ Debug mode sedang OFF.</strong> Aktifkan terlebih dahulu, lalu coba submit form Elementor.
                Log akan muncul di sini secara otomatis.
            </div>
            <?php endif; ?>

            <?php if ( empty( $logs ) ): ?>
            <div style="background:#f0f0f1;border-radius:6px;padding:30px;text-align:center;color:#666">
                Belum ada log. Aktifkan debug mode lalu submit form.
            </div>
            <?php else: ?>

            <!-- Log Table -->
            <div style="overflow-x:auto">
            <table class="wp-list-table widefat fixed striped" style="border-radius:8px;overflow:hidden">
                <thead style="background:#1d2327">
                    <tr>
                        <th style="color:#fff;width:160px">Waktu</th>
                        <th style="color:#fff;width:80px">Level</th>
                        <th style="color:#fff">Pesan</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $logs as $entry ):
                    $level = $entry['level'] ?? 'INFO';
                    $badge_style = match ( $level ) {
                        'ERROR' => 'background:#e74c3c;color:#fff',
                        'WARN'  => 'background:#f39c12;color:#fff',
                        default => 'background:#27ae60;color:#fff',
                    };
                    $row_bg = $level === 'ERROR' ? 'background:#fff5f5' : ( $level === 'WARN' ? 'background:#fffdf0' : '' );
                ?>
                <tr style="<?php echo esc_attr( $row_bg ); ?>">
                    <td style="font-size:12px;color:#555;white-space:nowrap"><?php echo esc_html( $entry['ts'] ?? '' ); ?></td>
                    <td>
                        <span style="<?php echo esc_attr( $badge_style ); ?>;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700">
                            <?php echo esc_html( $level ); ?>
                        </span>
                    </td>
                    <td style="font-family:monospace;font-size:12px;word-break:break-all">
                        <?php echo esc_html( $entry['message'] ?? '' ); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <p style="color:#999;font-size:12px;margin-top:8px">Menampilkan <?php echo count( $logs ); ?> entri terbaru (maks 150). Terbaru di atas.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function render_tables_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Akses ditolak.', 'wp-table-builder' ) );
        }

        $action   = sanitize_text_field( $_GET['action']   ?? '' );
        $table_id = absint( $_GET['table_id'] ?? 0 );

        if ( $action === 'edit' && $table_id > 0 ) {
            self::render_edit_view( $table_id );
        } else {
            self::render_list_view();
        }
    }

    private static function render_list_view() {
        $tables = get_posts( [
            'post_type'      => 'wtb_table',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $notice = self::get_flash_notice();
        $total_tables = count( $tables );
        $total_rows   = 0;
        foreach ( $tables as $tbl ) {
            $total_rows += self::count_rows( $tbl->ID );
        }
        ?>
        <div class="wrap wtb-admin-wrap">
            <div class="wtb-admin-header">
                <h1>
                    <span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
                    <?php esc_html_e( 'Table Builder', 'wp-table-builder' ); ?>
                </h1>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wtb-new-table' ) ); ?>" class="wtb-btn-primary">
                    <span class="dashicons dashicons-plus-alt2" style="font-size:16px; width:16px; height:16px;" aria-hidden="true"></span>
                    <?php esc_html_e( 'Buat Tabel Baru', 'wp-table-builder' ); ?>
                </a>
            </div>

            <?php if ( $notice ) : ?>
                <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> wtb-save-notice is-dismissible">
                    <p><?php echo esc_html( $notice['message'] ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $tables ) ) : ?>
                <div class="wtb-stats-grid">
                    <div class="wtb-stat-card">
                        <div class="wtb-stat-icon">
                            <span class="dashicons dashicons-grid-view"></span>
                        </div>
                        <div class="wtb-stat-info">
                            <span class="wtb-stat-value"><?php echo esc_html( $total_tables ); ?></span>
                            <span class="wtb-stat-label"><?php esc_html_e( 'Total Tabel Dibuat', 'wp-table-builder' ); ?></span>
                        </div>
                    </div>
                    <div class="wtb-stat-card">
                        <div class="wtb-stat-icon">
                            <span class="dashicons dashicons-menu-alt"></span>
                        </div>
                        <div class="wtb-stat-info">
                            <span class="wtb-stat-value"><?php echo esc_html( $total_rows ); ?></span>
                            <span class="wtb-stat-label"><?php esc_html_e( 'Total Baris Data', 'wp-table-builder' ); ?></span>
                        </div>
                    </div>
                    <div class="wtb-stat-card">
                        <div class="wtb-stat-icon">
                            <span class="dashicons dashicons-shortcode"></span>
                        </div>
                        <div class="wtb-stat-info">
                            <span class="wtb-stat-value"><?php esc_html_e( 'Shortcode & Elementor', 'wp-table-builder' ); ?></span>
                            <span class="wtb-stat-label"><?php esc_html_e( 'Siap ditempel di halaman web', 'wp-table-builder' ); ?></span>
                        </div>
                    </div>
                </div>

                <div class="wtb-table-card">
                    <table class="wtb-table-list">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e( 'Nama Tabel', 'wp-table-builder' ); ?></th>
                                <th scope="col" style="width:110px;"><?php esc_html_e( 'Kolom', 'wp-table-builder' ); ?></th>
                                <th scope="col" style="width:110px;"><?php esc_html_e( 'Baris', 'wp-table-builder' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Shortcode', 'wp-table-builder' ); ?></th>
                                <th scope="col" style="width:230px; text-align:right;"><?php esc_html_e( 'Aksi', 'wp-table-builder' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $tables as $table ) : ?>
                                <?php self::render_table_row( $table ); ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="wtb-empty-state">
                    <div class="wtb-empty-icon-wrap">
                        <span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
                    </div>
                    <h3><?php esc_html_e( 'Belum Ada Tabel Dibuat', 'wp-table-builder' ); ?></h3>
                    <p><?php esc_html_e( 'Buat tabel pertama Anda untuk menampilkan data yang rapi dan interaktif di Elementor atau WordPress editor.', 'wp-table-builder' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wtb-new-table' ) ); ?>" class="wtb-btn-primary">
                        <span class="dashicons dashicons-plus-alt2" style="font-size:16px; width:16px; height:16px;" aria-hidden="true"></span>
                        <?php esc_html_e( 'Buat Tabel Pertama', 'wp-table-builder' ); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_table_row( WP_Post $table ) {
        $column_count  = self::count_columns( $table->ID );
        $row_count     = self::count_rows( $table->ID );
        $edit_url      = admin_url( 'admin.php?page=wtb-tables&action=edit&table_id=' . $table->ID );

        $duplicate_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=wtb_duplicate_table&table_id=' . $table->ID ),
            'wtb_duplicate_' . $table->ID
        );

        $delete_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=wtb_delete_table&table_id=' . $table->ID ),
            'wtb_delete_' . $table->ID
        );

        $shortcode = '[wtb_table id="' . $table->ID . '"]';
        ?>
        <tr>
            <td>
                <a href="<?php echo esc_url( $edit_url ); ?>" class="wtb-table-name">
                    <?php echo esc_html( $table->post_title ); ?>
                </a>
            </td>
            <td><strong><?php echo esc_html( $column_count ); ?></strong> kolom</td>
            <td><strong><?php echo esc_html( $row_count ); ?></strong> baris</td>
            <td>
                <span class="wtb-shortcode-badge">
                    <code><?php echo esc_html( $shortcode ); ?></code>
                    <button type="button" class="wtb-btn-copy-mini wtb-btn-copy-shortcode" data-shortcode="<?php echo esc_attr( $shortcode ); ?>" title="<?php esc_attr_e( 'Salin Shortcode', 'wp-table-builder' ); ?>">
                        <?php esc_html_e( 'Salin', 'wp-table-builder' ); ?>
                    </button>
                </span>
            </td>
            <td>
                <div class="wtb-action-buttons" style="justify-content: flex-end;">
                    <a href="<?php echo esc_url( $edit_url ); ?>" class="wtb-btn-action">
                        <span class="dashicons dashicons-edit" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php esc_html_e( 'Edit', 'wp-table-builder' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $duplicate_url ); ?>" class="wtb-btn-action" title="<?php esc_attr_e( 'Duplikat tabel', 'wp-table-builder' ); ?>">
                        <span class="dashicons dashicons-admin-page" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php esc_html_e( 'Duplikat', 'wp-table-builder' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $delete_url ); ?>" class="wtb-btn-action wtb-btn-delete" onclick="return confirm('<?php esc_attr_e( 'Yakin ingin menghapus tabel ini?', 'wp-table-builder' ); ?>')">
                        <span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php esc_html_e( 'Hapus', 'wp-table-builder' ); ?>
                    </a>
                </div>
            </td>
        </tr>
        <?php
    }

    private static function render_edit_view( int $table_id ) {
        $post = get_post( $table_id );

        if ( ! $post || $post->post_type !== 'wtb_table' ) {
            wp_die( esc_html__( 'Tabel tidak ditemukan.', 'wp-table-builder' ) );
        }

        $list_url = admin_url( 'admin.php?page=wtb-tables' );
        ?>
        <div class="wrap wtb-admin-wrap">

            <div class="wtb-save-bar">
                <div class="wtb-save-bar__left">
                    <a href="<?php echo esc_url( $list_url ); ?>" class="wtb-back-link" title="<?php esc_attr_e( 'Kembali ke daftar tabel', 'wp-table-builder' ); ?>">
                        ← <?php esc_html_e( 'Semua Tabel', 'wp-table-builder' ); ?>
                    </a>
                    <input type="text"
                           id="wtb-input-title"
                           class="wtb-title-input"
                           value="<?php echo esc_attr( $post->post_title ); ?>"
                           placeholder="<?php esc_attr_e( 'Nama Tabel', 'wp-table-builder' ); ?>">
                </div>
                <div style="display:flex; gap:8px;">
                    <button id="wtb-btn-export-csv" type="button" class="wtb-btn-action" style="background:#fff; border:1px solid #cbd5e1; color:#334155; padding:6px 12px; border-radius:6px; font-weight:500; display:flex; align-items:center; gap:6px; cursor:pointer;" title="<?php esc_attr_e( 'Export Data ke CSV', 'wp-table-builder' ); ?>">
                        <span class="dashicons dashicons-download" style="font-size:16px; width:16px; height:16px; margin:0;" aria-hidden="true"></span>
                        <?php esc_html_e( 'Export CSV', 'wp-table-builder' ); ?>
                    </button>
                    <button id="wtb-btn-import-csv" type="button" class="wtb-btn-action" style="background:#fff; border:1px solid #cbd5e1; color:#334155; padding:6px 12px; border-radius:6px; font-weight:500; display:flex; align-items:center; gap:6px; cursor:pointer;" title="<?php esc_attr_e( 'Import Data dari CSV (Tabel Manual saja)', 'wp-table-builder' ); ?>">
                        <span class="dashicons dashicons-upload" style="font-size:16px; width:16px; height:16px; margin:0;" aria-hidden="true"></span>
                        <?php esc_html_e( 'Import CSV', 'wp-table-builder' ); ?>
                    </button>
                    <input type="file" id="wtb-csv-upload" accept=".csv" style="display:none;" />
                    <button id="wtb-btn-save" type="button" class="wtb-btn-primary wtb-btn-save">
                        <span class="dashicons dashicons-saved" style="font-size:16px; width:16px; height:16px;" aria-hidden="true"></span>
                        <?php esc_html_e( 'Simpan Tabel', 'wp-table-builder' ); ?>
                    </button>
                </div>
            </div>

            <div id="wtb-save-notice" class="wtb-save-notice notice" style="display:none;" role="alert"></div>

            <div id="wtb-onboarding-tips" class="wtb-onboarding-tips" role="region" aria-label="<?php esc_attr_e( 'Panduan cara pakai editor', 'wp-table-builder' ); ?>">
                <div class="wtb-tips-header">
                    <div class="wtb-tips-header-title">
                        <span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
                        <span><?php esc_html_e( 'Cara Pakai Editor Tabel', 'wp-table-builder' ); ?></span>
                    </div>
                    <button type="button" class="wtb-tips-dismiss" id="wtb-btn-dismiss-tips" title="<?php esc_attr_e( 'Tutup panduan ini', 'wp-table-builder' ); ?>">
                        ✕
                    </button>
                </div>
                <ol class="wtb-tips-steps">
                    <li class="wtb-tips-step">
                        <span class="wtb-step-num" aria-hidden="true">1</span>
                        <div class="wtb-step-body">
                            <strong><?php esc_html_e( 'Tambah Kolom', 'wp-table-builder' ); ?></strong>
                            <span><?php esc_html_e( 'Klik tombol "+ Kolom" di ujung header tabel.', 'wp-table-builder' ); ?></span>
                        </div>
                    </li>
                    <li class="wtb-tips-step">
                        <span class="wtb-step-num" aria-hidden="true">2</span>
                        <div class="wtb-step-body">
                            <strong><?php esc_html_e( 'Atur Tipe Data', 'wp-table-builder' ); ?></strong>
                            <span><?php esc_html_e( 'Ketik nama & pilih tipe data (Teks, Angka, Link, Rating, dll).', 'wp-table-builder' ); ?></span>
                        </div>
                    </li>
                    <li class="wtb-tips-step">
                        <span class="wtb-step-num" aria-hidden="true">3</span>
                        <div class="wtb-step-body">
                            <strong><?php esc_html_e( 'Isi Baris Data', 'wp-table-builder' ); ?></strong>
                            <span><?php esc_html_e( 'Klik "+ Tambah Baris" lalu isi nilai di setiap cell.', 'wp-table-builder' ); ?></span>
                        </div>
                    </li>
                    <li class="wtb-tips-step">
                        <span class="wtb-step-num" aria-hidden="true">4</span>
                        <div class="wtb-step-body">
                            <strong><?php esc_html_e( 'Simpan & Tempel', 'wp-table-builder' ); ?></strong>
                            <span><?php esc_html_e( 'Klik "Simpan Tabel" lalu gunakan Shortcode atau Elementor.', 'wp-table-builder' ); ?></span>
                        </div>
                    </li>
                </ol>
            </div>
            <script>
            (function() {
                var el = document.getElementById('wtb-onboarding-tips');
                if (localStorage.getItem('wtb_tips_dismissed')) {
                    if (el) el.style.display = 'none';
                }
                document.addEventListener('click', function(e) {
                    if (e.target && (e.target.id === 'wtb-btn-dismiss-tips' || e.target.closest('#wtb-btn-dismiss-tips'))) {
                        if (el) {
                            el.style.opacity = '0';
                            el.style.transform = 'translateY(-8px)';
                            setTimeout(function() { el.style.display = 'none'; }, 280);
                        }
                        localStorage.setItem('wtb_tips_dismissed', '1');
                    }
                });
            })();
            </script>

            <div id="wtb-editor-loader" class="wtb-loader">
                <span class="spinner is-active" style="float:none; margin:0;"></span>
                <span><?php esc_html_e( 'Memuat data tabel...', 'wp-table-builder' ); ?></span>
            </div>

            <div class="wtb-editor-layout" id="wtb-editor-main" style="display:none;">

                <div class="wtb-editor-panel">
                    <div class="wtb-editor-panel__header">
                        <h2><?php esc_html_e( 'Editor Kolom & Baris', 'wp-table-builder' ); ?></h2>
                        <p class="description">
                            <?php esc_html_e( 'Klik nama kolom untuk edit header. Klik cell untuk mengisi data.', 'wp-table-builder' ); ?>
                        </p>
                    </div>

                    <div id="wtb-editor-table-wrapper">
                        <table id="wtb-editor-table" aria-label="<?php esc_attr_e( 'Editor tabel', 'wp-table-builder' ); ?>">
                            <thead>
                                <tr id="wtb-column-headers">
                                </tr>
                            </thead>
                            <tbody id="wtb-rows-body">
                            </tbody>
                        </table>
                    </div>

                    <div class="wtb-add-row-area">
                        <button id="wtb-btn-add-row" type="button" class="wtb-btn-add-row">
                            + <?php esc_html_e( 'Tambah Baris Baru', 'wp-table-builder' ); ?>
                        </button>
                    </div>
                </div>

                <aside class="wtb-settings-panel">
                    <h2>
                        <span class="dashicons dashicons-admin-settings" style="color:var(--wtb-primary);"></span>
                        <?php esc_html_e( 'Pengaturan Tabel', 'wp-table-builder' ); ?>
                    </h2>
                    <p class="description" style="margin-bottom:16px; font-size:0.85em; color:#64748b;">
                        <?php esc_html_e( 'Pengaturan tampilan (warna, border, shadow, dll) tersedia di panel Elementor saat menambah widget ke halaman.', 'wp-table-builder' ); ?>
                    </p>

                        <div class="wtb-settings-group">
                            <label for="wtb_data_source"><strong><?php esc_html_e( 'Sumber Data (Data Source)', 'wp-table-builder' ); ?></strong></label>
                            <select id="wtb_data_source" data-setting-key="data_source" style="margin-top: 4px; width: 100%;">
                                <option value="manual"><?php esc_html_e( 'Manual (Input Baris / Elementor Form)', 'wp-table-builder' ); ?></option>
                                <option value="wp_posts"><?php esc_html_e( 'WordPress Posts (Dynamic)', 'wp-table-builder' ); ?></option>
                            </select>
                        </div>

                        <div class="wtb-settings-group wtb-wp-posts-setting" style="display:none; padding-left:12px; border-left:3px solid #4f46e5;">
                            <label for="wtb_post_type"><?php esc_html_e( 'Tipe Post', 'wp-table-builder' ); ?></label>
                            <select id="wtb_post_type" data-setting-key="post_type" style="margin-top: 4px; margin-bottom: 12px; width: 100%;">
                                <?php
                                $post_types = get_post_types( [ 'public' => true ], 'objects' );
                                foreach ( $post_types as $pt ) {
                                    if ( $pt->name === 'wtb_table' || $pt->name === 'attachment' ) continue;
                                    echo '<option value="' . esc_attr( $pt->name ) . '">' . esc_html( $pt->label ) . '</option>';
                                }
                                ?>
                            </select>
                            
                            <label for="wtb_posts_limit"><?php esc_html_e( 'Batas Tampil (Limit)', 'wp-table-builder' ); ?></label>
                            <input type="number" id="wtb_posts_limit" data-setting-key="posts_limit" value="10" min="-1" style="margin-top: 4px; width: 100%;">
                            <span class="description" style="display:block; margin-top:4px;">Gunakan -1 untuk menampilkan semua.</span>
                        </div>
                        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 16px 0;">

                        <div class="wtb-settings-group">
                            <label>
                                <input type="checkbox" id="wtb_enable_search" data-setting-key="enable_search" value="1" checked>
                                <?php esc_html_e( 'Tampilkan search box', 'wp-table-builder' ); ?>
                            </label>
                        </div>

                        <div class="wtb-settings-group">
                            <label>
                                <input type="checkbox" id="wtb_enable_sort" data-setting-key="enable_sort" value="1" checked>
                                <?php esc_html_e( 'Aktifkan sort kolom', 'wp-table-builder' ); ?>
                            </label>
                        </div>

                        <div class="wtb-settings-group">
                            <label>
                                <input type="checkbox" id="wtb_show_file_preview" data-setting-key="show_file_preview" value="1" checked>
                                <?php esc_html_e( 'Tampilkan preview file (modal popup)', 'wp-table-builder' ); ?>
                            </label>
                        </div>

                        <div class="wtb-settings-group">
                            <label for="wtb_responsive_mode"><?php esc_html_e( 'Mode Responsif Mobile', 'wp-table-builder' ); ?></label>
                            <select id="wtb_responsive_mode" data-setting-key="responsive_mode">
                                <option value="scroll"><?php esc_html_e( 'Horizontal Scroll', 'wp-table-builder' ); ?></option>
                                <option value="collapse"><?php esc_html_e( 'Collapsible Rows', 'wp-table-builder' ); ?></option>
                            </select>
                        </div>

                        <div class="wtb-settings-group">
                            <label for="wtb_server_side_threshold">
                                <?php esc_html_e( 'Threshold Server-Side (baris)', 'wp-table-builder' ); ?>
                            </label>
                            <input type="number" id="wtb_server_side_threshold" data-setting-key="server_side_threshold" value="200" min="10" max="10000">
                        </div>

                    </form>

                    <div class="wtb-shortcode-info">
                        <p><?php esc_html_e( 'Shortcode Tabel:', 'wp-table-builder' ); ?></p>
                        <code>[wtb_table id="<?php echo esc_html( $table_id ); ?>"]</code>
                        <button type="button" class="wtb-btn-primary wtb-btn-copy-shortcode"
                                data-shortcode='[wtb_table id="<?php echo esc_attr( $table_id ); ?>"]'>
                            <span class="dashicons dashicons-admin-page" style="font-size:14px; width:14px; height:14px;"></span>
                            <?php esc_html_e( 'Salin Shortcode', 'wp-table-builder' ); ?>
                        </button>
                    </div>
                </aside>


            </div>
        </div>
        <?php
    }

    public static function render_new_table_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Akses ditolak.', 'wp-table-builder' ) );
        }

        $notice = self::get_flash_notice();
        ?>
        <div class="wrap wtb-admin-wrap">
            <?php if ( $notice ) : ?>
                <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> wtb-save-notice is-dismissible">
                    <p><?php echo esc_html( $notice['message'] ); ?></p>
                </div>
            <?php endif; ?>

            <div class="wtb-new-table-card">
                <div class="wtb-new-table-header">
                    <div class="wtb-new-table-icon">
                        <span class="dashicons dashicons-table-import"></span>
                    </div>
                    <h2><?php esc_html_e( 'Buat Tabel Baru', 'wp-table-builder' ); ?></h2>
                    <p><?php esc_html_e( 'Berikan nama tabel untuk memulai membuat kolom dan baris data.', 'wp-table-builder' ); ?></p>
                </div>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="wtb_create_table">
                    <?php wp_nonce_field( 'wtb_create_table', 'wtb_nonce' ); ?>

                    <div class="wtb-form-group">
                        <label for="wtb_new_table_title"><?php esc_html_e( 'Nama Tabel', 'wp-table-builder' ); ?> *</label>
                        <input type="text"
                               id="wtb_new_table_title"
                               name="wtb_title"
                               required
                               autofocus
                               maxlength="200"
                               placeholder="<?php esc_attr_e( 'Contoh: Daftar Produk Unggulan', 'wp-table-builder' ); ?>">
                    </div>

                    <div class="wtb-form-actions">
                        <button type="submit" class="wtb-btn-primary" style="flex:1; justify-content:center;">
                            <span class="dashicons dashicons-plus-alt2" style="font-size:16px; width:16px; height:16px;"></span>
                            <?php esc_html_e( 'Buat Tabel & Mulai Edit', 'wp-table-builder' ); ?>
                        </button>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wtb-tables' ) ); ?>" class="wtb-btn-action" style="padding: 10px 18px;">
                            <?php esc_html_e( 'Batal', 'wp-table-builder' ); ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    public static function handle_create_table() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Akses ditolak.', 'wp-table-builder' ) );
        }

        check_admin_referer( 'wtb_create_table', 'wtb_nonce' );

        $title = WTB_Sanitizer::plain_text( $_POST['wtb_title'] ?? '' );

        if ( empty( $title ) ) {
            self::redirect_with_notice(
                admin_url( 'admin.php?page=wtb-new-table' ),
                'error',
                __( 'Nama tabel tidak boleh kosong.', 'wp-table-builder' )
            );
            return;
        }

        $table_id = wp_insert_post( [
            'post_title'  => $title,
            'post_type'   => 'wtb_table',
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $table_id ) ) {
            self::redirect_with_notice(
                admin_url( 'admin.php?page=wtb-new-table' ),
                'error',
                __( 'Gagal membuat tabel. Coba lagi.', 'wp-table-builder' )
            );
            return;
        }

        $default_settings = WTB_Sanitizer::table_settings( [] );
        update_post_meta( $table_id, '_wtb_settings', wp_json_encode( $default_settings ) );

        self::redirect_with_notice(
            admin_url( 'admin.php?page=wtb-tables&action=edit&table_id=' . $table_id ),
            'success',
            __( 'Tabel berhasil dibuat! Sekarang kamu bisa menambah kolom dan baris.', 'wp-table-builder' )
        );
    }

    public static function handle_delete_table() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Akses ditolak.', 'wp-table-builder' ) );
        }

        $table_id = absint( $_GET['table_id'] ?? 0 );
        check_admin_referer( 'wtb_delete_' . $table_id );

        if ( $table_id > 0 ) {
            global $wpdb;
            wp_delete_post( $table_id, true );
            $wpdb->delete( $wpdb->prefix . 'wtb_columns', [ 'table_id' => $table_id ], [ '%d' ] );
            $wpdb->delete( $wpdb->prefix . 'wtb_rows',    [ 'table_id' => $table_id ], [ '%d' ] );
        }

        self::redirect_with_notice(
            admin_url( 'admin.php?page=wtb-tables' ),
            'success',
            __( 'Tabel berhasil dihapus.', 'wp-table-builder' )
        );
    }

    public static function handle_duplicate_table() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Akses ditolak.', 'wp-table-builder' ) );
        }

        $table_id = absint( $_GET['table_id'] ?? 0 );
        check_admin_referer( 'wtb_duplicate_' . $table_id );

        if ( $table_id <= 0 ) {
            self::redirect_with_notice(
                admin_url( 'admin.php?page=wtb-tables' ),
                'error',
                __( 'ID tabel tidak valid.', 'wp-table-builder' )
            );
            return;
        }

        $rest_request = new WP_REST_Request( 'POST', '/wtb/v1/tables/' . $table_id . '/duplicate' );
        $rest_request->set_param( 'id', $table_id );

        $response = WTB_Rest_Controller::duplicate_table( $rest_request );

        if ( is_wp_error( $response ) ) {
            self::redirect_with_notice(
                admin_url( 'admin.php?page=wtb-tables' ),
                'error',
                __( 'Gagal menduplikat tabel.', 'wp-table-builder' )
            );
            return;
        }

        $data         = $response->get_data();
        $new_table_id = $data['new_table_id'] ?? 0;

        self::redirect_with_notice(
            admin_url( 'admin.php?page=wtb-tables&action=edit&table_id=' . $new_table_id ),
            'success',
            __( 'Tabel berhasil diduplikat!', 'wp-table-builder' )
        );
    }

    private static function count_columns( int $table_id ): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wtb_columns WHERE table_id = %d",
                $table_id
            )
        );
    }

    private static function count_rows( int $table_id ): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wtb_rows WHERE table_id = %d",
                $table_id
            )
        );
    }

    private static function redirect_with_notice( string $url, string $type, string $message ) {
        set_transient( 'wtb_admin_notice_' . get_current_user_id(), [
            'type'    => $type,
            'message' => $message,
        ], 60 );

        wp_safe_redirect( $url );
        exit;
    }

    private static function get_flash_notice(): ?array {
        $user_id = get_current_user_id();
        $notice  = get_transient( 'wtb_admin_notice_' . $user_id );

        if ( $notice ) {
            delete_transient( 'wtb_admin_notice_' . $user_id );
            return $notice;
        }

        return null;
    }
}
