<?php
/**
 * File to add Beaver Builder as supported page builder.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Multilingual_plugins\Easy_Language\Parser\BeaverBuilder;

/**
 * Add Beaver Builder-object to list of supported pagebuilder.
 *
 * @param array<int,mixed> $pagebuilder_list List of supported pagebuilder.
 *
 * @return array<int,mixed>
 */
function easy_language_pagebuilder_beaver_builder( array $pagebuilder_list ): array {
	// add Beaver Builder to list.
	$pagebuilder_list[] = BeaverBuilder::get_instance();

	// return resulting list of page-builders.
	return $pagebuilder_list;
}
add_filter( 'easy_language_pagebuilder', 'easy_language_pagebuilder_beaver_builder' );
