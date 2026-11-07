<?php
/**
 * File for handling of operations via WP CLI.
 *
 * @package easy-language
 */

namespace easyLanguage\ThirdPartySupport\TranslatePress;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Handler for CLI-operations.
 */
class Cli {

	/**
	 * Reset simplifications.
	 *
	 * Empties the tables of TranslatePress for the languages of Easy Language.
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
	public function trp_reset_simplifications( array $attributes = array(), array $options = array() ): void {
		// get confirmation, as this empties the tables of TranslatePress for our languages.
		\WP_CLI::confirm( 'Do you really want to remove all simplifications of Easy Language in TranslatePress?', $options );

		Init::get_instance()->reset_simplifications();

		// return ok-message.
		\WP_CLI::success( 'All TranslatePress-simplifications has been reset.' );
	}
}
