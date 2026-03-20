<?php
/**
 * Charts index template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$charts          = AMC_Data::get_all_charts();
$amc_page_title  = 'Charts Index';
$amc_body_class  = 'amc-charts-index';

include AMC_PLUGIN_DIR . 'templates/parts/document-start.php';
include AMC_PLUGIN_DIR . 'templates/parts/site-header.php';
?>
<main class="amc-main">
	<section class="amc-page-hero">
		<div class="amc-container">
			<p class="amc-section-label">All Charts</p>
			<h1>Every list lives on its own route, ready for future categories.</h1>
			<p>These chart destinations are dynamically driven from plugin chart definitions and seeded WordPress content, so future admin management can extend them without rebuilding the frontend.</p>
		</div>
	</section>

	<section class="amc-section">
		<div class="amc-container">
			<div class="amc-card-grid">
				<?php foreach ( $charts as $chart ) : ?>
					<?php include AMC_PLUGIN_DIR . 'templates/parts/chart-card.php'; ?>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
</main>
<?php
include AMC_PLUGIN_DIR . 'templates/parts/site-footer.php';
include AMC_PLUGIN_DIR . 'templates/parts/document-end.php';
