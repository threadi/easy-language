<?php
/**
 * File for initializing the easy-language-own translations.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

use easyLanguage\Api_Base;
use easyLanguage\Helper;
use easyLanguage\Languages;
use WP_Query;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles a single post-object.
 */
class Post_Object implements Easy_Language_Object {
	/**
	 * The ID of the object.
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * The translate-type of the object.
	 *
	 * @var string
	 */
	private string $translate_type = 'translatable';

	/**
	 * Initialize the object.
	 *
	 * @param int $post_id The Post-ID.
	 */
	public function __construct( int $post_id ) {
		// secure the given ID.
		$this->id = $post_id;

		/**
		 * Check translate-typ of object: translatable or translated.
		 */
		if ( get_post_meta( $this->get_id(), 'easy_language_translation_original_id', true ) ) {
			$this->translate_type = 'translated';
		}

		/**
		 * Set original language for translatable type-objects, if not set.
		 */
		if ( 'translatable' === $this->translate_type && empty( get_post_meta( $this->get_id(), 'easy_language_text_language', true ) ) ) {
			update_post_meta( $this->get_id(), 'easy_language_text_language', helper::get_wp_lang() );
		}
	}

	/**
	 * Return the object language depending on object type.
	 *
	 * @return array
	 */
	public function get_language(): array {
		$languages = Languages::get_instance()->get_active_languages();
		if ( 'translatable' === $this->translate_type ) {
			$languages     = Languages::get_instance()->get_possible_source_languages();
			$language_code = get_post_meta( $this->get_id(), 'easy_language_text_language', true );
		} else {
			$language_code = get_post_meta( $this->get_id(), 'easy_language_translation_language', true );
		}
		if ( ! empty( $language_code ) && ! empty( $languages[ $language_code ] ) ) {
			return array(
				$language_code => $languages[ $language_code ],
			);
		}
		return array();
	}

	/**
	 * Return the post-type of this object.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return get_post_type( $this->get_id() );
	}

	/**
	 * Return the ID of this object.
	 *
	 * @return int
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * Get post-ID of the original post.
	 *
	 * @return int
	 */
	public function get_original_object_as_int(): int {
		return absint( get_post_meta( $this->get_id(), 'easy_language_translation_original_id', true ) );
	}

	/**
	 * Return whether this object is a translated object.
	 *
	 * @return bool
	 */
	public function is_translated(): bool {
		return 'translated' === $this->translate_type;
	}

	/**
	 * Return whether this object is a translatable object.
	 *
	 * @return bool
	 */
	public function is_translatable(): bool {
		return 'translatable' === $this->translate_type;
	}

	/**
	 * Return whether a given post type is translated in given language.
	 *
	 * @param string $language The language to check.
	 *
	 * @return bool
	 */
	public function is_translated_in_language( string $language ): bool {
		return $this->get_translated_in_language( $language ) > 0;
	}

	/**
	 * Return the post_id of the translation of this object in a given language.
	 *
	 * @param string $language_code The language we search.
	 *
	 * @return int
	 */
	public function get_translated_in_language( string $language_code ): int {
		$query  = array(
			'post_type'                       => $this->get_type(),
			'post_status'                     => 'any',
			'meta_query'                      => array(
				'relation' => 'AND',
				array(
					'key'     => 'easy_language_translation_original_id',
					'value'   => $this->get_id(),
					'compare' => '=',
				),
				array(
					'key'     => 'easy_language_translation_language',
					'value'   => $language_code,
					'compare' => '=',
				),
			),
			'fields'                          => 'ids',
			'do_not_use_easy_language_filter' => '1',
		);
		$result = new WP_Query( $query );
		if ( 1 === $result->post_count ) {
			return $result->posts[0];
		}
		return 0;
	}

	/**
	 * Get WP-own post object as array.
	 *
	 * @return array
	 */
	public function get_object_as_array(): array {
		return get_post( $this->get_id(), ARRAY_A );
	}

	/**
	 * Return the object-title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return get_post_field( 'post_title', $this->get_id() );
	}

	/**
	 * Return the post content of this object.
	 *
	 * @return string
	 */
	public function get_content(): string {
		return get_post_field( 'post_content', $this->get_id() );
	}

	/**
	 * Get language specific URL for this object.
	 *
	 * @param string $slug The slug of the language.
	 * @param string $language_code The language-code.
	 *
	 * @return string
	 * @noinspection PhpUnused
	 */
	public function get_language_specific_url( string $slug, string $language_code ): string {
		// define target-url.
		$url = trailingslashit( $slug );

		// if actual object is translated, link to the translated object.
		if ( $this->is_translated_in_language( $language_code ) ) {
			if ( in_array( get_option( 'easy_language_switcher_link', '' ), array( 'hide_not_translated', 'link_translated' ), true ) ) {
				$url = get_permalink( $this->get_translated_in_language( $language_code ) );
			} elseif ( 'page' === get_option( 'show_on_front' ) ) {
					$object_id          = absint( get_option( 'page_on_front', 0 ) );
					$object             = new Post_Object( $object_id );
					$translated_post_id = $object->get_translated_in_language( $language_code );
					$url                = get_permalink( $translated_post_id );
			} elseif ( 'posts' === get_option( 'show_on_front' ) ) {
				$url = get_home_url();
			}
		} elseif ( key( $this->get_language() ) === $language_code ) {
			$url = get_permalink( $this->get_id() );
		} elseif ( in_array( get_option( 'easy_language_switcher_link', '' ), array( 'hide_not_translated', 'link_translated' ), true ) ) {
			// if this page is not translated, link to the homepage.
			$url = get_home_url();
		}

		// return resulting url.
		return $url;
	}

	/**
	 * Set marker that the translatable content of this object has been changed.
	 *
	 * @param string $language_code The language we search.
	 *
	 * @return void
	 */
	public function mark_as_changed_in_language( string $language_code ): void {
		if ( false === $this->is_translatable() ) {
			return;
		}

		// set marker.
		update_post_meta( $this->get_id(), 'easy_language_' . $language_code . '_changed', '1' );
	}

	/**
	 * Return whether the content of this object has been changed.
	 *
	 * @param string $language_code The language-code.
	 *
	 * @return bool
	 */
	public function has_changed( string $language_code ): bool {
		$changed_marker = absint( get_post_meta( $this->get_id(), 'easy_language_' . $language_code . '_changed', true ) );
		return $this->is_translatable() && 1 === $changed_marker;
	}

	/**
	 * Delete changed marker.
	 *
	 * @param int|string $language_code The language-code. TODO int?.
	 *
	 * @return void
	 */
	public function remove_changed_marker( int|string $language_code ): void {
		if ( false === $this->is_translatable() ) {
			return;
		}

		// delete marker.
		delete_post_meta( $this->get_id(), 'easy_language_' . $language_code . '_changed' );
	}

	/**
	 * Add a language as translated language to translatable object.
	 *
	 * @param string $target_language The language we search.
	 *
	 * @return void
	 */
	public function add_translated_language( string $target_language ): void {
		// only for translatable object.
		if ( false === $this->is_translatable() ) {
			delete_post_meta( $this->get_id(), 'easy_language_translated_in' );
			return;
		}

		// get actual value.
		$value = get_post_meta( $this->get_id(), 'easy_language_translated_in', true );
		if ( false === str_contains( $value, ',' . $target_language . ',' ) ) {
			$value .= ',' . $target_language . ',';
		}

		// add new language to list.
		update_post_meta( $this->get_id(), 'easy_language_translated_in', $value );
	}

	/**
	 * Remove a language as translated language from a translatable object.
	 *
	 * @param string $target_language The language we search.
	 *
	 * @return void
	 */
	public function remove_translated_language( string $target_language ): void {
		// only for translatable object.
		if ( false === $this->is_translatable() ) {
			return;
		}

		// get actual value.
		$value = get_post_meta( $this->get_id(), 'easy_language_translated_in', true );

		// remove language from list.
		$value = str_replace( ',' . $target_language . ',', '', $value );
		if ( empty( $value ) ) {
			delete_post_meta( $this->get_id(), 'easy_language_translated_in' );
		} else {
			update_post_meta( $this->get_id(), 'easy_language_translated_in', $value );
		}
	}

	/**
	 * Get pagebuilder of this object.
	 *
	 * @return object|false
	 */
	public function get_page_builder(): object|false {
		// check the list of supported pagebuilder for compatibility.
		// the first one which matches will be used.
		foreach ( apply_filters( 'easy_language_pagebuilder', array() ) as $page_builder_obj ) {
			if ( $page_builder_obj->is_object_using_pagebuilder( $this ) ) {
				$page_builder_obj->set_object_id( $this->get_id() );
				return $page_builder_obj;
			}
		}

		// return false if no pagebuilder could be detected.
		return false;
	}

	/**
	 * Return the link to translate the actual object via given api.
	 *
	 * @return string
	 */
	public function get_translation_via_api_link(): string {
		return add_query_arg(
			array(
				'action' => 'easy_language_get_automatic_translation',
				'id'     => $this->get_id(),
				'nonce'  => wp_create_nonce( 'easy-language-get-automatic-translation' ),
			),
			get_admin_url() . 'admin.php'
		);
	}

	/**
	 * Get link to create a translation of the actual object with given language.
	 *
	 * @param string $language_code The language we search.
	 *
	 * @return string
	 */
	public function get_translate_link( string $language_code ): string {
		return add_query_arg(
			array(
				'action'   => 'easy_language_add_translation',
				'nonce'    => wp_create_nonce( 'easy-language-add-translation' ),
				'post'     => $this->get_id(),
				'language' => $language_code,
			),
			get_admin_url() . 'admin.php'
		);
	}

	/**
	 * Return the post-status.
	 *
	 * @return string
	 */
	public function get_status(): string {
		return get_post_status( $this->get_id() );
	}

	/**
	 * Return quota-state of this object regarding a given api.
	 *
	 * States:
	 * - ok => could be translated
	 * - above_limit => if characters of this object are more than the quota-limit
	 * - exceeded => if quota is exceeded
	 *
	 * @param Api_Base $api_obj The Api-object.
	 *
	 * @return array
	 */
	public function get_quota_state( Api_Base $api_obj ): array {
		// define return-array.
		$return_array = array(
			'status'        => 'ok',
			'chars_count'   => 0,
			'quota_percent' => 0,
			'quota_rest'    => 0,
		);

		// get chars to translate.
		$filter  = array(
			'object_id' => $this->get_id(),
		);
		$entries = Db::get_instance()->get_entries( $filter );
		foreach ( $entries as $entry ) {
			$return_array['chars_count'] += absint( strlen( $entry->get_original() ) );
		}

		// get quota value.
		$quota_array = $api_obj->get_quota();
		if ( ! empty( $quota_array['character_limit'] ) && 0 < $quota_array['character_limit'] ) {
			$return_array['quota_percent'] = absint( $quota_array['character_spent'] ) / absint( $quota_array['character_limit'] );
			$return_array['quota_rest']    = absint( $quota_array['character_limit'] ) - absint( $quota_array['character_spent'] );
		}

		// chars are above the rest of the quota.
		if ( $return_array['quota_rest'] < $return_array['chars_count'] ) {
			$return_array['status'] = 'above_limit';
		}

		// quota is exceeded.
		if ( 0 === $return_array['quota_rest'] ) {
			$return_array['status'] = 'exceeded';
		}

		// if unlimited-marker is set, set status to ok.
		if ( ! empty( $quota_array['unlimited'] ) ) {
			$return_array['status'] = 'ok';
		}

		// return ok.
		return $return_array;
	}
}
