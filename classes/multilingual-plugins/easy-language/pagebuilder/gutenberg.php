<?php
/**
 * File to add Gutenberg as supported page builder.
 *
 * @package easy-language
 */

use easyLanguage\Multilingual_plugins\Easy_Language\Parser\Gutenberg;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Add Gutenberg-object to list of supported pagebuilder.
 *
 * @param $list
 *
 * @return array
 */
function easy_language_pagebuilder_gutenberg( $list ): array {
	$list[] = Gutenberg::get_instance();
	return $list;
}
add_filter( 'easy_language_pagebuilder', 'easy_language_pagebuilder_gutenberg' );
