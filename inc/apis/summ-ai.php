<?php
/**
 * File to add SUMM AI API with minimal usage.
 *
 * @package easy-language
 */

use easyLanguage\Apis\Summ_Ai\Summ_AI;

/**
 * Register the SUMM AI Api in the easy language plugin.
 *
 * @param array $api_list List of available APIs.
 * @return array
 */
function easy_language_register_summ_ai_api( array $api_list ): array {
	$api_list[] = Summ_AI::get_instance();
	return $api_list;
}
add_filter( 'easy_language_register_api', 'easy_language_register_summ_ai_api', 30 );
