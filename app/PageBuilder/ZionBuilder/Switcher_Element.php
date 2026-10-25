<?php
/**
 * File for the language switcher element for Zion Builder.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder\ZionBuilder;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\Switcher;
use easyLanguage\Plugin\Helper;
use ZionBuilder\Elements\Element;

/**
 * Object for the language switcher element in Zion Builder.
 *
 * @noinspection PhpUnused
 */
class Switcher_Element extends Element {
	/**
	 * Return the unique type of this element.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'easy-language-switcher';
	}

	/**
	 * Return the name of this element.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Language Switcher', 'easy-language' );
	}

	/**
	 * Return the keywords for the search in the editor.
	 *
	 * @return array<int,string>
	 */
	public function get_keywords() {
		return array( __( 'Easy Language', 'easy-language' ), __( 'Menu', 'easy-language' ), 'language', 'switcher' );
	}

	/**
	 * Return the category of this element.
	 *
	 * @return string
	 */
	public function get_category() {
		return 'content';
	}

	/**
	 * Return no icon to use the image of this element.
	 *
	 * @return string
	 */
	public function get_element_icon() {
		return '';
	}

	/**
	 * Return the image of this element in the list of elements.
	 *
	 * @return string
	 */
	public function get_element_image() {
		return Helper::get_plugin_url() . 'gfx/easy-language-icon.png';
	}

	/**
	 * Return the configuration for the editor: render the element on the server.
	 *
	 * @return array<string,mixed>
	 */
	public function get_config_for_editor() {
		return array(
			'requires_data_for_render' => true,
		);
	}

	/**
	 * Register the options of this element.
	 *
	 * @param \ZionBuilder\Options\Options $options The options object.
	 *
	 * @return void
	 */
	public function options( $options ) {
		foreach ( \easyLanguage\PageBuilder\ZionBuilder::get_instance()->get_element_options() as $section => $group_config ) {
			// bail if this section has no options.
			if ( empty( $group_config['options'] ) ) {
				continue;
			}

			// add the group.
			$group = $options->add_group( 'easy_language_' . $section, $group_config['config'] );

			// add the options of this group.
			foreach ( $group_config['options'] as $name => $option ) {
				$group->add_option( $name, $option );
			}
		}
	}

	/**
	 * Render this element.
	 *
	 * @param \ZionBuilder\Options\Options $options The options object.
	 *
	 * @return void
	 */
	public function render( $options ) {
		// collect the values.
		$values = array();
		foreach ( array_keys( Switcher::get_instance()->get_widget_fields() ) as $name ) {
			$value = $options->get_value( $name );
			if ( is_null( $value ) ) {
				continue;
			}
			$values[ $name ] = $value;
		}

		// output the switcher.
		echo \easyLanguage\PageBuilder\ZionBuilder::get_instance()->render( $values, $this->is_preview() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Return whether this element is rendered in the editor of Zion Builder.
	 *
	 * @return bool
	 */
	private function is_preview(): bool {
		// elements rendered by the editor via REST API are previews.
		if ( $this->is_server_render ) {
			return true;
		}

		// check the preview frame of the editor.
		return class_exists( '\ZionBuilder\Plugin' ) && isset( \ZionBuilder\Plugin::$instance->editor->preview ) && \ZionBuilder\Plugin::$instance->editor->preview->is_preview_mode();
	}
}
