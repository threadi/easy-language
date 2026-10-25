<?php
/**
 * File to handle support for the page builder "Fusion" by Agency Dominion.
 *
 * Hint: this is not the "Fusion Builder" of Avada.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Helper;
use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\EasyLanguage\Switcher;

/**
 * Object to handle support for the page builder Fusion.
 *
 * Adds the language switcher as Fusion extension (element).
 */
class Fusion extends PageBuilder_Base {
	/**
	 * The shortcode of our language switcher element.
	 */
	public const SHORTCODE = 'easy_language_fusion_switcher';

	/**
	 * Prefix for our own sections in the settings dialog of the elements.
	 */
	private const SECTION_PREFIX = 'easy_language_';

	/**
	 * Instance of this object.
	 *
	 * @var ?Fusion
	 */
	private static ?Fusion $instance = null;

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
	public static function get_instance(): Fusion {
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
		// Fusion registers its own elements on "init" with priority 12.
		add_action( 'init', array( $this, 'register_element' ), 12 );
		add_filter( 'fsn_param_sections', array( $this, 'add_sections' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_icon_style' ) );
	}

	/**
	 * Load the style for the icon of our element in the editor of Fusion (on the edit pages of posts).
	 *
	 * Fusion shows the icons of elements as ligature of the font "Material Icons" (text content of an <i> element),
	 * which the icon class cannot reach. So we add a rule which hides the text of the icon of our element and shows
	 * the logo of the plugin instead.
	 *
	 * @param string $hook The actual admin page.
	 *
	 * @return void
	 */
	public function enqueue_icon_style( string $hook ): void {
		// bail if Fusion is not active or this is not an edit page.
		if ( ! $this->is_active() || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		// get the URL of the logo.
		$url = Helper::get_plugin_url() . 'gfx/easy-language-icon.svg';

		// add our rule to the style for the icons in page builders.
		wp_enqueue_style( 'easy-language-builder-icon' );
		wp_add_inline_style(
			'easy-language-builder-icon',
			'.element-item[data-element-type="' . self::SHORTCODE . '"] .material-icons { display: inline-block; width: 1em; height: 1em; overflow: hidden; color: transparent; background: url("' . esc_url( $url ) . '") center / contain no-repeat; vertical-align: middle; }'
		);
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return function_exists( 'fsn_map' );
	}

	/**
	 * Register our language switcher as element (extension) in Fusion.
	 *
	 * The shortcode is registered before the element is mapped, as Fusion replaces the shortcode in the backend with
	 * its own output for the editor.
	 *
	 * @return void
	 */
	public function register_element(): void {
		// bail if Fusion is not active.
		if ( ! $this->is_active() || ! function_exists( 'fsn_map' ) ) {
			return;
		}

		// register the shortcode for the output in frontend.
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );

		// map the shortcode as element.
		fsn_map(
			array(
				'name'          => __( 'Language Switcher', 'easy-language' ),
				'shortcode_tag' => self::SHORTCODE,
				'icon'          => 'translate',
				'params'        => $this->get_params(),
			)
		);
	}

	/**
	 * Add our own sections (tabs) to the settings dialog of the elements.
	 *
	 * Fusion uses one list of sections for all elements and hides sections without params.
	 *
	 * @param array<int,array<string,string>> $sections List of sections.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function add_sections( array $sections ): array {
		// collect our sections (except the main settings, which are shown in the section "General").
		$own_sections = array();
		foreach ( Switcher::get_instance()->get_widget_sections() as $key => $title ) {
			if ( 'settings' === $key ) {
				continue;
			}
			$own_sections[] = array(
				'id'   => self::SECTION_PREFIX . $key,
				'name' => $title,
			);
		}

		// add them after the first section ("General").
		array_splice( $sections, 1, 0, $own_sections );

		// return the resulting list.
		return $sections;
	}

	/**
	 * Return the params of our element in the format of Fusion.
	 *
	 * The params are generated from the central field definitions of the switcher.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_params(): array {
		$switcher = Switcher::get_instance();
		$fields   = $switcher->get_widget_fields();

		// get the design fields for the placeholders of size fields.
		$design_fields = $switcher->get_toggle_design_fields();

		// collect the params.
		$params = array();
		foreach ( $fields as $name => $field ) {
			// get the basic settings of this param.
			$param = array(
				'param_name' => $name,
				'label'      => $field['label'],
				'section'    => 'settings' === $field['section'] ? 'general' : self::SECTION_PREFIX . $field['section'],
			);
			if ( ! empty( $field['description'] ) ) {
				$param['help'] = $field['description'];
			}

			// add the type specific settings.
			switch ( $field['type'] ) {
				case 'select':
					$param['type']    = 'select';
					$param['options'] = $this->get_select_options( (array) ( $field['options'] ?? array() ), (string) $field['default'] );
					break;
				case 'switch':
					if ( $field['default'] ) {
						// Fusion has no default values for checkboxes, so we use a select for switches enabled by default.
						$param['type']    = 'select';
						$param['options'] = array(
							'yes' => __( 'Yes', 'easy-language' ),
							'no'  => __( 'No', 'easy-language' ),
						);
						break;
					}
					$param['type'] = 'checkbox';
					break;
				case 'color':
					$param['type'] = 'colorpicker';
					break;
				case 'objects':
					// Fusion has no multiple select, so we use a text field for a comma separated list.
					$param['type']        = 'text';
					$param['placeholder'] = 'post:12,term:5';
					$param['help']        = __( 'Comma separated list of pages and terms, e.g. post:12,term:5.', 'easy-language' );
					break;
				case 'size':
				default:
					$param['type']        = 'text';
					$param['placeholder'] = $design_fields[ $name ]['placeholder'] ?? __( 'e.g. 24px', 'easy-language' );
					break;
			}

			// add the dependency.
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
	 * Return the options of a select with the default option as first entry.
	 *
	 * Fusion has no default values for fields, the browser selects the first option of a select if nothing is saved.
	 *
	 * @param array<int|string,mixed> $options The options.
	 * @param string                  $default_value The default value.
	 *
	 * @return array<int|string,mixed>
	 */
	private function get_select_options( array $options, string $default_value ): array {
		// bail if the default is not an option.
		if ( ! isset( $options[ $default_value ] ) ) {
			return $options;
		}

		// move the default option to the top.
		return array( $default_value => $options[ $default_value ] ) + $options;
	}

	/**
	 * Convert the conditions of a field to the dependency format of Fusion.
	 *
	 * Fusion supports only one dependency per param, but with a list of allowed values. So we use the first condition.
	 *
	 * @param array<string,mixed>               $field The field definition.
	 * @param array<string,array<string,mixed>> $fields All field definitions.
	 *
	 * @return array<string,mixed>
	 */
	private function get_dependency( array $field, array $fields ): array {
		// bail if this field has no conditions.
		if ( empty( $field['condition'] ) || ! is_array( $field['condition'] ) ) {
			return array();
		}

		// use the first condition with a known field.
		foreach ( $field['condition'] as $element => $allowed_values ) {
			// bail if the field is unknown.
			if ( ! isset( $fields[ $element ] ) ) {
				continue;
			}

			// for switches as checkbox: show the param if it is checked.
			if ( 'switch' === $fields[ $element ]['type'] && ! $fields[ $element ]['default'] ) {
				if ( array( true ) !== array_values( array_map( 'boolval', (array) $allowed_values ) ) ) {
					continue;
				}
				return array(
					'param_name' => $element,
					'not_empty'  => true,
				);
			}

			// for selects: show the param if one of the allowed values is selected.
			$allowed = array();
			foreach ( (array) $allowed_values as $allowed_value ) {
				if ( 'switch' === $fields[ $element ]['type'] ) {
					$allowed_value = $allowed_value ? 'yes' : 'no';
				}
				$allowed[] = (string) $allowed_value;
			}
			return array(
				'param_name' => $element,
				'value'      => $allowed,
			);
		}

		// return an empty list if no condition could be used.
		return array();
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

		// get the output.
		$output = Switcher::get_instance()->render_widget( $attributes, 'fusion' );

		// bail if there is no output (e.g. if the switcher should not be visible).
		if ( empty( $output ) ) {
			return '';
		}

		// get the classes of Fusion for the style settings of the element (margins, visibility and custom classes).
		$classes = 'fsn-easy-language-switcher';
		if ( function_exists( 'fsn_style_params_class' ) ) {
			$classes .= ' ' . fsn_style_params_class( $attributes );
		}

		// return the output.
		return '<div class="' . esc_attr( $classes ) . '">' . $output . '</div>';
	}
}
