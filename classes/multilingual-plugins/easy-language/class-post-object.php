<?php
/**
 * File for initializing the easy-language-own simplifications.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

use easyLanguage\Api_Base;
use easyLanguage\Helper;
use easyLanguage\Languages;
use WP_Post;
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
		if ( get_post_meta( $this->get_id(), 'easy_language_simplification_original_id', true ) ) {
			$this->translate_type = 'translated';
		}
	}

	/**
	 * Return the object language depending on object type.
	 *
	 * @return array
	 */
	public function get_language(): array {
		$languages = Languages::get_instance()->get_active_languages();

		// if this is a translatable object, get only source languages.
		if ( 'translatable' === $this->translate_type ) {
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
		return absint( get_post_meta( $this->get_id(), 'easy_language_simplification_original_id', true ) );
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
		if ( false === $this->has_translations() ) {
			return false;
		}
		return $this->get_translated_in_language( $language ) > 0;
	}

	/**
	 * Return the post_id of the simplification of this object in a given language.
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
	 * Get WP-own post object as WP-object.
	 *
	 * @return WP_Post
	 */
	public function get_object_as_object(): WP_Post {
		return get_post( $this->get_id() );
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
	 * @param string $language_code The language-code.
	 *
	 * @return void
	 */
	public function remove_changed_marker( string $language_code ): void {
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
	 * Return the link to simplify the actual object via given api.
	 *
	 * @return string
	 */
	public function get_simplification_via_api_link(): string {
		return add_query_arg(
			array(
				'action' => 'easy_language_get_simplification',
				'id'     => $this->get_id(),
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
		return get_post_status( $this->get_id() );
	}

	/**
	 * Return quota-state of this object regarding a given api.
	 *
	 * Possible states:
	 * - ok => could be translated
	 * - above_limit => if characters of this object are more than the quota-limit
	 * - above_text_limit => if one text is above the text-limit from used API
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

		// get text-limit from API.
		$max_text_length = $api_obj->get_max_text_length();
		$max_text_length_exceeded = false;

		// get entry-limit from API.
		$entry_limit_per_minute = $api_obj->get_max_requests_per_minute();

		// get chars to translate.
		$filter  = array(
			'object_id' => $this->get_id(),
		);
		$entries = Db::get_instance()->get_entries( $filter );
		foreach ( $entries as $entry ) {
			$text_length =  absint( strlen( $entry->get_original() ) );
			$return_array['chars_count'] += $text_length;
			if( $text_length > $max_text_length ) {
				$max_text_length_exceeded = true;
			}
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

		// if max text limit is exceeded, show hint.
		if( $max_text_length_exceeded ) {
			$return_array['status'] = 'above_text_limit';
		}

		// if more entries used as API would perform per minute.
		if( count($entries) > $entry_limit_per_minute ) {
			$return_array['status'] = 'above_entry_limit';
		}

		// return ok.
		return $return_array;
	}

	/**
	 * Process multiple simplification of a single post-object.
	 *
	 * @param Object $simplification_obj The simplification-object.
	 * @param array  $language_mappings The language-mappings.
	 * @param int    $limit Limit the entries processed during this request.
	 * @param bool   $initialization Mark if this is the initialization of a simplification.
	 *
	 * @return int
	 */
	public function process_simplifications( object $simplification_obj, array $language_mappings, int $limit = 0, bool $initialization = true ): int {
		// get object-hash.
		$hash = $this->get_md5();

		// get object type name.
		$object_type_name = Helper::get_objekt_type_name( $this );

		// initialize the simplification.
		if ( false !== $initialization ) {
			$simplification_results = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, array() );
			if ( ! empty( $simplification_results[ $hash ] ) ) {
				// remove previous results.
				unset( $simplification_results[ $hash ] );
			}
			update_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, $simplification_results + array( $hash => __( 'Please wait ..', 'easy-language' ) ) );

			// do not run simplification if it is already running in another process for this object.
			$simplification_running = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, array() );
			if ( ! empty( $simplification_running[ $hash ] ) && absint( $simplification_running[ $hash ] ) > 0 ) {
				// set result.
				/* translators: %1$s will be replaced by the object-title */
				$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, sprintf( __( 'Simplification for <i>%1$s</i> is already running.', 'easy-language' ), esc_html( $this->get_title() ) ) );

				// return 0 as we have not simplified anything.
				return 0;
			}

			/**
			 * Determine all texts stored for the project that are still on "in_process".
			 * If there are, cancel the process and give the user a choice:
			 * - Go back to the failed simplifications.
			 * - Ignore and do not simplify
			 */
			// define filter for entry-loading to check max count of entries for this object.
			$filter = array(
				'object_id' => $this->get_id(),
				'state'     => 'processing',
			);

			// get entries which are in process.
			$entries_in_process = Db::get_instance()->get_entries( $filter );
			if ( ! empty( $entries_in_process ) ) {
				// set result.
				/* translators: %1$s will be replaced by the object-title */
				$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, sprintf( __( 'A previously running simplification of texts of this %1$s failed. How do you want to deal with it?<br><br><a href="#" class="button button-primary elementor-button" data-run-again="1">Run simplifications again</a> <a href="#" class="button button-primary elementor-button" data-ignore-texts="1">Ignore the failed simplifications</a>', 'easy-language' ), esc_html( $object_type_name ) ) );

				// return 0 as we have not simplified anything.
				return 0;
			}

			// mark simplification for this object as running.
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, time() );

			// define filter for entry-loading to check max count of entries for this object.
			$filter = array(
				'object_id' => $this->get_id(),
			);

			// get entries.
			$max_entries = Db::get_instance()->get_entries( $filter );

			// set max texts to translate.
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_MAX, count( $max_entries ) );

			// set counter for translated texts to 0.
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT, 0 );
		}

		// counter for simplifications during this run.
		$c = 0;

		// define filter to get all entries for this object which should be simplified.
		$filter = array(
			'object_id' => $this->get_id(),
			'state'     => 'to_simplify',
		);

		// get limited entries.
		$entries = Db::get_instance()->get_entries( $filter, $limit );

		// if no more texts to simplify found, break the process and show hint depending on progress.
		if ( empty( $entries ) ) {
			// get max value.
			$simplification_max = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_MAX, array() );

			// get actual count.
			$simplification_counts = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT, array() );

			// set result if count is 0.
			if ( isset( $simplification_counts[ $hash ] ) && 0 === absint( $simplification_counts[ $hash ] ) ) {
				/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the used API-title */
				$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, sprintf( __( '<strong>The texts in this %1$s are already simplified.</strong><br>%2$s was not used. Nothing has been changed.', 'easy-language' ), esc_html( $object_type_name ), esc_html( $simplification_obj->init->get_title() ) ) );
			} else {
				// otherwise show hint that some texts are already optimized.
				/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the used API-title */
				$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, sprintf( __( '<strong>Some texts in this %1$s are already simplified.</strong><br>Other missing simplifications has been run via %2$s and are insert into the text.', 'easy-language' ), esc_html( $object_type_name ), esc_html( $simplification_obj->init->get_title() ) ) );
			}

			// set max value as count.
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT, absint( $simplification_max[ $hash ] ) );

			// remove running marker to mark end of process.
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, 0 );

			// return max value.
			return absint( $simplification_max[ $hash ] );
		}

		// initialize CLI process.
		$progress = Helper::is_cli() ? \WP_CLI\Utils\make_progress_bar( 'Run simplifications', count( $entries ) ) : false;

		// loop through simplifications of this object.
		foreach ( $entries as $entry ) {
			$c = $c + $this->process_simplification( $simplification_obj, $language_mappings, $entry );

			// update counter for simplification of texts.
			$simplification_count_in_loop = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT, array() );
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT, ++$simplification_count_in_loop[ $hash ] );

			// show progress on CLI.
			! $progress ?: $progress->tick();
		}

		// end progress on CLI.
		! $progress ?: $progress->finish();

		// save result for this simplification if we used an API.
		if ( $c > 0 ) {
			// set result.
			/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the used API-title */
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, sprintf( __( '<strong>Simplifications have been returned from %2$s.</strong><br>They were inserted into the %1$s.', 'easy-language' ), esc_html( $object_type_name ), esc_html( $simplification_obj->init->get_title() ) ) );
		}

		// get count value for running simplifications.
		$count_simplifications = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT, array() );

		// get max value for running simplifications.
		$max_simplifications = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_MAX, array() );

		// remove marker for running simplification on this object.
		if ( absint( $max_simplifications[ $hash ] ) <= absint( $count_simplifications[ $hash ] ) ) {
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, 0 );

			// trigger object-update.
			do_action( 'save_post_' . $this->get_type(), $this->get_id(), $this->get_object_as_object(), true );
		}

		// return simplification-count.
		return $c;
	}

	/**
	 * Process simplification of single text.
	 *
	 * @param Object $simplification_obj The simplification-object.
	 * @param array  $language_mappings The language-mappings.
	 * @param Text   $entry The text-object.
	 *
	 * @return int
	 * @noinspection PhpUnused
	 */
	public function process_simplification( object $simplification_obj, array $language_mappings, Text $entry ): int {
		// counter for simplifications.
		$c = 0;

		// set state for the entry to "processing".
		$entry->set_state( 'processing' );

		// get object the text belongs to, to get its target language.
		$object_language = $this->get_language();

		// send request for each active mapping between source-language and target-languages.
		foreach ( $language_mappings as $source_language => $target_languages ) {
			foreach ( $target_languages as $target_language ) {
				// only if this text is not already simplified in source-language matching the target-language.
				if ( ! empty( $object_language[ $target_language ] ) && false === $entry->has_translation_in_language( $target_language ) && $source_language === $entry->get_source_language() ) {
					// call API to get simplification of the given entry.
					$results = $simplification_obj->call_api( $entry->get_original(), $source_language, $target_language );

					// save simplification if results are available.
					if ( ! empty( $results ) ) {
						$entry->set_translation( $results['translated_text'], $target_language, $simplification_obj->init->get_name(), absint( $results['jobid'] ) );
						++$c;
					}
				}
			}
		}

		// set result if we have not got any simplification from API and no simplifications are available.
		if ( 0 === $c ) {
			if ( current_user_can( 'manage_options' ) ) {
				/* translators: %1$s will be replaced by the URL for the API-log in plugin-settings */
				$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, sprintf( __( '<strong>No simplifications get from API.</strong><br>Please check the <a href="%1$s">API-log</a> for errors.', 'easy-language' ), esc_url( Helper::get_api_logs_page_url() ) ) );
			} else {
				$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, __( '<strong>No simplifications get from API.</strong><br>Please consult an administrator to check the API-log.', 'easy-language' ) );
			}
		}

		// loop through the mapping languages to replace the texts in this object with the simplifications.
		$replaced_count = 0;
		foreach ( $language_mappings as $source_language => $target_languages ) {
			foreach ( $target_languages as $target_language ) {
				if ( false !== $entry->has_translation_in_language( $target_language ) && $source_language === $entry->get_source_language() ) {
					if ( $entry->replace_original_with_translation( $this->get_id(), $target_language ) ) {
						++$replaced_count;
					}
				}
			}
		}

		// set state to "in_use" to mark text as simplified and inserted.
		if( $replaced_count > 0 ) {
			$entry->set_state( 'in_use' );
		}

		// Set result if we got simplified texts from API but does not replace them.
		if ( $c > 0 && 0 === $replaced_count ) {
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, __( 'We got simplified texts from API but does not replace any texts. This might be an error with the pagebuilder-support of the Easy Language plugin.', 'easy-language' ) );

			// return 0.
			return 0;
		}

		// return simplification-count.
		return $c;
	}

	/**
	 * Get md5-hash for this object.
	 *
	 * @return string
	 */
	public function get_md5(): string {
		return md5( $this->get_id() . $this->get_type() );
	}

	/**
	 * Set marker during simplification.
	 *
	 * @param string     $option The option to change.
	 * @param string|int $value The value to set.
	 *
	 * @return void
	 */
	private function set_array_marker_during_simplification( string $option, string|int $value ): void {
		$actual_value                     = get_option( $option, array() );
		$actual_value[ $this->get_md5() ] = $value;
		update_option( $option, $actual_value );
	}

	/**
	 * Return whether this original object has translations.
	 *
	 * @return bool
	 */
	public function has_translations(): bool {
		// bail for translated objects.
		if ( $this->is_translated() ) {
			return false;
		}

		// get list of translations in languages.
		$languages = get_post_meta( $this->get_id(), 'easy_language_simplified_in', true );

		// return true if list is not empty.
		return ! empty( $languages );
	}

	/**
	 * Return entries which are assigned to this post-object.
	 *
	 * @return array
	 */
	public function get_entries(): array {
		return DB::get_instance()->get_entries(
			array(
				'object_id'   => $this->get_id(),
				'object_type' => $this->get_type(),
			)
		);
	}

	/**
	 * Cleanup single simplification marker of this object.
	 *
	 * @param string $marker The marker to cleanup.
	 *
	 * @return void
	 */
	public function cleanup_simplification_marker( string $marker ): void {
		$values = get_option( $marker, array() );
		if ( isset( $values[ $this->get_md5() ] ) ) {
			unset( $values[ $this->get_md5() ] );
		}
		update_option( $marker, $values );
	}

	/**
	 * Return whether this object is locked or not.
	 *
	 * @return bool
	 */
	public function is_locked(): bool {
		return wp_check_post_lock( $this->get_id() );
	}

	/**
	 * Add simplification object to this object if it is an not translatable object.
	 *
	 * @param string $target_language The target-language.
	 * @param Api_Base $api_object The API to use.
	 *
	 * @return bool|Post_Object
	 */
	public function add_simplification_object( string $target_language, Api_Base $api_object ): bool|Post_Object {
		// get DB-object.
		$db = DB::get_instance();

		// check if this object is already translated in this language.
		if ( false === $this->is_translated_in_language( $target_language ) ) {
			// get the source-language.
			$source_language = helper::get_wp_lang();
			if ( empty( $source_language ) ) {
				$source_language = Helper::get_wp_lang();
			}

			// get array with post-data of the original.
			$post_array = $this->get_object_as_array();

			// remove some settings.
			unset( $post_array['ID'] );
			unset( $post_array['page_template'] );
			unset( $post_array['guid'] );

			// set author to actual user.
			$post_array['post_author'] = get_current_user_id();

			// add the copy.
			$copied_post_id = wp_insert_post( $post_array );

			// copy taxonomies and post-meta.
			helper::copy_cpt( $this->get_id(), $copied_post_id );

			// mark the copied post as translation-object of the original.
			update_post_meta( $copied_post_id, 'easy_language_simplification_original_id', $this->get_id() );

			// save the source-language of the copied object.
			update_post_meta( $copied_post_id, 'easy_language_source_language', $source_language );

			// save the target-language of the copied object.
			update_post_meta( $copied_post_id, 'easy_language_simplification_language', $target_language );

			// save the API used for this simplification.
			update_post_meta( $copied_post_id, 'easy_language_api', $api_object->get_name() );

			// ste the language for the original object.
			update_post_meta( $this->get_id(), 'easy_language_text_language', $source_language );

			// parse text depending on used pagebuilder for this object.
			$pagebuilder_obj = $this->get_page_builder();
			$pagebuilder_obj->set_object_id( $copied_post_id );
			$pagebuilder_obj->set_title( $this->get_title() );
			$pagebuilder_obj->set_text( $this->get_content() );

			// loop through the resulting texts and add each one for simplification.
			foreach ( $pagebuilder_obj->get_parsed_texts() as $text ) {
				// bail if text is empty.
				if( empty($text) ) {
					continue;
				}

				// check if the text is already saved as original text for simplification.
				$original_text_obj = $db->get_entry_by_text( $text, $source_language );
				if ( false === $original_text_obj ) {
					// save the text for simplification.
					$original_text_obj = $db->add( $text, $source_language, 'post_content' );
				}
				$original_text_obj->set_object( get_post_type( $copied_post_id ), $copied_post_id, $pagebuilder_obj->get_name() );
				$original_text_obj->set_state( 'to_simplify' );
			}

			// check if the title has already saved as original text for simplification.
			$original_title_obj = $db->get_entry_by_text( $pagebuilder_obj->get_title(), $source_language );
			if ( false === $original_title_obj ) {
				// save the text for simplification.
				$original_title_obj = $db->add( $pagebuilder_obj->get_title(), $source_language, 'title' );
			}
			$original_title_obj->set_object( get_post_type( $copied_post_id ), $copied_post_id, $pagebuilder_obj->get_name() );
			$original_title_obj->set_state( 'to_simplify' );

			// add this language as translated language to original post.
			$this->add_translated_language( $target_language );

			// set marker to reset permalinks.
			Rewrite::get_instance()->set_refresh();

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
}
