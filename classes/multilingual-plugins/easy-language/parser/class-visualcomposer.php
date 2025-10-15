<?php
/**
 * File for handling VisualComposer pagebuilder for simplifications.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language\Parser;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use easyLanguage\Helper;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser_Base;
use easyLanguage\Multilingual_plugins\Easy_Language\Post_Object;

/**
 * Handler for parsing VisualComposer-blocks.
 */
class VisualComposer extends Parser_Base implements Parser {
	/**
	 * Internal name of the parser.
	 *
	 * @var string
	 */
	protected string $name = 'VisualComposer';

	/**
	 * Instance of this object.
	 *
	 * @var ?VisualComposer
	 */
	private static ?VisualComposer $instance = null;

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
	public static function get_instance(): VisualComposer {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Define flow-text-widgets.
	 *
	 * @return array<string,mixed>
	 */
	private function get_flow_text_widgets(): array {
		$widgets = array(
			'textBlock' => array(
				'output',
			),
		);

		/**
		 * Filter the possible Visual Composer widgets.
		 *
		 * @since 2.10.0 Available since 2.10.0.
		 *
		 * @param array<string,mixed> $widgets List of widgets.
		 */
		return apply_filters( 'easy_language_visual_composer_text_widgets', $widgets );
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
			'textBlock' => true,
		);

		/**
		 * Filter the possible Visual Composer widgets with HTML-support.
		 *
		 * @since 2.10.0 Available since 2.10.0.
		 *
		 * @param array $html_support_widgets List of widgets with HTML-support.
		 */
		$html_widgets = apply_filters( 'easy_language_visual_composer_html_widgets', $html_support_widgets );

		return isset( $html_widgets[ $widget_name ] );
	}

	/**
	 * Return parsed texts.
	 *
	 * Loop through the blocks and save their flow-text-elements (e.g. paragraphs and headings) to list.
	 *
	 * @return array<array<string,mixed>>
	 */
	public function get_parsed_texts(): array {
		// do nothing if Brizy is not active.
		if ( ! defined( 'VCV_PREFIX' ) || false === $this->is_active() ) {
			return array();
		}

		// list of resulting texts.
		$resulting_texts = array();

		// get editor contents and loop through its array.
		$data = get_post_meta( $this->get_object_id(), VCV_PREFIX . 'pageContent', true );
		if ( ! empty( $data ) ) {
			// decode the data as JSON-string.
			$json = rawurldecode( $data );

			// get array from this json.
			$editor_data = json_decode( $json, true );

			// bail if result is not an array.
			if( ! is_array( $editor_data ) ) {
				return array();
			}

			// bail if items is missing.
			if( empty( $editor_data['elements'] ) ) {
				return array();
			}

			// get the texts.
			$resulting_texts = $this->get_widgets( $editor_data['elements'], $resulting_texts );
		}

		// return resulting list.
		return $resulting_texts;
	}

	/**
	 * Replace original text with translation.
	 * This is done 1:1 for Kubio.
	 *
	 * @param string $original_complete Complete original content.
	 * @param string $simplified_part The translated content.
	 *
	 * @return string
	 */
	public function get_text_with_simplifications( string $original_complete, string $simplified_part ): string {
		// do nothing if Brizy is not active.
		if ( ! defined( 'VCV_PREFIX' ) || false === $this->is_active() ) {
			return $original_complete;
		}

		// get editor contents and loop through its array.
		$data = get_post_meta( $this->get_object_id(), VCV_PREFIX . 'pageContent', true );
		if ( ! empty( $data ) ) {
			// decode the data as JSON-string.
			$json = rawurldecode( $data );

			// get array from this json.
			$editor_data = json_decode( $json, true );

			// bail if result is not an array.
			if( ! is_array( $editor_data ) ) {
				return $original_complete;
			}

			// bail if items is missing.
			if( empty( $editor_data['elements'] ) ) {
				return $original_complete;
			}

			// replace the texts.
			$text = $this->replace_content_in_widgets( $editor_data['elements'], $simplified_part );

			// encode as JSON.
			$json = wp_json_encode( $text );

			// encode as URL and save it.
			update_post_meta( $this->get_object_id(), VCV_PREFIX . 'pageContent', rawurlencode( $json ) );
		}

		// return the string for post_content.
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
		return ! empty( get_post_meta( $post_object->get_id(), 'vcv-pageContent', true ) );
	}

	/**
	 * Return whether this pagebuilder plugin is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'visualcomposer/plugin-wordpress.php' );
	}

	/**
	 * Loop through the container to get the flow-text-modules.
	 *
	 * @param array<string,mixed> $container The container to parse.
	 * @param array<string,mixed> $resulting_texts The resulting texts.
	 * @return array<string,mixed>
	 */
	private function get_widgets( array $container, array $resulting_texts ): array {
		// get list of flow text widgets.
		$flow_text_widgets = $this->get_flow_text_widgets();

		foreach ( $container as $section ) {
			// bail if section is not an array.
			if( ! is_array( $section ) ) {
				continue;
			}

			// if section is of element type "block", get its contents.
			if( isset( $section['tag'] ) && ! empty( $flow_text_widgets[$section['tag']] ) ) {
				foreach( $flow_text_widgets[$section['tag']] as $entry_name ) {
					if( empty( $section[$entry_name] ) ) {
						continue;
					}
					$resulting_texts[] = array(
						'text' => $section[$entry_name],
						'html' => $this->is_flow_text_widget_html($section['tag']),
					);
				}
			}
		}

		// return resulting texts.
		return $resulting_texts;
	}

	/**
	 * Replace simplified content in widgets.
	 *
	 * @param array<string,mixed> $container The container to parse.
	 * @param string              $simplified_part The simplified text.
	 * @return array<string,mixed>
	 */
	private function replace_content_in_widgets( array $container, string $simplified_part ): array {
		// get list of flow text widgets.
		$flow_text_widgets = $this->get_flow_text_widgets();

		foreach ( $container as $index => $section ) {
			// bail if section is not an array.
			if( ! is_array( $section ) ) {
				continue;
			}

			// if section is of element type "block", get its contents.
			if( isset( $section['type'] ) && ! empty( $flow_text_widgets[$section['type']] ) ) {
				foreach( $flow_text_widgets[$section['type']] as $entry_name ) {
					if( empty( $section['value'][$entry_name] ) ) {
						continue;

					}
					$container[ $index ]['value'][ $entry_name ] = $simplified_part;
				}
			}
		}

		// return resulting container.
		return $container;
	}
}
