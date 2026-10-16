<?php
/**
 * File for the language switcher element for Oxygen (classic).
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder\Oxygen;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\PageBuilder\Switcher_Helper;
use easyLanguage\Plugin\Helper;

/**
 * Object for the language switcher element for Oxygen.
 *
 * This file is only loaded if the class OxyEl of Oxygen exists.
 *
 * @noinspection PhpUnused
 */
class Switcher_Element extends \OxyEl {
	/**
	 * Return the name of this element.
	 *
	 * @return string
	 */
	public function name() {
		return Switcher_Helper::get_title();
	}

	/**
	 * Return the slug of this element. The element tag will be "oxy-easy-language-switcher".
	 *
	 * @return string
	 */
	public function slug() {
		return 'easy-language-switcher';
	}

	/**
	 * Return the URL of the icon of this element.
	 *
	 * @return string
	 */
	public function icon() {
		return Helper::get_plugin_url() . 'gfx/easy-language-icon.png';
	}

	/**
	 * Return the place of the button in the "Add"-panel of Oxygen.
	 *
	 * @return string
	 */
	public function button_place() {
		return 'wordpress'; // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText
	}

	/**
	 * Remove the "Apply Params"-button, as the element is rebuild on each change.
	 *
	 * @return void
	 */
	public function afterInit() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->removeApplyParamsButton();
	}

	/**
	 * Add the controls of this element.
	 *
	 * @return void
	 */
	public function controls() {
		$labels = Switcher_Helper::get_labels();

		// add the settings.
		$this->addOptionControl(
			array(
				'type' => 'checkbox',
				'name' => $labels['show_icons'],
				'slug' => 'show_icons',
			)
		)->setValue( 'false' )->rebuildElementOnChange();
		$this->addOptionControl(
			array(
				'type' => 'checkbox',
				'name' => $labels['hide_actual_language'],
				'slug' => 'hide_actual_language',
			)
		)->setValue( 'false' )->rebuildElementOnChange();

		// add the spacing for the links.
		$spacing_section = $this->addControlSection( 'easy_language_spacing', $labels['spacing'], 'assets/icon.png', $this );
		$spacing_section->addPreset( 'padding', 'easy_language_link_margin', $labels['margin'], 'a' )->whiteList();
	}

	/**
	 * Output the element.
	 *
	 * @param array<string,mixed> $options The options of this element.
	 * @param array<string,mixed> $defaults The defaults of this element.
	 * @param mixed               $content The content of this element.
	 *
	 * @return void
	 */
	public function render( $options, $defaults, $content ) {
		// get the settings (Oxygen saves checkboxes as string "true" or "false").
		$show_icons           = isset( $options['show_icons'] ) && 'true' === $options['show_icons'];
		$hide_actual_language = isset( $options['hide_actual_language'] ) && 'true' === $options['hide_actual_language'];

		// output the switcher.
		echo Switcher_Helper::get_output( $show_icons, $hide_actual_language ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
