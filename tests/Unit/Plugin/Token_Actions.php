<?php
/**
 * Tests for the buttons to manage the tokens of the APIs (test and remove) via AJAX.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Plugin;

use easyLanguage\Plugin\Helper;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the token actions.
 */
class Token_Actions extends easyLanguageTests {
	/**
	 * The button contains the data for the AJAX-request and the URL as fallback without JavaScript.
	 *
	 * @return void
	 */
	public function test_button_contains_ajax_data_and_fallback(): void {
		$html = Helper::get_token_action_button( 'Test token', 'easy_language_summ_ai_test_token', 'easy-language-summ-ai-test-token' );

		$this->assertStringContainsString( 'easy-language-token-action', $html );
		$this->assertStringContainsString( 'data-action="easy_language_summ_ai_test_token"', $html );
		$this->assertStringContainsString( 'data-nonce="' . wp_create_nonce( 'easy-language-summ-ai-test-token' ) . '"', $html );
		$this->assertStringContainsString( 'admin.php?action=easy_language_summ_ai_test_token', $html );
		$this->assertStringNotContainsString( 'data-confirm-dialog', $html );
	}

	/**
	 * The data attributes survive wp_kses_post(), which is used for the descriptions in the DataView and the classic view.
	 *
	 * @return void
	 */
	public function test_button_survives_kses(): void {
		$confirm_dialog = Helper::get_remove_token_confirm_dialog();
		$html           = Helper::get_token_action_button( 'Remove token', 'easy_language_capito_remove_token', 'easy-language-capito-remove-token', $confirm_dialog );

		// kses normalizes entities in the URL (&#038; becomes &amp;), so we check the attributes and not the complete string.
		$kses = wp_kses_post( $html );
		$this->assertStringContainsString( 'easy-language-token-action', $kses );
		$this->assertStringContainsString( 'data-action="easy_language_capito_remove_token"', $kses );
		$this->assertStringContainsString( 'data-nonce="' . wp_create_nonce( 'easy-language-capito-remove-token' ) . '"', $kses );
		$this->assertStringContainsString( 'data-confirm-dialog="' . esc_attr( Helper::get_json( $confirm_dialog ) ) . '"', $kses );
	}

	/**
	 * The result dialog has the format for the event "easy-dialog-for-wordpress".
	 *
	 * @return void
	 */
	public function test_result_dialog(): void {
		$dialog = Helper::get_token_action_result_dialog( 'Test token', 'ok', true );
		$this->assertArrayHasKey( 'detail', $dialog );
		$this->assertSame( 'closeDialog();', $dialog['detail']['buttons'][0]['action'] );
		$this->assertArrayNotHasKey( 'className', $dialog['detail'] );

		$dialog = Helper::get_token_action_result_dialog( 'Remove token', 'error', false, true );
		$this->assertSame( 'location.reload();', $dialog['detail']['buttons'][0]['action'] );
		$this->assertSame( 'wp-dialog-error', $dialog['detail']['className'] );
	}

	/**
	 * The AJAX-actions are registered for each API with a token.
	 *
	 * @return void
	 */
	public function test_ajax_actions_are_registered(): void {
		$this->assertNotFalse( has_action( 'wp_ajax_easy_language_summ_ai_test_token', array( \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance(), 'run_token_test_via_ajax' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_easy_language_summ_ai_remove_token', array( \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance(), 'remove_token_via_ajax' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_easy_language_capito_test_token', array( \easyLanguage\Apis\Capito\Capito::get_instance(), 'run_token_test_via_ajax' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_easy_language_capito_remove_token', array( \easyLanguage\Apis\Capito\Capito::get_instance(), 'remove_token_via_ajax' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_easy_language_chatgpt_remove_token', array( \easyLanguage\Apis\ChatGpt\ChatGpt::get_instance(), 'remove_token_via_ajax' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_easy_language_summ_ai_get_quota', array( \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance(), 'get_quota_from_api_via_ajax' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_easy_language_capito_get_quota', array( \easyLanguage\Apis\Capito\Capito::get_instance(), 'get_quota_from_api_via_ajax' ) ) );
	}

	/**
	 * The button could use own classes and an own title for the dialog (used for the update of the quota).
	 *
	 * @return void
	 */
	public function test_button_with_own_classes_and_dialog_title(): void {
		$html = wp_kses_post( Helper::get_token_action_button( 'Update now', 'easy_language_capito_get_quota', 'easy-language-capito-get-quota', array(), 'button button-secondary', 'Update quota' ) );

		$this->assertStringContainsString( 'class="button button-secondary easy-language-token-action"', $html );
		$this->assertStringContainsString( 'data-dialog-title="Update quota"', $html );
		$this->assertStringNotContainsString( 'button-primary', $html );
	}
}
