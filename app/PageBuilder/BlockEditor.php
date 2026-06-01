<?php
/**
 * File to handle support for the page builder Block Editor.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// deny direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\Init;
use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\Plugin\Helper;
use WP_Screen;

/**
 * Object to handle support for the page builder Block Editor.
 */
class BlockEditor extends PageBuilder_Base {
	/**
	 * Instance of this object.
	 *
	 * @var ?BlockEditor
	 */
	private static ?BlockEditor $instance = null;

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
	public static function get_instance(): BlockEditor {
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
		add_action( 'init', array( $this, 'register_sidebar' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_sidebar' ) );
	}

	/**
	 * Register our block for the sidebar.
	 *
	 * @return void
	 */
	public function register_sidebar(): void {
		wp_register_script(
			'easy-language-sidebar',
			Helper::get_plugin_url() . 'js/generated/sidebar.js',
			array( 'wp-plugins', 'wp-editor', 'react' ),
			Helper::get_file_version( Helper::get_plugin_path() . 'js/generated/sidebar.js' ),
			true
		);
	}

	/**
	 * Enqueue the sidebar in the editor.
	 *
	 * @return void
	 */
	public function enqueue_sidebar(): void {
		global $wp_version;

		// bail if the screen function is not present.
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		// bail if the version is not 7.0 or newer.
		if ( ! version_compare( $wp_version, '7.0', '>=' ) ) {
			return;
		}

		// get the screen.
		$screen = get_current_screen();

		// bail if the used post-type is not supported.
		if ( $screen instanceof WP_Screen && ! Init::get_instance()->is_post_type_supported( $screen->post_type ) ) {
			return;
		}

		// enqueue the sidebar in the editor.
		wp_enqueue_script( 'easy-language-sidebar' );
	}

	/**
	 * Return whether the page builder is active or not.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return true;
	}
}
