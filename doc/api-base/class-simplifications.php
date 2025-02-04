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
