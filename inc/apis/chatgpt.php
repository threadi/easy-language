<?php
/**
 * File to add ChatGpt to the plugin.
 *
 * @package easy-language.
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Apis\ChatGpt\ChatGpt;

/**
 * Register the ChatGpt Api in this plugin.
 *
 * @param array<int,mixed> $api_list List of available APIs.
 * @return array<int,mixed>
 */
function easy_language_register_chatgpt( array $api_list ): array {
	$api_list[] = ChatGpt::get_instance();
	return $api_list;
}
add_filter( 'easy_language_register_api', 'easy_language_register_chatgpt', 200 );
