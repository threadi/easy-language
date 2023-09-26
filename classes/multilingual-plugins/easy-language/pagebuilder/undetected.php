<?php
/**
 * File to add Undetected as supported page builder.
 *
 * @package easy-language
 */

use easyLanguage\Multilingual_plugins\Easy_Language\Parser\Undetected;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Add Undetected-object to list of supported pagebuilder.
 *
 * Must be last index in list of supported pagebuilder, so we use PHP_INT_MAX for position.
 *
 * @param $list
 *
 * @return array
 */
function easy_language_pagebuilder_undetected( $list ): array {
	$list[] = Undetected::get_instance();
	return $list;
}
add_filter( 'easy_language_pagebuilder', 'easy_language_pagebuilder_undetected', PHP_INT_MAX );
