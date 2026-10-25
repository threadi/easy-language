<?php
/**
 * Tests for the quota handling (Objects::get_quota_state() and Helper::is_quota_used_up()).
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the quota handling with the free quota of SUMM AI.
 */
class Quota extends easyLanguageTests {
	/**
	 * The simplified object.
	 *
	 * @var \easyLanguage\EasyLanguage\Objects
	 */
	private \easyLanguage\EasyLanguage\Objects $copy;

	/**
	 * The API object.
	 *
	 * @var \easyLanguage\Apis\Summ_Ai\Summ_Ai
	 */
	private \easyLanguage\Apis\Summ_Ai\Summ_Ai $api_obj;

	/**
	 * Prepare the test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		switch_to_locale( 'de_DE' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// use the free mode of SUMM AI with its fixed limit.
		$this->api_obj = \easyLanguage\Apis\Summ_Ai\Summ_Ai::get_instance();
		update_option( 'easy_language_summ_ai_mode', 'free' );
		update_option( 'easy_language_summ_ai_quota', 0 );

		// create an original and its simplified object (text with 50 characters).
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Kontingent',
				'post_content' => '<p>' . str_repeat( 'a', 43 ) . '</p>',
			)
		);
		$copy    = ( new \easyLanguage\EasyLanguage\Post_Object( $post_id ) )->add_simplification_object( 'de_LS', $this->api_obj, true );
		$this->assertInstanceOf( \easyLanguage\EasyLanguage\Objects::class, $copy );
		$this->copy = $copy;
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		// no TRUNCATE via Db::reset_simplifications() here: in MySQL it commits the test transaction,
		// the texts of this test would then remain for other tests. The rollback of WordPress removes them.
		update_option( 'easy_language_summ_ai_quota', 0 );
		parent::tear_down();
	}

	/**
	 * Set the spent characters and return the quota state of the simplified object.
	 *
	 * @param int $spent The spent characters.
	 *
	 * @return array<string,mixed>
	 */
	private function get_state( int $spent ): array {
		update_option( 'easy_language_summ_ai_quota', $spent );
		return $this->copy->get_quota_state( $this->api_obj );
	}

	/**
	 * Test the states depending on the rest of the quota.
	 *
	 * @return void
	 */
	public function test_get_quota_state(): void {
		$chars = $this->get_state( 0 )['chars_count'];
		$this->assertGreaterThan( 0, $chars );

		// enough quota.
		$state = $this->get_state( 0 );
		$this->assertEquals( 'ok', $state['status'] );
		$this->assertEquals( EASY_LANGUAGE_SUMM_AI_QUOTA, $state['quota_rest'] );

		// not enough for all texts, but not used up.
		$state = $this->get_state( EASY_LANGUAGE_SUMM_AI_QUOTA - 1 );
		$this->assertEquals( 'above_limit', $state['status'] );
		$this->assertEquals( 1, $state['quota_rest'] );

		// exactly used up.
		$state = $this->get_state( EASY_LANGUAGE_SUMM_AI_QUOTA );
		$this->assertEquals( 'exceeded', $state['status'] );

		// more spent than the limit allows (the API may accept requests beyond the limit).
		$state = $this->get_state( EASY_LANGUAGE_SUMM_AI_QUOTA + 1764 );
		$this->assertEquals( 'exceeded', $state['status'] );
		$this->assertEquals( -1764, $state['quota_rest'] );
	}

	/**
	 * Test the check whether the quota is used up.
	 *
	 * @return void
	 */
	public function test_is_quota_used_up(): void {
		update_option( 'easy_language_summ_ai_quota', 0 );
		$this->assertFalse( \easyLanguage\Plugin\Helper::is_quota_used_up( $this->api_obj ) );

		update_option( 'easy_language_summ_ai_quota', EASY_LANGUAGE_SUMM_AI_QUOTA - 1 );
		$this->assertFalse( \easyLanguage\Plugin\Helper::is_quota_used_up( $this->api_obj ) );

		update_option( 'easy_language_summ_ai_quota', EASY_LANGUAGE_SUMM_AI_QUOTA );
		$this->assertTrue( \easyLanguage\Plugin\Helper::is_quota_used_up( $this->api_obj ) );

		update_option( 'easy_language_summ_ai_quota', EASY_LANGUAGE_SUMM_AI_QUOTA + 1764 );
		$this->assertTrue( \easyLanguage\Plugin\Helper::is_quota_used_up( $this->api_obj ) );
	}
}
