<?php
/**
 * Custom Elementor control for language-info about actual page/post.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language\Parser\Elementor;

use Elementor\Base_Data_Control;
use easyLanguage\Multilingual_plugins\Easy_Language\Post_Object;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Define the custom control.
 */
class Languages extends Base_Data_Control {

	/**
	 * Get easy language control type.
	 *
	 * Retrieve the control type, in this case `easy_languages`.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Control type.
	 */
	public function get_type(): string {
		return 'easy_languages';
	}

	/**
	 * Get the available languages.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @return array Currency control default settings.
	 */
	protected function get_default_settings(): array {
		// get the ID of the original post.
		$original_post_id = absint(get_post_meta( get_the_ID(), 'easy_language_translation_original_id', true ));
		if( $original_post_id == 0 ) {
			$original_post_id = absint(get_the_ID());
		}

		// get the original-object.
		$post_object = new Post_Object( $original_post_id );

		// get the post-language.
		$language_array = $post_object->get_language();

		// return merge of both settings.
		return array_merge( $language_array, \easyLanguage\Languages::get_instance()->get_active_languages() );
	}

	/**
	 * Get currency control default value.
	 *
	 * Retrieve the default value of the currency control. Used to return the
	 * default value while initializing the control.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Currency control default value.
	 */
	public function get_default_value(): string {
		return 'EUR';
	}

	/**
	 * Render currency control output in the editor.
	 *
	 * Used to generate the control HTML in the editor using Underscore JS
	 * template. The variables for the class are available using `data` JS
	 * object.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function content_template(): void {
		$post_id = absint($_GET['post']);

		// get the ID of the original post.
		$original_post_id = absint(get_post_meta( $post_id, 'easy_language_translation_original_id', true ));
		if( $original_post_id == 0 ) {
			$original_post_id = $post_id;
		}

		// get the original-object.
		$post_object = new Post_Object( $original_post_id );

		?>
		<# if ( data.description ) { #>
			<div class="elementor-control-field-description">{{{ data.description }}}</div>
		<# } #>

		<div class="elementor-control-field easy-language-table">
			<div class="elementor-control-input-wrapper">
				<?php
					$post_object->get_page_builder()->get_language_switch( $post_id );
				?>
			</div>
		</div>
		<?php
	}
}
