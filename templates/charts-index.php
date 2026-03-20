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
			<p>These chart destinations are dynamically driven from plugin chart definitions and real plugin data, so future admin management can extend them without rebuilding the frontend.</p>
		</div>
	</section>

	<section class="amc-section">
		<div class="amc-container">
			<div class="amc-card-grid">
				<?php if ( $charts ) : ?>
					<?php foreach ( $charts as $chart ) : ?>
						<?php include AMC_PLUGIN_DIR . 'templates/parts/chart-card.php'; ?>
					<?php endforeach; ?>
				<?php else : ?>
					<p>No chart categories are active yet. Add charts and publish chart weeks from the Kontentainment Charts dashboard to populate this page.</p>
				<?php endif; ?>
			</div>
		</div>
	</section>
</main>
<?php
include AMC_PLUGIN_DIR . 'templates/parts/site-footer.php';
include AMC_PLUGIN_DIR . 'templates/parts/document-end.php';
