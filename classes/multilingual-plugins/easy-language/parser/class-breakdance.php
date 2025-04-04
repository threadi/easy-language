<?php
/**
 * File for handling Breakdance pagebuilder for simplifications.
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
use function Breakdance\Data\get_tree;
use function Breakdance\Data\set_meta;

/**
 * Handler for parsing Breakdance-blocks.
 */
class Breakdance extends Parser_Base implements Parser {
	/**
	 * Internal name of the parser.
	 *
	 * @var string
	 */
	protected string $name = 'Breakdance';

	/**
	 * Instance of this object.
	 *
	 * @var ?Breakdance
	 */
	private static ?Breakdance $instance = null;

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
	public static function get_instance(): Breakdance {
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
			'EssentialElements\Text'          => array(
				'properties' => array(
					'content' => array(
						'content' => array(
							'text'
						)
					)
				)
			),
			'EssentialElements\PostTitle'              => array(
				'properties' => array(
					'content' => array(
						'content' => array(
							'text'
						)
					)
				)
			),
		);

		/**
		 * Filter the possible Breakdance widgets.
		 *
		 * @since 2.7.0 Available since 2.7.0.
		 *
		 * @param array $widgets List of widgets.
		 */
		return apply_filters( 'easy_language_breakdance_text_widgets', $widgets );
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
			'EssentialElements\Text' => true,
		);

		/**
		 * Filter the possible Breakdance widgets with HTML-support.
		 *
		 * @since 2.7.0 Available since 2.7.0.
		 *
		 * @param array $html_support_widgets List of widgets with HTML-support.
		 */
		$html_widgets = apply_filters( 'easy_language_breakdance_html_widgets', $html_support_widgets );

		return isset( $html_widgets[ $widget_name ] );
	}

	/**
	 * Return parsed texts.
	 *
	 * Get the Breakdance-content and parse its widgets to get the content of flow-text-widgets.
	 *
	 * @return array
	 */
	public function get_parsed_texts(): array {
		// do nothing if Breakdance is not active.
		if ( false === $this->is_breakdance_active() ) {
			return array();
		}

		// get the actual _breakdance_data to get the texts of supported widgets.
		$data = get_tree( $this->get_object_id() );

		// bail if data is empty.
		if ( empty( $data ) ) {
			return array();
		}

		// bail if root does not exist.
		if( empty( $data['root']['children'] ) ) {
			return array();
		}

		// define returning list.
		$resulting_texts = array();

		// collect the texts.
		foreach ( $data['root']['children'] as $widget ) {
			// bail if no data is set.
			if ( empty( $widget['data'] ) ) {
				continue;
			}

			$resulting_texts = $this->get_widgets( $widget, $resulting_texts );
		}

		// return resulting texts.
		return $resulting_texts;
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

		// get the contents of this widget, if it is allowed.
		if ( ! empty( $widget['data']['type'] ) && ! empty( $flow_text_widgets[$widget['data']['type']] ) ) {
			// add this widget with its content to the list.
			$resulting_texts[] = array(
				'text' => $widget['data'][ 'properties' ][ 'content' ][ 'content' ][ 'text' ],
				'html' => $this->is_flow_text_widget_html( $widget['data']['type'] ),
			);
		}

		// loop through inner-widgets.
		if( ! empty( $widget['children'] ) ) {
			foreach ( $widget['children'] as $sub_widget ) {
				$resulting_texts = $this->get_widgets( $sub_widget, $resulting_texts );
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
	 * @param string $original_complete The original text.
	 * @param string $simplified_part The simplified text-part.
	 * @return string
	 */
	public function get_text_with_simplifications( string $original_complete, string $simplified_part ): string {
		// do nothing if Breakdance is not active.
		if ( false === $this->is_breakdance_active() ) {
			return $original_complete;
		}

		// get the content in postmeta "_breakdance_data".
		$data = get_tree( $this->get_object_id() );

		// bail if data is empty.
		if ( empty( $data ) ) {
			return $original_complete;
		}

		// bail if root does not exist.
		if( empty( $data['root']['children'] ) ) {
			return $original_complete;
		}

		// replace the texts.
		foreach ( $data['root']['children'] as $index => $container ) {
			// bail if no data is set.
			if ( empty( $widget['data'] ) ) {
				continue;
			}

			// bail if text does not match.
			if( $this->get_text() !== $data['root']['children'][ $index ][ 'properties' ][ 'content' ][ 'content' ][ 'text' ] ) {
				continue;
			}

			// replace the text.
			$data['root']['children'][ $index ][ 'properties' ][ 'content' ][ 'content' ][ 'text' ] = $simplified_part;
		}

		// save it.
		set_meta(
			$this->get_object_id(),
			'_breakdance_data',
			[
				'tree_json_string' => $data,
			]
		);

		// replacement for post_content.
		return str_replace( $this->get_text(), $simplified_part, $original_complete );
	}

	/**
	 * Return whether Breakdance is active.
	 *
	 * @return bool
	 */
	private function is_breakdance_active(): bool {
		return Helper::is_plugin_active( 'breakdance/plugin.php' );
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The post-object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		return get_post_meta( $post_object->get_id(), '_breakdance_data', true );
	}

	/**
	 * Return edit link for Breakdance-object.
	 *
	 * @return string
	 */
	public function get_edit_link(): string {
		if ( $this->is_breakdance_active() ) {
			return \Breakdance\Admin\get_builder_loader_url((string) $this->get_object_id() );
		}
		return parent::get_edit_link();
	}

	/**
	 * Return whether this pagebuilder plugin is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->is_breakdance_active();
	}
}
