<?php
/**
 * Tests for the tables in the backend of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\EasyLanguage;

use easyLanguage\EasyLanguage\Db;
use easyLanguage\EasyLanguage\Tables\Texts_In_Use_Table;
use easyLanguage\EasyLanguage\Tables\Texts_To_Simplify_Table;
use easyLanguage\EasyLanguage\Text;
use easyLanguage\Plugin\Tables\Language_Icons_Table;
use easyLanguage\Plugin\Tables\Log_Api_Table;
use easyLanguage\Plugin\Tables\Log_Table;
use easyLanguage\Tests\Switcher_Tests;

/**
 * Object to test the output of the tables: texts come from contents of the website and from the APIs, so they must
 * never be output as HTML.
 */
class Tables extends Switcher_Tests {

	/**
	 * A text with HTML, which must not be output as HTML.
	 */
	private const DANGEROUS = '<script>alert(1)</script><img src=x onerror=alert(2)>Text';

	/**
	 * Load the tables of WordPress.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		set_current_screen( 'settings_page_easy_language_settings' );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * Return a text with a dangerous original and simplification, used on the simplified page.
	 *
	 * @return Text
	 */
	private function get_dangerous_text(): Text {
		$text = Db::get_instance()->add( self::DANGEROUS . ' ' . wp_generate_password( 8, false ), 'de_DE', 'post_content', true );
		$this->assertInstanceOf( Text::class, $text );
		$text->set_object( 'page', $this->ids['about_ls'], 0, '' );
		$text->set_simplification( self::DANGEROUS, 'de_LS', 'summ_ai', 0 );
		return $text;
	}

	/**
	 * Assert that the given output contains no HTML of the dangerous text.
	 *
	 * @param string $output The output.
	 * @param string $column The name of the column.
	 *
	 * @return void
	 */
	private function assert_safe( string $output, string $column ): void {
		$this->assertStringNotContainsString( '<script', $output, 'Column ' . $column . ' outputs HTML.' );
		$this->assertStringNotContainsString( '<img src=x', $output, 'Column ' . $column . ' outputs HTML.' );
	}

	/**
	 * The table of simplified texts outputs no HTML of the texts.
	 *
	 * @return void
	 */
	public function test_texts_in_use_table_escapes_texts(): void {
		$text  = $this->get_dangerous_text();
		$table = new Texts_In_Use_Table();

		foreach ( array_keys( $table->get_columns() ) as $column ) {
			$this->assert_safe( $table->column_default( $text, $column ), $column );
		}

		// the texts are shown as plain text.
		$this->assertStringContainsString( 'Text', $table->column_default( $text, 'original' ) );
		$this->assertSame( '', $table->column_default( $text, 'unknown_column' ) );
	}

	/**
	 * The options of the table of simplified texts contain the secured link to delete it.
	 *
	 * @return void
	 */
	public function test_texts_in_use_table_options(): void {
		$text    = $this->get_dangerous_text();
		$options = ( new Texts_In_Use_Table() )->column_default( $text, 'options' );

		$this->assertStringContainsString( 'action=easy_language_delete_simplification', $options );
		$this->assertStringContainsString( 'id=' . $text->get_id(), $options );
		$this->assertStringContainsString( 'nonce=', $options );
	}

	/**
	 * The column could be hidden via filter.
	 *
	 * @return void
	 */
	public function test_texts_in_use_table_column_filter(): void {
		$text = $this->get_dangerous_text();
		add_filter( 'easy_language_simplification_table_used_in', '__return_empty_string' );
		$this->assertSame( '', ( new Texts_In_Use_Table() )->column_default( $text, 'original' ) );
		remove_filter( 'easy_language_simplification_table_used_in', '__return_empty_string' );
	}

	/**
	 * The table of texts to simplify outputs no HTML of the texts.
	 *
	 * @return void
	 */
	public function test_texts_to_simplify_table_escapes_texts(): void {
		$text  = $this->get_dangerous_text();
		$table = new Texts_To_Simplify_Table();

		foreach ( array_keys( $table->get_columns() ) as $column ) {
			$this->assert_safe( $table->column_default( $text, $column ), $column );
		}

		// the options contain the secured link to delete the text.
		$options = $table->column_default( $text, 'options' );
		$this->assertStringContainsString( 'action=easy_language_delete_text_for_simplification', $options );
		$this->assertStringContainsString( 'nonce=', $options );
	}

	/**
	 * The tables could be prepared (with the texts of the database) without errors.
	 *
	 * @return void
	 */
	public function test_tables_could_be_prepared(): void {
		$this->get_dangerous_text();
		foreach ( array( new Texts_In_Use_Table(), new Texts_To_Simplify_Table(), new Language_Icons_Table(), new Log_Table(), new Log_Api_Table() ) as $table ) {
			$table->prepare_items();
			$this->assertIsArray( $table->items, get_class( $table ) );

			// the output of the complete table contains no HTML of the texts.
			ob_start();
			$table->display();
			$this->assert_safe( (string) ob_get_clean(), get_class( $table ) );
		}
	}

	/**
	 * The log outputs no scripts of its entries.
	 *
	 * @return void
	 */
	public function test_log_table_escapes_entries(): void {
		$table = new Log_Table();
		$item  = array(
			'state'   => 'error',
			'date'    => gmdate( 'Y-m-d H:i:s' ),
			'log'     => self::DANGEROUS,
			'user_id' => 0,
		);

		$this->assertStringNotContainsString( '<script', $table->column_default( $item, 'log' ) );
		$this->assertStringNotContainsString( 'onerror', $table->column_default( $item, 'log' ) );
		$this->assertSame( __( 'Unknown', 'easy-language' ), $table->column_default( $item, 'user_id' ) );
	}

	/**
	 * The API log outputs no scripts of requests and responses.
	 *
	 * @return void
	 */
	public function test_api_log_table_escapes_entries(): void {
		$table = new Log_Api_Table();
		$item  = array(
			'state'    => 'error',
			'date'     => gmdate( 'Y-m-d H:i:s' ),
			'request'  => self::DANGEROUS,
			'response' => self::DANGEROUS,
			'api'      => 'summ_ai',
		);

		foreach ( array( 'request', 'response' ) as $column ) {
			$this->assertStringNotContainsString( '<script', $table->column_default( $item, $column ) );
			$this->assertStringNotContainsString( 'onerror', $table->column_default( $item, $column ) );
		}
		$this->assertSame( '', $table->column_default( $item, 'unknown_column' ) );
	}

	/**
	 * The table of icons contains each language once with its code for the replacement.
	 *
	 * @return void
	 */
	public function test_language_icons_table(): void {
		$table = new Language_Icons_Table();
		$table->prepare_items();
		$this->assertNotEmpty( $table->items );

		$codes = wp_list_pluck( $table->items, 'code' );
		$this->assertSame( array_unique( $codes ), $codes );

		foreach ( $table->items as $item ) {
			$this->assertStringContainsString( 'data-language-code="' . esc_attr( $item['code'] ) . '"', $table->column_default( $item, 'icon' ) );
			$this->assertSame( esc_html( $item['label'] ), $table->column_default( $item, 'language' ) );
		}
	}
}
