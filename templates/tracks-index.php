<?php
/**
 * Tracks index template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tracks          = AMC_Data::get_public_tracks();
$amc_page_title  = 'All Tracks';
$amc_body_class  = 'amc-tracks-index';

include AMC_PLUGIN_DIR . 'templates/parts/document-start.php';
include AMC_PLUGIN_DIR . 'templates/parts/site-header.php';
?>
<main class="amc-main">
	<section class="amc-page-hero">
		<div class="amc-container">
			<p class="amc-section-label">Tracks</p>
			<h1>Browse the track library behind the charts.</h1>
			<p>Tracks appear here once they have been created directly, matched to uploads, or auto-created safely during the ingestion workflow.</p>
		</div>
	</section>

	<section class="amc-section">
		<div class="amc-container">
			<div class="amc-card-grid">
				<?php if ( $tracks ) : ?>
					<?php foreach ( $tracks as $track ) : ?>
						<div class="amc-panel">
							<div class="amc-mini-row">
								<?php echo amc_cover_markup( $track, 'sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<div>
									<strong><a href="<?php echo esc_url( $track['url'] ); ?>"><?php echo esc_html( $track['name'] ); ?></a></strong>
									<span><?php echo esc_html( ! empty( $track['artist']['name'] ) ? $track['artist']['name'] : 'Unknown artist' ); ?></span>
								</div>
								<em><?php echo esc_html( $track['duration'] ? $track['duration'] : 'Track' ); ?></em>
							</div>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<p>No tracks have been added yet. Track records will appear here after real uploads are matched or safely auto-created.</p>
				<?php endif; ?>
			</div>
		</div>
	</section>
</main>
<?php
include AMC_PLUGIN_DIR . 'templates/parts/site-footer.php';
include AMC_PLUGIN_DIR . 'templates/parts/document-end.php';
