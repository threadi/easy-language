<?php
/**
 * File for initializing the SCF-support.
 *
 * @package easy-language
 */

namespace easyLanguage\ThirdPartySupport;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\Db;
use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\EasyLanguage\Text;
use easyLanguage\Plugin\Base;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\ThirdPartySupport_Base;

/**
 * Object to handle the WPML support.
 */
class Scf extends Base implements ThirdPartySupport_Base {

	/**
	 * Name of this plugin.
	 *
	 * @var string
	 */
	protected string $name = 'scf';

	/**
	 * Title of this plugin.
	 *
	 * @var string
	 */
	protected string $title = 'SCF';

	/**
	 * Instance of this object.
	 *
	 * @var ?Scf
	 */
	private static ?Scf $instance = null;

	/**
	 * Constructor for Init-Handler.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Scf {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if plugin is not enabled.
		if ( ! $this->is_active() ) {
			return;
		}

		// use ACF hooks.
		add_action( 'acf/render_field_settings', array( $this, 'extend_field_settings' ) );

		// use our own hooks.
		add_action( 'easy_language_add_post_simplification', array( $this, 'add_meta_fields_to_simplification' ), 10, 3 );
		add_action( 'easy_language_replace_texts', array( $this, 'replace_meta_field_texts' ), 10, 4 );
	}

	/**
	 * Run on plugin-installation.
	 *
	 * @return void
	 */
	public function install(): void {}

	/**
	 * Run on uninstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void {}

	/**
	 * Run on deactivation.
	 *
	 * @return void
	 */
	public function deactivation(): void {}

	/**
	 * Additional cli functions we do not use for this plugin.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Return whether this object is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'secure-custom-fields/secure-custom-fields.php' );
	}

	/**
	 * Return the list of active languages this plugin is using atm.
	 *
	 * @return array<string,string>
	 */
	public function get_active_languages(): array {
		return array();
	}

	/**
	 * Embed simplification-related scripts, which are also used by some PageBuilders.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function get_simplifications_scripts(): void {}

	/**
	 * Add meta fields to simplification.
	 *
	 * @param Post_Object $post_object The post object.
	 * @param int         $post_id The post ID of the simplification object.
	 * @param string      $source_language The source language.
	 * @return void
	 */
	public function add_meta_fields_to_simplification( Post_Object $post_object, int $post_id, string $source_language ): void {
		// get all ACF fields on this object.
		$fields = get_fields( $post_object->get_id() );

		// bail if no fields are configured.
		if ( empty( $fields ) ) {
			return;
		}

		// get the field types.
		$field_types = $this->get_types();

		// get DB-object.
		$db = Db::get_instance();

		// check each field for its type.
		foreach ( $fields as $key => $content ) {
			// get the field array with its settings.
			$field_array = get_field_object( $key, $post_object->get_id() );

			// bail if field object could not be loaded.
			if ( ! is_array( $field_array ) ) {
				continue;
			}

			// bail if type is not set.
			if ( empty( $field_array['type'] ) ) {
				continue;
			}

			// bail if given type is not supported.
			if ( ! array_key_exists( $field_array['type'], $field_types ) ) {
				continue;
			}

			// bail if setting for this field is not enabled.
			if ( ! isset( $field_array['easy_language_simplify'] ) || 1 !== absint( $field_array['easy_language_simplify'] ) ) {
				continue;
			}

			// check if this field has already saved as original text for simplification.
			$original_meta_obj = $db->get_entry_by_text( $content, $source_language );
			if ( ! $original_meta_obj instanceof Text ) {
				// save the text for simplification.
				$original_meta_obj = $db->add( $content, $source_language, 'scf_meta_' . $key, $field_types[ $field_array['type'] ] );
				if ( ! $original_meta_obj instanceof Text ) {
					continue;
				}
			}
			$original_meta_obj->set_object( $post_object->get_type(), $post_id, 0, '' );
			$original_meta_obj->set_state( 'to_simplify' );
		}
	}

	/**
	 * Return the list of supported field types.
	 *
	 * @return array<string,bool>
	 */
	private function get_types(): array {
		$types = array(
			'textarea' => false,
			'wysiwyg'  => true,
		);

		/**
		 * Filter the list of simplifiable fields in ACF.
		 *
		 * @since 3.2.0 Available since 3.2.0.
		 * @param array<string,bool> $types List of types.
		 */
		return apply_filters( 'easy_language_acf_types', $types );
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
	public function replace_meta_field_texts( Text $text, string $target_language, int $object_id, array $simplification_objects ): void {
		foreach ( $simplification_objects as $simplification_object ) {
			update_field( str_replace( 'scf_meta_', '', $simplification_object['field'] ), $text->get_simplification( $target_language ), $object_id );
		}
	}

	/**
	 * Return whether this is a language plugin.
	 *
	 * @return bool
	 */
	public function is_language_plugin(): bool {
		return false;
	}

	/**
	 * Extend the settings for supported fields.
	 *
	 * @param array<string,mixed> $field The field.
	 * @return void
	 */
	public function extend_field_settings( array $field ): void {
		// bail if this field is not supported.
		if ( ! array_key_exists( $field['type'], $this->get_types() ) ) {
			return;
		}

		// add setting to enable the simplification.
		acf_render_field_setting(
			$field,
			array(
				'label'        => __( 'Can be simplified', 'easy-language' ),
				'instructions' => '',
				'name'         => 'easy_language_simplify',
				'type'         => 'true_false',
				'ui'           => 1,
			),
			true
		);
	}
}
