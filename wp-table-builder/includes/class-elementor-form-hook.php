<?php
/**
 * Registers the table-insert form action with Elementor Pro.
 *
 * Deliberately split from the action class: this file extends nothing,
 * so it can be loaded while only free Elementor is active, and every
 * Pro-specific reference sits behind a runtime guard inside the
 * callback. Checking here - when Pro itself fires the hook - rather
 * than on plugins_loaded is the fix for v1's silent registration
 * failure, where detection raced Pro's load order.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Elementor_Form_Hook {

	public static function init() {
		add_action(
			'elementor_pro/forms/actions/register',
			[ __CLASS__, 'register_action' ]
		);
	}

	public static function register_action( $actions_manager ) {
		if (
			! class_exists( '\ElementorPro\Plugin' )
			|| ! class_exists(
				'\ElementorPro\Modules\Forms\Classes\Action_Base'
			)
		) {
			return;
		}

		require_once __DIR__ . '/class-elementor-form-action.php';

		$actions_manager->register( new WTB_Elementor_Form_Action() );
	}
}
