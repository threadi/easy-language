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
		add_filter( 'wp_insert_post_data', array( $this, 'fix_product_duplication_slashing' ), 1 );
	}

	/**
	 * Check if Divi 5.0 or newer is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		// first check for the plugin.
		if ( Helper::is_plugin_active( 'divi-builder/divi-builder.php' ) ) {
			// get the plugin version.
			require_once ABSPATH . 'wp-admin/includes/admin.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/divi-builder/divi-builder.php' );
			if ( version_compare( $plugin_data['Version'], '5.0.0', '>=' ) ) {
				return true;
			}
		}

		// check for the theme.
		$theme = wp_get_theme();
		if ( 'Divi' === $theme->get( 'Name' ) ) {
			$version = substr( $theme->get( 'Version' ), 0, 5 );
			return version_compare( $version, '5.0.0', '>=' );
		}

		// check for the parent theme.
		if ( $theme->parent() && 'Divi' === $theme->parent()->get( 'Name' ) ) {
			$version = substr( $theme->parent()->get( 'Version' ), 0, 5 );
			return version_compare( $version, '5.0.0', '>=' );
		}

		// otherwise return false.
		return false;
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

		// slash the content.
		$data['post_content'] = SavingUtility::maybe_add_slash( $data['post_content'] );

		// return the resulting post data.
		return $data;
	}
}
