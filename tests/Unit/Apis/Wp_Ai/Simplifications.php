<?php
/**
 * Tests for the simplifications via WordPress connectors.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Apis\Wp_Ai;

use easyLanguage\Apis\Wp_Ai\Wp_Ai as Wp_Ai_Api;
use easyLanguage\Tests\easyLanguageTests;
use easyLanguage\Tests\Fakes\Ai_Model;
use easyLanguage\Tests\Fakes\Ai_Provider;

/**
 * Object to test the simplifications via WordPress connectors.
 */
class Simplifications extends easyLanguageTests {
	/**
	 * The simplifications object.
	 *
	 * @var \easyLanguage\Apis\Wp_Ai\Simplifications
	 */
	private \easyLanguage\Apis\Wp_Ai\Simplifications $simplifications;

	/**
	 * Prepare the test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// bail if the AI client of WordPress is not available (WordPress < 7.0).
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$this->markTestSkipped( 'The WordPress connectors require WordPress 7.0 or newer.' );
		}

		// use the fake AI provider as the only connected connector.
		Ai_Provider::register();
		Ai_Provider::$configured = true;
		Ai_Model::reset();
		delete_transient( Wp_Ai_Api::TRANSIENT_PROVIDERS );

		$simplifications = Wp_Ai_Api::get_instance()->get_simplifications_obj();
		$this->assertInstanceOf( \easyLanguage\Apis\Wp_Ai\Simplifications::class, $simplifications );
		$this->simplifications = $simplifications;
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		Ai_Provider::$configured = true;
		Ai_Model::reset();
		delete_transient( Wp_Ai_Api::TRANSIENT_PROVIDERS );
		parent::tear_down();
	}

	/**
	 * Test a successful simplification.
	 *
	 * @return void
	 */
	public function test_call_api(): void {
		Ai_Model::$response = 'Das ist ein einfacher Text.';

		$result = $this->simplifications->call_api( 'Das ist ein komplizierter Text.', 'de_DE', 'de_LS', false );

		$this->assertEquals( 'Das ist ein einfacher Text.', $result['translated_text'] );

		// the request contains the text and the instruction for Leichte Sprache.
		$this->assertCount( 1, Ai_Model::$requests );
		$this->assertEquals( 'Das ist ein komplizierter Text.', Ai_Model::$requests[0]['text'] );
		$this->assertStringContainsString( 'Leichter Sprache', Ai_Model::$requests[0]['system'] );
	}

	/**
	 * Test that HTML of the answer is filtered.
	 *
	 * @return void
	 */
	public function test_html_is_sanitized(): void {
		// HTML texts: only HTML allowed in posts.
		Ai_Model::$response = '<p>Ein <strong>einfacher</strong> Text.</p><script>alert(1)</script>';
		$result             = $this->simplifications->call_api( '<p>Ein Text.</p>', 'de_DE', 'de_LS', true );
		$this->assertStringContainsString( '<strong>einfacher</strong>', $result['translated_text'] );
		$this->assertStringNotContainsString( '<script', $result['translated_text'] );

		// plain texts: no HTML at all.
		Ai_Model::$response = 'Ein <strong>Titel</strong>';
		$result             = $this->simplifications->call_api( 'Ein Titel', 'de_DE', 'de_LS', false );
		$this->assertEquals( 'Ein Titel', $result['translated_text'] );
	}

	/**
	 * Test that Markdown code fences around the answer are removed.
	 *
	 * @return void
	 */
	public function test_code_fences_are_removed(): void {
		$this->assertEquals( '<p>Text</p>', $this->simplifications->clean_response( "```html\n<p>Text</p>\n```" ) );
		$this->assertEquals( 'Text', $this->simplifications->clean_response( "  Text \n" ) );
	}

	/**
	 * Test that errors of the AI are logged and no simplification is returned.
	 *
	 * @return void
	 */
	public function test_error_is_logged(): void {
		global $wpdb;

		Ai_Model::$error = 'Simulated error of the AI';

		$result = $this->simplifications->call_api( 'Ein Text.', 'de_DE', 'de_LS', false );
		$this->assertEmpty( $result );

		// the error is in the API log.
		$response = $wpdb->get_var( $wpdb->prepare( 'SELECT `response` FROM %i WHERE `api` = %s ORDER BY `id` DESC LIMIT 1', \easyLanguage\Plugin\Log_Api::get_instance()->get_table_name(), 'wp_ai' ) );
		$this->assertStringContainsString( 'Simulated error of the AI', (string) $response );
	}

	/**
	 * Test that nothing is simplified without a connected connector.
	 *
	 * @return void
	 */
	public function test_without_connector(): void {
		Ai_Provider::$configured = false;

		$result = $this->simplifications->call_api( 'Ein Text.', 'de_DE', 'de_LS', false );
		$this->assertEmpty( $result );
		$this->assertEmpty( Ai_Model::$requests );
	}
}
