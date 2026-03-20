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
			<p class="amc-footer__eyebrow">Phase 1 Demo Plugin</p>
			<h2>Arabic Music Charts</h2>
			<p>Public-facing plugin experience seeded with demo artists, tracks, albums, and dynamic chart categories ready for future expansion.</p>
		</div>
		<div class="amc-footer__links">
			<a href="<?php echo esc_url( AMC_Data::route_url( 'charts' ) ); ?>">Browse Charts</a>
			<a href="<?php echo esc_url( AMC_Data::route_url( 'charts/hot-100-tracks' ) ); ?>">Hot 100 Tracks</a>
			<a href="<?php echo esc_url( AMC_Data::route_url( 'charts/top-artists' ) ); ?>">Top Artists</a>
		</div>
	</div>
</footer>
