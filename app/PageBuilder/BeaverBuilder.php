<?php
/**
 * File to handle support for the page builder Beaver Builder.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// deny direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\EasyLanguage\Switcher;
use easyLanguage\Plugin\Helper;

/**
 * Object to handle support for the page builder Beaver Builder (Lite and Pro).
 *
 * Adds the module "Language Switcher" to Beaver Builder. The module itself (PHP class and template) is located in
 * legacy-classes/BeaverBuilder/ as Beaver Builder expects a fixed directory structure (the slug of a module is the
 * name of its class file, the output is loaded from "includes/frontend.php" beside it). The settings form of the
 * module is generated here from the field definitions of the switcher.
 */
class BeaverBuilder extends PageBuilder_Base {
	/**
	 * The slug of our module (equals the name of its directory and class file).
	 */
	public const MODULE_SLUG = 'easy-language-switcher';

	/**
	 * The name of the auto suggest action for the visibility objects.
	 */
	private const OBJECTS_ACTION = 'easy_language_objects';

	/**
	 * Instance of this object.
	 *
	 * @var ?BeaverBuilder
	 */
	private static ?BeaverBuilder $instance = null;

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
	public static function get_instance(): BeaverBuilder {
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
		// register our module after Beaver Builder has loaded its own modules (on "init" with priority 2).
		add_action( 'init', array( $this, 'register_module' ), 20 );

		// search and show the visibility objects in the auto suggest field of our module.
		add_filter( 'fl_ajax_fl_builder_autosuggest', array( $this, 'get_object_suggestions' ) );
		add_filter( 'fl_ajax_get_autosuggest_values', array( $this, 'get_object_values' ), 10, 2 );
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return ( Helper::is_plugin_active( 'beaver-builder-lite-version/fl-builder.php' ) || Helper::is_plugin_active( 'bb-plugin/fl-builder.php' ) ) && class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' );
	}

	/**
	 * Register our module in Beaver Builder.
	 *
	 * @return void
	 */
	public function register_module(): void {
		// bail if Beaver Builder is not active.
		$callback = array( 'FLBuilder', 'register_module' );
		if ( ! $this->is_active() || ! is_callable( $callback ) ) {
			return;
		}

		// load the class of our module.
		require_once $this->get_module_dir() . self::MODULE_SLUG . '.php';

		// bail if the class does not exist.
		if ( ! class_exists( 'Easy_Language_Beaver_Builder_Switcher' ) ) {
			return;
		}

		// register the module with its settings form.
		call_user_func( $callback, 'Easy_Language_Beaver_Builder_Switcher', $this->get_module_form() );
	}

	/**
	 * Return the path of the directory of our module.
	 *
	 * @return string
	 */
	public function get_module_dir(): string {
		return Helper::get_plugin_path() . 'legacy-classes/BeaverBuilder/' . self::MODULE_SLUG . '/';
	}

	/**
	 * Return the URL of the directory of our module.
	 *
	 * @return string
	 */
	public function get_module_url(): string {
		return Helper::get_plugin_url() . 'legacy-classes/BeaverBuilder/' . self::MODULE_SLUG . '/';
	}

	/**
	 * Return the icon of our module (the Easy Language logo) as inline SVG.
	 *
	 * Beaver Builder shows the icon in its module list and accepts inline SVG code for it.
	 *
	 * @return string
	 */
	public function get_module_icon(): string {
		$path = Helper::get_plugin_path() . 'gfx/easy-language-icon.svg';
		$svg  = file_exists( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		// bail if this is no SVG.
		if ( ! str_starts_with( trim( $svg ), '<svg' ) ) {
			return '';
		}

		// use the size of the icons of Beaver Builder.
		return (string) preg_replace( '/^<svg([^>]*?)\swidth="[^"]*"\s+height="[^"]*"/', '<svg$1 width="20" height="20"', trim( $svg ) );
	}

	/**
	 * Return the settings form of our module in the format of Beaver Builder.
	 *
	 * Each section of the field definitions of the switcher is a tab of the form. Fields with conditions are shown
	 * and hidden via the "toggle" setting of the select field they depend on. If all fields of a section use the same
	 * condition, the complete tab is toggled.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_module_form(): array {
		$switcher = Switcher::get_instance();
		$fields   = $switcher->get_widget_fields();

		// group the fields by their sections.
		$sections = array();
		foreach ( $switcher->get_widget_sections() as $section => $title ) {
			$sections[ $section ] = array();
		}
		foreach ( $fields as $name => $field ) {
			$section = isset( $field['section'] ) && is_string( $field['section'] ) && isset( $sections[ $field['section'] ] ) ? $field['section'] : 'settings';

			$sections[ $section ][ $name ] = $field;
		}

		// collect the toggles: which fields (or tabs) are shown for which values of other fields.
		$toggles = array();
		foreach ( $sections as $section => $section_fields ) {
			// toggle the complete tab if all of its fields use the same condition.
			$condition = $this->get_common_condition( $section_fields );
			if ( ! empty( $condition ) ) {
				$toggles = $this->add_toggle( $toggles, $fields, $condition, 'tabs', $this->get_tab_name( (string) $section ) );
				continue;
			}

			// toggle the single fields.
			foreach ( $section_fields as $name => $field ) {
				if ( ! empty( $field['condition'] ) && is_array( $field['condition'] ) ) {
					$toggles = $this->add_toggle( $toggles, $fields, $field['condition'], 'fields', (string) $name );
				}
			}
		}

		// create the tabs.
		$titles = $switcher->get_widget_sections();
		$form   = array();
		foreach ( $sections as $section => $section_fields ) {
			// bail if this section has no fields.
			if ( empty( $section_fields ) ) {
				continue;
			}

			// get the fields of this tab.
			$tab_fields = array();
			foreach ( $section_fields as $name => $field ) {
				$tab_fields[ $name ] = $this->get_field( $field, $toggles[ $name ] ?? array() );
			}

			// add the tab.
			$form[ $this->get_tab_name( (string) $section ) ] = array(
				'title'    => $titles[ $section ] ?? $section,
				'sections' => array(
					'easy_language_' . $section => array(
						'title'  => '',
						'fields' => $tab_fields,
					),
				),
			);
		}

		// return the resulting form.
		return $form;
	}

	/**
	 * Return the name of the tab for a section.
	 *
	 * @param string $section The section.
	 *
	 * @return string
	 */
	private function get_tab_name( string $section ): string {
		return 'easy_language_' . sanitize_key( $section );
	}

	/**
	 * Return a single field for Beaver Builder from a field definition.
	 *
	 * @param array<string,mixed>                           $field The field definition.
	 * @param array<string,array<string,array<int,string>>> $toggle The toggle for this field (if other fields depend on it).
	 *
	 * @return array<string,mixed>
	 */
	private function get_field( array $field, array $toggle ): array {
		$option = array(
			'label'   => $field['label'] ?? '',
			'default' => is_scalar( $field['default'] ) ? (string) $field['default'] : '',
			// the output is rendered by PHP, so refresh the preview of the module on changes.
			'preview' => array(
				'type' => 'refresh',
			),
		);

		// add the field type specific settings.
		switch ( $field['type'] ) {
			case 'select':
				$option['type']    = 'select';
				$option['options'] = $field['options'] ?? array();
				break;

			case 'switch':
				// Beaver Builder has no switch: use a select with "yes" and "no" like its own modules.
				$option['type']    = 'select';
				$option['options'] = array(
					'no'  => __( 'No', 'easy-language' ),
					'yes' => __( 'Yes', 'easy-language' ),
				);
				$option['default'] = ! empty( $field['default'] ) ? 'yes' : 'no';
				break;

			case 'size':
				$units             = isset( $field['units'] ) && is_array( $field['units'] ) ? array_values( $field['units'] ) : array( 'px' );
				$option['type']    = 'unit';
				$option['units']   = $units;
				$option['slider']  = $this->get_slider( $units );
				$option['default'] = '';
				break;

			case 'color':
				$option['type']       = 'color';
				$option['show_reset'] = true;
				$option['show_alpha'] = true;
				$option['default']    = '';
				break;

			case 'objects':
				$option['type']    = 'suggest';
				$option['action']  = self::OBJECTS_ACTION;
				$option['default'] = '';
				break;

			default:
				$option['type'] = 'text';
				break;
		}

		// add the description as help text.
		if ( ! empty( $field['description'] ) ) {
			$option['help'] = $field['description'];
		}

		// add the toggle for depending fields.
		if ( ! empty( $toggle ) && 'select' === $option['type'] ) {
			$option['toggle'] = $toggle;
		}

		// return the resulting field.
		return $option;
	}

	/**
	 * Return the slider settings for a unit field.
	 *
	 * @param array<int,string> $units List of units.
	 *
	 * @return array<string,array<string,int|float>>
	 */
	private function get_slider( array $units ): array {
		$slider = array();
		foreach ( $units as $unit ) {
			$slider[ $unit ] = 'px' === $unit
				? array(
					'min'  => 0,
					'max'  => 200,
					'step' => 1,
				)
				: array(
					'min'  => 0,
					'max'  => '%' === $unit ? 50 : 10,
					'step' => '%' === $unit ? 1 : 0.1,
				);
		}
		return $slider;
	}

	/**
	 * Add a toggle for an element (a field or a tab) with a condition.
	 *
	 * Hint: if a condition uses multiple fields, the element is shown or hidden by the last change of any of them.
	 *
	 * @param array<string,array<string,array<string,array<int,string>>>> $toggles The toggles.
	 * @param array<string,array<string,mixed>>                           $fields The field definitions.
	 * @param array<string,mixed>                                         $condition The condition.
	 * @param string                                                      $type The type of the element ("fields" or "tabs").
	 * @param string                                                      $target The name of the element.
	 *
	 * @return array<string,array<string,array<string,array<int,string>>>>
	 */
	private function add_toggle( array $toggles, array $fields, array $condition, string $type, string $target ): array {
		foreach ( $condition as $controller => $allowed_values ) {
			// bail if the controlling field does not exist or could not toggle other elements.
			if ( ! isset( $fields[ $controller ] ) || ! in_array( $fields[ $controller ]['type'], array( 'select', 'switch' ), true ) ) {
				continue;
			}

			// add the target for each allowed value (switches use "yes" and "no").
			foreach ( (array) $allowed_values as $value ) {
				if ( 'switch' === $fields[ $controller ]['type'] ) {
					$value = $value ? 'yes' : 'no';
				}
				$toggles[ $controller ][ (string) $value ][ $type ][] = $target;
			}
		}
		return $toggles;
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
	 * Return the search results for the auto suggest field of the visibility objects.
	 *
	 * Beaver Builder only knows its own auto suggest actions (posts, terms, users, links) and returns nothing for
	 * other ones. So we add the results for our action to the response of its AJAX request.
	 *
	 * @param mixed $result The result of Beaver Builder.
	 *
	 * @return mixed
	 */
	public function get_object_suggestions( mixed $result ): mixed {
		// bail if this is not our action (the nonce has been checked by Beaver Builder).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['fl_as_action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['fl_as_action'] ) ) : '';
		if ( ! $this->is_active() || self::OBJECTS_ACTION !== $action ) {
			return $result;
		}

		// get the search string.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_REQUEST['fl_as_query'] ) ? urldecode( sanitize_text_field( wp_unslash( $_REQUEST['fl_as_query'] ) ) ) : '';
		$search = sanitize_text_field( $search );

		// return the results in the format of Beaver Builder.
		return $this->convert_objects( Switcher::get_instance()->search_objects( $search ) );
	}

	/**
	 * Return the labels of the chosen visibility objects for the auto suggest field when the settings are opened.
	 *
	 * @param mixed               $result The result of Beaver Builder (field name => JSON with the selected entries).
	 * @param array<string,mixed> $args The arguments of the request.
	 *
	 * @return mixed
	 */
	public function get_object_values( mixed $result, array $args ): mixed {
		// bail if Beaver Builder is not active or no fields are given.
		if ( ! $this->is_active() || ! is_array( $result ) || empty( $args['fields'] ) || ! is_array( $args['fields'] ) ) {
			return $result;
		}

		// add the labels for our fields.
		$switcher = Switcher::get_instance();
		foreach ( $args['fields'] as $field ) {
			// bail if this is not our field.
			if ( ! is_array( $field ) || ! isset( $field['action'], $field['name'] ) || self::OBJECTS_ACTION !== $field['action'] ) {
				continue;
			}

			// get the labels of the chosen objects.
			$objects = $this->convert_objects( $switcher->get_object_labels( $switcher->sanitize_objects( $field['value'] ?? '' ) ) );

			// add them in the format of Beaver Builder.
			$result[ (string) $field['name'] ] = str_replace( "'", '&#39;', (string) wp_json_encode( $objects ) );
		}

		// return the resulting list.
		return $result;
	}

	/**
	 * Convert a list of objects (with value and label) to the format of the auto suggest field.
	 *
	 * @param array<int,array<string,string>> $objects The objects.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function convert_objects( array $objects ): array {
		$list = array();
		foreach ( $objects as $object ) {
			$list[] = array(
				'name'  => $object['label'],
				'value' => $object['value'],
			);
		}
		return $list;
	}

	/**
	 * Return the output of our module.
	 *
	 * Called by the template of our module in legacy-classes/BeaverBuilder/.
	 *
	 * @param array<string,mixed> $settings The settings of the module.
	 *
	 * @return string
	 */
	public function render_module( array $settings ): string {
		$switcher = Switcher::get_instance();

		// convert the values of Beaver Builder.
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
					// Beaver Builder saves the unit in an extra field.
					if ( is_scalar( $value ) && is_numeric( $value ) ) {
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

		// return the output.
		return $switcher->render_widget( $values, 'beaverbuilder', $this->is_preview() );
	}

	/**
	 * Convert a color from Beaver Builder (hex colors are saved without "#") to a CSS color.
	 *
	 * @param string $color The color.
	 *
	 * @return string
	 */
	private function convert_color( string $color ): string {
		$color = trim( $color );

		// add the "#" to hex colors.
		if ( preg_match( '/^(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color ) ) {
			return '#' . $color;
		}

		// return any other value (e.g. rgba() or CSS variables of global colors).
		return $color;
	}

	/**
	 * Return whether the module is rendered in the editor of Beaver Builder.
	 *
	 * The editor renders modules in the frontend (also via its own AJAX requests to the edited page).
	 *
	 * @return bool
	 */
	private function is_preview(): bool {
		$callback = array( 'FLBuilderModel', 'is_builder_active' );
		return is_callable( $callback ) && true === call_user_func( $callback );
	}
}
