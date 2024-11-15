<?php
/**
 * File for handling Themify pagebuilder for simplifications.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language\Parser;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Helper;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser_Base;
use easyLanguage\Multilingual_plugins\Easy_Language\Post_Object;
use ThemifyBuilder_Data_Manager;

/**
 * Handler for parsing themify-content.
 */
class Themify extends Parser_Base implements Parser {
	/**
	 * Internal name of the parser.
	 *
	 * @var string
	 */
	protected string $name = 'Themify';

	/**
	 * Instance of this object.
	 *
	 * @var ?Themify
	 */
	private static ?Themify $instance = null;

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
	public static function get_instance(): Themify {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Define flow-text-widgets.
	 *
	 * @return array
	 */
	private function get_flow_text_widgets(): array {
		$widgets = array(
			'fancy-heading' => array(
				'heading',
				'sub_heading',
			),
			'text'          => array(
				'content_text',
			),
			'buttons'       => array(
				'content_button' => array(
					'label',
				),
			),
		);

		/**
		 * Filter the possible themify widgets.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $widgets List of widgets.
		 */
		return apply_filters( 'easy_language_themify_text_widgets', $widgets );
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
		 * Filter the possible themify widgets with HTML-support.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $html_support_widgets List of widgets with HTML-support.
		 */
		$html_widgets = apply_filters( 'easy_language_themify_html_widgets', $html_support_widgets );

		return isset( $html_widgets[ $widget_name ] );
	}

	/**
	 * Return parsed texts.
	 *
	 * Get the themify-content and parse its widgets to get the content of flow-text-widgets.
	 *
	 * @return array
	 */
	public function get_parsed_texts(): array {
		// do nothing if themify is not active.
		if ( false === $this->is_themify_active() ) {
			return array();
		}

		// list of resulting texts.
		$resulting_texts = array();

		// get editor contents and loop through its array.
		$data = ThemifyBuilder_Data_Manager::get_data( $this->get_object_id() );
		if ( ! empty( $data ) ) {
			foreach ( $data as $container ) {
				$resulting_texts = $this->get_widgets( (array) $container, $resulting_texts );
			}
		}

		// return resulting list.
		return $resulting_texts;
	}

	/**
	 * Replace single original text with translation.
	 *
	 * We replace the text complete 1:1.
	 *
	 * @param string $original_complete Complete original content.
	 * @param string $simplified_part The translated content.
	 * @return string
	 */
	public function get_text_with_simplifications( string $original_complete, string $simplified_part ): string {
		// do nothing if themify is not active.
		if ( false === $this->is_themify_active() ) {
			return $original_complete;
		}

		// get editor contents and loop through its array.
		$data = ThemifyBuilder_Data_Manager::get_data( $this->get_object_id() );
		if ( ! empty( $data ) ) {
			foreach ( $data as $name => $container ) {
				$data[ $name ] = $this->replace_content_in_widgets( (array) $container, $simplified_part );
			}
		}

		// save the data for themify.
		ThemifyBuilder_Data_Manager::save_data( $data, $this->get_object_id() );

		// return the string for post_content.
		return str_replace( $this->get_text(), $simplified_part, $original_complete );
	}

	/**
	 * Return whether themify is active.
	 *
	 * @return bool
	 */
	private function is_themify_active(): bool {
		return Helper::is_plugin_active( 'themify-builder/themify-builder.php' );
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		return ! empty( get_post_meta( $post_object->get_id(), '_themify_builder_settings_json', true ) );
	}

	/**
	 * Return whether this pagebuilder plugin is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->is_themify_active();
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
	 * Run themify-specific updates on object.
	 *
	 * @param Post_Object $post_object The object.
	 *
	 * @return void
	 */
	public function update_object( Post_Object $post_object ): void {
		do_action( 'save_post' );
	}

	/**
	 * Loop through the container to get the flow-text-modules.
	 *
	 * @param array $container The container to parse.
	 * @param array $resulting_texts The resulting list of texts.
	 * @return array
	 */
	private function get_widgets( array $container, array $resulting_texts ): array {
		foreach ( $container as $name => $sub_container ) {
			foreach ( $this->get_flow_text_widgets() as $flow_text_name => $flow_text_settings ) {
				if ( ! is_array( $sub_container ) && 'mod_name' === $name && $sub_container === $flow_text_name ) {
					foreach ( $flow_text_settings as $entry_name => $entry ) {
						if ( is_array( $entry ) ) {
							foreach ( $entry as $sub_entry ) {
								foreach ( $container['mod_settings'][ $entry_name ] as $index2 => $sub_sub_container ) {
									if ( ! empty( $container['mod_settings'][ $entry_name ][ $index2 ][ $sub_entry ] ) ) {
										$resulting_texts[ $container['mod_settings'][ $entry_name ][ $index2 ][ $sub_entry ] ] = array(
											'text' => $container['mod_settings'][ $entry_name ][ $index2 ][ $sub_entry ],
											'html' => $this->is_flow_text_widget_html( $flow_text_name ),
										);
									}
								}
							}
						} else {
							$resulting_texts[ $container['mod_settings'][ $entry ] ] = array(
								'text' => $container['mod_settings'][ $entry ],
								'html' => $this->is_flow_text_widget_html( $flow_text_name ),
							);
						}
					}
				} elseif ( is_array( $sub_container ) ) {
					$resulting_texts = $this->get_widgets( $sub_container, $resulting_texts );
				}
			}
		}

		// return resulting texts.
		return $resulting_texts;
	}

	/**
	 * Replace simplified content in widgets.
	 *
	 * @param array  $container The container to parse.
	 * @param string $simplified_part The simplified text.
	 * @return array
	 */
	private function replace_content_in_widgets( array $container, string $simplified_part ): array {
		// loop through the entries in this container.
		foreach ( $container as $name => $sub_container ) {
			if ( is_array( $sub_container ) ) {
				if ( 'modules' === $name ) {
					foreach ( $sub_container as $index => $module ) {
						foreach ( $this->get_flow_text_widgets() as $flow_text_name => $flow_text_settings ) {
							if ( $module['mod_name'] === $flow_text_name ) {
								foreach ( $flow_text_settings as $entry_name => $entry ) {
									if ( is_array( $entry ) ) {
										foreach ( $entry as $sub_entry ) {
											foreach ( $container[ $name ][ $index ]['mod_settings'][ $entry_name ] as $index2 => $sub_sub_entry ) {
												if ( $this->get_text() === $container[ $name ][ $index ]['mod_settings'][ $entry_name ][ $index2 ][ $sub_entry ] ) {
													$container[ $name ][ $index ]['mod_settings'][ $entry_name ][ $index2 ][ $sub_entry ] = $simplified_part;
												}
											}
										}
									} elseif ( $this->get_text() === $container[ $name ][ $index ]['mod_settings'][ $entry ] ) {
											$container[ $name ][ $index ]['mod_settings'][ $entry ] = $simplified_part;
									}
								}
							}
						}
					}
				} else {
					$container[ $name ] = $this->replace_content_in_widgets( $sub_container, $simplified_part );
				}
			}
		}

		// return resulting container.
		return $container;
	}
}
