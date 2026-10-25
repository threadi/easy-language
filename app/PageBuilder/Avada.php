<?php
/**
 * File to handle support for the page builder Avada (Avada Builder / Fusion Builder).
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\EasyLanguage\Switcher;

/**
 * Object to handle support for the page builder Avada.
 *
 * Adds the language switcher as native element to the Avada Builder (backend and live builder).
 */
class Avada extends PageBuilder_Base {
	/**
	 * The shortcode of our language switcher element.
	 */
	public const SHORTCODE = 'easy_language_avada_switcher';

	/**
	 * Instance of this object.
	 *
	 * @var ?Avada
	 */
	private static ?Avada $instance = null;

	/**
	 * Marker whether the actual output is rendered for the preview in the live builder.
	 *
	 * @var bool
	 */
	private bool $is_preview_render = false;

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
	public static function get_instance(): Avada {
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
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'fusion_builder_before_init', array( $this, 'register_element' ) );
		add_action( 'awb_before_ajax_shortcode_render', array( $this, 'start_preview_render' ) );
		add_action( 'awb_after_ajax_shortcode_render', array( $this, 'end_preview_render' ) );
		add_action( 'fusion_builder_enqueue_live_scripts', array( $this, 'enqueue_icon_style' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_icon_style_in_backend' ) );
	}

	/**
	 * Load the style for the icon of our element in the live builder or in the backend builder.
	 *
	 * @param bool $backend True if the style is loaded for the backend builder.
	 *
	 * @return void
	 */
	public function enqueue_icon_style( bool $backend = false ): void {
		// bail if Avada is not active.
		if ( ! $this->is_active() ) {
			return;
		}

		// load the style for the icon.
		wp_enqueue_style( 'easy-language-builder-icon' );

		// in the backend builder Avada shows the icons inline before the name of the element (like Avada's own CSS does).
		if ( $backend ) {
			wp_add_inline_style( 'easy-language-builder-icon', '.fusion-module-icon.easy-language-builder-icon { display: inline-block !important; width: 1em !important; height: 1em !important; margin-right: 0.5em; vertical-align: -0.125em; } .rtl .fusion-module-icon.easy-language-builder-icon { margin-right: 0; margin-left: 0.5em; }' );
			return;
		}

		// in the live builder Avada shows the icons as block with font glyphs, which has no size without glyph; so we set
		// the size and show the icon as block (centered above the name like the other icons).
		wp_add_inline_style( 'easy-language-builder-icon', '.fusion-module-icon.easy-language-builder-icon { display: block !important; width: 1em !important; height: 1em !important; margin-left: auto; margin-right: auto; }' );
	}

	/**
	 * Load the style for the icon of our element in the backend builder (on the edit pages of posts).
	 *
	 * @param string $hook The actual admin page.
	 *
	 * @return void
	 */
	public function enqueue_icon_style_in_backend( string $hook ): void {
		// bail if this is not an edit page.
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$this->enqueue_icon_style( true );
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * Avada is active if the theme "Avada" (or a child theme of it) is used.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		$is_avada = false;
		$theme    = wp_get_theme();
		if ( 'Avada' === $theme->get( 'Name' ) ) {
			$is_avada = true;
		}
		if ( $theme->parent() && 'Avada' === $theme->parent()->get( 'Name' ) ) {
			$is_avada = true;
		}
		return $is_avada;
	}

	/**
	 * Register the shortcode of our element.
	 *
	 * The shortcode is used for the output in frontend and in the preview of the live builder.
	 *
	 * @return void
	 */
	public function register_shortcode(): void {
		// bail if Avada is not active.
		if ( ! $this->is_active() ) {
			return;
		}

		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
	}

	/**
	 * Register our language switcher as element in the Avada Builder.
	 *
	 * @return void
	 */
	public function register_element(): void {
		// bail if Avada is not active or the Avada Builder is not loaded.
		if ( ! $this->is_active() || ! function_exists( 'fusion_builder_map' ) ) {
			return;
		}

		fusion_builder_map(
			array(
				'name'      => __( 'Language Switcher', 'easy-language' ),
				'shortcode' => self::SHORTCODE,
				'icon'      => 'easy-language-builder-icon',
				'params'    => $this->get_params(),
			)
		);
	}

	/**
	 * Return the params of our element in the format of the Avada Builder.
	 *
	 * The params are generated from the central field definitions of the switcher.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_params(): array {
		$switcher = Switcher::get_instance();
		$fields   = $switcher->get_widget_fields();
		$sections = $switcher->get_widget_sections();

		// get the design fields for the hints of size fields.
		$design_fields = $switcher->get_toggle_design_fields();

		// collect the params.
		$params = array();
		foreach ( $fields as $name => $field ) {
			// get the basic settings of this param.
			$param = array(
				'heading'     => $field['label'],
				'description' => $field['description'] ?? '',
				'param_name'  => $name,
			);

			// add the section as group (tab), except for the main settings which are shown in the default tab "General".
			if ( 'settings' !== $field['section'] && isset( $sections[ $field['section'] ] ) ) {
				$param['group'] = $this->get_group_title( $sections[ $field['section'] ], $fields );
			}

			// add the type specific settings.
			switch ( $field['type'] ) {
				case 'select':
					$options          = (array) ( $field['options'] ?? array() );
					$param['type']    = $this->use_button_set( $options ) ? 'radio_button_set' : 'select';
					$param['value']   = $options;
					$param['default'] = (string) $field['default'];
					break;
				case 'switch':
					$param['type']    = 'radio_button_set';
					$param['value']   = array(
						'yes' => __( 'Yes', 'easy-language' ),
						'no'  => __( 'No', 'easy-language' ),
					);
					$param['default'] = $field['default'] ? 'yes' : 'no';
					break;
				case 'color':
					$param['type']    = 'colorpickeralpha';
					$param['value']   = '';
					$param['default'] = '';
					break;
				case 'objects':
					$param['type']             = 'multiple_select';
					$param['value']            = $this->get_object_options();
					$param['default']          = '';
					$param['placeholder_text'] = $field['label'];
					break;
				case 'size':
				default:
					$param['type']    = 'textfield';
					$param['value']   = '';
					$param['default'] = is_scalar( $field['default'] ) ? (string) $field['default'] : '';
					if ( empty( $param['description'] ) ) {
						$param['description'] = $design_fields[ $name ]['placeholder'] ?? __( 'e.g. 24px', 'easy-language' );
					}
					break;
			}

			// add the dependencies.
			$dependency = $this->get_dependency( $field, $fields );
			if ( ! empty( $dependency ) ) {
				$param['dependency'] = $dependency;
			}

			// add this param to the list.
			$params[] = $param;
		}

		// return the resulting list.
		return $params;
	}

	/**
	 * Return the title of a group (tab) for the settings of our element.
	 *
	 * Avada uses the lowercase title as HTML ID of the tab, and it reads the values for dependencies via the ID of the
	 * fields. So the ID of a tab must not be the name of a field (e.g. "Visibility" and the field "visibility"). We
	 * add an invisible trailing space in this case, which results in another ID.
	 *
	 * @param string                            $title The title of the section.
	 * @param array<string,array<string,mixed>> $fields All field definitions.
	 *
	 * @return string
	 */
	private function get_group_title( string $title, array $fields ): string {
		if ( isset( $fields[ str_replace( ' ', '-', strtolower( $title ) ) ] ) ) {
			$title .= ' ';
		}
		return $title;
	}

	/**
	 * Return whether the options of a select should be shown as button set (like Avada does for short options).
	 *
	 * @param array<int|string,mixed> $options The options.
	 *
	 * @return bool
	 */
	private function use_button_set( array $options ): bool {
		// bail if there are too many options.
		if ( count( $options ) > 3 ) {
			return false;
		}

		// check the length of the labels.
		foreach ( $options as $label ) {
			if ( ! is_string( $label ) || mb_strlen( $label ) > 20 ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Convert the conditions of a field to the dependency format of the Avada Builder.
	 *
	 * Avada combines all dependencies with AND and supports single values per rule. So a list of allowed values is
	 * converted to "!=" rules for all other options of the select.
	 *
	 * @param array<string,mixed>               $field The field definition.
	 * @param array<string,array<string,mixed>> $fields All field definitions.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_dependency( array $field, array $fields ): array {
		// bail if this field has no conditions.
		if ( empty( $field['condition'] ) || ! is_array( $field['condition'] ) ) {
			return array();
		}

		// convert each condition.
		$dependency = array();
		foreach ( $field['condition'] as $element => $allowed_values ) {
			// bail if the field is unknown.
			if ( ! isset( $fields[ $element ] ) ) {
				continue;
			}

			// convert the allowed values to strings like Avada saves them.
			$allowed = array();
			foreach ( (array) $allowed_values as $allowed_value ) {
				if ( 'switch' === $fields[ $element ]['type'] ) {
					$allowed_value = $allowed_value ? 'yes' : 'no';
				}
				$allowed[] = (string) $allowed_value;
			}

			// use a simple rule for one value.
			if ( 1 === count( $allowed ) ) {
				$dependency[] = array(
					'element'  => $element,
					'value'    => $allowed[0],
					'operator' => '==',
				);
				continue;
			}

			// otherwise exclude all values which are not allowed.
			$options = 'switch' === $fields[ $element ]['type'] ? array( 'yes', 'no' ) : array_map( 'strval', array_keys( (array) ( $fields[ $element ]['options'] ?? array() ) ) );
			foreach ( array_diff( $options, $allowed ) as $not_allowed ) {
				$dependency[] = array(
					'element'  => $element,
					'value'    => $not_allowed,
					'operator' => '!=',
				);
			}
		}

		// return the resulting list.
		return $dependency;
	}

	/**
	 * Return the options for the selection of pages and terms.
	 *
	 * The live builder of Avada runs in the frontend, so we load the options there also.
	 *
	 * @return array<string,string>
	 */
	private function get_object_options(): array {
		$switcher = Switcher::get_instance();

		// get the options for the backend.
		$options = $switcher->get_widget_object_options();
		if ( ! empty( $options ) || ! function_exists( 'fusion_is_builder_frame' ) || ! fusion_is_builder_frame() ) {
			return $options;
		}

		// get the options for the live builder.
		foreach ( $switcher->search_objects( '', 500 ) as $object ) {
			$options[ $object['value'] ] = $object['label'];
		}
		return $options;
	}

	/**
	 * Mark the following output as preview in the live builder.
	 *
	 * @return void
	 */
	public function start_preview_render(): void {
		$this->is_preview_render = true;
	}

	/**
	 * End the marker for the preview in the live builder.
	 *
	 * @return void
	 */
	public function end_preview_render(): void {
		$this->is_preview_render = false;
	}

	/**
	 * Return whether the output is rendered in the editor of Avada.
	 *
	 * @return bool
	 */
	private function is_preview(): bool {
		// the output via AJAX for the live builder.
		if ( $this->is_preview_render ) {
			return true;
		}

		// the preview frame of the live builder.
		return function_exists( 'fusion_is_preview_frame' ) && fusion_is_preview_frame();
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

		// return the output.
		return Switcher::get_instance()->render_widget( $attributes, 'avada', $this->is_preview() );
	}
}
