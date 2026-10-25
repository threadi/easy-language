<?php
/**
 * File for the element "Language Switcher" in the Avia Layout Builder of the theme Enfold.
 *
 * The Avia Layout Builder loads all PHP files in its shortcode folders and instantiates every global class which
 * extends aviaShortcodeTemplate. This folder is added via the filter "avia_load_shortcodes" in
 * \easyLanguage\PageBuilder\Avia.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

// bail if the Avia Layout Builder is not loaded.
if ( ! class_exists( 'aviaShortcodeTemplate' ) || class_exists( 'Easy_Language_Avia_Switcher', false ) ) {
	return;
}

/**
 * Object for the element "Language Switcher" in the Avia Layout Builder.
 *
 * @noinspection PhpUnused
 */
class Easy_Language_Avia_Switcher extends aviaShortcodeTemplate {
	/**
	 * Create the config array for the element.
	 *
	 * @return void
	 */
	protected function shortcode_insert_button() {
		$this->config['version']           = '1.0';
		$this->config['self_closing']      = 'yes';
		$this->config['name']              = __( 'Language Switcher', 'easy-language' );
		$this->config['tab']               = __( 'Plugin Additions', 'avia_framework' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
		$this->config['icon']              = \easyLanguage\PageBuilder\Avia::get_instance()->get_icon_url();
		$this->config['order']             = 10;
		$this->config['target']            = 'avia-target-insert';
		$this->config['shortcode']         = \easyLanguage\PageBuilder\Avia::SHORTCODE;
		$this->config['tooltip']           = __( 'Show a switcher between the language versions of the actual page.', 'easy-language' );
		$this->config['tinyMCE']           = array( 'disable' => true );
		$this->config['preview']           = 1;
		$this->config['disabling_allowed'] = false;
		$this->config['id_name']           = 'id';
		$this->config['id_show']           = 'yes';
	}

	/**
	 * Define the settings of the element (popup elements).
	 *
	 * The fields are generated from the central field definitions of the switcher. We add the default tab with
	 * the advanced settings of Avia (responsive visibility, custom CSS class and ID).
	 *
	 * @return void
	 */
	protected function popup_elements() {
		$this->elements = array(
			array(
				'type'          => 'tab_container',
				'nodescription' => true,
			),
		);

		// add the tabs with the fields of the switcher.
		foreach ( \easyLanguage\PageBuilder\Avia::get_instance()->get_popup_elements() as $element ) {
			$this->elements[] = $element;
		}

		// add the advanced settings of Avia.
		$this->elements[] = array(
			'type'          => 'tab',
			'name'          => __( 'Advanced', 'avia_framework' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
			'nodescription' => true,
		);
		$this->elements[] = array(
			'type'          => 'toggle_container',
			'nodescription' => true,
		);
		$this->elements[] = array(
			'type'        => 'template',
			'template_id' => 'screen_options_toggle',
		);
		$this->elements[] = array(
			'type'        => 'template',
			'template_id' => 'developer_options_toggle',
			'args'        => array( 'sc' => $this ),
		);
		$this->elements[] = array(
			'type'          => 'toggle_container_close',
			'nodescription' => true,
		);
		$this->elements[] = array(
			'type'          => 'tab_close',
			'nodescription' => true,
		);
		$this->elements[] = array(
			'type'          => 'tab_container_close',
			'nodescription' => true,
		);
	}

	/**
	 * Output the settings of the element in the modal popup.
	 *
	 * We add the options for the selection of pages and terms only here, so they are not loaded on every request.
	 *
	 * @param mixed $params The parameter of the builder.
	 *
	 * @return void
	 */
	public function popup_editor( $params ) {
		$this->elements = \easyLanguage\PageBuilder\Avia::get_instance()->add_object_options( $this->elements );
		parent::popup_editor( $params );
	}

	/**
	 * Return the output of the element in frontend and in the preview of the builder.
	 *
	 * @param array<string,mixed>|string $atts The attributes of the shortcode.
	 * @param string                     $content The content of the shortcode (not used).
	 * @param string                     $shortcodename The name of the shortcode.
	 * @param array<string,mixed>|string $meta The meta data of the element from the builder.
	 *
	 * @return string
	 */
	public function shortcode_handler( $atts, $content = '', $shortcodename = '', $meta = '' ) {
		// WordPress uses an empty string if no attributes are set.
		if ( ! is_array( $atts ) ) {
			$atts = array();
		}
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		// get the output of the switcher.
		$switcher = \easyLanguage\PageBuilder\Avia::get_instance()->render( $atts );

		// bail if the switcher should not be visible.
		if ( '' === $switcher ) {
			return '';
		}

		// collect the classes of the wrapper (position in the builder, custom class and responsive visibility).
		$classes = array( 'easy-language-avia-switcher', 'av-' . sanitize_html_class( $shortcodename ) );
		if ( ! empty( $meta['el_class'] ) ) {
			$classes[] = trim( (string) $meta['el_class'] );
		}
		if ( class_exists( 'AviaHelper' ) && method_exists( 'AviaHelper', 'av_mobile_sizes' ) ) {
			$mobile = AviaHelper::av_mobile_sizes( $atts );
			if ( ! empty( $mobile['av_display_classes'] ) ) {
				$classes[] = trim( $mobile['av_display_classes'] );
			}
		}

		// get the custom ID.
		$id = ! empty( $meta['custom_id_val'] ) ? ' id="' . esc_attr( $meta['custom_id_val'] ) . '"' : '';

		// return the output.
		return '<div' . $id . ' class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $switcher . '</div>';
	}
}
