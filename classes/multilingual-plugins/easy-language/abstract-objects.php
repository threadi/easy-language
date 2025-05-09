<?php
/**
 * File for our own object-handler.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Api_Base;
use easyLanguage\Api_Simplifications;
use easyLanguage\Apis;
use easyLanguage\Helper;

/**
 * Definition of our Objects object.
 */
abstract class Objects {
	/**
	 * The ID of the object.
	 *
	 * @var int
	 */
	protected int $id;

	/**
	 * The init object.
	 *
	 * @var Init
	 */
	public Init $init;

	/**
	 * The simplification-type of the object:
	 * - simplifiable => the original objects in WP.
	 * - simplified => the simplified objects of our own plugin.
	 *
	 * @var string
	 */
	protected string $simplify_type = '';

	/**
	 * Initialize the object.
	 *
	 * @param int $object_id The object-ID.
	 */
	public function __construct( int $object_id ) {
		// secure the given object-ID.
		$this->id = $object_id;
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
	 * Return whether this object is a simplified object.
	 *
	 * @return bool
	 */
	public function is_simplified(): bool {
		return 'simplified' === $this->get_simplification_type();
	}

	/**
	 * Return whether this object is a simplifiable object.
	 *
	 * @return bool
	 */
	public function is_simplifiable(): bool {
		return 'simplifiable' === $this->get_simplification_type();
	}

	/**
	 * Get simplification type.
	 *
	 * @return string
	 */
	protected function get_simplification_type(): string {
		return '';
	}

	/**
	 * Return whether this original object has simplifications.
	 *
	 * @return bool
	 */
	public function has_simplifications(): bool {
		return false;
	}

	/**
	 * Return edit link for pagebuilder-object.
	 *
	 * @return string
	 */
	public function get_edit_link(): string {
		return '';
	}

	/**
	 * Return whether this object should not be used during automatic simplification.
	 *
	 * @return bool true if it should not be used
	 * @noinspection PhpUnused
	 */
	public function is_automatic_mode_prevented(): bool {
		return true;
	}

	/**
	 * Return language-specific title for the type of the given object.
	 *
	 * @return string
	 */
	public function get_type_name(): string {
		return '';
	}

	/**
	 * Return the post_id of the simplification of this object in a given language.
	 *
	 * @param string $language_code The language we search.
	 *
	 * @return int
	 */
	public function get_simplification_in_language( string $language_code ): int {
		if ( empty( $language_code ) ) {
			return 0;
		}
		return 0;
	}

	/**
	 * Return whether a given object is simplified in given language.
	 *
	 * @param string $language The language to check.
	 *
	 * @return bool
	 */
	public function is_simplified_in_language( string $language ): bool {
		if ( false === $this->has_simplifications() ) {
			return false;
		}
		return $this->get_simplification_in_language( $language ) > 0;
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
	 * @param string                                                  $option The option to change.
	 * @param array<string,array<array<string,string>|string>|string> $value The value to set.
	 *
	 * @return void
	 */
	public function set_array_marker_during_simplification( string $option, string|int|array $value ): void {
		$actual_value                     = get_option( $option, array() );
		$actual_value[ $this->get_md5() ] = $value;
		update_option( $option, $actual_value );
	}

	/**
	 * Cleanup single simplification marker of this object.
	 *
	 * @param string $marker The marker to clean up.
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
	 * Return whether this object has a specific state.
	 *
	 * @param string $state The state to set.
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public function has_state( string $state ): bool {
		return $state === $this->get_status();
	}

	/**
	 * Return the post-status.
	 *
	 * @return string
	 */
	public function get_status(): string {
		return '';
	}

	/**
	 * Process multiple text-simplification of a single object-object (like a post).
	 *
	 * @param Api_Simplifications $simplification_obj The simplification-object of the used API.
	 * @param array<string,mixed> $language_mappings The language-mappings.
	 * @param int                 $limit Limit the entries processed during this request.
	 * @param bool                $initialization Mark if this is the initialization of a simplification.
	 *
	 * @return int
	 */
	public function process_simplifications( Api_Simplifications $simplification_obj, array $language_mappings, int $limit = 0, bool $initialization = true ): int {
		// get object-hash.
		$hash = $this->get_md5();

		$js_top = '';
		/**
		 * Set top for JS-location if page builder which makes it necessary is actually used.
		 *
		 * @since 2.2.0 Available since 2.2.0.
		 * @param string $js_top The top-string.
		 */
		$js_top = apply_filters( 'easy_language_js_top', $js_top );

		// initialize the simplification.
		if ( false !== $initialization ) {
			$simplification_results = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, array() );
			if ( ! empty( $simplification_results[ $hash ] ) ) {
				// remove previous results.
				unset( $simplification_results[ $hash ] );
			}
			update_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, $simplification_results );

			// do not run simplification if it is already running in another process for this object.
			$simplification_running = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, array() );
			if ( ! empty( $simplification_running[ $hash ] ) && absint( $simplification_running[ $hash ] ) > 0 ) {
				// set result.
				$dialog = array(
					'className' => 'wp-dialog-error',
					'title'     => __( 'Simplification canceled', 'easy-language' ),
					'texts'     => array(
						/* translators: %1$s will be replaced by the object-title */
						'<p>' . sprintf( __( 'Simplification for <i>%1$s</i> is already running.', 'easy-language' ), esc_html( $this->get_title() ) ) . '</p>',
					),
					'buttons'   => array(
						array(
							'action'  => 'location.reload();',
							'variant' => 'primary',
							'text'    => __( 'OK', 'easy-language' ),
						),
					),
				);
				$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, $dialog );

				// set error marker for return value.
				$this->set_error_marker_for_process();

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
				'object_id'   => $this->get_id(),
				'object_type' => $this->get_type(),
				'state'       => 'processing',
			);

			// get entries which are in process and show error if there are any.
			$entries_in_process = Db::get_instance()->get_entries( $filter );
			if ( ! empty( $entries_in_process ) ) {
				// set result.
				$dialog = array(
					'className' => 'wp-dialog-error',
					'title'     => __( 'Simplification canceled', 'easy-language' ),
					'texts'     => array(
						/* translators: %1$s will be replaced by the object-title */
						'<p>' . sprintf( __( 'A previously running simplification of texts of this %1$s failed. How do you want to deal with it?', 'easy-language' ), esc_html( $this->get_type_name() ) ) . '</p>',
					),
					'buttons'   => array(
						array(
							'action'  => 'easy_language_reset_processing_simplification("' . $this->get_id() . '", "' . $this->get_type() . '");',
							'variant' => 'primary',
							'text'    => __( 'Run simplification again', 'easy-language' ),
						),
						array(
							'action'  => 'easy_language_ignore_processing_simplification("' . $this->get_id() . '", "' . $this->get_type() . '");',
							'variant' => 'primary',
							'text'    => __( 'Ignore the failed simplifications', 'easy-language' ),
						),
						array(
							'action' => 'location.reload();',
							'text'   => __( 'Cancel', 'easy-language' ),
						),
					),
				);
				$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, $dialog );

				// set error marker for return value.
				$this->set_error_marker_for_process();

				// return 0 as we have not simplified anything.
				return 0;
			}

			// define filter for entry-loading to check max count of entries for this object.
			$filter = array(
				'object_id'   => $this->get_id(),
				'object_type' => $this->get_type(),
			);

			// get entries.
			$max_entries = Db::get_instance()->get_entries( $filter );

			// get entry-count.
			$max_entry_count = count( $max_entries );

			// do not run simplification if the requested object contains more than the text-limit of the API allow.
			$api_obj = Apis::get_instance()->get_active_api();
			if ( false !== $api_obj && $max_entry_count > $api_obj->get_max_requests_per_minute() ) {
				// set result.
				$dialog = array(
					'className' => 'wp-dialog-hint',
					'title'     => __( 'Simplification canceled', 'easy-language' ),
					'texts'     => array(
						/* translators: %1$s will be replaced by the object-title (like page or post), %2$s will be replaced by the API-title */
						'<p>' . sprintf( __( 'The %1$s contains more text widgets than the API %2$s could handle in a short time.<br>The texts will be automatically simplified in the background.', 'easy-language' ), esc_html( $this->get_type_name() ), esc_html( $api_obj->get_title() ) ) . '</p>',
					),
					'buttons'   => array(
						array(
							'action'  => $js_top . 'location.href="' . get_permalink( $this->get_id() ) . '";',
							'variant' => 'primary',
							'text'    => __( 'Show in frontend', 'easy-language' ),
						),
						array(
							'action'  => $js_top . 'location.href="' . $this->get_edit_link() . '";',
							'variant' => 'secondary',
							'text'    => __( 'Edit', 'easy-language' ),
						),
						array(
							'action' => $js_top . 'location.reload();',
							'text'   => __( 'Cancel', 'easy-language' ),
						),
					),
				);
				if ( $this->is_automatic_mode_prevented() ) {
					/* translators: %1$s will be replaced by the object-title (like page or post), %2$s will be replaced by the API-title */
					$dialog['texts'][0] = '<p>' . sprintf( __( 'The %1$s contains more text widgets than the API %2$s could handle in a short time.<br>The texts could be automatically simplified in the background if you enable this on the page settings.', 'easy-language' ), esc_html( $this->get_type_name() ), esc_html( $api_obj->get_title() ) ) . '</p>';
				}
				$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, $dialog );

				// set error marker for return value.
				$this->set_error_marker_for_process();

				// return 0 as we have not simplified anything.
				return 0;
			}

			// mark simplification for this object as running.
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, time() );

			// set max texts to simplify.
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_MAX, $max_entry_count );

			// set counter for simplified texts to 0.
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT, 0 );
		}

		// counter for simplifications during this run.
		$c = 0;

		// define filter to get all entries for this object which should be simplified.
		$filter = array(
			'object_id'   => $this->get_id(),
			'object_type' => $this->get_type(),
			'state'       => 'to_simplify',
		);

		// get limited entries.
		$entries = Db::get_instance()->get_entries( $filter, array(), $limit );

		// if no more texts to simplify found, break the process and show hint depending on progress.
		if ( empty( $entries ) ) {
			// get max value.
			$simplification_max = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_MAX, array() );

			// get actual count.
			$simplification_counts = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT, array() );

			// set result if count is 0.
			if ( isset( $simplification_counts[ $hash ] ) && 0 === absint( $simplification_counts[ $hash ] ) ) {
				$dialog = array(
					'className' => 'wp-dialog-hint',
					'title'     => __( 'Simplification canceled', 'easy-language' ),
					'texts'     => array(
						/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the used API-title */
						'<p>' . sprintf( __( '<strong>The texts in this %1$s are already simplified.</strong><br>%2$s was not used. Nothing has been changed.', 'easy-language' ), esc_html( $this->get_type_name() ), esc_html( $simplification_obj->get_api()->get_title() ) ) . '</p>',
					),
					'buttons'   => array(
						array(
							'action'  => $js_top . 'location.href="' . get_permalink( $this->get_id() ) . '";',
							'variant' => 'primary',
							'text'    => __( 'Show in frontend', 'easy-language' ),
						),
						array(
							'action'  => $js_top . 'location.href="' . $this->get_edit_link() . '";',
							'variant' => 'secondary',
							'text'    => __( 'Edit', 'easy-language' ),
						),
						array(
							'action' => $js_top . 'location.reload();',
							'text'   => __( 'Cancel', 'easy-language' ),
						),
					),
				);
			} else {
				// otherwise show hint that some texts are already optimized.
				$dialog = array(
					'className' => 'wp-dialog-hint',
					'title'     => __( 'Simplification canceled', 'easy-language' ),
					'texts'     => array(
						/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the used API-title */
						'<p>' . sprintf( __( '<strong>Some texts in this %1$s are already simplified.</strong><br>Other missing simplifications has been run via %2$s and are insert into the text.', 'easy-language' ), esc_html( $this->get_type_name() ), esc_html( $simplification_obj->get_api()->get_title() ) ) . '</p>',
					),
					'buttons'   => array(
						array(
							'action'  => $js_top . 'location.href="' . get_permalink( $this->get_id() ) . '";',
							'variant' => 'primary',
							'text'    => __( 'Show in frontend', 'easy-language' ),
						),
						array(
							'action'  => $js_top . 'location.href="' . $this->get_edit_link() . '";',
							'variant' => 'secondary',
							'text'    => __( 'Edit', 'easy-language' ),
						),
						array(
							'action' => $js_top . 'location.reload();',
							'text'   => __( 'Cancel', 'easy-language' ),
						),
					),
				);
			}
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, $dialog );

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
			$c += $this->process_simplification( $simplification_obj, $language_mappings, $entry );

			// update counter for simplification of texts.
			$simplification_count_in_loop = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT, array() );
			if ( is_array( $simplification_count_in_loop ) ) {
				$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT, ++$simplification_count_in_loop[ $hash ] );
			}

			// show progress on CLI.
			$progress ? $progress->tick() : false;
		}

		// end progress on CLI.
		$progress ? $progress->finish() : false;

		// save result for this simplification if we used an API.
		if ( $c > 0 ) {
			// set result.
			$dialog = array(
				'className' => 'wp-dialog-green',
				'title'     => __( 'Simplification processed', 'easy-language' ),
				'texts'     => array(
					/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the used API-title */
					'<p>' . sprintf( __( '<strong>Simplifications have been returned from %2$s.</strong><br>They were inserted into the %1$s.', 'easy-language' ), esc_html( $this->get_type_name() ), esc_html( $simplification_obj->get_api()->get_title() ) ) . '</p>',
				),
				'buttons'   => array(
					array(
						'action'  => $js_top . 'location.href="' . get_permalink( $this->get_id() ) . '";',
						'variant' => 'primary',
						'text'    => __( 'Show in frontend', 'easy-language' ),
					),
					array(
						'action'  => $js_top . 'location.href="' . $this->get_edit_link() . '";',
						'variant' => 'primary',
						'text'    => __( 'Edit', 'easy-language' ),
					),
					array(
						'action'  => $js_top . 'location.reload();',
						'variant' => 'secondary',
						'text'    => __( 'Cancel', 'easy-language' ),
					),
				),
			);
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, $dialog );
		}

		// get count value for running simplifications.
		$count_simplifications = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT, array() );

		// get max value for running simplifications.
		$max_simplifications = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_MAX, array() );

		// remove marker for running simplification on this object.
		if ( absint( $max_simplifications[ $hash ] ) <= absint( $count_simplifications[ $hash ] ) ) {
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, 0 );

			$this->process_simplification_trigger_on_end();
		}

		// return simplification-count.
		return $c;
	}

	/**
	 * Process simplification of single text.
	 *
	 * @param Api_Simplifications $simplification_obj The simplification-object.
	 * @param array<string,mixed> $language_mappings The language-mappings.
	 * @param Text                $entry The text-object.
	 *
	 * @return int
	 */
	public function process_simplification( Api_Simplifications $simplification_obj, array $language_mappings, Text $entry ): int {
		// counter for simplifications.
		$c = 0;

		// set state for the entry to "processing".
		$entry->set_state( 'processing' );

		// get object the text belongs to, to get its target language.
		$object_language = $this->get_language();

		// marker if API-errors happened.
		$api_errors = false;

		// send request for each active mapping between source-language and target-languages.
		foreach ( $language_mappings as $source_language => $target_languages ) {
			foreach ( $target_languages as $target_language ) {
				// only if this text is not already simplified in source-language matching the target-language.
				if ( ! empty( $object_language[ $target_language ] ) && false === $entry->has_simplification_in_language( $target_language ) && $source_language === $entry->get_source_language() ) {
					// call API to get simplification of the given entry.
					$results = $simplification_obj->call_api( $entry->get_original(), $source_language, $target_language, $entry->is_html() );

					// save simplification if results are available.
					if ( ! empty( $results ) ) {
						$entry->set_simplification( (string) $results['translated_text'], $target_language, $simplification_obj->get_api()->get_name(), absint( $results['jobid'] ) );
						++$c;
					} else {
						$api_errors = true;
					}
				}
			}
		}

		// set result if we have not got any simplification from API and no simplifications are available.
		if ( false !== $api_errors && 0 === $c ) {
			$dialog = array(
				'className' => 'wp-dialog-error',
				'title'     => __( 'Simplification canceled', 'easy-language' ),
				'texts'     => array(
					/* translators: %1$s will be replaced by the URL for the API-log in plugin-settings */
					'<p>' . sprintf( __( '<strong>No simplifications get from API.</strong><br>Please check the <a href="%1$s">API-log</a> for errors.', 'easy-language' ), esc_url( Helper::get_api_logs_page_url() ) ) . '</p>',
				),
				'buttons'   => array(
					array(
						'action'  => 'location.reload();',
						'variant' => 'primary',
						'text'    => __( 'OK', 'easy-language' ),
					),
				),
			);
			if ( ! current_user_can( 'manage_options' ) ) {
				$dialog['texts'][0] = __( '<strong>No simplifications get from API.</strong><br>Please consult an administrator to check the API-log.', 'easy-language' );
			}
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, $dialog );
		}

		// loop through the mapping languages to replace the texts in this object with the simplifications.
		$replaced_count = 0;
		foreach ( $language_mappings as $source_language => $target_languages ) {
			foreach ( $target_languages as $target_language ) {
				if ( false !== $entry->has_simplification_in_language( $target_language ) && $source_language === $entry->get_source_language() && $entry->replace_original_with_simplification( $this->get_id(), $target_language ) ) {
					++$replaced_count;
				}
			}
		}

		$js_top = '';
		/**
		 * Set top for JS-location if page builder which makes it necessary is actually used.
		 *
		 * @since 2.2.0 Available since 2.2.0.
		 * @param string $js_top The top-string.
		 */
		$js_top = apply_filters( 'easy_language_js_top', $js_top );

		// set state to "in_use" to mark text as simplified and inserted.
		if ( 0 === $c && $replaced_count > 0 ) {
			$entry->set_state( 'in_use' );

			// create dialog.
			$dialog = array(
				'className' => 'wp-dialog-success',
				'title'     => __( 'Simplification processed', 'easy-language' ),
				'texts'     => array(
					/* translators: %1$s will be replaced by the object-title (like post or page) */
					'<p>' . sprintf( __( 'The texts are already simplified local.<br><strong>We did not use the API to simplify them again.</strong><br>The texts in this %1$s are replaced with its local available simplification.', 'easy-language' ), esc_html( $this->get_type_name() ) ) . '</p>',
				),
				'buttons'   => array(
					array(
						'action'  => $js_top . 'location.href="' . get_permalink( $this->get_id() ) . '";',
						'variant' => 'primary',
						'text'    => __( 'Show in frontend', 'easy-language' ),
					),
					array(
						'action'  => $js_top . 'location.href="' . $this->get_edit_link() . '";',
						'variant' => 'primary',
						'text'    => __( 'Edit', 'easy-language' ),
					),
					array(
						'action'  => $js_top . 'location.reload();',
						'variant' => 'secondary',
						'text'    => __( 'Cancel', 'easy-language' ),
					),
				),
			);
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, $dialog );
		}

		// Set result if we got simplified texts from API but does not replace them.
		if ( $c > 0 && 0 === $replaced_count ) {
			$dialog = array(
				'className' => 'wp-dialog-error',
				'title'     => __( 'Simplification canceled', 'easy-language' ),
				'texts'     => array(
					'<p>' . __( 'We got simplified texts from API but does not replace any texts. This might be an error with the pagebuilder-support of the Easy Language plugin.', 'easy-language' ) . '</p>',
				),
				'buttons'   => array(
					array(
						'action'  => 'location.reload();',
						'variant' => 'primary',
						'text'    => __( 'OK', 'easy-language' ),
					),
				),
			);
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, $dialog );

			// return 0.
			return 0;
		}

		// set state to in_use.
		$entry->set_state( 'in_use' );

		// return simplification-count.
		return $c;
	}

	/**
	 * Call object-specific trigger after processed simplification.
	 *
	 * @return void
	 */
	protected function process_simplification_trigger_on_end() {}

	/**
	 * Set error marker for running simplification.
	 *
	 * @return void
	 */
	private function set_error_marker_for_process(): void {
		// mark simplification for this object as if it has been run.
		$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, 0 );

		// set max texts to 1.
		$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_MAX, 1 );

		// set counter to 1.
		$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT, 1 );
	}

	/**
	 * Return quota-state of this object regarding a given api.
	 *
	 * Possible states:
	 * - ok => could be simplified
	 * - above_limit => if characters of this object are more than the quota-limit
	 * - above_text_limit => if one text is above the text-limit from used API
	 * - exceeded => if quota is exceeded
	 *
	 * @param Api_Base $api_obj The Api-object.
	 *
	 * @return array<string,mixed>
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
		$max_text_length          = $api_obj->get_max_text_length();
		$max_text_length_exceeded = false;

		// get entry-limit from API.
		$entry_limit_per_minute = $api_obj->get_max_requests_per_minute();

		// get chars to simplify.
		$filter  = array(
			'object_id' => $this->get_id(),
		);
		$entries = Db::get_instance()->get_entries( $filter );
		foreach ( $entries as $entry ) {
			$text_length                  = absint( strlen( $entry->get_original() ) );
			$return_array['chars_count'] += $text_length;
			if ( $text_length > $max_text_length ) {
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
		if ( $max_text_length_exceeded ) {
			$return_array['status'] = 'above_text_limit';
		}

		// if more entries used as API would perform per minute.
		if ( count( $entries ) > $entry_limit_per_minute ) {
			$return_array['status'] = 'above_entry_limit';
		}

		// return ok.
		return $return_array;
	}

	/**
	 * Return the languages of this object.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function get_language(): array {
		return array();
	}

	/**
	 * Get post-ID of the original post.
	 *
	 * @return int
	 */
	public function get_original_object_as_int(): int {
		return 0;
	}

	/**
	 * Return the post-type of this object.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return '';
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
		if ( empty( $slug ) ) {
			return '';
		}
		if ( empty( $language_code ) ) {
			return '';
		}
		return '';
	}

	/**
	 * Return the used page builder.
	 *
	 * @return Parser_Base|false
	 */
	public function get_page_builder(): Parser_Base|false {
		return false;
	}

	/**
	 * Add simplification object to this object if it is a not simplifiable object.
	 *
	 * @param string   $target_language The target-language.
	 * @param Api_Base $api_object The API to use.
	 * @param bool     $prevent_automatic_mode True if automatic mode is prevented.
	 * @return bool|Post_Object
	 */
	public function add_simplification_object( string $target_language, Api_Base $api_object, bool $prevent_automatic_mode ): bool|Post_Object {
		if ( empty( $target_language ) ) {
			return false;
		}
		if ( $api_object->is_active() ) {
			return false;
		}
		if ( $prevent_automatic_mode ) {
			return false;
		}
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
	public function set_automatic_mode_prevented( bool $prevent_automatic_mode ): void {}

	/**
	 * Return the public title of the object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return '';
	}

	/**
	 * Call API to simplify single text.
	 *
	 * @param string $text_to_translate The text to translate.
	 * @param string $source_language The source language of the text.
	 * @param string $target_language The target language of the text.
	 * @param bool   $is_html Marker if the text contains HTML-Code.
	 * @return array<string,int|string> The result as array.
	 */
	public function call_api( string $text_to_translate, string $source_language, string $target_language, bool $is_html ): array {
		if ( empty( $text_to_translate ) ) {
			return array();
		}
		if ( empty( $source_language ) ) {
			return array();
		}
		if ( empty( $target_language ) ) {
			return array();
		}
		if ( empty( $is_html ) ) {
			return array();
		}
		return array();
	}

	/**
	 * Return whether this object is locked or not.
	 *
	 * @return bool true if object is locked.
	 */
	public function is_locked(): bool {
		return false;
	}

	/**
	 * Get link to create a simplification of the actual object with given language.
	 *
	 * @param string $language_code The language we search.
	 *
	 * @return string
	 */
	public function get_simplification_link( string $language_code ): string {
		if ( empty( $language_code ) ) {
			return '';
		}
		return '';
	}
}
