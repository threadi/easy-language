<?php
/**
 * File for our own texts-handling.
 *
 * @noinspection PhpUndefinedClassInspection
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

use easyLanguage\Apis;
use easyLanguage\Helper;
use easyLanguage\Languages;
use easyLanguage\Log;
use Gettext\Translation;
use Gettext\Translations;
use WP_Post;
use Gettext\Generator\PoGenerator;

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

		// add single object for simplification.
		add_action( 'admin_action_easy_language_add_simplification', array( $this, 'add_object_to_simplification' ) );

		// get simplification of given object.
		add_action( 'admin_action_easy_language_get_simplification', array( $this, 'get_simplification' ) );

		// get simplification of given text.
		add_action( 'admin_action_easy_language_get_simplification_of_entry', array( $this, 'get_simplification_of_entry' ) );

		// export simplified texts.
		add_action( 'admin_action_easy_language_export_simplifications', array( $this, 'export_simplifications' ) );

		// check texts in updated post-types-objects.
		foreach ( $init->get_supported_post_types() as $post_type => $enabled ) {
			add_action( 'save_post_' . $post_type, array( $this, 'update_simplification_of_post' ), 10, 3 );
		}

		// if object is trashed.
		add_action( 'wp_trash_post', array( $this, 'trash_object' ) );

		// if object is untrashed.
		add_filter( 'untrashed_post', array( $this, 'untrash_object' ), 10, 2 );

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
			$post_obj      = new Post_Object( $original_post_id );
			$copy_post_obj = $post_obj->add_simplification_object( $target_language, $api_object, false );
			if ( $copy_post_obj ) {
				// Log event.
				Log::get_instance()->add_log( 'New simplification object created: ' . $copy_post_obj->get_title(), 'success' );

				// forward user to the edit-page of the newly created object.
				wp_safe_redirect( $copy_post_obj->get_page_builder()->get_edit_link() );
				exit;
			}

			// Log event.
			Log::get_instance()->add_log( 'Error during creating of new simplification object based on ' . $post_obj->get_title(), 'error' );

		}

		// Log event.
		Log::get_instance()->add_log( 'Faulty request to create new simplified object.', 'error' );

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

		// secure the title.
		$post_title = $post_obj->get_title();

		// if this is a simplified object, clean it up.
		if ( $post_obj->is_simplified() ) {
			/**
			 * Remove the object from the simplification-marker arrays.
			 */
			$post_obj->cleanup_simplification_marker( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING );
			$post_obj->cleanup_simplification_marker( EASY_LANGUAGE_OPTION_SIMPLIFICATION_MAX );
			$post_obj->cleanup_simplification_marker( EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT );
			$post_obj->cleanup_simplification_marker( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS );

			// get original post.
			$original_post = new Post_Object( $post_obj->get_original_object_as_int() );

			// cleanup language marker on original post, if it does not have any simplification.
			if ( false === $original_post->has_simplifications() ) {
				delete_post_meta( $original_post->get_id(), 'easy_language_text_language' );
			}
		}

		// delete the text-entries of the deleted object.
		foreach ( $post_obj->get_entries() as $entry ) {
			$entry->delete( $post_id );
		}

		// Log event.
		Log::get_instance()->add_log( 'Deleted simplified object <i>' . $post_title . '</i>', 'success' );
	}

	/**
	 * Get simplification via API if one is available via admin_action-click.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function get_simplification(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-get-simplification', 'nonce' );

		// get api.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( false === $api_obj ) {
			// no api active => do nothing and forward user.
			wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
			exit;
		}

		// get object id.
		$object_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		// get object type.
		$object_type = isset( $_GET['type'] ) ? absint( $_GET['type'] ) : '';

		if ( $object_id > 0 ) {
			// Log event.
			Log::get_instance()->add_log( 'Request to simplify object ' . absint( $object_id ) . ' (' . $object_type . ') without JS.', 'success' );

			// run simplification of this object.
			$object = Helper::get_object( $object_id, $object_type );
			$object->process_simplifications( $api_obj->get_simplifications_obj(), $api_obj->get_active_language_mapping() );
		}

		// redirect user back to editor.
		wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
		exit;
	}

	/**
	 * Run simplification of single text via link-request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function get_simplification_of_entry(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-get-simplification-of-entry', 'nonce' );

		// get api.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( false === $api_obj ) {
			// no api active => do nothing and forward user.
			wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
			exit;
		}

		// get text id.
		$entry_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( $entry_id > 0 ) {
			// get the requested text.
			$query   = array(
				'id'         => $entry_id,
				'not_locked' => true,
			);
			$entries = DB::get_instance()->get_entries( $query, array(), 1 );

			// bail if we have no results.
			if ( empty( $entries ) ) {
				// Log event.
				Log::get_instance()->add_log( 'Requested object ' . $entry_id . ' could not be found for simplification.', 'error' );

				wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
				exit;
			}

			// get entry.
			$entry = $entries[0];

			// get the objects where this text is been used.
			$post_objects = $entry->get_objects();

			// bail if no objects could be found.
			if ( empty( $post_objects ) ) {
				// Log event.
				Log::get_instance()->add_log( 'Requested object ' . $entry_id . ' could not be found for simplification. #2', 'error' );

				wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
				exit;
			}

			// get object of the first one.
			$object = Helper::get_object( absint( $post_objects[0]['object_id'] ), $post_objects[0]['object_type'] );

			// bail if none could be found.
			if ( false === $object ) {
				// Log event.
				Log::get_instance()->add_log( 'Requested object ' . $entry_id . ' could not be found for simplification. #3', 'error' );

				wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
				exit;
			}

			// call simplification for each text on this object.
			$object->process_simplification( $api_obj->get_simplifications_obj(), $api_obj->get_mapping_languages(), $entry );
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

		// Log event.
		Log::get_instance()->add_log( 'Check updated <i>' . $post_obj->get_title() . '</i>', 'success' );

		// if this is an original object, check its contents.
		if ( $post_obj->is_simplifiable() ) {
			// parse text depending on used pagebuilder.
			$pagebuilder_obj = $post_obj->get_page_builder();

			// only get texts if pagebuilder is known.
			if ( false !== $pagebuilder_obj ) {
				// set object-id to pagebuilder-object.
				$pagebuilder_obj->set_object_id( $post_obj->get_id() );

				// set original text to simplify in pagebuilder-object.
				$pagebuilder_obj->set_text( $post_obj->get_content() );

				// get all simplifications for this object in all active languages.
				foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
					$translated_post_id = $post_obj->get_simplification_in_language( $language_code );

					// loop through the resulting texts and check if the text has been changed (aka: is not available in translation-db).
					foreach ( $pagebuilder_obj->get_parsed_texts() as $text ) {
						// bail if text is empty.
						if ( empty( $text['text'] ) ) {
							continue;
						}

						$filter = array(
							'object_id'   => $translated_post_id,
							'object_type' => $post_obj->get_type(),
							'hash'        => $this->db->get_string_hash( $text['text'] ),
						);
						if ( empty( Db::get_instance()->get_entries( $filter ) ) ) {
							// mark the object as changed as translated content has been changed or new content has been added in the given language.
							$post_obj->mark_as_changed_in_language( $language_code );
						}
					}
				}
			}
		}

		// if this is a simplified object, update the simplified contents and reset the changed-marker on its original.
		if ( $post_obj->is_simplified() ) {
			// bail if a simplification of this object is actual running.
			$running_simplifications = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, array() );
			if ( ! empty( $running_simplifications[ $post_obj->get_md5() ] ) && absint( $running_simplifications[ $post_obj->get_md5() ] ) > 0 ) {
				return;
			}

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

			// get target language from simplified object.
			$target_languages = $post_obj->get_language();
			$target_language  = array_key_first( $target_languages );

			// get the actual title from object.
			$title = $pagebuilder_obj->get_title();

			// get parsed texts from object.
			$parsed_texts = $pagebuilder_obj->get_parsed_texts();

			// delete in DB existing texts of its object is not part of the actual content.
			// also check for their simplifications.
			$query   = array(
				'object_id'   => $post_id,
				'object_type' => $post_obj->get_type(),
				'lang'        => $source_language,
			);
			$entries = $this->db->get_entries( $query );
			if ( ! empty( $target_language ) && ! empty( $entries ) ) {
				foreach ( $entries as $entry ) {
					// do nothing if this is a simplified text.
					if ( $entry->has_simplification_in_language( $target_language ) ) {
						continue;
					}
					if ( false === $entry->is_field( 'title' ) &&
						false === in_array( trim( $entry->get_simplification( $target_language ) ), $parsed_texts, true )
						&& false === in_array( $entry->get_original(), $parsed_texts, true )
					) {
						$entry->delete();
					} elseif ( false !== $entry->is_field( 'title' )
						&& trim( $entry->get_simplification( $target_language ) !== $title )
					) {
						$entry->delete();
					}
				}
			}

			// loop through the resulting texts and compare them with the existing texts in object.
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
				$original_text_obj = $this->db->get_entry_by_text( $text['text'], $source_language );
				if ( false === $original_text_obj ) {
					// also check if this is a simplified text of the given language.
					if ( false === $this->db->get_entry_by_simplification( trim( $text['text'] ), $source_language ) ) {
						// if not save the text for simplification.
						$original_text_obj = $this->db->add( $text['text'], $source_language, 'post_content', $text['html'] );
						$original_text_obj->set_object( get_post_type( $post_obj->get_id() ), $post_obj->get_id(), $index, $pagebuilder_obj->get_name() );
						$original_text_obj->set_state( 'in_use' );
					}
				}
			}

			// check if the title has already saved as original text for simplification.
			$original_title_obj = $this->db->get_entry_by_text( $title, $source_language );
			if ( false === $original_title_obj ) {
				// also check if this is a simplified text of the given language.
				if ( false === $this->db->get_entry_by_simplification( trim( $title ), $source_language ) ) {
					// save the text for simplification.
					$original_title_obj = $this->db->add( $title, $source_language, 'title', false );
					$original_title_obj->set_object( get_post_type( $post_id ), $post_id, 0, $pagebuilder_obj->get_name() );
					$original_title_obj->set_state( 'in_use' );
				}
			}

			// remove changed-marker on original object for each language.
			$original_post = new Post_Object( $post_obj->get_original_object_as_int() );
			foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
				$original_post->remove_changed_marker( $language_code );
			}

			// Log event.
			Log::get_instance()->add_log( 'Update of ' . $post_obj->get_title() . ' has been processed.', 'success' );
		}
	}

	/**
	 * If simplified object is moved to trash, update the settings on its original object.
	 *
	 * @param int $post_id The post-ID.
	 *
	 * @return void
	 */
	public function trash_object( int $post_id ): void {
		// get the object.
		$post_obj = new Post_Object( $post_id );

		// if this is a simplified object, reset the changed-marker on its original and cleanup db.
		if ( $post_obj->is_simplified() ) {
			$original_post = new Post_Object( $post_obj->get_original_object_as_int() );
			$languages     = $post_obj->get_language();
			$language_code = array_key_first( $languages );
			if ( ! empty( $language_code ) ) {
				// remove language from list of translated languages on original post.
				$original_post->remove_language( $language_code );

				// remove changed marker on original post.
				$original_post->remove_changed_marker( $language_code );
			}

			// Log event.
			Log::get_instance()->add_log( '<i>' . $post_obj->get_title() . '</i> has been moved to trash and cleaned up.', 'success' );
		}
	}

	/**
	 * If simplified object is removed from trash, update the settings on its original object.
	 *
	 * @param int $post_id The post-ID.
	 *
	 * @return void
	 */
	public function untrash_object( int $post_id ): void {
		// get the object.
		$post_obj = new Post_Object( $post_id );

		// if this is a simplified object, reset the changed-marker on its original and cleanup db.
		if ( $post_obj->is_simplified() ) {
			$original_post = new Post_Object( $post_obj->get_original_object_as_int() );
			$languages     = $post_obj->get_language();
			$language_code = array_key_first( $languages );
			if ( ! empty( $language_code ) ) {
				// add language from list of translated languages on original post.
				$original_post->add_language( $language_code );
			}

			// Log event.
			Log::get_instance()->add_log( $post_obj->get_title() . ' has been removed from trash.', 'success' );
		}
	}

	/**
	 * Return all texts in DB without any filter.
	 *
	 * @return array
	 */
	public function get_texts(): array {
		return $this->db->get_entries();
	}

	/**
	 * Export simplified texts as po file.
	 *
	 * @return void
	 * @noinspection PhpUndefinedClassInspection
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function export_simplifications(): void {
		// check nonce.
		if ( ( isset( $_REQUEST['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'easy-language-export-simplifications' ) ) || empty( $_REQUEST['nonce'] ) ) {
			// redirect user back.
			wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
			exit;
		}

		// get language to export.
		$export_language_code = isset( $_GET['lang'] ) ? sanitize_text_field( wp_unslash( $_GET['lang'] ) ) : '';

		if ( ! empty( $export_language_code ) && class_exists( 'Translations' ) ) {

			// define query for entries.
			$query = array(
				'state'            => 'in_use',
				'object_not_state' => 'trash',
				'lang'             => $export_language_code,
				'target_lang'      => $export_language_code,
			);

			// return resulting entry-objects.
			$entries = DB::get_instance()->get_entries( $query );

			// define translations-object which will be exported as po-file.
			$translations = Translations::create( get_option( 'blogname' ) );
			$translations->setDescription( __( 'List of with Easy Language simplified texts.', 'easy-language' ) );
			$translations->getHeaders()->set( 'Last-Translator', get_option( 'admin_email' ) );
			$translations->getHeaders()->set( 'X-Generator', Helper::get_plugin_name() );

			// add each entry to the translation object.
			foreach ( $entries as $entry ) {
				$translation = Translation::create( '', $entry->get_original() );
				foreach ( $entry->get_target_languages() as $language_code => $language ) {
					if ( ! $translation->isTranslated() ) {
						$translation->translate( trim( $entry->get_simplification( $language_code ) ) );
					}
				}
				$translations->add( $translation );
			}

			// get the resulting po-file as string.
			$po_generator = new PoGenerator();
			$po           = $po_generator->generateString( $translations );

			$replace_from = '"Last-Translator';
			$po           = str_replace( $replace_from, '"Language: ' . $export_language_code . '\n"' . PHP_EOL . $replace_from, $po );

			// Log event.
			Log::get_instance()->add_log( 'Simplifications exported.', 'success' );

			// get WP Filesystem-handler.
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
			global $wp_filesystem;

			// return header.
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: inline; filename="' . sanitize_file_name( gmdate( 'YmdHi' ) . '_' . get_option( 'blogname' ) . '.po"' ) );
			header( 'Content-Length: ' . strlen( $po ) );
			echo $wp_filesystem->get_contents( $po );
			exit;
		}

		// redirect user back.
		wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
		exit;
	}
}
