<?php
/**
 * Regression tests for the stability fixes in the plugin classes.
 *
 * @package easy-language
 */

namespace {
	// Polylang functions for the tests of Helper::get_wp_lang(), controlled via globals.
	if ( ! function_exists( 'pll_current_language' ) ) {
		/**
		 * Fake for the function of Polylang.
		 *
		 * @param string $field The requested field.
		 *
		 * @return string|false
		 */
		function pll_current_language( string $field = 'slug' ): string|false { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed
			return $GLOBALS['el_test_pll_current'] ?? false;
		}
	}
	if ( ! function_exists( 'pll_default_language' ) ) {
		/**
		 * Fake for the function of Polylang.
		 *
		 * @param string $field The requested field.
		 *
		 * @return string|false
		 */
		function pll_default_language( string $field = 'slug' ): string|false {
			return $GLOBALS['el_test_pll_default'] ?? false;
		}
	}
}

namespace easyLanguage\Tests\Unit\Stability\D {

	use easyLanguage\Plugin\Crypt;
	use easyLanguage\Plugin\Helper;
	use easyLanguage\Plugin\Intervals\Minutly20;
	use easyLanguage\Plugin\Setup;
	use easyLanguage\Plugin\Uninstall;
	use easyLanguage\Tests\easyLanguageTests;
	use ReflectionMethod;

	/**
	 * Object to test the fixes in the plugin classes.
	 */
	class Plugin_Fixes extends easyLanguageTests {
		/**
		 * Clean up after each test.
		 *
		 * @return void
		 */
		public function tear_down(): void {
			unset( $GLOBALS['el_test_pll_current'], $GLOBALS['el_test_pll_default'] );
			remove_filter( 'pre_option_active_plugins', array( $this, 'get_active_plugins_with_polylang' ) );
			parent::tear_down();
		}

		/**
		 * Return the list of active plugins with Polylang.
		 *
		 * @return array<int,string>
		 */
		public function get_active_plugins_with_polylang(): array {
			return array( 'polylang/polylang.php' );
		}

		/**
		 * The interval "every 20 minutes" has 20 minutes.
		 *
		 * @return void
		 */
		public function test_interval_20_minutes(): void {
			$this->assertSame( 20 * MINUTE_IN_SECONDS, Minutly20::get_instance()->get_time() );
		}

		/**
		 * With Polylang the current language is used, and if none is set (e.g. in backend or cron) the default language.
		 *
		 * @return void
		 */
		public function test_get_wp_lang_with_polylang(): void {
			add_filter( 'pre_option_active_plugins', array( $this, 'get_active_plugins_with_polylang' ) );
			$GLOBALS['el_test_pll_default'] = 'de_DE';

			// no current language (e.g. in backend or cron): use the default language.
			$GLOBALS['el_test_pll_current'] = false;
			$this->assertSame( 'de_DE', Helper::get_wp_lang() );

			// current language (e.g. in frontend).
			$GLOBALS['el_test_pll_current'] = 'fr_FR';
			$this->assertSame( 'fr_FR', Helper::get_wp_lang() );

			// neither: use the locale of WordPress.
			$GLOBALS['el_test_pll_current'] = false;
			$GLOBALS['el_test_pll_default'] = false;
			$this->assertSame( get_locale(), Helper::get_wp_lang() );
		}

		/**
		 * Plugins which are active network-wide are detected.
		 *
		 * @return void
		 */
		public function test_is_plugin_active_network_wide(): void {
			if ( ! is_multisite() ) {
				$this->markTestSkipped( 'Multisite only.' );
			}
			$this->assertFalse( Helper::is_plugin_active( 'el-test/el-test.php' ) );

			update_site_option( 'active_sitewide_plugins', array( 'el-test/el-test.php' => time() ) );
			$this->assertTrue( Helper::is_plugin_active( 'el-test/el-test.php' ) );
		}

		/**
		 * An encrypted value, which could not be decrypted, is not shown as set.
		 *
		 * @return void
		 */
		public function test_masked_value_on_decryption_error(): void {
			global $wpdb;
			$option = 'easy_language_capito_api_key';

			// a valid value is masked.
			update_option( $option, 'my-secret-token' );
			$this->assertSame( Crypt::MASK, Crypt::get_instance()->get_masked_value( $option ) );

			// save a value, which could not be decrypted (e.g. after a change of the key).
			$wpdb->update( $wpdb->options, array( 'option_value' => Crypt::PREFIX . 'not-decryptable' ), array( 'option_name' => $option ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			wp_cache_delete( $option, 'options' );
			wp_cache_delete( 'alloptions', 'options' );

			$this->assertSame( '', Crypt::get_instance()->get_masked_value( $option ) );
		}

		/**
		 * The setup uses the option of Easy Setup for the actual step.
		 *
		 * @return void
		 */
		public function test_setup_steps_use_option_of_easy_setup(): void {
			update_option( 'esfw_step', 0 );

			Setup::get_instance()->update_step( 2 );
			$this->assertSame( 2, absint( get_option( 'esfw_step' ) ) );

			Setup::get_instance()->update_process_step( 1 );
			$this->assertSame( 3, absint( get_option( 'esfw_step' ) ) );

			$this->assertFalse( get_option( 'esfw_steps' ) );
		}

		/**
		 * The options of the abilities are part of the options removed during uninstallation (they are registered only on init:40).
		 *
		 * @return void
		 */
		public function test_uninstall_removes_abilities_options(): void {
			$method = new ReflectionMethod( Uninstall::class, 'get_options' );
			$method->setAccessible( true );
			$options = $method->invoke( Uninstall::get_instance() );

			$this->assertContains( 'easy_language_abilities_enabled', $options );
			$this->assertContains( 'easy_language_abilities_mode', $options );
		}

		/**
		 * The list of sites for (de)activation and uninstallation contains also archived sites.
		 *
		 * @return void
		 */
		public function test_get_blogs_contains_archived_sites(): void {
			if ( ! is_multisite() ) {
				$this->markTestSkipped( 'Multisite only.' );
			}
			$blog_id = self::factory()->blog->create();
			update_blog_status( $blog_id, 'archived', '1' );

			foreach ( array( Uninstall::class => Uninstall::get_instance(), \easyLanguage\Plugin\Installer::class => \easyLanguage\Plugin\Installer::get_instance() ) as $class_name => $obj ) {
				$method = new ReflectionMethod( $class_name, 'get_blogs' );
				$method->setAccessible( true );
				$blogs = $method->invoke( $obj );
				$this->assertContains( $blog_id, $blogs, $class_name );
				$this->assertContains( get_main_site_id(), $blogs, $class_name );
			}
		}

		/**
		 * The activation does not change the chosen API.
		 *
		 * Fails until the fix in Summ_Ai::install() of another package is merged.
		 *
		 * @group needs-merge
		 *
		 * @return void
		 */
		public function test_activation_keeps_chosen_api(): void {
			// no external requests (e.g. for the translations of WordPress).
			$block = static function (): array {
				return array(
					'headers'  => array(),
					'body'     => '{"translations":[]}',
					'response' => array( 'code' => 200, 'message' => 'OK' ),
					'cookies'  => array(),
				);
			};
			add_filter( 'pre_http_request', $block );

			update_option( 'easy_language_api', 'capito' );

			\easyLanguage\Plugin\Installer::get_instance()->activation();

			remove_filter( 'pre_http_request', $block );

			$this->assertSame( 'capito', get_option( 'easy_language_api' ) );
		}
	}
}
