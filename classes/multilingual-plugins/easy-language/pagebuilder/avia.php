<?php
/**
 * File to add Avia as supported page builder.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Multilingual_plugins\Easy_Language\Parser\Avia;

/**
 * Add Avia-object to list of supported pagebuilder.
 *
 * @param array $pagebuilder_list List of supported pagebuilder.
 *
 * @return array
 */
function easy_language_pagebuilder_avia( array $pagebuilder_list ): array {
	// add avada to list.
	$pagebuilder_list[] = Avia::get_instance();

	// return resulting list of page-builders.
	return $pagebuilder_list;
}
add_filter( 'easy_language_pagebuilder', 'easy_language_pagebuilder_avia' );
