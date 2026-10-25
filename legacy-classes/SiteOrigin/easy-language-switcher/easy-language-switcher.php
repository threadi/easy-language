<?php
/**
 * Widget Name: Language Switcher
 * Description: Language switcher of Easy Language to switch between the original and simplified versions of contents.
 * Author: laOlaWeb
 * Author URI: https://laolaweb.com
 * Keywords: easy language, language, switcher, menu
 *
 * File for the language switcher widget in the SiteOrigin Widgets Bundle.
 *
 * The Widgets Bundle loads this file itself from the widget folder registered by
 * \easyLanguage\PageBuilder\SiteOrigin::add_widget_folder() (the folder and file must be named like the widget ID,
 * the header above contains the data for the widget list of the Widgets Bundle).
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\PageBuilder\SiteOrigin;

/**
 * Object for the language switcher widget in the SiteOrigin Widgets Bundle.
 *
 * The form fields and the output are provided by \easyLanguage\PageBuilder\SiteOrigin.
 *
 * @noinspection PhpUnused
 */
class Easy_Language_SiteOrigin_Switcher_Widget extends SiteOrigin_Widget {
	/**
	 * The output of the widget for the actual rendering.
	 *
	 * @var string
	 */
	private string $html = '';

	/**
	 * Constructor for this widget.
	 */
	public function __construct() {
		parent::__construct(
			SiteOrigin::WIDGET_ID,
			__( 'Language Switcher', 'easy-language' ),
			array(
				'description' => __( 'Easy Language', 'easy-language' ),
				'classname'   => 'easy-language-switcher-widget',
			),
			array(),
			false,
			plugin_dir_path( __FILE__ )
		);
	}

	/**
	 * Return the form fields of this widget.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_widget_form() {
		return SiteOrigin::get_instance()->get_widget_form();
	}

	/**
	 * Move flat values (e.g. from the classic widget) into the sections of this widget.
	 *
	 * @param array<string,mixed> $instance The widget instance.
	 *
	 * @return array<string,mixed>
	 */
	public function modify_instance( $instance ) {
		return SiteOrigin::get_instance()->move_values_to_sections( (array) $instance );
	}

	/**
	 * Return no LESS style as the styles of the switcher are loaded by Easy Language.
	 *
	 * @param array<string,mixed> $instance The widget instance.
	 *
	 * @return string
	 */
	public function get_style_name( $instance ) {
		return '';
	}

	/**
	 * Output the widget.
	 *
	 * Outputs nothing (not even the wrapper of the widget) if the switcher should not be visible.
	 *
	 * @param array<string,mixed> $args The arguments of the widget area.
	 * @param array<string,mixed> $instance The widget instance.
	 *
	 * @return void
	 */
	public function widget( $args, $instance ) {
		// get the output of the switcher.
		$this->html = SiteOrigin::get_instance()->render( (array) $instance );

		// bail if the switcher should not be visible.
		if ( '' === $this->html ) {
			return;
		}

		// output the widget via the Widgets Bundle (which uses get_html_content()).
		parent::widget( $args, $instance );
		$this->html = '';
	}

	/**
	 * Return the HTML content of the widget (used by the Widgets Bundle instead of a template file).
	 *
	 * @param array<string,mixed> $instance The widget instance.
	 * @param array<string,mixed> $args The arguments of the widget area.
	 * @param array<string,mixed> $template_vars The template variables.
	 * @param string              $css_name The name of the CSS of this instance.
	 *
	 * @return string
	 */
	public function get_html_content( $instance, $args, $template_vars, $css_name ) {
		return $this->html;
	}
}

// register the widget in the Widgets Bundle.
siteorigin_widget_register( SiteOrigin::WIDGET_ID, __FILE__, 'Easy_Language_SiteOrigin_Switcher_Widget' );
