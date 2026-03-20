<?php
/**
 * Single chart template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$chart_slug      = get_query_var( 'amc_chart' );
$chart           = AMC_Data::get_chart( $chart_slug );
$amc_page_title  = $chart ? $chart['title'] : 'Chart';
$amc_body_class  = 'amc-chart-page';

include AMC_PLUGIN_DIR . 'templates/parts/document-start.php';
include AMC_PLUGIN_DIR . 'templates/parts/site-header.php';
?>
<main class="amc-main">
	<?php if ( $chart ) : ?>
		<section class="amc-page-hero amc-page-hero--<?php echo esc_attr( $chart['accent'] ); ?>">
			<div class="amc-container">
				<p class="amc-section-label"><?php echo esc_html( $chart['kicker'] ); ?></p>
				<h1><?php echo esc_html( $chart['title'] ); ?></h1>
				<p><?php echo esc_html( $chart['description'] ); ?></p>
			</div>
		</section>

		<div class="amc-container">
			<?php
			$featured = $chart['featured'];
			include AMC_PLUGIN_DIR . 'templates/parts/featured-item.php';
			?>

			<?php
			$entries = $chart['entries'];
			include AMC_PLUGIN_DIR . 'templates/parts/chart-table.php';
			?>

			<?php
			$lists = array_slice( AMC_Data::get_more_charts( $chart['slug'] ), 0, 3 );
			$title = 'More Lists';
			$copy  = 'Jump to adjacent charts without leaving the editorial flow.';
			include AMC_PLUGIN_DIR . 'templates/parts/more-lists.php';
			?>
		</div>
	<?php else : ?>
		<section class="amc-page-hero">
			<div class="amc-container">
				<p class="amc-section-label">Chart Not Found</p>
				<h1>This chart route is not available yet.</h1>
				<p>The chart slug did not match any registered chart definition or seeded chart record.</p>
			</div>
		</section>
	<?php endif; ?>
</main>
<?php
include AMC_PLUGIN_DIR . 'templates/parts/site-footer.php';
include AMC_PLUGIN_DIR . 'templates/parts/document-end.php';
