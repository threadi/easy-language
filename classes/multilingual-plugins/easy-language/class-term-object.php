<?php
/**
 * File for initializing the easy-language-own translations.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

use easyLanguage\Helper;
use easyLanguage\Languages;
use WP_Term_Query;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles a single post-object.
 */
class Term_Object implements Easy_Language_Object {
	/**
	 * The ID of the object.
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * The taxonomy of the object.
	 *
	 * @var string
	 */
	private string $taxonomy;

	/**
	 * The translate-type of the object.
	 *
	 * @var string
	 */
	private string $translate_type = 'translatable';

	/**
	 * Initialize the object.
	 *
	 * @param int $term_id The Term-ID.
	 */
	public function __construct( int $term_id, string $taxonomy ) {
		// secure the given ID.
		$this->id = $term_id;

		// secure the given taxonomy.
		$this->taxonomy = $taxonomy;

		/**
		 * Check translate-typ of object: translatable or translated.
		 */
		if( get_term_meta( $this->get_id(), 'easy_language_translation_original_id', true ) ) {
			$this->translate_type = 'translated';
		}

		/**
		 * Set original language for translatable type-objects, if not set.
		 */
		if( 'translatable' === $this->translate_type && empty(get_post_meta( $this->get_id(), 'easy_language_text_language', true )) ) {
			update_term_meta( $this->get_id(), 'easy_language_text_language', helper::get_wp_lang() );
		}
	}

	/**
	 * Return the object language depending on object type.
	 *
	 * @return array
	 */
	public function get_language(): array {
		$languages = Languages::get_instance()->get_active_languages();
		if( 'translatable' === $this->translate_type ) {
			$languages = Languages::get_instance()->get_possible_source_languages();
			$language_code = get_term_meta( $this->get_id(), 'easy_language_text_language', true );
		}
		else {
			$language_code = get_term_meta( $this->get_id(), 'easy_language_translation_language', true );
		}
		if( !empty($language_code) && !empty($languages[$language_code]) ) {
			return array(
				$language_code => $languages[ $language_code ]
			);
		}
		return array();
	}

	/**
	 * Return the type of this object.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return '';
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
	 * Get term-ID of the original term.
	 *
	 * @return int
	 */
	public function get_original_object_as_int(): int {
		return absint(get_term_meta( $this->get_id(), 'easy_language_translation_original_id', true ) );
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
	 * @param $language
	 *
	 * @return bool
	 */
	public function is_translated_in_language( $language ): bool {
		return $this->get_translated_in_language( $language ) > 0;
	}

	/**
	 * Return the post_id of the translation of this object in a given language.
	 *
	 * @param string $language_code
	 *
	 * @return int
	 */
	public function get_translated_in_language( string $language_code ): int {
		$query = array(
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => 'easy_language_translation_original_id',
					'value' => $this->get_id(),
					'compare' => '='
				),
				array(
					'key' => 'easy_language_translation_language',
					'value' => $language_code,
					'compare' => '='
				),
			),
			'fields' => 'ids',
			'hide_empty' => false,
			'do_not_use_easy_language_filter' => '1'
		);
		$result = new WP_Term_Query( $query );
		if( null !== $result->terms && 1 === count($result->terms) ) {
			return $result->terms[0];
		}
		return 0;
	}

	/**
	 * Return the object-title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return get_term_field( 'name', $this->get_id() );
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
		if( $this->is_translated_in_language( $language_code ) ) {
			if ( 'link_translated' === get_option( 'easy_language_switcher_link', '' ) ) {
				$url = get_permalink( $this->get_translated_in_language( $language_code ) );
			}
			else {
				if( 'page' === get_option('show_on_front') ) {
					$object_id = absint( get_option( 'page_on_front', 0 ) );
					$object = new Post_Object( $object_id );
					$translated_post_id = $object->get_translated_in_language( $language_code );
					$url = get_permalink( $translated_post_id );
				}
				elseif( 'posts' === get_option('show_on_front') ) {
					$url = get_home_url();
				}
			}
		}
		elseif( $language_code === key($this->get_language()) ) {
			$url = get_permalink( $this->get_id() );
		}
		// if this page is not translated, link to the homepage.
		elseif( 'link_translated' === get_option( 'easy_language_switcher_link', '' ) ) {
			$url = get_home_url();
		}

		// return resulting url.
		return $url;
	}

	/**
	 * Return false for pagebuilder-request as terms does not use a pagebuilder.
	 *
	 * @return object|false
	 * @noinspection PhpUnused
	 */
	public function get_page_builder(): object|false {
		return false;
	}

	/**
	 * Set marker that the translatable content of this object has been changed.
	 *
	 * @param string $language_code
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function mark_as_changed_in_language( string $language_code ): void {
		if( false === $this->is_translatable() ) {
			return;
		}

		// set marker.
		update_term_meta( $this->get_id(), 'easy_language_'.$language_code.'_changed', '1' );
	}

	/**
	 * Return whether the content of this object has been changed.
	 *
	 * @param string $language_code The language-code.
	 *
	 * @return bool
	 */
	public function has_changed( string $language_code ): bool {
		$changed_marker = absint( get_term_meta( $this->get_id(), 'easy_language_'.$language_code.'_changed', true ) );
		return $this->is_translatable() && 1 === $changed_marker;
	}

	/**
	 * Delete changed marker.
	 *
	 * @param int|string $language_code The language-code.
	 *
	 * @return void
	 */
	public function remove_changed_marker( int|string $language_code ): void {
		if( false === $this->is_translatable() ) {
			return;
		}

		// delete marker.
		delete_term_meta( $this->get_id(), 'easy_language_'.$language_code.'_changed' );
	}

	/**
	 * Add a language as translated language to translatable object.
	 *
	 * @param string $target_language
	 *
	 * @return void
	 */
	public function add_translated_language( string $target_language ): void {
		// only for translatable object.
		if( false === $this->is_translatable() ) {
			delete_term_meta( $this->get_id(), 'easy_language_translated_in' );
			return;
		}

		// get actual value.
		$value = get_term_meta( $this->get_id(), 'easy_language_translated_in', true );
		if( false === str_contains( $value, ','.$target_language.',' ) ) {
			$value .= ',' . $target_language . ',';
		}

		// add new language to list.
		update_term_meta( $this->get_id(), 'easy_language_translated_in', $value );
	}

	/**
	 * Remove a language as translated language from a translatable object.
	 *
	 * @param string $target_language
	 *
	 * @return void
	 */
	public function remove_translated_language( string $target_language ): void {
		// only for translatable object.
		if( false === $this->is_translatable() ) {
			return;
		}

		// get actual value.
		$value = get_term_meta( $this->get_id(), 'easy_language_translated_in', true );

		// remove language from list.
		$value = str_replace( ','.$target_language.',', '', $value );
		if( empty($value) ) {
			delete_term_meta( $this->get_id(), 'easy_language_translated_in' );
		}
		else {
			update_term_meta( $this->get_id(), 'easy_language_translated_in', $value );
		}
	}

	/**
	 * Return the link to translate the actual object via given api.
	 *
	 * @return string
	 */
	public function get_translation_via_api_link(): string {
		return add_query_arg( array(
				'action'   => 'easy_language_get_automatic_translation',
				'id'     => $this->get_id(),
				'nonce'    => wp_create_nonce( 'easy-language-get-automatic-translation' ),
				'taxonomy' => $this->get_taxonomy_name()
			),
			get_admin_url() . 'admin.php'
		);
	}

	/**
	 * Get link to create a translation of the actual object with given language.
	 *
	 * @param string $language_code
	 *
	 * @return string
	 */
	public function get_translate_link( string $language_code ): string {
		return add_query_arg( array(
				'action'   => 'easy_language_add_translation',
				'nonce'    => wp_create_nonce( 'easy-language-add-translation' ),
				'term'     => $this->get_id(),
				'taxonomy' => $this->get_taxonomy_name(),
				'language' => $language_code
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
	 * Return the edit link for this term.
	 *
	 * @return string
	 */
	public function get_edit_link(): string {
		return add_query_arg( array(
				'taxonomy' => $this->get_taxonomy_name(),
				'tag_ID' => $this->get_id()
			),
			get_admin_url().'term.php'
		);
	}

	/**
	 * Return taxonomy-name.
	 *
	 * @return string
	 */
	public function get_taxonomy_name(): string {
		return $this->taxonomy;
	}

	/**
	 * Return the delete link for this term.
	 *
	 * @return string
	 */
	public function get_delete_link(): string {
		return wp_nonce_url(
			add_query_arg( array(
					'action' => 'delete',
					'taxonomy' => $this->get_taxonomy_name(),
					'tag_ID' => $this->get_id()
				),
				get_admin_url().'edit-tags.php'
			), 'delete-tag_' . $this->get_id()
		);
	}

	/**
	 * Return the name of the term.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return get_term_field( 'name', $this->get_id(), $this->get_taxonomy_name() );
	}
}
