<?php
/**
 * File for initializing the easy-language-own simplifications.
 *
 * @package easy-language
 */

namespace easyLanguage\EasyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Dependencies\easySettingsForWordPress\Fields\Checkbox;
use easyLanguage\Dependencies\easySettingsForWordPress\Fields\Checkboxes;
use easyLanguage\Dependencies\easySettingsForWordPress\Fields\Number;
use easyLanguage\Dependencies\easySettingsForWordPress\Fields\Radio;
use easyLanguage\Dependencies\easySettingsForWordPress\Fields\Select;
use easyLanguage\Dependencies\easySettingsForWordPress\Page;
use easyLanguage\Dependencies\easySettingsForWordPress\Settings;
use easyLanguage\Plugin\Api_Base;
use easyLanguage\Plugin\Apis;
use easyLanguage\Plugin\Base;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Languages;
use easyLanguage\Plugin\Log;
use easyLanguage\Plugin\Setup;
use easyLanguage\Plugin\ThirdPartySupport_Base;
use easyLanguage\Dependencies\easyTransientsForWordPress\Transients;
use WP_Admin_Bar;
use WP_Post;
use WP_Post_Type;
use WP_Query;
use WP_Role;
use WP_Screen;
use WP_Term;
use WP_User;

/**
 * Initializer for this plugin.
 */
class Init extends Base implements ThirdPartySupport_Base {
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
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// get our own rewrite-handler.
		Rewrite::get_instance()->init( $this );

		// get our own switcher-handler.
		Switcher::get_instance()->init( $this );

		// get our own texts-handler.
		Texts::get_instance()->init( $this );

		// get our own parsers-handler.
		Parsers::get_instance()->init();

		// get our own REST API-support-handler.
		Rest_Api::get_instance()->init();

		// add settings.
		add_action( 'init', array( $this, 'add_settings' ), 20 );

		// backend simplification-hooks to show or hide simplified pages.
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'pre_get_posts', array( $this, 'hide_simplified_posts' ) );
		add_filter( 'get_pages', array( $this, 'remove_simplified_pages' ) );

		// add ajax-actions hooks.
		add_action( 'wp_ajax_easy_language_add_simplification_object', array( $this, 'ajax_add_simplification' ) );
		add_action( 'wp_ajax_easy_language_run_simplification', array( $this, 'ajax_run_simplification' ) );
		add_action( 'wp_ajax_easy_language_run_data_deletion', array( $this, 'deletion_simplified_data' ) );
		add_action( 'wp_ajax_easy_language_get_info_delete_data', array( $this, 'get_info_about_deletion_of_simplified_data' ) );
		add_action( 'wp_ajax_easy_language_reset_processing_simplification', array( $this, 'ajax_reset_processing_simplification' ) );
		add_action( 'wp_ajax_easy_language_ignore_processing_simplification', array( $this, 'ajax_ignore_processing_simplification' ) );
		add_action( 'wp_ajax_easy_language_set_simplification_prevention_on_object', array( $this, 'ajax_set_simplification_prevention' ) );

		// embed files.
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ), PHP_INT_MAX );

		// schedule hook.
		add_action( 'easy_language_automatic_simplification', array( $this, 'run_automatic_simplification' ) );

		// misc hooks.
		add_filter( 'admin_body_class', array( $this, 'add_body_class' ) );
		add_action( 'wp_ajax_easy_language_dismiss_intro_step_2', array( $this, 'dismiss_intro_step_2' ) );
		add_action( 'current_screen', array( $this, 'screen_actions' ) );
		add_action( 'update_option_WPLANG', array( $this, 'option_locale_changed' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $this, 'add_simplification_button_in_admin_bar' ), 500 );
		add_action( 'admin_bar_menu', array( $this, 'show_simplification_process' ), 400 );
		add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );
		add_filter( 'site_status_tests', array( $this, 'add_site_status_test' ) );
		add_action( 'admin_action_easy_language_create_automatic_cron', array( $this, 'create_automatic_simplification_cron' ) );
		add_filter( 'easy_language_get_object', array( $this, 'get_post_object' ), 20, 2 );
		add_filter( 'easy_language_first_simplify_dialog', array( $this, 'change_first_simplify_dialog' ), 10, 3 );
		add_action( 'admin_action_easy_language_delete_text_for_simplification', array( $this, 'delete_text_for_simplification' ) );
		add_action( 'admin_action_easy_language_delete_all_to_simplified_texts', array( $this, 'delete_all_to_simplified_texts' ) );
	}

	/**
	 * Add language-columns for each supported post type.
	 *
	 * @return void
	 */
	public function admin_init(): void {
		// bail if setup has not been run.
		if ( ! Setup::get_instance()->is_completed() ) {
			return;
		}

		// get active API.
		$api_obj = Apis::get_instance()->get_active_api();

		// bail if no API is set.
		if ( ! $api_obj instanceof Api_Base ) {
			return;
		}

		// bail if actual WordPress-language is not in the supported source language list.
		$source_languages = Languages::get_instance()->get_possible_source_languages();
		if ( empty( $source_languages[ Helper::get_wp_lang() ] ) ) {
			return;
		}

		// get supported post-types and loop through them.
		foreach ( $this->get_supported_post_types() as $post_type => $enabled ) {
			// get the post type as object to get additional settings of it.
			$post_type_obj = get_post_type_object( $post_type );

			// bail if post type does not exist.
			if ( is_null( $post_type_obj ) ) {
				continue;
			}

			// bail if the post-type is not visible in backend.
			if ( false === $post_type_obj->show_in_menu ) {
				continue;
			}

			add_filter( 'manage_' . $post_type . '_posts_columns', array( $this, 'add_post_type_columns' ) );
			add_action( 'manage_' . $post_type . '_posts_custom_column', array( $this, 'add_post_type_column_content' ), 10, 2 );
			add_filter( 'views_edit-' . $post_type, array( $this, 'add_post_type_view' ) );
		}

		// add filter for changed posts.
		add_action( 'restrict_manage_posts', array( $this, 'add_posts_filter' ) );
		add_action( 'parse_query', array( $this, 'posts_filter' ) );

		// support for nested pages: show simplified pages as action-links.
		if ( Helper::is_plugin_active( 'wp-nested-pages/nestedpages.php' ) ) {
			add_filter( 'post_row_actions', array( $this, 'add_post_row_action' ), 10, 2 );
		}
	}

	/**
	 * Add one column for each enabled language in supported post-types.
	 *
	 * @param array<string,string> $columns The columns of this post-table.
	 *
	 * @return array<string,string>
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

		// bail if no API could be loaded.
		if ( ! $api_obj ) {
			return;
		}

		// get object of this post.
		$post_object = new Post_Object( $post_id );

		// show only the used language in trash.
		if ( 'easy-language' === $column && 'trash' === get_post_status( $post_id ) ) {
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
			// bail if this is not our column.
			if ( 'easy-language-' . strtolower( $language_code ) !== $column ) {
				continue;
			}

			// check if this object is already simplified in this language.
			if ( false !== $post_object->is_simplified_in_language( $language_code ) ) {
				// yes, it is simplified.

				// get the post-ID of the simplified object.
				$simplified_post_id = $post_object->get_simplification_in_language( $language_code );

				// get page-builder of this object.
				$simplified_post_obj = new Post_Object( $simplified_post_id );
				$page_builder        = $simplified_post_obj->get_page_builder();

				// get link to view object in frontend.
				$show_link = get_permalink( $simplified_post_obj->get_id() );
				if ( ! $show_link ) {
					$show_link = '';
				}

				// do not show anything if the used page builder plugin is not available.
				if ( $page_builder && false === $page_builder->is_active() ) {
					/* translators: %1$s will be replaced by the name of the PageBuilder (like Elementor) */
					echo '<span class="dashicons dashicons-warning easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_dialog_for_unavailable_page_builder( $post_object, $page_builder ) ) . '" title="' . esc_attr( sprintf( __( 'Used page builder %1$s not available', 'easy-language' ), esc_html( $page_builder->get_name() ) ) ) . '"></span>';

					// get link to delete this simplification.
					$delete_simplification = get_delete_post_link( $simplified_post_id );
					if ( ! is_string( $delete_simplification ) ) {
						$delete_simplification = '';
					}

					// show link to delete the simplified object.
					/* translators: %1$s is the name of the language */
					echo '<a href="' . esc_url( $delete_simplification ) . '" class="dashicons dashicons-trash easy-language-trash" title="' . esc_attr( sprintf( __( 'Delete simplification in %1$s.', 'easy-language' ), esc_html( $settings['label'] ) ) ) . '" data-object-type-name="' . esc_attr( $post_object->get_type_name() ) . '" data-title="' . esc_attr( $post_object->get_title() ) . '">&nbsp;</a>';
					continue;
				}

				// get page-builder-specific edit-link if user has capability for it.
				if ( $page_builder && current_user_can( 'edit_el_simplifier' ) ) {
					$edit_simplification = $page_builder->get_edit_link();

					// show link to add simplification for this language.
					/* translators: %1$s is the name of the language */
					echo '<a href="' . esc_url( $edit_simplification ) . '" class="dashicons dashicons-edit" title="' . esc_attr( sprintf( __( 'Edit simplification in %1$s.', 'easy-language' ), esc_html( $settings['label'] ) ) ) . '">&nbsp;</a>';
				}

				// create link to run simplification of this object via API (if available).
				if ( current_user_can( 'edit_el_simplifier' ) ) {
					// get quota-state of this object.
					$quota_status = $simplified_post_obj->get_quota_state( $api_obj );

					// only if it is ok and API is configured show simplify-icon.
					if ( 'ok' === $quota_status['status'] && $api_obj->is_configured() ) {
						// get link to add simplification.
						$do_simplification = $simplified_post_obj->get_simplification_via_api_link();

						// get link to delete this simplification.
						$post_permalinks = get_permalink( $simplified_post_id );
						if ( ! is_string( $post_permalinks ) ) {
							$post_permalinks = '';
						}

						// show link to simplify this object via api.
						/* translators: %1$s is the name of the language, %2$s is the name of the used API, %3$s will be the API-title */
						echo '<a href="' . esc_url( $do_simplification ) . '" class="dashicons dashicons-translation easy-language-translate-object" data-id="' . absint( $simplified_post_obj->get_id() ) . '" data-link="' . esc_url( $post_permalinks ) . '" data-object-type="' . esc_attr( $simplified_post_obj->get_type() ) . '" title="' . esc_attr( sprintf( __( 'Simplify this %1$s in %2$s with %3$s.', 'easy-language' ), esc_html( $post_object->get_type_name() ), esc_html( $settings['label'] ), esc_html( $api_obj->get_title() ) ) ) . '">&nbsp;</a>';
					} elseif ( 'above_entry_limit' === $quota_status['status'] && $api_obj->is_configured() ) {
						// show simple not clickable icon if API is configured but limit for texts is exceeded.
						/* translators: %1$s will be replaced by the object name (like "page"), %2$s will be replaced by the API name (like SUMM AI), %3$s will be replaced by the API-title */
						echo '<span class="dashicons dashicons-translation" title="' . esc_attr( sprintf( __( 'To many text-widgets in this %1$s for simplification with %2$s. The %3$s will be simplified in background automatically.', 'easy-language' ), esc_html( $post_object->get_type_name() ), esc_html( $api_obj->get_title() ), esc_html( $post_object->get_type_name() ) ) ) . '">&nbsp;</span>';
					} elseif ( $api_obj->is_configured() ) {
						// show simple not clickable icon if API is configured but no quota available.
						/* translators: %1$s will be replaced by the object name (like "page"), %2$s will be replaced by the API name (like SUMM AI), %3$s will be replaced by the API-title */
						echo '<span class="dashicons dashicons-translation" title="' . esc_attr( sprintf( __( 'Not enough quota to simplify this %1$s in %2$s with %3$s.', 'easy-language' ), esc_html( $post_object->get_type_name() ), esc_html( $settings['label'] ), esc_html( $api_obj->get_title() ) ) ) . '">&nbsp;</span>';
					} elseif ( $api_obj->has_settings() ) {
						// show simple not clickable icon if API is not configured.
						/* translators: %1$s will be replaced by the API-title */
						echo '<span class="dashicons dashicons-translation" title="' . esc_attr( sprintf( __( 'API %1$s not configured.', 'easy-language' ), $api_obj->get_title() ) ) . '">&nbsp;</span>';
					}

					// show quota hint.
					$this->show_quota_hint( $api_obj );
				}

				// show link to view object in frontend.
				echo '<a href="' . esc_url( $show_link ) . '" class="dashicons dashicons-admin-site-alt3" target="_blank" title="' . esc_attr( __( 'Show in fronted (opens new window)', 'easy-language' ) ) . '">&nbsp;</a>';

				// get link to delete this simplification if user has capability for it.
				if ( current_user_can( 'delete_el_simplifier' ) ) {
					$delete_simplification = get_delete_post_link( $simplified_post_id );
					if ( ! $delete_simplification ) {
						$delete_simplification = '';
					}

					// show link to delete the simplified post.
					/* translators: %1$s is the name of the language */
					echo '<a href="' . esc_url( $delete_simplification ) . '" class="dashicons dashicons-trash easy-language-trash" title="' . esc_attr( sprintf( __( 'Delete simplification in %1$s.', 'easy-language' ), $settings['label'] ) ) . '" data-object-type-name="' . esc_attr( $post_object->get_type_name() ) . '" data-title="' . esc_attr( $post_object->get_title() ) . '">&nbsp;</a>';
				}

				// show mark if automatic simplification for this object is prevented.
				if ( $simplified_post_obj->is_automatic_mode_prevented() && 1 === absint( get_option( 'easy_language_automatic_simplification_enabled', 1 ) ) ) {
					$dialog = array(
						/* translators: %1$s will be replaced by the object-title */
						'title'   => sprintf( __( 'Enable automatic simplification?', 'easy-language' ), esc_html( $post_object->get_title() ) ),
						'texts'   => array(
							/* translators: %1$s will be replaced by the API-title */
							'<p>' . sprintf( __( 'After activation the texts in this object will automatic simplified with the API %1$s.', 'easy-language' ), esc_html( $api_obj->get_title() ) ) . '</p>',
						),
						'buttons' => array(
							array(
								'action'  => 'easy_language_prevent_automatic_simplification( ' . absint( $simplified_post_obj->get_id() ) . ', "' . $simplified_post_obj->get_type() . '", false, null , "location.reload();" );',
								'variant' => 'primary',
								'text'    => __( 'Yes, enable it', 'easy-language' ),
							),
							array(
								'action'  => 'closeDialog();',
								'variant' => 'secondary',
								'text'    => __( 'No, let it disabled', 'easy-language' ),
							),
						),
					);
					echo '<span class="dashicons dashicons-admin-generic easy-language-automatic-simplification-prevented easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_dialog_for_attribute( $dialog ) ) . '" title="' . esc_attr__( 'Automatic simplification is prevented', 'easy-language' ) . '"></span>';
				}

				// show mark if content of original object has been changed.
				if ( $post_object->has_changed( $language_code ) && current_user_can( 'edit_el_simplifier' ) ) {
					echo '<span class="dashicons dashicons-image-rotate" title="' . esc_attr__( 'Original content has been changed!', 'easy-language' ) . '"></span>';
				}
			} else {
				// create link to simplify this post if used pagebuilder is active.
				$page_builder = $post_object->get_page_builder();

				// bail if page builder could not be loaded.
				if ( ! $page_builder ) {
					return;
				}

				// if detected page builder is active.
				if ( $page_builder->is_active() ) {
					// get simplification-URL.
					$create_simplification_link = $post_object->get_simplification_link( $language_code );

					// add warning before adding simplified object if used pagebuilder is unknown.
					$add_class                 = 'easy-dialog-for-wordpress';
					$show_page_builder_warning = false;
					if ( 'Undetected' === $page_builder->get_name() ) {
						$show_page_builder_warning = true;
						$add_class                 = 'easy-language-missing-pagebuilder-warning';
					}

					// define dialog for click on simplify-link.
					$dialog = array(
						/* translators: %1$s will be replaced by the object-title */
						'title'   => sprintf( __( 'Add simplification for %1$s', 'easy-language' ), esc_html( $post_object->get_title() ) ),
						'texts'   => array(
							/* translators: %1$s will be replaced by the object-type-name (e.g. post or page), %2$s will be replaced by the API-title */
							'<p>' . sprintf( __( 'Please decide how you want to proceed to simplify this %1$s.<br>Note that the use of the API %2$s may incur costs.', 'easy-language' ), esc_html( $post_object->get_type_name() ), esc_html( $api_obj->get_title() ) ) . '</p>',
						),
						'buttons' => array(
							array(
								'action'  => 'easy_language_add_simplification_object(' . absint( $post_id ) . ', "' . $post_object->get_type() . '", "' . esc_attr( $language_code ) . '", "auto", true );',
								'variant' => 'primary',
								/* translators: %1$s will be replaced by the API-title */
								'text'    => sprintf( __( 'Simplify now via %1$s', 'easy-language' ), esc_html( $api_obj->get_title() ) ),
							),
							array(
								'action'  => 'easy_language_add_simplification_object(' . absint( $post_id ) . ', "' . $post_object->get_type() . '", "' . esc_attr( $language_code ) . '", "manually", ' . $api_obj->is_configured() . ' );',
								'variant' => 'secondary',
								/* translators: %1$s will be replaced by the object-type-name (e.g. post or page) */
								'text'    => sprintf( __( 'Just add %1$s', 'easy-language' ), esc_html( $post_object->get_type_name() ) ),
							),
							array(
								'action' => 'closeDialog();',
								'text'   => __( 'Cancel', 'easy-language' ),
							),
						),
					);

					// add settings-button.
					if ( current_user_can( 'manage_options' ) ) {
						$dialog['buttons'][] = array(
							'href'      => esc_url( Helper::get_settings_page_url() ),
							'className' => 'dashicons dashicons-admin-generic',
							'text'      => '&nbsp;',
						);
					}

					/**
					 * Filter the dialog.
					 *
					 * @since 2.0.0 Available since 2.0.0.
					 *
					 * @param array<mixed> $dialog The dialog configuration.
					 * @param Api_Base $api_obj The used API as object.
					 * @param Post_Object $post_object The Post as object.
					 */
					$dialog = apply_filters( 'easy_language_first_simplify_dialog', $dialog, $api_obj, $post_object );

					// show link to add simplification for this language.
					/* translators: %1$s is the name of the language */
					echo '<a href="' . esc_url( $create_simplification_link ) . '" class="dashicons dashicons-plus ' . esc_attr( $add_class ) . '" data-dialog="' . esc_attr( Helper::get_dialog_for_attribute( $dialog ) ) . '" data-title="' . esc_attr( $post_object->get_title() ) . '" data-object-type-name="' . esc_attr( $post_object->get_type_name() ) . '" title="' . esc_attr( sprintf( esc_html__( 'Simplify this %1$s.', 'easy-language' ), esc_html( $settings['label'] ) ) ) . '">&nbsp;</a>';

					// if the detected pagebuilder is "undetected" show warning.
					if ( false !== $show_page_builder_warning ) {
						$dialog = array(
							/* translators: %1$s will be replaced by the object-title */
							'title'   => sprintf( __( 'Unknown page builder or Classic Editor', 'easy-language' ), esc_html( $post_object->get_title() ) ),
							'texts'   => array(
								/* translators: %1$s will be replaced by the API-title */
								'<p>' . sprintf( __( 'This %1$s has been edited with an unknown page builder.<br>This could also be the Classic Editor.<br>If this %1$s has been edited with another page builder, the plugin Easy Language does not support it atm.<br>Please <a href="%2$s" target="_blank">contact our support forum</a>.', 'easy-language' ), esc_html( $post_object->get_type_name() ), esc_url( Helper::get_plugin_support_url() ) ) . '</p>',
							),
							'buttons' => array(
								array(
									'action'  => 'closeDialog();',
									'variant' => 'primary',
									'text'    => __( 'OK', 'easy-language' ),
								),
							),
						);

						/* translators: %1$s is the name of the object (e.g. page or post) */
						echo '<span class="dashicons dashicons-warning easy-dialog-for-wordpress"  data-dialog="' . esc_attr( Helper::get_dialog_for_attribute( $dialog ) ) . '" title="' . esc_attr( sprintf( __( 'This %1$s has been edited with an unknown page builder or the classic editor', 'easy-language' ), esc_html( $post_object->get_type_name() ) ) ) . '"></span>';
					}
				} else {
					// otherwise should warning that the for this object used page builder is not active or not supported.
					/* translators: %1$s will be replaced by the name of the PageBuilder (like Elementor) */
					echo '<span class="dashicons dashicons-warning easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_dialog_for_unavailable_page_builder( $post_object, $page_builder ) ) . '" title="' . esc_attr( sprintf( __( 'Used page builder %1$s not available', 'easy-language' ), esc_html( $page_builder->get_name() ) ) ) . '"></span>';
				}
			}
		}
	}

	/**
	 * Hide our simplified objects in queries for actively supported post types in backend.
	 *
	 * @param WP_Query $query The query-object.
	 *
	 * @return void
	 */
	public function hide_simplified_posts( WP_Query $query ): void {
		if ( is_admin() && '' === $query->get( 'do_not_use_easy_language_filter' ) && $query->get( 'post_status' ) !== 'trash' ) {
			// get the active API.
			$api = Apis::get_instance()->get_active_api();

			// bail if no API is enabled.
			if ( ! $api instanceof Api_Base ) {
				return;
			}

			// get our supported post-types.
			$post_types = $this->get_supported_post_types();

			// get requested post-types, if they are a post-type.
			$hide_simplified_posts = false;
			if ( is_array( $query->get( 'post_type' ) ) ) {
				foreach ( $query->get( 'post_type' ) as $post_type ) {
					if ( ! empty( $post_types[ $post_type ] ) ) {
						$hide_simplified_posts = true;
					}
				}
			} elseif ( ! empty( $post_types[ $query->get( 'post_type' ) ] ) ) {
				$hide_simplified_posts = true;
			}
			if ( $hide_simplified_posts ) {
				$query->set(
					'meta_query',
					array(
						array(
							'key'     => 'easy_language_simplification_original_id',
							'compare' => 'NOT EXISTS',
						),
					)
				);

				// get language from request.
				$language = filter_input( INPUT_GET, 'lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				if ( is_null( $language ) ) {
					return;
				}

				// check if requested language is supported by our plugin.
				$languages = $api->get_active_target_languages();

				// bail if requested language is not supported by this API.
				if ( empty( $languages[ $language ] ) ) {
					return;
				}

				// change the request for post to show only them who are simplified in the given language.
				$query->set(
					'meta_query',
					array(
						'relation' => 'AND',
						array(
							'key'     => 'easy_language_simplified_in',
							'value'   => $language,
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

	/**
	 * If locale setting changed in WP, change the plugin-settings.
	 *
	 * @param string $old_value The old value of the changed option.
	 * @param string $new_value The new value of the changed option.
	 *
	 * @return void
	 */
	public function option_locale_changed( string $old_value, string $new_value ): void {
		// if new value is empty, use our fallback.
		if ( empty( $new_value ) ) {
			$new_value = EASY_LANGUAGE_LANGUAGE_EMERGENCY;
		}

		// same for old_value.
		if ( empty( $old_value ) ) {
			$old_value = EASY_LANGUAGE_LANGUAGE_EMERGENCY;
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
		if ( $api_obj instanceof Api_Base ) {
			$source_languages = $api_obj->get_supported_source_languages();
			if ( ! empty( $source_languages[ $new_value ] ) ) {
				$add = true;
			}
		}

		// add the new language as activate language.
		if ( false !== $add ) {
			$languages[ $new_value ] = '1';
		}

		// update resulting setting.
		update_option( 'easy_language_source_languages', $languages );

		// validate language support on API.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( $api_obj instanceof Api_Base ) {
			Helper::validate_language_support_on_api( $api_obj );
		}

		// update SUMM AI setting.
		$languages = array( Helper::get_wp_lang() => '1' );
		update_option( 'easy_language_summ_ai_source_languages', $languages );

		// Log event.
		/* translators: %1$s will be replaced by the old value, %2$s by the new one. */
		Log::get_instance()->add_log( sprintf( __( 'Locale in WordPress changed from %1$s to %2$s', 'easy-language' ), $old_value, $new_value ), 'success' );
	}

	/**
	 * Add simplification-button in admin bar in frontend.
	 *
	 * @param WP_Admin_Bar $admin_bar The admin-bar-object.
	 *
	 * @return void
	 */
	public function add_simplification_button_in_admin_bar( WP_Admin_Bar $admin_bar ): void {
		// do not show anything in wp-admin.
		if ( is_admin() ) {
			return;
		}

		// do not show if user has no capabilities for this.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// get active API.
		$api_obj = Apis::get_instance()->get_active_api();

		// bail if no API is set.
		if ( ! ( $api_obj instanceof Api_Base ) ) {
			return;
		}

		// bail if actual WordPress-language is not in the supported source language list.
		$source_languages = $api_obj->get_supported_source_languages();
		if ( empty( $source_languages[ Helper::get_wp_lang() ] ) ) {
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

		// check if this object is a simplified object.
		if ( $object->is_simplified() ) {
			$object_id = $object->get_original_object_as_int();
			// get new object as base for the listing.
			$object = $this->get_object_by_wp_object( get_queried_object(), $object_id );
		}

		// bail if not object could be loaded.
		if ( false === $object ) {
			return;
		}

		// bail if post type is not supported.
		if ( false === $this->is_post_type_supported( $object->get_type() ) ) {
			return;
		}

		// secure the menu ID.
		$id = 'easy-language-translate-button';

		// get object type name.
		$object_type_name = '';
		if ( $object instanceof Post_Object ) {
			$object_type_name = $object->get_type_name();
		}

		// add not clickable main menu where all languages will be added as dropdown-items.
		$admin_bar->add_menu(
			array(
				'id'     => $id,
				'parent' => null,
				'group'  => null,
				/* translators: %1$s will be replaced by the object-name (e.g. page oder post) */
				'title'  => sprintf( __( 'Simplify this %1$s', 'easy-language' ), esc_html( $object_type_name ) ),
				'href'   => '',
			)
		);

		// add sub-entry for each possible target language.
		Helper::generate_admin_bar_language_menu( $id, $admin_bar, $target_languages, $object, $object_type_name );
	}

	/**
	 * Initialize our main CLI-functions.
	 *
	 * @return void
	 * @noinspection PhpFullyQualifiedNameUsageInspection
	 */
	public function cli(): void {
		\WP_CLI::add_command( 'easy-language', 'easyLanguage\EasyLanguage\Cli' );
	}

	/**
	 * Return supported post-types.
	 *
	 * @return array<string>
	 */
	public function get_supported_post_types(): array {
		// get the setting.
		$supported_post_types = get_option( 'easy_language_post_types', array() );

		// if setting is empty, return empty array.
		if ( ! is_array( $supported_post_types ) ) {
			return array();
		}

		// return the list of supported post types.
		return $supported_post_types;
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

		// get our own simplifier-role.
		$translator_role = get_role( 'el_simplifier' );

		// get all simplified, draft and marked objects in any supported language to set them to publish.
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
			foreach ( $results->get_posts() as $post_id ) {
				// get its int value.
				$post_id = absint( $post_id );

				// get state this post hav before.
				$post_state = get_post_meta( $post_id, 'easy_language_simplification_state_changed_from', true );

				// set the state.
				$array = array(
					'ID'          => $post_id,
					'post_status' => $post_state,
				);
				wp_update_post( $array );

				// remove marker.
				delete_post_meta( $post_id, 'easy_language_simplification_state_changed_from' );
			}

			// get post-type-names.
			$post_type_names = \easyLanguage\Plugin\Init::get_instance()->get_post_type_names();

			// add cap for translator-role to edit this post-types.
			if ( ! empty( $post_type_names[ $post_type ] ) && $translator_role instanceof WP_Role ) {
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

		// enable automatic simplifications.
		if ( ! get_option( 'easy_language_automatic_simplification_enabled' ) ) {
			update_option( 'easy_language_automatic_simplification_enabled', 1 );
		}

		// set amount of simplification items per automatic run.
		if ( ! get_option( 'easy_language_automatic_item_count' ) ) {
			update_option( 'easy_language_automatic_item_count', 6 );
		}

		// set intervall for automatic simplifications.
		if ( ! get_option( 'easy_language_automatic_simplification' ) ) {
			update_option( 'easy_language_automatic_simplification', '10minutely' );
		}

		// check if automatic interval exist, if not create it.
		if ( ! wp_next_scheduled( 'easy_language_automatic_simplification' ) ) {
			// add it.
			wp_schedule_event( time(), get_option( 'easy_language_automatic_simplification', '10minutely' ), 'easy_language_automatic_simplification' );
		}

		// set db cache for icons.
		if ( ! get_option( 'easy_language_icons' ) ) {
			update_option( 'easy_language_icons', array() );
		}

		// Log event.
		Log::get_instance()->add_log( __( 'Plugin activated', 'easy-language' ), 'success' );

		// validate language support on API.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( $api_obj instanceof Api_Base ) {
			Helper::validate_language_support_on_api( $api_obj );
		}
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
			// get all simplified objects in any supported language to set them to draft.
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
				foreach ( $results->get_posts() as $post_id ) {
					// get its int value.
					$post_id = absint( $post_id );

					// get actual state.
					$post_state = get_post_status( $post_id );

					// update post-state.
					$array = array(
						'ID'          => $post_id,
						'post_status' => $new_state_setting,
					);
					wp_update_post( $array );

					// save which state the post had before.
					update_post_meta( $post_id, 'easy_language_simplification_state_changed_from', $post_state );
				}
			}
		}

		// Log event.
		Log::get_instance()->add_log( __( 'Plugin deactivated', 'easy-language' ), 'success' );
	}

	/**
	 * Run during uninstallation of this plugin.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		// remove all by any API simplified objects.
		foreach ( Apis::get_instance()->get_available_apis() as $api_object ) {
			foreach ( $api_object->get_simplified_post_type_objects() as $post_id ) {
				wp_delete_post( $post_id, true );
			}
		}

		// delete meta keys on parent objects.
		delete_post_meta_by_key( 'easy_language_simplified_in' );
		delete_post_meta_by_key( 'easy_language_text_language' );

		// remove simplified texts.
		foreach ( Db::get_instance()->get_entries() as $entry ) {
			$entry->delete();
		}

		// remove all 'easy_language_text_language'-marker.
		$query   = array(
			'post_status'    => 'any',
			'meta_query'     => array(
				array(
					'key'     => 'easy_language_text_language',
					'compare' => 'EXISTS',
				),
			),
			'fields'         => 'ids',
			'posts_per_page' => -1,
		);
		$results = new WP_Query( $query );
		foreach ( $results->get_posts() as $post_id ) {
			delete_post_meta( absint( $post_id ), 'easy_language_text_language' );
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
		foreach ( $switcher->get_posts() as $post_id ) {
			wp_delete_post( absint( $post_id ), true );
		}

		// delete options.
		$options = array(
			'easy_language_post_types',
			'easy_language_languages',
			'easy_language_switcher_link',
			EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING,
			EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT,
			EASY_LANGUAGE_OPTION_SIMPLIFICATION_MAX,
			EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS,
			'easy_language_switcher_default',
			'easy_language_state_on_deactivation',
			'easy_language_state_on_api_change',
			'easy_language_generate_permalink',
			'easy_language_intro_step_2',
			'easy_language_automatic_simplification',
			'easy_language_automatic_item_count',
			'easy_language_automatic_simplification_enabled',
			'easy_language_icons',
			EASY_LANGUAGE_OPTION_DELETION_COUNT,
			EASY_LANGUAGE_OPTION_DELETION_MAX,
			EASY_LANGUAGE_OPTION_DELETION_RUNNING,
			'easy_language_source_languages',
		);
		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// schedule deaktivieren.
		wp_clear_scheduled_hook( 'easy_language_automatic_simplification' );
	}

	/**
	 * Add settings for this plugin.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// get the settings object.
		$settings_obj = Settings::get_instance();

		// get the settings page.
		$settings_page = $settings_obj->get_page( 'easy_language_settings' );

		// bail if the page is not available.
		if( ! $settings_page instanceof Page ) {
			return;
		}

		// add tab.
		$general_tab = $settings_page->add_tab( 'general', 20 );
		$general_tab->set_title( __( 'General Settings', 'easy-language' ) );

		// add section.
		$general_main_section = $general_tab->add_section( 'general_main_section', 10 );
		$general_main_section->set_title( __( 'General Settings', 'easy-language' ) );

		// get all actual post-types in this project.
		$post_types_array = array( 'post', 'page' );
		$post_types       = array();
		foreach ( $post_types_array as $post_type ) {
			// get the post-type object.
			$post_type_obj = get_post_type_object( $post_type );

			// bail if post-type object could not be loaded.
			if ( ! $post_type_obj instanceof WP_Post_Type ) {
				continue;
			}

			// add label.
			if ( false !== $post_type_obj->show_ui && false !== $post_type_obj->public && 'attachment' !== $post_type_obj->name ) {
				$post_types[ $post_type ] = $post_type_obj->label;
			}
		}

		/**
		 * Filter possible post-types.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array<string,mixed> $post_types The list of possible post-types.
		 */
		$post_types = apply_filters( 'easy_language_possible_post_types', $post_types );

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_post_types' );
		$setting->set_section( $general_main_section );
		$setting->set_show_in_rest( true );
		$setting->set_type( 'array' );
		$setting->set_default( array( 'page', 'post' ) );
		$field = new Checkboxes();
		$field->set_title( __( 'Choose supported post-types', 'easy-language' ) );
		$field->set_options( $post_types );
		$field->set_sanitize_callback( array( $this, 'validate_post_types' ) );
		$setting->set_field( $field );

		// get active api for readonly-marker depending on active api.
		$active_api = Apis::get_instance()->get_active_api();
		$readonly   = true;
		if ( $active_api && false === $active_api->has_settings() ) {
			$readonly = false;
		}

		$languages = array();
		foreach( Languages::get_instance()->get_possible_target_languages( true ) as $key => $settings ) {
			$languages[ $key ] = $settings['label'];
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_languages' );
		$setting->set_section( $general_main_section );
		$setting->set_show_in_rest( true );
		$setting->set_type( 'array' );
		$setting->set_default( array() );
		$field = new Checkboxes();
		$field->set_title( __( 'Choose languages', 'easy-language' ) );
		/* translators: %1$s will be replaced by the settings-URL for the active API */
		$field->set_description( ( $readonly && $active_api ) ? sprintf( __( 'Go to <a href="%1$s">API-settings</a> to choose the languages you want to use.', 'easy-language' ), esc_url( $active_api->get_settings_url() ) ) : __( 'Choose the language you want to use for simplifications of texts.', 'easy-language' ) );
		$field->set_options( $languages );
		$field->set_readonly( $readonly );
		$field->set_sanitize_callback( array( $this, 'change_languages' ) );
		$setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_state_on_deactivation' );
		$setting->set_section( $general_main_section );
		$setting->set_show_in_rest( true );
		$setting->set_type( 'string' );
		$setting->set_default( array() );
		$field = new Select();
		$field->set_title( __( 'Set object state on plugin deactivation', 'easy-language' ) );
		$field->set_description( __( 'If the plugin is disabled, your simplified objects will get the state set here. If plugin is reactivated they will be set to their state before.<br><strong>Hint:</strong> During uninstallation all simplified objects will be deleted regardless of the setting here.', 'easy-language' ) );
		$field->set_options( array(
			'disabled' => __( 'Do not change anything', 'easy-language' ),
			'draft'    => __( 'Set to draft', 'easy-language' ),
			'trash'    => __( 'Set to trash', 'easy-language' ),
		) );
		$setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_state_on_api_change' );
		$setting->set_section( $general_main_section );
		$setting->set_show_in_rest( true );
		$setting->set_type( 'string' );
		$setting->set_default( array() );
		$field = new Select();
		$field->set_title( __( 'Set object state on API change', 'easy-language' ) );
		$field->set_description( __( 'If the API is changed, set all objects of the former API to the state set here.', 'easy-language' ) );
		$field->set_options( array(
			'disabled' => __( 'Do not change anything', 'easy-language' ),
			'draft'    => __( 'Set to draft', 'easy-language' ),
			'trash'    => __( 'Set to trash', 'easy-language' ),
		) );
		$setting->set_field( $field );

		// add setting.
		$permalink_setting = $settings_obj->add_setting( 'easy_language_generate_permalink' );
		$permalink_setting->set_section( $general_main_section );
		$permalink_setting->set_show_in_rest( true );
		$permalink_setting->set_type( 'integer' );
		$permalink_setting->set_default( 1 );
		$field = new Checkbox();
		$field->set_title( __( 'Generate permalink for translated objects', 'easy-language' ) );
		$field->set_description( __( 'If enabled an individual permalink will be generated from title after simplification of the title.', 'easy-language' ) );
		$permalink_setting->set_field( $field );

		// add section.
		$automatic_section = $general_tab->add_section( 'general_automatic_section', 20 );
		$automatic_section->set_title( __( 'Automatic Settings', 'easy-language' ) );

		// add setting.
		$automatic_simplification_setting = $settings_obj->add_setting( 'easy_language_automatic_simplification_enabled' );
		$automatic_simplification_setting->set_section( $automatic_section );
		$automatic_simplification_setting->set_show_in_rest( true );
		$automatic_simplification_setting->set_type( 'integer' );
		$automatic_simplification_setting->set_default( 1 );
		$field = new Checkbox();
		$field->set_title( __( 'Enable automatic simplifications', 'easy-language' ) );
		$field->set_description( __( 'If enabled open simplifications will be run automatically in the intervall set below.', 'easy-language' ) );
		$automatic_simplification_setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_automatic_item_count' );
		$setting->set_section( $automatic_section );
		$setting->set_show_in_rest( true );
		$setting->set_type( 'integer' );
		$setting->set_default( 6 );
		$field = new Number();
		$field->set_title( __( 'Number of items per run', 'easy-language' ) );
		$field->set_description( __( 'The amount of items per automatic simplification run.', 'easy-language' ) );
		$field->add_depend( $automatic_simplification_setting, 1 );
		$setting->set_field( $field );

		// get possible intervals.
		$intervals = array(); // TODO nur eigene Intervalle verwenden.
		foreach ( wp_get_schedules() as $name => $schedule ) {
			$intervals[ $name ] = $schedule['display'];
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_automatic_simplification' );
		$setting->set_section( $automatic_section );
		$setting->set_show_in_rest( true );
		$setting->set_type( 'string' );
		$setting->set_default( array() );
		$field = new Select();
		$field->set_title( __( 'Interval for automatic simplification', 'easy-language' ) );
		$field->set_description( __( 'Simplification are run automatically in this intervall.', 'easy-language' ) );
		$field->set_options( $intervals );
		$field->set_sanitize_callback( array( $this, 'set_automatic_interval' ) );
		$field->add_depend( $automatic_simplification_setting, 1 );
		$setting->set_field( $field );

		// add section.
		$frontend_section = $general_tab->add_section( 'settings_section_frontend', 30 );
		$frontend_section->set_title( __( 'Frontend Settings', 'easy-language' ) );

		// add setting.
		$setting = $settings_obj->add_setting( 'easy_language_switcher_link' );
		$setting->set_section( $frontend_section );
		$setting->set_show_in_rest( true );
		$setting->set_type( 'string' );
		$setting->set_default( array() );
		$field = new Radio();
		$field->set_title( __( 'Choose link-mode for language switcher', 'easy-language' ) );
		$field->set_options( array(
			'do_not_link'         => array(
				'label'       => __( 'do not link translated pages', 'easy-language' ),
				'description' => __( 'The links in the switcher will general link to the language-specific homepage.', 'easy-language' ),
			),
			'link_translated'     => array(
				'label'       => __( 'link translated pages', 'easy-language' ),
				'description' => __( 'The Links in the switcher will link to the translated page. If a page is not translated, the link will target the language-specific homepage.', 'easy-language' ),
			),
			'hide_not_translated' => array(
				'label'       => __( 'do not link not translated pages', 'easy-language' ),
				'description' => __( 'The Links in the switcher will link to the translated page. If a page is not translated, the link will not be visible.', 'easy-language' ),
			),
		) );
		$setting->set_field( $field );
	}

	/**
	 * Return languages this plugin would support.
	 *
	 * @return array<string,mixed>
	 */
	public function get_supported_languages(): array {
		$settings_language = (array) get_option( 'easy_language_languages', array() );
		if ( empty( $settings_language ) ) {
			$settings_language = array();
		}
		return $settings_language;
	}

	/**
	 * Add row actions to show simplification-options and -state per object for plugin nested pages.
	 *
	 * @param array<string> $actions The possible actions for posts.
	 * @param WP_Post       $post The post-object.
	 *
	 * @return array<string>
	 */
	public function add_post_row_action( array $actions, WP_Post $post ): array {
		// get post-object.
		$post_object = new Post_Object( $post->ID );

		// get pagebuilder.
		$page_builder = $post_object->get_page_builder();

		// bail if page builder is unknown.
		if ( ! $page_builder ) {
			return $actions;
		}

		// get active API.
		$api_obj = Apis::get_instance()->get_active_api();

		// bail if no API is active.
		if ( ! $api_obj ) {
			return $actions;
		}

		// get actual supported languages.
		foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
			// check if this object is already translated in this language.
			if ( false !== $post_object->is_simplified_in_language( $language_code ) ) {

				// do not show anything if the used page builder plugin is not available.
				if ( false === $page_builder->is_active() ) {
					/* translators: %1$s will be replaced by the name of the PageBuilder (like Elementor) */
					echo '<span class="dashicons dashicons-warning easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_dialog_for_unavailable_page_builder( $post_object, $page_builder ) ) . '" title="' . esc_attr( sprintf( esc_html__( 'Used page builder %1$s not available', 'easy-language' ), esc_html( $page_builder->get_name() ) ) ) . '"></span>';
				} else {
					// create link to edit the simplification post.
					$edit_translation = $page_builder->get_edit_link();

					// show link to add simplification for this language.
					$actions[ 'easy-language-' . $language_code ] = '<a href="' . esc_url( $edit_translation ) . '"><i class="dashicons dashicons-edit"></i> ' . esc_html( $settings['label'] ) . '</a>';
				}
			} else {
				// create link to simplify this post.
				$create_translation = $post_object->get_simplification_link( $language_code );

				// define dialog for click on simplify-link.
				$dialog = array(
					/* translators: %1$s will be replaced by the object-title */
					'title'   => sprintf( __( 'Add simplification for %1$s', 'easy-language' ), esc_html( $post_object->get_title() ) ),
					'texts'   => array(
						/* translators: %1$s will be replaced by the object-type-name (e.g. post or page), %2$s will be replaced by the API-title */
						'<p>' . sprintf( __( 'Please decide how you want to proceed to simplify this %1$s.<br>Note that the use of the API %2$s may incur costs.', 'easy-language' ), esc_html( $post_object->get_type_name() ), esc_html( $api_obj->get_title() ) ) . '</p>',
					),
					'buttons' => array(
						array(
							'action'  => 'easy_language_add_simplification_object(' . absint( $post_object->get_id() ) . ', "' . $post_object->get_type() . '", "' . esc_attr( $language_code ) . '", "auto", true );',
							'variant' => 'primary',
							/* translators: %1$s will be replaced by the API-title */
							'text'    => sprintf( __( 'Simplify now via %1$s', 'easy-language' ), esc_html( $api_obj->get_title() ) ),
						),
						array(
							'action'  => 'easy_language_add_simplification_object(' . absint( $post_object->get_id() ) . ', "' . $post_object->get_type() . '", "' . esc_attr( $language_code ) . '", "manually", ' . $api_obj->is_configured() . ' );',
							'variant' => 'secondary',
							/* translators: %1$s will be replaced by the object-type-name (e.g. post or page) */
							'text'    => sprintf( __( 'Just add %1$s', 'easy-language' ), esc_html( $post_object->get_type_name() ) ),
						),
						array(
							'action' => 'closeDialog();',
							'text'   => __( 'Cancel', 'easy-language' ),
						),
					),
				);

				/**
				 * Filter the dialog.
				 *
				 * @since 2.0.0 Available since 2.0.0.
				 *
				 * @param array $dialog The dialog configuration.
				 * @param Api_Base $api_obj The used API as object.
				 * @param Post_Object $post_object The Post as object.
				 */
				$dialog = apply_filters( 'easy_language_first_simplify_dialog', $dialog, $api_obj, $post_object );

				// show link to add simplification for this language.
				$actions[ 'easy-language-' . $language_code ] = '<a href="' . esc_url( $create_translation ) . '" class="easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_dialog_for_attribute( $dialog ) ) . '"><i class="dashicons dashicons-plus"></i> ' . esc_html( $settings['label'] ) . '</a>';
			}
		}

		// return resulting list of actions.
		return $actions;
	}

	/**
	 * Get our own object for given WP-object.
	 *
	 * @param array<int|string,mixed>|WP_Post|WP_Post_Type|WP_Term|WP_User|null $wp_object The WP-object.
	 * @param int                                                               $id The ID of the WP-object.
	 *
	 * @return Objects|false
	 */
	public function get_object_by_wp_object( array|WP_Post|WP_Post_Type|WP_Term|WP_User|null $wp_object, int $id ): Objects|false {
		// bail if original object is null.
		if ( is_null( $wp_object ) ) {
			return false;
		}

		// bail if original object is not an object.
		if ( ! is_object( $wp_object ) ) {
			return false;
		}

		// get the object.
		$object = match ( get_class( $wp_object ) ) {
			'WP_Post' => new Post_Object( $id ),
			default => false,
		};

		/**
		 * Filter the resulting easy language object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param Objects|false $object The easy language object to filter (e.g. WP_Post).
		 * @param array|WP_Post|WP_Post_Type|WP_Term|WP_User|null $wp_object The original WP object.
		 * @param int $id The ID of the object.
		 */
		return apply_filters( 'easy_language_get_object_by_wp_object', $object, $wp_object, $id );
	}

	/**
	 * Add filter for our supported post-types to get changed contents.
	 *
	 * @return void
	 */
	public function add_posts_filter(): void {
		// get called post_type.
		$called_post_type = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( is_null( $called_post_type ) ) {
			$called_post_type = 'post';
		}

		// get supported post-types.
		$post_types = $this->get_supported_post_types();
		foreach ( $post_types as $post_type => $enabled ) {
			if ( $post_type === $called_post_type && 1 === absint( $enabled ) ) {
				$selected_option = filter_input( INPUT_GET, 'admin_filter_easy_language_changed', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				if ( is_null( $selected_option ) ) {
					$selected_option = '';
				}
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
	 * Filter for simplified content in table-list.
	 *
	 * @param WP_Query $query The query-object.
	 *
	 * @return void
	 */
	public function posts_filter( WP_Query $query ): void {
		global $pagenow;

		$selected_option = filter_input( INPUT_GET, 'admin_filter_easy_language_changed', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( is_null( $selected_option ) ) {
			$selected_option = '';
		}

		// do not change anything if this is not the main query, our filter-var is not set or this is not edit.php.
		if ( 'edit.php' !== $pagenow || empty( $selected_option ) || false === $query->is_main_query() ) {
			return;
		}

		// get called post-type.
		$called_post_type = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( is_null( $called_post_type ) ) {
			$called_post_type = 'post';
		}

		// get supported post-types.
		$post_types = $this->get_supported_post_types();
		foreach ( $post_types as $post_type => $enabled ) {
			if ( $post_type === $called_post_type && 1 === absint( $enabled ) ) {
				remove_action( 'pre_get_posts', array( $this, 'hide_translated_objects' ) );
				switch ( $selected_option ) {
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
	 * @param array<string,string> $views List of views.
	 *
	 * @return array<string,string>
	 */
	public function add_post_type_view( array $views ): array {
		// bail if 'get_current_screen' is unknown.
		if ( ! function_exists( 'get_current_screen' ) ) {
			return $views;
		}

		// get screen.
		$screen = get_current_screen();

		// bail if screen could not be loaded.
		if ( ! $screen ) {
			return $views;
		}

		// get called post_type.
		$post_type = $screen->post_type;

		// get called post-type.
		$requested_lang = filter_input( INPUT_GET, 'lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( is_null( $requested_lang ) ) {
			$requested_lang = '';
		}

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

			// mark the filter as active if it is used.
			$class = '';
			if ( $language_code === $requested_lang ) {
				$class = 'current';
			}

			// add the filter to the list.
			$views[ 'easy-language-' . $language_code ] = '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $settings['label'] ) . '</a>';
		}
		return $views;
	}

	/**
	 * Run simplification via AJAX.
	 *
	 * Output-Format: count-of-simplifications;count-of-total-simplifications;running-marker;result-as-easy-dialog-for-wordpress-array
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function ajax_run_simplification(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-run-simplification-nonce', 'nonce' );

		// get the object-id from request.
		$object_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		// get the object-type from request.
		$object_type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 0;

		if ( ! empty( $object_type ) && absint( $object_id ) > 0 ) {
			// get object.
			$object = Helper::get_object( $object_id, $object_type );

			// bail if object is not a simplified object.
			if ( false === $object || ! $object->is_simplified() ) {
				// Log event.
				/* translators: %1$d will be replaced by an ID, %2$s by a type name. */
				Log::get_instance()->add_log( sprintf( __( 'Requested object %1$d (%2$s) is not intended to be simplified.', 'easy-language' ), $object_id, $object_type ), 'error' );

				// collect return array.
				$return = array(
					1,
					1,
					0,
					array(
						'className' => 'wp-dialog-error',
						'title'     => __( 'Error', 'easy-language' ),
						'texts'     => array(
							'<p>' . __( 'Requested object is not intended to be simplified!', 'easy-language' ) . '</p>',
						),
						'buttons'   => array(
							array(
								'action'  => 'closeDialog();',
								'variant' => 'primary',
								'text'    => __( 'OK', 'easy-language' ),
							),
						),
					),
				);
				wp_send_json( $return );
			}

			// get active API as object.
			$api_obj = Apis::get_instance()->get_active_api();

			// bail if no API is activated.
			if ( ! $api_obj instanceof Api_Base ) {
				// Log event.
				Log::get_instance()->add_log( __( 'No API active for simplification of texts.', 'easy-language' ), 'error' );

				// collect return array.
				$return = array(
					1,
					1,
					0,
					array(
						'className' => 'wp-dialog-error',
						'title'     => __( 'Error', 'easy-language' ),
						'texts'     => array(
							'<p>' . __( 'No API activated!', 'easy-language' ) . '</p>',
						),
						'buttons'   => array(
							array(
								'action'  => 'closeDialog();',
								'variant' => 'primary',
								'text'    => __( 'OK', 'easy-language' ),
							),
						),
					),
				);
				wp_send_json( $return );
			}

			// get info if this is a simplification-initialization.
			$initialization = isset( $_POST['initialization'] ) ? filter_var( wp_unslash( $_POST['initialization'] ), FILTER_VALIDATE_BOOLEAN ) : false;

			// run simplification of X text-entries in given object.
			$object->process_simplifications( $api_obj->get_simplifications_obj(), $api_obj->get_active_language_mapping(), absint( get_option( 'easy_language_api_text_limit_per_process', 1 ) ), $initialization );

			// get running simplifications.
			$running_simplifications = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, array() );

			// get max value for running simplifications.
			$max_simplifications = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_MAX, array() );

			// get count value for running simplifications.
			$count_simplifications = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_COUNT, array() );

			// get result (if set).
			$results = get_option( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS, array() );

			// check if all values are available and return general error if not.
			if ( ! isset( $count_simplifications[ $object->get_md5() ] ) || ! isset( $max_simplifications[ $object->get_md5() ] ) || ! isset( $running_simplifications[ $object->get_md5() ] ) || ! isset( $results[ $object->get_md5() ] ) ) {
				$error_message = sprintf( '<p>Error: Simplification failed with %1$s:<br>', esc_html( $api_obj->get_title() ) );
				if ( ! isset( $count_simplifications[ $object->get_md5() ] ) ) {
					$error_message .= '* counting failed<br>';
				}
				if ( ! isset( $max_simplifications[ $object->get_md5() ] ) ) {
					$error_message .= '* max value failed<br>';
				}
				if ( ! isset( $running_simplifications[ $object->get_md5() ] ) ) {
					$error_message .= '* running marker failed<br>';
				}
				if ( ! isset( $results[ $object->get_md5() ] ) ) {
					$error_message .= '* no result returned<br>';
				}

				// Log event.
				/* translators: %1$d will be replaced by an ID, %2$s by a type name. */
				Log::get_instance()->add_log( sprintf( __( 'Simplification of %1$d (%2$s) run in an error: ', 'easy-language' ), $object_id, $object_type ) . $error_message, 'error' );

				// collect return array for this error.
				$return = array(
					1,
					1,
					0,
					array(
						'className' => 'wp-dialog-error',
						'title'     => __( 'Error', 'easy-language' ),
						'texts'     => array(
							/* translators: %1$s will be replaced by the support-URL */
							'<p>' . sprintf( __( '<strong>This error should never happen!</strong> Please contact the <a href="%1$s" target="_blank">support forum (opens new window)</a> about the following error:', 'easy-language' ), esc_url( Helper::get_plugin_support_url() ) ) . '</p>',
							$error_message,
						),
						'buttons'   => array(
							array(
								'action'  => 'location.reload();',
								'variant' => 'primary',
								'text'    => __( 'OK', 'easy-language' ),
							),
						),
					),
				);

				// return results.
				wp_send_json( $return );
			}

			// collect return array.
			$return = array(
				absint( $count_simplifications[ $object->get_md5() ] ),
				absint( $max_simplifications[ $object->get_md5() ] ),
				absint( $running_simplifications[ $object->get_md5() ] ),
				$results[ $object->get_md5() ],
			);
			wp_send_json( $return );
		}

		// return general error.
		$return = array(
			1,
			1,
			0,
			array(
				'className' => 'wp-dialog-error',
				'title'     => __( 'Error', 'easy-language' ),
				'texts'     => array(
					'<p>' . __( 'Faulty simplification request!', 'easy-language' ) . '</p>',
				),
				'buttons'   => array(
					array(
						'action'  => 'closeDialog();',
						'variant' => 'primary',
						'text'    => __( 'OK', 'easy-language' ),
					),
				),
			),
		);
		wp_send_json( $return );
	}

	/**
	 * Remove simplified pages from get_pages-result.
	 *
	 * @param array<integer,WP_Post> $pages The list of pages.
	 *
	 * @return array<integer,WP_Post>
	 */
	public function remove_simplified_pages( array $pages ): array {
		foreach ( $pages as $index => $page ) {
			$post_obj = new Post_Object( $page->ID );
			if ( $post_obj->is_simplified() ) {
				unset( $pages[ $index ] );
			}
		}
		return $pages;
	}

	/**
	 * Embed simplification-related scripts, which are also used by some PageBuilders.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function get_simplifications_scripts(): void {
		// bail if actual language is not in possible source language list.
		$source_languages = Languages::get_instance()->get_possible_source_languages();
		if ( empty( $source_languages[ Helper::get_wp_lang() ] ) ) {
			return;
		}

		// backend-simplifications-JS.
		wp_enqueue_script(
			'easy-language-simplifications',
			plugins_url( '/admin/simplifications.js', EASY_LANGUAGE ),
			array( 'jquery', 'easy-dialog', 'wp-i18n' ),
			Helper::get_file_version( plugin_dir_path( EASY_LANGUAGE ) . '/admin/simplifications.js' ),
			true
		);

		// add php-vars to our simplifications-js-script.
		wp_localize_script(
			'easy-language-simplifications',
			'easyLanguageSimplificationJsVars',
			array(
				'ajax_url'                               => admin_url( 'admin-ajax.php' ),
				'add_simplification_nonce'               => wp_create_nonce( 'easy-language-add-simplification-nonce' ),
				'run_simplification_nonce'               => wp_create_nonce( 'easy-language-run-simplification-nonce' ),
				'set_simplification_prevention_nonce'    => wp_create_nonce( 'easy-language-set-simplification-prevention-nonce' ),
				'ignore_processing_simplification_nonce' => wp_create_nonce( 'easy-language-ignore-processing-simplification-nonce' ),
				'reset_processing_simplification_nonce'  => wp_create_nonce( 'easy-language-reset-processing-simplification-nonce' ),
			)
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				'easy-language-simplifications',
				'easy-language',
				plugin_dir_path( EASY_LANGUAGE ) . '/languages/'
			);
		}

		// add jquery-dirty script.
		wp_enqueue_script(
			'easy-language-admin-dirty-js',
			trailingslashit( plugin_dir_url( EASY_LANGUAGE ) ) . 'libs/jquery.dirty.js',
			array( 'jquery' ),
			Helper::get_file_version( trailingslashit( plugin_dir_path( EASY_LANGUAGE ) ) . 'libs/jquery.dirty.js' ),
			true
		);
	}

	/**
	 * Embed styles in frontend (for classic themes).
	 *
	 * @return void
	 */
	public function wp_enqueue_scripts(): void {
		wp_enqueue_style(
			'easy-language',
			plugins_url( '/classes/multilingual-plugins/easy-language/frontend/style.css', EASY_LANGUAGE ),
			array(),
			Helper::get_file_version( plugin_dir_path( EASY_LANGUAGE ) . '/classes/multilingual-plugins/easy-language/frontend/style.css' )
		);
	}

	/**
	 * Validate the post-type-setting.
	 *
	 * @param array<string>|null $values The possible values.
	 *
	 * @return array<string>
	 */
	public function validate_post_types( array|null $values ): array {
		if ( is_null( $values ) ) {
			$values = array();
		}
		return $values;
	}

	/**
	 * Show quota hint in backend tables for post-type-objects.
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

		$min_percent = 0.8;
		/**
		 * Hook for minimal quota percent.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param float $min_percent Minimal percent for quota warning.
		 */
		$min_percent = apply_filters( 'easy_language_quota_percent', $min_percent );

		if ( $quota_percent > $min_percent ) {
			/* translators: %1$d will be replaced by a percentage value between 0 and 100. */
			echo '<span class="dashicons dashicons-info-outline" title="' . esc_attr( sprintf( __( 'Quota for the used API is used for %1$d%%!', 'easy-language' ), round( (float) $quota_percent * 100 ) ) ) . '"></span>';
		}
	}

	/**
	 * Initialize the permalink refresh if languages changing.
	 *
	 * @param array<string>|null $value The new language-strings as list.
	 *
	 * @return array<string>|null
	 */
	public function change_languages( array|null $value ): array|null {
		$value = Helper::settings_validate_multiple_checkboxes( $value );
		Rewrite::get_instance()->set_refresh();
		return $value;
	}

	/**
	 * Return list of active languages this plugin is using atm.
	 *
	 * @return array<string,string>
	 */
	public function get_active_languages(): array {
		return $this->get_supported_languages();
	}

	/**
	 * Add marker on body-element.
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
	}

	/**
	 * Remove the intro step 2 if one of our supported post type table views are loaded.
	 *
	 * @param WP_Screen $screen The requested wp backend screen.
	 *
	 * @return void
	 */
	public function screen_actions( WP_Screen $screen ): void {
		// delete the api change and intro hint if one of the supported post type pages is called.
		if ( ! empty( $this->get_supported_post_types()[ $screen->post_type ] ) ) {
			Transients::get_instance()->get_transient_by_name( 'easy_language_api_changed' )->delete();
			if ( 1 === absint( get_option( 'easy_language_intro_step_2', 0 ) ) ) {
				update_option( 'easy_language_intro_step_2', 2 );
			}
		}
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
		if ( 1 === absint( get_option( EASY_LANGUAGE_OPTION_DELETION_RUNNING, 0 ) ) ) {
			// Log event.
			Log::get_instance()->add_log( __( 'Deletion of simplified texts is already running.', 'easy-language' ), 'error' );

			return;
		}

		// set this as running.
		update_option( EASY_LANGUAGE_OPTION_DELETION_RUNNING, 1 );

		// get all simplified text-entries.
		$entries = Db::get_instance()->get_entries();

		// set max entry count.
		update_option( EASY_LANGUAGE_OPTION_DELETION_MAX, count( $entries ) );

		// set counter to 0.
		update_option( EASY_LANGUAGE_OPTION_DELETION_COUNT, 0 );

		// loop through the entries and delete them.
		foreach ( $entries as $entry ) {
			$entry->delete();

			// update counter.
			update_option( EASY_LANGUAGE_OPTION_DELETION_COUNT, absint( get_option( update_option( EASY_LANGUAGE_OPTION_DELETION_COUNT, 0 ) ) ) + 1 ); // @phpstan-ignore argument.type

			// log this event.
			/* translators: %1$d wll be replaced by an ID. */
			Log::get_instance()->add_log( sprintf( __( 'Text %1$d deleted', 'easy-language' ), $entry->get_id() ), 'success' );
		}

		// remove running marker.
		delete_option( EASY_LANGUAGE_OPTION_DELETION_RUNNING );
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
	}

	/**
	 * Reset processing simplifications of given object to 'to_simplify'.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function ajax_reset_processing_simplification(): void {
		check_ajax_referer( 'easy-language-reset-processing-simplification-nonce', 'nonce' );

		// get the object-id from request.
		$object_id = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0;

		// get the object-type from request.
		$object_type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

		if ( $object_id > 0 ) {
			$filter = array(
				'object_id'   => $object_id,
				'object_type' => $object_type,
				'state'       => 'processing',
			);

			// get all simplified entries.
			$entries = Db::get_instance()->get_entries( $filter );

			// loop through the results and set the state.
			foreach ( $entries as $entry ) {
				$entry->set_state( 'to_simplify' );
			}
		}
	}

	/**
	 * Set processing simplifications of given object to 'ignore'.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function ajax_ignore_processing_simplification(): void {
		check_ajax_referer( 'easy-language-ignore-processing-simplification-nonce', 'nonce' );

		// get the object-id from request.
		$object_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		// get the object-type from request.
		$object_type = isset( $_POST['type'] ) ? absint( $_POST['type'] ) : '';

		if ( absint( $object_id ) > 0 ) {
			$filter = array(
				'object_id'   => $object_id,
				'object_type' => $object_type,
				'state'       => 'processing',
			);

			// get all simplified entries.
			$entries = Db::get_instance()->get_entries( $filter );

			// loop through the results and set the state.
			foreach ( $entries as $entry ) {
				$entry->set_state( 'ignore' );
			}
		}
	}

	/**
	 * Run automatic simplification (or via CLI).
	 *
	 * @return void
	 */
	public function run_automatic_simplification(): void {
		// get marker if CLI is used.
		$is_cli = Helper::is_cli();

		// bail if automatic simplification is disabled and this is not called via WP CLI.
		if ( ! $is_cli && 1 !== absint( get_option( 'easy_language_automatic_simplification_enabled', 0 ) ) ) {
			return;
		}

		// get the API.
		$api_obj = Apis::get_instance()->get_active_api();

		// bail if no API is active.
		if ( false === $api_obj ) {
			return;
		}

		// bail if active API is not configured.
		if ( false === $api_obj->is_configured() ) {
			return;
		}

		// get limit from settings.
		$limit = get_option( 'easy_language_automatic_item_count', 6 );

		// on WP CLI we are use an unlimited list of entries.
		if ( $is_cli ) {
			$limit = 0;
		}

		// get one text of a not locked, not prevented and actual not trashed object which should be simplified.
		$query               = $this->get_filter_for_entries_to_simplify();
		$query['not_locked'] = true;
		$entries             = Db::get_instance()->get_entries( $query, array(), $limit );

		// bail if no text could be found.
		if ( empty( $entries ) ) {
			return;
		}

		// loop through the results.
		foreach ( $entries as $entry ) {
			// get the simplification objects where this text is been used.
			$simplification_objects = $entry->get_objects();

			// bail if no objects could be found.
			if ( empty( $simplification_objects ) ) {
				continue;
			}

			// get object of the first one as we simplify this text only one time.
			$object = Helper::get_object( absint( $simplification_objects[0]['object_id'] ), $simplification_objects[0]['object_type'] );

			// bail if object could be loaded for this text.
			if ( ! $object instanceof Objects ) {
				continue;
			}

			// mark simplification for this object as running.
			$object->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, time() );

			// call translation for the text on the object.
			$object->process_simplification( $api_obj->get_simplifications_obj(), $api_obj->get_mapping_languages(), $entry );

			// remove running marker to mark end of process.
			$object->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, 0 );
		}
	}

	/**
	 * Add simplification of given object via AJAX.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function ajax_add_simplification(): void {
		check_ajax_referer( 'easy-language-add-simplification-nonce', 'nonce' );

		// define answer.
		$return = array(
			'status' => 'error',
		);

		// get active api.
		$api_object = Apis::get_instance()->get_active_api();

		// get id of original object.
		$original_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		// get type of original object.
		$original_type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

		// get language.
		$target_language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : '';

		if ( $original_id > 0 && ! empty( $target_language ) && $api_object && ! empty( $original_type ) ) {
			$object = Helper::get_object( $original_id, $original_type );
			if ( $object ) {
				// get the copied simplified object.
				$copy_obj = $object->add_simplification_object( $target_language, $api_object, false );
				if ( $copy_obj instanceof Post_Object ) {
					// get language for output its title.
					$languages = Languages::get_instance()->get_active_languages();

					// get simplification-list-url.
					$simplification_list_url = add_query_arg(
						array(
							'page'   => 'easy_language_settings',
							'tab'    => 'simplifications',
							'subtab' => 'to_simplify',
						),
						'options-general.php'
					);

					// collect return values.
					$return['status']                   = 'ok';
					$return['object_id']                = $copy_obj->get_id();
					$return['link']                     = get_permalink( $copy_obj->get_id() );
					$return['language']                 = $languages[ $target_language ]['label'];
					$return['object_type']              = $copy_obj->get_type();
					$return['object_type_name']         = $copy_obj->get_type_name();
					$return['title']                    = $copy_obj->get_title();
					$return['api_title']                = $api_object->get_title();
					$return['edit_link']                = $copy_obj->get_edit_link();
					$return['simplification_list_link'] = $simplification_list_url;
					$return['quota_state']              = $copy_obj->get_quota_state( $api_object );
				}
			}
		}

		// return result.
		wp_send_json( $return );
	}

	/**
	 * Show process of background-simplifications in admin-bar.
	 *
	 * @param WP_Admin_Bar $admin_bar The WP_Admin_Bar-object from WordPress.
	 *
	 * @return void
	 */
	public function show_simplification_process( WP_Admin_Bar $admin_bar ): void {
		// do not show if user has no capabilities for this.
		if ( ! current_user_can( 'edit_el_simplifier' ) ) {
			return;
		}

		// bail if automatic simplifications is disabled.
		if ( 1 !== absint( get_option( 'easy_language_automatic_simplification_enabled', 0 ) ) ) {
			return;
		}

		// get active API.
		$api_obj = Apis::get_instance()->get_active_api();

		// bail if no API is active.
		if ( false === $api_obj ) {
			return;
		}

		// bail if used API has no configuration set.
		if ( false === $api_obj->is_configured() ) {
			return;
		}

		// get _all_ items.
		$all_entries       = Db::get_instance()->get_entries();
		$all_entries_count = count( $all_entries );

		// bail if we have no entries at all.
		if ( 0 === $all_entries_count ) {
			return;
		}

		// get already simplified items.
		$simplified_entries       = Db::get_instance()->get_entries( array( 'has_simplification' => true ) );
		$simplified_entries_count = count( $simplified_entries );

		// get actual items to process the simplification in background.
		$entries_to_simplify       = Db::get_instance()->get_entries( $this->get_filter_for_entries_to_simplify() );
		$entries_to_simplify_count = count( $entries_to_simplify );
		$processed                 = ( ( $all_entries_count - $entries_to_simplify_count ) / $all_entries_count ) * 100;

		// get link to list of texts to simplify.
		$url = add_query_arg(
			array(
				'page'   => 'easy_language_settings',
				'tab'    => 'simplifications',
				'subtab' => 'to_simplify',
			),
			admin_url() . 'options-general.php'
		);

		// generate text depending on items to process.
		$admin_bar_text = __( 'Simplifications:', 'easy-language' ) . ' <progress value="' . absint( $processed ) . '" max="100"></progress>';
		/* translators: %1$d and %2$d will be replaced by digits, %3$s will be replaced by the API-title */
		$admin_bar_title = sprintf( __( '%1$d of %2$d texts simplified via %3$s', 'easy-language' ), absint( $simplified_entries_count ), absint( $simplified_entries_count + $entries_to_simplify_count ), esc_html( $api_obj->get_title() ) );

		// hide bar if no simplifications are to run.
		if ( 0 === $entries_to_simplify_count ) {
			return;
		}

		// add not clickable main menu where all languages will be added as dropdown-items.
		$admin_bar->add_menu(
			array(
				'id'     => 'easy-language-simplification-process',
				'parent' => null,
				'group'  => null,
				'title'  => $admin_bar_text,
				'href'   => esc_url( $url ),
				'meta'   => array(
					'title' => $admin_bar_title,
				),
			)
		);
	}

	/**
	 * Save the simplification prevention setting for an object.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function ajax_set_simplification_prevention(): void {
		check_ajax_referer( 'easy-language-set-simplification-prevention-nonce', 'nonce' );

		// define return value.
		$return = array(
			'success' => 'ok',
		);

		// get object id.
		$object_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		// get object type.
		$object_type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

		// is automatic mode prevented?
		$prevent_automatic_mode = isset( $_POST['prevent_automatic_simplification'] ) && 'true' === $_POST['prevent_automatic_simplification'];

		if ( $object_id > 0 && ! empty( $object_type ) ) {
			$post_obj = Helper::get_object( $object_id, $object_type );
			if ( $post_obj instanceof Objects ) {
				$post_obj->set_automatic_mode_prevented( $prevent_automatic_mode );
			}
		}

		// return result.
		wp_send_json( $return );
	}

	/**
	 * Set automatic simplification intervall.
	 *
	 * @param string|null $value The value for the interval.
	 *
	 * @return string
	 */
	public function set_automatic_interval( ?string $value ): string {
		// validate the value.
		$value = Helper::settings_validate_select_field( $value );

		// return empty string if validate is null.
		if ( is_null( $value ) ) {
			return '';
		}

		if ( ! empty( $value ) ) {
			wp_clear_scheduled_hook( 'easy_language_automatic_simplification' );
			wp_schedule_event( time(), $value, 'easy_language_automatic_simplification' );
		}

		// return setting.
		return $value;
	}

	/**
	 * Add some cron-intervals.
	 *
	 * @param array<string,array<string,mixed>> $intervals List of intervals.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function add_cron_intervals( array $intervals ): array {
		$intervals['4hourly']    = array(
			'interval' => 60 * 60 * 4,
			'display'  => __( 'every 4 hours', 'easy-language' ),
		);
		$intervals['3hourly']    = array(
			'interval' => 60 * 60 * 3,
			'display'  => __( 'every 3 hours', 'easy-language' ),
		);
		$intervals['2hourly']    = array(
			'interval' => 60 * 60 * 2,
			'display'  => __( 'every 2 hours', 'easy-language' ),
		);
		$intervals['30minutely'] = array(
			'interval' => 60 * 30,
			'display'  => __( 'every 30 Minutes', 'easy-language' ),
		);
		$intervals['20minutely'] = array(
			'interval' => 60 * 20,
			'display'  => __( 'every 20 Minutes', 'easy-language' ),
		);
		$intervals['10minutely'] = array(
			'interval' => 60 * 10,
			'display'  => __( 'every 10 Minutes', 'easy-language' ),
		);

		// return resulting list of additional intervals.
		return $intervals;
	}

	/**
	 * Return array to filter for entries which should be simplified.
	 *
	 * @return array<string,mixed>
	 */
	public function get_filter_for_entries_to_simplify(): array {
		return array(
			'state'            => 'to_simplify',
			'not_prevented'    => true,
			'object_not_state' => 'trash',
		);
	}

	/**
	 * Add custom status-check for running cronjobs of our own plugin.
	 *
	 * @param array<string,mixed> $statuses List of tests to run.
	 * @return array<string,mixed>
	 */
	public function add_site_status_test( array $statuses ): array {
		$statuses['async']['easy_language_automatic_cronjob']        = array(
			'label'    => __( 'Easy Language automatic cronjob', 'easy-language' ),
			'test'     => rest_url( 'easy-language/v1/automatic_cron_checks' ),
			'has_rest' => true,
		);
		$statuses['async']['easy_language_check_for_configured_api'] = array(
			'label'    => __( 'Easy Language API check', 'easy-language' ),
			'test'     => rest_url( 'easy-language/v1/api_check' ),
			'has_rest' => true,
		);
		return $statuses;
	}

	/**
	 * Create automatic simplification cron if it does not exist.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function create_automatic_simplification_cron(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-create-schedules', 'nonce' );

		// check if automatic interval exist, if not create it.
		if ( ! wp_next_scheduled( 'easy_language_automatic_simplification' ) ) {
			// add it.
			wp_schedule_event( time(), get_option( 'easy_language_automatic_simplification', '10minutely' ), 'easy_language_automatic_simplification' );
		}

		// redirect user back to previous page.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Get post-object by given id.
	 *
	 * @param Objects|false $the_object The object we will return.
	 * @param int           $object_id The object-ID.
	 * @return Objects|false
	 */
	public function get_post_object( Objects|false $the_object, int $object_id ): Objects|false {
		// bail if object is already found.
		if ( false !== $the_object ) {
			return $the_object;
		}

		// we assume it is a post-object and check for it.
		$wp_post_object = get_post( $object_id );

		// bail if object could not be found.
		if ( ! $wp_post_object instanceof WP_Post ) {
			return false;
		}

		// check if it is a supported post-type.
		$post_types = self::get_instance()->get_supported_post_types();

		// bail if object is not supported.
		if ( empty( $post_types[ $wp_post_object->post_type ] ) ) {
			return false;
		}

		// return the object.
		return new Post_Object( $object_id );
	}

	/**
	 * Change the initial simplify dialog depending on API-configuration.
	 *
	 * @param array<string,array<integer,mixed>> $dialog The dialog-configuration.
	 * @param Api_Base                           $api_obj The API.
	 * @param Post_Object                        $post_object The object used.
	 *
	 * @return array<string,array<integer,mixed>>
	 */
	public function change_first_simplify_dialog( array $dialog, Api_Base $api_obj, Post_Object $post_object ): array {
		// change options if active API is not configured.
		if ( false === $api_obj->is_configured() ) {
			if ( $api_obj->has_settings() && current_user_can( 'manage_options' ) ) {
				/* translators: %1$s will be replaced by the API-title, %2$s will be replaced by the object-type-name (e.g. post or page), %3$s will be replaced by the object-title */
				$dialog['texts'][0] = '<p>' . sprintf( __( 'The actual active API %1$s is not yet configured. You can configure it <a href="%2$s">here</a>.<br>Create a simplified %3$s for <i>%4$s</i> to edit is manually.', 'easy-language' ), esc_html( $api_obj->get_title() ), esc_url( $api_obj->get_settings_url() ), esc_html( $post_object->get_type_name() ), esc_html( $post_object->get_title() ) ) . '</p>';
			} else {
				/* translators: %1$s will be replaced by the API-title, %2$s will be replaced by the object-type-name (e.g. post or page), %3$s will be replaced by the object-title */
				$dialog['texts'][0] = '<p>' . sprintf( __( 'The actual active API %1$s is not yet configured.<br>Create a simplified %2$s for <i>%3$s</i> to edit is manually.', 'easy-language' ), esc_html( $api_obj->get_title() ), esc_html( $post_object->get_type_name() ), esc_html( $post_object->get_title() ) ) . '</p>';
			}
			$dialog['buttons'][0]            = $dialog['buttons'][1];
			$dialog['buttons'][0]['variant'] = 'primary';
			$dialog['buttons'][1]            = $dialog['buttons'][2];
			unset( $dialog['buttons'][2] );
		}

		// return resulting dialog.
		return $dialog;
	}

	/**
	 * Delete the requested simplification.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function delete_text_for_simplification(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-delete-text-for-simplification', 'nonce' );

		// get requested text.
		$text_id = ! empty( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( $text_id > 0 ) {
			$text_obj = new Text( $text_id );
			$text_obj->delete();

			// show success-message.
			$transients_obj = Transients::get_instance();
			$transient_obj  = $transients_obj->add();
			$transient_obj->set_name( 'easy_language_text_deleted' );
			$transient_obj->set_message( '<strong>' . __( 'The chosen text is deleted.', 'easy-language' ) . '</strong>' );
			$transient_obj->set_type( 'success' );
			$transient_obj->save();
		}

		// redirect user back to list.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Delete all to simplified texts.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function delete_all_to_simplified_texts(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-delete-all-to-simplified_texts', 'nonce' );

		// get all texts which should be simplified.
		$entries = Db::get_instance()->get_entries( self::get_instance()->get_filter_for_entries_to_simplify() );

		// delete them.
		foreach ( $entries as $entry ) {
			$entry->delete();
		}

		// show success-message.
		$transients_obj = Transients::get_instance();
		$transient_obj  = $transients_obj->add();
		$transient_obj->set_name( 'easy_language_text_deleted' );
		$transient_obj->set_message( '<strong>' . __( 'All to simplified texts has been deleted.', 'easy-language' ) . '</strong>' );
		$transient_obj->set_type( 'success' );
		$transient_obj->save();

		// Log event.
		Log::get_instance()->add_log( __( 'All to simplify texts has been deleted', 'easy-language' ), 'success' );

		// redirect user back to list.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}
}
