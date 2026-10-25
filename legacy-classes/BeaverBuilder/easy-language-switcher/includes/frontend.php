<?php
/**
 * Template for the output of the language switcher module in Beaver Builder.
 *
 * Variables provided by Beaver Builder:
 * - $module: the module object.
 * - $settings: the settings of the module as object.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

// bail if the settings are missing.
if ( ! isset( $settings ) || ! is_object( $settings ) ) {
	return;
}

// output the switcher (the output is already escaped).
echo \easyLanguage\PageBuilder\BeaverBuilder::get_instance()->render_module( (array) $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
