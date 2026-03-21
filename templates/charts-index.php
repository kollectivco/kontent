<?php
/**
 * Charts index template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$charts          = AMC_Data::get_all_charts();
$public_state    = AMC_Data::public_state();
$amc_page_title  = 'Charts Index';
$amc_body_class  = 'amc-charts-index';

include AMC_PLUGIN_DIR . 'templates/parts/document-start.php';
include AMC_PLUGIN_DIR . 'templates/parts/site-header.php';
?>
<main class="amc-main">
	<section class="amc-page-hero">
		<div class="amc-container">
			<p class="amc-section-label">All Charts</p>
			<h1><?php echo esc_html( $public_state['has_charts'] ? 'Every list lives on its own route, ready for live weekly updates.' : 'Chart routes are ready for the first production setup.' ); ?></h1>
			<p><?php echo esc_html( $public_state['has_charts'] ? 'These chart destinations are dynamically driven from plugin chart definitions and real plugin data, so future admin management can extend them without rebuilding the frontend.' : 'No chart categories have been created yet. The public index stays online and branded while the dashboard guides first-time setup.' ); ?></p>
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
					<p>No chart categories are active yet. Create the first chart, configure scoring, upload real source data, and publish the first live week from the Kontentainment Charts dashboard.</p>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="amc-section">
		<div class="amc-container amc-split">
			<div class="amc-panel">
				<div class="amc-panel__header">
					<p class="amc-section-label">About The Charts</p>
					<h2>Methodology and publishing flow</h2>
				</div>
				<p>Kontentainment Charts publishes chart weeks from real uploaded sources through parsing, validation, matching, generation, and final publishing review.</p>
				<a class="amc-text-link" href="<?php echo esc_url( AMC_Data::route_url( 'about' ) ); ?>">Read about the methodology</a>
			</div>
			<div class="amc-panel">
				<div class="amc-panel__header">
					<p class="amc-section-label">Discovery</p>
					<h2>Browse artists and tracks</h2>
				</div>
				<p>Use the library indexes to explore artists and tracks that are already present in the platform.</p>
				<div class="amc-home-hero__actions">
					<a class="amc-button" href="<?php echo esc_url( AMC_Data::route_url( 'artists' ) ); ?>">All Artists</a>
					<a class="amc-button amc-button--ghost" href="<?php echo esc_url( AMC_Data::route_url( 'tracks' ) ); ?>">All Tracks</a>
				</div>
			</div>
		</div>
	</section>
</main>
<?php
include AMC_PLUGIN_DIR . 'templates/parts/site-footer.php';
include AMC_PLUGIN_DIR . 'templates/parts/document-end.php';
