<?php
/**
 * File for base functions for each parser-object.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

use easyLanguage\Apis;
use easyLanguage\Helper;
use easyLanguage\Languages;
use WP_Query;

/**
 * Object with base functions for each parser-object.
 */
class Parser_Base {
	/**
	 * Object-Id.
	 *
	 * @var int
	 */
	private int $object_id;

	/**
	 * Internal name of the parser.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * The title to parse.
	 *
	 * @var string
	 */
	private string $title;

	/**
	 * The text to parse.
	 *
	 * @var string
	 */
	private string $text;

	/**
	 * Get title to parse.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return $this->title;
	}

	/**
	 * Set title to parse.
	 *
	 * @param $title
	 * @return void
	 */
	public function set_title( $title ): void {
		$this->title = $title;
	}

	/**
	 * Get text to parse.
	 *
	 * @return string
	 */
	public function get_text(): string {
		return $this->text;
	}

	/**
	 * Set text to parse.
	 *
	 * @param $text
	 * @return void
	 */
	public function set_text( $text ): void {
		$this->text = $text;
	}

	/**
	 * Get name of the parser.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Set the object-id which is parsed.
	 *
	 * @param int $object_id
	 * @return void
	 */
	public function set_object_id( int $object_id ): void {
		$this->object_id = $object_id;
	}

	/**
	 * Get the object-id which is parsed.
	 *
	 * @return int
	 */
	public function get_object_id(): int {
		return $this->object_id;
	}


	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $object
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $object ): bool {
		return true;
	}

	/**
	 * Return edit link for elementor-object.
	 *
	 * @return string
	 */
	public function get_edit_link(): string {
		return get_edit_post_link( $this->get_object_id(), 'edit' );
	}

	/**
	 * Get language switch for page builder.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function get_language_switch(): void {
        // bail if user has no translation capabilities.
        if( false === current_user_can( 'edit_el_translate') ) {
            return;
        }

		// get the post-object.
		$post_object = new Post_Object( get_the_ID() );

		// get the original-object.
		$original_post_object = new Post_Object( $this->get_object_id() );

		// get language of this object.
		$language_array = $original_post_object->get_language();

		// collect all active languages.
		$languages = array_merge( $language_array, Languages::get_instance()->get_active_languages() );

		// show translate-button if this is not the original post and an API is active.
		if( $post_object->get_id() !== $original_post_object->get_id() ) {

			// check if API for automatic translation is active.
			$api_obj = Apis::get_instance()->get_active_api();
			if( false !== $api_obj ) {

				// link to get automatic translation via API.
				$do_translation = $post_object->get_translation_via_api_link();

                // get quota-state for this object.
                $quota_state = $post_object->get_quota_state( $api_obj );

                // get object type name.
				$object_type = $post_object->get_type();

                // do not show translate-button if characters to translate are more than quota characters.
                if( 'above_limit' === $quota_state['status'] ) {
	                ?>
                    <p class="alert">
		                <?php
                            /* translators: %1$s will be replaced by the API-title */
                            echo sprintf( __( 'There would be %1$d characters to translate in this %2$s. That is more than the %3$d available in quota.', 'easy-language' ), esc_html($quota_state['chars_count']), esc_html($object_type), absint($quota_state['quota_rest']) );
		                ?>
                    </p>
	                <?php
                }
                elseif( 'exceeded' !== $quota_state['status'] ) {
                    // output.
                    ?>
                    <p>
                        <?php
                        /* translators: %1$d will be replaced by the amount of characters in this page/post, %2$s will be replaced by the name of this page-type (post or page)  */
                        echo wp_kses_post(sprintf(__('There would be %1$d characters translated in this %2$s.', 'easy-language'), esc_html($quota_state['chars_count']), esc_html($object_type) ));
                        ?>
                    </p>
                    <p><a href="<?php echo esc_url($do_translation); ?>" class="button button-secondary easy-language-translate-object" data-id="<?php echo absint($post_object->get_id()); ?>">
                        <?php
                            /* translators: %1$s will be replaced by the API-title */
                            echo sprintf(__( 'Translate via %1$s', 'easy-language' ), esc_html($api_obj->get_title() ));
                            if( $quota_state['quota_percent'] > apply_filters( 'easy_language_quota_percent', 0.8 ) ) {
                                echo '<span class="dashicons dashicons-info-outline" title="'.esc_attr( sprintf( __('Quota for the used API is used for %f percent!', 'easy-language' ), $quota_state['quota_percent'] ) ).'"></span>';
                            }
                        ?>
                        </a></p>
                    <?php
                }
                else {
                    ?>
                        <p class="alert">
                            <?php
                                /* translators: %1$s will be replaced by the API-title */
                                echo sprintf( __( 'No quota for automatic translation with %1$s available.', 'easy-language' ), esc_html($api_obj->get_title()) );
                            ?>
                        </p>
                    <?php
                }
			}
			elseif( current_user_can('manage_options')) {
                // output.
				?>
				<p>
					<?php
						echo esc_html__('No translation-API active.', 'easy-language');
                        ?> <a href="<?php echo esc_url(Helper::get_settings_page_url()); ?>"><span class="dashicons dashicons-admin-tools"></span></a><?php
					?>
				</p>
				<?php
			}
		}

		// loop through the languages to show them as selection.
		if( !empty($languages) ) {
			?><table><?php
			foreach ( $languages as $language_code => $settings ) {
				// set link to add translation for this language.
				$link = $original_post_object->get_translate_link( $language_code );
				/* translators: %1$s will be replaced by the language title */
				$link_title = __('Add translation in %1$s', 'easy-language');
				$link_content = '<span class="dashicons dashicons-plus"></span>';

				// get translated post for this language (if it is not the original).
				if( empty($settings['url']) ) {
					$link = $original_post_object->get_page_builder()->get_edit_link();
					$link_title = __( 'Original Content in %1$s', 'easy-language');
					$link_content = '<span class="dashicons dashicons-admin-home"></span>';
				}
				else {
					$query = array(
						'post_type' => get_post_type( get_the_ID() ),
						'post_status' => 'any',
						'meta_query' => array(
							'relation' => 'AND',
							array(
								'key' => 'easy_language_translation_original_id',
								'value' => $this->get_object_id(),
								'compare' => '='
							),
							array(
								'key' => 'easy_language_translation_language',
								'value' => $language_code,
								'compare' => '='
							)
						),
						'fields' => 'ids',
						'posts_per_page' => 1,
						'do_not_use_easy_language_filter' => true,
						'suppress_filters' => true
					);
					$results = new WP_Query($query);
					if( 1 === $results->post_count ) {
						$post_obj = new Post_Object( $results->posts[0] );
						$link = $post_obj->get_page_builder()->get_edit_link();
						/* translators: %1$s will be replaced by the language title */
						$link_title = __('Edit translation in %1$s', 'easy-language');
						$link_content = '<span class="dashicons dashicons-edit"></span>';
					}
				}

				// output.
				?><tr><th><?php echo $settings['label']; ?></th><td><a href="<?php echo esc_url($link); ?>" title="<?php echo esc_attr( sprintf( $link_title, $settings['label'] ) ); ?>"><?php echo $link_content; ?></a></td></tr><?php
			}
			?></table><?php
		}
	}

	/**
	 * Return whether this pagebuilder plugin is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return false;
	}

	/**
	 * Prevent translate-option in frontend.
	 *
	 * @return bool
	 */
	public function hide_translate_menu_in_frontend(): bool {
		return false;
	}

	/**
	 * Replace original title with translation.
	 *
	 * @param string $original_complete
	 * @param string $translated_part
	 *
	 * @return string
	 * @noinspection PhpUnused
	 */
	public function get_title_with_translations( string $original_complete, string $translated_part ): string {
		return str_replace( $this->get_title(), $translated_part, $original_complete );
	}
}
