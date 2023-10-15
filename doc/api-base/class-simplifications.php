<?php
/**
 * File for simplification-handling of this API.
 *
 * @package easy-language-test-api
 */

namespace easyLanguage\Apis\Your_Api;

use easyLanguage\Multilingual_Plugins;

/**
 * SimplificationS-Handling for this plugin.
 */
class Simplifications {
    /**
     * Instance of this object.
     *
     * @var ?Simplifications
     */
    private static ?Simplifications $instance = null;

    /**
     * Init-Object of this API.
     *
     * @var Your_Api
     */
    public Your_Api $init;

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
    public static function get_instance(): Simplifications
    {
        if (!static::$instance instanceof static) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Initialize this object.
     *
     * @param Your_Api $init
     * @return void
     */
    public function init( Your_Api $init ): void {
        $this->init = $init;
    }

    /**
     * Run simplifications of texts via active plugin.
     *
     * @return int Count of simplifications.
     */
    public function run(): int {
        // get active language-mappings.
        $mappings = $this->init->get_active_language_mapping();

        // counter for successfully simplifications.
        $c = 0;

        // get active plugins and check if one of them supports APIs.
        foreach( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
            if( $plugin_obj->is_supporting_apis() ) {
                $c = $c + $plugin_obj->process_simplifications( $this, $mappings );
            }
        }

        // return count of successfully simplifications.
        return $c;
    }

    /**
     * Call API to simplify a single text.
     *
     * @param string $text_to_translate
     * @param string $source_language
     * @param string $target_language
     *
     * @return array
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection*/
    public function call_api( string $text_to_translate, string $source_language, string $target_language ): array {
        return array(
            'translated_text' => 'Das ist der per Test-API übersetzte Text in leichter Sprache.',
            'jobid' => 42
        );
    }
}
