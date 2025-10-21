<?php
/**
 * File to handle support for the page builder Divi.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// deny direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\Init;
use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\Plugin\Helper;

/**
 * Object to handle support for the page builder Divi.
 */
class Divi extends PageBuilder_Base {
	/**
	 * Instance of this object.
	 *
	 * @var ?Divi
	 */
	private static ?Divi $instance = null;

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
	public static function get_instance(): Divi {
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
		add_filter( 'et_builder_page_settings_modal_toggles', array( $this, 'add_toggle' ) );
		add_filter( 'et_builder_page_settings_definitions', array( $this, 'add_fields' ) );
		add_action( 'et_fb_enqueue_assets', array( $this, 'add_scripts_and_styles' ) );
		add_filter( 'easy_language_js_top', array( $this, 'change_js_hierarchy' ) );
	}

	/**
	 * Add our custom toggle.
	 *
	 * @param array<string> $toggles The list of toggles.
	 *
	 * @return array<string>
	 */
	public function add_toggle( array $toggles ): array {
		$toggles['easy-language-simplifications'] = __( 'Simplify texts', 'easy-language' );
		return $toggles;
	}

	/**
	 * Add fields to our custom toggle.
	 *
	 * @param array<string,mixed> $fields The list of fields.
	 *
	 * @return array<string,mixed>
	 */
	public function add_fields( array $fields ): array {
		$post_types = array();
		foreach ( Init::get_instance()->get_supported_post_types() as $post_type => $enabled ) {
			$post_types[] = $post_type;
		}

		// add our custom fields where user can change language-settings.
		$fields['easy-language-simplify-texts'] = array(
			'meta_key'             => 'easy_language_divi_languages',
			'type'                 => 'easy-language-language-options',
			'show_in_bb'           => true,
			'option_category'      => 'basic_option',
			'tab_slug'             => 'content',
			'toggle_slug'          => 'easy-language-simplifications',
			'depends_on_post_type' => $post_types,
		);

		// return list of fields.
		return $fields;
	}

	/**
	 * Add custom CSS- and JS-files for divi.
	 *
	 * @return void
	 */
	public function add_scripts_and_styles(): void {
		// add styles for language field in Divi-settings.
		wp_register_style(
			'easy-language-language-field',
			trailingslashit( plugin_dir_url( EASY_LANGUAGE ) ) . 'legacy-classes/Divi/build/style-language_field.css',
			array(),
			Helper::get_file_version( plugin_dir_path( EASY_LANGUAGE ) . 'legacy-classes/Divi/build/style-language_field.css' ),
		);
		wp_enqueue_style( 'easy-language-language-field' );

		// add script for language field in Divi-settings.
		wp_register_script(
			'easy-language-language-field',
			trailingslashit( plugin_dir_url( EASY_LANGUAGE ) ) . 'legacy-classes/Divi/build/language_field.js',
			array( 'jquery', 'et-dynamic-asset-helpers' ),
			Helper::get_file_version( plugin_dir_path( EASY_LANGUAGE ) . 'legacy-classes/Divi/build/language_field.js' ),
			true
		);

		// add individual variables for language-field-JS.
		wp_localize_script(
			'easy-language-language-field',
			'easyLanguageDiviData',
			array(
				'rest' => array(
					'endpoints' => array(
						'language_options' => esc_url_raw( rest_url( 'easy-language/v1/language-options' ) ),
					),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
				),
			)
		);

		// enable the language-field-JS.
		wp_enqueue_script( 'easy-language-language-field' );

		// add simplification-scripts for Divi.
		wp_enqueue_script(
			'easy-language-divi-simplifications',
			plugins_url( 'legacy-classes/Divi/divi.js', EASY_LANGUAGE ),
			array( 'jquery', 'react-dialog', 'wp-i18n' ),
			Helper::get_file_version( plugin_dir_path( EASY_LANGUAGE ) . 'legacy-classes/Divi/divi.js' ),
			true
		);

		// add php-vars to our simplifications-js-script.
		wp_localize_script(
			'easy-language-divi-simplifications',
			'easyLanguageDiviSimplificationJsVars',
			array(
				'ajax_url'                 => admin_url( 'admin-ajax.php' ),
				'run_simplification_nonce' => wp_create_nonce( 'easy-language-run-simplification-nonce' ),
			)
		);

		// embed the react-dialog-component.
		$script_asset_path = Helper::get_plugin_path() . 'vendor/threadi/react-dialog/build/index.asset.php';
		$script_asset      = require $script_asset_path;
		wp_enqueue_script(
			'react-dialog',
			Helper::get_plugin_url() . 'vendor/threadi/react-dialog/build/index.js',
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		$admin_css      = Helper::get_plugin_url() . 'vendor/threadi/react-dialog/build/style-index.css';
		$admin_css_path = Helper::get_plugin_path() . 'vendor/threadi/react-dialog/build/style-index.css';
		wp_enqueue_style(
			'react-dialog',
			$admin_css,
			array( 'wp-components' ),
			Helper::get_file_version( $admin_css_path )
		);
	}

	/**
	 * Add JS-top for scripts if Divi is enabled.
	 *
	 * @param string $js_top The top marker for JS.
	 *
	 * @return string
	 */
	public function change_js_hierarchy( string $js_top ): string {
		// bail if Divi is not enabled.
		if ( ! $this->is_active() ) {
			return $js_top;
		}

		// return the top-marker.
		return 'top.';
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		$is_divi = Helper::is_plugin_active( 'divi-builder/divi-builder.php' );
		$theme   = wp_get_theme();
		if ( 'Divi' === $theme->get( 'Name' ) ) {
			$is_divi = true;
		}
		if ( $theme->parent() && 'Divi' === $theme->parent()->get( 'Name' ) ) {
			$is_divi = true;
		}
		return $is_divi;
	}
}
