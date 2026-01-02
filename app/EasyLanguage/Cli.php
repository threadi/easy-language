<?php
/**
 * File for handling of operations via WP CLI.
 *
 * @package easy-language
 */

namespace easyLanguage\EasyLanguage;

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
	public function reset_simplifications(): void {
		// reset the simplifications.
		Db::get_instance()->reset_simplifications();

		// return ok-message.
		\WP_CLI::success( 'All simplifications has been reset.' );
	}

	/**
	 * Process open simplifications via active API.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function process_simplifications(): void {
		// run the simplifications as if they were scheduled.
		Init::get_instance()->run_automatic_simplification();

		// return success message.
		\WP_CLI::success( 'Simplifications has been saved.' );
	}
}
