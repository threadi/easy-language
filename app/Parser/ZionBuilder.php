<?php
/**
 * File for parsing Zion Builder for simplifications.
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
 * Handler for parsing Zion Builder-elements.
 *
 * Zion Builder saves its elements as JSON in the post meta "_zionbuilder_page_elements".
 * Each element has the keys "uid", "element_type", "options" and "content" (list of child elements).
 */
class ZionBuilder extends Parser_Base implements Parser {
	/**
	 * The internal name of this parser.
	 *
	 * @var string
	 */
	protected string $name = 'Zion Builder';

	/**
	 * Name of the post meta with the elements.
	 *
	 * @var string
	 */
	private const META_KEY = '_zionbuilder_page_elements';

	/**
	 * Instance of this object.
	 *
	 * @var ?ZionBuilder
	 */
	private static ?ZionBuilder $instance = null;

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
	public static function get_instance(): ZionBuilder {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Define flow-text-widgets.
	 *
	 * The key is the element type, the value the option which holds the text.
	 *
	 * @return array<string,string>
	 */
	private function get_flow_text_widgets(): array {
		$widgets = array(
			'zion_text'    => 'content',
			'zion_heading' => 'content',
		);

		/**
		 * Filter the possible Zion Builder elements.
		 *
		 * @since 3.4.0 Available since 3.4.0.
		 *
		 * @param array<string,string> $widgets List of elements with the option holding its text.
		 */
		return apply_filters( 'easy_language_zionbuilder_text_widgets', $widgets );
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
			'zion_text'    => true,
			'zion_heading' => true,
		);

		/**
		 * Filter the possible Zion Builder elements with HTML-support.
		 *
		 * @since 3.4.0 Available since 3.4.0.
		 *
		 * @param array<string,bool> $html_support_widgets List of elements with HTML-support.
		 */
		$html_widgets = apply_filters( 'easy_language_zionbuilder_html_widgets', $html_support_widgets );

		return isset( $html_widgets[ $widget_name ] );
	}

	/**
	 * Return the elements of the actual object.
	 *
	 * @return array<int|string,mixed>
	 */
	private function get_elements(): array {
		$data = get_post_meta( $this->get_object_id(), self::META_KEY, true );

		// bail if no data is set.
		if ( empty( $data ) || ! is_string( $data ) ) {
			return array();
		}

		// decode the JSON.
		$elements = json_decode( $data, true );

		// bail if the result is not an array.
		if ( ! is_array( $elements ) ) {
			return array();
		}

		return $elements;
	}

	/**
	 * Return parsed texts.
	 *
	 * @return array<array<string,mixed>>
	 */
	public function get_parsed_texts(): array {
		// do nothing if Zion Builder is not active.
		if ( false === $this->is_active() ) {
			return array();
		}

		return $this->get_texts_from_elements( $this->get_elements(), array() );
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
		$flow_text_widgets = $this->get_flow_text_widgets();

		foreach ( $elements as $element ) {
			// bail if the element is not an array.
			if ( ! is_array( $element ) ) {
				continue;
			}

			// get the text if this is a supported element.
			if ( ! empty( $element['element_type'] ) && is_string( $element['element_type'] ) && ! empty( $flow_text_widgets[ $element['element_type'] ] ) ) {
				$option = $flow_text_widgets[ $element['element_type'] ];
				if ( ! empty( $element['options'][ $option ] ) && is_string( $element['options'][ $option ] ) ) {
					$resulting_texts[] = array(
						'text' => $element['options'][ $option ],
						'html' => $this->is_flow_text_widget_html( $element['element_type'] ),
					);
				}
			}

			// loop through the child elements.
			if ( ! empty( $element['content'] ) && is_array( $element['content'] ) ) {
				$resulting_texts = $this->get_texts_from_elements( $element['content'], $resulting_texts );
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
		$flow_text_widgets = $this->get_flow_text_widgets();

		foreach ( $elements as $index => $element ) {
			// bail if the element is not an array.
			if ( ! is_array( $element ) ) {
				continue;
			}

			// replace the text if this is a supported element with the original text.
			if ( ! empty( $element['element_type'] ) && is_string( $element['element_type'] ) && ! empty( $flow_text_widgets[ $element['element_type'] ] ) ) {
				$option = $flow_text_widgets[ $element['element_type'] ];
				if ( isset( $element['options'][ $option ] ) && $this->get_text() === $element['options'][ $option ] ) {
					$elements[ $index ]['options'][ $option ] = $simplified_part;
					$changed                                  = true;
				}
			}

			// loop through the child elements.
			if ( ! empty( $element['content'] ) && is_array( $element['content'] ) ) {
				$elements[ $index ]['content'] = $this->replace_text_in_elements( $element['content'], $simplified_part, $changed );
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
		// do nothing if Zion Builder is not active.
		if ( false === $this->is_active() ) {
			return $original_complete;
		}

		// replace the text in the elements.
		$changed  = false;
		$elements = $this->replace_text_in_elements( $this->get_elements(), $simplified_part, $changed );

		// save the elements, if something has been changed.
		if ( $changed ) {
			$this->save_elements( $elements );
		}

		// return the string for post_content.
		return str_replace( $this->get_text(), $simplified_part, $original_complete );
	}

	/**
	 * Save the elements of the actual object.
	 *
	 * Uses the Zion Builder API, if available, to update its internal cache, too.
	 *
	 * @param array<int|string,mixed> $elements List of elements.
	 *
	 * @return void
	 */
	private function save_elements( array $elements ): void {
		// use the Zion Builder API.
		if ( class_exists( '\ZionBuilder\Plugin' ) && isset( \ZionBuilder\Plugin::$instance->post_manager ) ) {
			$post_instance = \ZionBuilder\Plugin::$instance->post_manager->get_post_instance( $this->get_object_id() );
			if ( is_object( $post_instance ) && method_exists( $post_instance, 'save_template_data' ) ) {
				$post_instance->save_template_data( $elements );
				return;
			}
		}

		// get the JSON.
		$json = wp_json_encode( $elements );

		// bail if JSON could not be created.
		if ( ! is_string( $json ) ) {
			return;
		}

		// save it directly (slashed, as update_post_meta() removes slashes).
		update_post_meta( $this->get_object_id(), self::META_KEY, wp_slash( $json ) );
	}

	/**
	 * Run Zion Builder-specific tasks after the object has been updated.
	 *
	 * Removes the cached CSS- and JS-files of this object, Zion Builder regenerates them.
	 *
	 * @param Objects $post_object The object.
	 *
	 * @return void
	 */
	public function update_object( Objects $post_object ): void {
		if ( class_exists( '\ZionBuilder\Assets' ) ) {
			\ZionBuilder\Assets::delete_post_assets( $post_object->get_id() );
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
		return $this->is_active() && 'enabled' === get_post_meta( $post_object->get_id(), '_zionbuilder_page_status', true );
	}

	/**
	 * Return the edit link for the Zion Builder editor.
	 *
	 * @return string
	 */
	public function get_edit_link(): string {
		if ( $this->is_active() ) {
			return add_query_arg(
				array(
					'post_id' => $this->get_object_id(),
					'action'  => 'zion_builder_active',
				),
				admin_url( 'post.php' )
			);
		}
		return parent::get_edit_link();
	}

	/**
	 * Return whether this parser is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'zionbuilder/zionbuilder.php' );
	}
}
