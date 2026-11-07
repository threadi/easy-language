<?php
/**
 * File to handle support for the page builder Bold Page Builder.
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
 * Object to handle support for the page builder Bold Page Builder.
 *
 * Adds the element "Language Switcher" to Bold Page Builder. Elements of Bold Page Builder are shortcodes handled by
 * global classes named like the shortcode (see legacy-classes/BoldBuilder/). The settings of the element are mapped
 * via bt_bb_map() from the field definitions of the switcher.
 */
class BoldBuilder extends PageBuilder_Base {
	/**
	 * The name of our element (the shortcode and the class name of the element).
	 */
	public const ELEMENT = 'easy_language_bb_switcher';

	/**
	 * Instance of this object.
	 *
	 * @var ?BoldBuilder
	 */
	private static ?BoldBuilder $instance = null;

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
	public static function get_instance(): BoldBuilder {
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
		// Bold Page Builder creates its own elements during "init" with priority 10.
		add_action( 'init', array( $this, 'register_element' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_icon_style' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_icon_style' ) );
	}

	/**
	 * Return the CSS class of the icon of our element.
	 *
	 * Bold Page Builder adds this class to the element in its editors and shows the icon in its pseudo element
	 * "::before" (like "bt_bb_icon_<shortcode>" of its own elements).
	 *
	 * @return string
	 */
	private function get_icon_class(): string {
		return 'bt_bb_icon_' . self::ELEMENT;
	}

	/**
	 * Add the styles for the icon of our element (the Easy Language logo) to the editors of Bold Page Builder.
	 *
	 * Bold Page Builder sets the icon class on the whole item (e.g. the row in the backend editor) and shows the
	 * icon font glyph in "::before". So the generic class "easy-language-builder-icon" can not be used: we replace
	 * the glyph with the logo via an inline style on the registered style "easy-language-builder-icon".
	 *
	 * @return void
	 */
	public function enqueue_icon_style(): void {
		// bail if Bold Page Builder is not active.
		if ( ! $this->is_active() ) {
			return;
		}

		// bail if this is not an editor of Bold Page Builder (backend: edit page of posts; frontend: editors only).
		if ( is_admin() ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen instanceof \WP_Screen || 'post' !== $screen->base ) {
				return;
			}
		} elseif ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}

		// add the styles.
		$selector = '.bt_bb_icon.' . $this->get_icon_class() . '::before';
		wp_enqueue_style( 'easy-language-builder-icon' );
		wp_add_inline_style(
			'easy-language-builder-icon',
			$selector . '{content:"" !important;background-image:url(' . esc_url( Helper::get_plugin_url() . 'gfx/easy-language-icon.svg' ) . ') !important;background-position:center !important;background-repeat:no-repeat !important;background-size:70% !important;background-color:#fff !important;}'
		);
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'bold-page-builder/bold-builder.php' ) || class_exists( 'BT_BB_Element' );
	}

	/**
	 * Register our element in Bold Page Builder.
	 *
	 * The element registers its shortcode and its mapping itself (see BT_BB_Basic_Element::init()).
	 *
	 * @return void
	 */
	public function register_element(): void {
		// bail if Bold Page Builder is not active.
		if ( ! $this->is_active() || ! class_exists( 'BT_BB_Element' ) || ! function_exists( 'bt_bb_map' ) ) {
			return;
		}

		// load the element class.
		require_once Helper::get_plugin_path() . 'legacy-classes/BoldBuilder/' . self::ELEMENT . '.php';

		// create the element.
		$class_name = self::ELEMENT;
		if ( class_exists( $class_name ) ) {
			new $class_name();
		}
	}

	/**
	 * Return the mapping of our element for bt_bb_map().
	 *
	 * The settings are generated from the field definitions of the switcher. As Bold Page Builder does not support
	 * conditional fields, the conditions are noted in the descriptions. The sections are used as tabs.
	 *
	 * @return array<string,mixed>
	 */
	public function get_element_mapping(): array {
		$switcher = Switcher::get_instance();
		$fields   = $switcher->get_widget_fields();
		$sections = $switcher->get_widget_sections();

		// create the params.
		$params = array();
		foreach ( $fields as $name => $field ) {
			$param = array(
				'param_name' => $name,
				'heading'    => $field['label'] ?? $name,
			);

			// use the sections as tabs (the first section is the default tab "General") and show its values in the editor.
			$section = isset( $field['section'] ) ? (string) $field['section'] : 'settings';
			if ( 'settings' !== $section ) {
				$param['group'] = $sections[ $section ] ?? $section;
			} else {
				$param['preview'] = true;
			}

			// add the field type specific settings.
			switch ( $field['type'] ) {
				case 'select':
					$param['type']    = 'dropdown';
					$param['value']   = $this->get_dropdown_values( is_array( $field['options'] ?? null ) ? $field['options'] : array() );
					$param['default'] = (string) $field['default'];
					break;

				case 'switch':
					$param['type']  = 'checkbox';
					$param['value'] = array( __( 'Yes', 'easy-language' ) => 'yes' );
					if ( ! empty( $field['default'] ) ) {
						$param['default'] = 'yes';
					}
					break;

				case 'color':
					$param['type'] = 'colorpicker';
					break;

				case 'size':
					$param['type']        = 'textfield';
					$param['placeholder'] = __( 'e.g. 24px', 'easy-language' );
					break;

				case 'objects':
					// Bold Page Builder has no multiple select: use a comma separated list.
					$param['type']        = 'textfield';
					$param['placeholder'] = 'post:12,term:5';
					break;

				default:
					$param['type'] = 'textfield';
			}

			// add the description incl. the conditions.
			$description = $this->get_description( $field, $fields );
			if ( '' !== $description ) {
				$param['description'] = $description;
			}

			// add the param.
			$params[] = $param;
		}

		// return the resulting mapping.
		return array(
			'name'        => __( 'Language Switcher', 'easy-language' ),
			'description' => __( 'Easy Language', 'easy-language' ),
			'icon'        => $this->get_icon_class(),
			'params'      => $params,
		);
	}

	/**
	 * Return the values of a dropdown in the format of Bold Page Builder (label => value).
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
	 * Return the description of a field incl. a hint for its conditions.
	 *
	 * @param array<string,mixed>               $field The field definition.
	 * @param array<string,array<string,mixed>> $fields All field definitions.
	 *
	 * @return string
	 */
	private function get_description( array $field, array $fields ): string {
		$descriptions = array();

		// add the description of the field.
		if ( ! empty( $field['description'] ) ) {
			$descriptions[] = (string) $field['description'];
		}

		// add the description for the objects field.
		if ( 'objects' === $field['type'] ) {
			$descriptions[] = __( 'Comma separated list of pages and terms, e.g. post:12,term:5.', 'easy-language' );
		}

		// add the conditions.
		foreach ( isset( $field['condition'] ) && is_array( $field['condition'] ) ? $field['condition'] : array() as $controller => $allowed_values ) {
			// bail if the controlling field does not exist.
			if ( ! isset( $fields[ $controller ] ) ) {
				continue;
			}

			// get the labels of the allowed values.
			$labels = array();
			foreach ( (array) $allowed_values as $allowed_value ) {
				if ( is_bool( $allowed_value ) ) {
					$labels[] = $allowed_value ? __( 'Yes', 'easy-language' ) : __( 'No', 'easy-language' );
					continue;
				}
				$labels[] = (string) ( $fields[ $controller ]['options'][ $allowed_value ] ?? $allowed_value );
			}

			/* translators: %1$s: label of a setting, %2$s: list of values. */
			$descriptions[] = sprintf( __( 'Only used if "%1$s" is set to "%2$s".', 'easy-language' ), $fields[ $controller ]['label'] ?? $controller, implode( '", "', $labels ) );
		}

		// return the resulting description.
		return implode( ' ', $descriptions );
	}

	/**
	 * Return the output of the switcher for the attributes of our shortcode.
	 *
	 * @param array<string,mixed> $atts The attributes of the shortcode.
	 *
	 * @return string
	 */
	public function render_switcher( array $atts ): string {
		$switcher = Switcher::get_instance();

		// collect the values of our fields (Bold Page Builder encodes some characters in attributes).
		$values = array();
		foreach ( $switcher->get_widget_fields() as $name => $field ) {
			// unchecked switches are saved as empty value (otherwise a default of true could not be disabled).
			if ( isset( $atts[ $name ] ) && '' === $atts[ $name ] && 'switch' === $field['type'] ) {
				$values[ $name ] = false;
				continue;
			}

			// bail if no value is set.
			if ( ! isset( $atts[ $name ] ) || '' === $atts[ $name ] ) {
				continue;
			}
			$values[ $name ] = is_string( $atts[ $name ] ) ? html_entity_decode( str_ireplace( '``', '"', $atts[ $name ] ), ENT_QUOTES, 'UTF-8' ) : $atts[ $name ];
		}

		// return the output in frontend.
		if ( ! $this->is_preview() ) {
			return $switcher->render_widget( $values, 'bold', false );
		}

		// the front end editor renders the preview via AJAX without a queried object: use the edited post for it.
		global $wp_query;
		$original_query = $wp_query;
		$post_id        = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is checked by Bold Page Builder.
		$post           = get_post( $post_id );
		if ( $post instanceof WP_Post && 0 === get_queried_object_id() ) {
			$wp_query                    = new WP_Query(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_query->queried_object    = $post;
			$wp_query->queried_object_id = $post->ID;
		}

		// get the output.
		$output = $switcher->render_widget( $values, 'bold', true );

		// restore the original query.
		$wp_query = $original_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// return the output.
		return $output;
	}

	/**
	 * Return whether the element is rendered as preview in the front end editor of Bold Page Builder.
	 *
	 * The front end editor renders changed elements via the AJAX action "bt_bb_fe_get_html".
	 *
	 * @return bool
	 */
	private function is_preview(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is checked by Bold Page Builder.
		return wp_doing_ajax() && isset( $_POST['action'] ) && 'bt_bb_fe_get_html' === $_POST['action'];
	}
}
