<?php
/**
 * File for base functions for each parser-object.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

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
	 * @param string $title The title to parse.
	 * @return void
	 */
	public function set_title( string $title ): void {
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
	 * @param string $text The text to parse.
	 * @return void
	 */
	public function set_text( string $text ): void {
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
	 * @param int $object_id The object-id which contents are parsed.
	 *
	 * @return void
	 * @noinspection PhpUnused
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
	 * @param Post_Object $post_object The object which is parsed.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		return $post_object->is_simplifiable() || $post_object->is_simplified();
	}

	/**
	 * Return edit link for pagebuilder-object.
	 *
	 * @return string
	 */
	public function get_edit_link(): string {
		// get the edit-link by object-id.
		$link = get_edit_post_link( $this->get_object_id(), 'edit' );

		// return empty string if link does not exist.
		if ( is_null( $link ) ) {
			return '';
		}

		// return link if it does exist.
		return $link;
	}

	/**
	 * Get language switch for page builder.
	 *
	 * @param int $post_id The requested post_id.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function get_language_switch( int $post_id = 0 ): void {
		// bail if user has no simplification capabilities.
		if ( false === current_user_can( 'edit_el_simplifier' ) ) {
			return;
		}

		// get the post_id.
		if ( 0 === $post_id ) {
			$requested_post_id = get_the_ID();

			// bail if requested post ID is not known.
			if ( ! is_int( $requested_post_id ) ) {
				return;
			}

			// use the requested post ID.
			$post_id = $requested_post_id;
		}

		// get the post-object.
		$post_object = new Post_Object( $post_id );

		// get the original-object.
		$original_post_object = new Post_Object( $this->get_object_id() );

		// get language of this object.
		$language_array = $original_post_object->get_language();

		// collect all active languages.
		$languages = array_merge( $language_array, Languages::get_instance()->get_active_languages() );

		// show translate-button if this is not the original post and an API is active.
		if ( $post_object->get_id() !== $original_post_object->get_id() ) {

			// check if API for automatic simplification is active.
			$api_obj = Apis::get_instance()->get_active_api();
			if ( false !== $api_obj ) {

				// link to get automatic simplification via API.
				$do_simplification = $post_object->get_simplification_via_api_link();

				// get quota-state for this object.
				$quota_state = $post_object->get_quota_state( $api_obj );

				// do not show simplify-button if characters to simplify are more than quota characters.
				if ( 'above_limit' === $quota_state['status'] && $api_obj->is_configured() ) {
					?>
					<p class="alert">
						<?php
							/* translators: %1$s will be replaced by the API-title */
							printf( esc_html__( 'There would be %1$d characters to simplify in this %2$s. That is more than the %3$d available in quota.', 'easy-language' ), esc_html( $quota_state['chars_count'] ), esc_html( $post_object->get_type_name() ), absint( $quota_state['quota_rest'] ) );
						?>
					</p>
					<?php
				} elseif ( 'above_entry_limit' === $quota_state['status'] && $api_obj->is_configured() ) {
					?>
					<p>
						<?php
						/* translators: %1$s will be replaced by the API-title */
						printf( esc_html__( 'This %1$s contains more texts than %2$s would simplify in one loop. Simplify via API will not be possible.', 'easy-language' ), esc_html( $post_object->get_type_name() ), esc_html( $api_obj->get_title() ) );
						?>
					</p>
					<?php
					if ( 1 === absint( get_option( 'easy_language_automatic_simplification_enabled', 0 ) ) ) {
						?>
						<label><input type="checkbox" name="automatic_mode_prevented" data-id="<?php echo absint( $post_object->get_id() ); ?>" class="easy-language-automatic-simplification-prevention" value="1"
								<?php
								if ( $post_object->is_automatic_mode_prevented() ) {
									?>
									checked="checked"<?php } ?>><?php echo esc_html__( 'Not automatic simplified', 'easy-language' ); ?></label>
						<?php
					}
				} elseif ( 'above_text_limit' === $quota_state['status'] && $api_obj->is_configured() ) {
					?>
					<p>
						<?php
							/* translators: %1$s will be replaced by the API-title */
							printf( esc_html__( 'One or more texts are to long to simplify them with %1$s.', 'easy-language' ), esc_html( $api_obj->get_title() ) );
						?>
					</p>
					<?php
				} elseif ( 'exceeded' !== $quota_state['status'] && $api_obj->is_configured() ) {
					?>
					<p>
						<?php
						/* translators: %1$d will be replaced by the amount of characters in this page/post, %2$s will be replaced by the name of this page-type (post or page)  */
						echo wp_kses_post( sprintf( __( 'There would be %1$d characters translated in this %2$s.', 'easy-language' ), esc_html( $quota_state['chars_count'] ), esc_html( $post_object->get_type_name() ) ) );
						?>
					</p>
					<?php
					/* translators: %1$s will be replaced by tne object-type-name (e.g. post oder page), %2$s will be replaced by the API-name */
					$title = sprintf( __( 'Simplify this %1$s with %2$s.', 'easy-language' ), esc_html( $post_object->get_type_name() ), esc_html( $api_obj->get_title() ) );

					// set config for dialog.
					$dialog_config = array(
						'title'   => __( 'Simplify texts', 'easy-language' ),
						'texts'   => array(
							'<p>' . __( '<strong>Are you sure you want to simplify these texts via API?</strong><br>Hint: this could cause costs with the API.', 'easy-language' ) . '</p>',
						),
						'buttons' => array(
							array(
								'action'  => 'easy_language_get_simplification( ' . $post_id . ', "' . $post_object->get_type() . '" );',
								'variant' => 'primary',
								'text'    => __( 'Yes', 'easy-language' ),
							),
							array(
								'action'  => 'closeDialog();',
								'variant' => 'secondary',
								'text'    => __( 'No', 'easy-language' ),
							),
						),
					);

					$dialog = wp_json_encode( $dialog_config );
					if ( ! $dialog ) {
						$dialog = '';
					}

					// get permalink.
					$permalink = get_permalink( $post_id );
					if( ! $permalink ) {
						$permalink = '';
					}

					?>
					<p><a href="<?php echo esc_url( $do_simplification ); ?>" class="button button-secondary easy-dialog-for-wordpress elementor-button" data-dialog="<?php echo esc_attr( $dialog ); ?>" data-id="<?php echo absint( $post_object->get_id() ); ?>" data-link="<?php echo esc_url( $permalink ); ?>" title="<?php echo esc_attr( $title ); ?>">
					<?php
					// @phpstan-ignore argument.type
							$min_percent = 0.8;
							/**
							 * Hook for minimal quota percent.
							 *
							 * @since 2.0.0 Available since 2.0.0.
							 *
							 * @param float $min_percent Minimal percent for quota warning.
							 */
							$min_percent = apply_filters( 'easy_language_quota_percent', $min_percent );

							/* translators: %1$s will be replaced by the API-title */
							printf( esc_html__( 'Simplify with %1$s.', 'easy-language' ), esc_html( $api_obj->get_title() ) );

					if ( $quota_state['quota_percent'] > $min_percent ) {
						/* translators: %1$d will be replaced by a percentage value between 0 and 100. */
						echo '<span class="dashicons dashicons-info-outline" title="' . esc_attr( sprintf( __( 'Quota for the used API is used for %1$d%%!', 'easy-language' ), $quota_state['quota_percent'] ) ) . '"></span>';
					}
					?>
						</a>
					</p>
					<?php
					if ( 1 === absint( get_option( 'easy_language_automatic_simplification_enabled', 0 ) ) ) {
						?>
							<label><input type="checkbox" name="automatic_mode_prevented" data-id="<?php echo absint( $post_object->get_id() ); ?>" class="easy-language-automatic-simplification-prevention" value="1"
																												<?php
																												if ( $post_object->is_automatic_mode_prevented() ) {
																													?>
								checked="checked"<?php } ?>><?php echo esc_html__( 'Not automatic simplified', 'easy-language' ); ?></label>
						<?php
					}
				} elseif ( $api_obj->is_configured() ) {
					?>
						<p class="alert">
							<?php
								/* translators: %1$s will be replaced by the API-title */
								printf( esc_html__( 'No quota for automatic simplification with %1$s available.', 'easy-language' ), esc_html( $api_obj->get_title() ) );
							?>
						</p>
					<?php
				} elseif ( $api_obj->has_settings() ) {
					?>
					<p class="alert">
						<?php
						/* translators: %1$s will be replaced by the API-title */
						printf( esc_html__( 'API %1$s not configured.', 'easy-language' ), esc_html( $api_obj->get_title() ) );
						if ( current_user_can( 'manage_options' ) ) {
							?>
								<a href="<?php echo esc_url( $api_obj->get_settings_url() ); ?>"><span class="dashicons dashicons-admin-tools"></span></a>
							<?php
						}
						?>
					</p>
					<?php
				}
			} elseif ( current_user_can( 'manage_options' ) ) {
				// output.
				?>
				<p>
					<?php
						echo esc_html__( 'No simplification-API active.', 'easy-language' );
					?>
					<a href="<?php echo esc_url( Helper::get_settings_page_url() ); ?>"><span class="dashicons dashicons-admin-tools"></span></a></p>
				<?php
			}
		}

		// get page builder.
		$page_builder_obj = $original_post_object->get_page_builder();

		// bail if page builder could no be loaded.
		if ( ! $page_builder_obj ) {
			return;
		}

		// loop through the languages to show them as selection.
		if ( ! empty( $languages ) ) {
			?>
			<table>
			<?php

			foreach ( $languages as $language_code => $settings ) {
				// set link to add simplification for this language.
				$link = $original_post_object->get_simplification_link( $language_code );
				/* translators: %1$s will be replaced by the language title */
				$link_title   = __( 'Add simplification in %1$s.', 'easy-language' );
				$link_content = '<span class="dashicons dashicons-plus"></span>';

				// get translated post for this language (if it is not the original).
				if ( empty( $settings['url'] ) ) {
					$link = $page_builder_obj->get_edit_link();
					/* translators: %1$s will be replaced by the page-title where the original content resides */
					$link_title   = __( 'Original content in %1$s.', 'easy-language' );
					$link_content = '<span class="dashicons dashicons-admin-home"></span>';
				} else {
					$query   = array(
						'post_type'                       => get_post_type( $post_object->get_id() ),
						'post_status'                     => 'any',
						'meta_query'                      => array(
							'relation' => 'AND',
							array(
								'key'     => 'easy_language_simplification_original_id',
								'value'   => $this->get_object_id(),
								'compare' => '=',
							),
							array(
								'key'     => 'easy_language_simplification_language',
								'value'   => $language_code,
								'compare' => '=',
							),
						),
						'fields'                          => 'ids',
						'posts_per_page'                  => 1,
						'do_not_use_easy_language_filter' => true,
						'suppress_filters'                => true,
					);
					$results = new WP_Query( $query );
					if ( 1 === $results->post_count ) {
						// get post as our own object.
						$post_obj = new Post_Object( absint( $results->posts[0] ) );

						// get page builder.
						$new_page_builder_obj = $post_obj->get_page_builder();

						// bail if page builder could not be loaded.
						if ( ! $new_page_builder_obj ) {
							continue;
						}

						// get link of this object.
						$link = $new_page_builder_obj->get_edit_link();

						/* translators: %1$s is the name of the language */
						$link_title   = __( 'Edit simplification in %1$s.', 'easy-language' );
						$link_content = '<span class="dashicons dashicons-edit"></span>';
					}
				}

				// output.
				?>
				<tr><th><?php echo esc_html( $settings['label'] ); ?></th><td><a href="<?php echo esc_url( $link ); ?>" title="<?php echo esc_attr( sprintf( $link_title, esc_html( $settings['label'] ) ) ); ?>"><?php echo wp_kses_post( $link_content ); ?></a></td></tr>
				<?php
			}
			?>
			</table>
			<?php
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
	 * Replace original title with simplification.
	 *
	 * @param string $original_complete Complete original content.
	 * @param string $translated_part The translated content.
	 *
	 * @return string
	 * @noinspection PhpUnused
	 */
	public function get_title_with_simplifications( string $original_complete, string $translated_part ): string {
		return str_replace( $this->get_title(), $translated_part, $original_complete );
	}

	/**
	 * Run no updates on object per default.
	 *
	 * @param Post_Object $post_object The object.
	 *
	 * @return void
	 */
	public function update_object( Post_Object $post_object ): void {}

	/**
	 * Return the parsed texts from pagebuilder.
	 *
	 * @return array<array<string,mixed>>
	 */
	public function get_parsed_texts(): array {
		return array();
	}

	/**
	 * Replace original text with translation.
	 *
	 * @param string $original_complete Complete original content.
	 * @param string $simplified_part The translated content.
	 *
	 * @return string
	 */
	public function get_text_with_simplifications( string $original_complete, string $simplified_part ): string {
		if ( empty( $simplified_part ) ) {
			return $original_complete;
		}
		return $original_complete;
	}
}
