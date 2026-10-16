<?php
/**
 * File for the language switcher element for Zion Builder.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder\ZionBuilder;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\PageBuilder\Switcher_Helper;

/**
 * Object for the language switcher element for Zion Builder.
 *
 * This file is only loaded if Zion Builder registers its elements. The editor renders this element via PHP.
 *
 * @noinspection PhpUnused
 */
class Switcher_Element extends \ZionBuilder\Elements\Element {
	/**
	 * Return the unique type of this element.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'easy_language_switcher';
	}

	/**
	 * Return the name of this element.
	 *
	 * @return string
	 */
	public function get_name() {
		return Switcher_Helper::get_title();
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
	 * Return the keywords of this element.
	 *
	 * @return array<int,string>
	 */
	public function get_keywords() {
		return array( 'language', 'switcher', 'easy language', 'menu' );
	}

	/**
	 * Return the icon of this element.
	 *
	 * @return string
	 */
	public function get_element_icon() {
		return 'element-wp';
	}

	/**
	 * Add the options of this element.
	 *
	 * @param \ZionBuilder\Options\Options $options The options instance.
	 *
	 * @return void
	 */
	public function options( $options ) {
		$labels = Switcher_Helper::get_labels();

		$options->add_option(
			'show_icons',
			array(
				'type'    => 'checkbox_switch',
				'default' => false,
				'layout'  => 'inline',
				'title'   => $labels['show_icons'],
			)
		);
		$options->add_option(
			'hide_actual_language',
			array(
				'type'    => 'checkbox_switch',
				'default' => false,
				'layout'  => 'inline',
				'title'   => $labels['hide_actual_language'],
			)
		);

		// the spacing for the links is saved in the style options of the links.
		$options->add_group(
			'margin',
			array(
				'type'                    => 'dimensions',
				'title'                   => $labels['margin'],
				'min'                     => 0,
				'max'                     => 99999,
				'sync'                    => '_styles.links.styles.%%RESPONSIVE_DEVICE%%.default',
				'show_responsive_buttons' => true,
				'dimensions'              => array(
					array(
						'name' => 'top',
						'icon' => 'padding-top',
						'id'   => 'padding-top',
					),
					array(
						'name' => 'right',
						'icon' => 'padding-right',
						'id'   => 'padding-right',
					),
					array(
						'name' => 'bottom',
						'icon' => 'padding-bottom',
						'id'   => 'padding-bottom',
					),
					array(
						'name' => 'left',
						'icon' => 'padding-left',
						'id'   => 'padding-left',
					),
				),
			)
		);
	}

	/**
	 * Register the style options for the links.
	 *
	 * @return void
	 */
	public function on_register_styles() {
		$this->register_style_options_element(
			'links',
			array(
				'title'    => Switcher_Helper::get_labels()['spacing'],
				'selector' => '{{ELEMENT}} a',
			)
		);
	}

	/**
	 * Output the element.
	 *
	 * @param \ZionBuilder\Options\Options $options The options of this element.
	 *
	 * @return void
	 */
	public function render( $options ) {
		// get the settings.
		$show_icons           = (bool) $options->get_value( 'show_icons', false );
		$hide_actual_language = (bool) $options->get_value( 'hide_actual_language', false );

		// output the switcher.
		echo Switcher_Helper::get_output( $show_icons, $hide_actual_language ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
