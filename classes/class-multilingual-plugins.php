<?php
/**
 * File for initialisation of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Init the plugin.
 * This object is minify on purpose as the main functions are handled in own objects
 * depending on WordPress-settings.
 */
class Multilingual_Plugins
{

    /**
     * Instance of this object.
     *
     * @var ?Multilingual_Plugins
     */
    private static ?Multilingual_Plugins $instance = null;

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
    public static function get_instance(): Multilingual_Plugins
    {
        if (!static::$instance instanceof static) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Return available multilingual plugin-supports.
     *
     * This should be only one plugin: our easy-language.
     * If another one is active, remove easy-language from list.
     *
     * @return array
     */
    public function get_available_plugins(): array {
        $plugins = apply_filters( 'easy_language_register_plugin', array() );
        if( count($plugins) > 1 ) {
            foreach( $plugins as $index => $plugin_obj ) {
                if( false === $plugin_obj->is_foreign_plugin() ) {
                    unset($plugins[$index]);
                }
            }
        }
        return $plugins;
    }
}
