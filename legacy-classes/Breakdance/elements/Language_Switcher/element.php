<?php
/**
 * File for the language switcher element in Breakdance and Oxygen 6.
 *
 * Breakdance loads this file itself from our registered element location (see
 * \easyLanguage\PageBuilder\Breakdance\Switcher_Element::register_save_location()). The class must use a namespace
 * with one level ("Namespace\Element") as Breakdance uses the class name as slug of the element.
 *
 * The methods are named like the methods of Breakdance elements.
 *
 * @package easy-language
 */

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- method names are defined by Breakdance.

namespace EasyLanguageElements;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\PageBuilder\Breakdance\Switcher_Element;

/**
 * Object for the language switcher element in Breakdance and Oxygen 6.
 *
 * The definition and output are provided by Switcher_Element.
 */
class Language_Switcher extends \Breakdance\Elements\Element {
	/**
	 * Return the icon of this element (SVG markup of the Easy Language logo).
	 *
	 * @return string
	 */
	public static function uiIcon() {
		return Switcher_Element::get_instance()->get_icon();
	}

	/**
	 * Return the page builders where this element is available.
	 *
	 * @return array<int,string>
	 */
	public static function availableIn() {
		return array( 'breakdance', 'oxygen' );
	}

	/**
	 * Return the HTML tag of this element.
	 *
	 * @return string
	 */
	public static function tag() {
		return 'div';
	}

	/**
	 * Return the name of this element.
	 *
	 * @return string
	 */
	public static function name() {
		return __( 'Language Switcher', 'easy-language' );
	}

	/**
	 * Return the CSS class of this element.
	 *
	 * @return string
	 */
	public static function className() {
		return 'easy-language-switcher-element';
	}

	/**
	 * Return the category of this element.
	 *
	 * @return string
	 */
	public static function category() {
		return 'site';
	}

	/**
	 * Return the slug of this element (its class name).
	 *
	 * @return string
	 */
	public static function slug() {
		return __CLASS__;
	}

	/**
	 * Return the template: the output is generated via server side rendering.
	 *
	 * @return string
	 */
	public static function template() {
		return '%%SSR%%';
	}

	/**
	 * Return the default properties of a new element.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaultProperties() {
		return Switcher_Element::get_instance()->get_default_properties();
	}

	/**
	 * Return the controls of the content tab.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function contentControls() {
		return Switcher_Element::get_instance()->get_content_controls();
	}

	/**
	 * Return the controls of the design tab.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function designControls() {
		return Switcher_Element::get_instance()->get_design_controls();
	}

	/**
	 * Return the nesting rule: this element has no children.
	 *
	 * @return array<string,string>
	 */
	public static function nestingRule() {
		return array( 'type' => 'final' );
	}

	/**
	 * Return the order of this element in its category.
	 *
	 * @return int
	 */
	public static function order() {
		return 100;
	}

	/**
	 * Return the property paths which lead to a new server side rendering in the editor.
	 *
	 * @return array<int,string>
	 */
	public static function propertyPathsToSsrElementWhenValueChanges() {
		return Switcher_Element::get_instance()->get_property_paths();
	}

	/**
	 * Return the output of this element (server side rendering) in frontend and editor.
	 *
	 * @param mixed    $propertiesData The properties of the element.
	 * @param mixed    $parentPropertiesData The properties of the parent element.
	 * @param bool     $isBuilder True if the element is rendered in the editor.
	 * @param int|null $repeaterItemNodeId The ID of the repeater item.
	 *
	 * @return string
	 */
	public static function ssr( $propertiesData, $parentPropertiesData = array(), $isBuilder = false, $repeaterItemNodeId = null ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return Switcher_Element::get_instance()->render( $propertiesData, (bool) $isBuilder ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
	}
}
