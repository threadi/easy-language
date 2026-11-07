<?php
/**
 * Tests for the callbacks, which check and save the settings of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Plugin;

use easyLanguage\Apis\Capito\Capito;
use easyLanguage\Apis\Summ_Ai\Summ_Ai;
use easyLanguage\Plugin\Settings;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the sanitize and save callbacks of the settings.
 */
class Settings_Callbacks extends easyLanguageTests {

	/**
	 * The name of the active API for the tests.
	 *
	 * @var string
	 */
	private string $active_api = 'summ_ai';

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->active_api = 'summ_ai';
		add_filter( 'pre_option_easy_language_api', array( $this, 'get_active_api' ) );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_option_easy_language_api', array( $this, 'get_active_api' ) );
		wp_clear_scheduled_hook( 'easy_language_summ_ai_request_quota' );
		wp_clear_scheduled_hook( 'easy_language_capito_request_quota' );
		parent::tear_down();
	}

	/**
	 * Return the name of the active API (without running the routines for the change of the API).
	 *
	 * @return string
	 */
	public function get_active_api(): string {
		return $this->active_api;
	}

	/**
	 * Empty checkboxes are removed, the others are kept.
	 *
	 * @return void
	 */
	public function test_sanitize_checkboxes(): void {
		$settings = Settings::get_instance();
		$this->assertNull( $settings->sanitize_checkboxes( null ) );
		$this->assertSame( array(), $settings->sanitize_checkboxes( array() ) );
		$this->assertSame(
			array(
				'page' => '1',
				'post' => 1,
			),
			$settings->sanitize_checkboxes(
				array(
					'page'    => '1',
					'post'    => 1,
					'product' => '',
					'event'   => 0,
					'other'   => null,
				)
			)
		);
	}

	/**
	 * Radio values are saved as string.
	 *
	 * @return void
	 */
	public function test_sanitize_radio_as_string(): void {
		$this->assertSame( '', Settings::get_instance()->sanitize_radio_as_string( null ) );
		$this->assertSame( 'paid', Settings::get_instance()->sanitize_radio_as_string( 'paid' ) );
	}

	/**
	 * Only known APIs could be chosen.
	 *
	 * @return void
	 */
	public function test_sanitize_api(): void {
		$this->assertSame( 'summ_ai', Settings::get_instance()->sanitize_api( 'unknown_api' ) );
		$this->assertSame( 'summ_ai', Settings::get_instance()->sanitize_api( null ) );
		$this->assertSame( 'capito', Settings::get_instance()->sanitize_api( 'capito' ) );
		$this->assertSame( 'no_api', Settings::get_instance()->sanitize_api( 'no_api' ) );
	}

	/**
	 * The change of the API sets the simplified objects of the former API to draft and restores them on the way back.
	 *
	 * @return void
	 */
	public function test_save_api_changes_state_of_simplified_objects(): void {
		update_option( 'easy_language_post_types', array( 'post' => '1' ) );
		update_option( 'easy_language_state_on_api_change', 'draft' );

		// a published simplified post of SUMM AI.
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		update_post_meta( $post_id, 'easy_language_api', 'summ_ai' );

		// change to capito: the post is set to draft.
		$this->assertSame( 'capito', Settings::get_instance()->save_api( 'capito' ) );
		$this->assertSame( 'draft', get_post_status( $post_id ) );
		$this->assertSame( 'publish', get_post_meta( $post_id, 'easy_language_simplification_state_changed_from', true ) );

		// change back to SUMM AI: the post is published again.
		$this->active_api = 'capito';
		$this->assertSame( 'summ_ai', Settings::get_instance()->save_api( 'summ_ai' ) );
		$this->assertSame( 'publish', get_post_status( $post_id ) );
		$this->assertSame( '', get_post_meta( $post_id, 'easy_language_simplification_state_changed_from', true ) );
	}

	/**
	 * The change of the API does not change simplified objects if this is disabled.
	 *
	 * @return void
	 */
	public function test_save_api_without_state_change(): void {
		update_option( 'easy_language_post_types', array( 'post' => '1' ) );
		update_option( 'easy_language_state_on_api_change', 'disabled' );

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		update_post_meta( $post_id, 'easy_language_api', 'summ_ai' );

		Settings::get_instance()->save_api( 'capito' );
		$this->assertSame( 'publish', get_post_status( $post_id ) );
	}

	/**
	 * The language settings save only chosen languages and refresh the rewrite rules.
	 *
	 * @return void
	 */
	public function test_change_languages(): void {
		delete_transient( 'easy_language_refresh_rewrite_rules' );

		$result = \easyLanguage\EasyLanguage\Init::get_instance()->change_languages(
			array(
				'de_LS' => '1',
				'de_EL' => '',
			)
		);

		$this->assertSame( array( 'de_LS' => '1' ), $result );
		$this->assertNotFalse( get_transient( 'easy_language_refresh_rewrite_rules' ) );
		$this->assertNull( \easyLanguage\EasyLanguage\Init::get_instance()->change_languages( null ) );
	}

	/**
	 * Checkboxes without any chosen value are saved as empty list.
	 *
	 * @return void
	 */
	public function test_validate_checkboxes(): void {
		$this->assertSame( array(), \easyLanguage\EasyLanguage\Init::get_instance()->validate_checkboxes( null ) );
		$this->assertSame( array( 'page' => '1' ), \easyLanguage\EasyLanguage\Init::get_instance()->validate_checkboxes( array( 'page' => '1' ) ) );
	}

	/**
	 * An invalid email for SUMM AI is not saved, the former email is kept.
	 *
	 * @return void
	 */
	public function test_validate_summ_ai_email(): void {
		update_option( 'easy_language_summ_api_email', 'former@example.com' );

		$this->assertSame( 'new@example.com', Summ_Ai::get_instance()->validate_api_email( 'new@example.com' ) );
		$this->assertSame( '', Summ_Ai::get_instance()->validate_api_email( '' ) );
		$this->assertSame( 'former@example.com', Summ_Ai::get_instance()->validate_api_email( 'no-email' ) );
		$this->assertSame( 'former@example.com', Summ_Ai::get_instance()->validate_api_email( '"><script>@example' ) );
	}

	/**
	 * The quota of SUMM AI is requested only with an active API and a paid API key.
	 *
	 * @return void
	 */
	public function test_summ_ai_quota_interval(): void {
		// without API key: no schedule (also an existing one is removed).
		wp_schedule_event( time(), 'daily', 'easy_language_summ_ai_request_quota' );
		update_option( 'easy_language_summ_ai_mode', 'free' );
		$this->assertSame( 'daily', Summ_Ai::get_instance()->set_quota_interval( 'daily' ) );
		$this->assertFalse( wp_next_scheduled( 'easy_language_summ_ai_request_quota' ) );

		// with paid API key: scheduled.
		update_option( 'easy_language_summ_ai_mode', 'paid' );
		update_option( 'easy_language_summ_ai_api_key', 'example-key' );
		Summ_Ai::get_instance()->set_quota_interval( 'daily' );
		$this->assertNotFalse( wp_next_scheduled( 'easy_language_summ_ai_request_quota' ) );

		// other API active: no schedule.
		$this->active_api = 'capito';
		Summ_Ai::get_instance()->set_quota_interval( 'daily' );
		$this->assertFalse( wp_next_scheduled( 'easy_language_summ_ai_request_quota' ) );

		delete_option( 'easy_language_summ_ai_api_key' );
		delete_option( 'easy_language_summ_ai_mode' );
	}

	/**
	 * The quota of capito is requested only with an active API and a token.
	 *
	 * @return void
	 */
	public function test_capito_quota_interval(): void {
		$this->active_api = 'capito';

		// without token: no schedule.
		delete_option( 'easy_language_capito_api_key' );
		Capito::get_instance()->set_quota_interval( 'daily' );
		$this->assertFalse( wp_next_scheduled( 'easy_language_capito_request_quota' ) );

		// with token: scheduled.
		update_option( 'easy_language_capito_api_key', 'example-token' );
		$this->assertSame( 'daily', Capito::get_instance()->set_quota_interval( 'daily' ) );
		$this->assertNotFalse( wp_next_scheduled( 'easy_language_capito_request_quota' ) );

		// empty interval: no schedule.
		Capito::get_instance()->set_quota_interval( '' );
		$this->assertFalse( wp_next_scheduled( 'easy_language_capito_request_quota' ) );

		delete_option( 'easy_language_capito_api_key' );
	}

	/**
	 * The change of the capito settings removes the cached teams.
	 *
	 * @return void
	 */
	public function test_capito_clean_team_cache(): void {
		set_transient( 'easy_language_capito_teams', array( 'team' ) );
		set_transient( 'easy_language_capito_teams_failed', 1 );

		$this->assertSame( 'value', Capito::get_instance()->clean_team_cache( 'value' ) );

		$this->assertFalse( get_transient( 'easy_language_capito_teams' ) );
		$this->assertFalse( get_transient( 'easy_language_capito_teams_failed' ) );
	}

	/**
	 * The select fields keep their value outside of the settings form.
	 *
	 * @return void
	 */
	public function test_validate_select_field(): void {
		$this->assertSame( 'daily', \easyLanguage\Plugin\Helper::settings_validate_select_field( 'daily' ) );
		$this->assertSame( '', \easyLanguage\Plugin\Helper::settings_validate_select_field( '' ) );
	}
}
