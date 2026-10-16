<?php
/**
 * File for parsing Fusion page builder (by Agency Dominion) for simplifications.
 *
 * This is not the Fusion Builder of the Avada theme, which is handled by the Avada parser.
 *
 * @package easy-language
 */

namespace easyLanguage\Parser;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Helper;
use easyLanguage\EasyLanguage\Parser;
use easyLanguage\EasyLanguage\Parser_Base;
use easyLanguage\EasyLanguage\Post_Object;

/**
 * Handler for parsing Fusion-shortcodes.
 *
 * Fusion saves its content as shortcodes in post_content, e.g.:
 * [fsn_row][fsn_column][fsn_text]<h2>Heading</h2><p>Text</p>[/fsn_text][/fsn_column][/fsn_row]
 *
 * Headings and paragraphs are part of the HTML in the text element.
 */
class Fusion extends Parser_Base implements Parser {
	use Shortcode_Replacement;

	/**
	 * The internal name of this parser.
	 *
	 * @var string
	 */
	protected string $name = 'Fusion';

	/**
	 * Instance of this object.
	 *
	 * @var ?Fusion
	 */
	private static ?Fusion $instance = null;

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
	public static function get_instance(): Fusion {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Define flow-text-shortcodes.
	 *
	 * The key is the shortcode, the value a list of attributes with texts. An empty list means: the content of the
	 * shortcode is the text.
	 *
	 * @return array<string,array<int,string>>
	 */
	private function get_flow_text_shortcodes(): array {
		$shortcodes = array(
			'fsn_text' => array(),
		);

		/**
		 * Filter the possible Fusion shortcodes.
		 *
		 * @since 3.4.0 Available since 3.4.0.
		 *
		 * @param array<string,array<int,string>> $shortcodes List of shortcodes.
		 */
		return apply_filters( 'easy_language_fusion_text_widgets', $shortcodes );
	}

	/**
	 * Return whether a given widget used HTML or not for its texts.
	 *
	 * @param string $widget_name The requested widget.
	 *
	 * @return bool
	 */
	private function is_flow_text_widget_html( string $widget_name ): bool {
		$html_support_widgets = array(
			'fsn_text' => true,
		);

		/**
		 * Filter the possible Fusion shortcodes with HTML-support.
		 *
		 * @since 3.4.0 Available since 3.4.0.
		 *
		 * @param array<string,bool> $html_support_widgets List of shortcodes with HTML-support.
		 */
		$html_widgets = apply_filters( 'easy_language_fusion_html_widgets', $html_support_widgets );

		return isset( $html_widgets[ $widget_name ] );
	}

	/**
	 * Return parsed texts.
	 *
	 * @return array<array<string,mixed>>
	 */
	public function get_parsed_texts(): array {
		// do nothing if Fusion is not active.
		if ( false === $this->is_active() ) {
			return array();
		}

		// list of resulting texts.
		$resulting_texts = array();

		// get content of supported flow-text-shortcodes.
		foreach ( $this->get_flow_text_shortcodes() as $shortcode => $attributes ) {
			preg_match_all( '/' . get_shortcode_regex( array( $shortcode ) ) . '/s', $this->get_text(), $matches );

			// get the content of the shortcodes.
			if ( empty( $attributes ) && ! empty( $matches[5] ) ) {
				foreach ( $matches[5] as $text ) {
					if ( '' !== trim( $text ) ) {
						$resulting_texts[] = array(
							'text' => $text,
							'html' => $this->is_flow_text_widget_html( $shortcode ),
						);
					}
				}
				continue;
			}

			// get the attributes of the shortcodes.
			if ( ! empty( $attributes ) && ! empty( $matches[3] ) ) {
				foreach ( $matches[3] as $shortcode_attributes_string ) {
					// shortcode_parse_atts() returns a string if no attributes could be parsed.
					$shortcode_attributes = (array) shortcode_parse_atts( $shortcode_attributes_string );
					foreach ( $shortcode_attributes as $attribute => $attribute_value ) {
						if ( ! empty( $attribute_value ) && in_array( $attribute, $attributes, true ) ) {
							$resulting_texts[] = array(
								'text' => $attribute_value,
								'html' => $this->is_flow_text_widget_html( $shortcode ),
							);
						}
					}
				}
			}
		}

		// return the resulting list of parsed texts.
		return $resulting_texts;
	}

	/**
	 * Replace original text with translation.
	 *
	 * @param string $original_complete Complete original content.
	 * @param string $simplified_part The translated content.
	 *
	 * @return string
	 */
	public function get_text_with_simplifications( string $original_complete, string $simplified_part ): string {
		// do nothing if Fusion is not active.
		if ( false === $this->is_active() ) {
			return $original_complete;
		}

		// replace the text only in the supported shortcodes.
		return $this->replace_text_in_shortcodes( $original_complete, $this->get_text(), $simplified_part, $this->get_flow_text_shortcodes() );
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		return $this->is_active() && str_contains( $post_object->get_content(), '[fsn_' );
	}

	/**
	 * Return whether this parser is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'fusion/fusion-core.php' );
	}
}
