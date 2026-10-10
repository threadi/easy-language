<?php
/**
 * File for handling of operations via WP CLI.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Handler for CLI-operations.
 */
class Cli {
	/**
	 * Resets all settings of this plugin.
	 *
	 * In a multisite only the actual site (use --url) will be reset.
	 *
	 *  [--not-light]
	 *  : Prevent reset of light plugin.
	 *
	 *  [--yes]
	 *  : Answer yes to the confirmation message.
	 *
	 * @param array<string,string> $attributes Marker to delete all data or not.
	 * @param array<string,string> $options List of options.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function reset_plugin( array $attributes = array(), array $options = array() ): void {
		// get confirmation, as this will delete all settings and simplifications.
		\WP_CLI::confirm( 'Do you really want to reset all settings and data of Easy Language in this site?', $options );

		// uninstall everything from the plugin (only in the actual site).
		Uninstall::get_instance()->run();

		/**
		 * Run additional tasks for uninstallation.
		 *
		 * @since 2.3.0 Available since 2.3.0.
		 *
		 * @param array<string,string> $options Options used to call this command.
		 */
		do_action( 'easy_language_uninstaller', $options );

		// run installer tasks (only in the actual site).
		Installer::get_instance()->activation();

		/**
		 * Run additional tasks for installation.
		 *
		 * @since 2.3.0 Available since 2.3.0.
		 */
		do_action( 'easy_language_installer' );

		// return ok-message.
		\WP_CLI::success( 'Plugin has been reset.' );
	}
}
