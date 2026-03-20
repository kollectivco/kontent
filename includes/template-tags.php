<?php
/**
 * Template helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'amc_cover_markup' ) ) {
	/**
	 * Render cover tile.
	 *
	 * @param array  $entity Entity array.
	 * @param string $size CSS size modifier.
	 * @return string
	 */
	function amc_cover_markup( $entity, $size = 'md' ) {
		$name     = isset( $entity['name'] ) ? $entity['name'] : '';
		$gradient = ! empty( $entity['gradient'] ) ? $entity['gradient'] : 'ocean';
		$initials = array_slice( preg_split( '/\s+/', $name ), 0, 2 );
		$initials = array_map(
			function ( $part ) {
				return function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $part, 0, 1 ) ) : strtoupper( substr( $part, 0, 1 ) );
			},
			$initials
		);

		return sprintf(
			'<div class="amc-cover amc-cover--%1$s amc-cover--%2$s"><span>%3$s</span></div>',
			esc_attr( $gradient ),
			esc_attr( $size ),
			esc_html( implode( '', $initials ) )
		);
	}
}

if ( ! function_exists( 'amc_route_label' ) ) {
	/**
	 * Human label for movement.
	 *
	 * @param string $movement Movement value.
	 * @return string
	 */
	function amc_route_label( $movement ) {
		switch ( $movement ) {
			case 'up':
				return 'Rising';
			case 'down':
				return 'Falling';
			case 'new':
				return 'New';
			default:
				return 'Holding';
		}
	}
}

if ( ! function_exists( 'amc_movement_note' ) ) {
	/**
	 * Descriptive movement note.
	 *
	 * @param array $entry Chart entry.
	 * @return string
	 */
	function amc_movement_note( $entry ) {
		$delta = isset( $entry['movement_delta'] ) ? (int) $entry['movement_delta'] : 0;

		switch ( $entry['movement'] ) {
			case 'up':
				return $delta > 1 ? 'Up ' . $delta . ' spots' : 'Up 1 spot';
			case 'down':
				return $delta > 1 ? 'Down ' . $delta . ' spots' : 'Down 1 spot';
			case 'new':
				return 'New this week';
			default:
				return 'No change';
		}
	}
}
