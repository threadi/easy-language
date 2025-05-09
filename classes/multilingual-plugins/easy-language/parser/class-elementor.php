<?php
/**
 * File for handling Elementor pagebuilder for simplifications.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language\Parser;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Elementor\Core\Files\CSS\Post as Post_CSS;
use Elementor\Plugin;
use easyLanguage\Helper;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser_Base;
use easyLanguage\Multilingual_plugins\Easy_Language\Post_Object;

/**
 * Handler for parsing Elementor-blocks.
 */
class Elementor extends Parser_Base implements Parser {
	/**
	 * Internal name of the parser.
	 *
	 * @var string
	 */
	protected string $name = 'Elementor';

	/**
	 * Instance of this object.
	 *
	 * @var ?Elementor
	 */
	private static ?Elementor $instance = null;

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
	public static function get_instance(): Elementor {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Define flow-text-widgets.
	 *
	 * @return array
	 */
	private function get_flow_text_widgets(): array {
		$widgets = array(
			'text-editor'          => array(
				'editor',
			),
			'heading'              => array(
				'title',
			),
			'testimonial-carousel' => array(
				'slides' => array(
					'content',
				),
			),
			'icon-list'            => array(
				'icon_list' => array(
					'text',
				),
			),
			'button'               => array(
				'text',
			),
		);

		/**
		 * Filter the possible Elementor widgets.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $widgets List of widgets.
		 */
		return apply_filters( 'easy_language_elementor_text_widgets', $widgets );
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
			'text-editor' => true,
		);

		/**
		 * Filter the possible Elementor widgets with HTML-support.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $html_support_widgets List of widgets with HTML-support.
		 */
		$html_widgets = apply_filters( 'easy_language_elementor_html_widgets', $html_support_widgets );

		return isset( $html_widgets[ $widget_name ] );
	}

	/**
	 * Return parsed texts.
	 *
	 * Get the elementor-content and parse its widgets to get the content of flow-text-widgets.
	 *
	 * @return array<array<string,mixed>>
	 */
	public function get_parsed_texts(): array {
		// do nothing if elementor is not active.
		if ( false === $this->is_elementor_active() ) {
			return array();
		}

		// define returning list.
		$resulting_texts = array();

		// get the actual elementor_data to get the texts of supported widgets.
		$data = Plugin::$instance->documents->get( $this->get_object_id() )->get_elements_data();
		if ( ! empty( $data ) ) {
			foreach ( $data as $container ) {
				if ( ! empty( $container['elements'] ) ) {
					foreach ( $container['elements'] as $widget ) {
						$resulting_texts = $this->get_widgets( (array) $widget, $resulting_texts );
					}
				}
			}
		}

		// return resulting texts.
		return $resulting_texts;
	}

	/**
	 * Replace single original text with translation.
	 *
	 * We replace the text complete 1:1.
	 *
	 * @param string $original_complete The original text.
	 * @param string $simplified_part The simplified text-part.
	 * @return string
	 */
	public function get_text_with_simplifications( string $original_complete, string $simplified_part ): string {
		// do nothing if elementor is not active.
		if ( false === $this->is_elementor_active() ) {
			return $original_complete;
		}

		// replace content in postmeta "_elementor_data".
		$data = Plugin::$instance->documents->get( $this->get_object_id() )->get_elements_data();
		if ( ! empty( $data ) ) {
			foreach ( $data as $index1 => $container ) {
				if ( ! empty( $container['elements'] ) ) {
					foreach ( $container['elements'] as $index2 => $widget ) {
						$data[ $index1 ]['elements'][ $index2 ] = $this->replace_content_in_widgets( $widget, $simplified_part );
					}
				}
			}
		}
		update_post_meta( $this->get_object_id(), '_elementor_data', wp_slash( wp_json_encode( $data ) ) );

		// replacement for post_content.
		return str_replace( $this->get_text(), $simplified_part, $original_complete );
	}

	/**
	 * Loop through the elementor-widget to get the contents of the defined
	 * flow-text-widgets.
	 *
	 * @param array $widget The widget-array.
	 * @param array $resulting_texts The resulting texts as array.
	 * @return array
	 */
	private function get_widgets( array $widget, array $resulting_texts ): array {
		// get content if it is a valid flow-text-widget.
		$flow_text_widgets = $this->get_flow_text_widgets();
		if ( ! empty( $widget['widgetType'] ) && ! empty( $flow_text_widgets[ $widget['widgetType'] ] ) ) {
			foreach ( $flow_text_widgets[ $widget['widgetType'] ] as $name => $text ) {
				if ( is_array( $text ) ) {
					foreach ( $widget['settings'][ $name ] as $index => $content ) {
						foreach ( $text as $content_key ) {
							if ( ! empty( $widget['settings'][ $name ][ $index ][ $content_key ] ) ) {
								$resulting_texts[] = array(
									'text' => $widget['settings'][ $name ][ $index ][ $content_key ],
									'html' => $this->is_flow_text_widget_html( $widget['widgetType'] ),
								);
							}
						}
					}
				} elseif ( ! empty( $widget['settings'][ $text ] ) ) {
						$resulting_texts[] = array(
							'text' => $widget['settings'][ $text ],
							'html' => $this->is_flow_text_widget_html( $widget['widgetType'] ),
						);
				}
			}
		}

		// loop through inner-widgets.
		foreach ( $widget['elements'] as $sub_widget ) {
			$resulting_texts = $this->get_widgets( $sub_widget, $resulting_texts );
		}

		// return resulting list.
		return $resulting_texts;
	}

	/**
	 * Replace the original widget texts with the translated texts.
	 * Only in the supported flow-text-elements.
	 *
	 * @param array  $widget The widget-array.
	 * @param string $simplified_text The simplified text-part.
	 * @return array
	 */
	private function replace_content_in_widgets( array $widget, string $simplified_text ): array {
		// get content if it is a valid flow-text-widget.
		$flow_text_widgets = $this->get_flow_text_widgets();
		if ( ! empty( $widget['widgetType'] ) && ! empty( $flow_text_widgets[ $widget['widgetType'] ] ) ) {
			foreach ( $flow_text_widgets[ $widget['widgetType'] ] as $name => $text ) {
				if ( is_array( $text ) ) {
					foreach ( $widget['settings'][ $name ] as $index => $content ) {
						foreach ( $text as $content_key ) {
							if ( $this->get_text() === $widget['settings'][ $name ][ $index ][ $content_key ] ) {
								$widget['settings'][ $name ][ $index ][ $content_key ] = $simplified_text;
							}
						}
					}
				} elseif ( $this->get_text() === $widget['settings'][ $text ] ) {
						$widget['settings'][ $text ] = $simplified_text;
				}
			}
		}

		// loop through inner-widgets.
		foreach ( $widget['elements'] as $index => $sub_widget ) {
			$widget['elements'][ $index ] = $this->replace_content_in_widgets( $sub_widget, $simplified_text );
		}

		// return resulting widget.
		return $widget;
	}

	/**
	 * Return whether Elementor is active.
	 *
	 * @return bool
	 */
	private function is_elementor_active(): bool {
		return Helper::is_plugin_active( 'elementor/elementor.php' );
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The post-object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		return get_post_meta( $post_object->get_id(), '_elementor_data', true );
	}

	/**
	 * Return edit link for elementor-object.
	 *
	 * @return string
	 */
	public function get_edit_link(): string {
		if ( $this->is_elementor_active() ) {
			return Plugin::$instance->documents->get( $this->get_object_id() )->get_edit_url();
		}
		return parent::get_edit_link();
	}

	/**
	 * Return whether this pagebuilder plugin is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->is_elementor_active();
	}

	/**
	 * Run Elementor-specific updates on object.
	 *
	 * @param Post_Object $post_object The object.
	 *
	 * @return void
	 */
	public function update_object( Post_Object $post_object ): void {
		// update object-specific css.
		$post_css = Post_CSS::create( $post_object->get_id() );
		$post_css->update();
	}
}
