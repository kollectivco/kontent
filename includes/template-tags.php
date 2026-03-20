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
