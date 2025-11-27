<?php
/**
 * File for initializing the taxonomy support.
 *
 * @package easy-language
 */

namespace easyLanguage\EasyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Api_Base;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Languages;
use WP_Post;
use WP_Taxonomy;
use WP_Term;
use WP_Term_Query;

/**
 * Handles a single term-object.
 */
class Term_Object extends Objects implements Easy_Language_Interface {
	/**
	 * The taxonomy of the object.
	 *
	 * @var string
	 */
	private string $taxonomy;

	/**
	 * Initialize the object.
	 *
	 * @param int    $term_id The Term-ID.
	 * @param string $taxonomy The taxonomy.
	 */
	public function __construct( int $term_id, string $taxonomy ) {
		parent::__construct( $term_id );

		// secure the given taxonomy.
		$this->taxonomy = $taxonomy;
	}

	/**
	 * Get simplification type.
	 *
	 * @return string
	 */
	protected function get_simplification_type(): string {
		if ( empty( $this->simplify_type ) ) {
			$this->simplify_type = 'simplifiable';
			if ( get_term_meta( $this->get_id(), 'easy_language_simplification_original_id', true ) ) {
				$this->simplify_type = 'simplified';
			}
		}
		return $this->simplify_type;
	}

	/**
	 * Return the object language depending on object type.
	 *
	 * @return array<string,mixed>
	 */
	public function get_language(): array {
		$languages = Languages::get_instance()->get_active_languages();
		if ( $this->is_simplifiable() ) {
			$languages     = Languages::get_instance()->get_possible_source_languages();
			$language_code = get_term_meta( $this->get_id(), 'easy_language_text_language', true );
			if ( empty( $language_code ) ) {
				$language_code = Helper::get_wp_lang();
			}
		} else {
			$language_code = get_term_meta( $this->get_id(), 'easy_language_simplification_language', true );
		}
		if ( ! empty( $language_code ) && ! empty( $languages[ $language_code ] ) ) {
			return array(
				$language_code => $languages[ $language_code ],
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
		return $this->taxonomy;
	}

	/**
	 * Return the term ID of the original term.
	 *
	 * @return int
	 */
	public function get_original_object_as_int(): int {
		return absint( get_term_meta( $this->get_id(), 'easy_language_simplification_original_id', true ) );
	}

	/**
	 * Return whether this original object has simplifications.
	 *
	 * @return bool
	 */
	public function has_simplifications(): bool {
		// bail for simplified objects.
		if ( $this->is_simplified() ) {
			return false;
		}

		// get list of simplifications in languages.
		$languages = get_term_meta( $this->get_id(), 'easy_language_simplified_in', true );

		// return true if list is not empty.
		return ! empty( $languages );
	}

	/**
	 * Return the term_id of the simplification of this object in a given language.
	 *
	 * @param string $language_code The language we search.
	 *
	 * @return int
	 */
	public function get_simplification_in_language( string $language_code ): int {
		$query  = array(
			'meta_query'                      => array(
				'relation' => 'AND',
				array(
					'key'     => 'easy_language_simplification_original_id',
					'value'   => $this->get_id(),
					'compare' => '=',
				),
				array(
					'key'     => 'easy_language_simplification_language',
					'value'   => $language_code,
					'compare' => '=',
				),
			),
			'fields'                          => 'ids',
			'hide_empty'                      => false,
			'do_not_use_easy_language_filter' => '1',
		);
		$result = new WP_Term_Query( $query );
		if ( null !== $result->terms && 1 === count( $result->terms ) ) {
			return absint( $result->terms[0] );
		}
		return 0;
	}

	/**
	 * Return the object-title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		// get the title.
		$title = get_term_field( 'name', $this->get_id() );

		// bail if title is not a string.
		if ( ! is_string( $title ) ) {
			return '';
		}

		// return the title.
		return $title;
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
		if ( $this->is_simplified_in_language( $language_code ) ) {
			if ( 'link_translated' === get_option( 'easy_language_switcher_link', '' ) ) {
				$url = get_term_link( $this->get_simplification_in_language( $language_code ) );
			} elseif ( 'page' === get_option( 'show_on_front' ) ) {
				$object_id = absint( get_option( 'page_on_front', 0 ) );
				$object    = new Term_Object( $object_id, $this->get_type() );
				$url       = get_term_link( $object->get_simplification_in_language( $language_code ) );
			} elseif ( 'posts' === get_option( 'show_on_front' ) ) {
				$url = get_home_url();
			}
		} elseif ( key( $this->get_language() ) === $language_code ) {
			$url = get_term_link( $this->get_id() );
		} elseif ( 'link_translated' === get_option( 'easy_language_switcher_link', '' ) ) {
			// if this page is not translated, link to the homepage.
			$url = get_home_url();
		}

		// bail if URL is not a string.
		if ( ! is_string( $url ) ) {
			return '';
		}

		// return the resulting url.
		return $url;
	}

	/**
	 * Set marker that the simplified content of this object has been changed.
	 *
	 * @param string $language_code The language we will mark.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function mark_as_changed_in_language( string $language_code ): void {
		if ( false === $this->is_simplifiable() ) {
			return;
		}

		// set marker.
		update_term_meta( $this->get_id(), 'easy_language_' . $language_code . '_changed', '1' );
	}

	/**
	 * Return whether the content of this object has been changed.
	 *
	 * @param string $language_code The language-code.
	 *
	 * @return bool
	 */
	public function has_changed( string $language_code ): bool {
		$changed_marker = absint( get_term_meta( $this->get_id(), 'easy_language_' . $language_code . '_changed', true ) );
		return $this->is_simplifiable() && 1 === $changed_marker;
	}

	/**
	 * Delete changed marker.
	 *
	 * @param int|string $language_code The language-code.
	 *
	 * @return void
	 */
	public function remove_changed_marker( int|string $language_code ): void {
		if ( false === $this->is_simplifiable() ) {
			return;
		}

		// delete marker.
		delete_term_meta( $this->get_id(), 'easy_language_' . $language_code . '_changed' );
	}

	/**
	 * Add a language as simplified language to simplifiable object.
	 *
	 * @param string $target_language The language we search.
	 *
	 * @return void
	 */
	public function add_language( string $target_language ): void {
		// only for translatable object.
		if ( false === $this->is_simplifiable() ) {
			delete_term_meta( $this->get_id(), 'easy_language_simplified_in' );
			return;
		}

		// get actual value.
		$value = get_term_meta( $this->get_id(), 'easy_language_simplified_in', true );
		if ( false === str_contains( $value, ',' . $target_language . ',' ) ) {
			$value .= ',' . $target_language . ',';
		}

		// add new language to list.
		update_term_meta( $this->get_id(), 'easy_language_simplified_in', $value );
	}

	/**
	 * Remove a language as translated language from a translatable object.
	 *
	 * @param string $target_language The language we search.
	 *
	 * @return void
	 */
	public function remove_language( string $target_language ): void {
		// only for simplifiable object.
		if ( false === $this->is_simplifiable() ) {
			return;
		}

		// get actual value.
		$value = get_term_meta( $this->get_id(), 'easy_language_simplified_in', true );

		// remove language from list.
		$value = str_replace( ',' . $target_language . ',', '', $value );
		if ( empty( $value ) ) {
			delete_term_meta( $this->get_id(), 'easy_language_simplified_in' );
		} else {
			update_term_meta( $this->get_id(), 'easy_language_simplified_in', $value );
		}
	}

	/**
	 * Return the link to simplify the actual object via given api.
	 *
	 * @return string
	 */
	public function get_simplification_via_api_link(): string {
		return add_query_arg(
			array(
				'action'   => 'easy_language_get_term_simplification',
				'id'       => $this->get_id(),
				'nonce'    => wp_create_nonce( 'easy-language-get-term-simplification' ),
				'taxonomy' => $this->get_type(),
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
	public function get_simplification_link( string $language_code ): string {
		return add_query_arg(
			array(
				'action'   => 'easy_language_add_simplification_term',
				'nonce'    => wp_create_nonce( 'easy-language-add-simplification-term' ),
				'term'     => $this->get_id(),
				'taxonomy' => $this->get_type(),
				'language' => $language_code,
			),
			get_admin_url() . 'admin.php'
		);
	}

	/**
	 * Return the term-status: always published as terms does not have such an entity.
	 *
	 * @return string
	 */
	public function get_status(): string {
		return 'publish';
	}

	/**
	 * Return the delete link for this term.
	 *
	 * @return string
	 */
	public function get_delete_link(): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'   => 'delete',
					'taxonomy' => $this->get_type(),
					'tag_ID'   => $this->get_id(),
				),
				get_admin_url() . 'edit-tags.php'
			),
			'delete-tag_' . $this->get_id()
		);
	}

	/**
	 * Return the name of the term.
	 *
	 * @return string
	 */
	public function get_name(): string {
		// get the name.
		$name = get_term_field( 'name', $this->get_id(), $this->get_type() );

		// bail if name is not a string.
		if ( ! is_string( $name ) ) {
			return '';
		}

		// return the name.
		return $name;
	}

	/**
	 * Return whether this object should not be used during automatic simplification.
	 *
	 * For terms this is not preventable.
	 *
	 * @return bool true if it should not be used
	 * @noinspection PhpUnused
	 */
	public function is_automatic_mode_prevented(): bool {
		return false;
	}

	/**
	 * Return language-specific title for the type of the given object.
	 *
	 * @return string
	 */
	public function get_type_name(): string {
		// get taxonomy of the actual object.
		$taxonomy = get_taxonomy( $this->get_type() );

		// if taxonomy could be loaded, use its singular name.
		if ( $taxonomy instanceof WP_Taxonomy ) {
			// return its label as singular.
			return $taxonomy->labels->singular_name;
		}

		// fallback to general name.
		return 'term';
	}

	/**
	 * Add simplification object to this object if it is a not simplifiable object.
	 *
	 * @param string   $target_language The target-language.
	 * @param Api_Base $api_object The API to use.
	 * @param bool     $prevent_automatic_mode True if automatic mode is prevented.
	 *
	 * @return bool|Objects
	 **/
	public function add_simplification_object( string $target_language, Api_Base $api_object, bool $prevent_automatic_mode ): bool|Objects {
		// bail if object is already simplified.
		if ( $this->is_simplified_in_language( $target_language ) ) {
			return false;
		}

		// get the source-language.
		$source_language = Helper::get_lang_of_object( $this->get_id(), $this->get_type() );
		if ( empty( $source_language ) ) {
			$source_language = Helper::get_wp_lang();
		}

		// get the original-term as array.
		$term = get_term( $this->get_id(), $this->get_type(), ARRAY_A );

		// bail if term could not be loaded as array.
		if ( ! is_array( $term ) ) {
			return false;
		}

		// remove unnecessary fields.
		unset( $term['term_id'], $term['name'], $term['taxonomy'] );

		// save the copy.
		$result = wp_insert_term( $this->get_name() . '-2', $this->get_type(), $term ); // @phpstan-ignore argument.type

		// bail on error.
		if ( is_wp_error( $result ) ) {
			return false;
		}

		// get the ID of the copy.
		$copied_term_id = $result['term_id'];

		// mark the copied term as translation-object of the original.
		update_term_meta( $copied_term_id, 'easy_language_simplification_original_id', $this->get_id() );

		// save the source-language of the copied object.
		update_term_meta( $copied_term_id, 'easy_language_source_language', $source_language );

		// save the target-language of the copied object.
		update_term_meta( $copied_term_id, 'easy_language_simplification_language', $target_language );

		// save the API used for this simplification.
		update_term_meta( $copied_term_id, 'easy_language_api', $api_object->get_name() );

		// get name and description as translatable texts.
		$title = get_term_field( 'name', $copied_term_id, $this->get_type() );
		if ( ! is_string( $title ) ) {
			$title = '';
		}
		$description = get_term_field( 'description', $copied_term_id, $this->get_type() );
		if ( ! is_string( $description ) ) {
			$description = '';
		}

		// set this texts as translatable texts.
		foreach ( array(
			'taxonomy_title'       => $title,
			'taxonomy_description' => $description,
		) as $field => $text ) {
			// check if the text is already saved as original text for simplification.
			$original_text_obj = Db::get_instance()->get_entry_by_text( $text, $source_language );
			if ( false === $original_text_obj ) {
				// save the text for simplification.
				$original_text_obj = Db::get_instance()->add( $text, $source_language, $field, false );
			}
			if ( $original_text_obj instanceof Text ) {
				$original_text_obj->set_object( $this->get_type(), $copied_term_id, 0, '' );
			}
		}

		// add this language as translated language to original term.
		$this->add_language( $target_language );

		// set marker to reset permalinks.
		Rewrite::get_instance()->set_refresh();

		// get object of copy and return it.
		return new Term_Object( $copied_term_id, $this->get_type() );
	}

	/**
	 * Return public URL for this object.
	 *
	 * @return string
	 */
	public function get_link(): string {
		// get the term URL.
		$url = get_term_link( $this->get_id() );

		// bail if URL could not be loaded.
		if ( ! is_string( $url ) ) {
			return '';
		}

		// return the URL.
		return $url;
	}

	/**
	 * Return the edit link.
	 *
	 * @return string
	 */
	public function get_edit_link(): string {
		// get the edit URL.
		$url = get_edit_term_link( $this->get_id() );

		// bail if the URL is not a string.
		if ( ! is_string( $url ) ) {
			return '';
		}

		// return the title.
		return $url;
	}

	/**
	 * Automatic simplification of terms could not be stopped.
	 *
	 * @param bool $prevent_automatic_mode true if automatic should be prevented.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 **/
	public function set_automatic_mode_prevented( bool $prevent_automatic_mode ): void {}

	/**
	 * Return WP-own object of this plugin handled object.
	 *
	 * @return WP_Post|WP_Term|false
	 */
	public function get_object_as_object(): WP_Post|WP_Term|false {
		// get the term.
		$term = get_term( $this->get_id(), $this->get_type() );

		// bail if term could not be loaded.
		if ( ! $term instanceof WP_Term ) {
			return false;
		}

		// return the term.
		return $term;
	}
}
