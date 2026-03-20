<?php
/**
 * Custom dashboard template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$amc_page_title = AMC_Routing::get_route_context()['title'];
$amc_body_class = 'amc-dashboard-page';

include AMC_PLUGIN_DIR . 'templates/parts/document-start.php';
AMC_Admin::render_custom_dashboard();
include AMC_PLUGIN_DIR . 'templates/parts/document-end.php';
