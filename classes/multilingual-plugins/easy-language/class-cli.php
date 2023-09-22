<?php
/**
 * File for handling of operations via WP CLI.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

use easyLanguage\Apis;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handler for CLI-operations.
 */
class Cli {
    /**
     * Reset translations.
     *
     * @return void
     * @noinspection PhpUnused
     */
    public function reset_translations(): void {
        // get db-object.
        $db_obj = Db::get_instance();

        // reset the translations.
        $db_obj->reset_translations();

        // return ok-message.
        \WP_CLI::success("All translations has been reset.");
    }

    /**
     * Process open translations via active API.
     *
     * @return void
     * @noinspection PhpUnused
     * @noinspection PhpUndefinedClassInspection
     */
    public function process_translations(): void {
        // get active API.
        $api_obj = Apis::get_instance()->get_active_api();
        if( false !== $api_obj ) {
            $c = $api_obj->get_translations_obj()->run();

            // return message.
            \WP_CLI::success($c." translations has been saved.");
            exit;
        }

        // return message.
        \WP_CLI::error(" No API activated to automatic translations.");
    }
}
