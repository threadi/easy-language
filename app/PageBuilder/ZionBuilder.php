<?php
/**
 * File to handle support for the page builder Zion Builder.
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
 * Object to handle support for the page builder Zion Builder.
 *
 * Adds the language switcher as native Zion Builder element.
 */
class ZionBuilder extends PageBuilder_Base {
	/**
	 * Instance of this object.
	 *
	 * @var ?ZionBuilder
	 */
	private static ?ZionBuilder $instance = null;

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
	public static function get_instance(): ZionBuilder {
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
		add_action( 'zionbuilder/elements_manager/register_elements', array( $this, 'register_element' ) );
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'zionbuilder/zionbuilder.php' ) && class_exists( '\ZionBuilder\Elements\Element' );
	}

	/**
	 * Register our element in Zion Builder.
	 *
	 * @param object $elements_manager The elements manager of Zion Builder.
	 *
	 * @return void
	 */
	public function register_element( object $elements_manager ): void {
		// bail if Zion Builder is not active.
		if ( ! $this->is_active() || ! method_exists( $elements_manager, 'register_element' ) ) {
			return;
		}

		// bail if our element class could not be loaded.
		$class_name = '\easyLanguage\PageBuilder\ZionBuilder\Switcher_Element';
		if ( ! class_exists( $class_name ) ) {
			return;
		}

		// register it.
		$elements_manager->register_element( new $class_name() );
	}

	/**
	 * Return the options of our element in the format of Zion Builder.
	 *
	 * The options are generated from the central field definitions of the switcher. Each section is a group.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_element_options(): array {
		$switcher = Switcher::get_instance();
		$groups   = array();
		foreach ( $switcher->get_widget_sections() as $section => $title ) {
			$groups[ $section ] = array(
				'config'  => array(
					'type'      => 'panel_accordion',
					'title'     => esc_html( $title ),
					'collapsed' => 'settings' !== $section,
				),
				'options' => array(),
			);
		}

		// add the fields.
		foreach ( $switcher->get_widget_fields() as $name => $field ) {
			$option = array(
				'title'   => esc_html( $field['label'] ),
				'default' => $field['default'],
			);

			// add the description.
			if ( ! empty( $field['description'] ) ) {
				$option['description'] = esc_html( $field['description'] );
			}

			// set the type specific settings.
			switch ( $field['type'] ) {
				case 'select':
					$option['type']    = 'select';
					$option['options'] = $this->get_options( $field['options'] );
					break;
				case 'switch':
					$option['type']    = 'checkbox_switch';
					$option['layout']  = 'inline';
					$option['default'] = (bool) $field['default'];
					break;
				case 'color':
					$option['type'] = 'colorpicker';
					break;
				case 'size':
					$option['type']  = 'number_unit';
					$option['units'] = array_values( (array) $field['units'] );
					$option['min']   = 0;
					break;
				case 'objects':
					$option['type']        = 'select';
					$option['multiple']    = true;
					$option['filterable']  = true;
					$option['options']     = $this->get_options( $switcher->get_widget_object_options() );
					$option['default']     = array();
					$option['placeholder'] = esc_html( $field['label'] );
					break;
				default:
					$option['type'] = 'text';
					break;
			}

			// add the conditions.
			if ( ! empty( $field['condition'] ) && is_array( $field['condition'] ) ) {
				$option['dependency'] = array();
				foreach ( $field['condition'] as $controller => $allowed_values ) {
					$option['dependency'][] = array(
						'option' => $controller,
						'value'  => array_values( (array) $allowed_values ),
					);
				}
			}

			// add the option to its section.
			$section = isset( $field['section'] ) && is_string( $field['section'] ) ? $field['section'] : 'settings';
			if ( ! isset( $groups[ $section ] ) ) {
				$section = 'settings';
			}
			$groups[ $section ]['options'][ $name ] = $option;
		}

		// show a group only if the conditions of all its options match (if they are the same for all options).
		foreach ( $groups as $section => $group ) {
			$dependencies = array_column( $group['options'], 'dependency' );
			if ( ! empty( $dependencies ) && count( $dependencies ) === count( $group['options'] ) && 1 === count( array_unique( array_map( 'wp_json_encode', $dependencies ) ) ) ) {
				$groups[ $section ]['config']['dependency'] = $dependencies[0];
			}
		}

		// return the resulting list.
		return $groups;
	}

	/**
	 * Return the options for select fields in the format of Zion Builder.
	 *
	 * @param array<int|string,mixed> $options The options as value => label.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_options( array $options ): array {
		$list = array();
		foreach ( $options as $value => $label ) {
			$list[] = array(
				'id'   => (string) $value,
				'name' => (string) $label,
			);
		}
		return $list;
	}

	/**
	 * Return the output of our element.
	 *
	 * @param array<string,mixed> $values The values of the element.
	 * @param bool                $preview True if the element is rendered in the editor.
	 *
	 * @return string
	 */
	public function render( array $values, bool $preview ): string {
		return Switcher::get_instance()->render_widget( $values, 'zionbuilder', $preview );
	}
}
