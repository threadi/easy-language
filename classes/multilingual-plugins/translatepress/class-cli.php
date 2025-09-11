<?php
/**
 * File for handling of operations via WP CLI.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\TranslatePress;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Handler for CLI-operations.
 */
class Cli {

	/**
	 * Reset simplifications.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function trp_reset_simplifications(): void {
		easy_language_trp_reset_simplifications();

		// return ok-message.
		\WP_CLI::success( 'All TranslatePress-simplifications has been reset.' );
	}
}
