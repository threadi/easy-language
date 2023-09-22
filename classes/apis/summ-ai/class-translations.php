<?php
/**
 * File for translations-handling of this API.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\Summ_Ai;

use easyLanguage\Multilingual_Plugins;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Translations-Handling for this plugin.
 */
class Translations {
    /**
     * Instance of this object.
     *
     * @var ?Translations
     */
    private static ?Translations $instance = null;

    /**
     * Init-Object of this API.
     *
     * @var Summ_AI
     */
    public Summ_AI $init;

    /**
     * Constructor for Init-Handler.
     */
    private function __construct() {}

    /**
     * Prevent cloning of this object.
     *
     * @return void
     */
    private function __clone() {}

    /**
     * Return the instance of this Singleton object.
     */
    public static function get_instance(): Translations
    {
        if (!static::$instance instanceof static) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Initialize this object.
     *
     * @param Summ_AI $init
     * @return void
     */
    public function init( Summ_AI $init ): void {
        $this->init = $init;
    }

	/**
	 * Run translations of texts via active plugin.
	 *
	 * @return int Count of translations.
	 */
    public function run(): int {
        // get active language-mappings.
        $mappings = $this->init->get_active_language_mapping();

        // counter for successfully translations.
        $c = 0;

        // get active plugins and check if one of them supports APIs.
        foreach( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
            if( $plugin_obj->is_supporting_apis() ) {
                $c = $c + $plugin_obj->process_translations( $this, $mappings );
            }
        }

        // return count of successfully translations.
        return $c;
    }

    /**
     * Call API to translate single text.
     *
     * @param string $text_to_translate
     * @param string $source_language
     * @param string $target_language
     * @return array
     * @noinspection PhpUnused
     */
    public function call_api( string $text_to_translate, string $source_language, string $target_language ): array {
		// build request.
        $request = new Request();
        $request->set_url( EASY_LANGUAGE_SUMM_AI_API_URL );
        $request->set_text( $text_to_translate );
        $request->set_text_type( 'plain_text' );
        $request->set_source_language( $source_language );
        $request->set_target_language( $target_language );
        $request->send();

        // return result depending on http-status.
        if ( 200 === $request->get_http_status() ) {
            // get the response.
            $response = $request->get_response();

            // transform it to array.
            $request_array = json_decode( $response, true );

			// keep the given html-attributes around the text if testmode is enabled.
	        // otherwise use the code from SUMM AI API 1:1.
	        $translated_text = $request_array['translated_text'];

			// save character-count to quota if answer does not contain "no_count".
	        if( empty($request_array['no_count']) ) {
		        update_option( 'easy_language_summ_ai_quota', absint( get_option( 'easy_language_summ_ai_quota', 0 ) ) + strlen( $text_to_translate ) );
	        }

            // return translation to plugin which will save it.
            return array(
                'translated_text' => $translated_text,
                'jobid' => absint($request_array['jobid'])
            );
        }

        // return nothing.
        return array();
    }
}
