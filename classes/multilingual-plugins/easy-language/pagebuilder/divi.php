<?php
/**
 * File to add Undetected as supported page builder.
 *
 * @package easy-language
 */

use easyLanguage\Apis;
use easyLanguage\Languages;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser\Divi;
use easyLanguage\Multilingual_plugins\Easy_Language\Post_Object;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Add Undetected-object to list of supported pagebuilder.
 *
 * @param $list
 *
 * @return array
 */
function easy_language_pagebuilder_divi( $list ): array {
	$list[] = Divi::get_instance();
	return $list;
}
add_filter( 'easy_language_pagebuilder', 'easy_language_pagebuilder_divi', PHP_INT_MAX );

/**
 * Add custom admin bar as language-switcher in divi.
 *
 * @param WP_Admin_Bar $admin_bar
 *
 * @return void
 */
function easy_language_pagebuilder_divi_admin_bar( WP_Admin_Bar $admin_bar ): void {
	// bail if user has no translation capabilities.
	if( false === current_user_can( 'edit_el_translate') ) {
		return;
	}

	if( function_exists('et_core_is_fb_enabled') && Divi::get_instance()->is_active() && et_core_is_fb_enabled() ) {
		// get the active languages.
		$target_languages = Languages::get_instance()->get_active_languages();

		// if actual language is not supported as source language, do not show anything.
		if( empty($target_languages) ) {
			return;
		}

		// get our own object for the requested object.
		$post_object = new Post_Object( get_the_ID() );

		if( $post_object->is_translatable() ) {
			// set the actual object as original-object.
			$original_post_object = $post_object;
		}
		else {
			// get original as object.
			$original_post_object = new Post_Object( $post_object->get_original_object_as_int() );
		}

		// secure the menu ID.
		$id = 'easy-language-divi-translate-button';

		// add not clickable main menu where all languages will be added as dropdown-items.
		$admin_bar->add_menu( array(
			'id'    => $id,
			'parent' => null,
			'group'  => null,
			'title' => __( 'Languages', 'easy-language' ),
			'href'  => '',
		) );

		// add sub-entry for each possible target language.
		foreach( array_merge($original_post_object->get_language(), $target_languages) as $language_code => $settings ) {
			// if the original post is translated in this language, get its edit-url.
			if ( $original_post_object->is_translated_in_language( $language_code ) ) {
				$object_id      = $original_post_object->get_translated_in_language( $language_code );
				$translated_obj = new Post_Object( $object_id );
				$url            = $translated_obj->get_page_builder()->get_edit_link();
			} else {
				if ( ! empty( $original_post_object->get_language()[ $language_code ] ) ) {
					$url = $original_post_object->get_page_builder()->get_edit_link();
				} else {
					// create link to generate a new translation for this object.
					$url = $original_post_object->get_translate_link( $language_code );
				}
			}

			// add language as possible translation-target.
			if( !empty($url) ) {
				$admin_bar->add_menu( array(
					'id'     => $id . '-' . $language_code,
					'parent' => $id,
					'title'  => $settings['label'],
					'href'   => $url,
				) );
			}
		}

		// add option to translate this page via api.
		// show translate-button if this is not the original post.
		if( $post_object->get_id() !== $original_post_object->get_id() ) {
			// check if API for automatic translation is active.
			$api_obj = Apis::get_instance()->get_active_api();
			if( false !== $api_obj ) {
				$admin_bar->add_menu( array(
					'id'     => $id . '-translate-object',
					'parent' => $id,
					'title'  => sprintf(__( 'Translate via %1$s', 'easy-language' ), $api_obj->get_title()),
					'href'   => $post_object->get_translation_via_api_link(),
				) );
			}
			elseif( current_user_can('manage_options') ) {
				$admin_bar->add_menu( array(
					'id'     => $id . '-translate-object',
					'parent' => $id,
					'title'  => __('No translation-API active.', 'easy-language'),
					'href'   => '',
				) );
			}
		}
	}
}
add_action( 'admin_bar_menu', 'easy_language_pagebuilder_divi_admin_bar', 500 );
