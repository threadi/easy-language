<?php
/**
 * Tests for the migrations in class easyLanguage\Plugin\Update.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Plugin;

use easyLanguage\Apis\Summ_Ai\Summ_Ai;
use easyLanguage\Plugin\Update;
use easyLanguage\Tests\easyLanguageTests;
use ReflectionMethod;

/**
 * Object to test the migrations of older settings and data during updates.
 */
class Update_Migrations extends easyLanguageTests {
	/**
	 * Call a private method of the update object.
	 *
	 * @param string            $method The method name.
	 * @param array<int,mixed>  $args   The arguments.
	 *
	 * @return mixed
	 */
	private function call_private( string $method, array $args = array() ): mixed {
		$reflection = new ReflectionMethod( Update::class, $method );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( Update::get_instance(), $args );
	}

	/**
	 * 2.3.0: the setup state is copied to the new option, if not already set.
	 *
	 * @return void
	 */
	public function test_version230_copies_setup_state(): void {
		delete_option( 'esfw_completed' );
		update_option( 'wp_easy_setup_completed', array( 'easy-language-intro' ) );

		Update::get_instance()->version230();
		$this->assertSame( array( 'easy-language-intro' ), get_option( 'esfw_completed' ) );

		// an existing value is not overwritten.
		update_option( 'wp_easy_setup_completed', array( 'other' ) );
		Update::get_instance()->version230();
		$this->assertSame( array( 'easy-language-intro' ), get_option( 'esfw_completed' ) );
	}

	/**
	 * 2.3.0: the old schedules for the quota are removed.
	 *
	 * @return void
	 */
	public function test_version230_removes_old_schedules(): void {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'easy_language_capito_quota_interval' );
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'easy_language_summ_ai_request_quota' );

		Update::get_instance()->version230();

		$this->assertFalse( wp_next_scheduled( 'easy_language_capito_quota_interval' ) );
		$this->assertFalse( wp_next_scheduled( 'easy_language_summ_ai_request_quota' ) );
	}

	/**
	 * 2.4.0: the old separator is removed and separator and new lines are set for each target language of SUMM AI.
	 *
	 * @return void
	 */
	public function test_version240_sets_summ_ai_language_settings(): void {
		update_option( 'easy_language_summ_ai_separator', 'interpunct' );
		delete_option( 'easy_language_summ_ai_target_languages_separator' );
		delete_option( 'easy_language_summ_ai_target_languages_new_lines' );

		Update::get_instance()->version240();

		$this->assertFalse( get_option( 'easy_language_summ_ai_separator' ) );

		$target_languages = Summ_Ai::get_instance()->get_supported_target_languages();
		$separators       = get_option( 'easy_language_summ_ai_target_languages_separator' );
		$new_lines        = get_option( 'easy_language_summ_ai_target_languages_new_lines' );
		$this->assertIsArray( $separators );
		$this->assertIsArray( $new_lines );
		foreach ( $target_languages as $language => $settings ) {
			$this->assertSame( $settings['separator'], $separators[ $language ] );
			$this->assertSame( $settings['new_lines'] ? 1 : 0, $new_lines[ $language ] );
		}
	}

	/**
	 * 2.4.0: existing settings for separator and new lines are kept.
	 *
	 * @return void
	 */
	public function test_version240_keeps_existing_settings(): void {
		update_option( 'easy_language_summ_ai_target_languages_separator', array( 'de_LS' => 'hyphen' ) );
		update_option( 'easy_language_summ_ai_target_languages_new_lines', array( 'de_LS' => 0 ) );

		Update::get_instance()->version240();

		$this->assertSame( array( 'de_LS' => 'hyphen' ), get_option( 'easy_language_summ_ai_target_languages_separator' ) );
		$this->assertSame( array( 'de_LS' => 0 ), get_option( 'easy_language_summ_ai_target_languages_new_lines' ) );
	}

	/**
	 * 2.5.0: embolden negative is set for each target language of SUMM AI, existing settings are kept.
	 *
	 * @return void
	 */
	public function test_version250_sets_embolden_negative(): void {
		delete_option( 'easy_language_summ_ai_target_languages_embolden_negative' );

		Update::get_instance()->version250();

		$embolden = get_option( 'easy_language_summ_ai_target_languages_embolden_negative' );
		$this->assertIsArray( $embolden );
		foreach ( Summ_Ai::get_instance()->get_supported_target_languages() as $language => $settings ) {
			$this->assertSame( $settings['embolden_negative'] ? 1 : 0, $embolden[ $language ] );
		}

		// existing settings are kept.
		update_option( 'easy_language_summ_ai_target_languages_embolden_negative', array( 'de_LS' => 1 ) );
		Update::get_instance()->version250();
		$this->assertSame( array( 'de_LS' => 1 ), get_option( 'easy_language_summ_ai_target_languages_embolden_negative' ) );
	}

	/**
	 * 2.9.0: the account type of capito is set to "user".
	 *
	 * @return void
	 */
	public function test_version290_sets_capito_account_type(): void {
		delete_option( 'easy_language_capito_account_type' );

		Update::get_instance()->version290();

		$this->assertSame( 'user', get_option( 'easy_language_capito_account_type' ) );
	}

	/**
	 * 3.0.0: the SUMM AI settings per target language are split into one option per language and setting.
	 *
	 * @return void
	 */
	public function test_version300_splits_summ_ai_settings(): void {
		update_option( 'easy_language_summ_ai_target_languages', array( 'de_LS' => '1', 'de_EL' => '0' ) );
		update_option( 'easy_language_summ_ai_target_languages_separator', array( 'de_LS' => 'hyphen', 'de_EL' => 'interpunct' ) );
		update_option( 'easy_language_summ_ai_target_languages_new_lines', array( 'de_LS' => 1, 'de_EL' => 0 ) );
		update_option( 'easy_language_summ_ai_target_languages_embolden_negative', array( 'de_LS' => 0, 'de_EL' => 1 ) );

		Update::get_instance()->version300();

		// new options per language.
		$this->assertSame( '1', get_option( 'easy_language_summ_ai_target_languages_de_LS' ) );
		$this->assertSame( '0', get_option( 'easy_language_summ_ai_target_languages_de_EL' ) );
		$this->assertSame( 'hyphen', get_option( 'easy_language_summ_ai_target_languages_de_LS_separator' ) );
		$this->assertSame( 'interpunct', get_option( 'easy_language_summ_ai_target_languages_de_EL_separator' ) );
		$this->assertEquals( 1, get_option( 'easy_language_summ_ai_target_languages_de_LS_new_line' ) );
		$this->assertEquals( 0, get_option( 'easy_language_summ_ai_target_languages_de_EL_new_line' ) );
		$this->assertEquals( 0, get_option( 'easy_language_summ_ai_target_languages_de_LS_embolden_negative' ) );
		$this->assertEquals( 1, get_option( 'easy_language_summ_ai_target_languages_de_EL_embolden_negative' ) );

		// old options are removed.
		foreach ( array( '', '_separator', '_new_lines', '_embolden_negative' ) as $suffix ) {
			$this->assertFalse( get_option( 'easy_language_summ_ai_target_languages' . $suffix ) );
		}
	}

	/**
	 * 3.0.0: a second run does not change the migrated settings.
	 *
	 * @return void
	 */
	public function test_version300_second_run_keeps_settings(): void {
		update_option( 'easy_language_summ_ai_target_languages', array( 'de_LS' => '1' ) );
		update_option( 'easy_language_summ_ai_target_languages_separator', array( 'de_LS' => 'hyphen' ) );

		Update::get_instance()->version300();
		Update::get_instance()->version300();

		$this->assertSame( '1', get_option( 'easy_language_summ_ai_target_languages_de_LS' ) );
		$this->assertSame( 'hyphen', get_option( 'easy_language_summ_ai_target_languages_de_LS_separator' ) );
	}

	/**
	 * 3.0.0: interval settings get the prefix of the intervals of this plugin, if this is a registered interval.
	 *
	 * @return void
	 */
	public function test_version300_migrates_interval_settings(): void {
		// old value of an own interval.
		update_option( 'easy_language_capito_quota_interval', 'daily' );
		// value which has already the prefix.
		update_option( 'easy_language_summ_ai_quota_interval', 'easy_language_weekly' );
		// unknown value.
		update_option( 'easy_language_automatic_simplification', 'unknown' );

		Update::get_instance()->version300();

		$this->assertSame( 'easy_language_daily', get_option( 'easy_language_capito_quota_interval' ) );
		$this->assertSame( 'easy_language_weekly', get_option( 'easy_language_summ_ai_quota_interval' ) );
		$this->assertSame( 'unknown', get_option( 'easy_language_automatic_simplification' ) );
	}

	/**
	 * 3.0.2: the rewrite rules are refreshed.
	 *
	 * @return void
	 */
	public function test_version302_requests_rewrite_refresh(): void {
		delete_transient( 'easy_language_refresh_rewrite_rules' );

		Update::get_instance()->version302();

		$this->assertEquals( 1, get_transient( 'easy_language_refresh_rewrite_rules' ) );
	}

	/**
	 * 3.3.0: the API tokens in the saved requests are anonymized, everything else is kept.
	 *
	 * @return void
	 */
	public function test_version330_anonymizes_tokens(): void {
		global $wpdb;
		$table = \easyLanguage\EasyLanguage\Db::get_instance()->get_wpdb_prefix() . 'easy_language_chatgpt';

		// a request with token, an already anonymized one and a truncated one (no valid JSON).
		$requests = array(
			'token'      => '{"headers":{"Authorization":"Bearer sk-secret-123","Content-Type":"application/json"},"body":"text"}',
			'anonymized' => '{"headers":{"Authorization":"anonymized"},"body":"other"}',
			'truncated'  => '{"headers":{"Authorization":"Bearer sk-secret-\\"456","Content-Ty',
		);
		$ids      = array();
		foreach ( $requests as $key => $request ) {
			$wpdb->insert( $table, array( 'request' => $request, 'response' => 'response ' . $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$ids[ $key ] = (int) $wpdb->insert_id;
		}

		Update::get_instance()->version330();

		// get the requests again.
		$get = static function ( int $id ) use ( $wpdb, $table ): array {
			return (array) $wpdb->get_row( $wpdb->prepare( 'SELECT `request`, `response` FROM %i WHERE `id` = %d', $table, $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		};

		$token = $get( $ids['token'] );
		$this->assertSame( '{"headers":{"Authorization":"anonymized","Content-Type":"application/json"},"body":"text"}', $token['request'] );
		$this->assertSame( 'response token', $token['response'] );

		$this->assertSame( $requests['anonymized'], $get( $ids['anonymized'] )['request'] );

		$truncated = $get( $ids['truncated'] );
		$this->assertStringNotContainsString( 'sk-secret', $truncated['request'] );
		$this->assertStringContainsString( '"Authorization":"anonymized"', $truncated['request'] );

		// a second run does not change anything.
		Update::get_instance()->version330();
		$this->assertSame( $token['request'], $get( $ids['token'] )['request'] );
	}

	/**
	 * The updates run only the migrations for versions newer than the version in the database and save the new version.
	 *
	 * @return void
	 */
	public function test_run_updates_runs_only_newer_migrations(): void {
		// markers of the migrations 2.9.0 and 4.0.0.
		update_option( 'easy_language_capito_account_type', 'team' );
		update_option( 'easy_language_switcher_compatibility', 0 );

		// update from 3.0.0: 2.9.0 must not run, 4.0.0 must run.
		$this->call_private( 'run_updates', array( '4.0.0', '3.0.0' ) );

		$this->assertSame( 'team', get_option( 'easy_language_capito_account_type' ) );
		$this->assertEquals( 1, get_option( 'easy_language_switcher_compatibility' ) );
		$this->assertSame( '4.0.0', get_option( 'easyLanguageVersion' ) );
	}

	/**
	 * Updates from old versions run all migrations.
	 *
	 * @return void
	 */
	public function test_run_updates_from_old_version(): void {
		update_option( 'easy_language_capito_account_type', 'team' );
		update_option( 'easy_language_summ_ai_target_languages', array( 'de_LS' => '1' ) );

		$this->call_private( 'run_updates', array( '4.0.0', '2.8.0' ) );

		$this->assertSame( 'user', get_option( 'easy_language_capito_account_type' ) );
		$this->assertFalse( get_option( 'easy_language_summ_ai_target_languages' ) );
		$this->assertSame( '1', get_option( 'easy_language_summ_ai_target_languages_de_LS' ) );

		// the automatic simplification is scheduled with a registered interval.
		$event = wp_get_scheduled_event( 'easy_language_automatic_simplification' );
		$this->assertNotFalse( $event );
		$this->assertArrayHasKey( $event->schedule, wp_get_schedules() );
	}

	/**
	 * The automatic simplification is moved from a not registered old interval to a registered one.
	 *
	 * @return void
	 */
	public function test_run_updates_moves_old_schedule(): void {
		// simulate an old event with an interval which is not registered anymore.
		wp_clear_scheduled_hook( 'easy_language_automatic_simplification' );
		$crons = _get_cron_array();
		$crons[ time() + 60 ]['easy_language_automatic_simplification'][ md5( serialize( array() ) ) ] = array( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			'schedule' => 'minutely',
			'args'     => array(),
			'interval' => 60,
		);
		_set_cron_array( $crons );
		update_option( 'easy_language_automatic_simplification', '15minutly' );

		$this->call_private( 'run_updates', array( '4.0.0', '3.3.0' ) );

		$event = wp_get_scheduled_event( 'easy_language_automatic_simplification' );
		$this->assertNotFalse( $event );
		$this->assertSame( 'easy_language_15minutly', $event->schedule );
	}

	/**
	 * Only one process could run the updates: the lock could not be set twice, an orphaned lock is removed.
	 *
	 * @return void
	 */
	public function test_lock(): void {
		delete_option( 'easy_language_update_lock' );

		$this->assertTrue( $this->call_private( 'set_lock' ) );
		$this->assertFalse( $this->call_private( 'set_lock' ) );

		$this->call_private( 'release_lock' );
		$this->assertTrue( $this->call_private( 'set_lock' ) );

		// an orphaned lock (older than 10 minutes) is removed.
		global $wpdb;
		$wpdb->update( $wpdb->options, array( 'option_value' => (string) ( time() - HOUR_IN_SECONDS ) ), array( 'option_name' => 'easy_language_update_lock' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->assertTrue( $this->call_private( 'set_lock' ) );

		$this->call_private( 'release_lock' );
		$this->assertFalse( get_option( 'easy_language_update_lock' ) );
	}
}
