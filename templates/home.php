<?php
/**
 * Home route template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$context        = AMC_Data::get_home_context();
$hero_chart     = $context['hero_chart'];
$featured       = $hero_chart['featured'];
$chart          = $hero_chart;
$amc_page_title = 'Arabic Music Charts';
$amc_body_class = 'amc-home';

include AMC_PLUGIN_DIR . 'templates/parts/document-start.php';
include AMC_PLUGIN_DIR . 'templates/parts/site-header.php';
?>
<main class="amc-main">
	<section class="amc-home-hero">
		<div class="amc-container amc-home-hero__inner">
			<div class="amc-home-hero__copy">
				<p class="amc-section-label">Week of <?php echo esc_html( wp_date( 'F j, Y' ) ); ?></p>
				<h1>Premium Arabic charts with a bold editorial pulse.</h1>
				<p>Track the week’s most influential artists, songs, and albums through a cinematic dark interface built for chart storytelling.</p>
				<div class="amc-home-hero__actions">
					<a class="amc-button" href="<?php echo esc_url( AMC_Data::route_url( 'charts' ) ); ?>">Open Charts</a>
					<a class="amc-button amc-button--ghost" href="<?php echo esc_url( $hero_chart['url'] ); ?>">See Hot 100</a>
				</div>
			</div>
			<div class="amc-home-hero__featured">
				<?php include AMC_PLUGIN_DIR . 'templates/parts/featured-item.php'; ?>
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
				<?php foreach ( $context['featured_charts'] as $chart ) : ?>
					<?php include AMC_PLUGIN_DIR . 'templates/parts/chart-card.php'; ?>
				<?php endforeach; ?>
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
			</div>

			<div class="amc-panel">
				<div class="amc-panel__header">
					<p class="amc-section-label">Top Artists</p>
					<h2>Momentum Leaders</h2>
				</div>
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
</main>
<?php
include AMC_PLUGIN_DIR . 'templates/parts/site-footer.php';
include AMC_PLUGIN_DIR . 'templates/parts/document-end.php';
