<?php
/**
 * File to add WP Bakery as supported page builder.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Multilingual_plugins\Easy_Language\Parser\WPBakery;

/**
 * Add WPBakery-object to list of supported pagebuilder.
 *
 * @param array $pagebuilder_list List of supported pagebuilder.
 *
 * @return array
 */
function easy_language_pagebuilder_wp_bakery( array $pagebuilder_list ): array {
	// add wpBakery to list.
	$pagebuilder_list[] = WPBakery::get_instance();

	// return resulting list of page-builders.
	return $pagebuilder_list;
}
add_filter( 'easy_language_pagebuilder', 'easy_language_pagebuilder_wp_bakery' );
