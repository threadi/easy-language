<?php
/**
 * File for our own simplification-machine.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

use easyLanguage\Languages;

/**
 * Object for single translatable text based on DB-dataset.
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
	 * @var array
	 */
	private array $simplifications = array();

	/**
	 * Source language.
	 *
	 * @var string
	 */
	private string $source_language;

	/**
	 * Constructor for this object.
	 *
	 * @param int $id The original object id of this text from originals-table.
	 */
	public function __construct( int $id ) {
		global $wpdb;

		// secure id of this object.
		$this->id = $id;

		// set the table-name for originals.
		$wpdb->easy_language_originals = DB::get_instance()->get_wpdb_prefix() . 'easy_language_originals';

		// set the table-name for originals.
		$wpdb->easy_language_originals_objects = DB::get_instance()->get_wpdb_prefix() . 'easy_language_originals_objects';

		// set the table-name for simplifications.
		$wpdb->easy_language_simplifications = DB::get_instance()->get_wpdb_prefix() . 'easy_language_simplifications';
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
	 * Set original text.
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
	 * Set original text.
	 *
	 * @param string $language The source-language.
	 * @return void
	 */
	public function set_source_language( string $language ): void {
		$this->source_language = $language;
	}

	/**
	 * Get the simplification of this text in the given language from Db.
	 *
	 * If given language is unknown or no simplification exist,
	 * return the original text.
	 *
	 * @param string $target_language The target language for this simplification.
	 * @return string
	 */
	public function get_translation( string $target_language ): string {
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
	public function has_translation_in_language( string $language ): bool {
		return ! empty( $this->get_translation_from_db( $language ) );
	}

	/**
	 * Get translation of this text in the given language.
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
		$result = $wpdb->get_row( $wpdb->prepare( 'SELECT `translation` FROM ' . $wpdb->easy_language_simplifications . ' WHERE `oid` = %d AND `language` = %s', array( $this->get_id(), $language ) ), ARRAY_A );
		if ( ! empty( $result ) ) {
			// save in object.
			$this->simplifications[ $language ] = $result['translation'];

			// return result.
			return $result['translation'];
		}

		// return empty string if no translation exist.
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
	public function set_translation( string $translated_text, string $target_language, string $used_api, int $job_id ): void {
		global $wpdb;

		// get current user.
		$user_id = 0;
		if ( is_user_logged_in() ) {
			$user    = wp_get_current_user();
			$user_id = $user->ID;
		}

		// save the simplification for this text.
		$query = array(
			'oid'         => $this->get_id(),
			'time'        => gmdate( 'Y-m-d H:i:s' ),
			'translation' => $translated_text,
			'language'    => $target_language,
			'used_api'    => $used_api,
			'jobid'       => $job_id,
			'user_id'     => $user_id,
		);
		$wpdb->insert( $wpdb->easy_language_simplifications, $query );

		// change state of text to "simplified".
		$this->set_state( 'simplified' );

		// save simplification in object.
		$this->simplifications[ $target_language ] = $translated_text;
	}

	/**
	 * Replace the original with the translation and save this in object.
	 *
	 * @param int    $object_id The object-ID where the text should be replaced.
	 * @param string $target_language The target language for the translated text.
	 *
	 * @return bool
	 */
	public function replace_original_with_translation( int $object_id, string $target_language ): bool {
		// get simplification objects.
		$simplification_objects = $this->get_objects();

		// bail if translation-objects could not be loaded.
		if ( empty( $simplification_objects ) ) {
			return false;
		}

		// get object.
		$object = Init::get_instance()->get_object_by_wp_object( get_post( $object_id ), $object_id );

		// bail of no object could be loaded.
		if ( false === $object ) {
			return false;
		}

		/**
		 * Replace content depending on the field.
		 */
		foreach ( $simplification_objects as $translation_object ) {
			switch ( $translation_object['field'] ) {
				case 'title':
					// replace text depending on used pagebuilder for original text.
					$obj = $object->get_page_builder();
					$obj->set_title( $object->get_title() );

					// get title.
					$title = $obj->get_title_with_simplifications( $object->get_title(), $this->get_translation( $target_language ) );

					// update post-entry.
					$query = array(
						'ID'         => $object_id,
						'post_title' => wp_strip_all_tags( $title ),
					);

					// add individual post-name for permalink of enabled.
					if ( 1 === absint( get_option( 'easy_language_generate_permalink', 0 ) ) ) {
						$query['post_name'] = wp_unique_post_slug( $title, $object_id, $object->get_status(), $object->get_type(), 0 );
					}

					// save it.
					wp_update_post( $query );

					// run pagebuilder-specific tasks to update settings or trigger third party events.
					$obj->update_object( $object );
					break;

				case 'post_content':
					// replace text depending on used pagebuilder for original text.
					$obj = $object->get_page_builder();

					// do nothing if not page builder could be loaded.
					if ( false === $obj ) {
						return false;
					}

					// set object-id to pagebuilder-object.
					$obj->set_object_id( $object_id );

					// set original text to simplify in pagebuilder-object.
					$obj->set_text( $this->get_original() );

					// get the resulting text depending on pagebuilder.
					$content = $obj->get_text_with_simplifications( $object->get_content(), $this->get_translation( $target_language ) );

					// update post-entry.
					$query = array(
						'ID'           => $object_id,
						'post_type'    => $translation_object['object_type'],
						'post_content' => $content,
					);
					wp_update_post( $query );
					break;
				default:
					do_action( 'easy_language_replace_texts', $this, $target_language, $object_id, $translation_objects );
			}
		}

		// set state to "in_use" to mark text as translated and inserted.
		$this->set_state( 'in_use' );

		// return true as we have replaced contents.
		return true;
	}

	/**
	 * Set state of this text.
	 * Will save it also in DB.
	 *
	 * @param string $state The state for this translation.
	 * @return void
	 */
	private function set_state( string $state ): void {
		global $wpdb;
		$wpdb->update( $wpdb->easy_language_originals, array( 'state' => $state ), array( 'id' => $this->get_id() ) );
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

		// delete connection between text and given object_id.
		if ( $object_count > 0 ) {
			$wpdb->delete(
				$wpdb->easy_language_originals_objects,
				array(
					'oid'       => $this->get_id(),
					'blog_id'   => get_current_blog_id(),
					'object_id' => $object_id,
				)
			);
		} else {
			$wpdb->delete(
				$wpdb->easy_language_originals_objects,
				array(
					'oid'     => $this->get_id(),
					'blog_id' => get_current_blog_id(),
				)
			);
		}

		// if this text is used only from 1 object, delete it completely with its simplifications.
		// TODO einstellbar machen, ob wirklich alle übersetzungen gelöscht werden sollen (um datenbank clean zu halten)
		if ( 1 === $object_count ) {
			$wpdb->delete( $wpdb->easy_language_originals, array( 'id' => $this->get_id() ) );
			$wpdb->delete( $wpdb->easy_language_simplifications, array( 'oid' => $this->get_id() ) );
		}
	}

	/**
	 * Return list of objects which are using this text.
	 *
	 * @return array
	 */
	public function get_objects(): array {
		global $wpdb;

		// get from DB.
		$prepared_sql = $wpdb->prepare(
			'SELECT oo.`object_type`, oo.`object_id`, oo.`page_builder`, o.`field`, oo.`state`
				FROM ' . $wpdb->easy_language_originals_objects . ' oo
				JOIN ' . $wpdb->easy_language_originals . ' o ON (o.id = oo.oid)
				WHERE oo.`oid` = %d AND oo.`blog_id` = %d',
			array( $this->get_id(), get_current_blog_id() )
		);

		// return result.
		return (array) $wpdb->get_results( $prepared_sql, ARRAY_A );
	}

	/**
	 * Set given ID and type as object which is using this text.
	 *
	 * @param string $type The object-type (e.g. post, page, category).
	 * @param int    $item_id The object-ID from WP.
	 * @param string $page_builder The used pagebuilder.
	 * @return void
	 */
	public function set_object( string $type, int $item_id, string $page_builder ): void {
		global $wpdb;
		$query = array(
			'oid'          => $this->get_id(),
			'time'         => gmdate( 'Y-m-d H:i:s' ),
			'object_type'  => $type,
			'object_id'    => $item_id,
			'blog_id'      => get_current_blog_id(),
			'page_builder' => $page_builder,
		);
		$wpdb->insert( $wpdb->easy_language_originals_objects, $query );
	}
}
