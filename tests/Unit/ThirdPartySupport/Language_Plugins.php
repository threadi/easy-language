<?php
/**
 * Tests for the support of multilingual plugins and field plugins (without these plugins).
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\ThirdPartySupport;

use easyLanguage\EasyLanguage\Db;
use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Plugin\Languages;
use easyLanguage\Tests\easyLanguageTests;
use WP_HTTP_Requests_Response;

/**
 * Object to test the support of Polylang, Sublanguage, WPML, SCF and the machine translators of TranslatePress.
 */
class Language_Plugins extends easyLanguageTests {
	/**
	 * The body of simulated responses of the APIs.
	 *
	 * @var string|null
	 */
	private ?string $body = null;

	/**
	 * Load the fakes.
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		require_once dirname( __DIR__, 2 ) . '/Fakes/Stability/F/TranslatePress.php';
		require_once dirname( __DIR__, 2 ) . '/Fakes/Stability/F/Acf.php';
	}

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->body = null;

		// use SUMM AI with the language "Leichte Sprache" (as in the tests of the switcher).
		$this->set_api( 'summ_ai' );
		update_option( 'easy_language_summ_ai_target_languages_de_LS', 1 );
		add_filter( 'pre_http_request', array( $this, 'filter_requests' ), 10, 3 );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		global $sitepress;
		$sitepress = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		remove_filter( 'pre_http_request', array( $this, 'filter_requests' ) );
		remove_all_filters( 'pre_option_easy_language_api' );
		remove_all_filters( 'easy_language_scf_types' );
		unregister_post_type( 'language' );
		parent::tear_down();
	}

	/**
	 * Simulate the responses of the APIs (or an error without body).
	 *
	 * @param mixed               $response The response.
	 * @param array<string,mixed> $parsed_args The arguments.
	 * @param string              $url The URL.
	 *
	 * @return mixed
	 */
	public function filter_requests( mixed $response, array $parsed_args, string $url ): mixed {
		if ( is_null( $this->body ) ) {
			return new \WP_Error( 'blocked', 'Requests are blocked in this test.' );
		}
		$requests_response              = new \WpOrg\Requests\Response();
		$requests_response->status_code = 200;
		return array(
			'http_response' => new WP_HTTP_Requests_Response( $requests_response, $parsed_args['filename'] ?? '' ),
			'body'          => $this->body,
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
	 * Polylang gets our languages as predefined languages and their flags.
	 *
	 * @return void
	 */
	public function test_polylang_languages_and_flags(): void {
		$polylang  = \easyLanguage\ThirdPartySupport\Polylang\Init::get_instance();
		$languages = Languages::get_instance()->get_active_languages();
		$this->assertNotEmpty( $languages );

		// predefined languages.
		$predefined = $polylang->add_predefined_language( array( 'en_US' => array( 'code' => 'en' ) ) );
		$this->assertArrayHasKey( 'en_US', $predefined );
		foreach ( $languages as $code => $language ) {
			$this->assertSame( $language['url'], $predefined[ $code ]['code'] );
			$this->assertSame( $code, $predefined[ $code ]['locale'] );
		}

		// flags of all possible languages.
		$flags = $polylang->add_flag( array() );
		foreach ( Languages::get_instance()->get_possible_target_languages() as $code => $language ) {
			$this->assertSame( $language['label'], $flags[ $code ] );
		}

		// the flag of an unknown language is not changed.
		$this->assertSame( array( 'url' => 'flag.png' ), $polylang->get_flag( array( 'url' => 'flag.png' ), 'xx_unknown' ) );

		// the flag of our language uses our icon as URL and as embedded image.
		$flag = $polylang->get_flag( array(), 'de_LS' );
		$this->assertStringEndsWith( 'gfx/de_LS.png', $flag['url'] );
		$this->assertStringStartsWith( 'data:image/png;base64,', $flag['src'] );
	}

	/**
	 * Polylang is not active and returns no languages without the plugin.
	 *
	 * @return void
	 */
	public function test_polylang_without_plugin(): void {
		$polylang = \easyLanguage\ThirdPartySupport\Polylang\Init::get_instance();
		$this->assertFalse( $polylang->is_active() );
		$this->assertSame( array(), $polylang->get_active_languages() );
		$this->assertTrue( $polylang->is_language_plugin() );
	}

	/**
	 * Sublanguage gets our languages as posts of its post type, only once and only by administrators.
	 *
	 * @return void
	 */
	public function test_sublanguage_adds_languages_once(): void {
		register_post_type( 'language' );
		$sublanguage = \easyLanguage\ThirdPartySupport\Sublanguage\Init::get_instance();
		$languages   = Languages::get_instance()->get_active_languages();
		$this->assertNotEmpty( $languages );
		delete_option( 'easy_language_sublanguage_languages_marker' );

		// not for other users.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$sublanguage->wp_init();
		$this->assertSame( array(), $sublanguage->get_active_languages() );

		// for administrators.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$sublanguage->wp_init();
		$active = $sublanguage->get_active_languages();
		foreach ( array_keys( $languages ) as $code ) {
			$this->assertSame( '1', $active[ $code ] ?? '' );
		}
		$this->assertNotEmpty( get_option( 'easy_language_sublanguage_languages_marker' ) );

		// a second run (also without marker) does not add the languages again.
		delete_option( 'easy_language_sublanguage_languages_marker' );
		$sublanguage->wp_init();
		$this->assertCount(
			count( $languages ),
			get_posts(
				array(
					'post_type'   => 'language',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
	}

	/**
	 * WPML returns the codes of its active languages and does not change anything without its classes.
	 *
	 * @return void
	 */
	public function test_wpml(): void {
		global $sitepress, $wpdb;
		$wpml = \easyLanguage\ThirdPartySupport\Wpml\Init::get_instance();

		// the active languages of WPML.
		$sitepress = new class() { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			/**
			 * Return the active languages.
			 *
			 * @return array<int,array<string,string>>
			 */
			public function get_active_languages(): array {
				return array(
					array( 'code' => 'de' ),
					array( 'code' => 'de_LS' ),
				);
			}
		};
		$this->assertSame(
			array(
				'de'    => '1',
				'de_LS' => '1',
			),
			$wpml->get_active_languages()
		);

		// without the classes of WPML: an error is logged, nothing is marked as done.
		delete_option( 'easy_language_wpml_languages_marker' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$wpml->admin_init();
		$this->assertSame( '', (string) get_option( 'easy_language_wpml_languages_marker', '' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->assertGreaterThan( 0, absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `log` LIKE %s', \easyLanguage\Plugin\Log::get_instance()->get_table_name(), '%WPML%' ) ) ) );

		// not for other users.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$wpml->admin_init();
		$this->assertSame( '', (string) get_option( 'easy_language_wpml_languages_marker', '' ) );
	}

	/**
	 * SCF fields, which could be simplified, are added as texts of the object.
	 *
	 * @return void
	 */
	public function test_scf_fields_are_added(): void {
		$post_id = self::factory()->post->create();
		$content = 'Ein Feld von SCF ' . wp_generate_password( 8, false );
		update_post_meta( $post_id, 'beschreibung', $content );

		\easyLanguage\ThirdPartySupport\Scf::get_instance()->add_meta_fields_to_simplification( new Post_Object( $post_id ), $post_id, 'de_DE' );

		$text = Db::get_instance()->get_entry_by_text( $content, 'de_DE' );
		$this->assertNotFalse( $text );
		$this->assertTrue( $text->is_field( 'scf_meta_beschreibung' ) );
		$this->assertSame( 'to_simplify', $text->get_state() );
	}

	/**
	 * SCF fields of types, which are not supported, are ignored.
	 *
	 * @return void
	 */
	public function test_scf_types_could_be_filtered(): void {
		add_filter( 'easy_language_scf_types', '__return_empty_array' );
		$post_id = self::factory()->post->create();
		$content = 'Ein nicht unterstütztes Feld ' . wp_generate_password( 8, false );
		update_post_meta( $post_id, 'beschreibung', $content );

		\easyLanguage\ThirdPartySupport\Scf::get_instance()->add_meta_fields_to_simplification( new Post_Object( $post_id ), $post_id, 'de_DE' );

		$this->assertFalse( Db::get_instance()->get_entry_by_text( $content, 'de_DE' ) );
	}

	/**
	 * SCF is not active without the plugin and is no multilingual plugin.
	 *
	 * @return void
	 */
	public function test_scf_without_plugin(): void {
		$scf = \easyLanguage\ThirdPartySupport\Scf::get_instance();
		$this->assertFalse( $scf->is_active() );
		$this->assertFalse( $scf->is_language_plugin() );
		$this->assertSame( array(), $scf->get_active_languages() );
	}

	/**
	 * The machine translators of TranslatePress work only with their own API.
	 *
	 * @return void
	 */
	public function test_translatepress_needs_own_api(): void {
		$this->set_api( 'capito' );
		$translator = new \easyLanguage\ThirdPartySupport\TranslatePress\TranslatePress_Summ_Ai_Machine_Translator();
		$this->assertSame( array(), $translator->translate_array( array( 'Ein Text.' ), 'de_LS', 'de_DE' ) );
		$this->assertInstanceOf( \WP_Error::class, $translator->send_request( 'de_DE', 'de_LS', 'Ein Text.' ) );

		// empty lists are not sent.
		$this->set_api( 'summ_ai' );
		$this->assertSame( array(), $translator->translate_array( array(), 'de_LS', 'de_DE' ) );
	}

	/**
	 * Strings, which could not be simplified, keep their original text and their key.
	 *
	 * @return void
	 */
	public function test_translatepress_keeps_original_on_error(): void {
		$this->set_api( 'summ_ai' );
		$this->body = null;

		$translator = new \easyLanguage\ThirdPartySupport\TranslatePress\TranslatePress_Summ_Ai_Machine_Translator();
		$result     = $translator->translate_array(
			array(
				7  => 'Erster Text.',
				12 => 'Zweiter Text.',
			),
			'de_LS',
			'de_DE'
		);

		$this->assertSame(
			array(
				7  => 'Erster Text.',
				12 => 'Zweiter Text.',
			),
			$result
		);
		$this->assertSame( array(), $translator->machine_translator_logger->counted );
	}

	/**
	 * The simplified strings contain no scripts and keep their line breaks.
	 *
	 * @return void
	 */
	public function test_translatepress_filters_html(): void {
		$this->set_api( 'summ_ai' );
		$this->body = (string) wp_json_encode(
			array(
				'translated_text' => "Erste Zeile.\nZweite Zeile.<script>alert(1)</script>",
				'jobid'           => 1,
			)
		);

		$translator = new \easyLanguage\ThirdPartySupport\TranslatePress\TranslatePress_Summ_Ai_Machine_Translator();
		$result     = $translator->translate_array( array( 'Ein Text.' ), 'de_LS', 'de_DE' );

		$this->assertStringNotContainsString( '<script', $result[0] );
		$this->assertStringContainsString( 'Erste Zeile.', $result[0] );
		$this->assertStringContainsString( 'Zweite Zeile.', $result[0] );
	}

	/**
	 * The language of TranslatePress is found by the code or the URL part of our languages (case-insensitive).
	 *
	 * @return void
	 */
	public function test_translatepress_language_by_url_part(): void {
		$this->set_api( 'summ_ai' );
		$this->body = (string) wp_json_encode(
			array(
				'translated_text' => 'Ein leichter Text.',
				'jobid'           => 1,
			)
		);

		// get the URL part of "de_LS".
		$languages = \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance()->get_supported_target_languages();
		$this->assertNotEmpty( $languages['de_LS']['url'] );

		$sent = array();
		$catch = static function ( $response, $parsed_args ) use ( &$sent ) {
			$sent[] = (array) json_decode( (string) ( $parsed_args['body'] ?? '' ), true );
			return $response;
		};
		add_filter( 'pre_http_request', $catch, 5, 2 );

		$translator = new \easyLanguage\ThirdPartySupport\TranslatePress\TranslatePress_Summ_Ai_Machine_Translator();
		$result_url = $translator->translate_array( array( 'Ein Text.' ), strtoupper( $languages['de_LS']['url'] ), 'de_DE' );
		$result_code = $translator->translate_array( array( 'Ein Text.' ), 'de_LS', 'de_DE' );

		remove_filter( 'pre_http_request', $catch, 5 );

		$this->assertSame( array( 'Ein leichter Text.' ), $result_url );
		$this->assertSame( array( 'Ein leichter Text.' ), $result_code );
		$this->assertCount( 2, $sent );
		$this->assertSame( $sent[1]['output_language_level'] ?? '', $sent[0]['output_language_level'] ?? null );
	}
}
