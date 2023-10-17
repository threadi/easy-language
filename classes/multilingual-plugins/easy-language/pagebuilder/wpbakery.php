<?php
/**
 * File to add WP Bakery as supported page builder.
 *
 * @package easy-language
 */

use easyLanguage\Multilingual_plugins\Easy_Language\Parser\WPBakery;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add WPBakery-object to list of supported pagebuilder.
 *
 * @param array $list List of supported pagebuilder.
 *
 * @return array
 */
function easy_language_pagebuilder_wp_bakery( array $list ): array {
	$wp_bakery_obj = WPBakery::get_instance();
	if ( $wp_bakery_obj->is_active() ) {
		// add wpBakery to list.
		$list[] = $wp_bakery_obj;
	}

	// return resulting list of page-builders.
	return $list;
}
add_filter( 'easy_language_pagebuilder', 'easy_language_pagebuilder_wp_bakery' );
