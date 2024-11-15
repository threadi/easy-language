<?php
/**
 * File for handling of operations via WP CLI.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Apis;

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
		// get active API.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( false !== $api_obj ) {
			$c = $api_obj->get_simplifications_obj()->run();

			// return message.
			\WP_CLI::success( $c . ' simplifications has been saved.' );
			exit;
		}

		// return message.
		\WP_CLI::error( 'No API activated to automatic simplifications.' );
	}
}
