<?php
/**
 * File for handling von operations via WP CLI.
 *
 * @package easy-language
 */

namespace easyLanguage;

use easyLanguage\Api\Summ_Ai\Request;
use easyLanguage\Multilingual_plugins\Easy_Language\Db;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handler for CLI-operations.
 */
class Cli {
	/**
	 * Reset the plugin as it will be de- and reinstalled.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function reset_plugin(): void {
		// uninstall everything from the plugin.
		$uninstaller = Uninstall::get_instance();
		$uninstaller->run();

		// run actions as if the plugin is activated.
		$init = Install::get_instance();
		$init->activation();

		// return ok-message.
		\WP_CLI::success("Plugin has been reset.");
	}
}
