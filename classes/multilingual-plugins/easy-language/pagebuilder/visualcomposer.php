<?php
/**
 * File to add Visual Composer as supported page builder.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Multilingual_plugins\Easy_Language\Parser\VisualComposer;

/**
 * Add Visual Composer-object to list of supported pagebuilder.
 *
 * @param array<int,mixed> $pagebuilder_list List of supported pagebuilder.
 *
 * @return array<int,mixed>
 */
function easy_language_pagebuilder_visual_composer( array $pagebuilder_list ): array {
	// add Visual Composer to list.
	$pagebuilder_list[] = VisualComposer::get_instance();

	// return resulting list of page-builders.
	return $pagebuilder_list;
}
add_filter( 'easy_language_pagebuilder', 'easy_language_pagebuilder_visual_composer' );
