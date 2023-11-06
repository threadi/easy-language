<?php
/**
 * File for our own object-handler.
 *
 * TODO Datei füllen und object-klassen entschlacken
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

// prevent direct access.
use easyLanguage\Apis;
use easyLanguage\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parser for texts.
 */
abstract class Objects {
	/**
	 * The ID of the object.
	 *
	 * @var int
	 */
	protected int $id;

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
	 * @param string $option The option to change.
	 * @param string|int|array $value The value to set.
	 *
	 * @return void
	 */
	protected function set_array_marker_during_simplification( string $option, string|int|array $value ): void {
		$actual_value                     = get_option( $option, array() );
		$actual_value[ $this->get_md5() ] = $value;
		update_option( $option, $actual_value );
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
	 * Return whether this object has a specific state.
	 *
	 * @param string $state
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public function has_state( string $state ): bool {
		return $state === $this->get_status();
	}

	/**
	 * Process multiple text-simplification of a single object-object.
	 *
	 * @param Object $simplification_obj The simplification-object of the used API.
	 * @param array  $language_mappings The language-mappings.
	 * @param int    $limit Limit the entries processed during this request.
	 * @param bool   $initialization Mark if this is the initialization of a simplification.
	 *
	 * @return int
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function process_simplifications( object $simplification_obj, array $language_mappings, int $limit = 0, bool $initialization = true ): int {
		// get object-hash.
		$hash = $this->get_md5();

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
					'title' => __( 'Simplification canceled', 'easy-language' ),
					'texts' => array(
						/* translators: %1$s will be replaced by the object-title */
						'<p>'.sprintf( __( 'Simplification for <i>%1$s</i> is already running.', 'easy-language' ), esc_html( $this->get_title() ) ).'</p>'
					),
					'buttons' => array(
						array(
							'action' => 'location.reload();',
							'variant' => 'primary',
							'text' => __(  'OK', 'easy-language' )
						)
					)
				);
				$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, $dialog );

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

			// get entries which are in process and show error if there are any.
			$entries_in_process = Db::get_instance()->get_entries( $filter );
			if ( ! empty( $entries_in_process ) ) {
				// set result.
				$dialog = array(
					'className' => 'wp-dialog-error',
					'title' => __( 'Simplification canceled', 'easy-language' ),
					'texts' => array(
						/* translators: %1$s will be replaced by the object-title */
						'<p>'.sprintf( __( 'A previously running simplification of texts of this %1$s failed. How do you want to deal with it?', 'easy-language' ), esc_html( $this->get_type_name() ) ).'</p>'
					),
					'buttons' => array(
						array(
							'action' => 'easy_language_reset_processing_simplification("' . $this->get_id() . '", "'.get_permalink($this->get_id()).'");',
							'variant' => 'primary',
							'text' => __(  'Run simplification again', 'easy-language' )
						),
						array(
							'action' => 'easy_language_ignore_processing_simplification("' . $this->get_id() . '", "'.$this->get_type().'");',
							'variant' => 'primary',
							'text' => __(  'Ignore the failed simplifications', 'easy-language' )
						),
						array(
							'action' => 'location.reload();',
							'text' => __(  'Cancel', 'easy-language' )
						)
					)
				);
				$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, $dialog );

				// return 0 as we have not simplified anything.
				return 0;
			}

			// define filter for entry-loading to check max count of entries for this object.
			$filter = array(
				'object_id' => $this->get_id(),
			);

			// get entries.
			$max_entries = Db::get_instance()->get_entries( $filter );

			// get entry-count.
			$max_entry_count = count( $max_entries );

			// do not run simplification if the requested object contains more than the text-limit of the API allow.
			$api_obj = Apis::get_instance()->get_active_api();
			if( false !== $api_obj && $max_entry_count > $api_obj->get_max_requests_per_minute() ) {
				// set result.
				$dialog = array(
					'className' => 'wp-dialog-hint',
					'title' => __( 'Simplification canceled', 'easy-language' ),
					'texts' => array(
						/* translators: %1$s will be replaced by the object-title (like page or post), %2$s will be replaced by the API-title */
						'<p>'.sprintf(__( 'The %1$s contains more text widgets than the API %2$s could handle in a short time.<br>The texts will be automatically simplified in the background.', 'easy-language' ), esc_html($this->get_type_name()), esc_html($api_obj->get_title())).'</p>'
					),
					'buttons' => array(
						array(
							'action' => 'location.href="'.get_permalink($this->get_id()).'";',
							'variant' => 'primary',
							'text' => __( 'Show in frontend', 'easy-language' )
						),
						array(
							'action' => 'location.href="'.$this->get_edit_link().'";',
							'variant' => 'secondary',
							'text' => __( 'Edit', 'easy-language' )
						),
						array(
							'action' => 'location.reload();',
							'text' => __(  'Cancel', 'easy-language' )
						)
					)
				);
				if( $this->is_automatic_mode_prevented() ) {
					/* translators: %1$s will be replaced by the object-title (like page or post), %2$s will be replaced by the API-title */
					$dialog['texts'][0] = '<p>'.sprintf(__( 'The %1$s contains more text widgets than the API %2$s could handle in a short time.<br>The texts could be automatically simplified in the background if you enable this on the page settings.', 'easy-language' ), esc_html($this->get_type_name()), esc_html($api_obj->get_title())).'</p>';
				}
				$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, $dialog );

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
			'object_id' => $this->get_id(),
			'state'     => 'to_simplify',
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
					'title' => __( 'Simplification canceled', 'easy-language' ),
					'texts' => array(
						/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the used API-title */
						'<p>'.sprintf( __( '<strong>The texts in this %1$s are already simplified.</strong><br>%2$s was not used. Nothing has been changed.', 'easy-language' ), esc_html( $this->get_type_name() ), esc_html( $simplification_obj->init->get_title() ) ).'</p>'
					),
					'buttons' => array(
						array(
							'action' => 'location.href="'.get_permalink($this->get_id()).'";',
							'variant' => 'primary',
							'text' => __( 'Show in frontend', 'easy-language' )
						),
						array(
							'action' => 'location.href="'.$this->get_edit_link().'";',
							'variant' => 'secondary',
							'text' => __( 'Edit', 'easy-language' )
						),
						array(
							'action' => 'location.reload();',
							'text' => __(  'Cancel', 'easy-language' )
						)
					)
				);
			} else {
				// otherwise show hint that some texts are already optimized.
				$dialog = array(
					'className' => 'wp-dialog-hint',
					'title' => __( 'Simplification canceled', 'easy-language' ),
					'texts' => array(
						/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the used API-title */
						'<p>'.sprintf( __( '<strong>Some texts in this %1$s are already simplified.</strong><br>Other missing simplifications has been run via %2$s and are insert into the text.', 'easy-language' ), esc_html( $this->get_type_name() ), esc_html( $simplification_obj->init->get_title() ) ).'</p>'
					),
					'buttons' => array(
						array(
							'action' => 'location.href="'.get_permalink($this->get_id()).'";',
							'variant' => 'primary',
							'text' => __( 'Show in frontend', 'easy-language' )
						),
						array(
							'action' => 'location.href="'.$this->get_edit_link().'";',
							'variant' => 'secondary',
							'text' => __( 'Edit', 'easy-language' )
						),
						array(
							'action' => 'location.reload();',
							'text' => __(  'Cancel', 'easy-language' )
						)
					)
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
			$dialog = array(
				'className' => 'wp-dialog-green',
				'title' => __( 'Simplification processed', 'easy-language' ),
				'texts' => array(
					/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the used API-title */
					'<p>'.sprintf( __( '<strong>Simplifications have been returned from %2$s.</strong><br>They were inserted into the %1$s.', 'easy-language' ), esc_html( $this->get_type_name() ), esc_html( $simplification_obj->init->get_title() ) ).'</p>'
				),
				'buttons' => array(
					array(
						'action' => 'location.href="'.get_permalink($this->get_id()).'";',
						'variant' => 'primary',
						'text' => __( 'Show in frontend', 'easy-language' )
					),
					array(
						'action' => 'location.href="'.$this->get_edit_link().'";',
						'variant' => 'primary',
						'text' => __( 'Edit', 'easy-language' )
					),
					array(
						'action' => 'location.reload();',
						'variant' => 'secondary',
						'text' => __(  'Cancel', 'easy-language' )
					)
				)
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

		// marker if API-errors happened.
		$api_errors = false;

		// send request for each active mapping between source-language and target-languages.
		foreach ( $language_mappings as $source_language => $target_languages ) {
			foreach ( $target_languages as $target_language ) {
				// only if this text is not already simplified in source-language matching the target-language.
				if ( ! empty( $object_language[ $target_language ] ) && false === $entry->has_simplification_in_language( $target_language ) && $source_language === $entry->get_source_language() ) {
					// call API to get simplification of the given entry.
					$results = $simplification_obj->call_api( $entry->get_original(), $source_language, $target_language );

					// save simplification if results are available.
					if ( ! empty( $results ) ) {
						$entry->set_simplification( $results['translated_text'], $target_language, $simplification_obj->init->get_name(), absint( $results['jobid'] ) );
						++$c;
					}
					else {
						$api_errors = true;
					}
				}
			}
		}

		// set result if we have not got any simplification from API and no simplifications are available.
		if ( false !== $api_errors && 0 === $c ) {
			$dialog = array(
				'className' => 'wp-dialog-error',
				'title' => __( 'Simplification canceled', 'easy-language' ),
				'texts' => array(
					/* translators: %1$s will be replaced by the URL for the API-log in plugin-settings */
					'<p>'.sprintf( __( '<strong>No simplifications get from API.</strong><br>Please check the <a href="%1$s">API-log</a> for errors.', 'easy-language' ), esc_url( Helper::get_api_logs_page_url() ) ).'</p>'
				),
				'buttons' => array(
					array(
						'action' => 'location.reload();',
						'variant' => 'primary',
						'text' => __(  'OK', 'easy-language' )
					)
				)
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
				if ( false !== $entry->has_simplification_in_language( $target_language ) && $source_language === $entry->get_source_language() ) {
					if ( $entry->replace_original_with_simplification( $this->get_id(), $target_language ) ) {
						++$replaced_count;
					}
				}
			}
		}

		// set state to "in_use" to mark text as simplified and inserted.
		if( 0 === $c && $replaced_count > 0 ) {
			$entry->set_state( 'in_use' );

			// create dialog.
			$dialog = array(
				'className' => 'wp-dialog-success',
				'title' => __( 'Simplification processed', 'easy-language' ),
				'texts' => array(
					/* translators: %1$s will be replaced by the object-title (like post or page) */
					'<p>'.sprintf( __( 'The texts are already simplified local.<br><strong>We did not use the API to simplify them again.</strong><br>The texts in this %1$s are replaced with its local available simplification.', 'easy-language' ), esc_html($this->get_type_name())).'</p>'
				),
				'buttons' => array(
					array(
						'action' => 'location.href="'.get_permalink($this->get_id()).'";',
						'variant' => 'primary',
						'text' => __( 'Show in frontend', 'easy-language' )
					),
					array(
						'action' => 'location.href="'.$this->get_edit_link().'";',
						'variant' => 'primary',
						'text' => __( 'Edit', 'easy-language' )
					),
					array(
						'action' => 'location.reload();',
						'variant' => 'secondary',
						'text' => __(  'Cancel', 'easy-language' )
					)
				)
			);
			$this->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, $dialog );
		}

		// Set result if we got simplified texts from API but does not replace them.
		if ( $c > 0 && 0 === $replaced_count ) {
			$dialog = array(
				'className' => 'wp-dialog-error',
				'title' => __( 'Simplification canceled', 'easy-language' ),
				'texts' => array(
					'<p>'.__( 'We got simplified texts from API but does not replace any texts. This might be an error with the pagebuilder-support of the Easy Language plugin.', 'easy-language' ).'</p>'
				),
				'buttons' => array(
					array(
						'action' => 'location.reload();',
						'variant' => 'primary',
						'text' => __(  'OK', 'easy-language' )
					)
				)
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
	private function process_simplification_trigger_on_end() {}
}
