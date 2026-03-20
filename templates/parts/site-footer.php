<?php
/**
 * Global footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<footer class="amc-footer">
	<div class="amc-container amc-footer__inner">
		<div>
			<p class="amc-footer__eyebrow">Kontentainment Charts</p>
			<h2>Kontentainment Charts</h2>
			<p>Public-facing chart experience powered by real chart categories, real chart weeks, and plugin-owned routes ready for live publishing.</p>
		</div>
		<div class="amc-footer__links">
			<a href="<?php echo esc_url( AMC_Data::route_url( 'charts' ) ); ?>">Browse Charts</a>
			<a href="<?php echo esc_url( AMC_Data::route_url( 'charts/hot-100-tracks' ) ); ?>">Hot 100 Tracks</a>
			<a href="<?php echo esc_url( AMC_Data::route_url( 'charts/top-artists' ) ); ?>">Top Artists</a>
		</div>
	</div>
</footer>
