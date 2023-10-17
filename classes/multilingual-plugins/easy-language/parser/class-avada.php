<?php
/**
 * File for handling Avada pagebuilder for simplifications.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language\Parser;

use easyLanguage\Multilingual_plugins\Easy_Language\Parser;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser_Base;
use easyLanguage\Multilingual_plugins\Easy_Language\Post_Object;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handler for parsing avada-content.
 */
class Avada extends Parser_Base implements Parser {
	/**
	 * Internal name of the parser.
	 *
	 * @var string
	 */
	protected string $name = 'Avada';

	/**
	 * Instance of this object.
	 *
	 * @var ?Avada
	 */
	private static ?Avada $instance = null;

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
	public static function get_instance(): Avada {
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
		return apply_filters(
			'easy_language_avada_text_widgets',
			array(
				'fusion_title' => array(),
				'fusion_text' => array()
			)
		);
	}

	/**
	 * Return parsed texts.
	 *
	 * Get the avada-content and parse its widgets to get the content of flow-text-widgets.
	 *
	 * @return array
	 */
	public function get_parsed_texts(): array {
		// do nothing if avada is not active.
		if ( false === $this->is_avada_active() ) {
			return array();
		}

		// list of resulting texts.
		$resulting_texts = array();

		// get content of supported flow-text-shortcodes.
		foreach ( $this->get_flow_text_shortcodes() as $shortcode => $attributes ) {
			preg_match_all( '/' . get_shortcode_regex( array( $shortcode ) ) . '/s', $this->get_text(), $matches );
			if ( empty($attributes) && ! empty( $matches[5] ) ) {
				foreach( $matches[5] as $texts ) {
					if (!empty($texts)) {
						$resulting_texts[] = $texts;
					}
				}
			}
			elseif( !empty($attributes) && !empty($matches[2]) && !empty($matches[3]) ) {
				foreach( $matches[2] as $key => $value ) {
					foreach( shortcode_parse_atts($matches[3][$key]) as $attribute => $attribute_value ) {
						if( in_array( $attribute, $attributes, true ) && !empty($attribute_value) ) {
							$resulting_texts[] = $attribute_value;
						}
					}
				}
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
	 * @param string $original_complete Complete original content.
	 * @param string $simplified_part The translated content.
	 * @return string
	 */
	public function get_text_with_simplifications( string $original_complete, string $simplified_part ): string {
		// do nothing if avada is not active.
		if ( false === $this->is_avada_active() ) {
			return $original_complete;
		}

		return str_replace( $this->get_text(), $simplified_part, $original_complete );
	}

	/**
	 * Return whether Avada is active.
	 *
	 * @return bool
	 */
	private function is_avada_active(): bool {
		$is_avada = false;
		$theme   = wp_get_theme();
		if ( 'Avada' === $theme->get( 'Name' ) ) {
			$is_avada = true;
		}
		if ( $theme->parent() && 'Avada' === $theme->parent()->get( 'Name' ) ) {
			$is_avada = true;
		}
		return $is_avada;
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		return 'active' === get_post_meta( $post_object->get_id(), 'fusion_builder_status', true );
	}

	/**
	 * Return whether this pagebuilder plugin is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->is_avada_active();
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
