<?php
/**
 * Plugin Name: WP Table Builder
 * Plugin URI:  https://example.com/wp-table-builder
 * Description: Plugin WordPress untuk membuat dan mengelola tabel kustom secara visual. Mendukung Gutenberg Block, Elementor Widget, dan Shortcode. Frontend tabel dilengkapi search, sort, filter, dan pagination via DataTables.js.
 * Version:     1.0.2
 * Author:      Your Name
 * Author URI:  https://example.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-table-builder
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WTB_VERSION',    '1.0.7' );
define( 'WTB_PLUGIN_FILE', __FILE__ );
define( 'WTB_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WTB_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

require_once WTB_PLUGIN_DIR . 'includes/class-activator.php';
require_once WTB_PLUGIN_DIR . 'includes/class-sanitizer.php';
require_once WTB_PLUGIN_DIR . 'includes/class-post-type.php';
require_once WTB_PLUGIN_DIR . 'includes/class-rest-controller.php';
require_once WTB_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once WTB_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once WTB_PLUGIN_DIR . 'includes/class-render.php';
require_once WTB_PLUGIN_DIR . 'includes/class-block.php';

register_activation_hook( __FILE__, [ 'WTB_Activator', 'activate' ] );

add_action( 'plugins_loaded', function () {
    WTB_Post_Type::init();
    WTB_Rest_Controller::init();
    WTB_Admin_Page::init();
    WTB_Shortcode::init();
    WTB_Block::init();

    add_action( 'elementor/widgets/register', function( $widgets_manager ) {
        require_once WTB_PLUGIN_DIR . 'includes/class-elementor-widget.php';
        $widgets_manager->register( new WTB_Elementor_Widget() );
    } );

    // Preconnect to Google Fonts CDN to speed up font dropdown loading in Elementor Editor
    add_action( 'elementor/editor/head', function() {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    } );

    // Enqueue DataTables & frontend assets inside the Elementor PREVIEW iframe
    // (not the admin panel) so the table widget shows pagination/search/length.
    add_action( 'elementor/preview/enqueue_scripts', function () {
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
    } );
} );
