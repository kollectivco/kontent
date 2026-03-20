<?php
/**
 * More lists grid.
 *
 * @var array  $lists
 * @var string $title
 * @var string $copy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="amc-section">
	<div class="amc-more-lists">
		<div class="amc-section__heading">
			<div>
				<p class="amc-section-label"><?php echo esc_html( isset( $title ) ? $title : 'More Lists' ); ?></p>
				<h2><?php echo esc_html( isset( $copy ) ? $copy : 'Keep exploring this week’s biggest stories.' ); ?></h2>
			</div>
			<a class="amc-text-link" href="<?php echo esc_url( AMC_Data::route_url( 'charts' ) ); ?>">View all charts</a>
		</div>
		<p class="amc-more-lists__intro">Each list keeps the same editorial system while staying flexible enough for future categories, regional splits, and special franchise charts.</p>
		<div class="amc-card-grid">
			<?php foreach ( $lists as $chart ) : ?>
				<?php include AMC_PLUGIN_DIR . 'templates/parts/chart-card.php'; ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
