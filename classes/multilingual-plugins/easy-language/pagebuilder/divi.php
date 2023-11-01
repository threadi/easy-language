<?php
/**
 * File to add Divi as supported page builder.
 *
 * @package easy-language
 */

use easyLanguage\Apis;
use easyLanguage\Helper;
use easyLanguage\Languages;
use easyLanguage\Multilingual_Plugins;
use easyLanguage\Multilingual_plugins\Easy_Language\Init;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser\Divi;
use easyLanguage\Multilingual_plugins\Easy_Language\Post_Object;

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
 * Add custom admin bar as language-switcher in divi.
 *
 * @param WP_Admin_Bar $admin_bar The admin-bar-object.
 *
 * @return void
 */
function easy_language_pagebuilder_divi_admin_bar( WP_Admin_Bar $admin_bar ): void {
	// bail if user has no translation capabilities.
	if ( false === current_user_can( 'edit_el_simplifier' ) ) {
		return;
	}

	if ( function_exists( 'et_core_is_fb_enabled' ) && Divi::get_instance()->is_active() && et_core_is_fb_enabled() ) {
		// get the active languages.
		$target_languages = Languages::get_instance()->get_active_languages();

		// if actual language is not supported as source language, do not show anything.
		if ( empty( $target_languages ) ) {
			return;
		}

		// get our own object for the requested object.
		$post_object = new Post_Object( get_the_ID() );

		if ( $post_object->is_translatable() ) {
			// set the actual object as original-object.
			$original_post_object = $post_object;
		} else {
			// get original as object.
			$original_post_object = new Post_Object( $post_object->get_original_object_as_int() );
		}

		// secure the menu ID.
		$id = 'easy-language-divi-translate-button';

		// get object type name.
		$object_type_name = Helper::get_objekt_type_name( $original_post_object );

		// add not clickable main menu where all languages will be added as dropdown-items.
		$admin_bar->add_menu(
			array(
				'id'     => $id,
				'parent' => null,
				'group'  => null,
				/* translators: %1$s will be replaced by the object-name (e.g. page oder post) */
				'title'  => sprintf( __( 'Simplify this %1$s', 'easy-language' ), esc_html( $object_type_name ) ),
				'href'   => '',
			)
		);

		// add sub-entry for each possible target language.
		Helper::generate_admin_bar_language_menu( $id, $admin_bar, $target_languages, $post_object, $object_type_name );

		// add option to translate this page via api.
		// show translate-button if this is not the original post.
		if ( $post_object->get_id() !== $original_post_object->get_id() ) {
			// check if API for automatic translation is active.
			$api_obj = Apis::get_instance()->get_active_api();
			if ( false !== $api_obj ) {
				$admin_bar->add_menu(
					array(
						'id'     => $id . '-translate-object',
						'parent' => $id,
						/* translators: %1$s will be replaced by the API-title */
						'title'  => sprintf( __( 'Simplify with %1$s.', 'easy-language' ), $api_obj->get_title() ),
						'href'   => $post_object->get_simplification_via_api_link(),
						'meta'   => array(
							'class' => 'easy-language-translate-object'
						),
					)
				);
			} elseif ( current_user_can( 'manage_options' ) ) {
				$admin_bar->add_menu(
					array(
						'id'     => $id . '-translate-object',
						'parent' => $id,
						'title'  => __( 'No simplification-API active.', 'easy-language' ),
						'href'   => '',
					)
				);
			}
		}
	}
}
add_action( 'admin_bar_menu', 'easy_language_pagebuilder_divi_admin_bar', 500 );

/**
 * Embed simplification scripts in frontend for using Divi.
 *
 * @return void
 */
function easy_language_pagebuilder_divi_scripts(): void {
	if ( function_exists( 'et_core_is_fb_enabled' ) && Divi::get_instance()->is_active() && et_core_is_fb_enabled() ) {
		Init::get_instance()->get_simplifications_scripts();

		// divi-specific editor-JS.
		wp_register_script(
			'easy-language-divi-admin',
			trailingslashit( plugin_dir_url( EASY_LANGUAGE ) ) . 'classes/multilingual-plugins/easy-language/admin/divi.js',
			array( 'jquery', 'et-dynamic-asset-helpers' ),
			filemtime( plugin_dir_path( EASY_LANGUAGE ) . '/classes/multilingual-plugins/easy-language/admin/divi.js' ),
			true
		);
		wp_enqueue_script( 'easy-language-divi-admin' );
	}
}
add_action( 'wp_enqueue_scripts', 'easy_language_pagebuilder_divi_scripts' );

/**
 * Add our custom toggle.
 *
 * @param array $toggles
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
 * @param array $fields
 *
 * @return array
 */
function easy_language_divi_add_fields( array $fields ): array {
	$post_types = array();
	foreach( Init::get_instance()->get_supported_post_types() as $post_type => $enabled ) {
		$post_types[] = $post_type;
	}

	$fields['easy-language-simplify-texts'] = array(
		'meta_key' => 'easy_language_divi_languages',
		'type' => 'easy-language-language-options',
		'options' => array(
			'off' => esc_html__('Off', 'et_builder'),
			'on' => esc_html__('On', 'et_builder'),
		),
		'show_in_bb' => true,
		'option_category' => 'basic_option',
		'tab_slug' => 'content',
		'toggle_slug' => 'easy-language-simplifications',
		'depends_on_post_type' => $post_types
	);
	return $fields;
}
add_filter( 'et_builder_page_settings_definitions', 'easy_language_divi_add_fields' );

/**
 * Save values from fields added via @easy_language_divi_add_fields.
 *
 * @param array $values
 *
 * @return array
 */
function easy_language_divi_save_values( array $values ): array {
	return $values;
}
add_filter( 'et_builder_page_settings_values', 'easy_language_divi_save_values' );

/**
 * Add custom JS-file for divi.
 *
 * @return void
 */
function easy_language_divi_add_scripts(): void {
	wp_register_script(
		'easy-language-divi',
		trailingslashit( plugin_dir_url( EASY_LANGUAGE ) ) . 'classes/multilingual-plugins/easy-language/parser/divi/build/language_field.js',
		array( 'jquery', 'et-dynamic-asset-helpers' ),
		filemtime( plugin_dir_path( EASY_LANGUAGE ) . '/classes/multilingual-plugins/easy-language/parser/divi/build/language_field.js' ),
		true
	);
	wp_enqueue_script( 'easy-language-divi' );
}
add_action( 'et_fb_enqueue_assets', 'easy_language_divi_add_scripts' );
