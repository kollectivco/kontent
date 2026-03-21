<?php
/**
 * About / methodology template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings        = AMC_DB::get_settings();
$amc_page_title  = 'About Charts';
$amc_body_class  = 'amc-about-page';

include AMC_PLUGIN_DIR . 'templates/parts/document-start.php';
include AMC_PLUGIN_DIR . 'templates/parts/site-header.php';
?>
<main class="amc-main">
	<section class="amc-page-hero">
		<div class="amc-container">
			<p class="amc-section-label">About The Charts</p>
			<h1>How Kontentainment Charts moves from source data to a live chart week.</h1>
			<p>The platform uses a production workflow: upload, parse, validate, match or auto-create, generate, review, and publish.</p>
		</div>
	</section>

	<section class="amc-section">
		<div class="amc-container amc-split">
			<div class="amc-panel">
				<div class="amc-panel__header">
					<p class="amc-section-label">Workflow</p>
					<h2>Operational sequence</h2>
				</div>
				<p>Operators manually choose the source platform, country, target chart, chart type, and week/date for every upload. The system never guesses chart ownership automatically.</p>
				<p>After parsing and validation, rows are either matched to existing entities, auto-created when safe, or sent to manual review when ambiguous.</p>
			</div>
			<div class="amc-panel">
				<div class="amc-panel__header">
					<p class="amc-section-label">Methodology</p>
					<h2>Current notes</h2>
				</div>
				<p><?php echo esc_html( $settings['methodology_text'] ? $settings['methodology_text'] : 'Methodology text has not been published yet. Configure it from the Kontentainment Charts settings and scoring workflow.' ); ?></p>
			</div>
		</div>
	</section>
</main>
<?php
include AMC_PLUGIN_DIR . 'templates/parts/site-footer.php';
include AMC_PLUGIN_DIR . 'templates/parts/document-end.php';
