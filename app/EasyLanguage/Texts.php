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

use easyLanguage\Dependencies\easyTransientsForWordPress\Transients;
use easyLanguage\Plugin\Api_Base;
use easyLanguage\Plugin\Apis;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Languages;
use easyLanguage\Plugin\Log;
use easyLanguage\Plugin\Settings;
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
	 * Marker whether we are actually searching for simplified terms.
	 *
	 * @var bool
	 */
	private bool $hiding_terms = false;

	/**
	 * List of simplified term IDs per taxonomies.
	 *
	 * @var array<string,array<int,int>>
	 */
	private array $simplified_terms = array();

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

		// add a single object for simplification.
		add_action( 'admin_action_easy_language_add_simplification', array( $this, 'add_post_object_to_simplification_by_request' ) );
		add_action( 'admin_action_easy_language_add_simplification_term', array( $this, 'add_term_object_to_simplification_by_request' ) );

		// get simplification of a given object.
		add_action( 'admin_action_easy_language_get_simplification', array( $this, 'get_post_simplification_by_request' ) );
		add_action( 'admin_action_easy_language_get_term_simplification', array( $this, 'get_term_simplification_by_request' ) );

		// get simplification of a given text.
		add_action( 'admin_action_easy_language_get_simplification_of_entry', array( $this, 'get_simplification_of_entry_by_request' ) );

		// export simplified texts.
		add_action( 'admin_action_easy_language_export_simplifications', array( $this, 'export_simplifications_by_request' ) );

		// some term-specific hooks.
		add_action( 'easy_language_replace_texts', array( $this, 'replace_term_texts' ), 10, 4 );
		add_filter( 'get_terms_args', array( $this, 'hide_simplified_terms' ) );

		// check texts in updated post-types-objects.
		foreach ( $init->get_supported_post_types() as $post_type => $enabled ) {
			add_action( 'save_post_' . $post_type, array( $this, 'update_simplification_of_post' ), 10, 3 );
		}

		// if the object is trashed.
		add_action( 'wp_trash_post', array( $this, 'trash_object' ) );

		// if the object is untrashed.
		add_action( 'untrashed_post', array( $this, 'untrash_object' ) );

		// delete simplifications if the object is deleted.
		add_action( 'before_delete_post', array( $this, 'delete_post_object' ) );
		add_action( 'pre_delete_term', array( $this, 'pre_delete_term_object' ), 10, 2 );
		add_action( 'delete_term', array( $this, 'delete_term_object' ), 10, 3 );
	}

	/**
	 * Add the post-object for simplification via request.
	 *
	 * The given object will be copied. All texts are added as texts to simplify.
	 *
	 * The author will after this be able to simplify this object manually or via API.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function add_post_object_to_simplification_by_request(): void {
		// get post ID.
		$original_post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		// check nonce.
		check_admin_referer( 'easy-language-add-simplification-' . $original_post_id, 'nonce' );

		// get active api.
		$api_object = Apis::get_instance()->get_active_api();

		// bail if capability is not granted.
		if ( ! current_user_can( 'edit_el_simplifier' ) || ! Init::get_instance()->current_user_can_edit_object( $original_post_id, (string) get_post_type( $original_post_id ) ) ) {
			wp_die(
				esc_html__( 'You are not allowed to simplify this object.', 'easy-language' ),
				'',
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}

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

				// if no page builder could be loaded, forward to the referer.
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
	 * Add a term for simplification via request.
	 *
	 * The given object will be copied. All texts are added as texts to simplify.
	 *
	 * The author will after this be able to simplify this object manually or via API.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function add_term_object_to_simplification_by_request(): void {
		// get term id.
		$original_term_id  = isset( $_GET['term'] ) ? absint( $_GET['term'] ) : 0;
		$original_taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : '';

		// check nonce.
		check_admin_referer( 'easy-language-add-simplification-term-' . $original_term_id, 'nonce' );

		// bail if capability is not granted or the taxonomy is not supported.
		if ( ! current_user_can( 'edit_el_simplifier' ) || ! Init::get_instance()->current_user_can_edit_object( $original_term_id, $original_taxonomy ) ) {
			wp_die(
				esc_html__( 'You are not allowed to simplify this object.', 'easy-language' ),
				'',
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}

		// get active api.
		$api_object = Apis::get_instance()->get_active_api();

		// get target-language.
		$target_language = isset( $_GET['language'] ) ? sanitize_text_field( wp_unslash( $_GET['language'] ) ) : '';

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
	 * Not limited to supported post-types to clean up data even after settings have been changed.
	 *
	 * @param int $post_id The ID of this post.
	 * @return void
	 */
	public function delete_post_object( int $post_id ): void {
		// bail if this is a revision, as they are never simplified or simplifiable objects.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

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

			// remove the language from the list of simplified languages on the original post (it is also deleted
			// without the trash before, e.g. via wp_delete_post( $post_id, true )).
			$language_code = array_key_first( $post_obj->get_language() );
			if ( ! empty( $language_code ) ) {
				$original_post->remove_language( $language_code );
				$original_post->remove_changed_marker( $language_code );
			}

			// clean up the language marker on the original post if it does not have any simplification.
			if ( false === $original_post->has_simplifications() ) {
				delete_post_meta( $original_post->get_id(), 'easy_language_text_language' );
			}

			// delete the text-entries of the deleted object.
			foreach ( $post_obj->get_entries() as $entry ) {
				$entry->delete( $post_id, $post_obj->get_type() );
			}

			// Log event.
			Log::get_instance()->add_log( __( 'Deleted simplified object ', 'easy-language' ) . '<i>' . $post_title . '</i>', 'success' );
		} else {
			// get all simplified objects for this original and delete them too.
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

			// remove the object from the simplification-marker arrays.
			$term_object->cleanup_simplification_marker( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING );
			$term_object->cleanup_simplification_marker( EASY_LANGUAGE_OPTION_SIMPLIFICATION_MAX );
			$term_object->cleanup_simplification_marker( EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT );
			$term_object->cleanup_simplification_marker( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS );
		} else {
			// get all simplified terms for this original and delete them too.
			foreach ( $term_object->get_simplifications() as $simplified_term_id ) {
				wp_delete_term( $simplified_term_id, $taxonomy );
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
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function delete_term_object( int $term_id, int $tt_id, string $taxonomy ): void {
		// get entries by this term.
		$entries = Db::get_instance()->get_entries(
			array(
				'object_id'   => $term_id,
				'object_type' => $taxonomy,
			)
		);

		// delete only their connection to this term (the texts could also be used by other objects).
		foreach ( $entries as $entry ) {
			$entry->delete( $term_id, $taxonomy );
		}
	}

	/**
	 * Get simplification via API if one is available via admin_action-click.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function get_post_simplification_by_request(): void {
		// get object id.
		$object_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		// check nonce.
		check_admin_referer( 'easy-language-get-simplification-' . $object_id, 'nonce' );

		// get api.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( ! $api_obj ) {
			// no api active => do nothing and forward user.
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// get object type.
		$object_type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';

		// bail if capability is not granted.
		if ( ! current_user_can( 'edit_el_simplifier' ) || ! Init::get_instance()->current_user_can_edit_object( $object_id, $object_type ) ) {
			wp_die(
				esc_html__( 'You are not allowed to simplify this object.', 'easy-language' ),
				'',
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}

		// bail if the API is not configured or its quota is used up.
		if ( ! $this->is_api_usable_for_simplification( $api_obj ) ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		if ( $object_id > 0 ) {
			// Log event.
			/* translators: %1$d will be replaced by an ID, %2$s by a type name. */
			Log::get_instance()->add_log( sprintf( __( 'Request to simplify object %1$d (%2$s) without JS.', 'easy-language' ), absint( $object_id ), $object_type ), 'success' );

			// run simplification of this object.
			$object = Helper::get_object( $object_id, $object_type );

			// run simplification only if the object could be loaded.
			if ( $object instanceof Objects ) {
				$object->process_simplifications( $api_obj->get_simplifications_obj(), $api_obj->get_active_language_mapping() );
			}
		}

		// redirect user back to the editor.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Get simplification via API if one is active.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function get_term_simplification_by_request(): void {
		// get object id.
		$object_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		// get taxonomy.
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : '';

		// check nonce.
		check_admin_referer( 'easy-language-get-term-simplification-' . $object_id, 'nonce' );

		// bail if capability is not granted or the taxonomy is not supported.
		if ( ! current_user_can( 'edit_el_simplifier' ) || ! Init::get_instance()->current_user_can_edit_object( $object_id, $taxonomy ) ) {
			wp_die(
				esc_html__( 'You are not allowed to simplify this object.', 'easy-language' ),
				'',
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}

		// get api.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( false === $api_obj ) {
			// no api active => do nothing and forward user.
			wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
			exit;
		}

		// bail if the API is not configured or its quota is used up.
		if ( ! $this->is_api_usable_for_simplification( $api_obj ) ) {
			wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
			exit;
		}

		if ( $object_id > 0 && ! empty( $taxonomy ) ) {
			// run simplification of this object.
			$term_obj = new Term_Object( $object_id, $taxonomy );
			$term_obj->process_simplifications( $api_obj->get_simplifications_obj(), $api_obj->get_active_language_mapping() );
		}

		// redirect user back to the editor.
		wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
		exit;
	}

	/**
	 * Run simplification of a single text via link-request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function get_simplification_of_entry_by_request(): void {
		// get text id.
		$entry_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		// check nonce.
		check_admin_referer( 'easy-language-get-simplification-of-entry-' . $entry_id, 'nonce' );

		// get api.
		$api_obj = Apis::get_instance()->get_active_api();

		// bail if no api is active.
		if ( false === $api_obj ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// bail if no entry ID is given.
		if ( 0 === $entry_id ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// bail if capability is not granted.
		if ( ! current_user_can( 'edit_el_simplifier' ) ) {
			// redirect user.
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// bail if the API is not configured or its quota is used up.
		if ( ! $this->is_api_usable_for_simplification( $api_obj ) ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

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
			Log::get_instance()->add_log( sprintf( __( 'The requested object %1$d could not be found for simplification.', 'easy-language' ), $entry_id ), 'error' );

			// redirect user back.
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// get the entry.
		$entry = $entries[0];

		// get the objects where this text is used.
		$post_objects = $entry->get_objects();

		// bail if no objects could be found.
		if ( empty( $post_objects ) ) {
			// Log event.
			/* translators: %1$s will be replaced by an ID. */
			Log::get_instance()->add_log( sprintf( __( 'The requested object %1$d could not be found for simplification.', 'easy-language' ), $entry_id ), 'error' );

			// redirect user back.
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// bail if the user is not allowed to edit the object.
		if ( ! Init::get_instance()->current_user_can_edit_object( absint( $post_objects[0]['object_id'] ), $post_objects[0]['object_type'] ) ) {
			wp_die(
				esc_html__( 'You are not allowed to simplify this object.', 'easy-language' ),
				'',
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}

		// get the object of the first one.
		$object = Helper::get_object( absint( $post_objects[0]['object_id'] ), $post_objects[0]['object_type'] );

		// bail if none could be found.
		if ( false === $object ) {
			// Log event.
			/* translators: %1$s will be replaced by an ID. */
			Log::get_instance()->add_log( sprintf( __( 'The requested object %1$d could not be found for simplification.', 'easy-language' ), $entry_id ), 'error' );

			// redirect user back.
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// bail if a simplification of this object is already running (e.g. via JS), do not touch its marker.
		if ( $object->is_simplification_running() ) {
			// Log event.
			/* translators: %1$s will be replaced by a title. */
			Log::get_instance()->add_log( sprintf( __( 'Simplification of a text of %1$s canceled as a simplification of this object is already running.', 'easy-language' ), '<i>' . esc_html( $object->get_title() ) . '</i>' ), 'error' );

			// show hint.
			/* translators: %1$s will be replaced by the object-title */
			$this->add_simplification_error_hint( sprintf( __( 'Simplification for <i>%1$s</i> is already running.', 'easy-language' ), esc_html( $object->get_title() ) ) );

			// redirect user back.
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// mark simplification for this object as running.
		$object->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, time() );

		// call simplification for each text on this object.
		$object->process_simplification( $api_obj->get_simplifications_obj(), $api_obj->get_active_language_mapping(), $entry );

		// remove running marker to mark end of process.
		$object->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, 0 );

		// run the object-specific tasks after the simplification (e.g. update of the object).
		$object->run_simplification_end_trigger();

		// redirect user back to the editor.
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

		// get the post-type.
		$post_type = get_post_type( $post_id );

		// bail if post-type could not be loaded.
		if ( ! $post_type ) {
			return;
		}

		// Log event.
		Log::get_instance()->add_log( __( 'Check updated ', 'easy-language' ) . '<i>' . $post_obj->get_title() . '</i>', 'success' );

		// if this is an original object, check its contents.
		if ( $post_obj->is_simplifiable() ) {
			// parse text depending on used pagebuilder.
			$pagebuilder_obj = $post_obj->get_page_builder();

			// only get texts if the pagebuilder is known.
			if ( false !== $pagebuilder_obj ) {
				// set object-id to pagebuilder-object.
				$pagebuilder_obj->set_object_id( $post_obj->get_id() );

				// set original text to simplify in pagebuilder-object.
				$pagebuilder_obj->set_text( $post_obj->get_content() );

				// get the parsed texts only once.
				$parsed_texts = $pagebuilder_obj->get_parsed_texts();

				// get all simplifications for this object in all active languages.
				foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
					$translated_post_id = $post_obj->get_simplification_in_language( $language_code );

					// bail if no simplification exists in this language.
					if ( 0 === $translated_post_id ) {
						continue;
					}

					// loop through the resulting texts and check if the text has been changed (aka: is not available in translation-db).
					foreach ( $parsed_texts as $text ) {
						// bail if the text is empty.
						if ( empty( $text['text'] ) ) {
							continue;
						}

						$filter = array(
							'object_id'   => $translated_post_id,
							'object_type' => $post_obj->get_type(),
							'hash'        => $this->db->get_string_hash( $text['text'] ),
						);
						if ( empty( $this->db->get_entries( $filter, array(), 1 ) ) ) {
							// mark the object as changed as translated content has been changed or new content has been added in the given language.
							$post_obj->mark_as_changed_in_language( $language_code );

							// no further checks necessary for this language.
							break;
						}
					}
				}
			}
		}

		// if this is a simplified object, update the simplified contents and reset the changed-marker on its original.
		if ( $post_obj->is_simplified() ) {
			// bail if a simplification of this object is actually running.
			if ( $post_obj->is_simplification_running() ) {
				return;
			}

			// parse text depending on the used pagebuilder for this object.
			$pagebuilder_obj = $post_obj->get_page_builder();

			// bail if the page builder could not be loaded.
			if ( ! $pagebuilder_obj ) {
				return;
			}

			// set object-id to pagebuilder-object.
			$pagebuilder_obj->set_object_id( $post_id );

			// set the original title to simplify in pagebuilder-object.
			$pagebuilder_obj->set_title( $post_obj->get_title() );

			// set original text to simplify in pagebuilder-object.
			$pagebuilder_obj->set_text( $post_obj->get_content() );

			// get source language from an original object.
			$parent_post_obj  = new Post_Object( $post_obj->get_original_object_as_int() );
			$source_languages = $parent_post_obj->get_language();

			// bail if a list is empty.
			if ( empty( $source_languages ) ) {
				return;
			}

			// get the first language as the source language.
			$source_language = (string) array_key_first( $source_languages );

			// get target language from a simplified object.
			$target_languages = $post_obj->get_language();
			$target_language  = (string) array_key_first( $target_languages );

			// get the actual title from the object.
			$title = $pagebuilder_obj->get_title();

			// get parsed texts from the object.
			$parsed_texts = $pagebuilder_obj->get_parsed_texts();

			// get the list of the parsed texts as strings.
			$parsed_text_list = array_map( 'trim', array_map( 'strval', array_column( $parsed_texts, 'text' ) ) );

			// Delete it in DB existing texts of its object is not part of the actual content.
			// Also check for their simplifications.
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

					// do nothing if this text is not part of the parsed content (e.g. texts of ACF- or SCF-fields).
					if ( $this->is_text_to_keep_on_object_update( $entry, $post_obj ) ) {
						continue;
					}

					// get the simplification.
					$simplification = trim( $entry->get_simplification( $target_language ) );

					// delete the connection to this object if the text is not part of the actual content anymore.
					if ( false === $entry->is_field( 'title' )
						&& false === in_array( $simplification, $parsed_text_list, true )
						&& false === in_array( trim( $entry->get_original() ), $parsed_text_list, true )
					) {
						$entry->delete( $post_id, $post_obj->get_type() );
					} elseif ( $simplification !== $title && false !== $entry->is_field( 'title' ) ) {
						$entry->delete( $post_id, $post_obj->get_type() );
					}
				}
			}

			// collect the texts which are already known for this object (originals and their simplifications).
			$known_texts = array();
			foreach ( $this->db->get_entries( $query ) as $entry ) {
				$known_texts[] = trim( $entry->get_original() );
				if ( ! empty( $target_language ) ) {
					$known_texts[] = trim( $entry->get_simplification( $target_language ) );
				}
			}

			// loop through the resulting texts and compare them with the existing texts in the object.
			foreach ( $parsed_texts as $index => $text ) {
				// bail if the text is empty.
				if ( empty( $text['text'] ) ) {
					continue;
				}

				// bail if the text is already known for this object.
				if ( in_array( trim( $text['text'] ), $known_texts, true ) ) {
					continue;
				}

				// set html-marker to true if not set.
				if ( ! isset( $text['html'] ) ) {
					$text['html'] = true;
				}

				// check if the text is already saved as original text for simplification.
				$original_text_obj = $this->db->get_entry_by_text( $text['text'], $source_language );

				// also check if this is a simplified text of the given language.
				if ( false === $original_text_obj && false === $this->db->get_entry_by_simplification( trim( $text['text'] ), $target_language ) ) {
					// if not, save the text for simplification.
					$original_text_obj = $this->db->add( $text['text'], $source_language, 'post_content', $text['html'] );

					// bail if text could not be added.
					if ( ! $original_text_obj instanceof Text ) {
						return;
					}

					// set the object.
					$original_text_obj->set_object( $post_type, $post_obj->get_id(), $index, $pagebuilder_obj->get_name() );
				} elseif ( $original_text_obj instanceof Text && ! $this->is_text_linked_to_object( $original_text_obj, $post_id, $post_type ) && false === $this->db->get_entry_by_simplification( trim( $text['text'] ), $target_language ) ) {
					// the text is already known from another object: link it to this object and mark it to simplify.
					$original_text_obj->set_object( $post_type, $post_id, $index, $pagebuilder_obj->get_name() );
					if ( 'ignore' !== $original_text_obj->get_state() ) {
						$original_text_obj->set_state( 'to_simplify' );
					}
				}
			}

			// check if the title has already saved as original text for simplification.
			$original_title_obj = $this->db->get_entry_by_text( $title, $source_language );
			// also check if this is a simplified text of the given language.
			if ( false === $original_title_obj && false === $this->db->get_entry_by_simplification( trim( $title ), $target_language ) ) {
				// save the text for simplification.
				$original_title_obj = $this->db->add( $title, $source_language, 'title', false );

				// bail if text could not be added.
				if ( ! $original_title_obj instanceof Text ) {
					return;
				}

				// set the object.
				$original_title_obj->set_object( $post_type, $post_id, 0, $pagebuilder_obj->get_name() );
			} elseif ( $original_title_obj instanceof Text && ! $this->is_text_linked_to_object( $original_title_obj, $post_id, $post_type ) && false === $this->db->get_entry_by_simplification( trim( $title ), $target_language ) ) {
				// the title is already known from another object: link it to this object and mark it to simplify.
				$original_title_obj->set_object( $post_type, $post_id, 0, $pagebuilder_obj->get_name() );
				if ( 'ignore' !== $original_title_obj->get_state() ) {
					$original_title_obj->set_state( 'to_simplify' );
				}
			}

			// remove changed-marker on an original object for each language.
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
	 * Update the settings on its original object, if a simplified object is moved to trash
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

		// bail if post-type is not supported.
		if ( ! Init::get_instance()->is_post_type_supported( $post_obj->get_type() ) ) {
			return;
		}

		// if this is a simplified object, reset the changed-marker on its original and cleanup db.
		if ( $post_obj->is_simplified() ) {
			$original_post = new Post_Object( $post_obj->get_original_object_as_int() );
			$languages     = $post_obj->get_language();
			$language_code = array_key_first( $languages );
			if ( ! empty( $language_code ) ) {
				// remove language from a list of translated languages on the original post.
				$original_post->remove_language( $language_code );

				// remove changed marker on original post.
				$original_post->remove_changed_marker( $language_code );
			}

			// Log event.
			/* translators: %1$s will be replaced by a title. */
			Log::get_instance()->add_log( sprintf( __( '%1$s has been moved to trash and cleaned up.', 'easy-language' ), '<i>' . $post_obj->get_title() . '</i>' ), 'success' );
		} else {
			// get all simplified objects for this original and move them also to trash
			// (wp_delete_post() without force would delete objects of custom post types permanently).
			foreach ( $post_obj->get_simplifications() as $simplified_post_id ) {
				wp_trash_post( $simplified_post_id );
			}
		}
	}

	/**
	 * If a simplified object is removed from trash, update the settings on its original object.
	 *
	 * @param int $post_id The post-ID.
	 *
	 * @return void
	 */
	public function untrash_object( int $post_id ): void {
		// get the object.
		$post_obj = new Post_Object( $post_id );

		// bail if post-type is not supported.
		if ( ! Init::get_instance()->is_post_type_supported( $post_obj->get_type() ) ) {
			return;
		}

		// if this is a simplified object, reset the changed-marker on its original and cleanup db.
		if ( $post_obj->is_simplified() ) {
			$original_post = new Post_Object( $post_obj->get_original_object_as_int() );
			$languages     = $post_obj->get_language();
			$language_code = array_key_first( $languages );
			if ( ! empty( $language_code ) ) {
				// add language from the list of translated languages on the original post.
				$original_post->add_language( $language_code );
			}

			// Log event.
			Log::get_instance()->add_log( $post_obj->get_title() . ' has been removed from trash.', 'success' );

		}

		// if this is a simplifiable object, untrash all simplified objects of this object.
		if ( $post_obj->is_simplifiable() ) {
			foreach ( $post_obj->get_simplifications( true ) as $simplified_post_id ) {
				wp_untrash_post( $simplified_post_id );
			}
		}
	}

	/**
	 * Return whether the given API could be used for a simplification requested without JS.
	 *
	 * Shows a hint for the user if not.
	 *
	 * @param Api_Base $api_obj The API to use.
	 *
	 * @return bool
	 */
	private function is_api_usable_for_simplification( Api_Base $api_obj ): bool {
		// bail if the API is not configured.
		if ( false === $api_obj->is_configured() ) {
			/* translators: %1$s will be replaced by the API title. */
			$message = sprintf( __( 'The API %1$s is not configured. Nothing has been simplified.', 'easy-language' ), esc_html( $api_obj->get_title() ) );

			// Log event.
			Log::get_instance()->add_log( $message, 'error' );

			// show hint.
			$this->add_simplification_error_hint( $message );

			// return false as the API could not be used.
			return false;
		}

		// bail if the quota of the API is used up.
		if ( Helper::is_quota_used_up( $api_obj ) ) {
			/* translators: %1$s will be replaced by the API title. */
			$message = sprintf( __( '<strong>The quota of %1$s is used up.</strong><br>Nothing has been simplified.', 'easy-language' ), esc_html( $api_obj->get_title() ) );

			// Log event.
			Log::get_instance()->add_log( $message, 'error' );

			// show hint.
			$this->add_simplification_error_hint( $message );

			// return false as the API could not be used.
			return false;
		}

		// return true as the API could be used.
		return true;
	}

	/**
	 * Show an error hint for the user regarding a simplification requested without JS.
	 *
	 * @param string $message The message (could contain HTML).
	 *
	 * @return void
	 */
	private function add_simplification_error_hint( string $message ): void {
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_name( 'easy_language_simplification_not_possible' );
		$transient_obj->set_message( $message );
		$transient_obj->set_type( 'error' );
		$transient_obj->save();
	}

	/**
	 * Return whether the given text is linked to the given object.
	 *
	 * @param Text   $entry The text-object.
	 * @param int    $object_id The object-ID.
	 * @param string $object_type The object-type.
	 *
	 * @return bool
	 */
	private function is_text_linked_to_object( Text $entry, int $object_id, string $object_type ): bool {
		foreach ( $entry->get_objects() as $object ) {
			if ( absint( $object['object_id'] ) === $object_id && $object_type === $object['object_type'] ) {
				return true;
			}
		}

		// return false if the text is not linked to this object.
		return false;
	}

	/**
	 * Return whether the given text should be kept on a simplified object during its update, even if it is not part
	 * of the parsed texts of the object (e.g. texts of ACF- or SCF-fields).
	 *
	 * @param Text        $entry The text-object.
	 * @param Post_Object $post_obj The simplified object.
	 *
	 * @return bool
	 */
	private function is_text_to_keep_on_object_update( Text $entry, Post_Object $post_obj ): bool {
		// texts of ACF- and SCF-fields are not part of the parsed content.
		$keep = false;
		foreach ( $entry->get_objects() as $object ) {
			if ( str_starts_with( (string) $object['field'], 'acf_meta_' ) || str_starts_with( (string) $object['field'], 'scf_meta_' ) ) {
				$keep = true;
				break;
			}
		}

		/**
		 * Filter whether a text should be kept on a simplified object during its update, even if it is not part of
		 * the parsed texts of this object (e.g. texts from custom fields).
		 *
		 * @since 3.3.0 Available since 3.3.0.
		 * @param bool $keep True if the text should be kept.
		 * @param Text $entry The text-object.
		 * @param Post_Object $post_obj The simplified object.
		 */
		return (bool) apply_filters( 'easy_language_keep_text_on_object_update', $keep, $entry, $post_obj );
	}

	/**
	 * Return all texts in the database without any filter.
	 *
	 * @return array<Text>
	 */
	public function get_texts(): array {
		return $this->db->get_entries();
	}

	/**
	 * Export simplified texts as .po file.
	 *
	 * @return void
	 * @noinspection PhpUndefinedClassInspection
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function export_simplifications_by_request(): void {
		// check nonce.
		check_admin_referer( 'easy-language-export-simplifications', 'nonce' );

		// bail if capability is not granted.
		if ( ! current_user_can( Settings::get_instance()->get_settings_object()->get_capability() ) ) {
			// redirect user.
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// get language to export.
		$export_language_code = isset( $_GET['lang'] ) ? sanitize_text_field( wp_unslash( $_GET['lang'] ) ) : '';

		if ( ! empty( $export_language_code ) && class_exists( Translations::class ) ) {

			// define query for entries.
			$query = array(
				'state'            => 'in_use',
				'object_not_state' => 'trash',
				'lang'             => $export_language_code,
				'target_lang'      => $export_language_code,
			);

			// return resulting entry-objects.
			$entries = Db::get_instance()->get_entries( $query );

			// define the "Translations"-object from gettext, which will be exported as po-file.
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
			$wp_filesystem = Helper::get_wp_filesystem();

			// return header.
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: inline; filename="' . sanitize_file_name( gmdate( 'YmdHi' ) . '_' . get_option( 'blogname' ) . '.po"' ) );
			header( 'Content-Length: ' . strlen( $po ) );
			echo $po; // phpcs:ignore WordPress.Security.EscapeOutput -- generated PO file content sent as raw file download, not rendered as HTML.
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

		/**
		 * Replace content depending on the field.
		 */
		foreach ( $simplification_objects as $simplification_object ) {
			// bail if this entry is not for the requested object.
			if ( absint( $simplification_object['object_id'] ) !== $object_id ) {
				continue;
			}

			// get the taxonomy from the simplification object.
			$taxonomy = (string) $simplification_object['object_type'];

			// bail if this is not a taxonomy.
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			switch ( $simplification_object['field'] ) {
				// simplify the title of the term.
				case 'taxonomy_title':
					// get title.
					$title = $text->get_simplification( $target_language );

					// set the query for the update.
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

					// set the query for the update.
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
	 * Hide our simplified terms in queries for terms in the backend.
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

		// bail if given taxonomy is not supported.
		if ( ! array_key_exists( $args['taxonomy'][0], Init::get_instance()->get_supported_taxonomies() ) ) {
			return $args;
		}

		// bail if we are already searching for simplified terms (prevents recursion through our own query).
		if ( $this->hiding_terms ) {
			return $args;
		}

		// get the cache key for the requested taxonomies.
		$cache_key = implode( ',', array_map( 'strval', (array) $args['taxonomy'] ) );

		// get all simplified terms of the requested taxonomies, if not already loaded during this request.
		if ( ! isset( $this->simplified_terms[ $cache_key ] ) ) {
			$this->hiding_terms = true;
			$query              = array(
				'taxonomy'     => $args['taxonomy'],
				'hide_empty'   => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- the simplified terms are linked to their originals via term meta, there is no other way to query them
				'meta_key'     => 'easy_language_simplification_original_id',
				'meta_compare' => 'EXISTS',
				'fields'       => 'ids',
			);
			$simplified_terms   = get_terms( $query );
			$this->hiding_terms = false;

			// secure the result.
			$this->simplified_terms[ $cache_key ] = is_array( $simplified_terms ) ? array_map( 'absint', $simplified_terms ) : array();
		}

		// bail if no terms could be found.
		if ( empty( $this->simplified_terms[ $cache_key ] ) ) {
			return $args;
		}

		// add them to the actual query and keep existing excludes.
		// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- simplified terms must be hidden in term lists and are only identifiable via term meta; the list is cached per request
		$args['exclude'] = array_unique( array_merge( wp_parse_id_list( $args['exclude'] ?? array() ), $this->simplified_terms[ $cache_key ] ) );

		// return resulting arguments.
		return $args;
	}
}
