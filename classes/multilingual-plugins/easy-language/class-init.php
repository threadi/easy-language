<?php
/**
 * File for initializing the easy-language-own simplifications.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

use easyLanguage\Api_Base;
use easyLanguage\Apis;
use easyLanguage\Base;
use easyLanguage\Helper;
use easyLanguage\Languages;
use easyLanguage\Multilingual_Plugins;
use easyLanguage\Multilingual_Plugins_Base;
use easyLanguage\Transients;
use WP_Admin_Bar;
use WP_Post;
use WP_Post_Type;
use WP_Query;
use WP_Screen;
use WP_Term;
use WP_User;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Initializer for this plugin.
 */
class Init extends Base implements Multilingual_Plugins_Base {
	/**
	 * Marker for foreign plugin (plugins which are supported by this plugin but not maintained).
	 *
	 * @var bool
	 */
	protected bool $foreign_plugin = false;

	/**
	 * Marker for API-support.
	 *
	 * @var bool
	 */
	protected bool $supports_apis = true;

	/**
	 * Name of this plugin.
	 *
	 * @var string
	 */
	protected string $name = 'easy-language';

	/**
	 * Title of this plugin.
	 *
	 * @var string
	 */
	protected string $title = 'Easy Language';

	/**
	 * Instance of this object.
	 *
	 * @var ?Init
	 */
	private static ?Init $instance = null;

	/**
	 * Constructor for Init-Handler.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Init {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// get our own rewrite-handler.
		$rewrite_obj = Rewrite::get_instance();
		$rewrite_obj->init( $this );

		// get our own switcher-handler.
		$switcher = Switcher::get_instance();
		$switcher->init( $this );

		// get our own texts-handler.
		$texts = Texts::get_instance();
		$texts->init( $this );

		// get our own pagebuilder-support-handler.
		$pagebuilder = Pagebuilder_Support::get_instance();
		$pagebuilder->init( $this );

		// include pagebuilder support.
		foreach ( glob( plugin_dir_path( EASY_LANGUAGE ) . 'classes/multilingual-plugins/easy-language/pagebuilder/*.php' ) as $filename ) {
			include $filename;
		}

		// misc hooks.
		add_action( 'update_option_WPLANG', array( $this, 'option_locale_changed' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 500 );

		// add settings.
		add_action( 'easy_language_settings_add_settings', array( $this, 'add_settings' ), 15 );

		// add settings tab.
		add_action( 'easy_language_settings_add_tab', array( $this, 'add_settings_tab' ), 15 );

		// add settings page.
		add_action( 'easy_language_settings_general_page', array( $this, 'add_settings_page' ) );

		// backend simplification-hooks to show or hide translated pages.
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'pre_get_posts', array( $this, 'hide_translated_posts' ) );
		add_filter( 'get_pages', array( $this, 'remove_simplified_pages' ) );

		// add ajax-actions hooks.
		add_action( 'wp_ajax_easy_language_run_simplification', array( $this, 'ajax_run_simplification' ) );
		add_action( 'wp_ajax_easy_language_get_info_simplification', array( $this, 'ajax_get_simplification_info' ) );
		add_action( 'wp_ajax_easy_language_run_data_deletion', array( $this, 'deletion_simplified_data' ) );
		add_action( 'wp_ajax_easy_language_get_info_delete_data', array( $this, 'get_info_about_deletion_of_simplified_data' ) );

		// embed files.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), PHP_INT_MAX );

		// misc hooks.
		add_filter( 'admin_body_class', array( $this, 'add_body_class' ) );
		add_action( 'wp_ajax_easy_language_dismiss_intro_step_2', array( $this, 'dismiss_intro_step_2' ) );
		add_action( 'current_screen', array( $this, 'screen_actions' ) );
	}

	/**
	 * Add language-columns for each supported post type.
	 *
	 * @return void
	 */
	public function admin_init(): void {
		// bail if support for our own languages is handled by other multilingual plugin.
		if ( Multilingual_Plugins::get_instance()->is_plugin_with_support_for_given_languages_enabled( $this->get_supported_languages() ) ) {
			return;
		}

		// get supported post-types and loop through them.
		foreach ( $this->get_supported_post_types() as $post_type => $enabled ) {
			// get the post type as object to get additional settings of it.
			$post_type_obj = get_post_type_object( $post_type );

			// go only further if the post-type is visible in backend.
			if ( false !== $post_type_obj->show_in_menu ) {
				add_filter( 'manage_' . $post_type . '_posts_columns', array( $this, 'add_post_type_columns' ) );
				add_action( 'manage_' . $post_type . '_posts_custom_column', array( $this, 'add_post_type_column_content' ), 10, 2 );
				add_filter( 'views_edit-' . $post_type, array( $this, 'add_post_type_view' ) );
			}
		}

		// add filter for changed posts.
		add_action( 'restrict_manage_posts', array( $this, 'add_posts_filter' ) );
		add_filter( 'parse_query', array( $this, 'posts_filter' ) );

		// support for nested pages: show translated pages as action-links.
		if ( Helper::is_plugin_active( 'wp-nested-pages/nestedpages.php' ) ) {
			add_filter( 'post_row_actions', array( $this, 'add_post_row_action' ), 10, 2 );
		}
	}

	/**
	 * Add one column for each enabled language in supported post-types.
	 *
	 * @param array $columns The columns of this post-table.
	 *
	 * @return array
	 */
	public function add_post_type_columns( array $columns ): array {
		// bail if we're looking at trash.
		$status = get_query_var( 'post_status' );

		// create new array for columns to get clean ordering.
		$new_columns          = array();
		$new_columns['cb']    = $columns['cb'];
		$new_columns['title'] = $columns['title'];

		// add only one column in trash-view.
		if ( 'trash' === $status ) {
			// create new array for columns to get clean ordering.
			$new_columns['easy-language'] = __( 'Used simplification', 'easy-language' );
			return array_merge( $new_columns, $columns );
		}

		// get actual supported languages.
		foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
			$new_columns[ 'easy-language-' . strtolower( $language_code ) ] = $settings['label'];
		}

		// return result.
		return array_merge( $new_columns, $columns );
	}

	/**
	 * Add content for the new added columns in post-types.
	 *
	 * @param string $column The column.
	 * @param int    $post_id The post-id.
	 *
	 * @return void
	 */
	public function add_post_type_column_content( string $column, int $post_id ): void {
		// get active API for automatic simplification.
		$api_obj = Apis::get_instance()->get_active_api();

		// get object of this post.
		$post_object = new Post_Object( $post_id );

		// show only the used language in trash.
		if ( 'trash' === get_post_status( $post_id ) && 'easy-language' === $column ) {
			// get the post-language.
			$language_array = $post_object->get_language();
			$language       = reset( $language_array );
			if ( ! empty( $language ) ) {
				// show title of the used language.
				echo esc_html( $language['label'] );
			}
			return;
		}

		// get actual supported languages.
		foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
			if ( 'easy-language-' . strtolower( $language_code ) === $column ) {
				// check if this object is already translated in this language.
				if ( false !== $post_object->is_translated_in_language( $language_code ) ) {
					// get the post-ID of the translated page.
					$translated_post_id = $post_object->get_translated_in_language( $language_code );

					// get page-builder of this object.
					$simplified_post_obj = new Post_Object( $translated_post_id );
					$page_builder        = $simplified_post_obj->get_page_builder();

					// get object type name.
					$object_type_name = Helper::get_objekt_type_name( $simplified_post_obj );

					// do not show anything if the used page builder plugin is not available.
					if ( false === $page_builder->is_active() ) {
						/* translators: %1$s will be replaced by the name of the PageBuilder (like Elementor) */
						echo '<span class="dashicons dashicons-lightbulb" title="' . esc_attr( sprintf( __( 'Used page builder %1$s not available', 'easy-language' ), $page_builder->get_name() ) ) . '"></span>';

						// get link to delete this simplification.
						$delete_translation = get_delete_post_link( $translated_post_id );

						// show link to delete the translated post.
						/* translators: %1$s is the name of the language */
						echo '<a href="' . esc_url( $delete_translation ) . '" class="dashicons dashicons-trash easy-language-trash" title="' . esc_attr( sprintf( __( 'Delete simplification in %1$s.', 'easy-language' ), esc_html( $settings['label'] ) ) ) . '">&nbsp;</a>';
						continue;
					}

					// get page-builder-specific edit-link if user has capability for it.
					if ( current_user_can( 'edit_el_simplifier' ) ) {
						$edit_simplification = $page_builder->get_edit_link();

						// show link to add simplification for this language.
						/* translators: %1$s is the name of the language */
						echo '<a href="' . esc_url( $edit_simplification ) . '" class="dashicons dashicons-edit" title="' . esc_attr( sprintf( __( 'Edit simplification in %1$s.', 'easy-language' ), esc_html( $settings['label'] ) ) ) . '">&nbsp;</a>';
					}

					// create link to run simplification of this page via API (if available).
					if ( false !== $api_obj && current_user_can( 'edit_el_simplifier' ) ) {
						// get quota-state of this object.
						$quota_status = $simplified_post_obj->get_quota_state( $api_obj );

						// only if it is ok show translate-icon and API is configured.
						if ( 'ok' === $quota_status['status'] && $api_obj->is_configured() ) {
							// get link to add simplification.
							$do_simplification = $simplified_post_obj->get_simplification_via_api_link();

							// show link to simplify this page via api.
							/* translators: %1$s is the name of the language, %2$s is the name of the used API, %3$s will be the API-title */
							echo '<a href="' . esc_url( $do_simplification ) . '" class="dashicons dashicons-translation easy-language-translate-object" data-id="' . absint( $simplified_post_obj->get_id() ) . '" data-link="' . esc_url( get_permalink( $translated_post_id ) ) . '" title="' . esc_attr( sprintf( __( 'Simplify this %1$s in %2$s with %3$s.', 'easy-language' ), esc_html( $object_type_name ), esc_html( $settings['label'] ), esc_html( $api_obj->get_title() ) ) ) . '">&nbsp;</a>';
						} elseif ( $api_obj->is_configured() ) {
							// show simple not clickable icon if API is configured but no quota available.
							/* translators: %1$s will be replaced by the object name (like "page"), %2$s will be replaced by the API name (like SUMM AI), %3$s will be replaced by the API-title */
							echo '<span class="dashicons dashicons-translation" title="' . esc_attr( sprintf( __( 'Not enough quota to simplify this %1$s in %2$s with %3$s.', 'easy-language' ), esc_html( $object_type_name ), esc_html( $settings['label'] ), esc_html( $api_obj->get_title() ) ) ) . '">&nbsp;</span>';
						} elseif ( $api_obj->has_settings() ) {
							// show simple not clickable icon if API is not configured.
							/* translators: %1$s will be replaced by the API-title */
							echo '<span class="dashicons dashicons-translation" title="' . esc_attr( sprintf( __( 'API %1$s not configured.', 'easy-language' ), $api_obj->get_title() ) ) . '">&nbsp;</span>';
						}

						// show quota hint.
						$this->show_quota_hint( $api_obj );
					}

					// get link to view object in frontend.
					$show_link = get_permalink( $simplified_post_obj->get_id() );

					// show link to view object in frontend.
					echo '<a href="' . esc_url( $show_link ) . '" class="dashicons dashicons-admin-site-alt3" target="_blank" title="' . esc_attr( __( 'Show in fronted (opens new window)', 'easy-language' ) ) . '">&nbsp;</a>';

					// get link to delete this simplification if user has capability for it.
					if ( current_user_can( 'delete_el_simplifier' ) ) {
						$delete_simplification = get_delete_post_link( $translated_post_id );

						// show link to delete the translated post.
						/* translators: %1$s is the name of the language */
						echo '<a href="' . esc_url( $delete_simplification ) . '" class="dashicons dashicons-trash easy-language-trash" title="' . esc_attr( sprintf( __( 'Delete simplification in %1$s.', 'easy-language' ), $settings['label'] ) ) . '">&nbsp;</a>';
					}

					// show mark if content of original page has been changed.
					if ( $post_object->has_changed( $language_code ) && current_user_can( 'edit_el_simplifier' ) ) {
						echo '<span class="dashicons dashicons-image-rotate" title="' . esc_html__( 'Original content has been changed!', 'easy-language' ) . '"></span>';
					}
				} else {
					// create link to simplify this post.
					$create_simplification = $post_object->get_simplification_link( $language_code );

					// show link to add simplification for this language.
					/* translators: %1$s is the name of the language */
					echo '<a href="' . esc_url( $create_simplification ) . '" class="dashicons dashicons-plus" title="' . esc_attr( sprintf( esc_html__( 'Add simplification of %1$s.', 'easy-language' ), esc_html( $settings['label'] ) ) ) . '">&nbsp;</a>';
				}
			}
		}
	}

	/**
	 * Hide our translated objects in queries for actively supported post types in backend.
	 *
	 * @param WP_Query $query The query-object.
	 *
	 * @return void
	 */
	public function hide_translated_posts( WP_Query $query ): void {
		if ( is_admin() && '' === $query->get( 'do_not_use_easy_language_filter' ) && $query->get( 'post_status' ) !== 'trash' ) {
			// get our supported post-types.
			$post_types = $this->get_supported_post_types();

			// get requested post-types, if they are a post-type.
			$hide_translated_posts = false;
			if ( is_array( $query->get( 'post_type' ) ) ) {
				foreach ( $query->get( 'post_type' ) as $post_type ) {
					if ( ! empty( $post_types[ $post_type ] ) ) {
						$hide_translated_posts = true;
					}
				}
			} elseif ( ! empty( $post_types[ $query->get( 'post_type' ) ] ) ) {
					$hide_translated_posts = true;
			}
			if ( $hide_translated_posts ) {
				$query->set(
					'meta_query',
					array(
						array(
							'key'     => 'easy_language_simplification_original_id',
							'compare' => 'NOT EXISTS',
						),
					)
				);

				if ( isset( $_GET['lang'] ) ) {
					$query->set(
						'meta_query',
						array(
							'relation' => 'AND',
							array(
								'key'     => 'easy_language_simplified_in',
								'value'   => sanitize_text_field( wp_unslash( $_GET['lang'] ) ),
								'compare' => 'LIKE',
							),
							array(
								'key'     => 'easy_language_simplification_original_id',
								'compare' => 'NOT EXISTS',
							),
						)
					);
				}
			}
		}
	}

	/**
	 * If locale setting changed in WP, change the plugin-settings.
	 *
	 * @param string $old_value The old value of the changed option.
	 * @param string $value The new value of the changed option.
	 *
	 * @return void
	 */
	public function option_locale_changed( string $old_value, string $value ): void {
		// if new value is empty, it is the en_US-default.
		if ( empty( $value ) ) {
			$value = 'en_US';
		}

		// same for old_value.
		if ( empty( $old_value ) ) {
			$old_value = 'en_US';
		}

		// get actual setting for source languages.
		$languages = get_option( 'easy_language_source_languages', array() );

		// remove the old-value from list, if it exists there.
		if ( isset( $languages[ $old_value ] ) ) {
			unset( $languages[ $old_value ] );
		}

		// check if the new value is supported as source language by active APIs.
		$add     = false;
		$api_obj = Apis::get_instance()->get_active_api();
		if ( false !== $api_obj ) {
			$source_languages = $api_obj->get_supported_source_languages();
			if ( ! empty( $source_languages[ $value ] ) ) {
				$add = true;
			}
		}

		// add the new language as activate language.
		if ( false !== $add ) {
			$languages[ $value ] = '1';
		}

		// update resulting setting.
		update_option( 'easy_language_source_languages', $languages );
	}

	/**
	 * Add simplification-button in admin bar in frontend.
	 *
	 * @param WP_Admin_Bar $admin_bar The admin-bar-object.
	 *
	 * @return void
	 */
	public function admin_bar_menu( WP_Admin_Bar $admin_bar ): void {
		// do not show anything in wp-admin.
		if ( is_admin() ) {
			return;
		}

		// do not show if user has no capabilities for this.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// bail if support for our own languages is handled by other multilingual plugin.
		if ( Multilingual_Plugins::get_instance()->is_plugin_with_support_for_given_languages_enabled( $this->get_supported_languages() ) ) {
			return;
		}

		// get the active languages.
		$target_languages = Languages::get_instance()->get_active_languages();

		// if actual language is not supported as source language, do not show anything.
		if ( empty( $target_languages ) ) {
			return;
		}

		// get current object id.
		$object_id = get_queried_object_id();

		// get our own object for the requested object.
		$object = $this->get_object_by_wp_object( get_queried_object(), $object_id );
		if ( false === $object ) {
			return;
		}

		// bail if used pagebuilder prevent display of translate-option in frontend (e.g. to use its own options).
		if ( $object->get_page_builder() && false !== $object->get_page_builder()->hide_translate_menu_in_frontend() ) {
			return;
		}

		// bail if a multilingual-plugin is used which already translates this page.
		foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			if ( $plugin_obj->is_foreign_plugin() ) {
				return;
			}
		}

		// check if this object is a translated object.
		if ( $object->is_translated() ) {
			$object_id = $object->get_original_object_as_int();
			// get new object as base for the listing.
			$object = $this->get_object_by_wp_object( get_queried_object(), $object_id );
		}

		// bail if not object could be loaded.
		if( false === $object ) {
			return;
		}

		// bail if post type is not supported.
		if ( false === $this->is_post_type_supported( $object->get_type() ) ) {
			return;
		}

		// secure the menu ID.
		$id = 'easy-language-translate-button';

		// get object type name.
		$object_type_name = Helper::get_objekt_type_name( $object );

		// add not clickable main menu where all languages will be added as dropdown-items.
		$admin_bar->add_menu(
			array(
				'id'     => $id,
				'parent' => null,
				'group'  => null,
				/* translators: %1$s will be replaced by the object-name (like page or post) */
				'title'  => sprintf( __( 'Simplify this %1$s ', 'easy-language' ), esc_html( $object_type_name ) ),
				'href'   => '',
			)
		);

		// add sub-entry for each possible target language.
		foreach ( $target_languages as $language_code => $target_language ) {
			/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the language-name */
			$title = sprintf(__( 'Show this %1$s in %2$s ', 'easy-language' ), esc_html($object_type_name), esc_html($target_language['label']) );

			// check if this object is already translated in this language.
			if ( false !== $object->is_translated_in_language( $language_code ) ) {
				// generate link-target to default editor with language-marker.
				$simplified_post_object = new Post_Object( $object->get_translated_in_language( $language_code ) );
				$url                    = $simplified_post_object->get_page_builder()->get_edit_link();
			} else {
				// create link to generate a new simplification for this object.
				$url = $object->get_simplification_link( $language_code );
				/* translators: %1$s will be replaced by the object-name (e.g. page or post), %2$s will be replaced by the language-name */
				$title = sprintf(__( 'Create a simplification of this %1$s in %2$s ', 'easy-language' ), esc_html($object_type_name), esc_html($target_language['label']) );
			}

			// add language as possible simplification-target.
			$admin_bar->add_menu(
				array(
					'id'     => $id . '-' . $language_code,
					'parent' => $id,
					'title'  => $target_language['label'],
					'href'   => $url,
					'meta' => array(
						'title' => esc_html($title)
					)
				)
			);
		}
	}

	/**
	 * Initialize our main CLI-functions.
	 *
	 * @return void
	 * @noinspection PhpUndefinedClassInspection
	 * @noinspection PhpFullyQualifiedNameUsageInspection
	 */
	public function cli(): void {
		\WP_CLI::add_command( 'easy-language', 'easyLanguage\Multilingual_plugins\Easy_Language\Cli' );
	}

	/**
	 * Return supported post-types.
	 *
	 * @return array
	 */
	public function get_supported_post_types(): array {
		return get_option( 'easy_language_post_types', array() );
	}

	/**
	 * Return supported post-types.
	 *
	 * @param string $post_type The name of the requested post-type.
	 *
	 * @return bool
	 */
	public function is_post_type_supported( string $post_type ): bool {
		$post_types = $this->get_supported_post_types();
		return ! empty( $post_types[ $post_type ] );
	}

	/**
	 * Run on plugin-activation.
	 *
	 * @return void
	 */
	public function install(): void {
		// set supported post-types.
		if ( ! get_option( 'easy_language_post_types' ) ) {
			update_option(
				'easy_language_post_types',
				array(
					'post' => '1',
					'page' => '1',
				)
			);
		}

		// set deactivation state for objects.
		if ( ! get_option( 'easy_language_state_on_deactivation' ) ) {
			update_option( 'easy_language_state_on_deactivation', 'draft' );
		}

		// set API state for objects.
		if ( ! get_option( 'easy_language_state_on_api_change' ) ) {
			update_option( 'easy_language_state_on_api_change', 'draft' );
		}

		// set to generate permalinks.
		if ( ! get_option( 'easy_language_generate_permalink' ) ) {
			update_option( 'easy_language_generate_permalink', '1' );
		}

		// set language-switcher-mode.
		if ( ! get_option( 'easy_language_switcher_link' ) ) {
			update_option( 'easy_language_switcher_link', 'hide_not_translated' );
		}

		// set supported language to one matching the project-language.
		if ( ! get_option( 'easy_language_languages' ) ) {
			$languages = array();
			$api_obj   = Apis::get_instance()->get_api_by_name( 'no_api' );
			if ( false === $api_obj ) {
				$api_obj = Apis::get_instance()->get_available_apis()[0];
			}
			$mappings = $api_obj->get_mapping_languages();
			foreach ( $api_obj->get_supported_source_languages() as $source_language => $enabled ) {
				if ( ! empty( $mappings[ $source_language ] ) ) {
					foreach ( $mappings[ $source_language ] as $language ) {
						$languages[ $language ] = '1';
					}
				}
			}
			update_option( 'easy_language_languages', $languages );
		}

		// get post-type-names.
		$post_type_names = \easyLanguage\Init::get_instance()->get_post_type_names();

		// get our own translator-role.
		$translator_role = get_role( 'el_simplifier' );

		// get all translated, draft and marked objects in any supported language to set them to publish.
		foreach ( $this->get_supported_post_types() as $post_type => $enabled ) {
			$query   = array(
				'post_type'                       => $post_type,
				'posts_per_page'                  => -1,
				'post_status'                     => array( 'any', 'trash' ),
				'fields'                          => 'ids',
				'meta_query'                      => array(
					'relation' => 'AND',
					array(
						'key'     => 'easy_language_simplification_original_id',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'easy_language_simplification_state_changed_from',
						'compare' => 'EXISTS',
					),
				),
				'do_not_use_easy_language_filter' => true,
			);
			$results = new WP_Query( $query );
			foreach ( $results->posts as $post_id ) {
				// get state this post hav before.
				$post_state = get_post_meta( $post_id, 'easy_language_simplification_state_changed_from', true );

				// set the state.
				$query = array(
					'ID'          => $post_id,
					'post_status' => $post_state,
				);
				wp_update_post( $query );

				// remove marker.
				delete_post_meta( $post_id, 'easy_language_simplification_state_changed_from' );
			}

			// add cap for translator-role to edit this post-types.
			if ( ! empty( $post_type_names[ $post_type ] ) ) {
				$translator_role->add_cap( 'create_' . $post_type_names[ $post_type ] );
				$translator_role->add_cap( 'delete_' . $post_type_names[ $post_type ] );
				$translator_role->add_cap( 'delete_others_' . $post_type_names[ $post_type ] );
				$translator_role->add_cap( 'delete_published_' . $post_type_names[ $post_type ] );
				$translator_role->add_cap( 'delete_private_' . $post_type_names[ $post_type ] );
				$translator_role->add_cap( 'edit_' . $post_type_names[ $post_type ] );
				$translator_role->add_cap( 'edit_others_' . $post_type_names[ $post_type ] );
				$translator_role->add_cap( 'edit_published_' . $post_type_names[ $post_type ] );
				$translator_role->add_cap( 'publish_' . $post_type_names[ $post_type ] );
			}
		}

		// load language file.
		load_plugin_textdomain( 'easy-language', false, dirname( plugin_basename( EASY_LANGUAGE ) ) . '/languages' );

		// set transient for hint where to start.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_dismissible_days( 2 );
		$transient_obj->set_name( 'easy_language_intro_step_1' );
		/* translators: %1$s will be replaced by the URL for api settings-URL. */
		$transient_obj->set_message( sprintf( __( '<strong>You have installed Easy Language - nice and thank you!</strong> Now check the <a href="%1$s">API-settings</a>, select one and start simplifying the texts in your website in easy or plain language.', 'easy-language' ), esc_url( Helper::get_settings_page_url() ) ) );
		$transient_obj->set_type( 'hint' );
		$transient_obj->save();
	}

	/**
	 * Run on deactivation.
	 *
	 * @return void
	 */
	public function deactivation(): void {
		// get new state setting.
		$new_state_setting = get_option( 'easy_language_state_on_deactivation', 'draft' );

		// if it is not disabled, go further.
		if ( 'disabled' !== $new_state_setting ) {
			// get all translated objects in any supported language to set them to draft.
			foreach ( $this->get_supported_post_types() as $post_type => $enabled ) {
				$query   = array(
					'post_type'                       => $post_type,
					'posts_per_page'                  => -1,
					'post_status'                     => array( 'any', 'trash' ),
					'fields'                          => 'ids',
					'meta_query'                      => array(
						array(
							'key'     => 'easy_language_simplification_original_id',
							'compare' => 'EXISTS',
						),
					),
					'do_not_use_easy_language_filter' => true,
				);
				$results = new WP_Query( $query );
				foreach ( $results->posts as $post_id ) {
					// get actual state.
					$post_state = get_post_status( $post_id );

					// update post-state.
					$query = array(
						'ID'          => $post_id,
						'post_status' => $new_state_setting,
					);
					wp_update_post( $query );

					// save which state the post had before.
					update_post_meta( $post_id, 'easy_language_simplification_state_changed_from', $post_state );
				}
			}
		}
	}

	/**
	 * Run during uninstallation of this plugin.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		// remove translated contents.
		foreach ( DB::get_instance()->get_entries() as $entry ) {
			$entry->delete();
		}

		// remove all 'easy_language_text_language'-marker.
		$query = array(
			'post_status' => 'any',
			'meta_query' => array(
				array(
					'key' => 'easy_language_text_language',
					'compare' => 'EXISTS'
				)
			),
			'fields' => 'ids',
			'posts_per_page' => -1
		);
		$results = new WP_Query( $query );
		foreach( $results->posts as $post_id ) {
			delete_post_meta( $post_id, 'easy_language_text_language' );
		}

		// remove custom transients which are not set via Transient-object.
		delete_transient( 'easy_language_refresh_rewrite_rules' );

		// delete switcher for classic menu.
		$query    = array(
			'post_type'     => EASY_LANGUAGE_CPT_SWITCHER,
			'post_status'   => 'any',
			'post_per_page' => -1,
			'fields'        => 'ids',
		);
		$switcher = new WP_Query( $query );
		foreach ( $switcher->posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		// delete options.
		$options = array(
			'easy_language_post_types',
			'easy_language_languages',
			'easy_language_switcher_link',
			EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING,
			EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT,
			EASY_LANGUAGE_OPTION_SIMPLIFICATION_MAX,
			'easy_language_switcher_default',
			'easy_language_state_on_deactivation',
			'easy_language_state_on_api_change',
			'easy_language_generate_permalink',
			'easy_language_intro_step_2',
		);
		foreach ( $options as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Add settings for this plugin.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		/**
		 * Easy Language Section
		 */
		add_settings_section(
			'settings_section_easy_language',
			__( 'General Settings', 'easy-language' ),
			'__return_true',
			'easyLanguageEasyLanguagePage'
		);

		// get all actual post-types in this project.
		$post_types_array = array( 'post', 'page' );
		$post_types       = array();
		foreach ( $post_types_array as $post_type ) {
			$post_type_obj = get_post_type_object( $post_type );
			if ( false !== $post_type_obj->show_ui && false !== $post_type_obj->public && 'attachment' !== $post_type_obj->name ) {
				$post_types[ $post_type ] = array(
					'label' => $post_type_obj->label,
				);
			}
		}

		// Choose supported post-types.
		add_settings_field(
			'easy_language_post_types',
			__( 'Choose supported post-types', 'easy-language' ),
			'easy_language_admin_multiple_checkboxes_field',
			'easyLanguageEasyLanguagePage',
			'settings_section_easy_language',
			array(
				'label_for' => 'easy_language_post_types',
				'fieldId'   => 'easy_language_post_types',
				'options'   => apply_filters( 'easy_language_possible_post_types', $post_types ),
			)
		);
		register_setting( 'easyLanguageEasyLanguageFields', 'easy_language_post_types', array( 'sanitize_callback' => array( $this, 'validate_post_types' ) ) );

		add_settings_field(
			'easy_language_advanced_pro_hint',
			'',
			'easy_language_admin_advanced_pro_hint',
			'easyLanguageEasyLanguagePage',
			'settings_section_easy_language',
			array(
				'label_for' => 'easy_language_advanced_pro_hint',
				'fieldId'   => 'easy_language_advanced_pro_hint',
			)
		);

		// add additional settings after post-typs.
		do_action( 'easy_language_add_settings_after_post_types' );

		// get active api for readonly-marker depending on active api.
		$active_api = Apis::get_instance()->get_active_api();
		$readonly   = true;
		if ( $active_api && false === $active_api->has_settings() ) {
			$readonly = false;
		}

		// Choose supported languages for manuel simplifications.
		add_settings_field(
			'easy_language_languages',
			__( 'Choose languages', 'easy-language' ),
			'easy_language_admin_multiple_checkboxes_field',
			'easyLanguageEasyLanguagePage',
			'settings_section_easy_language',
			array(
				'label_for'   => 'easy_language_languages',
				'fieldId'     => 'easy_language_languages',
				/* translators: %1$s will be replaced by the settings-URL for the active API */
				'description' => ( $readonly && $active_api ) ? sprintf( __( 'Go to <a href="%1$s">API-settings</a> to choose the languages you want to use.', 'easy-language' ), esc_url( $active_api->get_settings_url() ) ) : __( 'Choose the language you want to use for simplifications of texts.', 'easy-language' ),
				'options'     => Languages::get_instance()->get_possible_target_languages(),
				'readonly'    => $readonly,
			)
		);
		register_setting( 'easyLanguageEasyLanguageFields', 'easy_language_languages', array( 'sanitize_callback' => array( $this, 'change_languages' ) ) );

		// Set object state on plugin deactivation.
		add_settings_field(
			'easy_language_state_on_deactivation',
			__( 'Set object state on plugin deactivation', 'easy-language' ),
			'easy_language_admin_select_field',
			'easyLanguageEasyLanguagePage',
			'settings_section_easy_language',
			array(
				'label_for'     => 'easy_language_state_on_deactivation',
				'fieldId'       => 'easy_language_state_on_deactivation',
				'description'   => __( 'If plugin is disabled your translated objects will get the state set here. If plugin is reactivated they will be set to their state before.<br><strong>Hint:</strong> During uninstallation all translated objects will be deleted regardless of the setting here.', 'easy-language' ),
				'values'        => array(
					'disabled' => __( 'Do not change anything', 'easy-language' ),
					'draft'    => __( 'Set to draft', 'easy-language' ),
					'trash'    => __( 'Set to trash', 'easy-language' ),
				),
				'disable_empty' => true,
			)
		);
		register_setting( 'easyLanguageEasyLanguageFields', 'easy_language_state_on_deactivation' );

		// Set object state on api change.
		add_settings_field(
			'easy_language_state_on_api_change',
			__( 'Set object state on API change', 'easy-language' ),
			'easy_language_admin_select_field',
			'easyLanguageEasyLanguagePage',
			'settings_section_easy_language',
			array(
				'label_for'     => 'easy_language_state_on_api_change',
				'fieldId'       => 'easy_language_state_on_api_change',
				'description'   => __( 'If the API is changed, set all objects of the former API to the state set here.', 'easy-language' ),
				'values'        => array(
					'disabled' => __( 'Do not change anything', 'easy-language' ),
					'draft'    => __( 'Set to draft', 'easy-language' ),
					'trash'    => __( 'Set to trash', 'easy-language' ),
				),
				'disable_empty' => true,
			)
		);
		register_setting( 'easyLanguageEasyLanguageFields', 'easy_language_state_on_api_change' );

		// Set if translated pages should have a generated permalink.
		add_settings_field(
			'easy_language_generate_permalink',
			__( 'Generate permalink for translated objects', 'easy-language' ),
			'easy_language_admin_checkbox_field',
			'easyLanguageEasyLanguagePage',
			'settings_section_easy_language',
			array(
				'label_for'   => 'easy_language_generate_permalink',
				'fieldId'     => 'easy_language_generate_permalink',
				'description' => __( 'If enabled an individual permalink will be generated from title after simplification of the title.', 'easy-language' ),
			)
		);
		register_setting( 'easyLanguageEasyLanguageFields', 'easy_language_generate_permalink' );

		/**
		 * Frontend Section
		 */
		add_settings_section(
			'settings_section_frontend',
			__( 'Frontend Settings', 'easy-language' ),
			'__return_true',
			'easyLanguageEasyLanguagePage'
		);

		// Choose link-mode for language-switcher.
		add_settings_field(
			'easy_language_switcher_link',
			__( 'Choose link-mode for language switcher', 'easy-language' ),
			'easy_language_admin_multiple_radio_field',
			'easyLanguageEasyLanguagePage',
			'settings_section_frontend',
			array(
				'label_for' => 'easy_language_switcher_link',
				'fieldId'   => 'easy_language_switcher_link',
				'options'   => array(
					'do_not_link'         => array(
						'label'       => __( 'Do not link translated pages', 'easy-language' ),
						'enabled'     => true,
						'description' => __( 'The links in the switcher will general link to the language-specific homepage.', 'easy-language' ),
					),
					'link_translated'     => array(
						'label'       => __( 'Link translated pages.', 'easy-language' ),
						'enabled'     => true,
						'description' => __( 'The Links in the switcher will link to the translated page. If a page is not translated, the link will target the language-specific homepage.', 'easy-language' ),
					),
					'hide_not_translated' => array(
						'label'       => __( 'Do not link not translated pages.', 'easy-language' ),
						'enabled'     => true,
						'description' => __( 'The Links in the switcher will link to the translated page. If a page is not translated, the link will not be visible.', 'easy-language' ),
					),
				),
			)
		);
		register_setting( 'easyLanguageEasyLanguageFields', 'easy_language_switcher_link' );
	}

	/**
	 * Add settings-tab for this plugin.
	 *
	 * @param string $tab The actually called tab.
	 *
	 * @return void
	 */
	public function add_settings_tab( string $tab ): void {
		// check active tab.
		$active_class = '';
		if ( 'general' === $tab ) {
			$active_class = ' nav-tab-active';
		}

		// output tab.
		echo '<a href="' . esc_url( helper::get_settings_page_url() ) . '&tab=general" class="nav-tab' . esc_attr( $active_class ) . '">' . esc_html__( 'General Settings', 'easy-language' ) . '</a>';
	}

	/**
	 * Add settings page for this plugin.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		// check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// output.
		?>
		<form method="POST" action="<?php echo esc_url( get_admin_url() ); ?>options.php">
			<?php
			settings_fields( 'easyLanguageEasyLanguageFields' );
			do_settings_sections( 'easyLanguageEasyLanguagePage' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Return languages this plugin would support.
	 *
	 * @return array
	 */
	public function get_supported_languages(): array {
		$settings_language = (array) get_option( 'easy_language_languages', array() );
		if ( empty( $settings_language ) ) {
			$settings_language = array();
		}
		return $settings_language;
	}

	/**
	 * Add row actions to show translate-options and -state per object for nested pages.
	 *
	 * @param array   $actions The possible actions for posts.
	 * @param WP_Post $post The post-object.
	 *
	 * @return array
	 */
	public function add_post_row_action( array $actions, WP_Post $post ): array {
		// get post-object.
		$post_obj = new Post_Object( $post->ID );

		// get pagebuilder.
		$page_builder = $post_obj->get_page_builder();

		// get actual supported languages.
		foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
			// check if this object is already translated in this language.
			if ( false !== $post_obj->is_translated_in_language( $language_code ) ) {

				// do not show anything if the used page builder plugin is not available.
				if ( false === $page_builder->is_active() ) {
					/* translators: %1$s will be replaced by the name of the PageBuilder (like Elementor) */
					echo '<span class="dashicons dashicons-lightbulb" title="' . sprintf( esc_html__( 'Used page builder %1$s not available', 'easy-language' ), esc_html( $page_builder->get_name() ) ) . '"></span>';
				} else {
					// create link to edit the simplification post.
					$edit_translation = $page_builder->get_edit_link();

					// show link to add simplification for this language.
					$actions[ 'easy-language-' . $language_code ] = '<a href="' . esc_url( $edit_translation ) . '"><i class="dashicons dashicons-edit"></i> ' . esc_html( $settings['label'] ) . '</a>';
				}
			} else {
				// create link to simplify this post.
				$create_translation = $post_obj->get_simplification_link( $language_code );

				// show link to add simplification for this language.
				$actions[ 'easy-language-' . $language_code ] = '<a href="' . esc_url( $create_translation ) . '"><i class="dashicons dashicons-plus"></i> ' . esc_html( $settings['label'] ) . '</a>';
			}
		}
		return $actions;
	}

	/**
	 * Get our own object for given WP-object.
	 *
	 * @param WP_Term|WP_User|WP_Post_Type|WP_Post|null $wp_object The WP-object.
	 * @param int                                       $id The ID of the WP-object.
	 *
	 * @return object|false
	 */
	public function get_object_by_wp_object( WP_Term|WP_User|WP_Post_Type|WP_Post|null $wp_object, int $id ): object|false {
		if ( is_null( $wp_object ) ) {
			return false;
		}
		return match ( get_class( $wp_object ) ) {
			'WP_Post' => new Post_Object( $id ),
			default => false,
		};
	}

	/**
	 * Add filter for our supported post-types to get changed contents.
	 *
	 * @return void
	 */
	public function add_posts_filter(): void {
		// get called post_type.
		$called_post_type = ( isset( $_GET['post_type'] ) ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : 'post';

		// get supported post-types.
		$post_types = $this->get_supported_post_types();
		foreach ( $post_types as $post_type => $enabled ) {
			if ( 1 === absint( $enabled ) && $post_type === $called_post_type ) {
				$selected_option = isset( $_GET['admin_filter_easy_language_changed'] ) ? sanitize_text_field( wp_unslash( $_GET['admin_filter_easy_language_changed'] ) ) : '';
				?>
				<!--suppress HtmlFormInputWithoutLabel -->
				<select name="admin_filter_easy_language_changed">
					<option value=""><?php echo esc_html__( 'Filter easy language', 'easy-language' ); ?></option>
					<option value="translated"<?php echo ( 'translated' === $selected_option ) ? ' selected="selected"' : ''; ?>><?php echo esc_html__( 'show translated content', 'easy-language' ); ?></option>
					<option value="not_translated"<?php echo ( 'not_translated' === $selected_option ) ? ' selected="selected"' : ''; ?>><?php echo esc_html__( 'show not translated content', 'easy-language' ); ?></option>
					<option value="changed"<?php echo ( 'changed' === $selected_option ) ? ' selected="selected"' : ''; ?>><?php echo esc_html__( 'show changed content', 'easy-language' ); ?></option>
					<option value="not_changed"<?php echo ( 'not_changed' === $selected_option ) ? ' selected="selected"' : ''; ?>><?php echo esc_html__( 'show not changed content', 'easy-language' ); ?></option>
				</select>
				<?php
			}
		}
	}

	/**
	 * Filter for translated content in table-list.
	 *
	 * @param WP_Query $query The query-object.
	 *
	 * @return void
	 * @noinspection SpellCheckingInspection
	 */
	public function posts_filter( WP_Query $query ): void {
		global $pagenow;

		// do not change anything if this is not the main query, our filter-var is not set or this is not edit.php.
		if ( empty( $_GET['admin_filter_easy_language_changed'] ) || false === $query->is_main_query() || 'edit.php' !== $pagenow ) {
			return;
		}

		// get called post-type.
		$called_post_type = ( isset( $_GET['post_type'] ) ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : 'post';

		// get supported post-types.
		$post_types = $this->get_supported_post_types();
		foreach ( $post_types as $post_type => $enabled ) {
			if ( 1 === absint( $enabled ) && $post_type === $called_post_type ) {
				remove_action( 'pre_get_posts', array( $this, 'hide_translated_objects' ) );
				switch ( sanitize_text_field( wp_unslash( $_GET['admin_filter_easy_language_changed'] ) ) ) {
					case 'translated':
						// get all objects WITH translation-objects.
						$query->set(
							'meta_query',
							array(
								array(
									'key'     => 'easy_language_simplified_in',
									'compare' => 'EXISTS',
								),
							)
						);
						break;
					case 'not_translated':
						// get all objects WITHOUT translation-objects.
						$query->set(
							'meta_query',
							array(
								'relation' => 'AND',
								array(
									'key'     => 'easy_language_simplified_in',
									'compare' => 'NOT EXISTS',
								),
								array(
									'key'     => 'easy_language_simplification_original_id',
									'compare' => 'NOT EXISTS',
								),
							)
						);
						break;
					case 'changed':
						// get all objects which has been changed its content.
						$meta_query = array(
							'relation' => 'OR',
						);
						foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
							$meta_query[] = array(
								'relation' => 'AND',
								array(
									'key'     => 'easy_language_' . $language_code . '_changed',
									'compare' => 'EXISTS',
								),
								array(
									'key'     => 'easy_language_simplified_in',
									'compare' => 'EXISTS',
								),
							);
						}
						$query->set( 'meta_query', array( $meta_query ) );
						break;
					case 'not_changed':
						// get all objects which has NOT been changed its content.
						$meta_query = array(
							'relation' => 'OR',
						);
						foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
							$meta_query[] = array(
								'relation' => 'AND',
								array(
									'key'     => 'easy_language_' . $language_code . '_changed',
									'compare' => 'NOT EXISTS',
								),
								array(
									'key'     => 'easy_language_simplified_in',
									'compare' => 'EXISTS',
								),
							);
						}
						$query->set( 'meta_query', array( $meta_query ) );
						break;
				}
			}
		}
	}

	/**
	 * Add our languages as filter-views in lists of supported post-types.
	 *
	 * @param array $views List of views.
	 *
	 * @return array
	 */
	public function add_post_type_view( array $views ): array {
		// get screen.
		$screen = get_current_screen();

		// get called post_type.
		$post_type = $screen->post_type;

		// loop through active languages and add them to the filter.
		foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
			// define filter-url.
			$url = add_query_arg(
				array(
					'post_type' => $post_type,
					'lang'      => $language_code,
				),
				get_admin_url() . 'edit.php'
			);

			// add the filter to the list.
			$views[ 'easy-language-' . $language_code ] = '<a href="' . esc_url( $url ) . '">' . esc_html( $settings['label'] ) . '</a>';
		}
		return $views;
	}

	/**
	 * Run translation of given object via AJAX.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function ajax_run_simplification(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-simplification-start-nonce', 'nonce' );

		// get api.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( false === $api_obj ) {
			// no api active => forward user.
			wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
			exit;
		}

		// get the post-id from request.
		$post_id = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0;

		if ( absint( $post_id ) > 0 ) {
			// run simplification of this object.
			$post_obj = new Post_Object( $post_id );
			$post_obj->process_simplifications( $api_obj->get_simplifications_obj(), $api_obj->get_active_language_mapping() );
		}

		// return nothing.
		wp_die();
	}

	/**
	 * Get info about running translation of given object via AJAX.
	 *
	 * Format: count-of-simplifications;count-of-total-simplifications;running-marker;result-as-text
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function ajax_get_simplification_info(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-simplification-get-nonce', 'nonce' );

		// get the post-id from request.
		$post_id = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0;

		if ( $post_id > 0 ) {
			// get object.
			$post_obj = new Post_Object( $post_id );

			// get running simplifications.
			$running_simplifications = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, array() );

			// get max value for running simplifications.
			$max_simplifications = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_MAX, array() );

			// get count value for running simplifications.
			$count_simplifications = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT, array() );

			// get result (if set).
			$results = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, array() );

			// collect return value.
			echo absint( $count_simplifications[ $post_obj->get_md5() ] ) . ';' . absint( $max_simplifications[ $post_obj->get_md5() ] ) . ';' . absint( $running_simplifications[ $post_obj->get_md5() ] ) . ';' . wp_kses_post( $results[ $post_obj->get_md5() ] ) . ';'  . get_permalink($post_id);
		}

		// return nothing.
		wp_die();
	}

	/**
	 * Remove simplified pages from get_pages-result.
	 *
	 * @param array $pages The list of pages.
	 *
	 * @return array
	 */
	public function remove_simplified_pages( array $pages ): array {
		foreach ( $pages as $index => $page ) {
			$post_obj = new Post_Object( $page->ID );
			if ( $post_obj->is_translated() ) {
				unset( $pages[ $index ] );
			}
		}
		return $pages;
	}

	/**
	 * Embed translation-related scripts, which are also used by some pageBuilders.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function get_simplifications_scripts(): void {
		// backend-simplifications-JS.
		wp_enqueue_script(
			'easy-language-simplifications',
			plugins_url( '/classes/multilingual-plugins/easy-language/admin/simplifications.js', EASY_LANGUAGE ),
			array( 'jquery' ),
			filemtime( plugin_dir_path( EASY_LANGUAGE ) . '/classes/multilingual-plugins/easy-language/admin/simplifications.js' ),
			true
		);

		// add php-vars to our simplifications-js-script.
		wp_localize_script(
			'easy-language-simplifications',
			'easyLanguageSimplificationJsVars',
			array(
				'ajax_url'                        => admin_url( 'admin-ajax.php' ),
				'label_simplification_is_running' => __( 'Simplification in progress', 'easy-language' ),
				'label_simplification_done'       => __( 'Simplification completed', 'easy-language' ),
				'label_open_link'                 => __( 'Open frontend', 'easy-language' ),
				'label_ok'                        => __( 'OK', 'easy-language' ),
				'txt_please_wait'                 => __( 'Please wait', 'easy-language' ),
				'run_simplification_nonce'        => wp_create_nonce( 'easy-language-simplification-start-nonce' ),
				'get_simplification_nonce'        => wp_create_nonce( 'easy-language-simplification-get-nonce' ),
				'txt_simplification_has_been_run' => __( 'Simplification has been run.', 'easy-language' ),
				'translate_confirmation_question' => __( 'Simplify the texts in this object?', 'easy-language' ),
			)
		);

		// embed necessary scripts for progressbar.
		wp_enqueue_script( 'jquery-ui-progressbar' );
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_style(
			'easy-language-jquery-ui-styles',
			trailingslashit( plugin_dir_url( EASY_LANGUAGE ) ) . 'libs/jquery-ui.smoothness.css',
			false,
			filemtime( trailingslashit( plugin_dir_path( EASY_LANGUAGE ) ) . 'libs/jquery-ui.smoothness.css' ),
			false
		);

		// add jquery-dirty script.
		wp_enqueue_script(
			'easy-language-admin-dirty-js',
			trailingslashit( plugin_dir_url( EASY_LANGUAGE ) ) . 'libs/jquery.dirty.js',
			array( 'jquery' ),
			filemtime( trailingslashit( plugin_dir_path( EASY_LANGUAGE ) ) . 'libs/jquery.dirty.js' ),
			true
		);
	}

	/**
	 * Embed our own scripts.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {
		// Enabled the pointer-scripts.
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );

		// backend-JS.
		wp_enqueue_script(
			'easy-language-plugin-admin',
			plugins_url( '/classes/multilingual-plugins/easy-language/admin/js.js', EASY_LANGUAGE ),
			array( 'jquery' ),
			filemtime( plugin_dir_path( EASY_LANGUAGE ) . '/classes/multilingual-plugins/easy-language/admin/js.js' ),
			true
		);

		// add php-vars to our backend-js-script.
		wp_localize_script(
			'easy-language-plugin-admin',
			'easyLanguagePluginJsVars',
			array(
				'ajax_url'                        => admin_url( 'admin-ajax.php' ),
				'delete_confirmation_question'    => __( 'Do you really want to delete this translated object?', 'easy-language' ),
				'dismiss_intro_nonce'             => wp_create_nonce( 'easy-language-dismiss-intro-step-2' ),
				/* translators: %1$s will be replaced by the path to the easy language icon */
				'intro_step_2'                    => sprintf( __( '<p><img src="%1$s" alt=""><strong>Start to simplify texts in your pages.</strong></p><p>Simply click here and choose which page you want to translate.</p>', 'easy-language' ), Helper::get_plugin_url() . '/gfx/easy-language-icon.png' ),
			)
		);
	}

	/**
	 * Validate the post-type-setting.
	 *
	 * @param array|null $values The possible values.
	 *
	 * @return array
	 */
	public function validate_post_types( array|null $values ): array {
		if ( is_null( $values ) ) {
			$values = array();
		}
		return $values;
	}

	/**
	 * Show quota hint in backend tables.
	 *
	 * @param Api_Base $api_obj The used API.
	 *
	 * @return void
	 */
	public function show_quota_hint( Api_Base $api_obj ): void {
		$quota_array   = $api_obj->get_quota();
		$quota_percent = 0;
		if ( ! empty( $quota_array['character_limit'] ) && $quota_array['character_limit'] > 0 ) {
			$quota_percent = absint( $quota_array['character_spent'] ) / absint( $quota_array['character_limit'] );
		}
		if ( $quota_percent > apply_filters( 'easy_language_quota_percent', 0.8 ) ) {
			/* translators: %1$d will be replaced by a percentage value between 0 and 100. */
			echo '<span class="dashicons dashicons-info-outline" title="' . esc_attr( sprintf( __( 'Quota for the used API is used for %1$d%%!', 'easy-language' ), round( (float) $quota_percent * 100 ) ) ) . '"></span>';
		}
	}

	/**
	 * Initialize the permalink refresh if languages changing.
	 *
	 * @param array|null $value The new language-strings as list.
	 *
	 * @return array
	 */
	public function change_languages( array|null $value ): array {
		$value = Helper::settings_validate_multiple_checkboxes( $value );
		Rewrite::get_instance()->set_refresh();
		return $value;
	}

	/**
	 * Return list of active languages this plugin is using atm.
	 *
	 * @return array
	 */
	public function get_active_languages(): array {
		return $this->get_supported_languages();
	}

	/**
	 * Add marker for free version on body-element
	 *
	 * @param string $classes List of classes as string.
	 * @return string
	 * @noinspection PhpUnused
	 */
	public function add_body_class( string $classes ): string {
		if ( 1 === absint( get_option( 'easy_language_intro_step_2', 0 ) ) ) {
			$classes .= 'easy-language-intro-step-2';
		}
		return $classes;
	}

	/**
	 * Dismiss intro step 2.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function dismiss_intro_step_2(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-dismiss-intro-step-2', 'nonce' );

		// hide pointer.
		update_option( 'easy_language_intro_step_2', 2 );

		// return nothing.
		wp_die();
	}

	/**
	 * Remove intro step 2 if one of our supported post type table views are loaded.
	 *
	 * @param WP_Screen $screen The requested wp backend screen.
	 *
	 * @return void
	 */
	public function screen_actions( WP_Screen $screen ): void {
		// delete the api change and intro hint if one of the supported post type pages is called.
		if ( ! empty( $this->get_supported_post_types()[ $screen->post_type ] ) ) {
			Transients::get_instance()->get_transient_by_name( 'easy_language_api_changed' )->delete();
			if( 1 === absint(get_option( 'easy_language_intro_step_2', 0 ) ) ) {
				update_option('easy_language_intro_step_2', 2 );
			}
		}
	}

	/**
	 * Return all texts.
	 *
	 * @return array
	 */
	public function get_objects_with_texts(): array {
		$object_list = array();
		$texts_obj   = Texts::get_instance();
		foreach ( $texts_obj->get_texts() as $text_obj ) {
			foreach ( $text_obj->get_objects() as $object ) {
				$object_list[ $object['object_id'] ] = new Post_Object( $object['object_id'] );
			}
		}
		return $object_list;
	}

	/**
	 * Delete the simplified data.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function deletion_simplified_data(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-delete-data-nonce', 'nonce' );

		// bail if deletion is already running.
		if( 1 === absint(get_option( EASY_LANGUAGE_OPTION_DELETION_RUNNING, 0 )) ) {
			return;
		}

		// set this as running.
		update_option( EASY_LANGUAGE_OPTION_DELETION_RUNNING, 1 );

		// get all simplified entries.
		$entries = DB::get_instance()->get_entries();

		// set max entry count.
		update_option( EASY_LANGUAGE_OPTION_DELETION_MAX, count($entries) );

		// set counter to 0.
		update_option( EASY_LANGUAGE_OPTION_DELETION_COUNT, 0 );

		// loop through the entries and delete them.
		foreach( $entries as $entry ) {
			$entry->delete();

			// update counter.
			update_option( EASY_LANGUAGE_OPTION_DELETION_COUNT, absint(get_option( update_option( EASY_LANGUAGE_OPTION_DELETION_COUNT, 0 ))) + 1 );
		}

		// remove running marker.
		delete_option( EASY_LANGUAGE_OPTION_DELETION_RUNNING );

		wp_die();
	}

	/**
	 * Return info about deletion of simplified data.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function get_info_about_deletion_of_simplified_data(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-get-delete-data-nonce', 'nonce' );

		// get running deletion.
		$running_deletion = get_option( EASY_LANGUAGE_OPTION_DELETION_RUNNING, 0 );

		// get max value for running deletion.
		$max_deletions = get_option( EASY_LANGUAGE_OPTION_DELETION_MAX, 0 );

		// get count value for running deletion.
		$count_deletion = get_option( EASY_LANGUAGE_OPTION_DELETION_COUNT, 0 );

		// collect return value.
		echo absint( $count_deletion ) . ';' . absint( $max_deletions ) . ';' . absint( $running_deletion );

		// return nothing more.
		wp_die();
	}
}
