<?php
/**
 * File for handling of operations via WP CLI.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\TranslatePress;

use easyLanguage\Apis;
use easyLanguage\Helper;

/**
 * Handler for CLI-operations.
 */
class Cli
{
    /**
     * Reset translations.
     *
     * @return void
     * @noinspection PhpUnused
     */
    public function trp_reset_translations(): void
    {
        easy_language_trp_reset_translations();

        // return ok-message.
        \WP_CLI::success("All TranslatePress-translations has been reset.");
    }
}
