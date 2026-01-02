<?php
/**
 * File for parsing SeedProd pagebuilder for simplifications.
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
 * Handler for parsing SeedProd-content.
 */
class SeedProd extends Parser_Base implements Parser {
	/**
	 * Internal name of the parser.
	 *
	 * @var string
	 */
	protected string $name = 'SeedProd';

	/**
	 * Instance of this object.
	 *
	 * @var ?SeedProd
	 */
	private static ?SeedProd $instance = null;

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
	public static function get_instance(): SeedProd {
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
			'header' => array(
				'headerTxt',
			),
			'text'   => array(
				'txt',
			),
		);

		/**
		 * Filter the possible SeedProd widgets.
		 *
		 * @since 2.10.0 Available since 2.10.0.
		 *
		 * @param array<string,mixed> $widgets List of widgets.
		 */
		return apply_filters( 'easy_language_seedprod_text_widgets', $widgets );
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
			'text' => true,
		);

		/**
		 * Filter the possible SeedProd widgets with HTML-support.
		 *
		 * @since 2.10.0 Available since 2.10.0.
		 *
		 * @param array $html_support_widgets List of widgets with HTML-support.
		 */
		$html_widgets = apply_filters( 'easy_language_seedprod_html_widgets', $html_support_widgets );

		return isset( $html_widgets[ $widget_name ] );
	}

	/**
	 * Return parsed texts.
	 *
	 * Get the SeedProd-content and parse its widgets to get the content of flow-text-widgets.
	 *
	 * @return array<string,mixed>
	 */
	public function get_parsed_texts(): array {
		// do nothing if SeedProd is not active.
		if ( false === $this->is_seedprod_active() ) {
			return array();
		}

		// list of resulting texts.
		$resulting_texts = array();

		// get editor contents and loop through its array.
		$data_json = get_post_field( 'post_content_filtered', $this->get_object_id() );

		// bail if it is not a string.
		if ( ! is_string( $data_json ) ) { // @phpstan-ignore function.alreadyNarrowedType
			return array();
		}

		// decode the JSON string to an array.
		$data = json_decode( $data_json, true );

		// get its texts.
		if ( is_array( $data ) && ! empty( $data['document'] ) ) {
			$resulting_texts = $this->get_widgets( $data['document'], $resulting_texts );
		}

		// return the resulting list.
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
		// do nothing if SeedProd is not active.
		if ( false === $this->is_seedprod_active() ) {
			return $original_complete;
		}

		// get editor contents and loop through its array.
		$data_json = get_post_field( 'post_content_filtered', $this->get_object_id() );

		// bail if it is not a string.
		if ( ! is_string( $data_json ) ) { // @phpstan-ignore function.alreadyNarrowedType
			return $original_complete;
		}

		// decode the JSON-string to an array.
		$data = json_decode( $data_json, true );

		// get its texts.
		if ( is_array( $data ) && ! empty( $data['document'] ) ) {
			$data['document'] = $this->replace_content_in_widgets( $data['document'], $simplified_part );
		}

		// save the data for SeedProd.
		$query = array(
			'ID'                    => $this->get_object_id(),
			'post_content_filtered' => (string) wp_json_encode( $data ),
		);
		wp_insert_post( $query );

		// return the string for post_content.
		return str_replace( $this->get_text(), $simplified_part, $original_complete );
	}

	/**
	 * Return whether SeedProd is active.
	 *
	 * @return bool
	 */
	private function is_seedprod_active(): bool {
		return Helper::is_plugin_active( 'coming-soon/coming-soon.php' );
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		return ! empty( get_post_field( 'post_content_filtered', $post_object->get_id() ) );
	}

	/**
	 * Return whether this parser is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->is_seedprod_active();
	}

	/**
	 * Prevent the translate-option in the frontend.
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
		foreach ( $container as $section ) {
			// bail if the section is not an array.
			if ( ! is_array( $section ) ) {
				continue;
			}

			// if the section is of element type "block", get its contents.
			if ( isset( $section['elType'] ) && 'block' === $section['elType'] ) {
				foreach ( $this->get_flow_text_widgets() as $flow_text_name => $flow_text_settings ) {
					if ( $flow_text_name !== $section['type'] ) {
						continue;
					}
					foreach ( $flow_text_settings as $entry_name ) {
						if ( empty( $section['settings'][ $entry_name ] ) ) {
							continue;
						}
						$resulting_texts[] = array(
							'text' => $section['settings'][ $entry_name ],
							'html' => $this->is_flow_text_widget_html( $flow_text_name ),
						);
					}
				}
			} else {
				// loop through the deeper arrays.
				$resulting_texts = $this->get_widgets( $section, $resulting_texts );
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
		foreach ( $container as $index => $section ) {
			// bail if section is not an array.
			if ( ! is_array( $section ) ) {
				continue;
			}

			// if section is of element type "block", get its contents.
			if ( isset( $section['elType'] ) && 'block' === $section['elType'] ) {
				foreach ( $this->get_flow_text_widgets() as $flow_text_name => $flow_text_settings ) {
					if ( $flow_text_name !== $section['type'] ) {
						continue;
					}
					foreach ( $flow_text_settings as $entry_name ) {
						if ( empty( $section['settings'][ $entry_name ] ) ) {
							continue;
						}
						if ( $this->get_text() === $section['settings'][ $entry_name ] ) {
							$container[ $index ]['settings'][ $entry_name ] = $simplified_part;
						}
					}
				}
			}
		}

		// return resulting container.
		return $container;
	}
}
