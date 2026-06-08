<?php
declare(strict_types=1);
/**
 * Auto-Updater
 *
 * Hooks into the WordPress update transient to deliver
 * auto-updates from wpbuoy.com, authenticated by license key.
 *
 * @package YouSync
 */

namespace YouSync;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Updater {

	/** @var string Plugin slug (base slug, no -pro suffix). */
	private string $plugin_slug;

	/** @var string Plugin file relative to wp-content/plugins/. */
	private string $plugin_file;

	/** @var string Update-check endpoint URL. */
	private string $update_check_url;

	/** @var string Transient key for caching the update response. */
	private string $cache_key;

	/**
	 * @param string $plugin_slug  Base plugin slug (must match WC product slug).
	 * @param string $plugin_file  Relative plugin file path (e.g. "yousync/yousync.php").
	 * @param string $server_base  Base URL of the wpbuoy license server.
	 */
	public function __construct( string $plugin_slug, string $plugin_file, string $server_base ) {
		$this->plugin_slug      = $plugin_slug;
		$this->plugin_file      = $plugin_file;
		$this->update_check_url = untrailingslashit( $server_base ) . '/wp-json/wpbylm/v1/update-check';
		$this->cache_key        = 'yousync_update_' . md5( $plugin_slug );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'purge_cache' ), 10, 2 );
		add_action( 'wpbuoy_license_deactivated', array( $this, 'on_license_deactivated' ) );
		add_action( 'wpbuoy_license_activated', array( $this, 'on_license_activated' ) );
	}

	/**
	 * Inject update data into the update_plugins transient.
	 *
	 * Registers in $transient->response when an update is available,
	 * or $transient->no_update when current — the latter ensures
	 * "View Details" is always accessible in wp-admin.
	 */
	public function inject_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$license_key = yousync_license()->get_license_key();
		if ( empty( $license_key ) ) {
			delete_transient( $this->cache_key );
			unset( $transient->response[ $this->plugin_file ] );
			return $transient;
		}

		$info = $this->fetch_update_info();
		if ( ! $info ) {
			return $transient;
		}

		$plugin_data = (object) array(
			'slug'         => $this->plugin_slug,
			'plugin'       => $this->plugin_file,
			'new_version'  => $info['version'] ?? YOUSYNC_VERSION,
			'url'          => '',
			'package'      => $info['download_url'] ?? '',
			'tested'       => $info['tested'] ?? '',
			'requires'     => $info['requires'] ?? '',
			'requires_php' => $info['requires_php'] ?? '',
			'icons'        => $info['icons'] ?? array(),
			'banners'      => $info['banners'] ?? array(),
		);

		if ( isset( $info['version'] ) && version_compare( YOUSYNC_VERSION, $info['version'], '<' ) ) {
			$transient->response[ $this->plugin_file ] = $plugin_data;
		} else {
			$transient->no_update[ $this->plugin_file ] = $plugin_data;
		}

		return $transient;
	}

	/**
	 * Supply plugin details for the "View Details" thickbox modal.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$info = $this->fetch_update_info();
		if ( ! $info ) {
			return $result;
		}

		return (object) array(
			'name'          => 'YouSync Pro',
			'slug'          => $this->plugin_slug,
			'version'       => $info['version'] ?? YOUSYNC_VERSION,
			'author'        => $info['author'] ?? '',
			'homepage'      => $info['homepage'] ?? '',
			'requires'      => $info['requires'] ?? '6.0',
			'requires_php'  => $info['requires_php'] ?? '7.4',
			'tested'        => $info['tested'] ?? '',
			'last_updated'  => $info['last_updated'] ?? '',
			'download_link' => $info['download_url'] ?? '',
			'banners'       => $info['banners'] ?? array(),
			'icons'         => $info['icons'] ?? array(),
			'sections'      => $info['sections'] ?? array( 'changelog' => '' ),
		);
	}

	/**
	 * Clear cached update info after this plugin is updated.
	 */
	public function purge_cache( $upgrader, $options ) {
		if (
			'update' === $options['action'] &&
			'plugin' === $options['type'] &&
			! empty( $options['plugins'] ) &&
			in_array( $this->plugin_file, $options['plugins'], true )
		) {
			delete_transient( $this->cache_key );
		}
	}

	/**
	 * Purge cached update data when the license is deactivated.
	 */
	public function on_license_deactivated( string $slug ) {
		if ( $slug !== $this->plugin_slug ) {
			return;
		}
		delete_transient( $this->cache_key );
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * Purge cached update data when the license is activated.
	 */
	public function on_license_activated( string $slug ) {
		if ( $slug !== $this->plugin_slug ) {
			return;
		}
		delete_transient( $this->cache_key );
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * Fetch update metadata from the server, cached for 12 hours.
	 *
	 * @return array|null
	 */
	private function fetch_update_info() {
		$cached = get_transient( $this->cache_key );
		if ( false !== $cached ) {
			return $cached ?: null;
		}

		$license_key = yousync_license()->get_license_key();
		if ( empty( $license_key ) ) {
			return null;
		}

		$response = wp_remote_get(
			add_query_arg(
				array(
					'plugin_slug' => rawurlencode( $this->plugin_slug ),
					'license_key' => rawurlencode( $license_key ),
					'version'     => YOUSYNC_VERSION,
				),
				$this->update_check_url
			),
			array( 'timeout' => 15 )
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $this->cache_key, array(), HOUR_IN_SECONDS );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['success'] ) ) {
			set_transient( $this->cache_key, array(), HOUR_IN_SECONDS );
			return null;
		}

		set_transient( $this->cache_key, $body, 12 * HOUR_IN_SECONDS );
		return $body;
	}
}
