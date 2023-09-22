<?php
/**
 * File for handling WPBakery pagebuilder for translations.
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
 * Handler for parsing wp bakery-content.
 */
class WP_Bakery extends Parser_Base implements Parser {
	/**
	 * Internal name of the parser.
	 *
	 * @var string
	 */
	protected string $name = 'WPBakery';

	/**
	 * Instance of this object.
	 *
	 * @var ?WP_Bakery
	 */
	private static ?WP_Bakery $instance = null;

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
	public static function get_instance(): WP_Bakery {
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
			'vc_column_text'
		);
	}

	/**
	 * Return parsed texts.
	 *
	 * Get the wp bakery-content and parse its widgets to get the content of flow-text-widgets.
	 *
	 * @return array
	 */
	public function get_parsed_texts(): array {
		// do nothing if wp bakery is not active.
		if( false === $this->is_wp_bakery_active() ) {
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
		// do nothing if wp bakery is not active.
		if( false === $this->is_wp_bakery_active() ) {
			return $original_complete;
		}

		return str_replace( $this->get_text(), $translated_part, $original_complete );
	}

	/**
	 * Return whether WP Bakery is active.
	 *
	 * @return bool
	 */
	private function is_wp_bakery_active(): bool {
		return Helper::is_plugin_active( 'js_composer/js_composer.php' );
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $object
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $object ): bool {
		return 'true' === get_post_meta( $object->get_id(), '_wpb_vc_js_status', true );
	}

	/**
	 * Return edit link for wp bakery-object.
	 *
	 * @return string
	 */
	public function get_edit_link(): string {
		return add_query_arg(
			array(
				'vc_action'     => 'vc_inline',
				'post_id' => $this->get_object_id(),
				'post_type' => 'page'
			),
			get_admin_url() . 'post.php'
		);
	}

	/**
	 * Return whether this pagebuilder plugin is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->is_wp_bakery_active();
	}

	/**
	 * Prevent translate-option in frontend.
	 *
	 * @return bool
	 */
	public function hide_translate_menu_in_frontend(): bool {
		return true;
	}
}
