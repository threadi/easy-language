<?php
/**
 * File for handling of operations via WP CLI.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

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
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function reset_simplifications(): void {
		// get db-object.
		$db_obj = Db::get_instance();

		// reset the simplifications.
		$db_obj->reset_simplifications();

		// return ok-message.
		\WP_CLI::success( 'All simplifications has been reset.' );
	}

	/**
	 * Process open simplifications via active API.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function process_simplifications(): void {
		// run the simplifications as if they were scheduled.
		Init::get_instance()->run_automatic_simplification();

		// return success message.
		\WP_CLI::success( 'Simplifications has been saved.' );
	}
}
