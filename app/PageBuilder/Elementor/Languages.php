<?php
/**
 * Custom Elementor control for language-info about actual page/post.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder\Elementor;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Elementor\Base_Data_Control;
use easyLanguage\EasyLanguage\Post_Object;

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
		$original_post_id = absint( get_post_meta( get_the_ID(), 'easy_language_simplification_original_id', true ) );
		if ( 0 === $original_post_id ) {
			$original_post_id = absint( get_the_ID() );
		}

		// get the original-object.
		$post_object = new Post_Object( $original_post_id );

		// get the post-language.
		$language_array = $post_object->get_language();

		// return merge of both settings.
		return array_merge( $language_array, \easyLanguage\Plugin\Languages::get_instance()->get_active_languages() );
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
		$post_id = absint( filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT ) );

		if ( $post_id > 0 ) {
			// get the ID of the original post.
			$original_post_id = absint( get_post_meta( $post_id, 'easy_language_simplification_original_id', true ) );
			if ( 0 === $original_post_id ) {
				$original_post_id = $post_id;
			}

			// get the original-object.
			$original_post_object = new Post_Object( $original_post_id );

			?>
			<# if ( data.description ) { #>
				<div class="elementor-control-field-description">{{{ data.description }}}</div>
			<# } #>

			<div class="elementor-control-field easy-language-table">
				<div class="elementor-control-input-wrapper">
					<?php
						$original_post_object->get_page_builder()->get_language_switch( $post_id );
					?>
				</div>
			</div>
			<?php
		}
	}
}
