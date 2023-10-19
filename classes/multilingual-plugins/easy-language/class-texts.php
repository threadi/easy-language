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

		// add object for simplification.
		add_action( 'admin_action_easy_language_add_simplification', array( $this, 'add_object_to_simplification' ) );

		// get automatic simplification of given object.
		add_action( 'admin_action_easy_language_get_automatic_simplification', array( $this, 'get_automatic_simplification' ) );

		// check texts in updated post-types-objects.
		foreach ( $init->get_supported_post_types() as $post_type => $enabled ) {
			add_action( 'save_post_' . $post_type, array( $this, 'update_simplification_of_post' ), 10, 3 );
		}

		// if object is trashed.
		add_action( 'wp_trash_post', array( $this, 'trash_object' ) );

		// delete simplifications if object is really deleted.
		add_action( 'before_delete_post', array( $this, 'delete_object' ) );
	}

	/**
	 * Add post-object for simplification via request.
	 *
	 * The given object will be copied. All texts are added as texts to simplify.
	 *
	 * The author will after this be able to simplify this object manually or via API.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function add_object_to_simplification(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-add-simplification', 'nonce' );

		// get active api.
		$api_object = Apis::get_instance()->get_active_api();

		// get post id.
		$original_post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		// get target-language.
		$target_language = isset( $_GET['language'] ) ? sanitize_text_field( wp_unslash( $_GET['language'] ) ) : '';

		if ( $original_post_id > 0 && ! empty( $target_language ) && $api_object ) {
			// get post-object.
			$post_obj = new Post_Object( $original_post_id );

			// check if this object is already translated in this language.
			if ( false === $post_obj->is_translated_in_language( $target_language ) ) {
				// get the source-language.
				$source_language = helper::get_wp_lang();
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
				update_post_meta( $copied_post_id, 'easy_language_simplification_original_id', $original_post_id );

				// save the source-language of the copied object.
				update_post_meta( $copied_post_id, 'easy_language_source_language', $source_language );

				// save the target-language of the copied object.
				update_post_meta( $copied_post_id, 'easy_language_simplification_language', $target_language );

				// save the API used for this simplification.
				update_post_meta( $copied_post_id, 'easy_language_api', $api_object->get_name() );

				// ste the language for the original object.
				update_post_meta( $original_post_id, 'easy_language_text_language', $source_language );

				// parse text depending on used pagebuilder for this object.
				$pagebuilder_obj = $post_obj->get_page_builder();
				$pagebuilder_obj->set_object_id( $copied_post_id );
				$pagebuilder_obj->set_title( $post_obj->get_title() );
				$pagebuilder_obj->set_text( $post_obj->get_content() );

				// loop through the resulting texts and add each one for simplification.
				foreach ( $pagebuilder_obj->get_parsed_texts() as $text ) {
					// bail if text is empty.
					if( empty($text) ) {
						continue;
					}

					// check if the text is already saved as original text for simplification.
					$original_text_obj = $this->db->get_entry_by_text( $text, $source_language );
					if ( false === $original_text_obj ) {
						// save the text for simplification.
						$original_text_obj = $this->db->add( $text, $source_language, 'post_content' );
					}
					$original_text_obj->set_object( get_post_type( $copied_post_id ), $copied_post_id, $pagebuilder_obj->get_name() );
					$original_text_obj->set_state( 'to_simplify' );
				}

				// check if the title has already saved as original text for simplification.
				$original_title_obj = $this->db->get_entry_by_text( $pagebuilder_obj->get_title(), $source_language );
				if ( false === $original_title_obj ) {
					// save the text for simplification.
					$original_title_obj = $this->db->add( $pagebuilder_obj->get_title(), $source_language, 'title' );
				}
				$original_title_obj->set_object( get_post_type( $copied_post_id ), $copied_post_id, $pagebuilder_obj->get_name() );
				$original_title_obj->set_state( 'to_simplify' );

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

		// redirect user.
		wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
		exit;
	}

	/**
	 * Delete simplification of a post if it will be deleted.
	 *
	 * Not limited to supported post-types to clean up data even after settings has been changed.
	 *
	 * @param int $post_id The ID of this post.
	 * @return void
	 */
	public function delete_object( int $post_id ): void {
		// get the object.
		$post_obj = new Post_Object( $post_id );

		// if this is a translated object, clean it up.
		if ( $post_obj->is_translated() ) {
			/**
			 * Remove the object from the simplification-marker arrays.
			 */
			$post_obj->cleanup_simplification_marker( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING );
			$post_obj->cleanup_simplification_marker( EASY_LANGUAGE_OPTION_SIMPLIFICATION_MAX );
			$post_obj->cleanup_simplification_marker( EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT );
			$post_obj->cleanup_simplification_marker( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS );

			// get original post.
			$original_post = new Post_Object( $post_obj->get_original_object_as_int() );

			// cleanup language marker on original post, if it does not have any translations.
			if( false === $original_post->has_translations() ) {
				delete_post_meta( $original_post->get_id(), 'easy_language_text_language' );
			}
		}

		// delete the text-entries of the deleted object.
		foreach ( $post_obj->get_entries() as $entry ) {
			$entry->delete( $post_id );
		}
	}

	/**
	 * Get simplification via API if one is available.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function get_automatic_simplification(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-get-automatic-simplification', 'nonce' );

		// get api.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( false === $api_obj ) {
			// no api active => do nothing and forward user.
			wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
			exit;
		}

		// get object id.
		$object_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( $object_id > 0 ) {
			// run simplification of this object.
			$post_obj = new Post_Object( $object_id );
			$post_obj->process_simplifications( $api_obj->get_simplifications_obj(), $api_obj->get_active_language_mapping() );
		}

		// redirect user back to editor.
		wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
		exit;
	}

	/**
	 * Check an updated post-type-object regarding its simplifications.
	 *
	 * @param int     $post_id The Post-ID.
	 * @param WP_Post $post The post-object.
	 * @param bool    $update Marker if this is an update (true) or not (false).
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function update_simplification_of_post( int $post_id, WP_Post $post, bool $update ): void {
		// bail if this is not an update to prevent confusing during creation of simplification-objects.
		if ( false === $update ) {
			return;
		}

		// get the object.
		$post_obj = new Post_Object( $post_id );

		// if this is an original object, check its contents.
		if ( $post_obj->is_translatable() ) {
			// parse text depending on used pagebuilder.
			$pagebuilder_obj = $post_obj->get_page_builder();

			// only get texts if pagebuilder is known.
			if( false !== $pagebuilder_obj ) {
				// set object-id to pagebuilder-object.
				$pagebuilder_obj->set_object_id( $post_obj->get_id() );

				// set original text to simplify in pagebuilder-object.
				$pagebuilder_obj->set_text( $post_obj->get_content() );

				// get all simplifications for this object in all active languages.
				foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
					$translated_post_id = $post_obj->get_translated_in_language( $language_code );

					// loop through the resulting texts and check if the text has been changed (aka: is not available in translation-db).
					foreach ( $pagebuilder_obj->get_parsed_texts() as $text ) {
						// bail if text is empty.
						if ( empty( $text ) ) {
							continue;
						}

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
		}

		// if this is a translated object, update the translatable contents and reset the changed-marker on its original.
		if ( $post_obj->is_translated() ) {
			// bail if a simplification of this object is actual running.
			$running_simplifications = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, array() );
			if( !empty($running_simplifications[$post_obj->get_md5()]) && absint($running_simplifications[$post_obj->get_md5()]) > 0 ) {
				return;
			}

			/**
			 * Ermittle alle aktuellen Texte im Objekt.
			 * Prüfe pro Text, ob dieser bereits vorhanden und mit dem Objekt verbunden ist.
			 * Wenn nein, ergänze ihn als neuen zu vereinfachenden Text und verbinde ihn mit dem Objekt.
			 * Wenn ja, mache nichts.
			 *
			 * TODO
			 * Ermittle alle Original-Texte des Objektes in der DB, die von diesem - auch in ihrer Vereinfachung - nicht mehr genutzt werden.
			 * Nur wenn deren Vereinfachung (wenn vorhanden) von keinem anderen Objekt genutzt wird.
			 */

			// parse text depending on used pagebuilder for this object.
			$pagebuilder_obj = $post_obj->get_page_builder();

			// set object-id to pagebuilder-object.
			$pagebuilder_obj->set_object_id( $post_id );

			// set original title to simplify in pagebuilder-object.
			$pagebuilder_obj->set_title( $post_obj->get_title() );

			// set original text to simplify in pagebuilder-object.
			$pagebuilder_obj->set_text( $post_obj->get_content() );

			// get source language from original object.
			$parent_post_obj  = new Post_Object( $post_obj->get_original_object_as_int() );
			$source_languages = $parent_post_obj->get_language();
			$source_language  = array_key_first( $source_languages );

			// get target language from object.
			$target_languages = $post_obj->get_language();
			$target_language  = array_key_first( $target_languages );

			// get parsed texts from object.
			$parsed_texts = $pagebuilder_obj->get_parsed_texts();

			// delete in DB existing texts of this object which are not part of the actual content.
			// also check for their simplifications.
			$entries = $this->db->get_entries( array( 'object_id' => $post_id, 'lang' => $source_language ) );
			if( !empty($target_language) && !empty($entries) ) {
				foreach ( $entries as $entry ) {
					if ( false === in_array( trim( $entry->get_translation( $target_language ) ), $parsed_texts, true ) && false === in_array( $entry->get_original(), $parsed_texts, true ) ) {
						$entry->delete();
					}
				}
			}

			// loop through the resulting texts and compare them with the existing texts in object.
			foreach ( $pagebuilder_obj->get_parsed_texts() as $text ) {
				// bail if text is empty.
				if( empty($text) ) {
					continue;
				}

				// check if the text is already saved as original text for simplification.
				$original_text_obj = $this->db->get_entry_by_text( $text, $source_language );
				if ( false === $original_text_obj ) {
					// also check if this is a simplified text of the given language.
					if( false === $this->db->get_entry_by_simplification( trim($text), $source_language ) ) {
						// if not save the text for simplification.
						$original_text_obj = $this->db->add( $text, $source_language, 'post_content' );
						$original_text_obj->set_object( get_post_type( $post_obj->get_id() ), $post_obj->get_id(), $pagebuilder_obj->get_name() );
						$original_text_obj->set_state( 'to_simplify' );
					}
				}
			}

			// check if the title has already saved as original text for simplification.
			$title = $pagebuilder_obj->get_title();
			$original_title_obj = $this->db->get_entry_by_text( $title, $source_language );
			if ( false === $original_title_obj ) {
				// also check if this is a simplified text of the given language.
				if( false === $this->db->get_entry_by_simplification( trim($title), $source_language ) ) {
					// save the text for simplification.
					$original_title_obj = $this->db->add( $title, $source_language, 'title' );
					$original_title_obj->set_object( get_post_type( $post_id ), $post_id, $pagebuilder_obj->get_name() );
					$original_title_obj->set_state( 'to_simplify' );
				}
			}

			// remove changed-marker on original object.
			$original_post = new Post_Object( $post_obj->get_original_object_as_int() );
			// get all simplifications for this object in all active languages.
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
			if ( ! empty( $language_code ) ) {
				// remove language from list of translated languages on original post.
				$original_post->remove_translated_language( $language_code );

				// remove changed marker on original post.
				$original_post->remove_changed_marker( $language_code );
			}
		}
	}

	/**
	 * Return all texts.
	 *
	 * @return array
	 */
	public function get_texts(): array {
		return $this->db->get_entries();
	}
}
