<?php
/**
 * Plugin Name: Arabic Music Charts Platform
 * Plugin URI: https://github.com/kollectivco/kontent
 * Description: Phase 1 public-facing music charts experience with editorial chart pages, seeded demo data, and plugin-based routing.
 * Version: 1.0.0
 * Author: Codex
 * License: GPL2+
 * Text Domain: arabic-music-charts
 * Update URI: https://github.com/kollectivco/kontent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AMC_PLUGIN_VERSION', '1.0.0' );
define( 'AMC_PLUGIN_FILE', __FILE__ );
define( 'AMC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AMC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AMC_ROUTE_BASE', 'music-charts' );

require_once AMC_PLUGIN_DIR . 'includes/class-amc-data.php';
require_once AMC_PLUGIN_DIR . 'includes/class-amc-seeder.php';
require_once AMC_PLUGIN_DIR . 'includes/class-amc-routing.php';
require_once AMC_PLUGIN_DIR . 'includes/class-amc-updater.php';
require_once AMC_PLUGIN_DIR . 'includes/template-tags.php';
require_once AMC_PLUGIN_DIR . 'includes/class-amc-plugin.php';

register_activation_hook( AMC_PLUGIN_FILE, array( 'AMC_Plugin', 'activate' ) );
register_deactivation_hook( AMC_PLUGIN_FILE, array( 'AMC_Plugin', 'deactivate' ) );

AMC_Plugin::instance();
