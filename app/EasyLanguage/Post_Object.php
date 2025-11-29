<?php
/**
 * File for initializing the easy-language-own simplifications.
 *
 * @package easy-language
 */

namespace easyLanguage\EasyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Api_Base;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Init;
use easyLanguage\Plugin\Languages;
use WP_Post;
use WP_Query;
use WP_Term;

/**
 * Handles a single post-object.
 */
class Post_Object extends Objects implements Easy_Language_Interface {
	/**
	 * Get simplification type.
	 *
	 * @return string
	 */
	protected function get_simplification_type(): string {
		if ( empty( $this->simplify_type ) ) {
			$this->simplify_type = 'simplifiable';
			if ( get_post_meta( $this->get_id(), 'easy_language_simplification_original_id', true ) ) {
				$this->simplify_type = 'simplified';
			}
		}
		return $this->simplify_type;
	}

	/**
	 * Return the object language depending on object type.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function get_language(): array {
		$languages = Languages::get_instance()->get_active_languages();

		// if this is a simplifiable object, get only source languages.
		if ( $this->is_simplifiable() ) {
			$languages     = Languages::get_instance()->get_possible_source_languages();
			$language_code = get_post_meta( $this->get_id(), 'easy_language_text_language', true );
			if ( empty( $language_code ) ) {
				$language_code = Helper::get_wp_lang();
			}
		} else {
			$language_code = get_post_meta( $this->get_id(), 'easy_language_simplification_language', true );
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
		// get the post type.
		$post_type = get_post_type( $this->get_id() );

		// bail if post type could not be returned.
		if ( ! $post_type ) {
			return '';
		}

		// return the post type.
		return $post_type;
	}

	/**
	 * Get post-ID of the original post.
	 *
	 * @return int
	 */
	public function get_original_object_as_int(): int {
		return absint( get_post_meta( $this->get_id(), 'easy_language_simplification_original_id', true ) );
	}

	/**
	 * Return the post_id of the simplification of this object in a given language.
	 *
	 * @param string $language_code The language we search.
	 *
	 * @return int
	 */
	public function get_simplification_in_language( string $language_code ): int {
		$query  = array(
			'post_type'                       => $this->get_type(),
			'post_status'                     => 'any',
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
			'do_not_use_easy_language_filter' => '1',
		);
		$result = new WP_Query( $query );
		if ( 1 === $result->post_count ) {
			return absint( $result->posts[0] );
		}
		return 0;
	}

	/**
	 * Return the post_id of the simplification of this object in any languages.
	 *
	 * @param bool $with_trashed True if the trashed posts should be searched.
	 *
	 * @return array<int,int>
	 */
	public function get_simplifications( bool $with_trashed = false ): array {
		// define state we search.
		$post_status = array( 'any' );
		if ( $with_trashed ) {
			$post_status[] = 'trash';
		}

		// create the list.
		$list = array();

		// get the simplifications of the given object.
		$query  = array(
			'post_type'                       => $this->get_type(),
			'post_status'                     => $post_status,
			'meta_query'                      => array(
				array(
					'key'     => 'easy_language_simplification_original_id',
					'value'   => $this->get_id(),
					'compare' => '=',
				),
			),
			'fields'                          => 'ids',
			'do_not_use_easy_language_filter' => '1',
		);
		$result = new WP_Query( $query );

		// bail on no results.
		if ( 0 === $result->post_count ) {
			return array();
		}

		// collect the list.
		foreach ( $result->get_posts() as $post_id ) {
			$list[] = absint( $post_id );
		}

		// return the list.
		return $list;
	}

	/**
	 * Get WP-own post object as array.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_object_as_array(): array {
		// get post as array.
		$post = get_post( $this->get_id(), ARRAY_A );

		// bail if returned value is not an array.
		if ( ! is_array( $post ) ) {
			return array();
		}

		// return the post as array.
		return $post;
	}

	/**
	 * Get WP-own post object as WP-object.
	 *
	 * @return WP_Post|WP_Term|false
	 */
	public function get_object_as_object(): WP_Post|WP_Term|false {
		// get post as object.
		$post = get_post( $this->get_id() );

		// bail if returned value is not an object.
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		// return the post as object.
		return $post;
	}

	/**
	 * Return the object-title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return get_the_title( $this->get_id() );
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

		// if actual object is simplified, link to the simplified object.
		if ( $this->is_simplified_in_language( $language_code ) ) {
			if ( in_array( get_option( 'easy_language_switcher_link', '' ), array( 'hide_not_translated', 'link_translated' ), true ) ) {
				$url = get_permalink( $this->get_simplification_in_language( $language_code ) );
			} elseif ( 'page' === get_option( 'show_on_front' ) ) {
					$object_id = absint( get_option( 'page_on_front', 0 ) );
					$object    = new Post_Object( $object_id );
					$url       = get_permalink( $object->get_simplification_in_language( $language_code ) );
			} elseif ( 'posts' === get_option( 'show_on_front' ) ) {
				$url = get_home_url();
			}
		} elseif ( key( $this->get_language() ) === $language_code ) {
			$url = get_permalink( $this->get_id() );
		} elseif ( in_array( get_option( 'easy_language_switcher_link', '' ), array( 'hide_not_translated', 'link_translated' ), true ) ) {
			// if this page is not simplified, link to the homepage.
			$url = get_home_url();
		}

		// bail if no URL could be found.
		if ( ! $url ) {
			return '';
		}

		// return resulting url.
		return $url;
	}

	/**
	 * Set marker that the simplified content of this object has been changed.
	 *
	 * @param string $language_code The language we search.
	 *
	 * @return void
	 */
	public function mark_as_changed_in_language( string $language_code ): void {
		if ( false === $this->is_simplifiable() ) {
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
		return $this->is_simplifiable() && 1 === $changed_marker;
	}

	/**
	 * Delete changed marker.
	 *
	 * @param string $language_code The language-code.
	 *
	 * @return void
	 */
	public function remove_changed_marker( string $language_code ): void {
		if ( false === $this->is_simplifiable() ) {
			return;
		}

		// delete marker.
		delete_post_meta( $this->get_id(), 'easy_language_' . $language_code . '_changed' );
	}

	/**
	 * Add a language as simplified language to simplifiable object.
	 *
	 * @param string $target_language The language we search.
	 *
	 * @return void
	 */
	public function add_language( string $target_language ): void {
		// only for simplifiable object.
		if ( false === $this->is_simplifiable() ) {
			delete_post_meta( $this->get_id(), 'easy_language_simplified_in' );
			return;
		}

		// get actual value.
		$value = get_post_meta( $this->get_id(), 'easy_language_simplified_in', true );
		if ( false === str_contains( $value, ',' . $target_language . ',' ) ) {
			$value .= ',' . $target_language . ',';
		}

		// add new language to list.
		update_post_meta( $this->get_id(), 'easy_language_simplified_in', $value );
	}

	/**
	 * Remove a language from a simplified object.
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
		$value = get_post_meta( $this->get_id(), 'easy_language_simplified_in', true );

		// remove language from list.
		$value = str_replace( ',' . $target_language . ',', '', $value );
		if ( empty( $value ) ) {
			delete_post_meta( $this->get_id(), 'easy_language_simplified_in' );
		} else {
			update_post_meta( $this->get_id(), 'easy_language_simplified_in', $value );
		}
	}

	/**
	 * Return the parser of the pagebuilder which has been used to edit this object.
	 *
	 * @return Parser_Base|false
	 */
	public function get_page_builder(): Parser_Base|false {
		// check the list of supported parser for compatibility.
		// the first one which matches will be used.
		foreach ( Parsers::get_instance()->get_parsers_as_objects() as $parser_obj ) {
			// bail if the object does not use this PageBuilder.
			if ( ! $parser_obj->is_object_using_pagebuilder( $this ) ) {
				continue;
			}

			// set the object id.
			$parser_obj->set_object_id( $this->get_id() );

			// return the parser for this parser.
			return $parser_obj;
		}

		// return false if no parser could be detected.
		return false;
	}

	/**
	 * Return the link to simplify the actual object via given api.
	 *
	 * @return string
	 */
	public function get_simplification_via_api_link(): string {
		return add_query_arg(
			array(
				'action' => 'easy_language_get_simplification',
				'id'     => $this->get_id(),
				'type'   => $this->get_type(),
				'nonce'  => wp_create_nonce( 'easy-language-get-simplification' ),
			),
			get_admin_url() . 'admin.php'
		);
	}

	/**
	 * Get link to create a simplification of the actual object with given language.
	 *
	 * @param string $language_code The language we search.
	 *
	 * @return string
	 */
	public function get_simplification_link( string $language_code ): string {
		return add_query_arg(
			array(
				'action'   => 'easy_language_add_simplification',
				'nonce'    => wp_create_nonce( 'easy-language-add-simplification' ),
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
		// get post status.
		$post_status = get_post_status( $this->get_id() );

		// bail if no post status is found.
		if ( ! $post_status ) {
			return '';
		}

		// return the post status.
		return $post_status;
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
		$languages = get_post_meta( $this->get_id(), 'easy_language_simplified_in', true );

		// return true if the list is not empty.
		return ! empty( $languages );
	}

	/**
	 * Return entries which are assigned to this post-object.
	 *
	 * @return array<Text>
	 */
	public function get_entries(): array {
		return Db::get_instance()->get_entries(
			array(
				'object_id'   => $this->get_id(),
				'object_type' => $this->get_type(),
			)
		);
	}

	/**
	 * Return whether this object is locked or not.
	 *
	 * @return bool true if object is locked.
	 */
	public function is_locked(): bool {
		if ( ! function_exists( 'wp_check_post_lock' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}

		// get lock status.
		$lock = wp_check_post_lock( $this->get_id() );

		// if lock is an integer, it is locked.
		if ( ! is_bool( $lock ) ) {
			return true;
		}

		// return the lock value.
		return $lock;
	}

	/**
	 * Return whether this object should not be used during automatic simplification.
	 *
	 * @return bool true if it should not be used
	 * @noinspection PhpUnused
	 */
	public function is_automatic_mode_prevented(): bool {
		return absint( get_post_meta( $this->get_id(), 'easy_language_prevent_automatic_mode', true ) ) === 1;
	}

	/**
	 * Add simplification object to this object if it is a not simplifiable object.
	 *
	 * @param string   $target_language The target-language.
	 * @param Api_Base $api_object The API to use.
	 * @param bool     $prevent_automatic_mode True if automatic mode is prevented.
	 * @return bool|Objects
	 */
	public function add_simplification_object( string $target_language, Api_Base $api_object, bool $prevent_automatic_mode ): bool|Objects {
		// get DB-object.
		$db = Db::get_instance();

		// check if this object is already simplified in this language.
		if ( false === $this->is_simplified_in_language( $target_language ) ) {
			// get the source-language.
			$source_language = Helper::get_lang_of_object( $this->get_id(), $this->get_type() );
			if ( empty( $source_language ) ) {
				$source_language = Helper::get_wp_lang();
			}

			// get array with post-data of the original.
			$post_array = $this->get_object_as_array();

			// remove some settings.
			unset( $post_array['ID'], $post_array['page_template'], $post_array['guid'] );

			// set author to actual user.
			$post_array['post_author'] = get_current_user_id();

			// add the copy.
			$copied_post_id = wp_insert_post( $post_array ); // @phpstan-ignore argument.type

			// bail if post could not be created.
			if ( is_wp_error( $copied_post_id ) ) { // @phpstan-ignore function.impossibleType
				return false;
			}

			// copy taxonomies and post-metas of this post type object.
			Helper::copy_cpt( $this->get_id(), $copied_post_id );

			// mark the copied post as simplified-object of the original.
			update_post_meta( $copied_post_id, 'easy_language_simplification_original_id', $this->get_id() );

			// save the source-language of the copied object.
			update_post_meta( $copied_post_id, 'easy_language_source_language', $source_language );

			// save the target-language of the copied object.
			update_post_meta( $copied_post_id, 'easy_language_simplification_language', $target_language );

			// save the API used for this simplification.
			update_post_meta( $copied_post_id, 'easy_language_api', $api_object->get_name() );

			// set if automatic mode is prevented.
			update_post_meta( $copied_post_id, 'easy_language_prevent_automatic_mode', $prevent_automatic_mode );

			// set the language for the original object.
			update_post_meta( $this->get_id(), 'easy_language_text_language', $source_language );

			// parse text depending on used pagebuilder for this object.
			$pagebuilder_obj = $this->get_page_builder();

			// bail if page builder could not be loaded.
			if ( ! $pagebuilder_obj instanceof Parser_Base ) {
				return false;
			}

			// set settings on page builder object.
			$pagebuilder_obj->set_object_id( $copied_post_id );
			$pagebuilder_obj->set_title( $this->get_title() );
			$pagebuilder_obj->set_text( $this->get_content() );

			// loop through the resulting texts and add each one for simplification.
			foreach ( $pagebuilder_obj->get_parsed_texts() as $index => $text ) {
				// bail if text is empty.
				if ( empty( $text['text'] ) ) {
					continue;
				}

				// set html-marker to true if not set.
				if ( ! isset( $text['html'] ) ) {
					$text['html'] = true;
				}

				// check if the text is already saved as original text for simplification.
				$original_text_obj = $db->get_entry_by_text( $text['text'], $source_language );
				if ( ! $original_text_obj instanceof Text ) {
					// save the text for simplification.
					$original_text_obj = $db->add( $text['text'], $source_language, 'post_content', $text['html'] );
					if ( ! $original_text_obj instanceof Text ) {
						return false;
					}
				}
				$original_text_obj->set_object( $this->get_type(), $copied_post_id, $index, $pagebuilder_obj->get_name() );
				$original_text_obj->set_state( 'to_simplify' );
			}

			// check if the title has already saved as original text for simplification.
			$original_title_obj = $db->get_entry_by_text( $pagebuilder_obj->get_title(), $source_language );
			if ( ! $original_title_obj instanceof Text ) {
				// save the text for simplification.
				$original_title_obj = $db->add( $pagebuilder_obj->get_title(), $source_language, 'title', false );
				if ( ! $original_title_obj instanceof Text ) {
					return false;
				}
			}
			$original_title_obj->set_object( $this->get_type(), $copied_post_id, 0, $pagebuilder_obj->get_name() );
			$original_title_obj->set_state( 'to_simplify' );

			// add this language as simplified language to original post.
			$this->add_language( $target_language );

			// set marker to reset permalinks.
			Rewrite::get_instance()->set_refresh();

			// set lock on post to prevent automatic simplification.
			wp_set_post_lock( $copied_post_id );

			// get object of copy.
			$copy_post_obj = new Post_Object( $copied_post_id );

			// run pagebuilder-specific tasks.
			$pagebuilder_obj->update_object( $copy_post_obj );

			// return the new object.
			return $copy_post_obj;
		}

		// return false if no simplified object has been created.
		return false;
	}

	/**
	 * Set automatic mode prevention on object.
	 *
	 * @param bool $prevent_automatic_mode True if the automatic mode should be prevented for this object.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 **/
	public function set_automatic_mode_prevented( bool $prevent_automatic_mode ): void {
		update_post_meta( $this->get_id(), 'easy_language_prevent_automatic_mode', $prevent_automatic_mode );
	}

	/**
	 * Return language-specific title for the type of the given object.
	 *
	 * @return string
	 */
	public function get_type_name(): string {
		$object_type_settings = Init::get_instance()->get_post_type_settings();
		$object_type_name     = 'page';
		if ( ! empty( $object_type_settings[ $this->get_type() ] ) ) {
			$object_type_name = $object_type_settings[ $this->get_type() ]['label_singular'];
		}
		return $object_type_name;
	}

	/**
	 * Return public URL for this object.
	 *
	 * @return string
	 */
	public function get_link(): string {
		// get the term URL.
		$url = get_permalink( $this->get_id() );

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
		// get the page builder used for this object.
		$page_builder = $this->get_page_builder();
		if ( $page_builder ) {
			// return its custom edit link.
			return $page_builder->get_edit_link();
		}

		// return the default edit link as fallback.
		$edit_post_link = get_edit_post_link( $this->get_id() );

		// bail if post link could not be loaded.
		if ( ! is_string( $edit_post_link ) ) {
			return '';
		}

		// return the post-edit link.
		return $edit_post_link;
	}

	/**
	 * Call object-specific trigger after processed simplification.
	 *
	 * @return void
	 */
	protected function process_simplification_trigger_on_end(): void {
		// trigger object-update.
		do_action( 'save_post_' . $this->get_type(), $this->get_id(), $this->get_object_as_object(), true );
	}
}
