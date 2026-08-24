<?php
/**
 * Plugin Name:       WP Table Builder
 * Description:       Visual data tables with CSV, forms, Elementor.
 * Version:           1.0.0
 * Author:            adityjk
 * License:           GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       wp-table-builder
 *
 * Bootstrap only: constants, requires, hook wiring. No business
 * logic lives in this file - classes own their own hooks via ::init().
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WTB_VERSION', '1.0.0' );
define( 'WTB_PLUGIN_FILE', __FILE__ );
define( 'WTB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WTB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/*
 * Version lives in three places that must move together on every
 * release: the header above, WTB_VERSION here, and the packaging
 * script's filename argument. See DESIGN.md "Update mechanism".
 */

foreach (
	[
		'class-activator',
		'class-sanitizer',
		'class-table-post-type',
		'class-table-storage',
		'class-table-renderer',
		'class-table-css',
		'class-shortcode',
		'class-form-shortcode',
		'class-block',
		'class-rest-routes',
		'class-rest-tables',
		'class-rest-submissions',
		'class-csv-export',
		'class-csv-import',
		'class-debug-log',
		'class-admin-menu',
		'class-admin-table-list',
		'class-admin-table-editor',
		// Extends nothing, so it loads regardless of Elementor/Pro.
		'class-elementor-form-hook',
	]
	as $wtb_class_file
) {
	require_once WTB_PLUGIN_DIR . 'includes/' . $wtb_class_file . '.php';
}

register_activation_hook(
	WTB_PLUGIN_FILE,
	[ 'WTB_Activator', 'activate' ]
);

WTB_Table_Post_Type::init();
WTB_Shortcode::init();
WTB_Form_Shortcode::init();
WTB_Block::init();
WTB_Rest_Routes::init();
WTB_Csv_Export::init();
WTB_Csv_Import::init();
WTB_Debug_Log::init();
WTB_Admin_Menu::init();

// Pro detection happens inside the late-firing callback (the v1 fix).
WTB_Elementor_Form_Hook::init();

/**
 * Frontend handles. Registered only; shortcode/block/widget render
 * calls enqueue them where a table actually appears.
 */
function wtb_register_frontend_assets() {
	$vendor_version = '1.13.8';

	wp_register_style(
		'wtb-datatables',
		WTB_PLUGIN_URL . 'assets/vendor/jquery.dataTables.min.css',
		[],
		$vendor_version
	);
	wp_register_script(
		'wtb-datatables',
		WTB_PLUGIN_URL . 'assets/vendor/jquery.dataTables.min.js',
		[ 'jquery' ],
		$vendor_version,
		true
	);

	wp_register_style(
		'wtb-frontend',
		WTB_PLUGIN_URL . 'assets/css/frontend.css',
		[ 'wtb-datatables' ],
		WTB_VERSION
	);
	wp_register_script(
		'wtb-frontend',
		WTB_PLUGIN_URL . 'assets/js/frontend.js',
		[ 'jquery', 'wtb-datatables' ],
		WTB_VERSION,
		true
	);
}
add_action(
	'wp_enqueue_scripts',
	'wtb_register_frontend_assets'
);

/**
 * Block-editor handle; WTB_Block names it as its editor_script.
 */
function wtb_register_block_editor_assets() {
	wp_register_script(
		'wtb-block-editor',
		WTB_PLUGIN_URL . 'assets/js/block-editor.js',
		[
			'wp-blocks',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-api-fetch',
			'wp-server-side-render',
			'wp-i18n',
		],
		WTB_VERSION,
		true
	);
}
add_action(
	'enqueue_block_editor_assets',
	'wtb_register_block_editor_assets'
);

/**
 * Admin handles, registered before default-priority enqueues.
 */
function wtb_register_admin_assets() {
	wp_register_style(
		'wtb-admin',
		WTB_PLUGIN_URL . 'assets/css/admin.css',
		[],
		WTB_VERSION
	);

	/*
	 * The builder is three layers sharing one namespace: base
	 * (REST/DOM plumbing), cells + settings panel (stateless widget
	 * factories), builder (views). Only the last handle gets enqueued.
	 */
	wp_register_script(
		'wtb-admin-base',
		WTB_PLUGIN_URL . 'assets/js/admin-base.js',
		[ 'jquery' ],
		WTB_VERSION,
		true
	);
	wp_register_script(
		'wtb-admin-cells',
		WTB_PLUGIN_URL . 'assets/js/admin-cells.js',
		[ 'wtb-admin-base' ],
		WTB_VERSION,
		true
	);
	wp_register_script(
		'wtb-admin-settings-panel',
		WTB_PLUGIN_URL . 'assets/js/admin-settings-panel.js',
		[ 'wtb-admin-base' ],
		WTB_VERSION,
		true
	);
	wp_register_script(
		'wtb-admin-builder',
		WTB_PLUGIN_URL . 'assets/js/admin-builder.js',
		[
			'jquery',
			'wtb-admin-base',
			'wtb-admin-cells',
			'wtb-admin-settings-panel',
		],
		WTB_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'wtb_register_admin_assets', 0 );

/**
 * Widget + dynamic tag extend Elementor classes, so their requires
 * must wait until Elementor is definitely loaded - whichever direction
 * the load order goes.
 */
function wtb_load_elementor_integration() {
	require_once WTB_PLUGIN_DIR
		. 'includes/class-elementor-widget.php';
	require_once WTB_PLUGIN_DIR
		. 'includes/class-elementor-dynamic-tag.php';

	WTB_Elementor_Dynamic_Tag::init();

	add_action(
		'elementor/widgets/register',
		function ( $widgets_manager ) {
			$widgets_manager->register( new \WTB_Elementor_Widget() );
		}
	);
}

if ( did_action( 'elementor/loaded' ) ) {
	wtb_load_elementor_integration();
} else {
	add_action(
		'elementor/loaded',
		'wtb_load_elementor_integration'
	);
}

/**
 * Self-hosted updates through GitHub Releases via plugin-update-checker.
 * vendor/ is a runtime dependency: packaging must never exclude it,
 * and a missing copy degrades to "no update checks", never a fatal.
 */
$wtb_update_checker_file = WTB_PLUGIN_DIR
	. 'vendor/plugin-update-checker/plugin-update-checker.php';

if (
	file_exists( $wtb_update_checker_file )
	&& class_exists( 'Puc_v4_Factory' )
) {
	$wtb_update_checker = Puc_v4_Factory::buildUpdateChecker(
		apply_filters(
			'wtb_update_repository',
			'https://github.com/adityjk/Plugin-to-create-tables-in-elementor/'
		),
		WTB_PLUGIN_FILE,
		'wp-table-builder'
	);

	$wtb_update_checker->setBranch( 'v2-development' );
}
