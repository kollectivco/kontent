<?php
/**
 * Artists index template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$artists         = AMC_Data::get_public_artists();
$amc_page_title  = 'All Artists';
$amc_body_class  = 'amc-artists-index';

include AMC_PLUGIN_DIR . 'templates/parts/document-start.php';
include AMC_PLUGIN_DIR . 'templates/parts/site-header.php';
?>
<main class="amc-main">
	<section class="amc-page-hero">
		<div class="amc-container">
			<p class="amc-section-label">Artists</p>
			<h1>Browse the artist library behind Kontentainment Charts.</h1>
			<p>Use this page to discover artist profiles already connected to the live charts workflow, even when not every chart page has an active published week.</p>
		</div>
	</section>

	<section class="amc-section">
		<div class="amc-container">
			<div class="amc-card-grid">
				<?php if ( $artists ) : ?>
					<?php foreach ( $artists as $artist ) : ?>
						<div class="amc-panel">
							<div class="amc-mini-row">
								<?php echo amc_cover_markup( $artist, 'sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<div>
									<strong><a href="<?php echo esc_url( $artist['url'] ); ?>"><?php echo esc_html( $artist['name'] ); ?></a></strong>
									<span><?php echo esc_html( trim( $artist['country'] . ' • ' . $artist['genres'], ' •' ) ); ?></span>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<p>No artists have been added yet. Artist profiles will appear here after real uploads create or match artist records.</p>
				<?php endif; ?>
			</div>
		</div>
	</section>
</main>
<?php
include AMC_PLUGIN_DIR . 'templates/parts/site-footer.php';
include AMC_PLUGIN_DIR . 'templates/parts/document-end.php';
