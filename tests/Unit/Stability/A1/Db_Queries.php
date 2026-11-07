<?php
/**
 * Stability tests for the queries in class easyLanguage\EasyLanguage\Db.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\A1;

use easyLanguage\EasyLanguage\Db;
use easyLanguage\Tests\easyLanguageTests;

/**
 * Object to test the queries of the DB-object.
 */
class Db_Queries extends easyLanguageTests {
	/**
	 * Add a text with a unique content.
	 *
	 * @param string $field The field.
	 *
	 * @return \easyLanguage\EasyLanguage\Text
	 */
	private function add_text( string $field = 'post_content' ): \easyLanguage\EasyLanguage\Text {
		$text = Db::get_instance()->add( 'Ein Beispiel Text ' . uniqid(), 'de_DE', $field, false );
		$this->assertInstanceOf( \easyLanguage\EasyLanguage\Text::class, $text );
		return $text;
	}

	/**
	 * Return whether the given text is in the list.
	 *
	 * @param \easyLanguage\EasyLanguage\Text        $text The text.
	 * @param array<\easyLanguage\EasyLanguage\Text> $entries The list.
	 *
	 * @return int
	 */
	private function count_in_list( \easyLanguage\EasyLanguage\Text $text, array $entries ): int {
		$count = 0;
		foreach ( $entries as $entry ) {
			if ( $entry->get_id() === $text->get_id() ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Texts used only by objects of another blog must not be returned for the actual blog.
	 *
	 * @return void
	 */
	public function test_texts_of_other_blogs_are_not_returned(): void {
		global $wpdb;

		$text = $this->add_text();

		// link the text to an object of another blog.
		$wpdb->insert(
			Db::get_instance()->get_table_name_originals_objects(),
			array(
				'oid'          => $text->get_id(),
				'time'         => gmdate( 'Y-m-d H:i:s' ),
				'object_type'  => 'post',
				'object_id'    => 4711,
				'order'        => 0,
				'blog_id'      => get_current_blog_id() + 100,
				'page_builder' => '',
			)
		);

		$filter = \easyLanguage\EasyLanguage\Init::get_instance()->get_filter_for_entries_to_simplify();
		$this->assertEquals( 0, $this->count_in_list( $text, Db::get_instance()->get_entries( $filter ) ) );
	}

	/**
	 * A text used multiple times must be returned and counted only once.
	 *
	 * @return void
	 */
	public function test_texts_are_returned_only_once(): void {
		$text    = $this->add_text();
		$post_id = self::factory()->post->create();
		$post_2  = self::factory()->post->create();

		// use the text two times in the same object and once in another object.
		$text->set_object( 'post', $post_id, 1, '' );
		$text->set_object( 'post', $post_id, 2, '' );
		$text->set_object( 'post', $post_2, 1, '' );

		// check the list for the object.
		$filter = array(
			'object_id'   => $post_id,
			'object_type' => 'post',
		);
		$this->assertEquals( 1, $this->count_in_list( $text, Db::get_instance()->get_entries( $filter ) ) );
		$this->assertEquals( 1, Db::get_instance()->count_entries( $filter ) );

		// check the list of texts to simplify.
		$filter = \easyLanguage\EasyLanguage\Init::get_instance()->get_filter_for_entries_to_simplify();
		$this->assertEquals( 1, $this->count_in_list( $text, Db::get_instance()->get_entries( $filter ) ) );
		$this->assertEquals( 1, $this->count_in_list( $text, Db::get_instance()->get_entries( $filter, array( 'order_by' => 'date', 'order' => 'desc' ), 10 ) ) );
		$count = Db::get_instance()->count_entries( $filter );
		$this->assertCount( $count, Db::get_instance()->get_entries( $filter ) );

		// check the list with PHP-side filter.
		$filter['not_locked'] = true;
		$this->assertEquals( 1, $this->count_in_list( $text, Db::get_instance()->get_entries( $filter ) ) );
	}

	/**
	 * Resetting the simplifications must mark the simplified texts to simplify them again.
	 *
	 * @return void
	 */
	public function test_reset_simplifications_resets_state(): void {
		$text = $this->add_text();
		$text->set_simplification( 'Einfacher Text', 'de_LS', 'summ_ai', 1 );
		$text->set_state( 'in_use' );
		$ignored = $this->add_text();
		$ignored->set_state( 'ignore' );

		Db::get_instance()->reset_simplifications();

		$this->assertEquals( 'to_simplify', $text->get_state() );
		$this->assertEquals( 'ignore', $ignored->get_state() );
		$this->assertFalse( ( new \easyLanguage\EasyLanguage\Text( $text->get_id() ) )->has_simplification_in_language( 'de_LS' ) );
	}
}
