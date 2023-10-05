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
 * SUMM AI API URL for free version.
 */
const EASY_LANGUAGE_SUMM_AI_API_URL = 'https://api.laolaweb.com/summaiproxy/index.php';

/**
 * Capito API URL for translations.
 */
const EASY_LANGUAGE_CAPITO_API_URL = 'https://api.capito.ai/simplification';

/**
 * Capito API URL for quotas.
 */
const EASY_LANGUAGE_CAPITO_API_URL_QUOTA = 'https://api.capito.ai/shop/me/quotas';

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
 * Define names for progressbar during translation.
 */
const EASY_LANGUAGE_OPTION_TRANSLATE_COUNT   = 'easy_language_translate_count';
const EASY_LANGUAGE_OPTION_TRANSLATE_MAX     = 'easy_language_translate_max';
const EASY_LANGUAGE_OPTION_TRANSLATE_RUNNING = 'easy_language_translate_running';

/**
 * Hash for plugin-installation.
 */
const EASY_LANGUAGE_HASH = 'easy_language_hash';

/**
 * Quota for SUMM AI in free plugin.
 * Changes will not work as this is also checked by API.
 */
const EASY_LANGUAGE_SUMM_AI_QUOTA = 18000;

/**
 * Quota for Capito API.
 * Changes will not work as this is also checked by API.
 */
const EASY_LANGUAGE_CAPITO_QUOTA = 1000000;
