<?php
/**
 * File for parsing Brizy pagebuilder for simplifications.
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
 * Handler for parsing Brizy-content.
 */
class Brizy extends Parser_Base implements Parser {
	/**
	 * Internal name of the parser.
	 *
	 * @var string
	 */
	protected string $name = 'Brizy';

	/**
	 * Instance of this object.
	 *
	 * @var ?Brizy
	 */
	private static ?Brizy $instance = null;

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
	public static function get_instance(): Brizy {
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
			'RichText' => array(
				'text',
			),
		);

		/**
		 * Filter the possible Brizy widgets.
		 *
		 * @since 2.10.0 Available since 2.10.0.
		 *
		 * @param array<string,mixed> $widgets List of widgets.
		 */
		return apply_filters( 'easy_language_brizy_text_widgets', $widgets );
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
			'RichText' => true,
		);

		/**
		 * Filter the possible Brizy widgets with HTML-support.
		 *
		 * @since 2.10.0 Available since 2.10.0.
		 *
		 * @param array $html_support_widgets List of widgets with HTML-support.
		 */
		$html_widgets = apply_filters( 'easy_language_brizy_html_widgets', $html_support_widgets );

		return isset( $html_widgets[ $widget_name ] );
	}

	/**
	 * Return parsed texts.
	 *
	 * Get the Brizy-content and parse its widgets to get the content of flow-text-widgets.
	 *
	 * @return array<string,mixed>
	 */
	public function get_parsed_texts(): array {
		// do nothing if Brizy is not active.
		if ( false === $this->is_brizy_active() ) {
			return array();
		}

		// list of resulting texts.
		$resulting_texts = array();

		// get editor contents and loop through its array.
		$data = get_post_meta( $this->get_object_id(), 'brizy', true );
		if ( ! empty( $data['brizy-post']['editor_data'] ) ) {
			// decode the data as JSON-string.
			$json = base64_decode( $data['brizy-post']['editor_data'] );

			// bail if decoded data is not a string.
			if ( ! is_string( $json ) ) { // @phpstan-ignore function.alreadyNarrowedType
				return array();
			}

			// get array from this json.
			$editor_data = json_decode( $json, true );

			// bail if result is not an array.
			if ( ! is_array( $editor_data ) ) {
				return array();
			}

			// bail if items is missing.
			if ( empty( $editor_data['items'] ) ) {
				return array();
			}

			// get the texts.
			$resulting_texts = $this->get_widgets( $editor_data['items'], $resulting_texts );
		}

		// return resulting list.
		return $resulting_texts;
	}

	/**
	 * Replace single original text with simplifications.
	 *
	 * We replace the text complete 1:1.
	 *
	 * @param string $original_complete Complete original content.
	 * @param string $simplified_part The simplified content.
	 * @return string
	 */
	public function get_text_with_simplifications( string $original_complete, string $simplified_part ): string {
		// do nothing if Brizy is not active.
		if ( false === $this->is_brizy_active() ) {
			return $original_complete;
		}

		// get editor contents and loop through its array.
		$data = get_post_meta( $this->get_object_id(), 'brizy', true );
		if ( ! empty( $data['brizy-post']['editor_data'] ) ) {
			// decode the data as JSON-string.
			$json = base64_decode( $data['brizy-post']['editor_data'] );

			// bail if decoded data is not a string.
			if ( ! is_string( $json ) ) { // @phpstan-ignore function.alreadyNarrowedType
				return $original_complete;
			}

			// get array from this json.
			$editor_data = json_decode( $json, true );

			// bail if result is not an array.
			if ( ! is_array( $editor_data ) ) {
				return $original_complete;
			}

			// bail if items are missing.
			if ( empty( $editor_data['items'] ) ) {
				return $original_complete;
			}

			// replace the texts.
			$text = $this->replace_content_in_widgets( $editor_data['items'], $simplified_part );

			// encode as JSON.
			$data['brizy-post']['editor_data'] = Helper::get_json( $text );

			// encode editor data.
			$data['brizy-post']['editor_data'] = base64_encode( $data['brizy-post']['editor_data'] );

			// save the data for Brizy.
			update_post_meta( $this->get_object_id(), 'brizy', $data );
		}

		// return the string for post_content.
		return str_replace( $this->get_text(), $simplified_part, $original_complete );
	}

	/**
	 * Return whether SiteOrigin is active.
	 *
	 * @return bool
	 */
	private function is_brizy_active(): bool {
		return Helper::is_plugin_active( 'brizy/brizy.php' );
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		return ! empty( get_post_meta( $post_object->get_id(), 'brizy_enabled', true ) );
	}

	/**
	 * Return whether this parser is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->is_brizy_active();
	}

	/**
	 * Prevent a translate-option in the frontend.
	 *
	 * @return bool
	 */
	public function hide_translate_menu_in_frontend(): bool {
		return true;
	}

	/**
	 * Loop through the container to get the flow-text-modules.
	 *
	 * @param array<string,mixed>     $container The container to parse.
	 * @param array<int|string,mixed> $resulting_texts The resulting texts.
	 * @return array<string,mixed>
	 */
	private function get_widgets( array $container, array $resulting_texts ): array {
		// get the list of flow text widgets.
		$flow_text_widgets = $this->get_flow_text_widgets();

		foreach ( $container as $section ) {
			// bail if the section is not an array.
			if ( ! is_array( $section ) ) {
				continue;
			}

			// if the section is of element type "block", get its contents.
			if ( isset( $section['type'] ) && ! empty( $flow_text_widgets[ $section['type'] ] ) ) {
				foreach ( $flow_text_widgets[ $section['type'] ] as $entry_name ) {
					if ( empty( $section['value'][ $entry_name ] ) ) {
						continue;
					}
					$resulting_texts[] = array(
						'text' => $section['value'][ $entry_name ],
						'html' => $this->is_flow_text_widget_html( $section['type'] ),
					);
				}
			} else {
				// loop through the deeper arrays.
				$resulting_texts = $this->get_widgets( $section['value']['items'], $resulting_texts );
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
		// get a list of flow text widgets.
		$flow_text_widgets = $this->get_flow_text_widgets();

		foreach ( $container as $index => $section ) {
			// bail if section is not an array.
			if ( ! is_array( $section ) ) {
				continue;
			}

			// if section is of element type "block", get its contents.
			if ( isset( $section['type'] ) && ! empty( $flow_text_widgets[ $section['type'] ] ) ) {
				foreach ( $flow_text_widgets[ $section['type'] ] as $entry_name ) {
					if ( empty( $section['value'][ $entry_name ] ) ) {
						continue;

					}
					$container[ $index ]['value'][ $entry_name ] = $simplified_part;
				}
			} else {
				// loop through the deeper arrays.
				$container = $this->replace_content_in_widgets( $section['value']['items'], $simplified_part );
			}
		}

		// return resulting container.
		return $container;
	}
}
