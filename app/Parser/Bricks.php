<?php
/**
 * File for parsing Bricks pagebuilder for simplifications.
 *
 * @package easy-language
 */

namespace easyLanguage\Parser;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\Parser;
use easyLanguage\EasyLanguage\Parser_Base;
use easyLanguage\EasyLanguage\Post_Object;

/**
 * Handler for parsing Bricks content.
 */
class Bricks extends Parser_Base implements Parser {
	/**
	 * Internal name of the parser.
	 *
	 * @var string
	 */
	protected string $name = 'Bricks';

	/**
	 * Instance of this object.
	 *
	 * @var ?Bricks
	 */
	private static ?Bricks $instance = null;

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
	public static function get_instance(): Bricks {
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
			'text-basic' => array(
				'text',
			),
			'text'       => array(
				'text',
			),
		);

		/**
		 * Filter the possible Bricks widgets.
		 *
		 * @since 3.1.0 Available since 3.1.0.
		 *
		 * @param array<string,mixed> $widgets List of widgets.
		 */
		return apply_filters( 'easy_language_bricks_text_widgets', $widgets );
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
			'text-basic' => false,
			'text'       => true,
		);

		/**
		 * Filter the possible Bricks widgets with HTML support.
		 *
		 * @since 3.1.0 Available since 3.1.0.
		 *
		 * @param array $html_support_widgets List of widgets with HTML support.
		 */
		$html_widgets = apply_filters( 'easy_language_bricks_html_widgets', $html_support_widgets );

		return isset( $html_widgets[ $widget_name ] );
	}

	/**
	 * Return parsed texts.
	 *
	 * Get the Bricks-content and parse its widgets to get the content of flow-text-widgets.
	 *
	 * @return array<int,mixed>
	 */
	public function get_parsed_texts(): array {
		// do nothing if Bricky is not active.
		if ( false === $this->is_bricks_active() ) {
			return array();
		}

		// list of resulting texts.
		$resulting_texts = array();

		// get editor contents and loop through its array.
		$data = get_post_meta( $this->get_object_id(), BRICKS_DB_PAGE_CONTENT, true );
		if ( is_array( $data ) && ! empty( $data ) ) {
			// get the texts.
			$resulting_texts = $this->get_widgets( $data, $resulting_texts );
		}

		// return the resulting list.
		return $resulting_texts;
	}

	/**
	 * Replace the single original text with simplifications.
	 *
	 * We replace the text complete 1:1.
	 *
	 * @param string $original_complete Complete original content.
	 * @param string $simplified_part The simplified content.
	 * @return string
	 */
	public function get_text_with_simplifications( string $original_complete, string $simplified_part ): string {
		// do nothing if Bricks is not active.
		if ( false === $this->is_bricks_active() ) {
			return $original_complete;
		}

		// get editor contents and loop through its array.
		$data = get_post_meta( $this->get_object_id(), BRICKS_DB_PAGE_CONTENT, true );
		if ( is_array( $data ) && ! empty( $data ) ) {
			// replace the texts.
			$text = $this->replace_content_in_widgets( $data, $simplified_part );

			// save the data for Bricks.
			update_post_meta( $this->get_object_id(), BRICKS_DB_PAGE_CONTENT, $text );
		}

		// return the string for post_content.
		return str_replace( $this->get_text(), $simplified_part, $original_complete );
	}

	/**
	 * Return whether Bricks is active.
	 *
	 * @return bool
	 */
	private function is_bricks_active(): bool {
		return \easyLanguage\PageBuilder\Bricks::get_instance()->is_active();
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		return defined( 'BRICKS_DB_PAGE_CONTENT' ) && ! empty( get_post_meta( $post_object->get_id(), BRICKS_DB_PAGE_CONTENT, true ) );
	}

	/**
	 * Return whether this parser is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->is_bricks_active();
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
	 * @param array<string,mixed> $container The container to parse.
	 * @param array<int,mixed>    $resulting_texts The resulting texts.
	 * @return array<int,mixed>
	 */
	private function get_widgets( array $container, array $resulting_texts ): array {
		// get the list of flow text widgets.
		$flow_text_widgets = $this->get_flow_text_widgets();

		foreach ( $container as $section ) {
			// bail if the section is not an array.
			if ( ! is_array( $section ) ) {
				continue;
			}

			// if the section contains settings, get them.
			if ( isset( $section['settings'] ) && ! empty( $flow_text_widgets[ $section['name'] ] ) ) {
				foreach ( $flow_text_widgets[ $section['name'] ] as $entry_name ) {
					if ( empty( $section['settings'][ $entry_name ] ) ) {
						continue;
					}
					$resulting_texts[] = array(
						'text' => $section['settings'][ $entry_name ],
						'html' => $this->is_flow_text_widget_html( $section['name'] ),
					);
				}
			}
			// loop through the deeper arrays.
			$resulting_texts = $this->get_widgets( $section['children'], $resulting_texts );
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
			// bail if the section is not an array.
			if ( ! is_array( $section ) ) {
				continue;
			}

			// if the section contains settings, get them.
			if ( isset( $section['settings'] ) && ! empty( $flow_text_widgets[ $section['name'] ] ) ) {
				foreach ( $flow_text_widgets[ $section['name'] ] as $entry_name ) {
					if ( empty( $section['settings'][ $entry_name ] ) ) {
						continue;
					}
					$container[ $index ]['settings'][ $entry_name ] = $simplified_part;
				}
			}

			// loop through the deeper arrays.
			$container[ $index ]['children'] = $this->replace_content_in_widgets( $section['children'], $simplified_part );
		}

		// return the resulting container.
		return $container;
	}

	/**
	 * Return the edit link for a Bricks-object.
	 *
	 * @return string
	 */
	public function get_edit_link(): string {
		// bail if Bricks is not active.
		if ( ! \easyLanguage\PageBuilder\Bricks::get_instance()->is_active() ) {
			return parent::get_edit_link();
		}

		// return the edit link to open the requested object ID in Bricks.
		return add_query_arg(
			array(
				'bricks' => 'run',
			),
			get_permalink( $this->get_object_id() )
		);
	}
}
