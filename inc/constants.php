<?php
/**
 * Constants used by this WordPress-plugin.
 *
 * @package easy-language
 */

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SUMM AI API URL for free version.
 */
const EASY_LANGUAGE_SUMM_AI_API_URL = 'http://dev4.laolaweb.com/summaiproxy/index.php';

/**
 * Set transient-based hints for the backend.
 */
const EASY_LANGUAGE_TRANSIENTS = array(
	'easy_language_message' => array(),
	'easy_language_refresh_rewrite_rules' => array()
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
 * options-list of transients.
 */
const EASY_LANGUAGE_TRANSIENT_LIST = 'el_transients';

/**
 * Define names for progressbar during translation.
 */
const EASY_LANGUAGE_OPTION_TRANSLATE_COUNT = 'elTranslateCount';
const EASY_LANGUAGE_OPTION_TRANSLATE_MAX = 'elTranslateMax';
const EASY_LANGUAGE_OPTION_TRANSLATE_RUNNING = 'elTranslateRunning';

/**
 * Hash for plugin-installation.
 */
const EASY_LANGUAGE_HASH = 'elHash';

/**
 * Quota for free plugin.
 * Changes will not work as this is also checked by API.
 */
const EASY_LANGUAGE_SUMM_AI_QUOTA = 18000;
