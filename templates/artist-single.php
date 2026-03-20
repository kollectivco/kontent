<?php
/**
 * Artist detail template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$artist          = AMC_Data::get_artist_by_slug( get_query_var( 'amc_artist' ) );
$tracks          = $artist ? AMC_Data::get_tracks_for_artist( $artist['id'] ) : array();
$amc_page_title  = $artist ? $artist['name'] : 'Artist';
$amc_body_class  = 'amc-artist-page';

include AMC_PLUGIN_DIR . 'templates/parts/document-start.php';
include AMC_PLUGIN_DIR . 'templates/parts/site-header.php';
?>
<main class="amc-main">
	<?php if ( $artist ) : ?>
		<section class="amc-detail-hero">
			<div class="amc-container amc-detail-hero__inner">
				<div class="amc-detail-hero__art">
					<?php echo amc_cover_markup( $artist, 'xl' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="amc-detail-hero__copy">
					<p class="amc-section-label">Artist Details</p>
					<h1><?php echo esc_html( $artist['name'] ); ?></h1>
					<p class="amc-detail-hero__sub"><?php echo esc_html( $artist['country'] . ' • ' . $artist['genres'] ); ?></p>
					<p><?php echo esc_html( $artist['description'] ); ?></p>
					<div class="amc-stat-row">
						<div><strong><?php echo esc_html( $artist['monthly'] ); ?></strong><span>Monthly Listeners</span></div>
						<div><strong><?php echo esc_html( $artist['streak'] ); ?></strong><span>Chart Streak</span></div>
						<div><strong><?php echo esc_html( count( $tracks ) ); ?></strong><span>Seeded Tracks</span></div>
					</div>
				</div>
			</div>
		</section>

		<section class="amc-section">
			<div class="amc-container amc-split">
				<div class="amc-panel">
					<div class="amc-panel__header">
						<p class="amc-section-label">Top Songs</p>
						<h2>Catalog Highlights</h2>
					</div>
					<?php foreach ( $tracks as $track ) : ?>
						<div class="amc-mini-row">
							<?php echo amc_cover_markup( $track, 'xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<div>
								<strong><a href="<?php echo esc_url( $track['url'] ); ?>"><?php echo esc_html( $track['name'] ); ?></a></strong>
								<span><?php echo esc_html( ! empty( $track['album']['name'] ) ? $track['album']['name'] : 'Single release' ); ?></span>
							</div>
							<em><?php echo esc_html( $track['duration'] ); ?></em>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="amc-panel">
					<div class="amc-panel__header">
						<p class="amc-section-label">Quick Links</p>
						<h2>Related Charts</h2>
					</div>
					<div class="amc-stack-links">
						<a href="<?php echo esc_url( AMC_Data::route_url( 'charts/top-artists' ) ); ?>">Top Artists</a>
						<a href="<?php echo esc_url( AMC_Data::route_url( 'charts/hot-100-artists' ) ); ?>">Hot 100 Artists</a>
						<a href="<?php echo esc_url( AMC_Data::route_url( 'charts/top-tracks' ) ); ?>">Top Tracks</a>
					</div>
				</div>
			</div>
		</section>
	<?php else : ?>
		<section class="amc-page-hero">
			<div class="amc-container">
				<p class="amc-section-label">Artist Not Found</p>
				<h1>This artist route is not available.</h1>
			</div>
		</section>
	<?php endif; ?>
</main>
<?php
include AMC_PLUGIN_DIR . 'templates/parts/site-footer.php';
include AMC_PLUGIN_DIR . 'templates/parts/document-end.php';
