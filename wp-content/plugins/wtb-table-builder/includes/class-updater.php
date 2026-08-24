<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WTB_Updater {

	const GITHUB_REPO = 'adityjk/Plugin-to-create-tables-in-elementor';
	const CACHE_KEY   = 'wtb_github_latest_release';
	const CACHE_TTL   = 12 * HOUR_IN_SECONDS;
	const ERROR_TTL   = 15 * MINUTE_IN_SECONDS;

	/**
	 * Register WordPress updater hooks.
	 */
	public static function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', [ __CLASS__, 'inject_update' ] );
		add_filter( 'plugins_api', [ __CLASS__, 'plugin_information' ], 20, 3 );
		add_action( 'upgrader_process_complete', [ __CLASS__, 'clear_cache' ], 10, 2 );
	}

	/**
	 * Plugin folder name (slug).
	 */
	private static function slug(): string {
		return basename( dirname( WTB_PLUGIN_FILE ) );
	}

	/**
	 * Full plugin basename, e.g. wtb-table-builder/wtb-table-builder.php
	 */
	private static function basename(): string {
		return plugin_basename( WTB_PLUGIN_FILE );
	}

	/**
	 * Fetch the latest release metadata from GitHub Releases API.
	 *
	 * @return array|null Release data or null when unavailable.
	 */
	private static function get_release(): ?array {
		$cached = get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		if ( is_array( $cached ) && empty( $cached ) ) {
			return null; // Recent failed lookup, negative cache still fresh.
		}

		$response = wp_remote_get(
			sprintf( 'https://api.github.com/repos/%s/releases/latest', self::GITHUB_REPO ),
			[
				'timeout' => 10,
				'headers' => [ 'Accept' => 'application/vnd.github+json' ],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			WTB_Debug_Logger::log( 'GitHub release check gagal.', 'ERROR', [
				'reason' => is_wp_error( $response )
					? $response->get_error_message()
					: 'HTTP ' . wp_remote_retrieve_response_code( $response ),
			] );
			set_transient( self::CACHE_KEY, [], self::ERROR_TTL );
			return null;
		}

		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$release = self::parse_release( is_array( $data ) ? $data : [] );

		if ( null === $release ) {
			WTB_Debug_Logger::log( 'Release GitHub terbaru tidak valid atau tidak memiliki asset ZIP.', 'WARN' );
			set_transient( self::CACHE_KEY, [], self::ERROR_TTL );
			return null;
		}

		set_transient( self::CACHE_KEY, $release, self::CACHE_TTL );

		return $release;
	}

	/**
	 * Extract relevant fields from a GitHub release payload.
	 *
	 * @param array $data Raw GitHub release JSON.
	 * @return array|null
	 */
	private static function parse_release( array $data ): ?array {
		$version = ltrim( (string) ( $data['tag_name'] ?? '' ), 'vV' );

		if ( '' === $version || ! preg_match( '/^\d/', $version ) ) {
			return null;
		}

		$package = '';

		foreach ( ( $data['assets'] ?? [] ) as $asset ) {
			$name    = strtolower( (string) ( $asset['name'] ?? '' ) );
			$url     = (string) ( $asset['browser_download_url'] ?? '' );
			$is_zip  = '.zip' === substr( $name, -4 );

			if ( ! $is_zip || '' === $url ) {
				continue;
			}

			if ( '' === $package || 0 === strpos( $name, strtolower( self::slug() ) ) ) {
				$package = $url;
			}
		}

		if ( '' === $package ) {
			return null;
		}

		return [
			'version'      => $version,
			'download_url' => $package,
			'changelog'    => (string) ( $data['body'] ?? '' ),
			'published_at' => (string) ( $data['published_at'] ?? gmdate( 'c' ) ),
			'html_url'     => (string) ( $data['html_url'] ?? 'https://github.com/' . self::GITHUB_REPO ),
		];
	}

	/**
	 * Build the plugin info object consumed by WordPress core.
	 *
	 * @param array|null $release Parsed release data, or null for local fallback.
	 * @return object
	 */
	private static function to_plugin_info( ?array $release ): object {
		$version      = $release['version'] ?? WTB_VERSION;
		$download_url = $release['download_url'] ?? '';
		$changelog    = $release['changelog'] ?? '';

		return (object) [
			'name'          => 'WP Table Builder',
			'slug'          => self::slug(),
			'plugin'        => self::basename(),
			'version'       => $version,
			'new_version'   => $version,
			'url'           => $release['html_url'] ?? 'https://github.com/' . self::GITHUB_REPO,
			'package'       => $download_url,
			'download_link' => $download_url,
			'requires'      => '5.0',
			'requires_php'  => '7.1',
			'tested'        => get_bloginfo( 'version' ),
			'last_updated'  => $release['published_at'] ?? gmdate( 'c' ),
			'sections'      => [
				'description' => '<p>Plugin visual untuk membuat dan mengelola tabel kustom di WordPress. Mendukung Gutenberg Block, Elementor Widget, dan Shortcode dengan search, sort, filter, serta pagination via DataTables.js.</p>',
				'changelog'   => '<p><strong>Versi ' . esc_html( $version ) . '</strong></p>'
					. wpautop( wp_kses_post( $changelog ) ),
			],
		];
	}

	/**
	 * Inject update data into the update_plugins transient.
	 *
	 * The slug "wp-table-builder" also exists on wordpress.org (a different
	 * plugin by dotcamp), so core's own check would otherwise offer to
	 * "update" this plugin to that one. This basename is therefore claimed
	 * exclusively: any wordpress.org entry is stripped before our own data,
	 * when available, replaces it.
	 *
	 * @param object|false $transient Update transient.
	 * @return object|false
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		unset(
			$transient->response[ self::basename() ],
			$transient->no_update[ self::basename() ]
		);

		$release = self::get_release();

		if ( null === $release ) {
			return $transient;
		}

		if ( version_compare( WTB_VERSION, $release['version'], '>=' ) ) {
			$transient->no_update[ self::basename() ] = self::to_plugin_info( $release );
		} else {
			$transient->response[ self::basename() ] = self::to_plugin_info( $release );
		}

		return $transient;
	}

	/**
	 * Provide data for the "View details" modal on the Plugins page.
	 *
	 * @param false|object|array $result Default false.
	 * @param string             $action Requested action.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object|array
	 */
	public static function plugin_information( $result, string $action, $args ) {
		if ( 'plugin_information' !== $action || self::slug() !== ( $args->slug ?? '' ) ) {
			return $result;
		}

		// Always short-circuit: falling through would let the wordpress.org
		// API return details for dotcamp's unrelated "wp-table-builder".
		return self::to_plugin_info( self::get_release() );
	}

	/**
	 * Purge cached release data after any plugin install/update finishes.
	 */
	public static function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
	}
}
