<?php
/**
 * File for our own page-builder-support.
 *
 * This file handles the extension of page builders for our plugin.
 * This does not include the parsing of content in this page builders.
 *
 * @package easy-language
 */

namespace easyLanguage\EasyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use WP_Post;

/**
 * Helper for support for different page-builders.
 */
class PageBuilders {

	/**
	 * Instance of this object.
	 *
	 * @var ?PageBuilders
	 */
	private static ?PageBuilders $instance = null;

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
	public static function get_instance(): PageBuilders {
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
		// bail if the given post-type is not supported.
		if ( ! Init::get_instance()->is_post_type_supported( $post_type) ) {
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

	/**
	 * Return the list of all page builders as objects.
	 *
	 * @return array<int,Parser_Base>
	 */
	public function get_page_builder_as_objects(): array {
		// create the list.
		$list = array();

		foreach ( $this->get_page_builder() as $page_builder_class_name ) {
			// create the classname.
			$classname = $page_builder_class_name . '::get_instance';

			// bail if classname is not callable.
			if ( ! is_callable( $classname ) ) {
				continue;
			}

			// get the object.
			$obj = $classname();

			// bail if the object is not the handler base.
			if ( ! $obj instanceof Parser_Base ) {
				continue;
			}

			// add the object to the list.
			$list[] = $obj;
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return the list of page builder class names we support.
	 *
	 * @return array<int,string>
	 */
	private function get_page_builder(): array {
		// create the list.
		$list = array(
			'\easyLanguage\PageBuilder\Avada',
			'\easyLanguage\PageBuilder\Avia',
			'\easyLanguage\PageBuilder\BeaverBuilder',
			'\easyLanguage\PageBuilder\BoldBuilder',
			'\easyLanguage\PageBuilder\Breakdance',
			'\easyLanguage\PageBuilder\Brizy',
			'\easyLanguage\PageBuilder\Divi',
			'\easyLanguage\PageBuilder\Elementor',
			'\easyLanguage\PageBuilder\Gutenberg',
			'\easyLanguage\PageBuilder\Kubio',
			'\easyLanguage\PageBuilder\Salients_WpBakery',
			'\easyLanguage\PageBuilder\SeedProd',
			'\easyLanguage\PageBuilder\SiteOrigin',
			'\easyLanguage\PageBuilder\Themify',
			'\easyLanguage\PageBuilder\Undetected',
			'\easyLanguage\PageBuilder\VisualComposer',
			'\easyLanguage\PageBuilder\WpBakery',
		);

		/**
		 * Filter the list of supported page builders.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param array<int,string> $list The list of supported page builders.
		 */
		return apply_filters( 'easy_language_page_builder_list', $list );
	}
}
