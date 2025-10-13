<?php
/**
 * File for handling SiteOrigin pagebuilder for simplifications.
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

/**
 * Handler for parsing SiteOrigin-content.
 */
class SiteOrigin extends Parser_Base implements Parser {
	/**
	 * Internal name of the parser.
	 *
	 * @var string
	 */
	protected string $name = 'SiteOrigin';

	/**
	 * Instance of this object.
	 *
	 * @var ?SiteOrigin
	 */
	private static ?SiteOrigin $instance = null;

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
	public static function get_instance(): SiteOrigin {
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
			'widget_text' => array(
				'title',
				'text',
			),
		);

		/**
		 * Filter the possible SiteOrigin widgets.
		 *
		 * @since 2.10.0 Available since 2.10.0.
		 *
		 * @param array<string,mixed> $widgets List of widgets.
		 */
		return apply_filters( 'easy_language_siteorigin_text_widgets', $widgets );
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
		 * Filter the possible Siteorigin widgets with HTML-support.
		 *
		 * @since 2.10.0 Available since 2.10.0.
		 *
		 * @param array $html_support_widgets List of widgets with HTML-support.
		 */
		$html_widgets = apply_filters( 'easy_language_siteorigin_html_widgets', $html_support_widgets );

		return isset( $html_widgets[ $widget_name ] );
	}

	/**
	 * Return parsed texts.
	 *
	 * Get the SiteOrigin-content and parse its widgets to get the content of flow-text-widgets.
	 *
	 * @return array<string,mixed>
	 */
	public function get_parsed_texts(): array {
		// do nothing if siteorigin is not active.
		if ( false === $this->is_siteorgin_active() ) {
			return array();
		}

		// list of resulting texts.
		$resulting_texts = array();

		// get editor contents and loop through its array.
		$data = get_post_meta( $this->get_object_id(), 'panels_data', true );
		if ( ! empty( $data['widgets'] ) ) {
			$resulting_texts = $this->get_widgets( $data['widgets'], $resulting_texts );
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
		// do nothing if siteorigin is not active.
		if ( false === $this->is_siteorgin_active() ) {
			return $original_complete;
		}

		// get editor contents and loop through its array.
		$data = get_post_meta( $this->get_object_id(), 'panels_data', true );
		if ( ! empty( $data['widgets'] ) ) {
			$data['widgets'] = $this->replace_content_in_widgets( $data['widgets'], $simplified_part );
		}

		// save the data for siteorigin.
		update_post_meta( $this->get_object_id(), 'panels_data', $data );

		// return the string for post_content.
		return str_replace( $this->get_text(), $simplified_part, $original_complete );
	}

	/**
	 * Return whether SiteOrigin is active.
	 *
	 * @return bool
	 */
	private function is_siteorgin_active(): bool {
		return Helper::is_plugin_active( 'siteorigin-panels/siteorigin-panels.php' );
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		return ! empty( get_post_meta( $post_object->get_id(), 'siteorigin_page_settings', true ) );
	}

	/**
	 * Return whether this pagebuilder plugin is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->is_siteorgin_active();
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
		foreach ( $container as $sub_container ) {
			foreach ( $this->get_flow_text_widgets() as $flow_text_name => $flow_text_settings ) {
				if ( is_array( $sub_container ) && $sub_container['option_name'] === $flow_text_name ) {
					foreach ( $flow_text_settings as $entry_name ) {
						$resulting_texts[] = array(
							'text' => $sub_container[ $entry_name ],
							'html' => $this->is_flow_text_widget_html( $entry_name ),
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
		foreach ( $container as $index => $sub_container ) {
			foreach ( $this->get_flow_text_widgets() as $flow_text_name => $flow_text_settings ) {
				if ( is_array( $sub_container ) && $sub_container['option_name'] === $flow_text_name ) {
					foreach ( $flow_text_settings as $entry_name ) {
						if ( $this->get_text() === $container[ $index ][ $entry_name ] ) {
							if ( 'title' === $entry_name ) {
								$container[ $index ][ $entry_name ] = wp_strip_all_tags( $simplified_part );
							} else {
								$container[ $index ][ $entry_name ] = $simplified_part;
							}
						}
					}
				}
			}
		}

		// return resulting container.
		return $container;
	}
}
