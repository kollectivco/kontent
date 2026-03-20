<?php
/**
 * Public route registration and template resolution.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AMC_Routing {
	/**
	 * Register rewrite rules.
	 *
	 * @return void
	 */
	public static function register_routes() {
		add_rewrite_rule( '^' . AMC_ROUTE_BASE . '/?$', 'index.php?amc_route=home', 'top' );
		add_rewrite_rule( '^' . AMC_ROUTE_BASE . '/charts/?$', 'index.php?amc_route=charts', 'top' );
		add_rewrite_rule( '^' . AMC_ROUTE_BASE . '/charts/([^/]+)/?$', 'index.php?amc_route=chart&amc_chart=$matches[1]', 'top' );
		add_rewrite_rule( '^' . AMC_ROUTE_BASE . '/track/([^/]+)/?$', 'index.php?amc_route=track&amc_track=$matches[1]', 'top' );
		add_rewrite_rule( '^' . AMC_ROUTE_BASE . '/artist/([^/]+)/?$', 'index.php?amc_route=artist&amc_artist=$matches[1]', 'top' );
		add_rewrite_rule( '^charts-dashboard/?$', 'index.php?amc_route=dashboard&amc_dashboard=dashboard', 'top' );
		add_rewrite_rule( '^charts-dashboard/([^/]+)/?$', 'index.php?amc_route=dashboard&amc_dashboard=$matches[1]', 'top' );
	}

	/**
	 * Register query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = 'amc_route';
		$vars[] = 'amc_chart';
		$vars[] = 'amc_track';
		$vars[] = 'amc_artist';
		$vars[] = 'amc_dashboard';

		return $vars;
	}

	/**
	 * Whether current request belongs to plugin.
	 *
	 * @return bool
	 */
	public static function is_plugin_route() {
		return (bool) get_query_var( 'amc_route' );
	}

	/**
	 * Route metadata for title/body state.
	 *
	 * @return array
	 */
	public static function get_route_context() {
		$route = get_query_var( 'amc_route' );

		switch ( $route ) {
			case 'home':
				return array( 'title' => 'Kontentainment Charts' );
			case 'charts':
				return array( 'title' => 'Charts Index' );
			case 'chart':
				$chart = AMC_Data::get_chart( get_query_var( 'amc_chart' ) );
				return array( 'title' => $chart ? $chart['title'] : 'Chart' );
			case 'track':
				$track = AMC_Data::get_track_by_slug( get_query_var( 'amc_track' ) );
				return array( 'title' => $track ? $track['name'] : 'Track' );
			case 'artist':
				$artist = AMC_Data::get_artist_by_slug( get_query_var( 'amc_artist' ) );
				return array( 'title' => $artist ? $artist['name'] : 'Artist' );
			case 'dashboard':
				$sections = AMC_Admin_Data::dashboard_sections();
				$key      = get_query_var( 'amc_dashboard' ) ? get_query_var( 'amc_dashboard' ) : 'dashboard';
				return array( 'title' => ! empty( $sections[ $key ]['title'] ) ? 'Kontentainment Charts - ' . $sections[ $key ]['title'] : 'Kontentainment Charts - Dashboard' );
			default:
				return array( 'title' => get_bloginfo( 'name' ) );
		}
	}

	/**
	 * Swap in plugin templates for plugin routes.
	 *
	 * @param string $template Existing template.
	 * @return string
	 */
	public static function template_include( $template ) {
		$route = get_query_var( 'amc_route' );

		if ( ! $route ) {
			return $template;
		}

		switch ( $route ) {
			case 'home':
				return AMC_PLUGIN_DIR . 'templates/home.php';
			case 'charts':
				return AMC_PLUGIN_DIR . 'templates/charts-index.php';
			case 'chart':
				return AMC_PLUGIN_DIR . 'templates/chart-page.php';
			case 'track':
				return AMC_PLUGIN_DIR . 'templates/track-single.php';
			case 'artist':
				return AMC_PLUGIN_DIR . 'templates/artist-single.php';
			case 'dashboard':
				return AMC_PLUGIN_DIR . 'templates/dashboard.php';
			default:
				return $template;
		}
	}
}
