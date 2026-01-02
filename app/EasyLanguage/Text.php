<?php
/**
 * File for our own simplification-machine.
 *
 * @package easy-language
 */

namespace easyLanguage\EasyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Api_Base;
use easyLanguage\Plugin\Apis;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Languages;
use easyLanguage\Plugin\Log;
use WP_User;

/**
 * Object for a single translatable text based on DB-dataset.
 */
class Text {

	/**
	 * Original text.
	 *
	 * @var string
	 */
	private string $original;

	/**
	 * ID of this object.
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * List of simplifications.
	 *
	 * @var array<string,string>
	 */
	private array $simplifications = array();

	/**
	 * Source language.
	 *
	 * @var string
	 */
	private string $source_language;

	/**
	 * The db-object.
	 *
	 * @var Db
	 */
	private Db $db;

	/**
	 * Constructor for this object.
	 *
	 * @param int $id The original object id of this text from the originals-table.
	 */
	public function __construct( int $id ) {
		// get db-object.
		$this->db = Db::get_instance();

		// secure id of this object.
		$this->id = $id;
	}

	/**
	 * Get the id.
	 *
	 * @return int
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * Get original text.
	 *
	 * @return string
	 */
	public function get_original(): string {
		return $this->original;
	}

	/**
	 * Set the original text.
	 *
	 * @param string $original The text to translate.
	 * @return void
	 */
	public function set_original( string $original ): void {
		$this->original = $original;
	}

	/**
	 * Get the source-language of this text.
	 *
	 * @return string
	 */
	public function get_source_language(): string {
		return $this->source_language;
	}

	/**
	 * Set the source language.
	 *
	 * @param string $language The source-language.
	 * @return void
	 */
	public function set_source_language( string $language ): void {
		$this->source_language = $language;
	}

	/**
	 * Return the simplification of this text in the given language from Db.
	 *
	 * If the given language is unknown or no simplification exists,
	 * return the original text.
	 *
	 * @param string $target_language The target language for this simplification.
	 * @return string
	 */
	public function get_simplification( string $target_language ): string {
		$supported_target_languages = Languages::get_instance()->get_active_languages();
		if ( ! empty( $supported_target_languages[ $target_language ] ) ) {
			$translation = $this->get_translation_from_db( $target_language );
			if ( ! empty( $translation ) ) {
				return wpautop( $translation );
			}
		}
		return $this->get_original();
	}

	/**
	 * Return if the text has a simplification in the given language.
	 *
	 * @param string $language The language we search.
	 * @return bool
	 */
	public function has_simplification_in_language( string $language ): bool {
		return ! empty( $this->get_translation_from_db( $language ) );
	}

	/**
	 * Return the translation of this text in the given language.
	 *
	 * @param string $language The language we search.
	 * @return string
	 */
	private function get_translation_from_db( string $language ): string {
		global $wpdb;

		if ( ! empty( $this->simplifications[ $language ] ) ) {
			return $this->simplifications[ $language ];
		}

		// get from DB.
		$result = $wpdb->get_row( $wpdb->prepare( 'SELECT `simplification` FROM ' . Db::get_instance()->get_table_name_simplifications() . ' WHERE `oid` = %d AND `language` = %s', array( $this->get_id(), $language ) ), ARRAY_A );
		if ( ! empty( $result ) ) {
			// save in the object.
			$this->simplifications[ $language ] = $result['simplification'];

			// return result.
			return $result['simplification'];
		}

		// return an empty string if no translation exists.
		return '';
	}

	/**
	 * Save a new translation for this text.
	 *
	 * @param string $translated_text The translated text.
	 * @param string $target_language The target language.
	 * @param string $used_api The name of the used API.
	 * @param int    $job_id The API-internal job-ID.
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function set_simplification( string $translated_text, string $target_language, string $used_api, int $job_id ): void {
		global $wpdb;

		// get current user.
		$user_id = 0;
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( $user instanceof WP_User ) { // @phpstan-ignore instanceof.alwaysTrue
				$user_id = $user->ID;
			}
		}

		// save the simplification for this text.
		$query = array(
			'oid'            => $this->get_id(),
			'time'           => gmdate( 'Y-m-d H:i:s' ),
			'simplification' => $translated_text,
			'hash'           => $this->db->get_string_hash( $translated_text ),
			'language'       => $target_language,
			'used_api'       => $used_api,
			'jobid'          => $job_id,
			'user_id'        => $user_id,
		);
		$wpdb->insert( Db::get_instance()->get_table_name_simplifications(), $query );

		// log error.
		if ( $wpdb->last_error ) {
			Log::get_instance()->add_log( __( 'Error during adding simplification of text in DB: ', 'easy-language' ) . $wpdb->last_error, 'error' );
		} else {
			// change the state of text to "simplified".
			$this->set_state( 'simplified' );

			// save simplification in the object.
			$this->simplifications[ $target_language ] = $translated_text;
		}
	}

	/**
	 * Replace the original with the translation and save this in the object.
	 *
	 * @param int    $object_id The object-ID where the text should be replaced.
	 * @param string $target_language The target language for the translated text.
	 *
	 * @return bool
	 */
	public function replace_original_with_simplification( int $object_id, string $target_language ): bool {
		// get simplification objects.
		$simplification_objects = $this->get_objects();

		// bail if translation-objects could not be loaded.
		if ( empty( $simplification_objects ) ) {
			return false;
		}

		// get object.
		$object = Helper::get_object( $object_id );

		// bail of the object is not an Objects object.
		if ( ! $object instanceof Objects ) {
			return false;
		}

		/**
		 * Replace content in the simplification object depending on the field.
		 */
		foreach ( $simplification_objects as $translation_object ) {
			switch ( $translation_object['field'] ) {
				case 'title':
					// replace text depending on used pagebuilder for original text.
					$obj = $object->get_page_builder();

					// bail if the pagebuilder could not be loaded.
					if ( ! $obj ) {
						return false;
					}

					// set title.
					$obj->set_title( $object->get_title() );

					// get title.
					$title = $obj->get_title_with_simplifications( $object->get_title(), $this->get_simplification( $target_language ) );

					// update post-entry.
					$array = array(
						'ID'         => $object_id,
						'post_title' => wp_strip_all_tags( $title ),
					);

					// add individual post-name for permalink if enabled.
					if ( 1 === absint( get_option( 'easy_language_generate_permalink', 0 ) ) ) {
						$array['post_name'] = wp_unique_post_slug( $title, $object_id, $object->get_status(), $object->get_type(), 0 );
					}

					// save it.
					wp_update_post( $array );

					// run pagebuilder-specific tasks to update settings or trigger third party events.
					$obj->update_object( $object );
					break;

				case 'post_content':
					// replace text depending on used pagebuilder for original text.
					$obj = $object->get_page_builder();

					// do nothing if not page builder could be loaded.
					if ( ! $obj ) {
						return false;
					}

					// set object-id to pagebuilder-object.
					$obj->set_object_id( $object_id );

					// set original text to simplify in pagebuilder-object.
					$obj->set_text( $this->get_original() );

					// get the resulting text depending on pagebuilder.
					$content = $obj->get_text_with_simplifications( $object->get_content(), $this->get_simplification( $target_language ) );

					// update post-entry.
					$array = array(
						'ID'           => $object_id,
						'post_type'    => $translation_object['object_type'],
						'post_content' => $content,
					);
					wp_update_post( $array );
					break;
				default:
					$instance = $this;
					/**
					 * Hook for alternatives to replace texts with its simplified forms.
					 *
					 * @since 2.0.0 Available since 2.0.0.
					 *
					 * @param Text $instance The text icon object.
					 * @param string $target_language The target language.
					 * @param int $object_id The ID of the object.
					 * @param array $simplification_objects List of simplification objects.
					 */
					do_action( 'easy_language_replace_texts', $instance, $target_language, $object_id, $simplification_objects );
			}
		}

		// return true as we have replaced contents.
		return true;
	}

	/**
	 * Set the state of this text.
	 *
	 * Only if it is one of these valid states:
	 * - to_simplify => text will be simplified
	 * - processing => text is simplified
	 * - in_use => text has been simplified
	 * - ignore => will not be simplified
	 *
	 * Will save this state in DB.
	 *
	 * @param string $state The state for this simplification.
	 *
	 * @return void
	 */
	public function set_state( string $state ): void {
		// bail, if not allowed state, is used.
		if ( ! in_array( $state, array( 'to_simplify', 'processing', 'in_use', 'ignore' ), true ) ) {
			return;
		}

		// get the db connection.
		global $wpdb;

		// update the state of this text to the given state-string.
		$wpdb->update( Db::get_instance()->get_table_name_originals(), array( 'state' => $state ), array( 'id' => $this->get_id() ) );

		// log any DB-errors.
		if ( $wpdb->last_error ) {
			Log::get_instance()->add_log( __( 'Error during updating state of entry in DB:', 'easy-language' ) . ' <code>' . wp_json_encode( $wpdb->last_error ) . '</code>', 'error' );
		}
	}

	/**
	 * Delete this entry with all of its simplifications.
	 *
	 * @param int $object_id The object-ID which connection should be deleted primarily.
	 *
	 * @return void
	 */
	public function delete( int $object_id = 0 ): void {
		global $wpdb;

		// get object count before we do anything.
		$object_count = count( $this->get_objects() );

		// delete the connection between text and given object_id.
		if ( $object_id > 0 ) {
			$wpdb->delete(
				Db::get_instance()->get_table_name_originals_objects(),
				array(
					'oid'       => $this->get_id(),
					'blog_id'   => get_current_blog_id(),
					'object_id' => $object_id,
				)
			);
		} else {
			$wpdb->delete(
				Db::get_instance()->get_table_name_originals_objects(),
				array(
					'oid'     => $this->get_id(),
					'blog_id' => get_current_blog_id(),
				)
			);
		}

		// if this text is used only from 1 object, delete it complete, including its simplifications.
		if ( 1 === $object_count && 1 === absint( get_option( 'easy_language_delete_unused_simplifications', 0 ) ) ) {
			$wpdb->delete( Db::get_instance()->get_table_name_originals(), array( 'id' => $this->get_id() ) );
			$wpdb->delete( Db::get_instance()->get_table_name_simplifications(), array( 'oid' => $this->get_id() ) );
		}
	}

	/**
	 * Return the list of simplification-objects which are using this text.
	 *
	 * @return array<array<string,string>>
	 */
	public function get_objects(): array {
		global $wpdb;

		// get our own DB-object.
		$db = Db::get_instance();

		// get from DB.
		$prepared_sql = $wpdb->prepare(
			'SELECT o.`id`, oo.`object_type`, oo.`object_id`, oo.`page_builder`, o.`field`, oo.`state`
				FROM ' . $db->get_table_name_originals_objects() . ' oo
				JOIN ' . $db->get_table_name_originals() . ' o ON (o.id = oo.oid)
				WHERE oo.`oid` = %d AND oo.`blog_id` = %d',
			array( $this->get_id(), get_current_blog_id() )
		);

		// get the results.
		$results = $wpdb->get_results( $prepared_sql, ARRAY_A );

		// return empty array on no results.
		if ( empty( $results ) ) {
			return array();
		}

		// return the results.
		return $results;
	}

	/**
	 * Set the given ID and type as the object that is using this text.
	 *
	 * @param string $type The object-type (e.g. post, page, category).
	 * @param int    $item_id The object-ID from WP.
	 * @param int    $order The order of this text in this object.
	 * @param string $page_builder The used pagebuilder.
	 *
	 * @return void
	 */
	public function set_object( string $type, int $item_id, int $order, string $page_builder ): void {
		global $wpdb;
		$query = array(
			'oid'          => $this->get_id(),
			'time'         => gmdate( 'Y-m-d H:i:s' ),
			'object_type'  => $type,
			'object_id'    => $item_id,
			'order'        => $order,
			'blog_id'      => get_current_blog_id(),
			'page_builder' => $page_builder,
		);
		$wpdb->insert( Db::get_instance()->get_table_name_originals_objects(), $query );

		// log error.
		if ( $wpdb->last_error ) {
			Log::get_instance()->add_log( __( 'Error during adding object to original in DB: ', 'easy-language' ) . $wpdb->last_error, 'error' );
		}
	}

	/**
	 * Return the date of this object (when it was saved).
	 *
	 * @return string
	 */
	public function get_date(): string {
		global $wpdb;

		// get from DB.
		$prepared_sql = $wpdb->prepare(
			'SELECT o.`time`
				FROM ' . Db::get_instance()->get_table_name_originals() . ' o
				WHERE o.`id` = %d',
			array( $this->get_id() )
		);

		// get result.
		$result = (array) $wpdb->get_results( $prepared_sql, ARRAY_A );

		// return the time.
		return $result[0]['time'];
	}

	/**
	 * Return whether this text is used for the given field.
	 *
	 * @param string $field The requested field (e.g. post_content, title ..).
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public function is_field( string $field ): bool {
		foreach ( $this->get_objects() as $object_array ) {
			if ( $field === $object_array['field'] ) {
				return true;
			}
		}

		// return false in other cases.
		return false;
	}

	/**
	 * Return the target languages that depend on settings of the objects where the text is used.
	 *
	 * @return array<string,string>
	 * @noinspection PhpUnused
	 */
	public function get_target_languages(): array {
		// get languages-object.
		$languages_obj = Languages::get_instance();

		// get possible target languages.
		$languages = $languages_obj->get_possible_target_languages();

		// define the resulting array.
		$item_languages = array();

		// loop through the objects of this text.
		foreach ( $this->get_objects() as $object_array ) {
			// get the object.
			$object = Helper::get_object( absint( $object_array['object_id'] ), $object_array['object_type'] );

			// bail if the object is unknown.
			if ( ! $object ) {
				continue;
			}

			// get the first language from the object.
			$language = array_key_first( $object->get_language() );

			// bail if language is unknown.
			if ( empty( $languages[ $language ] ) ) {
				continue;
			}

			// add language to the list.
			$item_languages[ $language ] = $languages[ $language ]['label'];
		}

		// return the resulting list.
		return $item_languages;
	}

	/**
	 * Get for simplification used API.
	 *
	 * @return Api_Base|false
	 * @noinspection PhpUnused
	 */
	public function get_api(): Api_Base|false {
		global $wpdb;

		// get from DB.
		$prepared_sql = $wpdb->prepare(
			'SELECT s.`used_api`
				FROM ' . Db::get_instance()->get_table_name_simplifications() . ' s
				WHERE s.`oid` = %d',
			array( $this->get_id() )
		);

		// get result.
		$result = (array) $wpdb->get_results( $prepared_sql, ARRAY_A );

		// get API-object if its name could be read.
		if ( ! empty( $result ) ) {
			return Apis::get_instance()->get_api_by_name( $result[0]['used_api'] );
		}

		// get the API name.
		return false;
	}

	/**
	 * Return the user who requested a specific simplification.
	 *
	 * @param string $language The requested language.
	 * @return int
	 */
	public function get_user_for_simplification( string $language ): int {
		global $wpdb;

		// get from DB.
		$result = $wpdb->get_row( $wpdb->prepare( 'SELECT `user_id` FROM ' . Db::get_instance()->get_table_name_simplifications() . ' WHERE `oid` = %d AND `language` = %s', array( $this->get_id(), $language ) ), ARRAY_A );
		if ( ! empty( $result ) ) {
			// return result.
			return $result['user_id'];
		}

		// return zero if no simplification exists.
		return 0;
	}

	/**
	 * Return whether this text contains HTML (depending on widgets in pageBuilders).
	 *
	 * @return bool
	 */
	public function is_html(): bool {
		global $wpdb;

		// get from DB.
		$prepared_sql = $wpdb->prepare(
			'SELECT o.`html`
				FROM ' . Db::get_instance()->get_table_name_originals() . ' o
				WHERE o.`id` = %d',
			array( $this->get_id() )
		);

		// get result.
		$result = (array) $wpdb->get_results( $prepared_sql, ARRAY_A );

		// return the result.
		return 1 === absint( $result[0]['html'] );
	}
}
