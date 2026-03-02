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
use easyLanguage\EasyLanguage\Post_Object;

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
		add_filter( 'builder/settings/page/controls_data', array( $this, 'add_page_setting' ), 10, 2 );
	}

	/**
	 * Add page setting to show the actual language.
	 *
	 * @param array<string,mixed> $data
	 *
	 * @return array<string,mixed>
	 */
	public function add_page_setting( array $data ): array {
		// get the requested post-id.
		$post_id = get_the_ID();

		// bail if no post-id is found.
		if( 0 === $post_id ) {
			return $data;
		}

		// get the ID of the original post.
		$original_post_id = absint( get_post_meta( $post_id, 'easy_language_simplification_original_id', true ) );
		if ( 0 === $original_post_id ) {
			$original_post_id = $post_id;
		}

		// get the original-object.
		$original_post_object = new Post_Object( $original_post_id );

		// get the content.
		ob_start();
		$original_post_object->get_page_builder()->get_language_switch( $post_id );
		$content = ob_get_clean();
		if( ! $content ) {
			return $data;
		}

		// add our own control groups
		$data['controlGroups']['easy-language'] = array(
			'title' => esc_html__( 'Easy Language', 'easy-language' ),
			'fullAccess' => true,
		);

		// add the info about the languages of this page.
		$data['controls']['easy_language'] = array(
			'group'       => 'easy-language',
			'type'        => 'info',
			'label'       => esc_html__( 'Simplify texts', 'easy-language' ),
			'content' => $content
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
		$theme   = wp_get_theme();
		if ( 'Bricks' === $theme->get( 'Name' ) ) {
			$is_bricks = true;
		}
		if ( $theme->parent() && 'Bricks' === $theme->parent()->get( 'Name' ) ) {
			$is_bricks = true;
		}
		return $is_bricks;
	}
}
