<?php
/**
 * File to add capito API with minimal usage.
 *
 * @package easy-language
 */

use easyLanguage\Apis\Capito\Capito;

/**
 * Register the capito Api in the easy language plugin.
 *
 * @param array $api_list List of available APIs.
 * @return array
 */
function easy_language_register_capito_api( array $api_list ): array {
	$api_list[] = Capito::get_instance();
	return $api_list;
}
add_filter( 'easy_language_register_api', 'easy_language_register_capito_api', 100 );
