<?php
/**
 * File to add WP Bakery as supported page builder.
 *
 * @package easy-language
 */

use easyLanguage\Multilingual_plugins\Easy_Language\Parser\Wp_Bakery;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Add WPBakery-object to list of supported pagebuilder.
 *
 * @param $list
 *
 * @return array
 */
function easy_language_pagebuilder_wp_bakery( $list ): array {
	$list[] = Wp_Bakery::get_instance();
	return $list;
}
add_filter( 'easy_language_pagebuilder', 'easy_language_pagebuilder_wp_bakery' );
