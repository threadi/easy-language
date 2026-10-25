<?php
/**
 * File to handle support for the page builder Themify Builder.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// deny direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\EasyLanguage\Switcher;
use easyLanguage\Plugin\Helper;
use WP_Post;
use WP_Query;

/**
 * Object to handle support for the page builder Themify Builder.
 *
 * Adds the module "Language Switcher" to Themify Builder. The module itself (PHP class and template) is located in
 * legacy-classes/Themify/ as Themify Builder expects a fixed file structure and global class names. The settings of
 * the module are defined in JavaScript by Themify Builder, so we generate them here from the field definitions of
 * the switcher and hand them over to legacy-classes/Themify/themify.js.
 */
class Themify extends PageBuilder_Base {
	/**
	 * The slug of our module.
	 */
	public const MODULE_SLUG = 'easy-language-switcher';

	/**
	 * The name of the icon of our module (Themify Builder references it as SVG symbol "#tf-ti-<icon>").
	 */
	public const ICON = 'easy-language-switcher';

	/**
	 * The name of the dataset to load the options of the visibility objects via AJAX.
	 */
	private const OBJECTS_DATASET = 'easy_language_objects';

	/**
	 * Instance of this object.
	 *
	 * @var ?Themify
	 */
	private static ?Themify $instance = null;

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
	public static function get_instance(): Themify {
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
		add_action( 'themify_builder_setup_modules', array( $this, 'register_module' ) );
		add_action( 'themify_builder_active_enqueue', array( $this, 'enqueue_editor_scripts' ) );
		add_filter( 'tb_select_dataset_' . self::OBJECTS_DATASET, array( $this, 'get_object_dataset' ) );
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * Themify Builder is available as plugin or bundled in themes of Themify.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'themify-builder/themify-builder.php' ) || class_exists( 'Themify_Builder_Model' );
	}

	/**
	 * Register our module in Themify Builder.
	 *
	 * @return void
	 */
	public function register_module(): void {
		// bail if Themify Builder is not active.
		$callback = array( 'Themify_Builder_Model', 'add_module' );
		if ( ! $this->is_active() || ! is_callable( $callback ) ) {
			return;
		}

		// register the module file. Themify Builder loads the template from the "templates" directory beside it.
		call_user_func( $callback, Helper::get_plugin_path() . 'legacy-classes/Themify/modules/module-' . self::MODULE_SLUG . '.php' );
	}

	/**
	 * Add the script with the settings of our module and the styles of the switcher to the editor of Themify Builder.
	 *
	 * @return void
	 */
	public function enqueue_editor_scripts(): void {
		// bail if Themify Builder is not active.
		if ( ! $this->is_active() ) {
			return;
		}

		// add the script which defines the settings of our module.
		$path = Helper::get_plugin_path() . 'legacy-classes/Themify/themify.js';
		wp_enqueue_script(
			'easy-language-themify-module',
			Helper::get_plugin_url() . 'legacy-classes/Themify/themify.js',
			array( 'themify-builder-modules-js' ),
			Helper::get_file_version( $path ),
			true
		);

		// add the settings of our module.
		wp_localize_script(
			'easy-language-themify-module',
			'easyLanguageThemifyModule',
			array(
				'options'  => $this->get_module_options(),
				'defaults' => $this->get_module_defaults(),
				'icon'     => $this->get_icon_symbol(),
			)
		);

		// add the styles of the switcher for the preview.
		wp_enqueue_style( 'easy-language-switcher' );
	}

	/**
	 * Return the icon of our module (the Easy Language logo) as data for an SVG symbol.
	 *
	 * Themify Builder shows the icons of modules as references to SVG symbols ("<use href="#tf-ti-<icon>">") in its
	 * sprite "#tf_svg", which it fills from its icon font. As our logo is not part of it, themify.js adds a symbol
	 * with the matching ID to this sprite before Themify Builder copies it into its panels.
	 *
	 * @return array<string,string>
	 */
	private function get_icon_symbol(): array {
		// get the logo.
		$path = Helper::get_plugin_path() . 'gfx/easy-language-icon.svg';
		$svg  = file_exists( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		// bail if the logo could not be read.
		if ( ! preg_match( '/<svg[^>]*viewBox="([^"]+)"[^>]*>(.*)<\/svg>/is', $svg, $matches ) ) {
			return array();
		}

		// return the data for the symbol.
		return array(
			'id'      => 'tf-ti-' . self::ICON,
			'viewBox' => $matches[1],
			'content' => trim( $matches[2] ),
		);
	}

	/**
	 * Return the options for the visibility objects as dataset for select fields in Themify Builder.
	 *
	 * Themify Builder loads them via AJAX if the settings of our module are opened.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function get_object_dataset(): array {
		return array(
			'options' => Switcher::get_instance()->get_widget_object_options(),
		);
	}

	/**
	 * Return the settings of our module in the format of Themify Builder.
	 *
	 * The settings are generated from the field definitions of the switcher, grouped by their sections.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_module_options(): array {
		$switcher = Switcher::get_instance();
		$fields   = $switcher->get_widget_fields();

		// collect the bindings: which fields are shown for which values of other fields.
		$bindings = $this->get_bindings( $fields );

		// group the fields by their sections.
		$sections = array();
		foreach ( $fields as $name => $field ) {
			$section                       = isset( $field['section'] ) ? (string) $field['section'] : 'settings';
			$sections[ $section ][ $name ] = $field;
		}

		// create the options.
		$options = array();
		$titles  = $switcher->get_widget_sections();
		foreach ( $sections as $section => $section_fields ) {
			// add a separator with the title of the section.
			$separator = array(
				'type'  => 'separator',
				'label' => $titles[ $section ] ?? $section,
			);

			// hide the separator together with its fields if all fields of the section use the same condition.
			$condition = $this->get_common_condition( $section_fields );
			if ( ! empty( $condition ) ) {
				$separator['wrap_class'] = 'easy_language_section_' . sanitize_html_class( $section );
				$bindings                = $this->add_binding( $bindings, $fields, $condition, $separator['wrap_class'] );
			}
			$options[] = $separator;

			// add the fields of this section.
			foreach ( $section_fields as $name => $field ) {
				$option = $this->get_option( (string) $name, $field );
				if ( isset( $bindings[ $name ] ) ) {
					$option['binding'] = $bindings[ $name ];
				}
				$options[] = $option;
			}
		}

		// add the field for custom CSS classes and ID like other modules.
		$options[] = array(
			'type'       => 'custom_css_id',
			'custom_css' => 'add_css_text',
		);

		// return the resulting options.
		return $options;
	}

	/**
	 * Return the default values of our module in the format of Themify Builder.
	 *
	 * @return array<string,mixed>
	 */
	public function get_module_defaults(): array {
		$defaults = array();
		foreach ( Switcher::get_instance()->get_widget_fields() as $name => $field ) {
			// switches are saved as "yes" or "no".
			if ( 'switch' === $field['type'] ) {
				$defaults[ $name ] = ! empty( $field['default'] ) ? 'yes' : 'no';
				continue;
			}

			// only use scalar values (Themify Builder saves sizes and colors as strings).
			if ( is_scalar( $field['default'] ) && '' !== $field['default'] ) {
				$defaults[ $name ] = $field['default'];
			}
		}
		return $defaults;
	}

	/**
	 * Return a single option for Themify Builder from a field definition.
	 *
	 * @param string              $name The name of the field.
	 * @param array<string,mixed> $field The field definition.
	 *
	 * @return array<string,mixed>
	 */
	private function get_option( string $name, array $field ): array {
		$option = array(
			'id'    => $name,
			'label' => $field['label'] ?? $name,
		);

		// add the field type specific settings.
		switch ( $field['type'] ) {
			case 'select':
				$option['type']    = 'select';
				$option['options'] = $field['options'] ?? array();
				$option['default'] = (string) $field['default'];
				break;

			case 'switch':
				$option['type']    = 'toggle_switch';
				$option['options'] = 'simple';
				$option['default'] = ! empty( $field['default'] ) ? 'on' : 'off';
				break;

			case 'size':
				$option['type']  = 'range';
				$option['units'] = $this->get_units( isset( $field['units'] ) && is_array( $field['units'] ) ? $field['units'] : array( 'px' ) );
				break;

			case 'color':
				$option['type'] = 'color';
				break;

			case 'objects':
				$option['type']     = 'select';
				$option['multiple'] = true;
				$option['dataset']  = self::OBJECTS_DATASET;
				break;

			default:
				$option['type'] = 'text';
				if ( is_scalar( $field['default'] ) ) {
					$option['default'] = (string) $field['default'];
				}
		}

		// add the description.
		if ( ! empty( $field['description'] ) ) {
			if ( in_array( $option['type'], array( 'select', 'text' ), true ) ) {
				$option['description'] = $field['description'];
			} else {
				$option['help'] = $field['description'];
			}
		}

		// return the resulting option.
		return $option;
	}

	/**
	 * Return the units for a range field in Themify Builder.
	 *
	 * @param array<int,string> $units List of units.
	 *
	 * @return array<string,array<string,int|float>>
	 */
	private function get_units( array $units ): array {
		$list = array();
		foreach ( $units as $unit ) {
			$list[ $unit ] = 'px' === $unit
				? array(
					'min' => 0,
					'max' => 200,
				)
				: array(
					'min'       => 0,
					'max'       => '%' === $unit ? 50 : 10,
					'increment' => '%' === $unit ? 1 : 0.1,
				);
		}
		return $list;
	}

	/**
	 * Return the bindings for the fields which control the visibility of other fields.
	 *
	 * Themify Builder shows and hides fields via "binding" on the controlling field: for each value of a select
	 * field (or checked/not_checked for switches) it lists the fields to show and to hide.
	 *
	 * @param array<string,array<string,mixed>> $fields The field definitions.
	 *
	 * @return array<string,array<string,array<string,array<int,string>>>>
	 */
	private function get_bindings( array $fields ): array {
		$bindings = array();
		foreach ( $fields as $name => $field ) {
			// bail if this field has no condition.
			if ( empty( $field['condition'] ) || ! is_array( $field['condition'] ) ) {
				continue;
			}

			// add the binding.
			$bindings = $this->add_binding( $bindings, $fields, $field['condition'], (string) $name );
		}
		return $bindings;
	}

	/**
	 * Add the binding for an element (a field or a separator) with a condition.
	 *
	 * Hint: if a condition uses multiple fields, the element is shown or hidden by the last change of any of them.
	 *
	 * @param array<string,array<string,array<string,array<int,string>>>> $bindings The bindings.
	 * @param array<string,array<string,mixed>>                           $fields The field definitions.
	 * @param array<string,mixed>                                         $condition The condition.
	 * @param string                                                      $target The class of the element to show or hide.
	 *
	 * @return array<string,array<string,array<string,array<int,string>>>>
	 */
	private function add_binding( array $bindings, array $fields, array $condition, string $target ): array {
		foreach ( $condition as $controller => $allowed_values ) {
			// bail if the controlling field does not exist.
			if ( ! isset( $fields[ $controller ] ) ) {
				continue;
			}

			// get the allowed values as list.
			$allowed_values = (array) $allowed_values;

			// get the possible values of the controlling field.
			$controller_field = $fields[ $controller ];
			if ( 'switch' === $controller_field['type'] ) {
				$values = array(
					'checked'     => in_array( true, $allowed_values, true ),
					'not_checked' => in_array( false, $allowed_values, true ),
				);
			} else {
				$values = array();
				foreach ( array_keys( $controller_field['options'] ?? array() ) as $value ) {
					$values[ (string) $value ] = in_array( (string) $value, array_map( 'strval', $allowed_values ), true );
				}
			}

			// add the target to "show" or "hide" for each value.
			foreach ( $values as $value => $show ) {
				$bindings[ $controller ][ $value ][ $show ? 'show' : 'hide' ][] = $target;
			}
		}
		return $bindings;
	}

	/**
	 * Return the condition all fields share, or an empty array if they use different conditions.
	 *
	 * @param array<string,array<string,mixed>> $fields The field definitions.
	 *
	 * @return array<string,mixed>
	 */
	private function get_common_condition( array $fields ): array {
		$condition = null;
		foreach ( $fields as $field ) {
			$field_condition = isset( $field['condition'] ) && is_array( $field['condition'] ) ? $field['condition'] : array();
			if ( null === $condition ) {
				$condition = $field_condition;
				continue;
			}
			if ( $condition !== $field_condition ) {
				return array();
			}
		}
		return is_array( $condition ) ? $condition : array();
	}

	/**
	 * Return the output of our module.
	 *
	 * Called by the template of our module in legacy-classes/Themify/templates/.
	 *
	 * @param array<string,mixed> $settings The settings of the module.
	 *
	 * @return string
	 */
	public function render_module( array $settings ): string {
		$switcher = Switcher::get_instance();

		// convert the values of Themify Builder.
		$values = array();
		foreach ( $switcher->get_widget_fields() as $name => $field ) {
			// bail if the value is not set.
			if ( ! isset( $settings[ $name ] ) ) {
				continue;
			}
			$value = $settings[ $name ];

			// convert the value depending on its type.
			switch ( $field['type'] ) {
				case 'size':
					// Themify Builder saves the unit in an extra field.
					if ( is_scalar( $value ) && '' !== $value && is_numeric( $value ) ) {
						$unit  = isset( $settings[ $name . '_unit' ] ) && is_string( $settings[ $name . '_unit' ] ) ? $settings[ $name . '_unit' ] : '';
						$value = $value . ( '' !== $unit ? $unit : 'px' );
					}
					break;

				case 'color':
					$value = is_string( $value ) ? $this->convert_color( $value ) : '';
					break;
			}

			// add the value.
			$values[ $name ] = $value;
		}

		// bail if this is not the preview via AJAX.
		if ( ! $this->is_preview() || ! wp_doing_ajax() ) {
			return $switcher->render_widget( $values, 'themify', $this->is_preview() );
		}

		// the editor renders the preview via AJAX without a queried object: use the edited post for it.
		global $wp_query;
		$original_query = $wp_query;
		$post           = get_post( $this->get_edited_post_id() );
		if ( $post instanceof WP_Post && 0 === get_queried_object_id() ) {
			$wp_query                    = new WP_Query(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_query->queried_object    = $post;
			$wp_query->queried_object_id = $post->ID;
		}

		// get the output.
		$output = $switcher->render_widget( $values, 'themify', true );

		// restore the original query.
		$wp_query = $original_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// return the output.
		return $output;
	}

	/**
	 * Return the ID of the post which is edited with Themify Builder.
	 *
	 * @return int
	 */
	private function get_edited_post_id(): int {
		if ( ! class_exists( 'Themify_Builder' ) || ! isset( \Themify_Builder::$builder_active_id ) ) {
			return 0;
		}
		return absint( \Themify_Builder::$builder_active_id );
	}

	/**
	 * Convert a color from Themify Builder (e.g. "ff0000_0.5" or "--var") to a CSS color.
	 *
	 * @param string $color The color.
	 *
	 * @return string
	 */
	private function convert_color( string $color ): string {
		$color = trim( $color );

		// bail on empty values.
		if ( '' === $color ) {
			return '';
		}

		// convert CSS variables.
		if ( str_starts_with( $color, '--' ) ) {
			return 'var(' . $color . ')';
		}

		// bail if this is no color with opacity.
		if ( ! preg_match( '/^#?([0-9a-f]{3}|[0-9a-f]{6})(?:_([0-9.]*))?$/i', $color, $matches ) ) {
			return $color;
		}

		// get the opacity.
		$opacity = isset( $matches[2] ) && '' !== $matches[2] ? (float) $matches[2] : 1.0;
		if ( $opacity >= 0.99 ) {
			return '#' . $matches[1];
		}

		// convert to rgba.
		$hex = 3 === strlen( $matches[1] ) ? $matches[1][0] . $matches[1][0] . $matches[1][1] . $matches[1][1] . $matches[1][2] . $matches[1][2] : $matches[1];
		return 'rgba(' . hexdec( substr( $hex, 0, 2 ) ) . ',' . hexdec( substr( $hex, 2, 2 ) ) . ',' . hexdec( substr( $hex, 4, 2 ) ) . ',' . max( 0, $opacity ) . ')';
	}

	/**
	 * Return whether the module is rendered in the editor of Themify Builder.
	 *
	 * @return bool
	 */
	private function is_preview(): bool {
		// the editor renders modules via AJAX with this flag.
		if ( class_exists( 'Themify_Builder' ) && isset( \Themify_Builder::$frontedit_active ) && true === \Themify_Builder::$frontedit_active ) {
			return true;
		}

		// the visual editor in frontend.
		$callback = array( 'Themify_Builder_Model', 'is_front_builder_activate' );
		return is_callable( $callback ) && true === call_user_func( $callback );
	}
}
