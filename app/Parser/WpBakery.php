<?php
/**
 * File for handling WPBakery pagebuilder for simplifications.
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
 * Handler for parsing wp bakery-content.
 */
class WpBakery extends Parser_Base implements Parser {
	/**
	 * Internal name of the parser.
	 *
	 * @var string
	 */
	protected string $name = 'WPBakery';

	/**
	 * Instance of this object.
	 *
	 * @var ?WpBakery
	 */
	private static ?WpBakery $instance = null;

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
	public static function get_instance(): WpBakery {
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
			'vc_column_text' => array(),
			'vc_btn'         => array(
				'title',
			),
			'block_title'    => array(
				'title',
			),
			'vc_toggle'      => array(
				'title',
			),
		);

		/**
		 * Filter the shortcodes.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array<string,mixed> $shortcodes List of shortcodes.
		 */
		return apply_filters( 'easy_language_wpbakery_text_widgets', $shortcodes );
	}

	/**
	 * Return whether a given widget used HTML or not for its texts.
	 *
	 * @param string $widget_name Name of the used widget.
	 *
	 * @return bool
	 */
	private function is_flow_text_widget_html( string $widget_name ): bool {
		$html_support_widgets = array(
			'vc_column_text' => true,
		);

		/**
		 * Filter the possible WP Bakery widgets with HTML-support.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array<string,mixed> $html_support_widgets List of widgets with HTML-support.
		 */
		$html_widgets = apply_filters( 'easy_language_wpbakery_html_widgets', $html_support_widgets );

		return isset( $html_widgets[ $widget_name ] );
	}

	/**
	 * Return parsed texts.
	 *
	 * Get the wp bakery-content and parse its widgets to get the content of flow-text-widgets.
	 *
	 * @return array<array<string,mixed>>
	 */
	public function get_parsed_texts(): array {
		// do nothing if wp bakery is not active.
		if ( false === $this->is_wp_bakery_active() ) {
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
				if ( ! empty( ! empty( $matches[5] ) ) ) {
					foreach ( $matches[5] as $texts ) {
						if ( ! empty( $texts ) ) {
							$resulting_texts[] = array(
								'text' => $texts,
								'html' => $this->is_flow_text_widget_html( $shortcode ),
							);
						}
					}
				}
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
	 * @param string $original_complete Complete original content.
	 * @param string $simplified_part The translated content.
	 * @return string
	 */
	public function get_text_with_simplifications( string $original_complete, string $simplified_part ): string {
		// do nothing if wp bakery is not active.
		if ( false === $this->is_wp_bakery_active() ) {
			return $original_complete;
		}

		return str_replace( $this->get_text(), $simplified_part, $original_complete );
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
	 * @param Post_Object $post_object The object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		return $this->is_active() && 'true' === get_post_meta( $post_object->get_id(), '_wpb_vc_js_status', true );
	}

	/**
	 * Return edit link for wp bakery-object.
	 *
	 * @return string
	 */
	public function get_edit_link(): string {
		return add_query_arg(
			array(
				'vc_action' => 'vc_inline',
				'post_id'   => $this->get_object_id(),
				'post_type' => 'page',
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

	/**
	 * Run WPBakery-specific updates on object.
	 *
	 * @param Post_Object $post_object The object.
	 *
	 * @return void
	 */
	public function update_object( Post_Object $post_object ): void {
		do_action( 'save_post' );
	}
}
