<?php
/**
 * Tests for class easyLanguage\Plugin\Crypt.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Plugin;

use easyLanguage\Plugin\Crypt as Crypt_Object;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the encryption of the API keys.
 */
class Crypt extends easyLanguageTests {
	/**
	 * The option used for the tests.
	 */
	private const OPTION = 'easy_language_capito_api_key';

	/**
	 * Return the value of the option as saved in the database (without decryption).
	 *
	 * @return string
	 */
	private function get_raw_value(): string {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT `option_value` FROM %i WHERE `option_name` = %s', $wpdb->options, self::OPTION ) );
	}

	/**
	 * Save the value in the database without encryption (as versions before 4.0.0 did).
	 *
	 * @param string $value The value.
	 *
	 * @return void
	 */
	private function set_raw_value( string $value ): void {
		global $wpdb;
		delete_option( self::OPTION );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => self::OPTION,
				'option_value' => $value,
				'autoload'     => 'off',
			)
		);
		wp_cache_delete( self::OPTION, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	/**
	 * Block requests to the APIs (e.g. from the validation of the API key).
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		add_filter( 'pre_http_request', array( $this, 'block_requests' ) );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'block_requests' ) );
		delete_option( self::OPTION );
		parent::tear_down();
	}

	/**
	 * Block all external requests.
	 *
	 * @return \WP_Error
	 */
	public function block_requests(): \WP_Error {
		return new \WP_Error( 'blocked', 'Requests are blocked in this test.' );
	}

	/**
	 * An API key is saved encrypted and read as plain text.
	 *
	 * @return void
	 */
	public function test_value_is_saved_encrypted(): void {
		update_option( self::OPTION, 'sk-my-secret-key' );

		$raw = $this->get_raw_value();
		$this->assertStringStartsWith( Crypt_Object::PREFIX, $raw );
		$this->assertStringNotContainsString( 'sk-my-secret-key', $raw );

		wp_cache_delete( self::OPTION, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$this->assertSame( 'sk-my-secret-key', get_option( self::OPTION ) );
	}

	/**
	 * The mask of the settings form does not change the saved key.
	 *
	 * @return void
	 */
	public function test_mask_keeps_the_saved_value(): void {
		update_option( self::OPTION, 'sk-my-secret-key' );
		$raw = $this->get_raw_value();

		update_option( self::OPTION, Crypt_Object::MASK );

		$this->assertSame( $raw, $this->get_raw_value() );
		$this->assertSame( 'sk-my-secret-key', get_option( self::OPTION ) );
		$this->assertSame( Crypt_Object::MASK, Crypt_Object::get_instance()->get_masked_value( self::OPTION ) );
	}

	/**
	 * An already encrypted value is not encrypted twice.
	 *
	 * @return void
	 */
	public function test_value_is_not_encrypted_twice(): void {
		update_option( self::OPTION, 'sk-my-secret-key' );
		$raw = $this->get_raw_value();

		$this->assertSame( $raw, Crypt_Object::get_instance()->encrypt_option( $raw, '', self::OPTION ) );
	}

	/**
	 * Empty values and values which are no strings are not encrypted.
	 *
	 * @return void
	 */
	public function test_empty_and_non_string_values(): void {
		$crypt = Crypt_Object::get_instance();
		$this->assertSame( '', $crypt->encrypt_option( '', '', self::OPTION ) );
		$this->assertSame( array( 'a' ), $crypt->encrypt_option( array( 'a' ), '', self::OPTION ) );
		$this->assertSame( false, $crypt->decrypt_option( false, self::OPTION ) );
		$this->assertSame( 'plain', $crypt->decrypt( 'plain', self::OPTION ) );
		$this->assertSame( '', $crypt->encrypt( '', self::OPTION ) );
		$this->assertSame( '', $crypt->get_masked_value( 'easy_language_not_existing_option' ) );
	}

	/**
	 * The migration encrypts unencrypted keys once.
	 *
	 * @return void
	 */
	public function test_migration(): void {
		$this->set_raw_value( 'sk-old-unencrypted-key' );

		Crypt_Object::get_instance()->migrate();
		$encrypted = $this->get_raw_value();
		$this->assertStringStartsWith( Crypt_Object::PREFIX, $encrypted );

		// a second run changes nothing.
		Crypt_Object::get_instance()->migrate();
		$this->assertSame( $encrypted, $this->get_raw_value() );

		wp_cache_delete( self::OPTION, 'options' );
		$this->assertSame( 'sk-old-unencrypted-key', get_option( self::OPTION ) );
	}

	/**
	 * Further options could be encrypted via filter.
	 *
	 * @return void
	 */
	public function test_options_filter(): void {
		$filter = static function ( array $options ): array {
			$options[] = 'my_own_secret_option';
			return $options;
		};
		add_filter( 'easy_language_encrypted_options', $filter );
		$this->assertContains( 'my_own_secret_option', Crypt_Object::get_instance()->get_options() );
		remove_filter( 'easy_language_encrypted_options', $filter );

		// the API keys of all APIs are encrypted.
		foreach ( array( 'easy_language_capito_api_key', 'easy_language_chatgpt_api_key', 'easy_language_summ_ai_api_key' ) as $option ) {
			$this->assertContains( $option, Crypt_Object::get_instance()->get_options() );
		}
	}
}
