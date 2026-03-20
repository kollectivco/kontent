<?php
/**
 * Featured number one block.
 *
 * @var array $featured
 * @var array $chart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entity = $featured['entity'];
?>
<section class="amc-featured amc-featured--<?php echo esc_attr( $chart['accent'] ); ?>">
	<div class="amc-featured__rank">
		<span>#1</span>
	</div>
	<div class="amc-featured__art">
		<?php echo amc_cover_markup( $entity, 'xl' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<div class="amc-featured__content">
		<div class="amc-featured__eyebrow">
			<p class="amc-section-label"><?php echo esc_html( $chart['kicker'] ); ?></p>
			<span class="amc-featured__microstate amc-move--<?php echo esc_attr( $featured['movement'] ); ?>">
				<?php echo esc_html( $featured['movement_icon'] . ' ' . amc_movement_note( $featured ) ); ?>
			</span>
		</div>
		<h2><?php echo esc_html( $entity['name'] ); ?></h2>
		<?php if ( ! empty( $entity['artist']['name'] ) ) : ?>
			<p class="amc-featured__sub"><?php echo esc_html( $entity['artist']['name'] ); ?></p>
		<?php elseif ( ! empty( $entity['country'] ) ) : ?>
			<p class="amc-featured__sub"><?php echo esc_html( $entity['country'] . ' • ' . $entity['genres'] ); ?></p>
		<?php elseif ( ! empty( $entity['artist']['country'] ) ) : ?>
			<p class="amc-featured__sub"><?php echo esc_html( $entity['artist']['country'] ); ?></p>
		<?php endif; ?>
		<p><?php echo esc_html( ! empty( $entity['description'] ) ? $entity['description'] : $chart['description'] ); ?></p>
		<div class="amc-stat-row">
			<div><strong><?php echo esc_html( $featured['last_rank'] ); ?></strong><span>Last Week</span></div>
			<div><strong><?php echo esc_html( $featured['peak_rank'] ); ?></strong><span>Peak</span></div>
			<div><strong><?php echo esc_html( $featured['weeks_on_chart'] ); ?></strong><span>Weeks</span></div>
			<div><strong><?php echo esc_html( $featured['movement_icon'] ); ?></strong><span><?php echo esc_html( amc_route_label( $featured['movement'] ) ); ?></span></div>
		</div>
		<div class="amc-featured__footer">
			<div class="amc-featured__quote">
				<strong>Editorial Take</strong>
				<p>This week’s leader balances staying power with visible momentum across the broader chart narrative.</p>
			</div>
			<?php if ( ! empty( $entity['url'] ) ) : ?>
				<a class="amc-button" href="<?php echo esc_url( $entity['url'] ); ?>">Open Details</a>
			<?php endif; ?>
		</div>
	</div>
</section>
