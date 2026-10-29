<?php
/**
 * Minimal fake of the base class for machine translators of TranslatePress.
 *
 * @package easy-language
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

if ( ! class_exists( 'TRP_Machine_Translator_Logger_Fake' ) ) {
	/**
	 * Fake of the logger of TranslatePress.
	 */
	class TRP_Machine_Translator_Logger_Fake {
		/**
		 * Counted strings.
		 *
		 * @var array<int,string>
		 */
		public array $counted = array();

		/**
		 * Log a request.
		 *
		 * @param array<string,mixed> $args The arguments.
		 *
		 * @return void
		 */
		public function log( array $args ): void {}

		/**
		 * Count a string towards the quota.
		 *
		 * @param string $text The string.
		 *
		 * @return void
		 */
		public function count_towards_quota( string $text ): void {
			$this->counted[] = $text;
		}

		/**
		 * Return whether the quota is exceeded.
		 *
		 * @return bool
		 */
		public function quota_exceeded(): bool {
			return false;
		}
	}
}

if ( ! class_exists( 'TRP_Machine_Translator' ) ) {
	/**
	 * Fake of the machine translator of TranslatePress.
	 */
	class TRP_Machine_Translator {
		/**
		 * The settings.
		 *
		 * @var array<string,mixed>
		 */
		public array $settings = array( 'default-language' => 'de_DE' );

		/**
		 * The ISO codes of the languages like TranslatePress maps them.
		 *
		 * @var array<string,string>
		 */
		public array $machine_translation_codes = array(
			'de_DE' => 'de',
			'de_EL' => 'de',
			'de_LS' => 'de',
			'de_b1' => 'de',
		);

		/**
		 * The logger.
		 *
		 * @var TRP_Machine_Translator_Logger_Fake
		 */
		public TRP_Machine_Translator_Logger_Fake $machine_translator_logger;

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->machine_translator_logger = new TRP_Machine_Translator_Logger_Fake();
		}

		/**
		 * Verify the parameters.
		 *
		 * @param string $target_language_code The target language.
		 * @param string $source_language_code The source language.
		 *
		 * @return bool
		 */
		public function verify_request_parameters( string $target_language_code, string $source_language_code ): bool {
			return true;
		}
	}
}
