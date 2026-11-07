<?php
/**
 * File for the definition of the language switcher element in Breakdance and Oxygen 6.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder\Breakdance;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\Switcher;
use easyLanguage\Plugin\Helper;

/**
 * Object with the definition and the output of the language switcher element in Breakdance and Oxygen 6.
 *
 * Oxygen 6 is built on the engine of Breakdance and uses the same element API. So both page builders use
 * the same element class (legacy-classes/Breakdance/elements/Language_Switcher/element.php), which only
 * delegates to this object. This object does not depend on any class of the page builders.
 */
class Switcher_Element {
	/**
	 * Name of the section in the design tab.
	 */
	private const DESIGN_SECTION = 'design';

	/**
	 * Instance of this object.
	 *
	 * @var ?Switcher_Element
	 */
	private static ?Switcher_Element $instance = null;

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
	public static function get_instance(): Switcher_Element {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register the directory with our elements as element location in Breakdance / Oxygen 6.
	 *
	 * Must be called during the action "breakdance_loaded" before priority 10, where the elements get loaded.
	 *
	 * @return void
	 */
	public function register_save_location(): void {
		// bail if the function is not available.
		if ( ! function_exists( '\Breakdance\ElementStudio\registerSaveLocation' ) ) {
			return;
		}

		// the path must be relative to the plugin directory (also for symlinked plugins we use the plugin basename).
		$directory = dirname( plugin_basename( EASY_LANGUAGE ) ) . '/legacy-classes/Breakdance/elements';

		// register the location, but hide it in the Element Studio as these elements must not be edited there.
		\Breakdance\ElementStudio\registerSaveLocation( $directory, 'EasyLanguageElements', 'element', 'Easy Language', true, true );
	}

	/**
	 * Return the icon of the element: the SVG markup of the Easy Language logo.
	 *
	 * Breakdance and Oxygen 6 accept the name of one of their own icons or SVG markup.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		// use the cache.
		static $icon = null;
		if ( is_string( $icon ) ) {
			return $icon;
		}

		// get the SVG of our logo.
		$file = Helper::get_plugin_path() . 'gfx/easy-language-icon.svg';
		$svg  = Helper::get_wp_filesystem( true )->exists( $file ) ? (string) Helper::get_wp_filesystem( true )->get_contents( $file ) : '';

		// use the size of the icon container of the page builder.
		$icon = (string) preg_replace( '/^<svg([^>]*?) width="[^"]*" height="[^"]*"/', '<svg$1 width="100%" height="100%"', trim( $svg ) );

		// use an icon of the page builder as fallback.
		if ( ! str_starts_with( $icon, '<svg' ) ) {
			$icon = 'GlobeIcon';
		}
		return $icon;
	}

	/**
	 * Return the controls of the content tab.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_content_controls(): array {
		$controls = array();
		foreach ( $this->get_sections() as $section => $title ) {
			if ( self::DESIGN_SECTION === $section ) {
				continue;
			}
			$controls[] = $this->get_section_control( $section, $title );
		}
		return $controls;
	}

	/**
	 * Return the controls of the design tab.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_design_controls(): array {
		$sections = $this->get_sections();
		if ( ! isset( $sections[ self::DESIGN_SECTION ] ) ) {
			return array();
		}
		return array( $this->get_section_control( self::DESIGN_SECTION, $sections[ self::DESIGN_SECTION ] ) );
	}

	/**
	 * Return the default properties of a new element.
	 *
	 * Only not empty defaults are set, as the conditions of the controls depend on them.
	 *
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	public function get_default_properties(): array {
		$properties = array();
		foreach ( Switcher::get_instance()->get_widget_fields() as $name => $field ) {
			if ( empty( $field['default'] ) || ! is_scalar( $field['default'] ) || 'switch' === $field['type'] ) {
				continue;
			}
			$section = $this->get_field_section( $field );
			$properties[ $this->get_tab( $section ) ][ $section ][ $name ] = $field['default'];
		}
		return $properties;
	}

	/**
	 * Return the property paths of all fields.
	 *
	 * Used to render the element again in the editor if one of the values changes.
	 *
	 * @return array<int,string>
	 */
	public function get_property_paths(): array {
		$paths = array();
		foreach ( Switcher::get_instance()->get_widget_fields() as $name => $field ) {
			$paths[] = $this->get_path( $name, $field );
		}
		return $paths;
	}

	/**
	 * Return the output of the element.
	 *
	 * @param mixed $properties The properties of the element as array.
	 * @param bool  $is_builder True if the element is rendered in the editor.
	 *
	 * @return string
	 */
	public function render( mixed $properties, bool $is_builder ): string {
		if ( ! is_array( $properties ) ) {
			$properties = array();
		}

		// collect the values of all fields from their property paths.
		$values = array();
		foreach ( Switcher::get_instance()->get_widget_fields() as $name => $field ) {
			$section = $this->get_field_section( $field );
			$tab     = $this->get_tab( $section );
			if ( ! isset( $properties[ $tab ][ $section ][ $name ] ) ) {
				continue;
			}
			$values[ $name ] = $this->convert_value( $properties[ $tab ][ $section ][ $name ], $field );
		}

		// get the output.
		$html = Switcher::get_instance()->render_widget( $values, $this->get_source(), $is_builder );

		// show the name of the element in the editor if there is no output.
		if ( empty( $html ) && $is_builder ) {
			return '<div class="easy-language-switcher-placeholder">' . esc_html__( 'Language Switcher', 'easy-language' ) . '</div>';
		}

		// return the output.
		return $html;
	}

	/**
	 * Return the source for the output: the actual page builder.
	 *
	 * @return string
	 */
	private function get_source(): string {
		return defined( 'BREAKDANCE_MODE' ) && 'oxygen' === constant( 'BREAKDANCE_MODE' ) ? 'oxygen' : 'breakdance';
	}

	/**
	 * Return the sections.
	 *
	 * @return array<string,string>
	 */
	private function get_sections(): array {
		return Switcher::get_instance()->get_widget_sections();
	}

	/**
	 * Return the section of a field.
	 *
	 * @param array<string,mixed> $field The field definition.
	 *
	 * @return string
	 */
	private function get_field_section( array $field ): string {
		$section = isset( $field['section'] ) && is_string( $field['section'] ) ? $field['section'] : 'settings';
		return isset( $this->get_sections()[ $section ] ) ? $section : 'settings';
	}

	/**
	 * Return the tab of a section: "design" for the design section, otherwise "content".
	 *
	 * @param string $section The section.
	 *
	 * @return string
	 */
	private function get_tab( string $section ): string {
		return self::DESIGN_SECTION === $section ? 'design' : 'content';
	}

	/**
	 * Return the property path of a field.
	 *
	 * @param string              $name The name of the field.
	 * @param array<string,mixed> $field The field definition.
	 *
	 * @return string
	 */
	private function get_path( string $name, array $field ): string {
		$section = $this->get_field_section( $field );
		return $this->get_tab( $section ) . '.' . $section . '.' . $name;
	}

	/**
	 * Return the control of a section with the controls of its fields.
	 *
	 * @param string $section The section.
	 * @param string $title The title of the section.
	 *
	 * @return array<string,mixed>
	 */
	private function get_section_control( string $section, string $title ): array {
		$fields   = Switcher::get_instance()->get_widget_fields();
		$children = array();
		foreach ( $fields as $name => $field ) {
			if ( $this->get_field_section( $field ) !== $section ) {
				continue;
			}

			// add the control of the field.
			$control    = $this->get_field_control( $name, $field, $fields );
			$children[] = $control;

			// show the description as hint below the field (the controls have no description).
			if ( ! empty( $field['description'] ) && 'objects' !== $field['type'] ) {
				$options = array(
					'type'            => 'alert_box',
					'layout'          => 'vertical',
					'alertBoxOptions' => array(
						'style'   => 'default',
						'content' => '<p>' . esc_html( $field['description'] ) . '</p>',
					),
				);
				if ( isset( $control['options']['condition'] ) ) {
					$options['condition'] = $control['options']['condition'];
				}
				$children[] = $this->get_control( $name . '_description', '', $options );
			}
		}

		// hide the complete section if all its fields have the same condition (e.g. the design of the toggle).
		$options    = array(
			'type'   => 'section',
			'layout' => 'vertical',
		);
		$conditions = array_unique( array_map( 'wp_json_encode', array_column( array_column( $children, 'options' ), 'condition' ) ) );
		$with_all   = count( array_column( array_column( $children, 'options' ), 'condition' ) ) === count( $children );
		if ( $with_all && 1 === count( $conditions ) ) {
			$options['condition'] = $children[0]['options']['condition'];
		}

		// return the section.
		return $this->get_control( $section, $title, $options, $children );
	}

	/**
	 * Return the control for a field.
	 *
	 * @param string                            $name The name of the field.
	 * @param array<string,mixed>               $field The field definition.
	 * @param array<string,array<string,mixed>> $fields All field definitions (for the paths of conditions).
	 *
	 * @return array<string,mixed>
	 */
	private function get_field_control( string $name, array $field, array $fields ): array {
		switch ( $field['type'] ) {
			case 'select':
				$options = array(
					'type'   => 'dropdown',
					'layout' => 'vertical',
					'items'  => array(),
				);
				foreach ( (array) ( $field['options'] ?? array() ) as $value => $label ) {
					// an option with empty value is used as placeholder as an empty value means "not set".
					if ( '' === $value ) {
						$options['placeholder'] = (string) $label;
						continue;
					}
					$options['items'][] = array(
						'value' => (string) $value,
						'text'  => (string) $label,
					);
				}
				break;

			case 'switch':
				$options = array(
					'type'   => 'toggle',
					'layout' => 'inline',
				);
				break;

			case 'size':
				$units   = array_values( array_map( 'strval', (array) ( $field['units'] ?? array( 'px' ) ) ) );
				$options = array(
					'type'        => 'unit',
					'layout'      => 'inline',
					'unitOptions' => array(
						'types'       => $units,
						'defaultType' => $units[0] ?? 'px',
					),
				);
				break;

			case 'color':
				$options = array(
					'type'         => 'color',
					'layout'       => 'inline',
					'colorOptions' => array( 'type' => 'solidOnly' ),
				);
				break;

			case 'objects':
				$options = array(
					'type'               => 'multiselect',
					'layout'             => 'vertical',
					'items'              => array(),
					'multiselectOptions' => array( 'searchable' => true ),
				);
				if ( ! empty( $field['description'] ) ) {
					$options['placeholder'] = (string) $field['description'];
				}
				foreach ( $this->get_object_options() as $value => $label ) {
					$options['items'][] = array(
						'value' => (string) $value,
						'text'  => $label,
					);
				}
				break;

			default:
				$options = array(
					'type'   => 'text',
					'layout' => 'vertical',
				);
		}

		// add the conditions (all must match).
		if ( ! empty( $field['condition'] ) && is_array( $field['condition'] ) ) {
			$conditions = array();
			foreach ( $field['condition'] as $condition_field => $values ) {
				if ( ! isset( $fields[ $condition_field ] ) ) {
					continue;
				}
				$conditions[] = array(
					'path'    => $this->get_path( (string) $condition_field, $fields[ $condition_field ] ),
					'operand' => 'is one of',
					'value'   => array_values( (array) $values ),
				);
			}
			if ( ! empty( $conditions ) ) {
				$options['condition'] = array( $conditions );
			}
		}

		// return the control.
		return $this->get_control( $name, (string) $field['label'], $options );
	}

	/**
	 * Return a control in the format of Breakdance (like \Breakdance\Elements\c()).
	 *
	 * @param string                         $slug The slug.
	 * @param string                         $label The label.
	 * @param array<string,mixed>            $options The options.
	 * @param array<int,array<string,mixed>> $children The child controls.
	 *
	 * @return array<string,mixed>
	 */
	private function get_control( string $slug, string $label, array $options, array $children = array() ): array {
		return array(
			'slug'               => $slug,
			'label'              => $label,
			'options'            => $options,
			'enableMediaQueries' => false,
			'enableHover'        => false,
			'children'           => $children,
			'keywords'           => array(),
		);
	}

	/**
	 * Return the options for the selection of pages and terms.
	 *
	 * The builder of Breakdance runs in the frontend, so we load the options there also.
	 *
	 * @return array<string,string>
	 */
	private function get_object_options(): array {
		$switcher = Switcher::get_instance();

		// get the options for the backend.
		$options = $switcher->get_widget_object_options();
		if ( ! empty( $options ) || ! current_user_can( 'edit_posts' ) ) {
			return $options;
		}

		// bail if this is not the builder of Breakdance.
		if ( ! isset( $_GET['breakdance'] ) || 'builder' !== sanitize_key( wp_unslash( $_GET['breakdance'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $options;
		}

		// get the options for the builder.
		foreach ( $switcher->search_objects( '', 500 ) as $object ) {
			$options[ $object['value'] ] = $object['label'];
		}
		return $options;
	}

	/**
	 * Convert a value of Breakdance to the format of the switcher.
	 *
	 * @param mixed               $value The value.
	 * @param array<string,mixed> $field The field definition.
	 *
	 * @return mixed
	 */
	private function convert_value( mixed $value, array $field ): mixed {
		// unit controls save an array with "number", "unit" and "style".
		if ( 'size' === $field['type'] && is_array( $value ) ) {
			if ( isset( $value['style'] ) && is_string( $value['style'] ) ) {
				return $value['style'];
			}
			return isset( $value['number'] ) && '' !== $value['number'] ? $value['number'] . ( $value['unit'] ?? 'px' ) : '';
		}

		// return the value as it is.
		return $value;
	}
}
