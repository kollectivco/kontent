<?php
/**
 * Plugin document start.
 *
 * @var string $amc_page_title
 * @var string $amc_body_class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( trim( 'amc-shell ' . ( isset( $amc_body_class ) ? $amc_body_class : '' ) ) ); ?>>
<?php wp_body_open(); ?>
<div class="amc-site-shell">
