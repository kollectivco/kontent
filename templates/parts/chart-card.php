<?php
/**
 * Chart card tile.
 *
 * @var array $chart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$featured = ! empty( $chart['featured'] ) ? $chart['featured'] : null;
?>
<article class="amc-chart-card amc-chart-card--<?php echo esc_attr( $chart['accent'] ); ?>">
	<div class="amc-chart-card__top">
		<p class="amc-section-label"><?php echo esc_html( $chart['kicker'] ); ?></p>
		<h3><a href="<?php echo esc_url( $chart['url'] ); ?>"><?php echo esc_html( $chart['title'] ); ?></a></h3>
		<p><?php echo esc_html( $chart['description'] ); ?></p>
	</div>

	<?php if ( $featured ) : ?>
		<div class="amc-chart-card__featured">
			<span class="amc-rank-pill">#<?php echo esc_html( $featured['current_rank'] ); ?></span>
			<div class="amc-chart-card__featured-main">
				<?php echo amc_cover_markup( $featured['entity'], 'sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div>
					<strong><?php echo esc_html( $featured['entity']['name'] ); ?></strong>
					<?php if ( ! empty( $featured['entity']['artist']['name'] ) ) : ?>
						<span><?php echo esc_html( $featured['entity']['artist']['name'] ); ?></span>
					<?php elseif ( ! empty( $featured['entity']['country'] ) ) : ?>
						<span><?php echo esc_html( $featured['entity']['country'] ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</div>
	<?php endif; ?>
</article>
