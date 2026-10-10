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
		// set the fallback dependencies and version.
		$dependencies = array( 'wp-plugins', 'wp-editor', 'react', 'wp-i18n' );
		$version      = '';

		// get the generated asset file of the sidebar script.
		$script_asset_path = Helper::get_plugin_path() . 'js/generated/sidebar.asset.php';

		// use its dependencies and version if it exists.
		$wp_filesystem = Helper::get_wp_filesystem( true );
		if ( $wp_filesystem->exists( $script_asset_path ) ) {
			$script_asset = require $script_asset_path;
			if ( is_array( $script_asset ) ) {
				if ( ! empty( $script_asset['dependencies'] ) && is_array( $script_asset['dependencies'] ) ) {
					$dependencies = $script_asset['dependencies'];
				}
				if ( ! empty( $script_asset['version'] ) && is_string( $script_asset['version'] ) ) {
					$version = $script_asset['version'];
				}
			}
		}

		// use the file version as fallback.
		if ( empty( $version ) && $wp_filesystem->exists( Helper::get_plugin_path() . 'js/generated/sidebar.js' ) ) {
			$version = Helper::get_file_version( Helper::get_plugin_path() . 'js/generated/sidebar.js' );
		}

		// register the script.
		wp_register_script(
			'easy-language-sidebar',
			Helper::get_plugin_url() . 'js/generated/sidebar.js',
			$dependencies,
			empty( $version ) ? false : $version,
			true
		);

		// add translations for the script.
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				'easy-language-sidebar',
				'easy-language',
				Helper::get_plugin_path() . 'languages/'
			);
		}
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
