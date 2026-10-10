<?php
/**
 * File to handle the encryption of sensitive settings, e.g. API keys.
 *
 * Uses the composer package threadi/crypt-for-wordpress. The key to encrypt
 * is not saved in the database, but e.g. in the wp-config.php.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use CryptForWordPress\Crypt as Crypt_Package;

/**
 * Object to encrypt and decrypt sensitive settings.
 */
class Crypt {
	/**
	 * Prefix of each value encrypted by this object.
	 *
	 * Values without this prefix are not encrypted yet (e.g. saved by an older version of this plugin).
	 */
	public const PREFIX = 'elcrypt:';

	/**
	 * The value, which is shown in forms instead of a saved secret.
	 *
	 * If a form returns this value, the saved secret is not changed.
	 */
	public const MASK = '********';

	/**
	 * Instance of this object.
	 *
	 * @var ?Crypt
	 */
	private static ?Crypt $instance = null;

	/**
	 * The package object.
	 *
	 * @var Crypt_Package|null
	 */
	private ?Crypt_Package $crypt = null;

	/**
	 * Constructor for this object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Crypt {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the encryption for our sensitive options.
	 *
	 * Must run before any of these options are read or saved.
	 *
	 * @return void
	 */
	public function init(): void {
		foreach ( $this->get_options() as $option ) {
			add_filter( 'option_' . $option, array( $this, 'decrypt_option' ), 10, 2 );

			// encrypt as last step, so validation callbacks of the settings get the plain value.
			add_filter( 'pre_update_option_' . $option, array( $this, 'encrypt_option' ), PHP_INT_MAX, 3 );
		}
	}

	/**
	 * Return the list of options, which are saved encrypted.
	 *
	 * The name of the option is also used as context for the encryption.
	 * An encrypted value could not be decrypted in any other option.
	 *
	 * @return array<int,string>
	 */
	public function get_options(): array {
		$options = array(
			'easy_language_capito_api_key',
			'easy_language_chatgpt_api_key',
			'easy_language_summ_ai_api_key',
		);

		/**
		 * Filter the list of options which are saved encrypted.
		 *
		 * @since 3.3.0 Available since 3.3.0.
		 * @param array<int,string> $options List of option names.
		 */
		return apply_filters( 'easy_language_encrypted_options', $options );
	}

	/**
	 * Return the package object.
	 *
	 * @return Crypt_Package
	 */
	private function get_crypt(): Crypt_Package {
		if ( is_null( $this->crypt ) ) {
			$this->crypt = new Crypt_Package( EASY_LANGUAGE );
			$this->crypt->set_slug( 'easy-language' );
		}

		return $this->crypt;
	}

	/**
	 * Return whether the given value is encrypted by this object.
	 *
	 * @param mixed $value The value to check.
	 *
	 * @return bool
	 */
	public function is_encrypted( mixed $value ): bool {
		return is_string( $value ) && str_starts_with( $value, self::PREFIX );
	}

	/**
	 * Encrypt a string.
	 *
	 * Returns an empty string if the encryption failed.
	 *
	 * @param string $plain_text The string to encrypt.
	 * @param string $context    What the value belongs to, e.g. the option name.
	 *
	 * @return string
	 */
	public function encrypt( #[\SensitiveParameter] string $plain_text, string $context ): string {
		// bail if string is empty.
		if ( '' === $plain_text ) {
			return '';
		}

		$crypt     = $this->get_crypt();
		$encrypted = $crypt->encrypt( $plain_text, $context );
		if ( '' === $encrypted ) {
			$this->log_errors();
			return '';
		}

		return self::PREFIX . $encrypted;
	}

	/**
	 * Decrypt a string encrypted by this object.
	 *
	 * Returns not encrypted strings unchanged, so values of older versions of this plugin are still usable until they are migrated.
	 *
	 * @param string $value   The string to decrypt.
	 * @param string $context What the value belongs to, e.g. the option name.
	 *
	 * @return string
	 */
	public function decrypt( string $value, string $context ): string {
		// return not encrypted values unchanged.
		if ( ! $this->is_encrypted( $value ) ) {
			return $value;
		}

		$crypt     = $this->get_crypt();
		$decrypted = $crypt->decrypt( substr( $value, strlen( self::PREFIX ) ), $context );
		if ( '' === $decrypted ) {
			$this->log_errors();
		}

		return $decrypted;
	}

	/**
	 * Decrypt the value of an option, when it is read.
	 *
	 * @param mixed  $value  The saved value.
	 * @param string $option The option name.
	 *
	 * @return mixed
	 */
	public function decrypt_option( mixed $value, string $option ): mixed {
		// bail if value is not a string.
		if ( ! is_string( $value ) ) {
			return $value;
		}

		return $this->decrypt( $value, $option );
	}

	/**
	 * Encrypt the value of an option, before it is updated.
	 *
	 * @param mixed  $value     The new value.
	 * @param mixed  $old_value The actual value (already decrypted).
	 * @param string $option    The option name.
	 *
	 * @return mixed
	 */
	public function encrypt_option( mixed $value, mixed $old_value, string $option ): mixed {
		// keep the saved value as it is, if a form returns the mask.
		// The raw value is used, so an encrypted value is kept even if it could not be decrypted at the moment.
		if ( self::MASK === $value ) {
			return $this->get_raw_value( $option );
		}

		return $this->get_encrypted_value( $value, $option );
	}

	/**
	 * Return the value of an option as it is saved in the database (without decryption).
	 *
	 * @param string $option The option name.
	 *
	 * @return string
	 */
	private function get_raw_value( string $option ): string {
		remove_filter( 'option_' . $option, array( $this, 'decrypt_option' ) );
		$value = get_option( $option, '' );
		add_filter( 'option_' . $option, array( $this, 'decrypt_option' ), 10, 2 );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Return the encrypted value for an option.
	 *
	 * @param mixed  $value  The value.
	 * @param string $option The option name.
	 *
	 * @return mixed
	 */
	private function get_encrypted_value( #[\SensitiveParameter] mixed $value, string $option ): mixed {
		// bail if value is not a string, empty or already encrypted.
		if ( ! is_string( $value ) || '' === $value || $this->is_encrypted( $value ) ) {
			return $value;
		}

		// encrypt the value.
		$encrypted = $this->encrypt( $value, $option );

		// save the value unencrypted if encryption is not possible, so the plugin stays usable.
		// The error is logged and the value will be encrypted with the next update.
		if ( '' === $encrypted ) {
			return $value;
		}

		return $encrypted;
	}

	/**
	 * Return the value to show in a form instead of the saved secret.
	 *
	 * @param string $option The option name.
	 *
	 * @return string
	 */
	public function get_masked_value( string $option ): string {
		return '' === $this->get_raw_value( $option ) ? '' : self::MASK;
	}

	/**
	 * Encrypt all saved values, which are not encrypted yet.
	 *
	 * Used during updates of this plugin.
	 *
	 * @return void
	 */
	public function migrate(): void {
		foreach ( $this->get_options() as $option ) {
			// get the value as saved in the database.
			$value = $this->get_raw_value( $option );

			// bail if value is empty or already encrypted.
			if ( '' === $value || $this->is_encrypted( $value ) ) {
				continue;
			}

			// encrypt it.
			$encrypted = $this->encrypt( $value, $option );

			// bail if encryption failed: the value stays unencrypted and will be migrated during the next update.
			if ( '' === $encrypted ) {
				continue;
			}

			// save the encrypted value without the filters.
			remove_filter( 'pre_update_option_' . $option, array( $this, 'encrypt_option' ), PHP_INT_MAX );
			update_option( $option, $encrypted );
			add_filter( 'pre_update_option_' . $option, array( $this, 'encrypt_option' ), PHP_INT_MAX, 3 );
		}
	}

	/**
	 * Remove the key of this plugin from its place (e.g. wp-config.php).
	 *
	 * Only use this during uninstallation, after all encrypted values have been deleted.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		$this->get_crypt()->uninstall();
	}

	/**
	 * Log the errors of the package.
	 *
	 * @return void
	 */
	private function log_errors(): void {
		$crypt = $this->get_crypt();

		// bail if no errors are set.
		if ( ! $crypt->has_errors() ) {
			return;
		}

		$errors = $crypt->get_errors();
		if ( is_null( $errors ) ) {
			return;
		}

		// log each error (the messages never contain the secret).
		// Each message is logged at most once per hour, as options are read on every request.
		foreach ( $errors->get_error_messages() as $message ) {
			$transient_name = 'easy_language_crypt_error_' . md5( $message );
			if ( false !== get_transient( $transient_name ) ) {
				continue;
			}
			set_transient( $transient_name, 1, HOUR_IN_SECONDS );

			/* translators: %1$s will be replaced by an error message. */
			Log::get_instance()->add_log( sprintf( __( 'Encryption error: %1$s', 'easy-language' ), $message ), 'error' );
		}

		// clear them, so they are not logged twice.
		$crypt->clear_errors();
	}
}
