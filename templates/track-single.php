<?php
/**
 * Track detail template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$track           = AMC_Data::get_track_by_slug( get_query_var( 'amc_track' ) );
$amc_page_title  = $track ? $track['name'] : 'Track';
$amc_body_class  = 'amc-track-page';
$hot_entries     = AMC_Data::get_chart_entries( 'hot-100-tracks' );

include AMC_PLUGIN_DIR . 'templates/parts/document-start.php';
include AMC_PLUGIN_DIR . 'templates/parts/site-header.php';
?>
<main class="amc-main">
	<?php if ( $track ) : ?>
		<section class="amc-detail-hero">
			<div class="amc-container amc-detail-hero__inner">
				<div class="amc-detail-hero__art">
					<?php echo amc_cover_markup( $track, 'xl' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="amc-detail-hero__copy">
					<p class="amc-section-label">Track Details</p>
					<h1><?php echo esc_html( $track['name'] ); ?></h1>
					<p class="amc-detail-hero__sub"><?php echo esc_html( $track['artist']['name'] ); ?><?php if ( ! empty( $track['album']['name'] ) ) : ?> • <?php echo esc_html( $track['album']['name'] ); ?><?php endif; ?></p>
					<p><?php echo esc_html( $track['description'] ); ?></p>
					<div class="amc-stat-row">
						<div><strong><?php echo esc_html( $track['duration'] ); ?></strong><span>Duration</span></div>
						<div><strong><?php echo esc_html( $track['artist']['country'] ); ?></strong><span>Origin</span></div>
						<div><strong><?php echo esc_html( $track['artist']['monthly'] ); ?></strong><span>Monthly Audience</span></div>
					</div>
					<a class="amc-button" href="<?php echo esc_url( $track['artist']['url'] ); ?>">Open Artist</a>
				</div>
			</div>
		</section>

		<section class="amc-section">
			<div class="amc-container amc-split">
				<div class="amc-panel">
					<div class="amc-panel__header">
						<p class="amc-section-label">Editorial Notes</p>
						<h2>Why it’s charting</h2>
					</div>
					<p>This seeded demo detail page is driven by WordPress post data and chart entry meta, ready to absorb real scoring and editorial workflows in later phases.</p>
					<p>The layout emphasizes hierarchy, visual identity, and navigable chart context without introducing admin or backend logic yet.</p>
				</div>

				<div class="amc-panel">
					<div class="amc-panel__header">
						<p class="amc-section-label">More Lists</p>
						<h2>Where it appears</h2>
					</div>
					<?php foreach ( array_slice( $hot_entries, 0, 4 ) as $entry ) : ?>
						<div class="amc-mini-row">
							<span class="amc-mini-row__rank"><?php echo esc_html( $entry['current_rank'] ); ?></span>
							<?php echo amc_cover_markup( $entry['entity'], 'xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<div>
								<strong><a href="<?php echo esc_url( $entry['entity']['url'] ); ?>"><?php echo esc_html( $entry['entity']['name'] ); ?></a></strong>
								<span><?php echo esc_html( $entry['entity']['artist']['name'] ); ?></span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php else : ?>
		<section class="amc-page-hero">
			<div class="amc-container">
				<p class="amc-section-label">Track Not Found</p>
				<h1>This track route is not available.</h1>
			</div>
		</section>
	<?php endif; ?>
</main>
<?php
include AMC_PLUGIN_DIR . 'templates/parts/site-footer.php';
include AMC_PLUGIN_DIR . 'templates/parts/document-end.php';
