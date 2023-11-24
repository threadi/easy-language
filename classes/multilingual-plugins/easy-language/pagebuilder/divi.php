<?php
/**
 * File to add Divi as supported page builder.
 *
 * @package easy-language
 */

use easyLanguage\Helper;
use easyLanguage\Multilingual_plugins\Easy_Language\Init;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser\Divi;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add Undetected-object to list of supported pagebuilder.
 * Only if divi is active.
 *
 * @param array $pagebuilder_list List of supported pagebuilder.
 *
 * @return array
 */
function easy_language_pagebuilder_divi( array $pagebuilder_list ): array {
	// add divi to list.
	$pagebuilder_list[] = Divi::get_instance();

	// return resulting list of page-builders.
	return $pagebuilder_list;
}
add_filter( 'easy_language_pagebuilder', 'easy_language_pagebuilder_divi', 2000 );

/**
 * Add our custom toggle.
 *
 * @param array $toggles The list of toggles.
 *
 * @return array
 */
function easy_language_divi_add_toggle( array $toggles ): array {
	$toggles['easy-language-simplifications'] = __( 'Simplify texts', 'easy-language' );
	return $toggles;
}
add_filter( 'et_builder_page_settings_modal_toggles', 'easy_language_divi_add_toggle' );

/**
 * Add fields to our custom toggle.
 *
 * @param array $fields The list of fields.
 *
 * @return array
 */
function easy_language_divi_add_fields( array $fields ): array {
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
add_filter( 'et_builder_page_settings_definitions', 'easy_language_divi_add_fields' );

/**
 * Add custom JS-file for divi.
 *
 * @return void
 */
function easy_language_divi_add_scripts(): void {
	// add styles for language field in Divi-settings.
	wp_register_style(
		'easy-language-language-field',
		trailingslashit( plugin_dir_url( EASY_LANGUAGE ) ) . 'classes/multilingual-plugins/easy-language/pagebuilder/divi/build/style-language_field.css',
		array(),
		filemtime( plugin_dir_path( EASY_LANGUAGE ) . '/classes/multilingual-plugins/easy-language/pagebuilder/divi/build/style-language_field.css' ),
	);
	wp_enqueue_style( 'easy-language-language-field' );

	// add script for language field in Divi-settings.
	wp_register_script(
		'easy-language-language-field',
		trailingslashit( plugin_dir_url( EASY_LANGUAGE ) ) . 'classes/multilingual-plugins/easy-language/pagebuilder/divi/build/language_field.js',
		array( 'jquery', 'et-dynamic-asset-helpers' ),
		filemtime( plugin_dir_path( EASY_LANGUAGE ) . '/classes/multilingual-plugins/easy-language/pagebuilder/divi/build/language_field.js' ),
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
		plugins_url( '/classes/multilingual-plugins/easy-language/admin/divi.js', EASY_LANGUAGE ),
		array( 'jquery', 'react-dialog', 'wp-i18n' ),
		filemtime( plugin_dir_path( EASY_LANGUAGE ) . '/classes/multilingual-plugins/easy-language/admin/divi.js' ),
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
		filemtime( $admin_css_path )
	);
}
add_action( 'et_fb_enqueue_assets', 'easy_language_divi_add_scripts' );
