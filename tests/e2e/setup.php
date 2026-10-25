<?php
/**
 * Prepare WordPress Playground for the E2E tests. Is run by tests/e2e/blueprint.json after the activation of the plugin.
 *
 * - uses ChatGPT as API (answered by the mock in tests/e2e/mu-plugins, no account at OpenAI is needed).
 * - marks the setup and the intro of the plugin as completed.
 * - creates the users and pages the tests are using.
 *
 * Throws an exception on any error, so Playground stops and the tests do not run against a broken installation.
 *
 * @package easy-language
 */

// load WordPress if this file is called directly by Playground.
if ( ! defined( 'ABSPATH' ) ) {
	require_once '/wordpress/wp-load.php';
}

// the API key for the mock (see tests/e2e/mu-plugins/easy-language-e2e-api-mock.php).
const EASY_LANGUAGE_E2E_API_KEY = 'test_api_key';

/**
 * Stop the setup with the given message.
 *
 * @param string $message The message.
 *
 * @return never
 * @throws Exception Always.
 */
function easy_language_e2e_fail( string $message ): never {
	throw new Exception( 'E2E setup: ' . $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
}

/**
 * Create a published page with the given content.
 *
 * @param string $title   The title.
 * @param string $slug    The slug.
 * @param string $content The content (block markup).
 *
 * @return int
 */
function easy_language_e2e_create_page( string $title, string $slug, string $content ): int {
	$page_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
		),
		true
	);
	if ( is_wp_error( $page_id ) ) {
		easy_language_e2e_fail( 'page "' . $title . '" could not be created: ' . $page_id->get_error_message() );
	}
	return $page_id;
}

// the setup runs as administrator.
wp_set_current_user( 1 );

// the plugin needs a supported source language: WordPress must use German. The blueprint sets it directly in
// the database, because WordPress would only accept it with installed language files. So the backend stays in
// English and the tests do not depend on translations.
if ( 'de_DE' !== get_locale() ) {
	easy_language_e2e_fail( 'WordPress does not use de_DE, but ' . get_locale() );
}

/**
 * Use ChatGPT as API.
 */
update_option( 'easy_language_api', 'chatgpt' );
$easy_language_e2e_api = \easyLanguage\Plugin\Apis::get_instance()->get_active_api();
if ( ! $easy_language_e2e_api instanceof \easyLanguage\Apis\ChatGpt\ChatGpt ) {
	easy_language_e2e_fail( 'ChatGPT could not be activated as API.' );
}

// save the API key (it is encrypted by the plugin) and check that it could be read again.
update_option( 'easy_language_chatgpt_api_key', EASY_LANGUAGE_E2E_API_KEY );
if ( EASY_LANGUAGE_E2E_API_KEY !== $easy_language_e2e_api->get_token() ) {
	easy_language_e2e_fail( 'the API key could not be saved encrypted.' );
}
global $wpdb;
if ( str_contains( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT `option_value` FROM %i WHERE `option_name` = %s', $wpdb->options, 'easy_language_chatgpt_api_key' ) ), EASY_LANGUAGE_E2E_API_KEY ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the raw value must be checked, without the decryption filter.
	easy_language_e2e_fail( 'the API key is saved unencrypted.' );
}

// use a known prompt, so the mock can separate it from the text.
foreach ( array_keys( $easy_language_e2e_api->get_supported_target_languages() ) as $easy_language_e2e_language ) {
	update_option( 'easy_language_chatgpt_target_languages_prompts_' . $easy_language_e2e_language, 'E2E-PROMPT: ' );
}

// activate Leichte Sprache and Einfache Sprache, so the switcher has more than one language to offer.
update_option(
	'easy_language_chatgpt_target_languages',
	array(
		'de_LS' => '1',
		'de_EL' => '1',
	)
);
$easy_language_e2e_languages = \easyLanguage\Plugin\Languages::get_instance()->get_active_languages();
foreach ( array( 'de_LS', 'de_EL' ) as $easy_language_e2e_language ) {
	if ( empty( $easy_language_e2e_languages[ $easy_language_e2e_language ] ) ) {
		easy_language_e2e_fail( 'the language ' . $easy_language_e2e_language . ' is not active, active are: ' . implode( ', ', array_keys( $easy_language_e2e_languages ) ) );
	}
}

/**
 * Complete the setup and the intro of the plugin, the tests should start with a configured plugin.
 */
\easySetupForWordPress\Setup::get_instance()->set_completed( \easyLanguage\Plugin\Setup::get_instance()->get_setup_name(), true );
if ( ! \easyLanguage\Plugin\Setup::get_instance()->is_completed() ) {
	easy_language_e2e_fail( 'the setup could not be marked as completed.' );
}
update_option( 'easy_language_intro_step_2', 2 );

/**
 * Create the users.
 */
foreach ( array( 'subscriber', 'editor' ) as $easy_language_e2e_role ) {
	$easy_language_e2e_user = wp_insert_user(
		array(
			'user_login' => $easy_language_e2e_role,
			'user_pass'  => $easy_language_e2e_role,
			'user_email' => $easy_language_e2e_role . '@example.com',
			'role'       => $easy_language_e2e_role,
		)
	);
	if ( is_wp_error( $easy_language_e2e_user ) ) {
		easy_language_e2e_fail( 'user ' . $easy_language_e2e_role . ' could not be created: ' . $easy_language_e2e_user->get_error_message() );
	}
}

/**
 * Create the pages.
 */
// the switcher as block and as shortcode: both are no flow texts, so they are copied into the simplifications unchanged.
$easy_language_e2e_switcher_block     = '<!-- wp:easy-language/switcher /-->';
$easy_language_e2e_switcher_shortcode = '<!-- wp:shortcode -->[easy-language-switcher]<!-- /wp:shortcode -->';

// a page which is simplified by the tests in the backend via the API.
easy_language_e2e_create_page(
	'E2E Via API',
	'e2e-via-api',
	'<!-- wp:paragraph --><p>Dieser Text wird über die Schnittstelle vereinfacht.</p><!-- /wp:paragraph -->'
);

// a page which gets a simplification by the tests in the backend, which is written by hand.
easy_language_e2e_create_page(
	'E2E Manually',
	'e2e-manually',
	'<!-- wp:paragraph --><p>Dieser Text wird von Hand vereinfacht.</p><!-- /wp:paragraph -->'
);

// a page which is already simplified in Leichte Sprache, for the tests in the frontend.
$easy_language_e2e_page_id = easy_language_e2e_create_page(
	'E2E Original',
	'e2e-original',
	'<!-- wp:heading --><h2 class="wp-block-heading">Eine Überschrift</h2><!-- /wp:heading -->' .
	'<!-- wp:paragraph --><p>Das ist ein komplizierter Text mit vielen Fremdwörtern.</p><!-- /wp:paragraph -->' .
	$easy_language_e2e_switcher_block
);

// a page without simplification, but with the switcher.
easy_language_e2e_create_page(
	'E2E Not Simplified',
	'e2e-not-simplified',
	'<!-- wp:paragraph --><p>Diese Seite gibt es nur in der Originalsprache.</p><!-- /wp:paragraph -->' . $easy_language_e2e_switcher_shortcode
);

// add the simplification in Leichte Sprache and simplify it via the API (the mock).
$easy_language_e2e_original = \easyLanguage\Plugin\Helper::get_object( $easy_language_e2e_page_id, 'page' );
if ( ! $easy_language_e2e_original instanceof \easyLanguage\EasyLanguage\Post_Object ) {
	easy_language_e2e_fail( 'the page "E2E Original" could not be loaded by the plugin.' );
}
$easy_language_e2e_copy = $easy_language_e2e_original->add_simplification_object( 'de_LS', $easy_language_e2e_api, true );
if ( ! $easy_language_e2e_copy instanceof \easyLanguage\EasyLanguage\Post_Object ) {
	easy_language_e2e_fail( 'the simplification of "E2E Original" could not be added.' );
}
$easy_language_e2e_copy->process_simplifications( $easy_language_e2e_api->get_simplifications_obj(), $easy_language_e2e_api->get_active_language_mapping(), 10 );
if ( ! str_contains( (string) get_post_field( 'post_content', $easy_language_e2e_copy->get_id() ), 'Leicht gesagt: ' ) ) {
	easy_language_e2e_fail( 'the simplification of "E2E Original" contains no simplified text: ' . get_post_field( 'post_content', $easy_language_e2e_copy->get_id() ) );
}

// use the new rewrite rules for the languages.
flush_rewrite_rules();
