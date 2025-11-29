<?php
/**
 * File for our own texts-handling.
 *
 * @noinspection PhpUndefinedClassInspection
 * @package easy-language
 */

namespace easyLanguage\EasyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Apis;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Languages;
use easyLanguage\Plugin\Log;
use Gettext\Translation;
use Gettext\Translations;
use WP_Post;
use Gettext\Generator\PoGenerator;

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
		$this->db = Db::get_instance();
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
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize text-specific hooks.
	 *
	 * @param Init $init The init-object.
	 * @return void
	 */
	public function init( Init $init ): void {

		// add single object for simplification.
		add_action( 'admin_action_easy_language_add_simplification', array( $this, 'add_post_object_to_simplification' ) );
		add_action( 'admin_action_easy_language_add_simplification_term', array( $this, 'add_term_object_to_simplification' ) );

		// get simplification of given object.
		add_action( 'admin_action_easy_language_get_simplification', array( $this, 'get_post_simplification' ) );
		add_action( 'admin_action_easy_language_get_term_simplification', array( $this, 'get_term_simplification' ) );

		// get simplification of given text.
		add_action( 'admin_action_easy_language_get_simplification_of_entry', array( $this, 'get_simplification_of_entry' ) );

		// export simplified texts.
		add_action( 'admin_action_easy_language_export_simplifications', array( $this, 'export_simplifications' ) );

		// some term specific hooks.
		add_action( 'easy_language_replace_texts', array( $this, 'replace_term_texts' ), 10, 4 );
		add_filter( 'get_terms_args', array( $this, 'hide_simplified_terms' ) );

		// check texts in updated post-types-objects.
		foreach ( $init->get_supported_post_types() as $post_type => $enabled ) {
			add_action( 'save_post_' . $post_type, array( $this, 'update_simplification_of_post' ), 10, 3 );
		}

		// if object is trashed.
		add_action( 'wp_trash_post', array( $this, 'trash_object' ) );

		// if object is untrashed.
		add_action( 'untrashed_post', array( $this, 'untrash_object' ) );

		// delete simplifications if object is really deleted.
		add_action( 'before_delete_post', array( $this, 'delete_post_object' ) );
		add_action( 'pre_delete_term', array( $this, 'pre_delete_term_object' ), 10, 2 );
		add_action( 'delete_term', array( $this, 'delete_term_object' ), 10, 3 );
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
	public function add_post_object_to_simplification(): void {
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
			if ( $copy_post_obj instanceof Post_Object ) {
				// Log event.
				Log::get_instance()->add_log( __( 'New simplification object created: ', 'easy-language' ) . $copy_post_obj->get_title(), 'success' );

				// get the page builder object.
				$page_builder_obj = $copy_post_obj->get_page_builder();

				// if no page builder could be loaded, forward to referer.
				if ( ! $page_builder_obj ) {
					wp_safe_redirect( wp_get_referer() );
					exit;
				}

				// forward user to the edit-page of the newly created object.
				wp_safe_redirect( $page_builder_obj->get_edit_link() );
				exit;
			}

			// Log event.
			Log::get_instance()->add_log( __( 'Error during creating of new simplification object based on ', 'easy-language' ) . $post_obj->get_title(), 'error' );

		}

		// Log event.
		Log::get_instance()->add_log( __( 'Faulty request to create new simplified object.', 'easy-language' ), 'error' );

		// redirect user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Add term for simplification via request.
	 *
	 * The given object will be copied. All texts are added as texts to simplify.
	 *
	 * The author will after this be able to simplify this object manually or via API.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function add_term_object_to_simplification(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-add-simplification-term', 'nonce' );

		// get active api.
		$api_object = Apis::get_instance()->get_active_api();

		// get target-language.
		$target_language = isset( $_GET['language'] ) ? sanitize_text_field( wp_unslash( $_GET['language'] ) ) : '';

		// get term id.
		$original_term_id  = isset( $_GET['term'] ) ? absint( $_GET['term'] ) : 0;
		$original_taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : '';

		if ( $original_term_id > 0 && ! empty( $target_language ) && $api_object ) {
			// get term-object.
			$term_obj = new Term_Object( $original_term_id, $original_taxonomy );
			$term_obj->add_simplification_object( $target_language, $api_object, false );
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
	public function delete_post_object( int $post_id ): void {
		// get the object.
		$post_obj = new Post_Object( $post_id );

		// bail if post type is not supported.
		if ( ! Init::get_instance()->is_post_type_supported( $post_obj->get_type() ) ) {
			return;
		}

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

			// delete the text-entries of the deleted object.
			foreach ( $post_obj->get_entries() as $entry ) {
				$entry->delete( $post_id );
			}

			// Log event.
			Log::get_instance()->add_log( __( 'Deleted simplified object ', 'easy-language' ) . '<i>' . $post_title . '</i>', 'success' );
		} else {
			// get all simplified objects for this original and delete them also.
			foreach ( $post_obj->get_simplifications( true ) as $simplified_post_id ) {
				wp_delete_post( $simplified_post_id, true );
			}
		}
	}

	/**
	 * Cleanup meta of parent-term on deletion of simplified term.
	 *
	 * @param int    $term_id The term-ID.
	 * @param string $taxonomy The taxonomy-name.
	 *
	 * @return void
	 */
	public function pre_delete_term_object( int $term_id, string $taxonomy ): void {
		// get term-object.
		$term_object = new Term_Object( $term_id, $taxonomy );

		// if this is a translated object, reset the changed-marker on its original
		// and cleanup db.
		if ( $term_object->is_simplified() ) {
			$original_post = new Term_Object( $term_object->get_original_object_as_int(), $taxonomy );
			$languages     = $term_object->get_language();
			$language_code = array_key_first( $languages );
			if ( ! empty( $language_code ) ) {
				$original_post->remove_language( $language_code );
				$original_post->remove_changed_marker( $language_code );
			}
		}
	}

	/**
	 * Delete term simplifications.
	 *
	 * @param int    $term_id The term-ID.
	 * @param int    $tt_id The taxonomy-ID.
	 * @param string $taxonomy The taxonomy-name.
	 *
	 * @return void
	 */
	public function delete_term_object( int $term_id, int $tt_id, string $taxonomy ): void {
		// get entries by this term.
		$entries = Db::get_instance()->get_entries(
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
	 * Get simplification via API if one is available via admin_action-click.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function get_post_simplification(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-get-simplification', 'nonce' );

		// get api.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( ! $api_obj ) {
			// no api active => do nothing and forward user.
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// get object id.
		$object_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		// get object type.
		$object_type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';

		if ( $object_id > 0 ) {
			// Log event.
			/* translators: %1$d will be replaced by an ID, %2$s by a type name. */
			Log::get_instance()->add_log( sprintf( __( 'Request to simplify object %1$d (%2$s) without JS.', 'easy-language' ), absint( $object_id ), $object_type ), 'success' );

			// run simplification of this object.
			$object = Helper::get_object( $object_id, $object_type );

			// run simplification only if object could be loaded.
			if ( $object instanceof Objects ) {
				$object->process_simplifications( $api_obj->get_simplifications_obj(), $api_obj->get_active_language_mapping() );
			}
		}

		// redirect user back to editor.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Get simplification via API if one is active.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function get_term_simplification(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-get-term-simplification', 'nonce' );

		// get api.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( false === $api_obj ) {
			// no api active => do nothing and forward user.
			wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
			exit;
		}

		// get object id.
		$object_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		// get taxonomy.
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : '';

		if ( $object_id > 0 && ! empty( $taxonomy ) ) {
			// run simplification of this object.
			$term_obj = new Term_Object( $object_id, $taxonomy );
			$term_obj->process_simplifications( $api_obj->get_simplifications_obj(), $api_obj->get_active_language_mapping() );
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
			wp_safe_redirect( wp_get_referer() );
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
			$entries = Db::get_instance()->get_entries( $query, array(), 1 );

			// bail if we have no results.
			if ( empty( $entries ) ) {
				// Log event.
				/* translators: %1$s will be replaced by an ID. */
				Log::get_instance()->add_log( sprintf( __( 'Requested object %1$d could not be found for simplification.', 'easy-language' ), $entry_id ), 'error' );

				// redirect user back.
				wp_safe_redirect( wp_get_referer() );
				exit;
			}

			// get entry.
			$entry = $entries[0];

			// get the objects where this text is been used.
			$post_objects = $entry->get_objects();

			// bail if no objects could be found.
			if ( empty( $post_objects ) ) {
				// Log event.
				/* translators: %1$s will be replaced by an ID. */
				Log::get_instance()->add_log( sprintf( __( 'Requested object %1$d could not be found for simplification.', 'easy-language' ), $entry_id ), 'error' );

				// redirect user back.
				wp_safe_redirect( wp_get_referer() );
				exit;
			}

			// get object of the first one.
			$object = Helper::get_object( absint( $post_objects[0]['object_id'] ), $post_objects[0]['object_type'] );

			// bail if none could be found.
			if ( false === $object ) {
				// Log event.
				/* translators: %1$s will be replaced by an ID. */
				Log::get_instance()->add_log( sprintf( __( 'Requested object %1$d could not be found for simplification.', 'easy-language' ), $entry_id ), 'error' );

				// redirect user back.
				wp_safe_redirect( wp_get_referer() );
				exit;
			}

			// call simplification for each text on this object.
			$object->process_simplification( $api_obj->get_simplifications_obj(), $api_obj->get_mapping_languages(), $entry );
		}

		// redirect user back to editor.
		wp_safe_redirect( wp_get_referer() );
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

		// get the post type.
		$post_type = get_post_type( $post_id );

		// bail if post type could not be loaded.
		if ( ! $post_type ) {
			return;
		}

		// Log event.
		Log::get_instance()->add_log( __( 'Check updated ', 'easy-language' ) . '<i>' . $post_obj->get_title() . '</i>', 'success' );

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
						if ( ! empty( Db::get_instance()->get_entries( $filter ) ) ) {
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

			// bail if page builder could not be loaded.
			if ( ! $pagebuilder_obj ) {
				return;
			}

			// set object-id to pagebuilder-object.
			$pagebuilder_obj->set_object_id( $post_id );

			// set original title to simplify in pagebuilder-object.
			$pagebuilder_obj->set_title( $post_obj->get_title() );

			// set original text to simplify in pagebuilder-object.
			$pagebuilder_obj->set_text( $post_obj->get_content() );

			// get source language from original object.
			$parent_post_obj  = new Post_Object( $post_obj->get_original_object_as_int() );
			$source_languages = $parent_post_obj->get_language();

			// bail if list is empty.
			if ( empty( $source_languages ) ) {
				return;
			}

			// get the first language as source language.
			$source_language = (string) array_key_first( $source_languages );

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

					// get the simplification.
					$simplification = trim( $entry->get_simplification( $target_language ) );

					// TODO $parse_texts genauer debuggen: wie kommt man an den zu löschenden Text darin?
					if ( false === $entry->is_field( 'title' )
						&& false === in_array( $simplification, $parsed_texts, true ) // @phpstan-ignore function.impossibleType,identical.alwaysTrue
						&& false === in_array( $entry->get_original(), $parsed_texts, true ) // @phpstan-ignore function.impossibleType,identical.alwaysTrue
					) {
						$entry->delete();
					} elseif ( $simplification !== $title && false !== $entry->is_field( 'title' ) ) {
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
				// also check if this is a simplified text of the given language.
				if ( ( false === $original_text_obj ) && false === $this->db->get_entry_by_simplification( trim( $text['text'] ), $source_language ) ) {
					// if not save the text for simplification.
					$original_text_obj = $this->db->add( $text['text'], $source_language, 'post_content', $text['html'] );

					// bail if text could not be added.
					if ( ! $original_text_obj ) {
						return;
					}

					// set object and state on Text object.
					$original_text_obj->set_object( $post_type, $post_obj->get_id(), $index, $pagebuilder_obj->get_name() );
					$original_text_obj->set_state( 'in_use' );
				}
			}

			// check if the title has already saved as original text for simplification.
			$original_title_obj = $this->db->get_entry_by_text( $title, $source_language );
			// also check if this is a simplified text of the given language.
			if ( ( false === $original_title_obj ) && false === $this->db->get_entry_by_simplification( trim( $title ), $source_language ) ) {
				// save the text for simplification.
				$original_title_obj = $this->db->add( $title, $source_language, 'title', false );

				// bail if text could not be added.
				if ( ! $original_title_obj ) {
					return;
				}

				// set object and state on Text object.
				$original_title_obj->set_object( $post_type, $post_id, 0, $pagebuilder_obj->get_name() );
				$original_title_obj->set_state( 'in_use' );
			}

			// remove changed-marker on original object for each language.
			$original_post = new Post_Object( $post_obj->get_original_object_as_int() );
			foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
				$original_post->remove_changed_marker( $language_code );
			}

			// log event.
			/* translators: %1$s will be replaced by a title. */
			Log::get_instance()->add_log( sprintf( __( 'Update of %1$s has been processed.', 'easy-language' ), $post_obj->get_title() ), 'success' );
		}
	}

	/**
	 * If simplified object is moved to trash, update the settings on its original object.
	 *
	 * If an original object is moved to trash, also trash any simplified object from this object.
	 *
	 * @param int $post_id The post-ID.
	 *
	 * @return void
	 */
	public function trash_object( int $post_id ): void {
		// get the object.
		$post_obj = new Post_Object( $post_id );

		// bail if post type is not supported.
		if ( ! Init::get_instance()->is_post_type_supported( $post_obj->get_type() ) ) {
			return;
		}

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
			/* translators: %1$s will be replaced by a title. */
			Log::get_instance()->add_log( sprintf( __( '%1$s has been moved to trash and cleaned up.', 'easy-language' ), '<i>' . $post_obj->get_title() . '</i>' ), 'success' );
		} else {
			// get all simplified objects for this original and move them also to trash.
			foreach ( $post_obj->get_simplifications() as $simplified_post_id ) {
				wp_delete_post( $simplified_post_id );
			}
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

		// bail if post type is not supported.
		if ( ! Init::get_instance()->is_post_type_supported( $post_obj->get_type() ) ) {
			return;
		}

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

		// if this is a simplifiable object, untrash all simplified object of this object.
		if ( $post_obj->is_simplifiable() ) {
			foreach ( $post_obj->get_simplifications( true ) as $simplified_post_id ) {
				wp_untrash_post( $simplified_post_id );
			}
		}
	}

	/**
	 * Return all texts in DB without any filter.
	 *
	 * @return array<Text>
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
			wp_safe_redirect( wp_get_referer() );
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
			$entries = Db::get_instance()->get_entries( $query );

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
			Log::get_instance()->add_log( __( 'Simplifications exported.', 'easy-language' ), 'success' );

			// get WP Filesystem-handler.
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
			global $wp_filesystem;

			// return header.
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: inline; filename="' . sanitize_file_name( gmdate( 'YmdHi' ) . '_' . get_option( 'blogname' ) . '.po"' ) );
			header( 'Content-Length: ' . strlen( $po ) );
			echo $wp_filesystem->get_contents( $po ); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}

		// redirect user back.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Replace texts in terms.
	 *
	 * @param Text                           $text The text-object.
	 * @param string                         $target_language The target language as string.
	 * @param int                            $object_id The object or the term we want to change.
	 * @param array<int,array<string,mixed>> $simplification_objects The objects in the term where the change should happen.
	 *
	 * @return void
	 */
	public function replace_term_texts( Text $text, string $target_language, int $object_id, array $simplification_objects ): void {
		// check for nonce.
		if ( isset( $_POST['easy-language-verify'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['easy-language-verify'] ) ), 'submit-application' ) ) {
			return;
		}

		// get log object.
		$log = Log::get_instance();

		// get taxonomy from request.
		$taxonomy = isset( $_REQUEST['type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['type'] ) ) : '';

		/**
		 * Replace content depending on the field.
		 */
		foreach ( $simplification_objects as $simplification_object ) {
			switch ( $simplification_object['field'] ) {
				// simplify the title of the term.
				case 'taxonomy_title':
					// get title.
					$title = $text->get_simplification( $target_language );

					// set query for update.
					$query = array(
						'name' => $title,
					);

					// run update.
					$result = wp_update_term( $object_id, $taxonomy, $query );
					if ( is_wp_error( $result ) ) {
						$log->add_log( 'Error during term simplification: ' . $result->get_error_message(), 'error' );
						return;
					}
					break;

				// simplify the description of the term.
				case 'taxonomy_description':
					// get description.
					$description = $text->get_simplification( $target_language );

					// set query for update.
					$query = array(
						'description' => $description,
					);

					// run update.
					$result = wp_update_term( $object_id, $taxonomy, $query );
					if ( is_wp_error( $result ) ) {
						$log->add_log( 'Error during term simplification: ' . $result->get_error_message(), 'error' );
						return;
					}
					break;
			}
		}
	}

	/**
	 * Hide our simplified terms in queries for terms in backend.
	 *
	 * @param array<string,mixed> $args The arguments for this query.
	 *
	 * @return array<string,mixed>
	 */
	public function hide_simplified_terms( array $args ): array {
		// check for nonce.
		if ( isset( $_POST['easy-language-verify'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['easy-language-verify'] ) ), 'submit-application' ) ) {
			return $args;
		}

		// bail if we are not in wp-admin.
		if ( ! is_admin() ) {
			return $args;
		}

		// bail if taxonomy is not set in argument.
		if ( empty( $args['taxonomy'][0] ) ) {
			return $args;
		}

		// bail if action was called.
		if ( ! empty( $_GET['action'] ) ) {
			return $args;
		}

		if ( array_key_exists( $args['taxonomy'][0], Init::get_instance()->get_supported_taxonomies() ) ) {
			// get all simplified terms.
			$query = array(
				'hide_empty'   => false,
				'meta_key'     => 'easy_language_simplification_original_id',
				'meta_compare' => 'EXISTS',
				'fields'       => 'ids',
			);

			// add them to the actual query.
			$args['exclude'] = get_terms( $query );
		}

		// return resulting arguments.
		return $args;
	}
}
