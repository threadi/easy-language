<?php
/**
 * File to handle support for the page builder Nimble Builder.
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
 * Object to handle support for the page builder Nimble Builder.
 *
 * Adds the language switcher as native Nimble Builder module.
 */
class Nimble extends PageBuilder_Base {
	/**
	 * The type of our module in Nimble Builder.
	 */
	public const MODULE_TYPE = 'easy_language_switcher_module';

	/**
	 * Name of the function of Nimble Builder which returns its framework object.
	 *
	 * @var string
	 */
	private string $framework_function = '\Nimble\CZR_Fmk_Base';

	/**
	 * Instance of this object.
	 *
	 * @var ?Nimble
	 */
	private static ?Nimble $instance = null;

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
	public static function get_instance(): Nimble {
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
		// register the module after post types and taxonomies are registered (needed for the visibility objects).
		add_action( 'init', array( $this, 'register_module' ), 100 );

		// add the module to the list of modules in the editor.
		add_filter( 'sek_get_module_collection', array( $this, 'add_module_to_collection' ) );

		// add the options for the visibility objects in the editor.
		add_filter( 'nimble-sek-localized-customizer-control-params', array( $this, 'add_object_choices' ) );

		// add the script for the module in the editor (the customizer).
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'add_editor_script' ), 30 );
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'nimble-builder/nimble-builder.php' ) && function_exists( '\Nimble\CZR_Fmk_Base' );
	}

	/**
	 * Register our module in Nimble Builder.
	 *
	 * We register it on every request as Nimble Builder registers only the modules used on the actual page in frontend.
	 *
	 * @return void
	 */
	public function register_module(): void {
		// bail if Nimble Builder is not active.
		if ( ! $this->is_active() ) {
			return;
		}

		// get the framework object of Nimble Builder.
		$callback = $this->framework_function;
		if ( ! is_callable( $callback ) ) {
			return;
		}
		$fmk = $callback();

		// bail if it could not be loaded.
		if ( ! is_object( $fmk ) || ! method_exists( $fmk, 'czr_pre_register_dynamic_module' ) ) {
			return;
		}

		// register the module.
		call_user_func( array( $fmk, 'czr_pre_register_dynamic_module' ), $this->get_module_params() );
	}

	/**
	 * Return the registration params of our module.
	 *
	 * The inputs are generated from the central field definitions of the switcher.
	 *
	 * @return array<string,mixed>
	 */
	private function get_module_params(): array {
		$switcher = Switcher::get_instance();
		$sections = $switcher->get_widget_sections();

		// collect the inputs.
		$inputs       = array();
		$last_section = '';
		foreach ( $switcher->get_widget_fields() as $name => $field ) {
			$input = array(
				'title'          => esc_html( $field['label'] ),
				'default'        => $field['default'],
				'refresh_markup' => true,
				'title_width'    => 'width-100',
				'width-100'      => true,
			);

			// add the description.
			if ( ! empty( $field['description'] ) ) {
				$input['notice_after'] = esc_html( $field['description'] );
			}

			// set the type specific settings.
			switch ( $field['type'] ) {
				case 'select':
					$input['input_type'] = 'simpleselect';
					$input['choices']    = $this->get_choices( $field['options'] );
					$input['default']    = (string) $field['default'];
					break;
				case 'switch':
					$input['input_type']  = 'nimblecheck';
					$input['default']     = (bool) $field['default'];
					$input['title_width'] = 'width-80';
					$input['input_width'] = 'width-20';
					unset( $input['width-100'] );
					break;
				case 'color':
					$input['input_type'] = 'wp_color_alpha';
					$input['default']    = '';
					break;
				case 'objects':
					// the choices are added only in the editor, see add_object_choices().
					$input['input_type'] = 'multiselect';
					$input['choices']    = array();
					$input['default']    = array();
					break;
				default:
					$input['input_type']  = 'text';
					$input['placeholder'] = '';
					$input['default']     = is_string( $field['default'] ) ? $field['default'] : '';
					if ( 'size' === $field['type'] ) {
						$notice = isset( $input['notice_after'] ) ? $input['notice_after'] . ' ' : '';
						/* translators: %1$s will be replaced by a list of CSS units. */
						$input['notice_after'] = $notice . esc_html( sprintf( __( 'Number with one of these units: %1$s', 'easy-language' ), implode( ', ', (array) $field['units'] ) ) );
					}
					break;
			}

			// add the title of the section before the first input of each section.
			$section = isset( $field['section'] ) && is_string( $field['section'] ) ? $field['section'] : 'settings';
			if ( $section !== $last_section ) {
				$input['html_before'] = '<hr/><h3>' . esc_html( $sections[ $section ] ?? $section ) . '</h3>';
				$last_section         = $section;
			}

			// add the input.
			$inputs[ $name ] = $input;
		}

		// return the params.
		return array(
			'dynamic_registration' => true,
			'module_type'          => self::MODULE_TYPE,
			'name'                 => __( 'Language Switcher', 'easy-language' ),
			'tmpl'                 => array(
				'item-inputs' => $inputs,
			),
			'render_tmpl_path'     => Helper::get_plugin_path() . 'legacy-classes/Nimble/switcher_module_tmpl.php',
		);
	}

	/**
	 * Add the choices for inputs of the type "objects" to the module data for the editor.
	 *
	 * They are only loaded here to prevent the queries on every other request.
	 *
	 * @param array<string,mixed> $params The params for the editor.
	 *
	 * @return array<string,mixed>
	 */
	public function add_object_choices( array $params ): array {
		// bail if our module is not registered.
		if ( ! isset( $params['registeredModules'][ self::MODULE_TYPE ]['tmpl']['item-inputs'] ) || ! is_array( $params['registeredModules'][ self::MODULE_TYPE ]['tmpl']['item-inputs'] ) ) {
			return $params;
		}

		// add the choices to each input of the type "objects".
		$switcher = Switcher::get_instance();
		foreach ( $switcher->get_widget_fields() as $name => $field ) {
			if ( 'objects' !== $field['type'] || ! isset( $params['registeredModules'][ self::MODULE_TYPE ]['tmpl']['item-inputs'][ $name ] ) ) {
				continue;
			}
			$params['registeredModules'][ self::MODULE_TYPE ]['tmpl']['item-inputs'][ $name ]['choices'] = $this->get_choices( $switcher->get_widget_object_options() );
		}

		// return the resulting params.
		return $params;
	}

	/**
	 * Return the choices for select inputs (Nimble Builder uses them as HTML).
	 *
	 * @param array<int|string,mixed> $options The options as value => label.
	 *
	 * @return array<string,string>
	 */
	private function get_choices( array $options ): array {
		$choices = array();
		foreach ( $options as $value => $label ) {
			$choices[ (string) $value ] = esc_html( (string) $label );
		}
		return $choices;
	}

	/**
	 * Add our module to the list of modules in the editor of Nimble Builder.
	 *
	 * @param array<int,array<string,mixed>> $collection The list of modules.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function add_module_to_collection( array $collection ): array {
		// bail if Nimble Builder is not active.
		if ( ! $this->is_active() ) {
			return $collection;
		}

		// add our module.
		$collection[] = array(
			'content-type' => 'module',
			'content-id'   => self::MODULE_TYPE,
			'title'        => __( 'Language Switcher', 'easy-language' ),
			'icon'         => Helper::get_plugin_url() . 'gfx/easy-language-icon.png',
		);

		// return the resulting list.
		return $collection;
	}

	/**
	 * Return the output of our module.
	 *
	 * @param array<string,mixed> $values The values of the module.
	 *
	 * @return string
	 */
	public function render( array $values ): string {
		// collect the values of our fields.
		$settings = array();
		foreach ( Switcher::get_instance()->get_widget_fields() as $name => $field ) {
			if ( ! isset( $values[ $name ] ) ) {
				continue;
			}
			$settings[ $name ] = $values[ $name ];
		}

		// return the output.
		return Switcher::get_instance()->render_widget( $settings, 'nimble', $this->is_preview() );
	}

	/**
	 * Return whether the module is rendered in the editor of Nimble Builder (the customizer preview).
	 *
	 * @return bool
	 */
	private function is_preview(): bool {
		// modules are rendered via AJAX in the editor (only for users which could edit contents).
		if ( wp_doing_ajax() && current_user_can( 'edit_posts' ) ) {
			return true;
		}

		// check if the customizer is used.
		return is_customize_preview();
	}

	/**
	 * Add the script for our module in the editor of Nimble Builder.
	 *
	 * Nimble Builder needs a description of each module in its JavaScript. It also handles the conditions of
	 * the inputs (which inputs are visible) only in JavaScript, so we add them here generic from our field definitions.
	 *
	 * @return void
	 */
	public function add_editor_script(): void {
		// bail if Nimble Builder is not active.
		if ( ! $this->is_active() ) {
			return;
		}

		// collect the conditions.
		$conditions = array();
		foreach ( Switcher::get_instance()->get_widget_fields() as $name => $field ) {
			if ( empty( $field['condition'] ) || ! is_array( $field['condition'] ) ) {
				continue;
			}
			foreach ( $field['condition'] as $controller => $allowed_values ) {
				$conditions[ $name ][ $controller ] = array_map( 'strval', (array) $allowed_values );
			}
		}

		// add the script.
		wp_register_script( 'easy-language-nimble', false, array( 'customize-controls', 'jquery', 'underscore' ), EASY_LANGUAGE_VERSION, true );
		wp_enqueue_script( 'easy-language-nimble' );
		wp_add_inline_script(
			'easy-language-nimble',
			'window.easyLanguageNimble = ' . wp_json_encode(
				array(
					'module'     => self::MODULE_TYPE,
					'conditions' => $conditions,
				)
			) . ';' . $this->get_editor_script()
		);
	}

	/**
	 * Return the script for our module in the editor.
	 *
	 * @return string
	 */
	private function get_editor_script(): string {
		return <<<'JS'
( function( api, $, _ ) {
	var config = window.easyLanguageNimble;
	function register() {
		if ( ! api.czr_sektions || ! api.CZRDynModule || ! api.CZRItem ) {
			return false;
		}
		var Constructor = {
			initialize: function( id, options ) {
				var module = this;
				module.itemConstructor = api.CZRItem.extend( module.CZRItemConstructor || {} );
				api.CZRDynModule.prototype.initialize.call( module, id, options );
			},
			CZRItemConstructor: {
				ready: function() {
					var item = this;
					item.inputCollection.bind( function( col ) {
						if ( _.isEmpty( col ) ) {
							return;
						}
						item.setInputVisibilityDeps();
					} );
					api.CZRItem.prototype.ready.call( item );
				},
				setInputVisibilityDeps: function() {
					var item = this;
					var isVisible = function( target ) {
						var visible = true;
						_.each( config.conditions[ target ], function( values, controller ) {
							if ( item.czr_Input.has( controller ) && ! _.contains( values, String( item.czr_Input( controller )() ) ) ) {
								visible = false;
							}
						} );
						return visible;
					};
					_.each( config.conditions, function( rules, target ) {
						if ( ! item.czr_Input.has( target ) ) {
							return;
						}
						_.each( rules, function( values, controller ) {
							if ( ! item.czr_Input.has( controller ) ) {
								return;
							}
							api.czr_sektions.scheduleVisibilityOfInputId.call( item.czr_Input( controller ), target, function() {
								return isVisible( target );
							} );
						} );
					} );
				}
			}
		};
		api.czrModuleMap = api.czrModuleMap || {};
		api.czrModuleMap[ config.module ] = {
			mthds: Constructor,
			crud: false,
			name: api.czr_sektions.getRegisteredModuleProperty( config.module, 'name' ),
			has_mod_opt: false,
			ready_on_section_expanded: false,
			ready_on_control_event: 'sek-accordion-expanded',
			defaultItemModel: api.czr_sektions.getDefaultItemModelFromRegisteredModuleData( config.module )
		};
		return true;
	}
	api.bind( 'ready', function() {
		var attempts = 0;
		var timer = setInterval( function() {
			attempts++;
			if ( register() || attempts > 300 ) {
				clearInterval( timer );
			}
		}, 100 );
	} );
} )( wp.customize, jQuery, _ );
JS;
	}
}
