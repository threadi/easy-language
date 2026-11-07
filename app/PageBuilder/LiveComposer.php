<?php
/**
 * File to handle support for the page builder Live Composer.
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
 * Object to handle support for the page builder Live Composer.
 *
 * Adds the language switcher as native Live Composer module.
 */
class LiveComposer extends PageBuilder_Base {
	/**
	 * The ID (class name) of our module in Live Composer.
	 */
	public const MODULE_ID = 'Easy_Language_Live_Composer_Switcher';

	/**
	 * Prefix for the option IDs of our module (prevents collisions with options of other modules in the editor).
	 */
	public const PREFIX = 'el_switcher_';

	/**
	 * Instance of this object.
	 *
	 * @var ?LiveComposer
	 */
	private static ?LiveComposer $instance = null;

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
	public static function get_instance(): LiveComposer {
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
		add_action( 'dslc_hook_register_modules', array( $this, 'register_module' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_editor_script' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_preview_style' ) );
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'live-composer-page-builder/ds-live-composer.php' ) && function_exists( 'dslc_register_module' );
	}

	/**
	 * Register our module in Live Composer.
	 *
	 * @return void
	 */
	public function register_module(): void {
		// bail if Live Composer is not active.
		if ( ! $this->is_active() || ! class_exists( 'DSLC_Module' ) || ! function_exists( 'dslc_register_module' ) ) {
			return;
		}

		// load the module class.
		require_once Helper::get_plugin_path() . 'legacy-classes/LiveComposer/Easy_Language_Live_Composer_Switcher.php';

		// register it.
		dslc_register_module( self::MODULE_ID );
	}

	/**
	 * Return the options of our module in the format of Live Composer.
	 *
	 * The options are generated from the central field definitions of the switcher.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_module_options(): array {
		$switcher = Switcher::get_instance();
		$sections = $switcher->get_widget_sections();

		// add the default option of Live Composer to show the module only on specific devices.
		$options = array(
			array(
				'label'   => __( 'Show On', 'easy-language' ),
				'id'      => 'css_show_on',
				'std'     => 'desktop tablet phone',
				'type'    => 'checkbox',
				'choices' => array(
					array(
						'label' => __( 'Desktop', 'easy-language' ),
						'value' => 'desktop',
					),
					array(
						'label' => __( 'Tablet', 'easy-language' ),
						'value' => 'tablet',
					),
					array(
						'label' => __( 'Phone', 'easy-language' ),
						'value' => 'phone',
					),
				),
				'section' => 'functionality',
				'tab'     => $sections['settings'] ?? '',
			),
		);

		// add our fields.
		foreach ( $switcher->get_widget_fields() as $name => $field ) {
			$section = isset( $field['section'] ) && is_string( $field['section'] ) ? $field['section'] : 'settings';
			$option  = array(
				'label'   => esc_html( $field['label'] ),
				'id'      => self::PREFIX . $name,
				'std'     => '',
				'type'    => 'text',
				'section' => 'design' === $section ? 'styling' : 'functionality',
				'tab'     => esc_html( $sections[ $section ] ?? $section ),
			);

			// add the description as help text.
			if ( ! empty( $field['description'] ) ) {
				$option['help'] = esc_html( $field['description'] );
			}

			// set the type specific settings.
			switch ( $field['type'] ) {
				case 'select':
					$option['type']    = 'select';
					$option['std']     = (string) $field['default'];
					$option['choices'] = array();
					foreach ( $field['options'] as $value => $label ) {
						$option['choices'][] = array(
							'label' => esc_html( $label ),
							'value' => esc_attr( (string) $value ),
						);
					}
					break;
				case 'switch':
					$option['type']    = 'checkbox';
					$option['std']     = $field['default'] ? 'yes' : '';
					$option['choices'] = array(
						array(
							'label' => esc_html__( 'Yes', 'easy-language' ),
							'value' => 'yes',
						),
					);
					break;
				case 'color':
					$option['type']              = 'color';
					$option['refresh_on_change'] = true;
					break;
				case 'size':
					$option['std'] = is_string( $field['default'] ) ? $field['default'] : '';
					$help          = isset( $option['help'] ) ? $option['help'] . ' ' : '';
					/* translators: %1$s will be replaced by a list of CSS units. */
					$option['help'] = $help . esc_html( sprintf( __( 'Number with one of these units: %1$s', 'easy-language' ), implode( ', ', (array) $field['units'] ) ) );
					break;
				case 'objects':
					$option['help'] = esc_html__( 'Comma separated list of pages and terms, e.g. post:12,term:5.', 'easy-language' );
					break;
			}

			// add the option to the list.
			$options[] = $option;
		}

		// return the resulting list.
		return $options;
	}

	/**
	 * Return the output of our module.
	 *
	 * @param array<string,mixed> $options The options of the module from Live Composer.
	 *
	 * @return string
	 */
	public function render( array $options ): string {
		// collect the values (Live Composer sets missing options to false).
		$values = array();
		foreach ( Switcher::get_instance()->get_widget_fields() as $name => $field ) {
			// get the value.
			$value = $options[ self::PREFIX . $name ] ?? null;

			// unchecked switches are saved as false (otherwise a default of true could not be disabled).
			if ( false === $value && 'switch' === $field['type'] ) {
				$values[ $name ] = false;
				continue;
			}

			// bail if no value is set.
			if ( is_null( $value ) || false === $value ) {
				continue;
			}

			// trim strings (checkboxes are saved as space separated lists).
			if ( is_string( $value ) ) {
				$value = trim( $value );
			}

			// add the value.
			$values[ $name ] = $value;
		}

		// return the output.
		return Switcher::get_instance()->render_widget( $values, 'live-composer', $this->is_preview( $options ) );
	}

	/**
	 * Return whether the module is rendered in the editor of Live Composer.
	 *
	 * @param array<string,mixed> $options The options of the module.
	 *
	 * @return bool
	 */
	private function is_preview( array $options ): bool {
		// modules rendered via AJAX are editor previews (only for users which could edit contents).
		if ( wp_doing_ajax() && ! isset( $options['module_render_nonajax'] ) && current_user_can( 'edit_posts' ) ) {
			return true;
		}

		// check if the editor is active.
		return function_exists( 'dslc_is_editor_active' ) && dslc_is_editor_active( 'access' );
	}

	/**
	 * Add the script for the conditions of our options in the editor of Live Composer.
	 *
	 * Live Composer can show options only depending on exactly one value of another option. Our conditions
	 * allow multiple values, so we handle them with an own script which uses the same markup as Live Composer.
	 *
	 * @param string $hook The actual hook.
	 *
	 * @return void
	 */
	public function add_editor_script( string $hook ): void {
		// bail if this is not the editor of Live Composer.
		if ( 'toplevel_page_livecomposer_editor' !== $hook || ! $this->is_active() ) {
			return;
		}

		// collect the conditions.
		$conditions = array();
		foreach ( Switcher::get_instance()->get_widget_fields() as $name => $field ) {
			if ( empty( $field['condition'] ) || ! is_array( $field['condition'] ) ) {
				continue;
			}
			foreach ( $field['condition'] as $controller => $allowed_values ) {
				$conditions[ self::PREFIX . $name ][ self::PREFIX . $controller ] = array_map( 'strval', (array) $allowed_values );
			}
		}

		// add the style for the icon of our module.
		$this->add_icon_style();

		// add the script.
		wp_register_script( 'easy-language-live-composer', false, array( 'jquery', 'dslc-editor-backend-js' ), EASY_LANGUAGE_VERSION, true );
		wp_enqueue_script( 'easy-language-live-composer' );
		wp_add_inline_script( 'easy-language-live-composer', 'window.easyLanguageLiveComposer = ' . wp_json_encode( $conditions ) . ';' . $this->get_editor_script() );
	}

	/**
	 * Add the style for the icon of our module in the preview of the editor (used e.g. while dragging the module).
	 *
	 * @return void
	 */
	public function add_preview_style(): void {
		// bail if the editor of Live Composer is not active.
		if ( ! $this->is_active() || ! function_exists( 'dslc_is_editor_active' ) || ! dslc_is_editor_active( 'access' ) ) {
			return;
		}

		// add the style.
		$this->add_icon_style();
	}

	/**
	 * Add the style for the icon of our module: the Easy Language logo.
	 *
	 * Live Composer only supports names of font icons, which it uses as class "dslc-icon-<name>". So we use the name
	 * "easy-language-builder-icon" and add the styles of the class "easy-language-builder-icon" for the resulting class.
	 *
	 * @return void
	 */
	private function add_icon_style(): void {
		wp_enqueue_style( 'easy-language-builder-icon' );
		wp_add_inline_style(
			'easy-language-builder-icon',
			'.dslc-icon-easy-language-builder-icon { display: inline-block !important; width: 1em !important; height: 1em !important; background: url(' . esc_url( Helper::get_plugin_url() . 'gfx/easy-language-icon.svg' ) . ') center / contain no-repeat !important; vertical-align: middle; }
			.dslca-module .dslca-icon.dslc-icon-easy-language-builder-icon { display: block !important; margin: 10px auto 11px; }
			.dslc-icon-easy-language-builder-icon::before, .dslc-icon-easy-language-builder-icon::after { content: none !important; display: none !important; }'
		);
	}

	/**
	 * Return the script to handle the conditions of our options.
	 *
	 * @return string
	 */
	private function get_editor_script(): string {
		return <<<'JS'
( function( $ ) {
	var conditions = window.easyLanguageLiveComposer || {};
	function getValue( id ) {
		var field = $( '.dslca-module-edit-option .dslca-module-edit-field[data-id="' + id + '"]' );
		if ( field.is( ':checkbox' ) ) {
			return field.is( ':checked' ) ? field.val() : '';
		}
		return String( field.val() );
	}
	function update() {
		var tab = $( '.dslca-module-edit-options-tab-hook.dslca-active' ).data( 'id' );
		$.each( conditions, function( target, rules ) {
			var option = $( '.dslca-module-edit-option[data-id="' + target + '"]' );
			if ( ! option.length ) {
				return;
			}
			var show = true;
			$.each( rules, function( controller, values ) {
				if ( values.indexOf( getValue( controller ) ) === -1 ) {
					show = false;
				}
			} );
			option.addClass( 'dependent' ).toggleClass( 'dep-show', show ).toggleClass( 'dep-hide', ! show );
			if ( ! show ) {
				option.hide();
			} else if ( tab === option.data( 'tab' ) ) {
				option.show();
			}
		} );
	}
	$( document ).on( 'change dslc-init-deps', '.dslca-module-edit-option .dslca-module-edit-field[data-id^="el_switcher_"]', update );
} )( jQuery );
JS;
	}
}
