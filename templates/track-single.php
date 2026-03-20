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
$track_positions = $track ? AMC_Data::get_track_chart_positions( $track['id'] ) : array();
$related_tracks  = $track ? AMC_Data::get_related_tracks( $track['id'], ! empty( $track['artist']['id'] ) ? $track['artist']['id'] : 0, 4 ) : array();
$related_artists = $track && ! empty( $track['artist']['id'] ) ? AMC_Data::get_related_artists( $track['artist']['id'], 4 ) : array();

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
						<div><strong><?php echo esc_html( count( $track_positions ) ); ?></strong><span>Chart Appearances</span></div>
					</div>
					<div class="amc-detail-hero__actions">
						<a class="amc-button" href="<?php echo esc_url( $track['artist']['url'] ); ?>">Open Artist</a>
						<a class="amc-button amc-button--ghost" href="<?php echo esc_url( AMC_Data::route_url( 'charts/hot-100-tracks' ) ); ?>">Back To Hot 100</a>
					</div>
				</div>
			</div>
		</section>

		<section class="amc-section">
			<div class="amc-container">
				<div class="amc-summary-grid">
					<div class="amc-summary-card">
						<span class="amc-section-label">Lead Artist</span>
						<strong><?php echo esc_html( $track['artist']['name'] ); ?></strong>
						<p><?php echo esc_html( $track['artist']['genres'] ); ?></p>
					</div>
					<div class="amc-summary-card">
						<span class="amc-section-label">Release Type</span>
						<strong><?php echo esc_html( ! empty( $track['album']['name'] ) ? 'Album Cut' : 'Standalone Single' ); ?></strong>
						<p><?php echo esc_html( ! empty( $track['album']['name'] ) ? $track['album']['name'] : 'Designed as a single-first release.' ); ?></p>
					</div>
					<div class="amc-summary-card">
						<span class="amc-section-label">Current Reach</span>
						<strong><?php echo esc_html( $track['artist']['monthly'] ); ?></strong>
						<p>Current seeded audience metric attached to the lead artist profile.</p>
					</div>
					<div class="amc-summary-card">
						<span class="amc-section-label">Chart Footprint</span>
						<strong><?php echo esc_html( count( $track_positions ) ); ?> lists</strong>
						<p>How many seeded charts currently feature this song.</p>
					</div>
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
					<p>This track lands like a premium lead-in moment: fast-recognition artwork, a commanding title lockup, and enough metadata to make the chart story legible at a glance.</p>
					<p>The detail page now reads more like a magazine side feature, balancing context, related discoveries, and ranking appearances without introducing any backend systems yet.</p>
				</div>

				<div class="amc-panel">
					<div class="amc-panel__header">
						<p class="amc-section-label">Chart Appearances</p>
						<h2>Where it appears</h2>
					</div>
					<?php if ( $track_positions ) : ?>
						<?php foreach ( $track_positions as $position ) : ?>
							<div class="amc-mini-row">
								<span class="amc-mini-row__rank"><?php echo esc_html( $position['entry']['current_rank'] ); ?></span>
								<?php echo amc_cover_markup( $position['entry']['entity'], 'xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<div>
									<strong><a href="<?php echo esc_url( $position['chart']['url'] ); ?>"><?php echo esc_html( $position['chart']['title'] ); ?></a></strong>
									<span><?php echo esc_html( amc_movement_note( $position['entry'] ) ); ?></span>
								</div>
								<em class="amc-move amc-move--<?php echo esc_attr( $position['entry']['movement'] ); ?>"><?php echo esc_html( $position['entry']['movement_icon'] ); ?></em>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p>No related chart placements have been seeded for this track yet.</p>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<section class="amc-section">
			<div class="amc-container amc-split">
				<div class="amc-panel">
					<div class="amc-panel__header">
						<p class="amc-section-label">Related Tracks</p>
						<h2>Keep listening</h2>
					</div>
					<?php if ( $related_tracks ) : ?>
						<?php foreach ( $related_tracks as $related_track ) : ?>
							<div class="amc-mini-row">
								<?php echo amc_cover_markup( $related_track, 'xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<div>
									<strong><a href="<?php echo esc_url( $related_track['url'] ); ?>"><?php echo esc_html( $related_track['name'] ); ?></a></strong>
									<span><?php echo esc_html( $related_track['artist']['name'] ); ?></span>
								</div>
								<em><?php echo esc_html( $related_track['duration'] ); ?></em>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p>More related songs will appear here as additional seeded releases are added.</p>
					<?php endif; ?>
				</div>

				<div class="amc-panel">
					<div class="amc-panel__header">
						<p class="amc-section-label">Related Artists</p>
						<h2>Chart neighbors</h2>
					</div>
					<?php if ( $related_artists ) : ?>
						<?php foreach ( $related_artists as $related_artist ) : ?>
							<div class="amc-mini-row">
								<?php echo amc_cover_markup( $related_artist, 'xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<div>
									<strong><a href="<?php echo esc_url( $related_artist['url'] ); ?>"><?php echo esc_html( $related_artist['name'] ); ?></a></strong>
									<span><?php echo esc_html( $related_artist['country'] . ' • ' . $related_artist['genres'] ); ?></span>
								</div>
								<em><?php echo esc_html( $related_artist['monthly'] ); ?></em>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p>Companion artist recommendations will populate here as more chart-linked profiles are added.</p>
					<?php endif; ?>
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
