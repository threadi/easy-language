<?php
/**
 * File to add Breakdance Builder as supported page builder.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Multilingual_plugins\Easy_Language\Parser\Breakdance;

/**
 * Add Breakdance-object to list of supported pagebuilder.
 *
 * @param array<int,mixed> $pagebuilder_list List of supported pagebuilder.
 *
 * @return array<int,mixed>
 */
function easy_language_pagebuilder_breakdance( array $pagebuilder_list ): array {
	// add Breakdance as PageBuilder.
	$pagebuilder_list[] = BreakDance::get_instance();

	// return list of supported page-builders.
	return $pagebuilder_list;
}
add_filter( 'easy_language_pagebuilder', 'easy_language_pagebuilder_breakdance' );
