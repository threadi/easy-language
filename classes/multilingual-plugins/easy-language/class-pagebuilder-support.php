<?php
/**
 * File for our own page-builder-support.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use WP_Post;

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
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize pagebuilder-support for our own plugin.
	 *
	 * @return void
	 */
	public function init(): void {
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
		// get supported post-types.
		$post_types = Init::get_instance()->get_supported_post_types();

		// bail if post type is not supported.
		if ( empty( $post_types[ $post_type ] ) ) {
			return;
		}

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

	/**
	 * Content of meta-box with infos about:
	 * - the actual edited language.
	 * - which language is also available.
	 * - the possibility to delete this simplification complete.
	 *
	 * @param WP_Post $post The Post-object.
	 *
	 * @return void
	 */
	public function render_meta_box_content( WP_Post $post ): void {
		// get the ID of the original post.
		$original_post_id = absint( get_post_meta( $post->ID, 'easy_language_simplification_original_id', true ) );
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

		// output.
		if ( ! empty( $language ) ) {
			?>
			<p>
				<?php
					/* translators: %1$s will be replaced by the type of the object, %2$s will be replaced by the name of the language */
					echo wp_kses_post( sprintf( __( 'You are editing this %1$s in the language <strong>%2$s</strong>.', 'easy-language' ), esc_html( $post_object->get_type_name() ), esc_html( $language['label'] ) ) );
				?>
			</p>
			<?php
		}

		/**
		 * Get page builder of this object.
		 */
		$page_builder = $original_post_object->get_page_builder();

		/**
		 * Bail if page builder could not be loaded.
		 */
		if ( ! $page_builder ) {
			return;
		}

		/**
		 * Show list of active languages the content could be simplified
		 * only if page builder is active.
		 */
		if ( $page_builder->is_active() ) {
			$page_builder->get_language_switch();
		}
	}
}
