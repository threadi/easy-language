<?php
/**
 * File to add no-api (represents API with no API-functions) to the plugin.
 *
 * @package easy-language.
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Apis\No_Api\No_Api;

/**
 * Register the No-Api in this plugin.
 *
 * @param array<int,mixed> $api_list List of available APIs.
 * @return array<int,mixed>
 */
function easy_language_register_no_api( array $api_list ): array {
	$api_list[] = No_Api::get_instance();
	return $api_list;
}
add_filter( 'easy_language_register_api', 'easy_language_register_no_api', 1000 );
