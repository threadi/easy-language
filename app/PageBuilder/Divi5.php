<?php
/**
 * File to handle support for the page builder Divi 5.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder;

// deny direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\PageBuilder_Base;
use easyLanguage\Plugin\Helper;
use ET\Builder\VisualBuilder\Saving\SavingUtility;

/**
 * Object to handle support for the page builder Divi 5.
 */
class Divi5 extends PageBuilder_Base {
	/**
	 * Instance of this object.
	 *
	 * @var ?Divi5
	 */
	private static ?Divi5 $instance = null;

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
	public static function get_instance(): Divi5 {
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
		// bail if Divi 5 is not active.
		if ( ! $this->is_active() ) {
			return;
		}

		// use hooks.
		add_action( 'woocommerce_product_duplicate_before_save', array( $this, 'enable_product_duplication_slashing' ) );
		add_action( 'woocommerce_product_duplicate', array( $this, 'disable_product_duplication_slashing' ) );
		add_action( 'init', array( $this, 'register_modules' ) );
		add_action( 'divi_visual_builder_assets_before_enqueue_scripts', array( $this, 'register_vb_scripts' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Return whether the module API of Divi 5 is available.
	 *
	 * @return bool
	 */
	private function is_module_api_available(): bool {
		return $this->is_active() && class_exists( '\ET\Builder\Packages\ModuleLibrary\ModuleRegistration' );
	}

	/**
	 * Register our own modules in Divi 5.
	 *
	 * @return void
	 */
	public function register_modules(): void {
		// bail if the module API of Divi 5 is not available.
		if ( ! $this->is_module_api_available() ) {
			return;
		}

		// register the language switcher module.
		Divi5\Switcher_Module::get_instance()->register();
	}

	/**
	 * Register the scripts for our modules in the visual builder of Divi 5.
	 *
	 * @return void
	 */
	public function register_vb_scripts(): void {
		// bail if the module API of Divi 5 is not available.
		if ( ! $this->is_module_api_available() || ! class_exists( '\ET\Builder\VisualBuilder\Assets\PackageBuildManager' ) ) {
			return;
		}

		// register the script of the language switcher module.
		Divi5\Switcher_Module::get_instance()->register_vb_script();
	}

	/**
	 * Register the REST API routes for our modules (used for the preview in the visual builder).
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		// bail if the module API of Divi 5 is not available.
		if ( ! $this->is_module_api_available() ) {
			return;
		}

		// register the route of the language switcher module.
		Divi5\Switcher_Module::get_instance()->register_rest_route();
	}

	/**
	 * Check if Divi 5.0 or newer is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		// use the cached result.
		static $is_active = null;
		if ( is_bool( $is_active ) ) {
			return $is_active;
		}

		// first check for the plugin.
		if ( Helper::is_plugin_active( 'divi-builder/divi-builder.php' ) ) {
			// get the plugin version.
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/divi-builder/divi-builder.php', false, false );
			if ( version_compare( $plugin_data['Version'], '5.0.0', '>=' ) ) {
				$is_active = true;
				return $is_active;
			}
		}

		// check for the theme.
		$theme = wp_get_theme();
		if ( 'Divi' === $theme->get( 'Name' ) ) {
			$version   = substr( (string) $theme->get( 'Version' ), 0, 5 );
			$is_active = version_compare( $version, '5.0.0', '>=' );
			return $is_active;
		}

		// check for the parent theme.
		if ( $theme->parent() && 'Divi' === $theme->parent()->get( 'Name' ) ) {
			$version   = substr( (string) $theme->parent()->get( 'Version' ), 0, 5 );
			$is_active = version_compare( $version, '5.0.0', '>=' );
			return $is_active;
		}

		// otherwise return false.
		$is_active = false;
		return $is_active;
	}

	/**
	 * Enable the slashing fix for D5 content during the duplication of a WooCommerce product.
	 *
	 * WooCommerce saves the duplicated product without slashing its content. Our own copies are already slashed,
	 * so the fix must not run for every insert or update.
	 *
	 * @return void
	 */
	public function enable_product_duplication_slashing(): void {
		add_filter( 'wp_insert_post_data', array( $this, 'fix_product_duplication_slashing' ), 1 );
	}

	/**
	 * Disable the slashing fix for D5 content after the duplication of a WooCommerce product.
	 *
	 * @return void
	 */
	public function disable_product_duplication_slashing(): void {
		remove_filter( 'wp_insert_post_data', array( $this, 'fix_product_duplication_slashing' ), 1 );
	}

	/**
	 * Fix slashing issue with D5 content.
	 *
	 * D5 content contains JSON-encoded Unicode escape sequences (e.g., \u003c for <, \u0026 for &) that
	 * need to be slashed before wp_insert_post() because WordPress core will unslash it during processing.
	 * Without proper slashing, the Unicode sequences get corrupted and appear as literal text on the frontend.
	 *
	 * @since 3.1.1
	 * @source woocommerce.php from Divi 5 theme.
	 *
	 * @param array<string,mixed> $data    An array of slashed, sanitized, and processed post data.
	 *
	 * @return array<string,mixed> Modified post data with corrected slashing for D5 content.
	 */
	public function fix_product_duplication_slashing( array $data ): array {
		// bail if no post content is given.
		if ( ! isset( $data['post_content'] ) ) {
			return $data;
		}

		// check if we have content from Divi 5 in the post content.
		$has_d5_content = (
			str_contains( $data['post_content'], '<!-- wp:divi/' ) ||
			str_contains( $data['post_content'], '<!-- wp:divi:' )
		);

		// bail if we do not have any Divi 5 content.
		if ( ! $has_d5_content ) {
			return $data;
		}

		// bail if the Divi utility is not available.
		if ( ! class_exists( SavingUtility::class ) || ! method_exists( SavingUtility::class, 'maybe_add_slash' ) ) {
			return $data;
		}

		// slash the content.
		$data['post_content'] = SavingUtility::maybe_add_slash( $data['post_content'] );

		// return the resulting post data.
		return $data;
	}
}
