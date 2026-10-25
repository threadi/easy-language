<?php
/**
 * File to handle support for the page builder Avia Layout Builder (Enfold theme).
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
 * Object to handle support for the page builder Avia Layout Builder of the theme Enfold.
 *
 * Adds the language switcher as native element to the Avia Layout Builder. Avia elements are global classes which
 * extend aviaShortcodeTemplate and are loaded by the builder from shortcode folders. Our element is located in
 * legacy-classes/Avia/ and added to these folders via the filter "avia_load_shortcodes". The element class only
 * forwards to this object, which generates the settings from the central field definitions of the switcher.
 */
class Avia extends PageBuilder_Base {
	/**
	 * The shortcode of our language switcher element.
	 */
	public const SHORTCODE = 'easy_language_avia_switcher';

	/**
	 * Instance of this object.
	 *
	 * @var ?Avia
	 */
	private static ?Avia $instance = null;

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
	public static function get_instance(): Avia {
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
		// Enfold sets its own folders with priority 1 (and overrides the list), so we must use a later priority.
		add_filter( 'avia_load_shortcodes', array( $this, 'add_shortcode_folder' ), 20 );
		add_filter( 'avf_preview_window_css_files', array( $this, 'add_preview_styles' ) );
		add_filter( 'avf_builder_element_icon_file', array( $this, 'set_icon_file' ), 10, 3 );
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * The Avia Layout Builder is active if the theme "Enfold" (or a child theme of it) is used.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		$is_enfold = false;
		$theme     = wp_get_theme();
		if ( 'Enfold' === $theme->get( 'Name' ) ) {
			$is_enfold = true;
		}
		if ( $theme->parent() && 'Enfold' === $theme->parent()->get( 'Name' ) ) {
			$is_enfold = true;
		}
		return $is_enfold;
	}

	/**
	 * Add our folder with the element to the folders the Avia Layout Builder loads its elements from.
	 *
	 * @param array<int,string>|mixed $paths List of folders.
	 *
	 * @return array<int,string>|mixed
	 */
	public function add_shortcode_folder( mixed $paths ): mixed {
		// bail if Enfold is not active or the value is not an array.
		if ( ! is_array( $paths ) || ! $this->is_active() ) {
			return $paths;
		}

		// add our folder (the builder loads all PHP files in it).
		$paths[] = Helper::get_plugin_path() . 'legacy-classes/Avia/';

		// return the resulting list.
		return $paths;
	}

	/**
	 * Add the styles of our switcher to the preview window of the element settings.
	 *
	 * The preview is shown in an iframe which only loads the stylesheets of this list.
	 *
	 * @param array<string,mixed>|mixed $css List of stylesheet URLs with load flag.
	 *
	 * @return array<string,mixed>|mixed
	 */
	public function add_preview_styles( mixed $css ): mixed {
		// bail if Enfold is not active or the value is not an array.
		if ( ! is_array( $css ) || ! $this->is_active() ) {
			return $css;
		}

		// add our style (the builder adds the version itself).
		$css[ Helper::get_plugin_url() . 'css/switcher.css' ] = 1;

		// return the resulting list.
		return $css;
	}

	/**
	 * Return the URL of the icon for our element.
	 *
	 * @return string
	 */
	public function get_icon_url(): string {
		return Helper::get_plugin_url() . 'gfx/easy-language-icon.svg';
	}

	/**
	 * Return the file of the icon for our element.
	 *
	 * The Avia Layout Builder inlines SVG icons from its own folder and sizes them like its own icons. Icons of
	 * other folders are shown as images with another size. So we tell the builder to use our SVG file.
	 *
	 * @param string|mixed $file The absolute path to the file.
	 * @param string|mixed $name The file name.
	 * @param string|mixed $icon The icon of the element (its URL).
	 *
	 * @return string|mixed
	 */
	public function set_icon_file( mixed $file, mixed $name, mixed $icon ): mixed {
		// bail if this is not our icon.
		if ( $this->get_icon_url() !== $icon ) {
			return $file;
		}

		// return the path to our icon.
		return Helper::get_plugin_path() . 'gfx/easy-language-icon.svg';
	}

	/**
	 * Return the content fields of our element in the format of the Avia Layout Builder (one tab per section).
	 *
	 * The fields are generated from the central field definitions of the switcher. The element class adds the
	 * default tab for the advanced settings of Avia.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_popup_elements(): array {
		$switcher = Switcher::get_instance();
		$fields   = $switcher->get_widget_fields();

		// group the fields by their sections.
		$sections = array();
		foreach ( $switcher->get_widget_sections() as $section => $title ) {
			$sections[ $section ] = array();
		}
		foreach ( $fields as $name => $field ) {
			$section = isset( $sections[ $field['section'] ] ) ? $field['section'] : 'settings';

			// add this field to its section.
			$sections[ $section ][] = $this->get_popup_element( $name, $field, $fields );
		}

		// create one tab for each section with fields.
		$titles   = $switcher->get_widget_sections();
		$elements = array();
		foreach ( $sections as $section => $section_elements ) {
			// bail if this section has no fields.
			if ( empty( $section_elements ) ) {
				continue;
			}

			// add the tab with its fields.
			$elements[] = array(
				'type'          => 'tab',
				'name'          => $titles[ $section ] ?? $section,
				'nodescription' => true,
			);
			foreach ( $section_elements as $element ) {
				$elements[] = $element;
			}
			$elements[] = array(
				'type'          => 'tab_close',
				'nodescription' => true,
			);
		}

		// return the resulting list.
		return $elements;
	}

	/**
	 * Return a single field of our element in the format of the Avia Layout Builder.
	 *
	 * @param string                            $name The name of the field.
	 * @param array<string,mixed>               $field The definition of the field.
	 * @param array<string,array<string,mixed>> $fields All field definitions.
	 *
	 * @return array<string,mixed>
	 */
	private function get_popup_element( string $name, array $field, array $fields ): array {
		// get the basic settings of this element.
		$element = array(
			'name' => $field['label'],
			'desc' => $field['description'] ?? '',
			'id'   => $name,
		);

		// add the type specific settings.
		switch ( $field['type'] ) {
			case 'select':
				$element['type']    = 'select';
				$element['std']     = is_scalar( $field['default'] ) ? (string) $field['default'] : '';
				$element['subtype'] = $this->get_subtype( (array) ( $field['options'] ?? array() ) );
				break;
			case 'switch':
				// a checked checkbox is saved with its ID as value, an unchecked one is not saved.
				$element['type'] = 'checkbox';
				$element['std']  = $field['default'] ? $name : '';

				// the builder positions the checkbox beside its description, so it needs one to get enough space.
				if ( empty( $element['desc'] ) ) {
					$element['desc'] = '&nbsp;';
				}
				break;
			case 'color':
				$element['type'] = 'colorpicker';
				$element['std']  = '';
				$element['rgba'] = true;
				break;
			case 'objects':
				// the options are only loaded if the settings of the element are opened (see add_object_options()).
				$element['type']     = 'select';
				$element['std']      = '';
				$element['multiple'] = 8;
				$element['subtype']  = array();
				break;
			case 'size':
			default:
				$element['type'] = 'input';
				$element['std']  = is_scalar( $field['default'] ) ? (string) $field['default'] : '';
				if ( empty( $element['desc'] ) ) {
					$design_fields   = Switcher::get_instance()->get_toggle_design_fields();
					$element['desc'] = $design_fields[ $name ]['placeholder'] ?? __( 'e.g. 24px', 'easy-language' );
				}
				break;
		}

		// add the dependency.
		$required = $this->get_required( $field, $fields );
		if ( ! empty( $required ) ) {
			$element['required'] = $required;
		}

		// return the resulting element.
		return $element;
	}

	/**
	 * Convert the options of a select to the format of the Avia Layout Builder (label => value).
	 *
	 * Labels must be unique in this format, so we add the value to duplicate labels.
	 *
	 * @param array<int|string,mixed> $options The options (value => label).
	 *
	 * @return array<string,string>
	 */
	private function get_subtype( array $options ): array {
		$subtype = array();
		foreach ( $options as $value => $label ) {
			$label = is_scalar( $label ) ? (string) $label : (string) $value;
			if ( isset( $subtype[ $label ] ) ) {
				$label .= ' (' . $value . ')';
			}
			$subtype[ $label ] = (string) $value;
		}
		return $subtype;
	}

	/**
	 * Convert the conditions of a field to the dependency format of the Avia Layout Builder.
	 *
	 * Avia supports only one dependency per element with the format array( field, operator, value ). A list of
	 * allowed values is converted to the operator "parent_in_array" with a comma separated list. If a field has
	 * multiple conditions, only the first one can be used.
	 *
	 * @param array<string,mixed>               $field The field definition.
	 * @param array<string,array<string,mixed>> $fields All field definitions.
	 *
	 * @return array<int,string>
	 */
	private function get_required( array $field, array $fields ): array {
		// bail if this field has no conditions.
		if ( empty( $field['condition'] ) || ! is_array( $field['condition'] ) ) {
			return array();
		}

		// use the first known condition.
		foreach ( $field['condition'] as $element => $allowed_values ) {
			// bail if the field is unknown.
			if ( ! isset( $fields[ $element ] ) ) {
				continue;
			}

			// a checked checkbox has a (prefixed) ID as value, an unchecked one an empty value.
			if ( 'switch' === $fields[ $element ]['type'] ) {
				$allowed = array_values( array_unique( array_map( 'boolval', (array) $allowed_values ) ) );
				if ( 1 !== count( $allowed ) ) {
					continue;
				}
				return array( (string) $element, $allowed[0] ? 'not' : 'equals', '' );
			}

			// convert the allowed values to strings.
			$allowed = array();
			foreach ( (array) $allowed_values as $allowed_value ) {
				$allowed[] = is_scalar( $allowed_value ) ? (string) $allowed_value : '';
			}

			// use a simple rule for one value.
			if ( 1 === count( $allowed ) ) {
				return array( (string) $element, 'equals', $allowed[0] );
			}

			// otherwise check against the list.
			return array( (string) $element, 'parent_in_array', implode( ',', $allowed ) );
		}

		// return an empty list if no condition could be used.
		return array();
	}

	/**
	 * Add the options for the selection of pages and terms to the elements of our element.
	 *
	 * This is called before the settings of the element are rendered via AJAX, so the objects are not loaded on
	 * every request.
	 *
	 * @param array<int|string,mixed> $elements The elements of the settings.
	 *
	 * @return array<int|string,mixed>
	 */
	public function add_object_options( array $elements ): array {
		$switcher = Switcher::get_instance();
		$fields   = $switcher->get_widget_fields();

		// add the options to each field with objects.
		foreach ( $elements as $index => $element ) {
			// bail if this is not a field with objects.
			if ( ! is_array( $element ) || ! isset( $element['id'] ) || ! isset( $fields[ $element['id'] ] ) || 'objects' !== $fields[ $element['id'] ]['type'] ) {
				continue;
			}

			// add the options.
			$elements[ $index ]['subtype'] = $this->get_subtype( $switcher->get_widget_object_options() );
		}

		// return the resulting list.
		return $elements;
	}

	/**
	 * Return the values of our element for the switcher from the attributes of the shortcode.
	 *
	 * The Avia Layout Builder does not save unchecked checkboxes. So a missing switch is disabled, if the shortcode
	 * was saved by the builder (which always saves the attribute "sc_version"). For manually written shortcodes the
	 * defaults are used.
	 *
	 * @param array<string,mixed> $attributes The attributes of the shortcode.
	 *
	 * @return array<string,mixed>
	 */
	public function get_values( array $attributes ): array {
		$from_builder = isset( $attributes['sc_version'] );

		// convert the switches.
		foreach ( Switcher::get_instance()->get_widget_fields() as $name => $field ) {
			// bail if this is not a switch.
			if ( 'switch' !== $field['type'] ) {
				continue;
			}

			// bail if the value is missing in a manually written shortcode.
			if ( ! isset( $attributes[ $name ] ) && ! $from_builder ) {
				continue;
			}

			// a checked checkbox has its ID as value.
			$value = $attributes[ $name ] ?? '';
			if ( ! in_array( $value, array( 'yes', 'no', 'on', 'off', '1', '0', 'true', 'false' ), true ) ) {
				$value = '' !== $value ? 'yes' : 'no';
			}
			$attributes[ $name ] = $value;
		}

		// return the resulting values.
		return $attributes;
	}

	/**
	 * Return whether the output is rendered in the editor of the Avia Layout Builder.
	 *
	 * The builder renders the preview of an element in its settings via AJAX ("text to preview" mode).
	 *
	 * @return bool
	 */
	public function is_preview(): bool {
		if ( ! function_exists( 'Avia_Builder' ) ) {
			return false;
		}
		$builder = Avia_Builder();
		return is_object( $builder ) && method_exists( $builder, 'in_text_to_preview_mode' ) && $builder->in_text_to_preview_mode();
	}

	/**
	 * Return the output of our element.
	 *
	 * @param array<string,mixed>|string $attributes The attributes of the shortcode.
	 *
	 * @return string
	 */
	public function render( array|string $attributes ): string {
		// convert the attributes.
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}

		// return the output.
		return Switcher::get_instance()->render_widget( $this->get_values( $attributes ), 'avia', $this->is_preview() );
	}
}
