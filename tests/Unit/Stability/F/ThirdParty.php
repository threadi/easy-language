<?php
/**
 * Regression tests for stability fixes in the third party support.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\F;

use easyLanguage\EasyLanguage\Db;
use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;
use WP_HTTP_Requests_Response;

/**
 * Object to test the stability fixes in the third party support.
 */
class ThirdParty extends easyLanguageTests {
	/**
	 * The bodies of the requests sent to the APIs.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $requests = array();

	/**
	 * Load the fakes.
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		require_once dirname( __DIR__, 3 ) . '/Fakes/Stability/F/TranslatePress.php';
	}

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->requests = array();
		add_filter( 'pre_http_request', array( $this, 'filter_requests' ), 10, 3 );
	}

	/**
	 * Simulate the responses of SUMM AI and capito.
	 *
	 * @param false|array<string,mixed> $response The response.
	 * @param array<string,mixed>       $parsed_args The arguments.
	 * @param string                    $url The URL.
	 *
	 * @return false|array<string,mixed>
	 */
	public function filter_requests( false|array $response, array $parsed_args, string $url ): false|array {
		$file = '';
		if ( str_starts_with( $url, \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance()->get_api_url() ) ) {
			$file = 'summ_ai.json';
		} elseif ( str_starts_with( $url, \easyLanguage\Apis\Capito\Capito::get_instance()->get_api_url() ) ) {
			$file = 'capito.json';
		}

		// bail if this is not one of our APIs.
		if ( '' === $file ) {
			return $response;
		}

		// save the request.
		$this->requests[] = is_string( $parsed_args['body'] ?? null ) ? (array) json_decode( $parsed_args['body'], true ) : array();

		$requests_response              = new \WpOrg\Requests\Response();
		$requests_response->status_code = 200;
		return array(
			'http_response' => new WP_HTTP_Requests_Response( $requests_response, $parsed_args['filename'] ?? '' ),
			'body'          => (string) file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . $file ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			'response'      => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'headers'       => array(),
		);
	}

	/**
	 * Set the active API without running the API change routines.
	 *
	 * @param string $api The name of the API.
	 *
	 * @return void
	 */
	private function set_api( string $api ): void {
		add_filter(
			'pre_option_easy_language_api',
			static function () use ( $api ): string {
				return $api;
			}
		);
	}

	/**
	 * The SUMM AI machine translator for TranslatePress uses the simplified text of the API and the right target language.
	 *
	 * @return void
	 */
	public function test_translatepress_summ_ai(): void {
		$this->set_api( 'summ_ai' );

		$translator = new \easyLanguage\ThirdPartySupport\TranslatePress\TranslatePress_Summ_Ai_Machine_Translator();
		$result     = $translator->translate_array( array( 5 => 'Ein Text.' ), 'de_EL', 'de_DE' );

		$this->assertSame( array( 5 => 'Das ist ein vereinfachter Beispiel Text.' ), $result );
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'plain', $this->requests[0]['output_language_level'] ?? '' );
		$this->assertSame( array( 'Ein Text.' ), $translator->machine_translator_logger->counted );
	}

	/**
	 * The capito machine translator for TranslatePress uses the content of the API and the capito language codes.
	 *
	 * @return void
	 */
	public function test_translatepress_capito(): void {
		$this->set_api( 'capito' );

		// requests without token are not sent, so set an example token.
		add_filter(
			'pre_option_easy_language_capito_api_key',
			static function (): string {
				return 'example-token';
			}
		);

		$translator = new \easyLanguage\ThirdPartySupport\TranslatePress\TranslatePress_Capito_Machine_Translator();
		$result     = $translator->translate_array( array( 'Ein Text.' ), 'de_b1', 'de_DE' );

		$this->assertSame( array( 'Das ist ein vereinfachter Beispiel Text.' ), $result );
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'de', $this->requests[0]['locale'] ?? '' );
		$this->assertSame( 'b1', $this->requests[0]['proficiency'] ?? '' );
	}

	/**
	 * The check for supported languages detects the engine "summ_ai".
	 *
	 * @return void
	 */
	public function test_translatepress_supported_languages_for_summ_ai(): void {
		$this->set_api( 'summ_ai' );

		$languages = array_keys( \easyLanguage\Plugin\Languages::get_instance()->get_possible_target_languages() );
		$this->assertNotEmpty( $languages );

		$settings = array( 'trp_machine_translation_settings' => array( 'translation-engine' => 'summ_ai' ) );
		$this->assertTrue( \easyLanguage\ThirdPartySupport\TranslatePress\Init::get_instance()->get_supported_languages_for_trp( false, array( 'de_DE', $languages[0] ), $settings ) );
	}

	/**
	 * WPML returns no languages if it is not loaded.
	 *
	 * @return void
	 */
	public function test_wpml_without_sitepress(): void {
		global $sitepress;
		$backup    = $sitepress;
		$sitepress = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertSame( array(), \easyLanguage\ThirdPartySupport\Wpml\Init::get_instance()->get_active_languages() );

		$sitepress = $backup; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * ACF fields are saved unformatted for the simplification.
	 *
	 * @return void
	 */
	public function test_acf_fields_are_unformatted(): void {
		require_once dirname( __DIR__, 3 ) . '/Fakes/Stability/F/Acf.php';

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'beschreibung', "Erste Zeile\n\nZweite Zeile [shortcode]" );

		\easyLanguage\ThirdPartySupport\Acf::get_instance()->add_meta_fields_to_simplification( new Post_Object( $post_id ), $post_id, 'de_DE' );

		$this->assertNotFalse( Db::get_instance()->get_entry_by_text( "Erste Zeile\n\nZweite Zeile [shortcode]", 'de_DE' ) );
	}
}
