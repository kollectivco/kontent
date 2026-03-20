<?php
/**
 * Main plugin orchestrator.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AMC_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var AMC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return AMC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_content_types' ), 5 );
		add_action( 'init', array( 'AMC_Routing', 'register_routes' ), 20 );
		add_action( 'init', array( $this, 'maybe_seed_demo_content' ), 30 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'query_vars', array( 'AMC_Routing', 'register_query_vars' ) );
		add_filter( 'template_include', array( 'AMC_Routing', 'template_include' ) );
		add_filter( 'document_title_parts', array( $this, 'filter_document_title' ) );

		AMC_Updater::boot();
		AMC_Admin::boot();
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		$plugin = self::instance();
		$plugin->register_content_types();
		AMC_Routing::register_routes();
		AMC_Seeder::seed();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Register data structures for future expansion.
	 *
	 * @return void
	 */
	public function register_content_types() {
		register_post_type(
			'amc_artist',
			array(
				'label'               => __( 'Artists', 'arabic-music-charts' ),
				'labels'              => array(
					'name'          => __( 'Artists', 'arabic-music-charts' ),
					'singular_name' => __( 'Artist', 'arabic-music-charts' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'capability_type'     => 'post',
				'has_archive'         => false,
				'rewrite'             => false,
				'publicly_queryable'  => false,
			)
		);

		register_post_type(
			'amc_track',
			array(
				'label'               => __( 'Tracks', 'arabic-music-charts' ),
				'labels'              => array(
					'name'          => __( 'Tracks', 'arabic-music-charts' ),
					'singular_name' => __( 'Track', 'arabic-music-charts' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'capability_type'     => 'post',
				'has_archive'         => false,
				'rewrite'             => false,
				'publicly_queryable'  => false,
			)
		);

		register_post_type(
			'amc_album',
			array(
				'label'               => __( 'Albums', 'arabic-music-charts' ),
				'labels'              => array(
					'name'          => __( 'Albums', 'arabic-music-charts' ),
					'singular_name' => __( 'Album', 'arabic-music-charts' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'capability_type'     => 'post',
				'has_archive'         => false,
				'rewrite'             => false,
				'publicly_queryable'  => false,
			)
		);

		register_post_type(
			'amc_chart',
			array(
				'label'               => __( 'Charts', 'arabic-music-charts' ),
				'labels'              => array(
					'name'          => __( 'Charts', 'arabic-music-charts' ),
					'singular_name' => __( 'Chart', 'arabic-music-charts' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'capability_type'     => 'post',
				'has_archive'         => false,
				'rewrite'             => false,
				'publicly_queryable'  => false,
			)
		);

		register_taxonomy(
			'amc_chart_group',
			array( 'amc_chart' ),
			array(
				'label'              => __( 'Chart Groups', 'arabic-music-charts' ),
				'public'             => false,
				'show_ui'            => false,
				'hierarchical'       => true,
				'show_in_rest'       => false,
				'publicly_queryable' => false,
				'rewrite'            => false,
			)
		);
	}

	/**
	 * Enqueue public assets on plugin routes only.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! AMC_Routing::is_plugin_route() ) {
			return;
		}

		if ( 'dashboard' === get_query_var( 'amc_route' ) ) {
			wp_enqueue_style(
				'amc-admin',
				AMC_PLUGIN_URL . 'assets/css/admin.css',
				array(),
				AMC_PLUGIN_VERSION
			);

			wp_enqueue_style(
				'amc-dashboard',
				AMC_PLUGIN_URL . 'assets/css/dashboard.css',
				array( 'amc-admin' ),
				AMC_PLUGIN_VERSION
			);

			wp_enqueue_script(
				'amc-admin',
				AMC_PLUGIN_URL . 'assets/js/admin.js',
				array(),
				AMC_PLUGIN_VERSION,
				true
			);

			wp_enqueue_script(
				'amc-dashboard',
				AMC_PLUGIN_URL . 'assets/js/dashboard.js',
				array( 'amc-admin' ),
				AMC_PLUGIN_VERSION,
				true
			);

			return;
		}

		wp_enqueue_style(
			'amc-frontend',
			AMC_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			AMC_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'amc-frontend',
			AMC_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			AMC_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * Seed demo data if plugin was copied in without activation running.
	 *
	 * @return void
	 */
	public function maybe_seed_demo_content() {
		$already_seeded = (int) get_option( 'amc_demo_seeded', 0 );
		$has_home_data  = get_page_by_path( 'top-artists', OBJECT, 'amc_chart' );

		if ( $already_seeded && $has_home_data ) {
			return;
		}

		AMC_Seeder::seed();
	}

	/**
	 * Improve document title for plugin views.
	 *
	 * @param array $title_parts Title parts.
	 * @return array
	 */
	public function filter_document_title( $title_parts ) {
		if ( ! AMC_Routing::is_plugin_route() ) {
			return $title_parts;
		}

		$route_context = AMC_Routing::get_route_context();

		if ( ! empty( $route_context['title'] ) ) {
			$title_parts['title'] = $route_context['title'];
		}

		return $title_parts;
	}
}
