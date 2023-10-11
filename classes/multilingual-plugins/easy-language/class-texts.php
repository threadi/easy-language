<?php
/**
 * File for our own texts-handling.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

use easyLanguage\Apis;
use easyLanguage\Helper;
use easyLanguage\Languages;
use WP_Post;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper for any text-handling.
 */
class Texts {


	/**
	 * Instance of this object.
	 *
	 * @var ?Texts
	 */
	private static ?Texts $instance = null;

	/**
	 * DB-object.
	 *
	 * @var Db
	 */
	private Db $db;

	/**
	 * Init-object.
	 *
	 * @var Init
	 */
	private Init $init;

	/**
	 * Constructor for this object.
	 */
	private function __construct() {
		$this->db = DB::get_instance();
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Texts {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Initialize text-specific hooks.
	 *
	 * @param Init $init The init-object.
	 * @return void
	 */
	public function init( Init $init ): void {
		$this->init = $init;

		// add object for translation.
		add_action( 'admin_action_easy_language_add_translation', array( $this, 'add_object_to_translate' ) );

		// get automatic translation of given object.
		add_action( 'admin_action_easy_language_get_automatic_translation', array( $this, 'get_automatic_translation' ) );

		// delete given translation by request.
		add_action( 'admin_action_easy_language_delete_translation', array( $this, 'delete_translation' ) );

		// check updated post-types.
		foreach ( $this->init->get_supported_post_types() as $post_type => $enabled ) {
			add_action( 'save_post_' . $post_type, array( $this, 'update_translation_of_post' ), 10, 3 );
		}

		// if object is trashed.
		add_action( 'wp_trash_post', array( $this, 'trash_object' ) );

		// delete translations if object is really deleted.
		add_action( 'delete_post', array( $this, 'delete_translation_of_post' ) );
		add_action( 'delete_term', array( $this, 'delete_translation_of_term' ), 10, 3 );
	}

	/**
	 * Add object for translation via request.
	 *
	 * The given object will be copied. All texts are added as texts to translate.
	 *
	 * The author will after this be able to translate this object manually or via API.
	 *
	 * @return void
	 */
	public function add_object_to_translate(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-add-translation', 'nonce' );

		// get post id.
		$original_post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		// get target-language.
		$target_language = isset( $_GET['language'] ) ? sanitize_text_field( wp_unslash( $_GET['language'] ) ) : '';

		if ( $original_post_id > 0 && ! empty( $target_language ) ) {
			// get post-object.
			$post_obj = new Post_Object( $original_post_id );

			// check if this object is already translated in this language.
			if ( false === $post_obj->is_translated_in_language( $target_language ) ) {
				// get the source-language.
				$source_language = get_post_meta( $original_post_id, 'easy_language_text_language', true );
				if ( empty( $source_language ) ) {
					$source_language = Helper::get_wp_lang();
				}

				// get array with post-data of the original.
				$post_array = $post_obj->get_object_as_array();

				// remove some settings.
				unset( $post_array['ID'] );
				unset( $post_array['page_template'] );
				unset( $post_array['guid'] );

				// set author to actual user.
				$post_array['post_author'] = get_current_user_id();

				// add the copy.
				$copied_post_id = wp_insert_post( $post_array );

				// copy taxonomies and post-meta.
				helper::copy_cpt( $original_post_id, $copied_post_id );

				// mark the copied post as translation-object of the original.
				update_post_meta( $copied_post_id, 'easy_language_translation_original_id', $original_post_id );

				// save the source-language of the copied object.
				update_post_meta( $copied_post_id, 'easy_language_source_language', $source_language );

				// save the target-language of the copied object.
				update_post_meta( $copied_post_id, 'easy_language_translation_language', $target_language );

				// parse text depending on used pagebuilder for this object.
				$pagebuilder_obj = $post_obj->get_page_builder();
				$pagebuilder_obj->set_object_id( $copied_post_id );
				$pagebuilder_obj->set_title( $post_obj->get_title() );
				$pagebuilder_obj->set_text( $post_obj->get_content() );

				// loop through the resulting texts and add each one for translation.
				foreach ( $pagebuilder_obj->get_parsed_texts() as $text ) {
					// check if the text is already saved as original text for translation.
					$original_text_obj = $this->db->get_entry_by_text( $text, $source_language );
					if ( false === $original_text_obj ) {
						// save the text for translation.
						$original_text_obj = $this->db->add( $text, $source_language, 'post_content' );
					}
					$original_text_obj->set_object( get_post_type( $copied_post_id ), $copied_post_id, $pagebuilder_obj->get_name() );
				}

				// check if the title has already saved as original text for translation.
				$original_title_obj = $this->db->get_entry_by_text( $pagebuilder_obj->get_title(), $source_language );
				if ( false === $original_title_obj ) {
					// save the text for translation.
					$original_title_obj = $this->db->add( $pagebuilder_obj->get_title(), $source_language, 'title' );
				}
				$original_title_obj->set_object( get_post_type( $copied_post_id ), $copied_post_id, $pagebuilder_obj->get_name() );

				// add this language as translated language to original post.
				$post_obj->add_translated_language( $target_language );

				// set marker to reset permalinks.
				Rewrite::get_instance()->set_refresh();

				// get object of copy.
				$copy_post_obj = new Post_Object( $copied_post_id );

				// run pagebuilder-specific tasks.
				$pagebuilder_obj->update_object( $copy_post_obj );

				// forward user to the edit-page of the newly created object.
				wp_safe_redirect( $copy_post_obj->get_page_builder()->get_edit_link() );
				exit;
			}
		}

		// get term id.
		$original_term_id  = isset( $_GET['term'] ) ? absint( $_GET['term'] ) : 0;
		$original_taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : '';

		if ( $original_term_id > 0 && ! empty( $target_language ) ) {
			// get term-object.
			$term_obj = new Term_Object( $original_term_id, $original_taxonomy );

			// check if this object is already translated in this language.
			if ( false === $term_obj->is_translated_in_language( $target_language ) ) {
				// get the source-language.
				$source_language = get_term_meta( $original_term_id, 'easy_language_text_language', true );
				if ( empty( $source_language ) ) {
					$source_language = Helper::get_wp_lang();
				}

				// get the original-term as array.
				$term = get_term( $original_term_id, $term_obj->get_taxonomy_name(), ARRAY_A );

				// remove unnecessary fields.
				unset( $term['term_id'] );
				unset( $term['name'] );
				unset( $term['taxonomy'] );

				// save the copy.
				$result = wp_insert_term( $term_obj->get_name() . '-2', $term_obj->get_taxonomy_name(), $term );
				if ( ! is_wp_error( $result ) ) {
					// get the ID of the copy.
					$copied_term_id = $result['term_id'];

					// mark the copied term as translation-object of the original.
					update_term_meta( $copied_term_id, 'easy_language_translation_original_id', $original_term_id );

					// save the source-language of the copied object.
					update_term_meta( $copied_term_id, 'easy_language_source_language', $source_language );

					// save the target-language of the copied object.
					update_term_meta( $copied_term_id, 'easy_language_translation_language', $target_language );

					// get name and description as translatable texts.
					$title       = get_term_field( 'name', $copied_term_id, $original_taxonomy );
					$description = get_term_field( 'description', $copied_term_id, $original_taxonomy );

					// set this texts as translatable texts.
					foreach ( array(
						'taxonomy_title'       => $title,
						'taxonomy_description' => $description,
					) as $field => $text ) {
						// check if the text is already saved as original text for translation.
						$original_text_obj = $this->db->get_entry_by_text( $text, $source_language );
						if ( false === $original_text_obj ) {
							// save the text for translation.
							$original_text_obj = $this->db->add( $text, $source_language, $field );
						}
						if ( $original_text_obj instanceof Text ) {
							$original_text_obj->set_object( $term_obj->get_taxonomy_name(), $copied_term_id, '' );
						}
					}

					// add this language as translated language to original term.
					$term_obj->add_translated_language( $target_language );

					// set marker to reset permalinks.
					Rewrite::get_instance()->set_refresh();

					// get object of copy.
					$copy_term_obj = new Term_Object( $copied_term_id, $original_taxonomy );

					// forward user to the edit-page of the newly created object.
					wp_safe_redirect( $copy_term_obj->get_edit_link() );
					exit;
				}
			}
		}

		// redirect user.
		wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
	}

	/**
	 * Delete translations of a post if it will be deleted.
	 *
	 * @param int $post_id The ID of this post.
	 * @return void
	 */
	public function delete_translation_of_post( int $post_id ): void {
		// get entries by post-type.
		$entries = $this->db->get_entries(
			array(
				'object_id'   => $post_id,
				'object_type' => get_post_type( $post_id ),
			)
		);

		// delete them.
		foreach ( $entries as $entry ) {
			$entry->delete();
		}
	}

	/**
	 * Delete translations of a term if it will be deleted.
	 *
	 * @param int    $term_id The term-ID.
	 * @param int    $tt_id The taxonomy-term-ID.
	 * @param string $taxonomy The taxonomy.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function delete_translation_of_term( int $term_id, int $tt_id, string $taxonomy ): void {
		// get entries by this term.
		$entries = $this->db->get_entries(
			array(
				'object_id'   => $term_id,
				'object_type' => $taxonomy,
			)
		);

		// delete them.
		foreach ( $entries as $entry ) {
			$entry->delete();
		}
	}

	/**
	 * Get automatic translation via API if one is available.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function get_automatic_translation(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-get-automatic-translation', 'nonce' );

		// get api.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( false === $api_obj ) {
			// no api active => do nothing and forward user.
			wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
			exit;
		}

		// get object id.
		$object_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		// get taxonomy, if set.
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : '';

		if ( $object_id > 0 ) {
			// run translation of this object.
			$this->init->process_translations( $api_obj->get_translations_obj(), $api_obj->get_active_language_mapping(), $object_id, $taxonomy );
		}

		// redirect user back to editor.
		wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
		exit;
	}

	/**
	 * Check updated translatable post-type-object.
	 *
	 * @param int     $post_id The Post-ID.
	 * @param WP_Post $post The post-object.
	 * @param bool    $update Marker if this is an update (true) or not (false).
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function update_translation_of_post( int $post_id, WP_Post $post, bool $update ): void {
		// bail if this is not an update to prevent confusing during creation of translation-objects.
		if ( false === $update ) {
			return;
		}

		// get the object.
		$post_obj = new Post_Object( $post_id );

		// if this is an original object, check its contents.
		if ( $post_obj->is_translatable() ) {
			// parse text depending on used pagebuilder.
			$obj = $post_obj->get_page_builder();

			// set object-id to pagebuilder-object.
			$obj->set_object_id( $post_obj->get_id() );

			// set original text to translate in pagebuilder-object.
			$obj->set_text( $post_obj->get_content() );

			// get all translations for this object in all active languages.
			foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
				$translated_post_id = $post_obj->get_translated_in_language( $language_code );

				// loop through the resulting texts and check if the text has been changed (aka: is not available in translation-db).
				foreach ( $obj->get_parsed_texts() as $text ) {
					$filter = array(
						'object_id'   => $translated_post_id,
						'object_type' => $post_obj->get_type(),
						'hash'        => $this->db->get_string_hash( $text ),
					);
					if ( empty( Db::get_instance()->get_entries( $filter ) ) ) {
						// mark the object as changed as translated content has been changed or new content has been added in the given language.
						$post_obj->mark_as_changed_in_language( $language_code );
					}
				}
			}
		}

		// if this is a translated object, reset the changed-marker on its original.
		if ( $post_obj->is_translated() ) {
			$original_post = new Post_Object( $post_obj->get_original_object_as_int() );
			// get all translations for this object in all active languages.
			foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
				$original_post->remove_changed_marker( $language_code );
			}
		}
	}

	/**
	 * If translated object is moved to trash, update the settings on its original object.
	 *
	 * @param int $post_id The post-ID.
	 *
	 * @return void
	 */
	public function trash_object( int $post_id ): void {
		// get the object.
		$post_obj = new Post_Object( $post_id );

		// if this is a translated object, reset the changed-marker on its original
		// and cleanup db.
		if ( $post_obj->is_translated() ) {
			$original_post = new Post_Object( $post_obj->get_original_object_as_int() );
			$languages     = $post_obj->get_language();
			$language_code = array_key_first( $languages );
			$original_post->remove_translated_language( $language_code );
			$original_post->remove_changed_marker( $language_code );
		}
	}

	/**
	 * Delete the requested translation.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function delete_translation(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-delete-translation', 'nonce' );

		// get requested text.
		$text_id = ! empty( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( $text_id > 0 ) {
			$text_obj = new Text( $text_id );
			$text_obj->delete();
		}

		// redirect user back to list.
		wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
		exit;
	}
}
