<?php
/**
 * Home route template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$context        = AMC_Data::get_home_context();
$public_state   = AMC_Data::public_state();
$hero_chart     = $context['hero_chart'];
$featured       = $hero_chart && ! empty( $hero_chart['featured'] ) ? $hero_chart['featured'] : null;
$chart          = $hero_chart;
$amc_page_title = 'Kontentainment Charts';
$amc_body_class = 'amc-home';

include AMC_PLUGIN_DIR . 'templates/parts/document-start.php';
include AMC_PLUGIN_DIR . 'templates/parts/site-header.php';
?>
<main class="amc-main">
	<section class="amc-home-hero">
		<div class="amc-container amc-home-hero__inner">
			<div class="amc-home-hero__copy">
				<p class="amc-section-label"><?php echo esc_html( $public_state['has_published_data'] ? 'Week of ' . wp_date( 'F j, Y' ) : 'Kontentainment Charts' ); ?></p>
				<?php if ( $public_state['has_published_data'] ) : ?>
					<h1>Premium chart stories with a bold editorial pulse.</h1>
					<p>Track the week’s most influential artists, songs, and albums through a cinematic dark interface built for chart storytelling.</p>
				<?php elseif ( $public_state['has_charts'] ) : ?>
					<h1>No live chart data yet, but the chart network is ready.</h1>
					<p>Chart categories already exist. Publish the first live week from the Kontentainment Charts dashboard to start filling this homepage with real rankings.</p>
				<?php else : ?>
					<h1>Kontentainment Charts is ready for its first live chart week.</h1>
					<p>Start in the dashboard by creating the first chart, configuring scoring rules, uploading a source file, and publishing the first real week.</p>
				<?php endif; ?>
				<div class="amc-home-hero__actions">
					<a class="amc-button" href="<?php echo esc_url( AMC_Data::route_url( 'charts' ) ); ?>"><?php echo esc_html( $public_state['has_published_data'] ? 'Open Charts' : 'View Chart Routes' ); ?></a>
					<a class="amc-button amc-button--ghost" href="<?php echo esc_url( AMC_Data::route_url( 'about' ) ); ?>">About Charts</a>
					<?php if ( $hero_chart && $public_state['has_published_data'] ) : ?>
						<a class="amc-button amc-button--ghost" href="<?php echo esc_url( $hero_chart['url'] ); ?>">Open Lead Chart</a>
					<?php else : ?>
						<a class="amc-button amc-button--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=kontentainment-charts' ) ); ?>">Open Dashboard</a>
					<?php endif; ?>
				</div>
			</div>
			<div class="amc-home-hero__featured">
				<?php if ( $hero_chart && $featured ) : ?>
					<?php include AMC_PLUGIN_DIR . 'templates/parts/featured-item.php'; ?>
				<?php else : ?>
					<section class="amc-featured">
						<div class="amc-featured__content">
							<p class="amc-section-label">No live chart data yet</p>
							<h2><?php echo esc_html( $public_state['has_charts'] ? 'Publish the first week to activate the homepage.' : 'Create the first chart to begin onboarding.' ); ?></h2>
							<p><?php echo esc_html( $public_state['has_charts'] ? 'Your chart routes are ready. The next step is uploading source files, reviewing parsing and matching, then publishing a live week.' : 'The plugin is installed cleanly and waiting for real production data. Once the first chart is created and published, this featured block will switch to live content.' ); ?></p>
						</div>
					</section>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="amc-section">
		<div class="amc-container">
			<div class="amc-section__heading">
				<div>
					<p class="amc-section-label">Chart Navigation</p>
					<h2>Featured Lists</h2>
				</div>
			</div>
			<div class="amc-card-grid">
				<?php if ( $context['featured_charts'] ) : ?>
					<?php foreach ( $context['featured_charts'] as $chart ) : ?>
						<?php include AMC_PLUGIN_DIR . 'templates/parts/chart-card.php'; ?>
					<?php endforeach; ?>
				<?php else : ?>
					<p>No chart categories are active yet. Create the first real chart from the Kontentainment Charts dashboard to start the onboarding flow.</p>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="amc-section">
		<div class="amc-container amc-split">
			<div class="amc-panel">
				<div class="amc-panel__header">
					<p class="amc-section-label">Top Tracks</p>
					<h2>Streaming Heat</h2>
				</div>
				<?php if ( $context['trending_tracks'] ) : ?>
					<?php foreach ( $context['trending_tracks'] as $entry ) : ?>
						<div class="amc-mini-row">
							<span class="amc-mini-row__rank"><?php echo esc_html( $entry['current_rank'] ); ?></span>
							<?php echo amc_cover_markup( $entry['entity'], 'xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<div>
								<strong><a href="<?php echo esc_url( $entry['entity']['url'] ); ?>"><?php echo esc_html( $entry['entity']['name'] ); ?></a></strong>
								<span><?php echo esc_html( $entry['entity']['artist']['name'] ); ?></span>
							</div>
							<em class="amc-move amc-move--<?php echo esc_attr( $entry['movement'] ); ?>"><?php echo esc_html( $entry['movement_icon'] ); ?></em>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<p>No live track chart data is published yet. Once the first track chart week goes live, top rows will appear here automatically.</p>
				<?php endif; ?>
			</div>

			<div class="amc-panel">
				<div class="amc-panel__header">
					<p class="amc-section-label">Top Artists</p>
					<h2>Momentum Leaders</h2>
				</div>
				<?php if ( $context['trending_artists'] ) : ?>
					<?php foreach ( $context['trending_artists'] as $entry ) : ?>
						<div class="amc-mini-row">
							<span class="amc-mini-row__rank"><?php echo esc_html( $entry['current_rank'] ); ?></span>
							<?php echo amc_cover_markup( $entry['entity'], 'xs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<div>
								<strong><a href="<?php echo esc_url( $entry['entity']['url'] ); ?>"><?php echo esc_html( $entry['entity']['name'] ); ?></a></strong>
								<span><?php echo esc_html( $entry['entity']['country'] ); ?></span>
							</div>
							<em class="amc-move amc-move--<?php echo esc_attr( $entry['movement'] ); ?>"><?php echo esc_html( $entry['movement_icon'] ); ?></em>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<p>No live artist chart data is published yet. Publish the first artist-facing week to activate this panel.</p>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<div class="amc-container">
		<?php
		$lists = $context['more_charts'];
		$title = 'More Charts';
		$copy  = 'Editorial chart destinations ready for weekly updates and future categories.';
		include AMC_PLUGIN_DIR . 'templates/parts/more-lists.php';
		?>
	</div>

	<section class="amc-section">
		<div class="amc-container amc-split">
			<div class="amc-panel">
				<div class="amc-panel__header">
					<p class="amc-section-label">Discovery</p>
					<h2>Browse the music library</h2>
				</div>
				<p>Explore real artists and tracks that have been added to Kontentainment Charts, even before every chart route is fully populated with live weeks.</p>
				<div class="amc-home-hero__actions">
					<a class="amc-button" href="<?php echo esc_url( AMC_Data::route_url( 'artists' ) ); ?>">All Artists</a>
					<a class="amc-button amc-button--ghost" href="<?php echo esc_url( AMC_Data::route_url( 'tracks' ) ); ?>">All Tracks</a>
				</div>
			</div>
			<div class="amc-panel">
				<div class="amc-panel__header">
					<p class="amc-section-label">Methodology</p>
					<h2>How the charts are built</h2>
				</div>
				<p>Read the platform methodology, understand the publishing flow, and see how source data becomes a live chart week.</p>
				<div class="amc-home-hero__actions">
					<a class="amc-button amc-button--ghost" href="<?php echo esc_url( AMC_Data::route_url( 'about' ) ); ?>">About The Charts</a>
				</div>
			</div>
		</div>
	</section>
</main>
<?php
include AMC_PLUGIN_DIR . 'templates/parts/site-footer.php';
include AMC_PLUGIN_DIR . 'templates/parts/document-end.php';
