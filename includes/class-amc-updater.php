<?php
/**
 * GitHub-powered plugin updater.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AMC_Updater {
	/**
	 * GitHub repository.
	 */
	const REPO = 'kollectivco/kontent';

	/**
	 * Bootstrap hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_post_install', array( __CLASS__, 'after_install' ), 10, 3 );
	}

	/**
	 * Add update data from GitHub release/tag metadata.
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public static function inject_update( $transient ) {
		if ( empty( $transient->checked ) || ! is_object( $transient ) ) {
			return $transient;
		}

		$plugin_basename = plugin_basename( AMC_PLUGIN_FILE );
		$plugin_data     = get_plugin_data( AMC_PLUGIN_FILE, false, false );
		$release         = self::get_latest_release();

		if ( empty( $release['version'] ) || version_compare( $release['version'], $plugin_data['Version'], '<=' ) ) {
			return $transient;
		}

		$transient->response[ $plugin_basename ] = (object) array(
			'slug'        => dirname( $plugin_basename ),
			'plugin'      => $plugin_basename,
			'new_version' => $release['version'],
			'url'         => 'https://github.com/' . self::REPO,
			'package'     => $release['package'],
			'tested'      => isset( $release['tested'] ) ? $release['tested'] : '',
			'requires'    => isset( $release['requires'] ) ? $release['requires'] : '',
			'icons'       => array(),
			'banners'     => array(),
		);

		return $transient;
	}

	/**
	 * Supply modal details for plugin info.
	 *
	 * @param false|object|array $result Existing result.
	 * @param string             $action API action.
	 * @param object             $args API args.
	 * @return false|object|array
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}

		$plugin_basename = plugin_basename( AMC_PLUGIN_FILE );
		$plugin_slug     = dirname( $plugin_basename );

		if ( $args->slug !== $plugin_slug ) {
			return $result;
		}

		$plugin_data = get_plugin_data( AMC_PLUGIN_FILE, false, false );
		$release     = self::get_latest_release();

		return (object) array(
			'name'          => $plugin_data['Name'],
			'slug'          => $plugin_slug,
			'version'       => ! empty( $release['version'] ) ? $release['version'] : $plugin_data['Version'],
			'author'        => '<a href="https://github.com/kollectivco">kollectivco</a>',
			'homepage'      => 'https://github.com/' . self::REPO,
			'requires'      => ! empty( $release['requires'] ) ? $release['requires'] : '6.0',
			'tested'        => ! empty( $release['tested'] ) ? $release['tested'] : '',
			'download_link' => ! empty( $release['package'] ) ? $release['package'] : '',
			'sections'      => array(
				'description' => '<p>Kontentainment Charts is a chart publishing plugin with premium public pages and a plugin-owned control center.</p>',
				'changelog'   => '<p>' . esc_html( ! empty( $release['body'] ) ? $release['body'] : 'See GitHub releases for change history.' ) . '</p>',
			),
		);
	}

	/**
	 * Keep plugin active path stable after GitHub zip extraction.
	 *
	 * @param bool  $response Install response.
	 * @param array $hook_extra Hook data.
	 * @param array $result Install result.
	 * @return array
	 */
	public static function after_install( $response, $hook_extra, $result ) {
		if ( empty( $hook_extra['plugin'] ) || plugin_basename( AMC_PLUGIN_FILE ) !== $hook_extra['plugin'] ) {
			return $result;
		}

		global $wp_filesystem;

		$plugin_dir_name = dirname( plugin_basename( AMC_PLUGIN_FILE ) );
		$target          = WP_PLUGIN_DIR . '/' . $plugin_dir_name;

		if ( ! empty( $result['destination'] ) && ! empty( $wp_filesystem ) ) {
			$wp_filesystem->move( $result['destination'], $target, true );
			$result['destination'] = $target;
		}

		if ( is_plugin_active( plugin_basename( AMC_PLUGIN_FILE ) ) ) {
			activate_plugin( plugin_basename( AMC_PLUGIN_FILE ) );
		}

		return $result;
	}

	/**
	 * Fetch latest release or tag.
	 *
	 * @return array
	 */
	private static function get_latest_release() {
		$cache_key = 'amc_github_release_data';
		$cached    = get_site_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'Kontentainment-Charts-Plugin',
		);

		$response = wp_remote_get( 'https://api.github.com/repos/' . self::REPO . '/releases/latest', array( 'headers' => $headers, 'timeout' => 15 ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$response = wp_remote_get( 'https://api.github.com/repos/' . self::REPO . '/tags', array( 'headers' => $headers, 'timeout' => 15 ) );

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return array();
			}

			$tags = json_decode( wp_remote_retrieve_body( $response ), true );
			$tag  = ! empty( $tags[0]['name'] ) ? $tags[0]['name'] : '';

			$data = array(
				'version' => ltrim( $tag, 'v' ),
				'package' => 'https://github.com/' . self::REPO . '/archive/refs/tags/' . rawurlencode( $tag ) . '.zip',
				'body'    => 'Tagged release from GitHub.',
			);

			set_site_transient( $cache_key, $data, 6 * HOUR_IN_SECONDS );
			return $data;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $release['tag_name'] ) ) {
			return array();
		}

		$data = array(
			'version'  => ltrim( $release['tag_name'], 'v' ),
			'package'  => ! empty( $release['zipball_url'] ) ? $release['zipball_url'] : '',
			'body'     => ! empty( $release['body'] ) ? wp_strip_all_tags( $release['body'] ) : '',
			'requires' => '6.0',
			'tested'   => get_bloginfo( 'version' ),
		);

		set_site_transient( $cache_key, $data, 6 * HOUR_IN_SECONDS );

		return $data;
	}
}
