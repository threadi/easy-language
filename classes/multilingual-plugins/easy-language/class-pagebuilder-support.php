<?php
/**
 * File for our own page-builder-support.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

use easyLanguage\Apis;
use easyLanguage\Base;
use easyLanguage\Helper;
use easyLanguage\Multilingual_Plugins;
use WP_Post;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper for pagebuilder-support.
 */
class Pagebuilder_Support {

	/**
	 * Instance of this object.
	 *
	 * @var ?Pagebuilder_Support
	 */
	private static ?Pagebuilder_Support $instance = null;

	/**
	 * Instance of initializing object.
	 *
	 * @var Base
	 */
	private Base $init;

	/**
	 * Constructor for this object.
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
	public static function get_instance(): Pagebuilder_Support {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Initialize pagebuilder-support for our own plugin.
	 *
	 * @param Base $init The Base-object.
	 *
	 * @return void
	 */
	public function init( Base $init ): void {
		// secure initializing object.
		$this->init = $init;

		// add meta-box.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
	}

	/**
	 * Add language-meta-box for edit-page of some post types.
	 *
	 * @param string $post_type The requested post-type.
	 *
	 * @return void
	 */
	public function add_meta_box( string $post_type ): void {
		// bail if support for our own languages is handled by other multilingual plugin.
		if ( Multilingual_Plugins::get_instance()->is_plugin_with_support_for_given_languages_enabled( $this->init->get_supported_languages() ) ) {
			return;
		}

		// only for supported post-types.
		$post_types = Init::get_instance()->get_supported_post_types();
		if ( ! empty( $post_types[ $post_type ] ) ) {
			// add meta-box.
			add_meta_box(
				'easy-language',
				__( 'Language', 'easy-language' ),
				array( $this, 'render_meta_box_content' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Content of meta-box with infos about ..
	 * .. the actual edited language.
	 * .. which language is also available.
	 * .. the possibility to delete this simplification complete.
	 *
	 * @param WP_Post $post The Post-object.
	 *
	 * @return void
	 */
	public function render_meta_box_content( WP_Post $post ): void {
		// get the ID of the original post.
		$original_post_id = absint( get_post_meta( $post->ID, 'easy_language_translation_original_id', true ) );
		if ( 0 === $original_post_id ) {
			$original_post_id = absint( $post->ID );
		}

		// get the post-object of the actual object.
		$post_object = new Post_Object( $post->ID );

		// get the original-object.
		$original_post_object = new Post_Object( $original_post_id );

		// get the post-language.
		$language_array = $post_object->get_language();
		$language       = reset( $language_array );

		// get object type name.
		$object_type_name = Helper::get_objekt_type_name( $post_object );

		// output.
		if ( ! empty( $language ) ) {
			?>
			<p>
				<?php
				/* translators: %1$s will be replaced by the type of the object, %2$s will be replaced by the name of the language */
					echo wp_kses_post( sprintf( __( 'You are editing this %1$s in the language <strong>%2$s</strong>.', 'easy-language' ), esc_html( $object_type_name ), esc_html( $language['label'] ) ) );
				?>
			</p>
			<?php
		}

		/**
		 * Get page builder of this object.
		 */
		$page_builder = $original_post_object->get_page_builder();

		/**
		 * Show list of active languages the content could be translated
		 * only if page builder is active.
		 */
		if ( $page_builder->is_active() ) {
			$page_builder->get_language_switch();
		}
	}
}
