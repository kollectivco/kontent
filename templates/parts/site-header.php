<?php
/**
 * Global header.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nav_items = array(
	array( 'label' => 'Home', 'url' => AMC_Data::route_url() ),
	array( 'label' => 'Charts', 'url' => AMC_Data::route_url( 'charts' ) ),
	array( 'label' => 'Top Artists', 'url' => AMC_Data::route_url( 'charts/top-artists' ) ),
	array( 'label' => 'Top Tracks', 'url' => AMC_Data::route_url( 'charts/top-tracks' ) ),
	array( 'label' => 'Top Albums', 'url' => AMC_Data::route_url( 'charts/top-albums' ) ),
	array( 'label' => 'Hot 100 Tracks', 'url' => AMC_Data::route_url( 'charts/hot-100-tracks' ) ),
	array( 'label' => 'Hot 100 Artists', 'url' => AMC_Data::route_url( 'charts/hot-100-artists' ) ),
);
?>
<header class="amc-header">
	<div class="amc-container amc-header__inner">
		<a class="amc-brand" href="<?php echo esc_url( AMC_Data::route_url() ); ?>">
			<span class="amc-brand__mark">KC</span>
			<span class="amc-brand__text">
				<strong>Kontentainment Charts</strong>
				<small>Premium charts editorial</small>
			</span>
		</a>

		<button class="amc-nav-toggle" type="button" aria-expanded="false" aria-controls="amc-nav">
			Menu
		</button>

		<nav class="amc-nav" id="amc-nav" aria-label="Primary">
			<?php foreach ( $nav_items as $item ) : ?>
				<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
			<?php endforeach; ?>
		</nav>
	</div>
</header>
