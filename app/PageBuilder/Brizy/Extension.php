<?php
/**
 * File for the Brizy extension of Easy Language.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder\Brizy;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Brizy_Editor_ThirdParty_ExtensionInterface;
use easyLanguage\Plugin\Helper;

/**
 * Object to register our third party element (the language switcher) in Brizy.
 *
 * Brizy loads the editor scripts of extensions in its editor and in its compiler, which creates the HTML for the
 * frontend.
 */
class Extension implements Brizy_Editor_ThirdParty_ExtensionInterface {
	/**
	 * Return the public URL of the directory with our files.
	 *
	 * @return string
	 */
	public function getPublicPath() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return Helper::get_plugin_url() . 'brizy/build/';
	}

	/**
	 * Return the internal name of the extension.
	 *
	 * @return string
	 */
	public function getName() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return 'easy-language';
	}

	/**
	 * Return the title of the extension.
	 *
	 * @return string
	 */
	public function getTitle() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return __( 'Easy Language', 'easy-language' );
	}

	/**
	 * Return the category of the extension.
	 *
	 * @return string
	 */
	public function getCategory() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return __( 'Easy Language', 'easy-language' );
	}

	/**
	 * Return the scripts for the editor (and the compiler) of Brizy.
	 *
	 * @return array<int,string>
	 */
	public function getEditorScripts() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return array( $this->getPublicPath() . 'index.js?ver=' . rawurlencode( $this->getVersion() ) );
	}

	/**
	 * Return the scripts for the frontend.
	 *
	 * @return array<int,string>
	 */
	public function getViewScripts() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return array();
	}

	/**
	 * Return the styles for the editor.
	 *
	 * @return array<int,string>
	 */
	public function getEditorStyles() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return array();
	}

	/**
	 * Return the styles for the frontend.
	 *
	 * @return array<int,string>
	 */
	public function getViewStyles() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return array();
	}

	/**
	 * Return the version of the extension (the version of the build).
	 *
	 * @return string
	 */
	public function getVersion() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$asset_file = Helper::get_plugin_path() . 'brizy/build/index.asset.php';
		if ( file_exists( $asset_file ) ) {
			$asset = include $asset_file;
			if ( is_array( $asset ) && ! empty( $asset['version'] ) && is_string( $asset['version'] ) ) {
				return $asset['version'];
			}
		}
		return Helper::get_file_version( Helper::get_plugin_path() . 'brizy/build/index.js' );
	}
}
