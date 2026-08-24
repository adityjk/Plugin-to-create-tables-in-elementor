<?php
/**
 * REST route table: namespace, paths, methods, permission checks.
 *
 * Registration only — every callback delegates to WTB_Rest_Tables
 * (admin-gated) or WTB_Rest_Submissions (public). The split exists
 * because the two security postures must stay visually separate.
 *
 * @package WTB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTB_Rest_Routes {

	const NAMESPACE_V1 = 'wtb/v1';

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register' ] );
	}

	public static function register() {
		self::admin_route( '/tables', WP_REST_Server::READABLE, 'list_tables' );
		self::admin_route( '/tables', WP_REST_Server::CREATABLE, 'create_table' );
		self::admin_route(
			'/tables/(?P<id>\d+)',
			WP_REST_Server::READABLE,
			'get_table'
		);
		self::admin_route(
			'/tables/(?P<id>\d+)',
			WP_REST_Server::CREATABLE,
			'save_table'
		);
		self::admin_route(
			'/tables/(?P<id>\d+)',
			WP_REST_Server::DELETABLE,
			'delete_table'
		);
		self::admin_route(
			'/tables/(?P<id>\d+)/duplicate',
			WP_REST_Server::CREATABLE,
			'duplicate_table'
		);

		self::public_route(
			'/data/(?P<id>\d+)',
			WP_REST_Server::READABLE,
			'data'
		);
		self::public_route(
			'/submit/(?P<id>\d+)',
			WP_REST_Server::CREATABLE,
			'submit'
		);
		self::public_route(
			'/row-count/(?P<id>\d+)',
			WP_REST_Server::READABLE,
			'row_count'
		);
	}

	/**
	 * Capability gate shared by every admin-side route.
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	private static function admin_route( $route, $method, $handler ) {
		register_rest_route(
			self::NAMESPACE_V1,
			$route,
			[
				'methods'             => $method,
				'callback'            => [ 'WTB_Rest_Tables', $handler ],
				'permission_callback' => [ __CLASS__, 'can_manage' ],
			]
		);
	}

	private static function public_route( $route, $method, $handler ) {
		register_rest_route(
			self::NAMESPACE_V1,
			$route,
			[
				'methods'             => $method,
				'callback'            => [ 'WTB_Rest_Submissions', $handler ],
				// Public by design; abuse is controlled inside the
				// handlers (rate limiting, nonce on submit).
				'permission_callback' => '__return_true',
			]
		);
	}
}
