<?php
/**
 * File to handle support for the page builder WPBakery Page Builder (incl. the Salient WPBakery Page Builder).
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\EasyLanguage\Switcher;
use easyLanguage\Plugin\Helper;

/**
 * Object to handle support for the page builder WPBakery Page Builder.
 *
 * Adds the element "Language Switcher" to WPBakery Page Builder and to its fork "Salient WPBakery Page Builder"
 * (bundled with the theme Salient), which share the same API. Elements of WPBakery are shortcodes, which are
 * mapped via vc_map() / vc_lean_map(). The settings of the element are generated from the field definitions of
 * the switcher.
 */
class WpBakery extends PageBuilder_Base {
	/**
	 * The shortcode (the "base" in WPBakery) of our element.
	 */
	public const SHORTCODE = 'easy_language_wpbakery_switcher';

	/**
	 * Instance of this object.
	 *
	 * @var ?WpBakery
	 */
	private static ?WpBakery $instance = null;

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
	public static function get_instance(): WpBakery {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// WPBakery fires "vc_before_init" during "init" (priority 11), its elements are mapped there.
		add_action( 'vc_before_init', array( $this, 'register_element' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_icon_style' ) );
	}

	/**
	 * Return the URL of the icon of our element.
	 *
	 * @return string
	 */
	private function get_icon_url(): string {
		return Helper::get_plugin_url() . 'gfx/easy-language-icon.svg';
	}

	/**
	 * Add the style for the icon of our element in the panel "Add element" of WPBakery.
	 *
	 * WPBakery supports URLs as icons and prints their styles itself, but its selectors do not match the panel
	 * "Add element" of the actual versions (in the backend and in the frontend editor, both on the edit page of
	 * posts). So we add a rule scoped to our element.
	 *
	 * @param string $hook The actual admin page.
	 *
	 * @return void
	 */
	public function enqueue_icon_style( string $hook ): void {
		// bail if this is not an edit page or WPBakery is not active.
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $this->is_active() ) {
			return;
		}

		// add the style.
		wp_enqueue_style( 'easy-language-builder-icon' );
		wp_add_inline_style( 'easy-language-builder-icon', '[data-element="' . self::SHORTCODE . '"] .vc_element-icon { background-image: url(' . esc_url( $this->get_icon_url() ) . '); background-position: center; background-size: contain; }' );
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * WPBakery is active as plugin "WPBakery Page Builder", as plugin "Salient WPBakery Page Builder" or bundled in
	 * a theme (then only its constant is available).
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'js_composer/js_composer.php' ) || Helper::is_plugin_active( 'js_composer_salient/js_composer.php' ) || defined( 'WPB_VC_VERSION' );
	}

	/**
	 * Register our element in WPBakery.
	 *
	 * We register the shortcode ourselves (WPBakery does not overwrite existing shortcodes) and map it lazily, so
	 * the settings are only generated if WPBakery needs them (e.g. in its editors).
	 *
	 * @return void
	 */
	public function register_element(): void {
		// bail if WPBakery is not active.
		if ( ! $this->is_active() ) {
			return;
		}

		// register the shortcode for the output.
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );

		// map the element.
		if ( function_exists( 'vc_lean_map' ) ) {
			vc_lean_map( self::SHORTCODE, array( $this, 'get_element_mapping' ) );
		} elseif ( function_exists( 'vc_map' ) ) {
			vc_map( $this->get_element_mapping() );
		}

		// add the AJAX search and the labels for the fields to choose pages and terms.
		foreach ( Switcher::get_instance()->get_widget_fields() as $name => $field ) {
			if ( 'objects' !== $field['type'] ) {
				continue;
			}
			add_filter( 'vc_autocomplete_' . self::SHORTCODE . '_' . $name . '_callback', array( $this, 'get_object_suggestions' ) );
			add_filter( 'vc_autocomplete_' . self::SHORTCODE . '_' . $name . '_render', array( $this, 'get_object_label' ) );
		}
	}

	/**
	 * Return the mapping of our element for vc_map().
	 *
	 * The params are generated from the field definitions of the switcher. The sections are used as groups (tabs),
	 * the main settings are shown in the default tab "General".
	 *
	 * @return array<string,mixed>
	 */
	public function get_element_mapping(): array {
		$switcher = Switcher::get_instance();
		$fields   = $switcher->get_widget_fields();
		$sections = $switcher->get_widget_sections();

		// get the design fields for the hints of size fields.
		$design_fields = $switcher->get_toggle_design_fields();

		// create the params.
		$params = array();
		foreach ( $fields as $name => $field ) {
			$param = array(
				'param_name' => $name,
				'heading'    => $field['label'] ?? $name,
			);

			// use the sections as groups (tabs), except for the main settings.
			$section = isset( $field['section'] ) ? (string) $field['section'] : 'settings';
			if ( 'settings' !== $section ) {
				$param['group'] = $sections[ $section ] ?? $section;
			}

			// collect the descriptions.
			$descriptions = array();
			if ( ! empty( $field['description'] ) ) {
				$descriptions[] = (string) $field['description'];
			}

			// add the field type specific settings.
			switch ( $field['type'] ) {
				case 'select':
					$param['type']        = 'dropdown';
					$param['value']       = $this->get_dropdown_values( is_array( $field['options'] ?? null ) ? $field['options'] : array() );
					$param['std']         = is_scalar( $field['default'] ) ? (string) $field['default'] : '';
					$param['admin_label'] = 'settings' === $section;
					break;

				case 'switch':
					$param['type']  = 'checkbox';
					$param['value'] = array( __( 'Yes', 'easy-language' ) => 'yes' );
					if ( ! empty( $field['default'] ) ) {
						$param['std'] = 'yes';
					}
					break;

				case 'color':
					$param['type'] = 'colorpicker';
					break;

				case 'objects':
					// use the autocomplete field with AJAX search (see get_object_suggestions()).
					$param['type']     = 'autocomplete';
					$param['settings'] = array(
						'multiple'       => true,
						'sortable'       => true,
						'unique_values'  => true,
						'min_length'     => 1,
						'display_inline' => true,
					);
					break;

				case 'size':
				default:
					$param['type'] = 'textfield';
					$param['std']  = is_scalar( $field['default'] ) ? (string) $field['default'] : '';
					if ( 'size' === $field['type'] ) {
						$descriptions[] = (string) ( $design_fields[ $name ]['placeholder'] ?? __( 'e.g. 24px', 'easy-language' ) );
					}
					break;
			}

			// add the dependency (WPBakery supports one controlling field per param, further conditions are described).
			$conditions = isset( $field['condition'] ) && is_array( $field['condition'] ) ? $field['condition'] : array();
			foreach ( $conditions as $controller => $allowed_values ) {
				// bail if the controlling field does not exist.
				if ( ! isset( $fields[ $controller ] ) ) {
					continue;
				}

				// use the first condition as dependency.
				if ( ! isset( $param['dependency'] ) ) {
					$dependency = $this->get_dependency( (string) $controller, (array) $allowed_values, $fields[ $controller ] );
					if ( ! empty( $dependency ) ) {
						$param['dependency'] = $dependency;
					}
					continue;
				}

				// describe the other conditions.
				$descriptions[] = $this->get_condition_description( (array) $allowed_values, $fields[ $controller ], (string) $controller );
			}

			// add the description.
			$descriptions = array_filter( $descriptions );
			if ( ! empty( $descriptions ) ) {
				$param['description'] = implode( ' ', $descriptions );
			}

			// add the param.
			$params[] = $param;
		}

		// return the resulting mapping.
		return array(
			'name'     => __( 'Language Switcher', 'easy-language' ),
			'base'     => self::SHORTCODE,
			'category' => __( 'Easy Language', 'easy-language' ),
			'icon'     => $this->get_icon_url(),
			'params'   => $params,
		);
	}

	/**
	 * Return the values of a dropdown in the format of WPBakery (label => value).
	 *
	 * @param array<int|string,mixed> $options The options as value => label.
	 *
	 * @return array<string,string>
	 */
	private function get_dropdown_values( array $options ): array {
		$values = array();
		foreach ( $options as $value => $label ) {
			$label = (string) $label;

			// prevent overwriting of options with the same label.
			if ( isset( $values[ $label ] ) ) {
				$label .= ' (' . $value . ')';
			}
			$values[ $label ] = (string) $value;
		}
		return $values;
	}

	/**
	 * Return the dependency for a param in the format of WPBakery.
	 *
	 * @param string              $controller The name of the controlling field.
	 * @param array<int,mixed>    $allowed_values The allowed values of the controlling field.
	 * @param array<string,mixed> $controller_field The definition of the controlling field.
	 *
	 * @return array<string,mixed> The dependency or an empty array if the condition does not restrict anything.
	 */
	private function get_dependency( string $controller, array $allowed_values, array $controller_field ): array {
		// for switches (checkboxes) WPBakery checks for the value "yes" or for an empty value.
		if ( 'switch' === $controller_field['type'] ) {
			// if both values are allowed, the condition does not restrict anything.
			if ( in_array( true, $allowed_values, true ) && in_array( false, $allowed_values, true ) ) {
				return array();
			}
			if ( in_array( true, $allowed_values, true ) ) {
				return array(
					'element' => $controller,
					'value'   => array( 'yes' ),
				);
			}
			return array(
				'element'  => $controller,
				'is_empty' => true,
			);
		}

		// for all other fields use the list of allowed values.
		return array(
			'element' => $controller,
			'value'   => array_values( array_map( 'strval', array_filter( $allowed_values, 'is_scalar' ) ) ),
		);
	}

	/**
	 * Return the description for a condition which could not be used as dependency.
	 *
	 * @param array<int,mixed>    $allowed_values The allowed values of the controlling field.
	 * @param array<string,mixed> $controller_field The definition of the controlling field.
	 * @param string              $controller The name of the controlling field.
	 *
	 * @return string
	 */
	private function get_condition_description( array $allowed_values, array $controller_field, string $controller ): string {
		// get the labels of the allowed values.
		$labels = array();
		foreach ( $allowed_values as $allowed_value ) {
			if ( is_bool( $allowed_value ) ) {
				$labels[] = $allowed_value ? __( 'Yes', 'easy-language' ) : __( 'No', 'easy-language' );
				continue;
			}
			$labels[] = (string) ( $controller_field['options'][ $allowed_value ] ?? $allowed_value );
		}

		/* translators: %1$s: label of a setting, %2$s: list of values. */
		return sprintf( __( 'Only used if "%1$s" is set to "%2$s".', 'easy-language' ), $controller_field['label'] ?? $controller, implode( '", "', $labels ) );
	}

	/**
	 * Return the suggestions for the AJAX search of the autocomplete fields for pages and terms.
	 *
	 * WPBakery checks the nonce and the capabilities of the user before it calls this filter.
	 *
	 * @param mixed $query The search string.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_object_suggestions( mixed $query ): array {
		return Switcher::get_instance()->search_objects( is_string( $query ) ? $query : '' );
	}

	/**
	 * Return the label of a chosen page or term for the autocomplete fields.
	 *
	 * WPBakery outputs the value and label without escaping, so we escape them here.
	 *
	 * @param mixed $value The entry with the keys "value" and "label".
	 *
	 * @return array<string,string>|false
	 */
	public function get_object_label( mixed $value ): array|false {
		// bail if the value is invalid.
		if ( ! is_array( $value ) || ! isset( $value['value'] ) || ! is_string( $value['value'] ) || ! preg_match( '/^(post|term):\d+$/', $value['value'] ) ) {
			return false;
		}

		// get the label (use the value if the object does not exist anymore, so it could be removed by the user).
		$labels = Switcher::get_instance()->get_object_labels( array( $value['value'] ) );
		$label  = ! empty( $labels[0]['label'] ) ? $labels[0]['label'] : $value['value'];

		// return the escaped entry.
		return array(
			'value' => esc_attr( $value['value'] ),
			'label' => esc_attr( $label ),
		);
	}

	/**
	 * Return the output of our element.
	 *
	 * @param array<string,mixed>|string $attributes The attributes of the shortcode.
	 *
	 * @return string
	 */
	public function render_shortcode( array|string $attributes ): string {
		// convert the attributes.
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}

		// collect the values of our fields (WPBakery encodes some characters in attributes).
		$values = array();
		foreach ( array_keys( Switcher::get_instance()->get_widget_fields() ) as $name ) {
			if ( ! isset( $attributes[ $name ] ) ) {
				continue;
			}
			$values[ $name ] = is_string( $attributes[ $name ] ) ? str_replace( array( '`{`', '`}`', '``' ), array( '[', ']', '"' ), $attributes[ $name ] ) : $attributes[ $name ];
		}

		// return the output.
		return Switcher::get_instance()->render_widget( $values, 'wpbakery', $this->is_preview() );
	}

	/**
	 * Return whether the element is rendered in the frontend editor of WPBakery.
	 *
	 * The frontend editor loads the page in an iframe with the parameter "vc_editable" and renders changed elements
	 * via AJAX requests to this page. WPBakery checks the nonce and the capabilities for this mode.
	 *
	 * @return bool
	 */
	private function is_preview(): bool {
		return function_exists( 'vc_is_page_editable' ) && vc_is_page_editable();
	}
}
