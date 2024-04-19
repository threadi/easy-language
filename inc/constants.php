<?php
/**
 * Constants used by this WordPress-plugin.
 *
 * @package easy-language
 */

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SUMM AI API URL.
 */
const EASY_LANGUAGE_SUMM_AI_PAID_API_URL = 'https://backend.summ-ai.com/translate/v1/';

/**
 * SUMM AI API URL for free version.
 */
const EASY_LANGUAGE_SUMM_AI_FREE_API_URL = 'https://api.laolaweb.com/summaiproxy/index.php';

/**
 * URL for Quota-Request to SUMM AI.
 */
const EASY_LANGUAGE_SUMM_AI_API_URL_QUOTA = 'https://backend.summ-ai.com/translate/v1/usage/';

/**
 * The capito API URL for simplifications.
 */
const EASY_LANGUAGE_CAPITO_API_URL = 'https://api.capito.ai/simplification';

/**
 * The capito API URL for quotas.
 */
const EASY_LANGUAGE_CAPITO_API_URL_QUOTA = 'https://api.capito.ai/shop/me/quotas';

/**
 * The capito API URL to get info about subscription.
 */
const EASY_LANGUAGE_CAPITO_SUBSCRIPTION_URL = 'https://api.capito.ai/shop/me/subscription';

/**
 * CHATGPT API URL for simplifications.
 */
const EASY_LANGUAGE_CHATGPT_API_URL = 'https://api.openai.com/v1/chat/completions';

/**
 * Set transient-based hints for the backend.
 */
const EASY_LANGUAGE_TRANSIENTS = array(
	'easy_language_message'               => array(),
	'easy_language_refresh_rewrite_rules' => array(),
);

/**
 * Name of custom post type for switcher-entries.
 */
const EASY_LANGUAGE_CPT_SWITCHER = 'lel_lang_switcher';

/**
 * Name of emergency language if no supported language is found.
 */
const EASY_LANGUAGE_LANGUAGE_EMERGENCY = 'en_US';

/**
 * Options-list of transients.
 */
const EASY_LANGUAGE_TRANSIENT_LIST = 'easy_language_transients';

/**
 * Define names for progressbar during simplification.
 */
const EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT   = 'easy_language_simplification_count';
const EASY_LANGUAGE_OPTION_SIMPLIFICATION_MAX     = 'easy_language_simplification_max';
const EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING = 'easy_language_simplification_running';
const EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS = 'easy_language_simplification_results';

/**
 * Define names for progressbar during data deletion.
 */
const EASY_LANGUAGE_OPTION_DELETION_COUNT   = 'easy_language_deletion_count';
const EASY_LANGUAGE_OPTION_DELETION_MAX     = 'easy_language_deletion_max';
const EASY_LANGUAGE_OPTION_DELETION_RUNNING = 'easy_language_deletion_running';

/**
 * Hash for plugin-installation.
 */
const EASY_LANGUAGE_HASH = 'easy_language_hash';

/**
 * Quota for SUMM AI in free plugin.
 * Changes will not work as this is also checked by API.
 */
const EASY_LANGUAGE_SUMM_AI_QUOTA = 9000;
