<?php
/**
 * Chart rankings table/list.
 *
 * @var array $entries
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="amc-table-card">
	<div class="amc-table-card__header">
		<p class="amc-section-label">Full Ranking</p>
		<h2>This Week’s Positions</h2>
	</div>
	<div class="amc-chart-table" role="table" aria-label="Chart rankings">
		<div class="amc-chart-table__head" role="rowgroup">
			<div class="amc-chart-table__row amc-chart-table__row--head" role="row">
				<span role="columnheader">Rank</span>
				<span role="columnheader">Title</span>
				<span role="columnheader">Last</span>
				<span role="columnheader">Peak</span>
				<span role="columnheader">Weeks</span>
				<span role="columnheader">Move</span>
			</div>
		</div>
		<div class="amc-chart-table__body" role="rowgroup">
			<?php foreach ( $entries as $entry ) : ?>
				<?php $entity = $entry['entity']; ?>
				<div class="amc-chart-table__row amc-chart-table__row--<?php echo esc_attr( $entry['movement'] ); ?>" role="row">
					<div class="amc-chart-table__rank-wrap" role="cell">
						<span class="amc-chart-table__rank"><?php echo esc_html( $entry['current_rank'] ); ?></span>
						<?php if ( 1 === (int) $entry['current_rank'] ) : ?>
							<small>#1</small>
						<?php endif; ?>
					</div>
					<div class="amc-chart-table__main" role="cell">
						<?php echo amc_cover_markup( $entity, 'sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<div>
							<strong>
								<?php if ( ! empty( $entity['url'] ) ) : ?>
									<a href="<?php echo esc_url( $entity['url'] ); ?>"><?php echo esc_html( $entity['name'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $entity['name'] ); ?>
								<?php endif; ?>
							</strong>
							<?php if ( ! empty( $entity['artist']['name'] ) ) : ?>
								<span><?php echo esc_html( $entity['artist']['name'] ); ?></span>
							<?php elseif ( ! empty( $entity['country'] ) ) : ?>
								<span><?php echo esc_html( $entity['country'] ); ?></span>
							<?php elseif ( ! empty( $entity['artist']['country'] ) ) : ?>
								<span><?php echo esc_html( $entity['artist']['country'] ); ?></span>
							<?php endif; ?>
							<em class="amc-inline-move amc-inline-move--detail amc-move--<?php echo esc_attr( $entry['movement'] ); ?>">
								<?php echo esc_html( amc_movement_note( $entry ) ); ?>
							</em>
						</div>
					</div>
					<span role="cell" data-label="Last"><?php echo esc_html( $entry['last_rank'] ); ?></span>
					<span role="cell" data-label="Peak"><?php echo esc_html( $entry['peak_rank'] ); ?></span>
					<span role="cell" data-label="Weeks"><?php echo esc_html( $entry['weeks_on_chart'] ); ?></span>
					<span class="amc-move amc-move--<?php echo esc_attr( $entry['movement'] ); ?>" role="cell" data-label="Move">
						<span class="amc-move__arrow"><?php echo esc_html( $entry['movement_icon'] ); ?></span>
						<span class="amc-move__text"><?php echo esc_html( amc_movement_note( $entry ) ); ?></span>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
