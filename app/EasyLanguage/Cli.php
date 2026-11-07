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
	 * Hint: on multisite this resets the simplifications of all blogs, as they are saved in tables used by all blogs.
	 *
	 *  [--yes]
	 *  : Answer yes to the confirmation message.
	 *
	 * @param array<int,string>    $attributes The positional arguments (not used).
	 * @param array<string,string> $options List of options.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function reset_simplifications( array $attributes = array(), array $options = array() ): void {
		// get confirmation, as this will delete all simplifications (in a multisite of all sites).
		\WP_CLI::confirm( 'Do you really want to reset all simplifications of Easy Language? In a multisite this affects all sites.', $options );

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
