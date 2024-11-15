<?php
/**
 * File to add Gutenberg as supported page builder.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Multilingual_plugins\Easy_Language\Parser\Gutenberg;

/**
 * Add Gutenberg-object to list of supported pagebuilder.
 *
 * @param array $pagebuilder_list List of supported pagebuilder.
 *
 * @return array
 */
function easy_language_pagebuilder_gutenberg( array $pagebuilder_list ): array {
	$pagebuilder_list[] = Gutenberg::get_instance();
	return $pagebuilder_list;
}
add_filter( 'easy_language_pagebuilder', 'easy_language_pagebuilder_gutenberg', 1000 );
