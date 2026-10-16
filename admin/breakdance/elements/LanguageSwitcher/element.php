<?php
/**
 * File for the language switcher element for Breakdance.
 *
 * Breakdance loads this file itself via the save location registered in easyLanguage\PageBuilder\Breakdance.
 * Each PHP-file in this directory, except ssr.php, is loaded by Breakdance.
 *
 * @package easy-language
 */

namespace EasyLanguageBreakdance;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use function Breakdance\Elements\c;
use function Breakdance\Elements\PresetSections\getPresetSection;

/**
 * Object for the language switcher element for Breakdance.
 *
 * phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
 * phpcs:disable Squiz.Commenting.FunctionComment.Missing
 */
class LanguageSwitcher extends \Breakdance\Elements\Element {
	public static function uiIcon() {
		return '<svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 3.8 5.5 3.8 9s-1.3 6.5-3.8 9c-2.5-2.5-3.8-5.5-3.8-9S9.5 5.5 12 3z"/></svg>';
	}

	public static function tag() {
		return 'div';
	}

	public static function tagOptions() {
		return array();
	}

	public static function tagControlPath() {
		return false;
	}

	public static function name() {
		return \easyLanguage\PageBuilder\Switcher_Helper::get_title();
	}

	public static function className() {
		return 'easy-language-switcher-breakdance';
	}

	public static function category() {
		return 'site';
	}

	public static function badge() {
		return false;
	}

	public static function slug() {
		return __CLASS__;
	}

	public static function template() {
		return (string) file_get_contents( __DIR__ . '/html.twig' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	public static function defaultCss() {
		return (string) file_get_contents( __DIR__ . '/default.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	public static function defaultProperties() {
		return array(
			'content' => array(
				'settings' => array(
					'show_icons'           => false,
					'hide_actual_language' => false,
				),
			),
		);
	}

	public static function defaultChildren() {
		return false;
	}

	public static function cssTemplate() {
		return (string) file_get_contents( __DIR__ . '/css.twig' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	public static function designControls() {
		$labels = \easyLanguage\PageBuilder\Switcher_Helper::get_labels();
		return array(
			c(
				'links',
				$labels['spacing'],
				array(
					getPresetSection(
						'EssentialElements\\spacing_padding_all',
						$labels['margin'],
						'margin',
						array( 'type' => 'popout' )
					),
				),
				array( 'type' => 'section' ),
				false,
				false,
				array()
			),
		);
	}

	public static function contentControls() {
		$labels = \easyLanguage\PageBuilder\Switcher_Helper::get_labels();
		return array(
			c(
				'settings',
				$labels['settings'],
				array(
					c(
						'show_icons',
						$labels['show_icons'],
						array(),
						array(
							'type'   => 'toggle',
							'layout' => 'inline',
						),
						false,
						false,
						array()
					),
					c(
						'hide_actual_language',
						$labels['hide_actual_language'],
						array(),
						array(
							'type'   => 'toggle',
							'layout' => 'inline',
						),
						false,
						false,
						array()
					),
				),
				array(
					'type'   => 'section',
					'layout' => 'vertical',
				),
				false,
				false,
				array()
			),
		);
	}

	public static function settingsControls() {
		return array();
	}

	public static function dependencies() {
		return false;
	}

	public static function settings() {
		return false;
	}

	public static function addPanelRules() {
		return false;
	}

	public static function actions() {
		return false;
	}

	public static function nestingRule() {
		return array( 'type' => 'final' );
	}

	public static function spacingBars() {
		return false;
	}

	public static function attributes() {
		return false;
	}

	public static function experimental() {
		return false;
	}

	public static function order() {
		return 100;
	}

	public static function dynamicPropertyPaths() {
		return false;
	}

	public static function additionalClasses() {
		return false;
	}

	public static function projectManagement() {
		return false;
	}

	public static function propertyPathsToWhitelistInFlatProps() {
		return false;
	}

	public static function propertyPathsToSsrElementWhenValueChanges() {
		return array( 'content.settings' );
	}
}
