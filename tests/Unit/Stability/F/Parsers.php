<?php
/**
 * Regression tests for stability fixes in the parsers.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Unit\Stability\F;

use easyLanguage\EasyLanguage\Post_Object;
use easyLanguage\Tests\easyLanguageTests;
use stdClass;

/**
 * Object to test the stability fixes in the parsers.
 */
class Parsers extends easyLanguageTests {
	/**
	 * List of plugins to mark as active.
	 *
	 * @var array<int,string>
	 */
	private array $active_plugins = array();

	/**
	 * Load the fakes.
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		require_once dirname( __DIR__, 3 ) . '/Fakes/Parser/Themify/ThemifyBuilder_Data_Manager.php';
		require_once dirname( __DIR__, 3 ) . '/Fakes/Parser/Breakdance/functions.php';
		require_once dirname( __DIR__, 3 ) . '/Fakes/Parser/Elementor/classes.php';
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_option_active_plugins', array( $this, 'get_active_plugins' ) );
		$this->active_plugins = array();
		parent::tear_down();
	}

	/**
	 * Return the list of active plugins.
	 *
	 * @return array<int,string>
	 */
	public function get_active_plugins(): array {
		return $this->active_plugins;
	}

	/**
	 * Mark the given plugin as active.
	 *
	 * @param string $plugin The plugin.
	 *
	 * @return void
	 */
	private function activate( string $plugin ): void {
		$this->active_plugins[] = $plugin;
		add_filter( 'pre_option_active_plugins', array( $this, 'get_active_plugins' ) );
	}

	/**
	 * Themify returns its texts as list (the index is used as order for Text::set_object()), duplicates only once.
	 *
	 * @return void
	 */
	public function test_themify_parsed_texts_are_list(): void {
		$this->activate( 'themify-builder/themify-builder.php' );

		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		\ThemifyBuilder_Data_Manager::save_data(
			array(
				array(
					'cols' => array(
						array(
							'modules' => array(
								array(
									'mod_name'     => 'fancy-heading',
									'mod_settings' => array(
										'heading'     => 'Willkommen',
										'sub_heading' => 'Willkommen',
									),
								),
								array(
									'mod_name'     => 'text',
									'mod_settings' => array(
										'content_text' => '<p>Text</p>',
									),
								),
							),
						),
					),
				),
			),
			$post_id
		);

		$parser = \easyLanguage\Parser\Themify::get_instance();
		$parser->set_object_id( $post_id );
		$texts = $parser->get_parsed_texts();

		$this->assertTrue( array_is_list( $texts ) );
		$this->assertCount( 2, $texts );
		$this->assertSame( 'Willkommen', $texts[0]['text'] );
	}

	/**
	 * Shortcodes without attributes do not produce warnings and their masked attribute values are returned unmasked.
	 *
	 * @return void
	 */
	public function test_wpbakery_shortcode_without_attributes_and_unmasking(): void {
		$this->activate( 'js_composer/js_composer.php' );

		$content = '[vc_btn][vc_btn title="Sag &quot;Ja&quot; &#91;jetzt&#93;"]';
		$parser  = \easyLanguage\Parser\WpBakery::get_instance();
		$parser->set_object_id( 0 );
		$parser->set_text( $content );

		$texts = $parser->get_parsed_texts();
		$this->assertSame( array( 'Sag "Ja" [jetzt]' ), array_column( $texts, 'text' ) );

		// the unmasked text is replaced and the result is masked again.
		$parser->set_text( 'Sag "Ja" [jetzt]' );
		$result = $parser->get_text_with_simplifications( $content, 'Sag "Nein"' );
		$this->assertSame( '[vc_btn][vc_btn title="Sag &quot;Nein&quot;"]', $result );

		// the parsed text of the result is the simplification itself.
		$parser->set_text( $result );
		$this->assertSame( array( 'Sag "Nein"' ), array_column( $parser->get_parsed_texts(), 'text' ) );
	}

	/**
	 * Same for BoldBuilder and Salients WPBakery.
	 *
	 * @return void
	 */
	public function test_other_shortcode_parsers_without_attributes(): void {
		$this->activate( 'bold-page-builder/bold-builder.php' );
		add_filter(
			'easy_language_boldbuilder_text_widgets',
			static function ( array $shortcodes ): array {
				$shortcodes['bt_bb_headline'] = array( 'headline' );
				return $shortcodes;
			}
		);
		$parser = \easyLanguage\Parser\BoldBuilder::get_instance();
		$parser->set_object_id( 0 );
		$parser->set_text( '[bt_bb_headline][/bt_bb_headline][bt_bb_headline headline="Hallo"][/bt_bb_headline]' );
		$this->assertContains( 'Hallo', array_column( $parser->get_parsed_texts(), 'text' ) );

		$this->activate( 'js_composer_salient/js_composer.php' );
		$parser = \easyLanguage\Parser\Salients_WpBakery::get_instance();
		$parser->set_object_id( 0 );
		$parser->set_text( '[vc_btn][vc_btn title="Hallo"]' );
		$this->assertSame( array( 'Hallo' ), array_column( $parser->get_parsed_texts(), 'text' ) );
	}

	/**
	 * Beaver Builder replaces the texts in the draft, too, and ignores nodes without settings.
	 *
	 * @return void
	 */
	public function test_beaverbuilder_draft_and_invalid_nodes(): void {
		update_option( 'active_plugins', array( 'beaver-builder-lite-version/fl-builder.php' ) );

		$get_data = static function (): array {
			return array(
				'a' => (object) array(
					'node'     => 'a',
					'type'     => 'module',
					'settings' => (object) array(
						'type'    => 'heading',
						'heading' => 'Willkommen',
					),
				),
				'b' => (object) array(
					'node' => 'b',
					'type' => 'module',
				),
				'c' => (object) array(
					'node'     => 'c',
					'settings' => (object) array( 'type' => 'heading' ),
				),
				'd' => (object) array(
					'node'     => 'd',
					'type'     => 'module',
					'settings' => (object) array(
						'type'    => 'heading',
						'heading' => array( 'kein Text' ),
					),
				),
			);
		};

		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_fl_builder_data', $get_data() );
		update_post_meta( $post_id, '_fl_builder_draft', $get_data() );

		$parser = \easyLanguage\Parser\BeaverBuilder::get_instance();
		$parser->set_object_id( $post_id );
		$parser->set_text( 'Willkommen' );

		$this->assertSame( array( 'Willkommen' ), array_column( $parser->get_parsed_texts(), 'text' ) );

		$parser->get_text_with_simplifications( 'Willkommen', 'Hallo' );

		$data  = get_post_meta( $post_id, '_fl_builder_data', true );
		$draft = get_post_meta( $post_id, '_fl_builder_draft', true );
		$this->assertInstanceOf( stdClass::class, $data['a'] );
		$this->assertSame( 'Hallo', $data['a']->settings->heading );
		$this->assertSame( 'Hallo', $draft['a']->settings->heading );
	}

	/**
	 * Elementor removes its element cache on update.
	 *
	 * @return void
	 */
	public function test_elementor_element_cache_is_removed(): void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_elementor_element_cache', array( 'x' => 1 ) );

		\easyLanguage\Parser\Elementor::get_instance()->update_object( new Post_Object( $post_id ) );

		$this->assertSame( '', get_post_meta( $post_id, '_elementor_element_cache', true ) );
	}

	/**
	 * Return a widget configuration with a property path for Breakdance.
	 *
	 * @param array<string,mixed> $widgets The widgets.
	 *
	 * @return array<string,mixed>
	 */
	public function add_breakdance_widget( array $widgets ): array {
		$widgets['EssentialElements\Heading'] = array(
			'properties' => array(
				'content' => array(
					'content' => array(
						'title',
					),
				),
			),
		);
		return $widgets;
	}

	/**
	 * Breakdance uses the property paths of the configuration.
	 *
	 * @return void
	 */
	public function test_breakdance_uses_configured_paths(): void {
		update_option( 'active_plugins', array( 'breakdance/plugin.php' ) );
		add_filter( 'easy_language_breakdance_text_widgets', array( $this, 'add_breakdance_widget' ) );

		$tree    = array(
			'root' => array(
				'id'       => 1,
				'data'     => array( 'type' => 'root' ),
				'children' => array(
					array(
						'id'       => 2,
						'data'     => array(
							'type'       => 'EssentialElements\Heading',
							'properties' => array( 'content' => array( 'content' => array( 'title' => 'Überschrift' ) ) ),
						),
						'children' => array(),
					),
					array(
						'id'       => 3,
						'data'     => array(
							'type'       => 'EssentialElements\Text',
							'properties' => array( 'content' => array( 'content' => array( 'text' => '<p>Text</p>' ) ) ),
						),
						'children' => array(),
					),
				),
			),
		);
		$post_id = self::factory()->post->create();
		\Breakdance\Data\set_meta( $post_id, '_breakdance_data', array( 'tree_json_string' => (string) wp_json_encode( $tree ) ) );

		$parser = \easyLanguage\Parser\Breakdance::get_instance();
		$parser->set_object_id( $post_id );
		$parser->set_text( 'Überschrift' );

		$this->assertSame( array( 'Überschrift', '<p>Text</p>' ), array_column( $parser->get_parsed_texts(), 'text' ) );

		$parser->get_text_with_simplifications( '', 'Titel' );

		$stored = \Breakdance\Data\get_tree( $post_id );
		$this->assertSame( 'Titel', $stored['root']['children'][0]['data']['properties']['content']['content']['title'] );
		$this->assertSame( '<p>Text</p>', $stored['root']['children'][1]['data']['properties']['content']['content']['text'] );

		remove_filter( 'easy_language_breakdance_text_widgets', array( $this, 'add_breakdance_widget' ) );
	}

	/**
	 * Add a Divi 5 block with a callback and a configured path.
	 *
	 * @param array<string,mixed> $blocks The blocks.
	 *
	 * @return array<string,mixed>
	 */
	public function add_divi5_block( array $blocks ): array {
		$blocks['divi/blurb'] = array(
			'html'     => true,
			'callback' => function ( $block ) {
				return $block['attrs']['title']['innerContent']['desktop']['value'] ?? '';
			},
			'paths'    => array( 'title.innerContent.desktop.value' ),
		);
		return $blocks;
	}

	/**
	 * Divi 5 writes the text of blocks with callback to their configured path, not to the path of "divi/text".
	 *
	 * @return void
	 */
	public function test_divi5_callback_block_uses_configured_path(): void {
		add_filter( 'easy_language_divi5_blocks', array( $this, 'add_divi5_block' ) );

		$content = serialize_block(
			array(
				'blockName'    => 'divi/blurb',
				'attrs'        => array( 'title' => array( 'innerContent' => array( 'desktop' => array( 'value' => 'Hallo Welt' ) ) ) ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$parser = \easyLanguage\Parser\Divi5::get_instance();
		$parser->set_text( 'Hallo Welt' );
		$result = parse_blocks( $parser->get_text_with_simplifications( $content, 'Hallo' ) );

		$this->assertSame( 'Hallo', $result[0]['attrs']['title']['innerContent']['desktop']['value'] );
		$this->assertArrayNotHasKey( 'content', $result[0]['attrs'] );

		remove_filter( 'easy_language_divi5_blocks', array( $this, 'add_divi5_block' ) );
	}

	/**
	 * Salients WPBakery does not run save_post for an object without post.
	 *
	 * @return void
	 */
	public function test_salients_update_object_without_post(): void {
		$post_id = self::factory()->post->create();
		$object  = new Post_Object( $post_id );
		wp_delete_post( $post_id, true );

		$runs = did_action( 'save_post' );
		\easyLanguage\Parser\Salients_WpBakery::get_instance()->update_object( $object );
		$this->assertSame( $runs, did_action( 'save_post' ) );
	}
}
