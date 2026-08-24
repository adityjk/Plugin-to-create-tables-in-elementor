<?php
/**
 * Plugin Name: WP Table Builder
 * Plugin URI:  https://github.com/adityjk/Plugin-to-create-tables-in-elementor
 * Description: Plugin WordPress untuk membuat dan mengelola tabel kustom secara visual. Mendukung Gutenberg Block, Elementor Widget, dan Shortcode. Frontend tabel dilengkapi search, sort, filter, dan pagination via DataTables.js.
 * Version:     1.2.0
 * Author:      AdityJK
 * Author URI:  https://example.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wtb-table-builder
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WTB_VERSION',     '1.2.0' );
define( 'WTB_PLUGIN_FILE', __FILE__ );
define( 'WTB_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WTB_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

require_once WTB_PLUGIN_DIR . 'includes/class-activator.php';
require_once WTB_PLUGIN_DIR . 'includes/class-sanitizer.php';
require_once WTB_PLUGIN_DIR . 'includes/class-post-type.php';
require_once WTB_PLUGIN_DIR . 'includes/class-debug-logger.php';
require_once WTB_PLUGIN_DIR . 'includes/class-table-repository.php';
require_once WTB_PLUGIN_DIR . 'includes/class-render.php';
require_once WTB_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once WTB_PLUGIN_DIR . 'includes/class-form-shortcode.php';
require_once WTB_PLUGIN_DIR . 'includes/class-rest-controller.php';
require_once WTB_PLUGIN_DIR . 'includes/class-csv.php';
require_once WTB_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once WTB_PLUGIN_DIR . 'includes/class-elementor-form-integration.php';
require_once WTB_PLUGIN_DIR . 'includes/class-elementor-form-action.php';
require_once WTB_PLUGIN_DIR . 'includes/class-block.php';
require_once WTB_PLUGIN_DIR . 'includes/class-updater.php';

register_activation_hook( __FILE__, [ 'WTB_Activator', 'activate' ] );

add_action( 'plugins_loaded', function () {

    // Re-run schema creation when the plugin version changed (update path).
    if ( get_option( 'wtb_db_version' ) !== WTB_VERSION ) {
        WTB_Activator::activate();
    }

    WTB_Post_Type::init();
    WTB_Shortcode::init();
    WTB_Form_Shortcode::init();
    WTB_Block::init();
    WTB_Rest_Controller::init();
    WTB_Admin_Page::init();
    WTB_Elementor_Form_Integration::init();
    WTB_Updater::init();

    add_action( 'elementor/widgets/register', function ( $widgets_manager ) {
        require_once WTB_PLUGIN_DIR . 'includes/class-elementor-widget.php';
        $widgets_manager->register( new WTB_Elementor_Widget() );
    } );

    // The dynamic tag class extends an Elementor class, so it can only be
    // loaded once Elementor is active (plugins_loaded runs after all plugins).
    if ( did_action( 'elementor/loaded' ) ) {
        require_once WTB_PLUGIN_DIR . 'includes/class-elementor-dynamic-tag.php';

        add_action( 'elementor/dynamic_tags/register', function ( $dynamic_tags ) {
            $dynamic_tags->register( new WTB_Elementor_Dynamic_Tag() );
        } );
    }

    // Preconnect to Google Fonts so the font dropdown in the Elementor editor loads faster.
    add_action( 'elementor/editor/head', function () {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    } );

    // DataTables + frontend assets must also load inside the Elementor preview
    // iframe for the widget's pagination/search/length to work while editing.
    add_action( 'elementor/preview/enqueue_scripts', function () {
        WTB_Render::enqueue_frontend_assets();
    } );
} );
