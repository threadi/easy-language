<?php
/**
 * File to add SeedProd as supported page builder.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Multilingual_plugins\Easy_Language\Parser\SeedProd;

/**
 * Add SeedProd-object to list of supported pagebuilder.
 *
 * @param array<int,mixed> $pagebuilder_list List of supported pagebuilder.
 *
 * @return array<int,mixed>
 */
function easy_language_pagebuilder_seedprod( array $pagebuilder_list ): array {
	// add SeedProd to list.
	$pagebuilder_list[] = SeedProd::get_instance();

	// return resulting list of page-builders.
	return $pagebuilder_list;
}
add_filter( 'easy_language_pagebuilder', 'easy_language_pagebuilder_seedprod' );
