<?php
/**
 * File to handle support for the page builder Bricks.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// deny direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\EasyLanguage\Parser_Base;
use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\EasyLanguage\Switcher;
use easyLanguage\PageBuilder\Bricks\Switcher_Element;
use easyLanguage\Plugin\Helper;

/**
 * Object to handle support for the page builder Bricks.
 */
class Bricks extends PageBuilder_Base {
	/**
	 * Instance of this object.
	 *
	 * @var ?Bricks
	 */
	private static ?Bricks $instance = null;

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
	public static function get_instance(): Bricks {
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
		add_filter( 'builder/settings/page/controls_data', array( $this, 'add_page_setting' ) );
		add_action( 'init', array( $this, 'register_elements' ), 11 );
		add_action( 'wp_ajax_easy_language_bricks_objects', array( $this, 'get_object_options' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_builder_styles' ) );
	}

	/**
	 * Add the style for the icon of our elements in the Bricks editor.
	 *
	 * @return void
	 */
	public function add_builder_styles(): void {
		// bail if this is not the main window of the Bricks editor.
		if ( ! $this->is_active() || ! function_exists( 'bricks_is_builder_main' ) || ! bricks_is_builder_main() ) {
			return;
		}

		// add the style for the icon.
		wp_enqueue_style( 'easy-language-builder-icon' );
	}

	/**
	 * Register our own elements in Bricks.
	 *
	 * Bricks registers its own elements during "init" with priority 10, so we use a later priority.
	 *
	 * @return void
	 */
	public function register_elements(): void {
		// bail if Bricks is not active.
		if ( ! $this->is_active() || ! class_exists( '\Bricks\Elements' ) ) {
			return;
		}

		// register the language switcher element (Bricks loads the file itself).
		\Bricks\Elements::register_element( Helper::get_plugin_path() . 'app/PageBuilder/Bricks/Switcher_Element.php', 'easy-language-switcher', Switcher_Element::class );
	}

	/**
	 * Return the options for the visibility objects of the switcher element via AJAX.
	 *
	 * Used by the select field in the Bricks editor which loads its options via AJAX. Supports the search
	 * (parameter "search") and the labels of already chosen objects (parameter "include").
	 *
	 * @return void
	 */
	public function get_object_options(): void {
		// check the nonce of the Bricks editor.
		if ( ! check_ajax_referer( 'bricks-nonce-builder', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// check the capability.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Missing capability' );
		}

		// get the switcher object.
		$switcher = Switcher::get_instance();

		// get the search.
		$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

		// get the options.
		$options = array();
		if ( ! empty( $search ) ) {
			foreach ( $switcher->search_objects( $search, 50 ) as $object ) {
				$options[ $object['value'] ] = $object['label'];
			}
		} else {
			$options = $switcher->get_widget_object_options();
		}

		// add the labels of the chosen objects.
		$include = isset( $_GET['include'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_GET['include'] ) ) : array();
		foreach ( $switcher->get_object_labels( array_values( $include ) ) as $object ) {
			$options[ $object['value'] ] = $object['label'];
		}

		// return the options.
		wp_send_json_success( $options );
	}

	/**
	 * Add page setting to show the actual language.
	 *
	 * @param array<string,mixed> $data The page settings.
	 *
	 * @return array<string,mixed>
	 */
	public function add_page_setting( array $data ): array {
		// get the requested post-id.
		$post_id = get_the_ID();

		// bail if no post-id is found.
		if ( ! is_int( $post_id ) ) {
			return $data;
		}

		// get the ID of the original post.
		$original_post_id = absint( get_post_meta( $post_id, 'easy_language_simplification_original_id', true ) );
		if ( 0 === $original_post_id ) {
			$original_post_id = $post_id;
		}

		// get the original-object.
		$original_post_object = new Post_Object( $original_post_id );

		// get the page builder object.
		$page_builder = $original_post_object->get_page_builder();

		// bail if the page builder could not be loaded.
		if ( ! $page_builder instanceof Parser_Base ) {
			return $data;
		}

		// get the content.
		ob_start();
		$page_builder->get_language_switch( $post_id );
		$content = ob_get_clean();
		if ( ! $content ) {
			return $data;
		}

		// add our own control groups.
		$data['controlGroups']['easy-language'] = array(
			'title'      => esc_html__( 'Easy Language', 'easy-language' ),
			'fullAccess' => true,
		);

		// add the info about the languages of this page.
		$data['controls']['easy_language'] = array(
			'group'   => 'easy-language',
			'type'    => 'info',
			'label'   => esc_html__( 'Simplify texts', 'easy-language' ),
			'content' => $content,
		);

		// return the resulting page settings.
		return $data;
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		$is_bricks = false;
		$theme     = wp_get_theme();
		if ( 'Bricks' === $theme->get( 'Name' ) ) {
			$is_bricks = true;
		}
		if ( $theme->parent() && 'Bricks' === $theme->parent()->get( 'Name' ) ) {
			$is_bricks = true;
		}
		return $is_bricks;
	}
}
