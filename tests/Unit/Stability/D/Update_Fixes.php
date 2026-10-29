<?php
/**
 * Regression tests for the fixes in the updates of the plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\D;

use easyLanguage\Plugin\Update;
use easyLanguage\Tests\easyLanguageTests;
use ReflectionMethod;

/**
 * Object to test the fixes in the updates.
 */
class Update_Fixes extends easyLanguageTests {
	/**
	 * Count of reads of the setup state.
	 *
	 * @var int
	 */
	private int $setup_reads = 0;

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_option_esfw_completed', array( $this, 'count_setup_reads' ) );
		remove_filter( 'pre_http_request', array( $this, 'block_requests' ) );
		wp_clear_scheduled_hook( 'easy_language_capito_request_quota' );
		parent::tear_down();
	}

	/**
	 * Count the reads of the setup state (used by the migration for 2.1.0).
	 *
	 * @param mixed $value The value.
	 *
	 * @return mixed
	 */
	public function count_setup_reads( mixed $value ): mixed {
		++$this->setup_reads;
		return $value;
	}

	/**
	 * Block external requests.
	 *
	 * @return array<string,mixed>
	 */
	public function block_requests(): array {
		return array(
			'headers'  => array(),
			'body'     => '{}',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
		);
	}

	/**
	 * Call the private method to run the updates.
	 *
	 * @param string $installed The installed version.
	 * @param string $db        The version in the database.
	 *
	 * @return void
	 */
	private function run_updates( string $installed, string $db ): void {
		$method = new ReflectionMethod( Update::class, 'run_updates' );
		$method->setAccessible( true );
		$method->invoke( Update::get_instance(), $installed, $db );
	}

	/**
	 * The check for the completed setup runs on every update (as before), not only from versions before 2.1.0.
	 *
	 * @return void
	 */
	public function test_setup_completion_check_runs_on_every_update(): void {
		add_filter( 'pre_option_esfw_completed', array( $this, 'count_setup_reads' ) );

		$this->run_updates( '4.0.0', '3.3.0' );
		$this->assertGreaterThan( 0, $this->setup_reads );
	}

	/**
	 * The migration of the interval settings in 3.0.0 also changes the scheduled events.
	 *
	 * @return void
	 */
	public function test_version300_reschedules_events(): void {
		add_filter( 'pre_http_request', array( $this, 'block_requests' ) );

		// capito is the active API with a token.
		update_option( 'easy_language_api', 'capito' );
		update_option( 'easy_language_capito_api_key', 'el-test-token' );

		// old settings and events with the old intervals.
		update_option( 'easy_language_automatic_simplification', '15minutly' );
		update_option( 'easy_language_capito_quota_interval', 'weekly' );
		wp_clear_scheduled_hook( 'easy_language_automatic_simplification' );
		wp_clear_scheduled_hook( 'easy_language_capito_request_quota' );
		wp_schedule_event( time(), 'hourly', 'easy_language_automatic_simplification' );
		wp_schedule_event( time(), 'daily', 'easy_language_capito_request_quota' );

		Update::get_instance()->version300();

		$event = wp_get_scheduled_event( 'easy_language_automatic_simplification' );
		$this->assertNotFalse( $event );
		$this->assertSame( 'easy_language_15minutly', $event->schedule );

		$event = wp_get_scheduled_event( 'easy_language_capito_request_quota' );
		$this->assertNotFalse( $event );
		$this->assertSame( 'easy_language_weekly', $event->schedule );
	}

	/**
	 * The quota request is not scheduled by the migration if the API is not active.
	 *
	 * @return void
	 */
	public function test_version300_does_not_schedule_quota_of_inactive_api(): void {
		add_filter( 'pre_http_request', array( $this, 'block_requests' ) );

		update_option( 'easy_language_api', 'no_api' );
		update_option( 'easy_language_capito_api_key', 'el-test-token' );
		update_option( 'easy_language_capito_quota_interval', 'weekly' );
		wp_clear_scheduled_hook( 'easy_language_capito_request_quota' );

		Update::get_instance()->version300();

		$this->assertSame( 'easy_language_weekly', get_option( 'easy_language_capito_quota_interval' ) );
		$this->assertFalse( wp_next_scheduled( 'easy_language_capito_request_quota' ) );
	}
}
