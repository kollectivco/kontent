<?php
/**
 * Plugin Name: Kontentainment Charts
 * Plugin URI: https://github.com/kollectivco/kontent
 * Description: Public-facing charts platform and control center for Kontentainment Charts.
 * Version: 3.2.0
 * Author: Codex
 * License: GPL2+
 * Text Domain: arabic-music-charts
 * Update URI: https://github.com/kollectivco/kontent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AMC_PLUGIN_VERSION', '3.2.0' );
define( 'AMC_PLUGIN_FILE', __FILE__ );
define( 'AMC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AMC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AMC_ROUTE_BASE', 'music-charts' );

require_once AMC_PLUGIN_DIR . 'includes/admin/class-amc-admin-data.php';
require_once AMC_PLUGIN_DIR . 'includes/admin/class-amc-admin.php';
require_once AMC_PLUGIN_DIR . 'includes/class-amc-capabilities.php';
require_once AMC_PLUGIN_DIR . 'includes/class-amc-db.php';
require_once AMC_PLUGIN_DIR . 'includes/class-amc-data.php';
require_once AMC_PLUGIN_DIR . 'includes/class-amc-ingestion.php';
require_once AMC_PLUGIN_DIR . 'includes/class-amc-seeder.php';
require_once AMC_PLUGIN_DIR . 'includes/class-amc-routing.php';
require_once AMC_PLUGIN_DIR . 'includes/class-amc-updater.php';
require_once AMC_PLUGIN_DIR . 'includes/template-tags.php';
require_once AMC_PLUGIN_DIR . 'includes/class-amc-plugin.php';

register_activation_hook( AMC_PLUGIN_FILE, array( 'AMC_Plugin', 'activate' ) );
register_deactivation_hook( AMC_PLUGIN_FILE, array( 'AMC_Plugin', 'deactivate' ) );

AMC_Plugin::instance();
