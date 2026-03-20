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
$artist_positions= $artist ? AMC_Data::get_artist_chart_positions( $artist['id'] ) : array();
$peer_artists    = $artist ? AMC_Data::get_related_artists( $artist['id'], 4 ) : array();

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
						<div><strong><?php echo esc_html( count( $artist_positions ) ); ?></strong><span>Chart Appearances</span></div>
					</div>
				</div>
			</div>
		</section>

		<section class="amc-section">
			<div class="amc-container">
				<div class="amc-summary-grid">
					<div class="amc-summary-card">
						<span class="amc-section-label">Origin</span>
						<strong><?php echo esc_html( $artist['country'] ); ?></strong>
						<p>Geographic identity remains a major part of the public chart narrative.</p>
					</div>
					<div class="amc-summary-card">
						<span class="amc-section-label">Genres</span>
						<strong><?php echo esc_html( $artist['genres'] ); ?></strong>
						<p>Genre framing helps the page feel closer to a real editorial profile.</p>
					</div>
					<div class="amc-summary-card">
						<span class="amc-section-label">Audience</span>
						<strong><?php echo esc_html( $artist['monthly'] ); ?></strong>
						<p>Seeded scale metric used to deepen the profile without adding real analytics yet.</p>
					</div>
					<div class="amc-summary-card">
						<span class="amc-section-label">Charts</span>
						<strong><?php echo esc_html( count( $artist_positions ) ); ?> active</strong>
						<p>Current seeded chart presence across artist-focused lists.</p>
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
					<?php if ( $tracks ) : ?>
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
					<?php else : ?>
						<p>No tracks are currently attached to this artist in the demo dataset.</p>
					<?php endif; ?>
				</div>

				<div class="amc-panel">
					<div class="amc-panel__header">
						<p class="amc-section-label">Related Charts</p>
						<h2>Current positions</h2>
					</div>
					<?php if ( $artist_positions ) : ?>
						<?php foreach ( $artist_positions as $position ) : ?>
							<div class="amc-mini-row">
								<span class="amc-mini-row__rank"><?php echo esc_html( $position['entry']['current_rank'] ); ?></span>
								<div>
									<strong><a href="<?php echo esc_url( $position['chart']['url'] ); ?>"><?php echo esc_html( $position['chart']['title'] ); ?></a></strong>
									<span><?php echo esc_html( amc_movement_note( $position['entry'] ) ); ?></span>
								</div>
								<em class="amc-move amc-move--<?php echo esc_attr( $position['entry']['movement'] ); ?>"><?php echo esc_html( $position['entry']['movement_icon'] ); ?></em>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p>This artist does not have seeded chart placements yet.</p>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<section class="amc-section">
			<div class="amc-container amc-split">
				<div class="amc-panel">
					<div class="amc-panel__header">
						<p class="amc-section-label">Editorial Snapshot</p>
						<h2>Profile notes</h2>
					</div>
					<p>This artist page now reads closer to a chart-profile spread: identity, stats, active songs, and chart context all support the primary visual hierarchy.</p>
					<p>The goal of Phase 1.1 is to make the public plugin feel like a media product before any scoring engine or admin tooling arrives in later phases.</p>
				</div>

				<div class="amc-panel">
					<div class="amc-panel__header">
						<p class="amc-section-label">Related Artists</p>
						<h2>Also trending</h2>
					</div>
					<?php if ( $peer_artists ) : ?>
						<?php foreach ( $peer_artists as $peer_artist ) : ?>
							<div class="amc-mini-row">
								<?php echo amc_cover_markup( $peer_artist, 'xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<div>
									<strong><a href="<?php echo esc_url( $peer_artist['url'] ); ?>"><?php echo esc_html( $peer_artist['name'] ); ?></a></strong>
									<span><?php echo esc_html( $peer_artist['country'] ); ?></span>
								</div>
								<em><?php echo esc_html( $peer_artist['monthly'] ); ?></em>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p>More peer profiles will show here as the artist index grows.</p>
					<?php endif; ?>
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
