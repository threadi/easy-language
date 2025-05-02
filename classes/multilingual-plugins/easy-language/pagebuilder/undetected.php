<?php
/**
 * File to add Undetected as supported page builder.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Multilingual_plugins\Easy_Language\Parser\Undetected;

/**
 * Add Undetected-object to list of supported pagebuilder.
 *
 * Must be last index in list of supported pagebuilder, so we use PHP_INT_MAX for position.
 *
 * @param array<int,mixed> $pagebuilder_list List of supported pagebuilder.
 *
 * @return array<int,mixed>
 */
function easy_language_pagebuilder_undetected( array $pagebuilder_list ): array {
	$pagebuilder_list[] = Undetected::get_instance();
	return $pagebuilder_list;
}
add_filter( 'easy_language_pagebuilder', 'easy_language_pagebuilder_undetected', PHP_INT_MAX );
