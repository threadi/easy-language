<?php
/**
 * File for parsing Avia pagebuilder for simplifications.
 *
 * @package easy-language
 */

namespace easyLanguage\Parser;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\Parser;
use easyLanguage\EasyLanguage\Parser_Base;
use easyLanguage\EasyLanguage\Post_Object;

/**
 * Handler for parsing Avia-content.
 */
class Avia extends Parser_Base implements Parser {
	/**
	 * Internal name of the parser.
	 *
	 * @var string
	 */
	protected string $name = 'Avia';

	/**
	 * Instance of this object.
	 *
	 * @var ?Avia
	 */
	private static ?Avia $instance = null;

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
	public static function get_instance(): Avia {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Define flow-text-shortcodes.
	 *
	 * @return array<string,mixed>
	 */
	private function get_flow_text_shortcodes(): array {
		$shortcodes = array(
			'av_heading'   => array(
				'heading',
			),
			'av_textblock' => array(),
		);

		/**
		 * Filter the possible Avia shortcodes.
		 *
		 * @since 2.6.0 Available since 2.6.0.
		 *
		 * @param array<string,mixed> $shortcodes List of shortcodes.
		 */
		return apply_filters( 'easy_language_avia_text_widgets', $shortcodes );
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
			'av_textblock' => true,
		);

		/**
		 * Filter the possible Avia widgets with HTML-support.
		 *
		 * @since 2.6.0 Available since 2.6.0.
		 *
		 * @param array<string,mixed> $html_support_widgets List of widgets with HTML-support.
		 */
		$html_widgets = apply_filters( 'easy_language_avia_html_widgets', $html_support_widgets );

		return isset( $html_widgets[ $widget_name ] );
	}

	/**
	 * Return parsed texts.
	 *
	 * Get the Avia-content and parse its widgets to get the content of flow-text-widgets.
	 *
	 * @return array<array<string,mixed>>
	 */
	public function get_parsed_texts(): array {
		// do nothing if Avia is not active.
		if ( false === $this->is_avia_active() ) {
			return array();
		}

		// list of resulting texts.
		$resulting_texts = array();

		// get content of supported flow-text-shortcodes.
		foreach ( $this->get_flow_text_shortcodes() as $shortcode => $attributes ) {
			preg_match_all( '/' . get_shortcode_regex( array( $shortcode ) ) . '/s', $this->get_text(), $matches );
			if ( empty( $attributes ) && ! empty( $matches[5] ) ) {
				foreach ( $matches[5] as $texts ) {
					if ( ! empty( $texts ) ) {
						$resulting_texts[] = array(
							'text' => $texts,
							'html' => $this->is_flow_text_widget_html( $shortcode ),
						);
					}
				}
			} elseif ( ! empty( $attributes ) && ! empty( $matches[2] ) && ! empty( $matches[3] ) ) {
				foreach ( $matches[2] as $key => $value ) {
					foreach ( shortcode_parse_atts( $matches[3][ $key ] ) as $attribute => $attribute_value ) {
						if ( in_array( $attribute, $attributes, true ) && ! empty( $attribute_value ) ) {
							$resulting_texts[] = array(
								'text' => $attribute_value,
								'html' => $this->is_flow_text_widget_html( $shortcode ),
							);
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
		// do nothing if Avia is not active.
		if ( false === $this->is_avia_active() ) {
			return $original_complete;
		}

		// get the avia builder object.
		$avia_builder = Avia_Builder();

		// bail if builder is not available.
		if ( is_null( $avia_builder ) ) {
			return $original_complete;
		}

		$text = str_replace(
			array(
				$this->get_text(),
				'<!-- wp:shortcode -->',
				'<!-- /wp:shortcode -->',
			),
			array( $simplified_part, '', '' ),
			$original_complete
		);

		// save the updated Avia content.
		$avia_builder->update_post_content( $this->get_object_id(), $text );

		// return the content for post_content.
		return str_replace( $this->get_text(), $simplified_part, $original_complete );
	}

	/**
	 * Return whether Avia is active.
	 *
	 * @return bool
	 */
	private function is_avia_active(): bool {
		// get the theme object.
		$theme = wp_get_theme();

		// return true if Avia Builder exists as object.
		if ( function_exists( 'Avia_Builder' ) ) {
			return true;
		}

		// return true if the theme is Enfold itself.
		if ( 'Enfold' === $theme->get( 'Name' ) ) {
			return true;
		}

		// return true if it is a child-theme with Enfold as parent.
		if ( $theme->parent() && 'Enfold' === $theme->parent()->get( 'Name' ) ) {
			return true;
		}

		// return false if it Avia is not used.
		return false;
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		return 'active' === get_post_meta( $post_object->get_id(), '_aviaLayoutBuilder_active', true );
	}

	/**
	 * Return whether this parser is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->is_avia_active();
	}
}
