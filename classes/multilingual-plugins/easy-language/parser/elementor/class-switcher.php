<?php
/**
 * File to add custom dynamic tag on elementor.
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language\Parser\Elementor;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

/**
 * Define the custom dynamic tag.
 */
class Switcher extends Tag {

	/**
	 * Get dynamic tag name.
	 *
	 * Retrieve the name of this tag.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Dynamic tag name.
	 */
	public function get_name(): string {
		return 'easy-language-switcher';
	}

	/**
	 * Get dynamic tag title.
	 *
	 * Returns the title of this tag.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Dynamic tag title.
	 */
	public function get_title(): string {
		return esc_html__( 'Language Switcher', 'easy-language' );
	}

	/**
	 * Get dynamic tag groups.
	 *
	 * Retrieve the list of groups this tag belongs to.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array Dynamic tag groups.
	 */
	public function get_group(): array {
		return array( \ElementorPro\Modules\DynamicTags\Module::SITE_GROUP );
	}

	/**
	 * Get dynamic tag categories.
	 *
	 * Retrieve the list of categories this tag belongs to.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array Dynamic tag categories.
	 */
	public function get_categories(): array {
		return array( Module::TEXT_CATEGORY );
	}

	/**
	 * Render tag output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @return void
	 * @since 1.0.0
	 * @access public
	 */
	public function render(): void {
		echo \EasyLanguage\Multilingual_plugins\Easy_Language\Switcher::get_instance()->get( array( 'hide_actual_language' => false, 'show_icons' => false ) );
	}
}
