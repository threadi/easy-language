<?php
/**
 * File for parsing BeaverBuilder pagebuilder for simplifications.
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
use stdClass;

/**
 * Handler for parsing BeaverBuilder-content.
 */
class BeaverBuilder extends Parser_Base implements Parser {
	/**
	 * Internal name of the parser.
	 *
	 * @var string
	 */
	protected string $name = 'BeaverBuilder';

	/**
	 * Instance of this object.
	 *
	 * @var ?BeaverBuilder
	 */
	private static ?BeaverBuilder $instance = null;

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
	public static function get_instance(): BeaverBuilder {
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
			'heading'   => array(
				'heading',
			),
			'callout'   => array(
				'text',
			),
			'rich-text' => array(
				'text',
			),
		);

		/**
		 * Filter the possible BeaverBuilder widgets.
		 *
		 * @since 2.10.0 Available since 2.10.0.
		 *
		 * @param array<string,mixed> $widgets List of widgets.
		 */
		return apply_filters( 'easy_language_beaverbuilder_text_widgets', $widgets );
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
			'callout'   => true,
			'rich-text' => true,
		);

		/**
		 * Filter the possible BeaverBuilder widgets with HTML-support.
		 *
		 * @since 2.10.0 Available since 2.10.0.
		 *
		 * @param array $html_support_widgets List of widgets with HTML-support.
		 */
		$html_widgets = apply_filters( 'easy_language_beaverbuilder_html_widgets', $html_support_widgets );

		return isset( $html_widgets[ $widget_name ] );
	}

	/**
	 * Return parsed texts.
	 *
	 * Get the BeaverBuilder-content and parse its widgets to get the content of flow-text-widgets.
	 *
	 * @return array<string,mixed>
	 */
	public function get_parsed_texts(): array {
		// do nothing if BeaverBuilder is not active.
		if ( false === $this->is_beaverbuilder_active() ) {
			return array();
		}

		// list of resulting texts.
		$resulting_texts = array();

		// get editor contents and loop through its array.
		$data = get_post_meta( $this->get_object_id(), '_fl_builder_data', true );
		if ( is_array( $data ) && ! empty( $data ) ) {
			$resulting_texts = $this->get_widgets( $data, $resulting_texts );
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
		// do nothing if BeaverBuilder is not active.
		if ( false === $this->is_beaverbuilder_active() ) {
			return $original_complete;
		}

		// get editor contents and loop through its array.
		$data = get_post_meta( $this->get_object_id(), '_fl_builder_data', true );
		if ( is_array( $data ) && ! empty( $data ) ) {
			$data['document'] = $this->replace_content_in_widgets( $data, $simplified_part );
		}

		// save the data for BeaverBuilder.
		update_post_meta( $this->get_object_id(), '_fl_builder_data', $data );

		// return the string for post_content.
		return str_replace( $this->get_text(), $simplified_part, $original_complete );
	}

	/**
	 * Return whether BeaverBuilder is active.
	 *
	 * @return bool
	 */
	private function is_beaverbuilder_active(): bool {
		return Helper::is_plugin_active( 'beaver-builder-lite-version/fl-builder.php' );
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		return ! empty( get_post_meta( $post_object->get_id(), '_fl_builder_enabled', true ) );
	}

	/**
	 * Return whether this parser is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->is_beaverbuilder_active();
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
	 * Loop through the container to get the flow-text-modules.
	 *
	 * @param array<string,mixed> $container The container to parse.
	 * @param array<string,mixed> $resulting_texts The resulting texts.
	 * @return array<string,mixed>
	 */
	private function get_widgets( array $container, array $resulting_texts ): array {
		foreach ( $container as $section ) {
			// bail if section is not an array.
			if ( ! $section instanceof stdClass ) {
				continue;
			}

			// if section is of element type "module", get its contents.
			if ( 'module' === $section->type ) {
				foreach ( $this->get_flow_text_widgets() as $flow_text_name => $flow_text_settings ) {
					if ( $flow_text_name !== $section->settings->type ) {
						continue;
					}
					foreach ( $flow_text_settings as $entry_name ) {
						if ( empty( $section->settings->{$entry_name} ) ) {
							continue;
						}

						// get the content.
						$content = $section->settings->{$entry_name};

						// bail if trimmed content is empty.
						if ( empty( trim( $content ) ) ) {
							continue;
						}

						// add the content to the list.
						$resulting_texts[] = array(
							'text' => $content,
							'html' => $this->is_flow_text_widget_html( $flow_text_name ),
						);
					}
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
		foreach ( $container as $index => $section ) {
			// bail if section is not an array.
			if ( ! $section instanceof stdClass ) {
				continue;
			}

			// if section is of element type "module", get its contents.
			if ( 'module' === $section->type ) {
				foreach ( $this->get_flow_text_widgets() as $flow_text_name => $flow_text_settings ) {
					if ( $flow_text_name !== $section->settings->type ) {
						continue;
					}
					foreach ( $flow_text_settings as $entry_name ) {
						if ( empty( $section->settings->{$entry_name} ) ) {
							continue;
						}

						// get the content.
						$content = $section->settings->{$entry_name};

						// bail if trimmed content is empty.
						if ( empty( trim( $content ) ) ) {
							continue;
						}

						if ( $this->get_text() === $content ) {
							$container[ $index ]->settings->{$entry_name} = $simplified_part;
						}
					}
				}
			}
		}

		// return resulting container.
		return $container;
	}
}
