<?php
/**
 * File for handling Divi pagebuilder for simplifications.
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
 * Handler for parsing Divi-content.
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
			'et_pb_text'   => array(),
			'et_pb_button' => array(
				'button_text',
			),
			'et_pb_blurb'  => array(),
		);

		/**
		 * Filter the possible Divi shortcodes.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array<string,mixed> $shortcodes List of shortcodes.
		 */
		return apply_filters( 'easy_language_divi_text_widgets', $shortcodes );
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
			'et_pb_text' => true,
		);

		/**
		 * Filter the possible Divi widgets with HTML-support.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array<string,mixed> $html_support_widgets List of widgets with HTML-support.
		 */
		$html_widgets = apply_filters( 'easy_language_divi_html_widgets', $html_support_widgets );

		return isset( $html_widgets[ $widget_name ] );
	}

	/**
	 * Return parsed texts.
	 *
	 * Get the Divi-content and parse its widgets to get the content of flow-text-widgets.
	 *
	 * @return array<array<string,mixed>>
	 */
	public function get_parsed_texts(): array {
		// do nothing if Divi is not active.
		if ( ! \easyLanguage\PageBuilder\Divi::get_instance()->is_active() ) {
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

		// return resulting list.
		return $resulting_texts;
	}

	/**
	 * Replace original text with translation.
	 *
	 * We replace the text complete 1:1.
	 *
	 * @param string $original_complete The original text.
	 * @param string $simplified_part The simplified text-part.
	 * @return string
	 */
	public function get_text_with_simplifications( string $original_complete, string $simplified_part ): string {
		// do nothing if Divi is not active.
		if ( false === \easyLanguage\PageBuilder\Divi::get_instance()->is_active() ) {
			return $original_complete;
		}

		return str_replace( $this->get_text(), $simplified_part, $original_complete );
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		return 'on' === get_post_meta( $post_object->get_id(), '_et_pb_use_builder', true );
	}

	/**
	 * Return edit link for Divi-object.
	 *
	 * @return string
	 */
	public function get_edit_link(): string {
		if ( \easyLanguage\PageBuilder\Divi::get_instance()->is_active() ) {
			return add_query_arg(
				array(
					'et_fb'     => '1',
					'PageSpeed' => 'Off',
				),
				et_fb_prepare_ssl_link( get_permalink( $this->get_object_id() ) )
			);
		}
		return parent::get_edit_link();
	}

	/**
	 * Prevent the translate-option in the frontend.
	 *
	 * @return bool
	 */
	public function hide_translate_menu_in_frontend(): bool {
		return et_core_is_fb_enabled();
	}
}
