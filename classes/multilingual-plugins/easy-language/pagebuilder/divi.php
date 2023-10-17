<?php
/**
 * File to add Divi as supported page builder.
 *
 * @package easy-language
 */

use easyLanguage\Apis;
use easyLanguage\Helper;
use easyLanguage\Languages;
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
	$divi_obj = Divi::get_instance();
	if ( $divi_obj->is_active() ) {
		// add divi to list.
		$pagebuilder_list[] = $divi_obj;
	}

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
				'title'  => sprintf( __( 'Simplify this %1$s ', 'easy-language' ), esc_html( $object_type_name ) ),
				'href'   => '',
			)
		);

		// add sub-entry for each possible target language.
		foreach ( array_merge( $original_post_object->get_language(), $target_languages ) as $language_code => $target_language ) {
			/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the language-name */
			$title = sprintf(__( 'Show this %1$s in %2$s ', 'easy-language' ), esc_html($object_type_name), esc_html($target_language['label']) );

			// check if this object is already translated in this language.
			if ( false !== $original_post_object->is_translated_in_language( $language_code ) ) {
				// generate link-target to default editor with language-marker.
				$simplified_post_object = new Post_Object( $original_post_object->get_translated_in_language( $language_code ) );
				$url                    = $simplified_post_object->get_page_builder()->get_edit_link();
			} else {
				// create link to generate a new simplification for this object.
				$url = $original_post_object->get_simplification_link( $language_code );
				/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the language-name */
				$title = sprintf(__( 'Create a simplification of this %1$s in %2$s ', 'easy-language' ), esc_html($object_type_name), esc_html($target_language['label']) );
			}

			// add language as possible translation-target.
			if ( ! empty( $url ) ) {
				$admin_bar->add_menu(
					array(
						'id'     => $id . '-' . $language_code,
						'parent' => $id,
						'title'  => $target_language['label'],
						'href'   => $url,
						'meta' => array(
							'title' => $title
						)
					)
				);
			}
		}

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
						'meta' => array(
							'onclick' => 'easy_language_simplification_init("'.absint($post_object->get_id()).'", "'.esc_url(get_permalink($post_object->get_id())).'");return false;'
						)
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
	}
}
add_action( 'wp_enqueue_scripts', 'easy_language_pagebuilder_divi_scripts' );
