<?php
/**
 * File to add no-api (represents API with no API-functions) to the plugin.
 *
 * @package easy-language.
 */

use easyLanguage\Apis\No_Api\No_Api;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the SUMM AI Api in this plugin.
 *
 * @param array $api_list List of available APIs.
 * @return array
 */
function easy_language_register_no_api( array $api_list ): array {
	$api_list[] = No_Api::get_instance();
	return $api_list;
}
add_filter( 'easy_language_register_api', 'easy_language_register_no_api', 200 );
