<?php
/**
 * Lightweight option-based log for diagnosing form submissions.
 *
 * Exists because "the form did nothing" reports come with no server
 * context; every accepted submission appends one line here so an
 * admin can confirm what was stored and when. Writes happen once per
 * submission and reads happen only in the admin viewer, so the option
 * is registered without autoload. Capped because an unbounded log in
 * wp_options is how slow sites are born.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Debug_Log {

	const OPTION_KEY = 'wtb_debug_log';
	const CAP        = 100;

	public static function init() {
		add_action(
			'admin_post_wtb_clear_log',
			[ __CLASS__, 'handle_clear' ]
		);
	}

	/**
	 * Prepend one entry; oldest fall off the end past the cap.
	 */
	public static function add( $message ) {
		$log = get_option( self::OPTION_KEY, [] );
		$log = is_array( $log ) ? $log : [];

		array_unshift(
			$log,
			[
				'time'    => time(),
				'message' => (string) $message,
			]
		);

		update_option(
			self::OPTION_KEY,
			array_slice( $log, 0, self::CAP ),
			false
		);
	}

	/**
	 * Newest first, matching the write order. Entries escaped at
	 * print time by whichever view renders them.
	 */
	public static function all() {
		$log = get_option( self::OPTION_KEY, [] );

		return is_array( $log ) ? $log : [];
	}

	public static function clear() {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Viewer link target; the nonce travels in the URL like any
	 * admin-post action.
	 */
	public static function clear_url() {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=wtb_clear_log' ),
			'wtb_clear_log'
		);
	}

	public static function handle_clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Not allowed.', 'wp-table-builder' ),
				403
			);
		}

		check_admin_referer( 'wtb_clear_log' );

		self::clear();

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=wtb-tables' );
		}

		wp_safe_redirect( $redirect );
		exit;
	}
}
