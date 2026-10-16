<?php
/**
 * File for parsing Oxygen (classic, up to version 4.x) for simplifications.
 *
 * @package easy-language
 */

namespace easyLanguage\Parser;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Helper;
use easyLanguage\EasyLanguage\Objects;
use easyLanguage\EasyLanguage\Parser;
use easyLanguage\EasyLanguage\Parser_Base;
use easyLanguage\EasyLanguage\Post_Object;

/**
 * Handler for parsing Oxygen-elements.
 *
 * Oxygen saves its elements as JSON-tree in the post meta "_ct_builder_json". The shortcodes in
 * "_ct_builder_shortcodes" are only used as fallback, if the JSON is missing.
 *
 * Each element has the keys "id", "name", "options" (with the text in "ct_content") and "children".
 */
class Oxygen extends Parser_Base implements Parser {
	/**
	 * The internal name of this parser.
	 *
	 * @var string
	 */
	protected string $name = 'Oxygen';

	/**
	 * Name of the post meta with the JSON-tree.
	 *
	 * @var string
	 */
	private const META_KEY = '_ct_builder_json';

	/**
	 * Instance of this object.
	 *
	 * @var ?Oxygen
	 */
	private static ?Oxygen $instance = null;

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
	public static function get_instance(): Oxygen {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Define flow-text-widgets.
	 *
	 * @return array<string,bool>
	 */
	private function get_flow_text_widgets(): array {
		$widgets = array(
			'ct_headline'   => true,
			'ct_text_block' => true,
			'oxy_rich_text' => true,
		);

		/**
		 * Filter the possible Oxygen elements.
		 *
		 * @since 3.4.0 Available since 3.4.0.
		 *
		 * @param array<string,bool> $widgets List of elements.
		 */
		return apply_filters( 'easy_language_oxygen_text_widgets', $widgets );
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
			'ct_headline'   => true,
			'ct_text_block' => true,
			'oxy_rich_text' => true,
		);

		/**
		 * Filter the possible Oxygen elements with HTML-support.
		 *
		 * @since 3.4.0 Available since 3.4.0.
		 *
		 * @param array<string,bool> $html_support_widgets List of elements with HTML-support.
		 */
		$html_widgets = apply_filters( 'easy_language_oxygen_html_widgets', $html_support_widgets );

		return isset( $html_widgets[ $widget_name ] );
	}

	/**
	 * Return whether the given element is a supported flow-text-element with a usable text.
	 *
	 * Texts with placeholders for inline child elements (e.g. a span or a link) are not supported, as the
	 * simplification could remove the placeholders and the child elements would be lost.
	 *
	 * @param array<int|string,mixed> $element The element.
	 *
	 * @return bool
	 */
	private function is_flow_text_element( array $element ): bool {
		// bail if this is not a supported element.
		if ( empty( $element['name'] ) || ! is_string( $element['name'] ) || empty( $this->get_flow_text_widgets()[ $element['name'] ] ) ) {
			return false;
		}

		// bail if no text is set.
		if ( empty( $element['options']['ct_content'] ) || ! is_string( $element['options']['ct_content'] ) ) {
			return false;
		}

		// bail if the text contains placeholders for inline child elements.
		return ! str_contains( $element['options']['ct_content'], 'ct-placeholder-' );
	}

	/**
	 * Return the JSON-tree of the actual object.
	 *
	 * @return array<int|string,mixed>
	 */
	private function get_tree(): array {
		$data = get_post_meta( $this->get_object_id(), self::META_KEY, true );

		// bail if no data is set.
		if ( empty( $data ) || ! is_string( $data ) ) {
			return array();
		}

		// decode the JSON.
		$tree = json_decode( $data, true );

		// bail if the result is not an array.
		if ( ! is_array( $tree ) ) {
			return array();
		}

		return $tree;
	}

	/**
	 * Return parsed texts.
	 *
	 * @return array<array<string,mixed>>
	 */
	public function get_parsed_texts(): array {
		// do nothing if Oxygen is not active.
		if ( false === $this->is_active() ) {
			return array();
		}

		// get the tree.
		$tree = $this->get_tree();

		// bail if no children are set.
		if ( empty( $tree['children'] ) || ! is_array( $tree['children'] ) ) {
			return array();
		}

		return $this->get_texts_from_elements( $tree['children'], array() );
	}

	/**
	 * Loop through the elements and get the texts of the flow-text-elements.
	 *
	 * @param array<int|string,mixed> $elements List of elements.
	 * @param array<int,mixed>        $resulting_texts The resulting texts.
	 *
	 * @return array<int,mixed>
	 */
	private function get_texts_from_elements( array $elements, array $resulting_texts ): array {
		foreach ( $elements as $element ) {
			// bail if the element is not an array.
			if ( ! is_array( $element ) ) {
				continue;
			}

			// get the text if this is a supported element.
			if ( $this->is_flow_text_element( $element ) ) {
				$resulting_texts[] = array(
					'text' => $element['options']['ct_content'],
					'html' => $this->is_flow_text_widget_html( $element['name'] ),
				);
			}

			// loop through the child elements.
			if ( ! empty( $element['children'] ) && is_array( $element['children'] ) ) {
				$resulting_texts = $this->get_texts_from_elements( $element['children'], $resulting_texts );
			}
		}

		return $resulting_texts;
	}

	/**
	 * Replace the original text with its simplification in the elements and their child elements.
	 *
	 * @param array<int|string,mixed> $elements List of elements.
	 * @param string                  $simplified_part The simplified text.
	 * @param bool                    $changed Will be set to true if a text has been replaced.
	 *
	 * @return array<int|string,mixed>
	 */
	private function replace_text_in_elements( array $elements, string $simplified_part, bool &$changed ): array {
		foreach ( $elements as $index => $element ) {
			// bail if the element is not an array.
			if ( ! is_array( $element ) ) {
				continue;
			}

			// replace the text if this is a supported element with the original text.
			if ( $this->is_flow_text_element( $element ) && $this->get_text() === $element['options']['ct_content'] ) {
				$elements[ $index ]['options']['ct_content'] = $simplified_part;
				$changed                                     = true;
			}

			// loop through the child elements.
			if ( ! empty( $element['children'] ) && is_array( $element['children'] ) ) {
				$elements[ $index ]['children'] = $this->replace_text_in_elements( $element['children'], $simplified_part, $changed );
			}
		}

		return $elements;
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
		// do nothing if Oxygen is not active.
		if ( false === $this->is_active() ) {
			return $original_complete;
		}

		// get the tree.
		$tree = $this->get_tree();

		// replace the texts, if children are set.
		if ( ! empty( $tree['children'] ) && is_array( $tree['children'] ) ) {
			$changed          = false;
			$tree['children'] = $this->replace_text_in_elements( $tree['children'], $simplified_part, $changed );

			// save the tree, if something has been changed.
			if ( $changed ) {
				$json = wp_json_encode( $tree, JSON_UNESCAPED_UNICODE );
				if ( is_string( $json ) ) {
					// save it slashed, as update_post_meta() removes slashes (like Oxygen does it).
					update_post_meta( $this->get_object_id(), self::META_KEY, wp_slash( $json ) );
				}
			}
		}

		// return the string for post_content.
		return str_replace( $this->get_text(), $simplified_part, $original_complete );
	}

	/**
	 * Run Oxygen-specific tasks after the object has been updated.
	 *
	 * Regenerates the cached CSS of this object.
	 *
	 * @param Objects $post_object The object.
	 *
	 * @return void
	 */
	public function update_object( Objects $post_object ): void {
		if ( function_exists( 'oxygen_vsb_cache_page_css' ) ) {
			oxygen_vsb_cache_page_css( $post_object->get_id() );
		}
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		// bail if Oxygen is not active.
		if ( ! $this->is_active() ) {
			return false;
		}

		// check the JSON for elements, like Oxygen does it.
		$data = get_post_meta( $post_object->get_id(), self::META_KEY, true );
		if ( empty( $data ) || ! is_string( $data ) ) {
			return false;
		}
		$tree = json_decode( $data, true );
		return is_array( $tree ) && ! empty( $tree['children'] );
	}

	/**
	 * Return the edit link for the Oxygen editor.
	 *
	 * @return string
	 */
	public function get_edit_link(): string {
		if ( $this->is_active() && function_exists( 'oxy_get_builder_url' ) ) {
			return oxy_get_builder_url( $this->get_object_id() );
		}
		if ( $this->is_active() && function_exists( 'ct_get_post_builder_link' ) ) {
			return ct_get_post_builder_link( $this->get_object_id() );
		}
		return parent::get_edit_link();
	}

	/**
	 * Return whether this parser is active.
	 *
	 * Only the classic Oxygen up to version 4.x is supported here.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		if ( ! Helper::is_plugin_active( 'oxygen/functions.php' ) ) {
			return false;
		}
		return ! defined( 'CT_VERSION' ) || version_compare( (string) constant( 'CT_VERSION' ), '6.0.0', '<' );
	}
}
