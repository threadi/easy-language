<?php
/**
 * File to add Avada as supported page builder.
 *
 * @package easy-language
 */

use easyLanguage\Multilingual_plugins\Easy_Language\Parser\Avada;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Avada-object to list of supported pagebuilder.
 *
 * @param array $list List of supported pagebuilder.
 *
 * @return array
 */
function easy_language_pagebuilder_avada( array $list ): array {
	$avada_obj = Avada::get_instance();
	if ( $avada_obj->is_active() ) {
		// add avada to list.
		$list[] = $avada_obj;
	}

	// return resulting list of page-builders.
	return $list;
}
add_filter( 'easy_language_pagebuilder', 'easy_language_pagebuilder_avada' );
