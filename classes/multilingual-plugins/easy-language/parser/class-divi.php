<?php
/**
 * File for handling Divi pagebuilder for translations.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language\Parser;

use easyLanguage\Helper;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser_Base;
use easyLanguage\Multilingual_plugins\Easy_Language\Post_Object;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handler for parsing divi-content.
 */
class Divi extends Parser_Base implements Parser {
    /**
     * Internal name of the parser.
     *
     * @var string
     */
    protected string $name = 'Divi';

    /**
     * Instance of this object.
     *
     * @var ?Divi
     */
    private static ?Divi $instance = null;

    /**
     * Constructor for this object.
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
    public static function get_instance(): Divi {
        if ( ! static::$instance instanceof static ) {
            static::$instance = new static();
        }

        return static::$instance;
    }

	/**
	 * Define flow-text-shortcodes.
	 *
	 * @return array
	 */
	private function get_flow_text_shortcodes(): array {
		return array(
			'et_pb_text'
		);
	}

    /**
     * Return parsed texts.
     *
     * Get the divi-content and parse its widgets to get the content of flow-text-widgets.
     *
     * @return array
     */
    public function get_parsed_texts(): array {
        // do nothing if divi is not active.
        if( false === $this->is_divi_active() ) {
            return array();
        }

		// list of resulting texts.
		$resulting_texts = array();

		// get content of supported flow-text-shortcodes.
	    foreach( $this->get_flow_text_shortcodes() as $shortcode ) {
		    preg_match_all( '/' . get_shortcode_regex( array( $shortcode ) ) . '/s', $this->get_text(), $matches );
		    if ( ! empty( $matches[5][0] ) ) {
			    $resulting_texts[] = $matches[5][0];
		    }
	    }

		// return resulting list.
        return $resulting_texts;
    }

    /**
     * Replace original text with translation.
     *
     * We replace the text complete 1:1.
     *
     * @param string $original_complete
     * @param string $translated_part
     * @return string
     */
    public function get_text_with_translations( string $original_complete, string $translated_part ): string {
	    // do nothing if divi is not active.
	    if( false === $this->is_divi_active() ) {
		    return $original_complete;
	    }

        return str_replace( $this->get_text(), $translated_part, $original_complete );
    }

    /**
     * Return whether Divi is active.
     *
     * @return bool
     */
    private function is_divi_active(): bool {
	    $is_divi = Helper::is_plugin_active( 'divi-builder/divi-builder.php' );
	    $theme = wp_get_theme();
	    if( 'Divi' === $theme->get( 'Name' ) ) {
		    $is_divi = true;
	    }
	    if( $theme->parent() && 'Divi' === $theme->parent()->get( 'Name' ) ) {
		    $is_divi = true;
	    }
        return $is_divi || Helper::is_plugin_active( 'divi-builder/divi-builder.php' );
    }

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $object
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $object ): bool {
		return 'on' === get_post_meta( $object->get_id(), '_et_pb_use_builder', true );
	}

	/**
	 * Return edit link for divi-object.
	 *
	 * @return string
	 */
	public function get_edit_link(): string {
		return add_query_arg(
			array(
				'et_fb'     => '1',
				'PageSpeed' => 'Off'
			),
			et_fb_prepare_ssl_link( get_permalink( $this->get_object_id() ) )
		);
	}

	/**
	 * Return whether this pagebuilder plugin is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->is_divi_active();
	}

	/**
	 * Prevent translate-option in frontend.
	 *
	 * @return bool
	 */
	public function hide_translate_menu_in_frontend(): bool {
		return et_core_is_fb_enabled();
	}
}
