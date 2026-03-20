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
$summary         = $chart && ! empty( $chart['summary'] ) ? $chart['summary'] : array();

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
				<div class="amc-hero-note">
					<strong>Editor’s note</strong>
					<span>This list is built to feel like a weekly cover story, with movement, longevity, and visual hierarchy taking priority.</span>
				</div>
			</div>
		</section>

		<div class="amc-container">
			<section class="amc-summary-grid">
				<div class="amc-summary-card">
					<span class="amc-section-label">Entries</span>
					<strong><?php echo esc_html( ! empty( $summary['entries'] ) ? $summary['entries'] : count( $chart['entries'] ) ); ?></strong>
					<p>Featured positions highlighted in a ranking-first editorial format.</p>
				</div>
				<div class="amc-summary-card">
					<span class="amc-section-label">Avg. Run</span>
					<strong><?php echo esc_html( ! empty( $summary['average_weeks'] ) ? $summary['average_weeks'] : 0 ); ?> weeks</strong>
					<p>Average time each entry has stayed visible on this week’s list.</p>
				</div>
				<div class="amc-summary-card">
					<span class="amc-section-label">Holding</span>
					<strong><?php echo esc_html( ! empty( $summary['steady_count'] ) ? $summary['steady_count'] : 0 ); ?></strong>
					<p>Acts or releases maintaining their exact position from last week.</p>
				</div>
				<div class="amc-summary-card">
					<span class="amc-section-label">Top Mover</span>
					<strong><?php echo esc_html( ! empty( $summary['top_mover']['entity']['name'] ) ? $summary['top_mover']['entity']['name'] : 'No change leader' ); ?></strong>
					<p><?php echo esc_html( ! empty( $summary['top_mover'] ) ? amc_movement_note( $summary['top_mover'] ) : 'Momentum is steady across this chart.' ); ?></p>
				</div>
			</section>

			<?php
			$featured = $chart['featured'];
			include AMC_PLUGIN_DIR . 'templates/parts/featured-item.php';
			?>

			<section class="amc-section">
				<div class="amc-editorial-strip">
					<div>
						<p class="amc-section-label">Chart Brief</p>
						<h2>What defines this week’s mood</h2>
					</div>
					<p>The upper tier leans heavily on familiar names with enough movement underneath to keep the table feeling alive. This pass sharpens the sense of momentum by treating every ranking row as a story beat instead of plain metadata.</p>
				</div>
			</section>

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
