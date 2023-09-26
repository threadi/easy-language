<?php
/**
 * File for initializing the easy-language-own translations.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

use easyLanguage\Apis;
use easyLanguage\Base;
use easyLanguage\Helper;
use easyLanguage\Languages;
use easyLanguage\Multilingual_Plugins_Base;
use WP_Admin_Bar;
use WP_Post;
use WP_Post_Type;
use WP_Query;
use WP_Term;
use WP_User;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

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
		$pagebuilder->init();

		// include pagebuilder support.
		foreach( glob(plugin_dir_path(EASY_LANGUAGE)."classes/multilingual-plugins/easy-language/pagebuilder/*.php") as $filename ) {
			include $filename;
		}

		// misc hooks.
		add_action( 'update_option_WPLANG', array( $this, 'option_locale_changed' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 500 );

        // add settings.
        add_action( 'easy_language_settings_add_settings', array( $this, 'add_settings' ), 15 );

        // add settings tab.
        add_action( 'easy_language_settings_add_tab', array( $this, 'add_settings_tab' ), 15 );

		// add translations-overview.
		add_action( 'easy_language_settings_translations_page', array( $this, 'add_translations' ), 15 );

		// add translations tab in settings-page.
		add_action( 'easy_language_settings_add_tab', array( $this, 'add_translations_tab' ), 50 );

        // add settings page.
        add_action( 'easy_language_settings_general_page', array( $this, 'add_settings_page' ) );

		// backend translation-hooks to show or hide translated pages.
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'pre_get_posts', array( $this, 'hide_translated_posts' ) );
		add_filter( 'get_terms_args', array( $this, 'hide_translated_terms' ) );
        add_filter( 'get_pages', array( $this, 'get_pages' ) );

		// add ajax-actions hooks.
		add_action( 'wp_ajax_easy_language_run_translation', array( $this, 'ajax_run_translation' ) );
		add_action( 'wp_ajax_easy_language_get_info_translation', array( $this, 'ajax_get_translation_info' ) );

        // embed files.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), PHP_INT_MAX );

	}

	/**
	 * Add language-columns for each supported post type and taxonomy.
	 *
	 * @return void
	 */
	public function admin_init(): void {
		// get supported post-types and loop through them.
		foreach( $this->get_supported_post_types() as $post_type => $enabled ) {
			// get the post type as object to get additional settings of it.
			$post_type_obj = get_post_type_object( $post_type );

			// go only further if the post-type is visible in backend
			// and trash status is not called.
			if( false !== $post_type_obj->show_in_menu ) {
				add_filter( 'manage_'.$post_type.'_posts_columns', array( $this, 'add_post_type_columns' ) );
				add_action( 'manage_'.$post_type.'_posts_custom_column' , array( $this, 'add_post_type_column_content' ), 10, 2 );
				add_filter( 'views_edit-'.$post_type, array( $this, 'add_post_type_view' ) );
			}
		}

        // get supported taxonomies and loop through them.
        foreach( $this->get_supported_taxonomies() as $category => $enabled ) {
	        add_filter( 'manage_edit-'.$category.'_columns', array( $this, 'add_taxonomy_columns' ) );
	        add_action( 'manage_'.$category.'_custom_column' , array( $this, 'add_taxonomy_column_content' ), 10, 3 );
        }

		// add filter for changed posts.
		add_action( 'restrict_manage_posts', array( $this, 'add_posts_filter' ) );
		add_filter( 'parse_query', array( $this, 'posts_filter' ) );

		// support for nested pages: show translated pages as action-links.
		if( helper::is_plugin_active('wp-nested-pages/nestedpages.php') ) {
			add_filter( 'post_row_actions', array( $this, 'add_post_row_action' ), 10, 2 );
		}
	}

	/**
	 * Add one column for each enabled language in supported post-types.
	 *
	 * @param $columns
	 *
	 * @return array
	 */
	public function add_post_type_columns( $columns ): array {
		// Bail if we're looking at trash.
		$status = get_query_var( 'post_status' );
		if ( 'trash' === $status ) {
			return $columns;
		}

		// create new array for columns to get clean ordering.
		$new_columns = array();
		$new_columns['cb'] = $columns['cb'];
		$new_columns['title'] = $columns['title'];

		// get actual supported languages.
		foreach( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
			$new_columns['easy-language-'.strtolower($language_code)] = $settings['label'];
		}

		// return result.
		return array_merge( $new_columns, $columns );
	}

	/**
	 * Add content for the new added columns in post-types.
	 *
	 * @param $column
	 * @param $post_id
	 *
	 * @return void
	 */
	public function add_post_type_column_content( $column, $post_id ): void {
		// get active API for automatic translation.
		$api_obj = Apis::get_instance()->get_active_api();

		// get object of this post.
		$post_object = new Post_Object( $post_id );

		// get actual supported languages.
		foreach( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
			if ( 'easy-language-'.strtolower($language_code) === $column ) {
				// check if this object is already translated in this language.
				if( false !== $post_object->is_translated_in_language( $language_code ) ) {
					// get the post-ID of the translated page.
					$translated_post_id = $post_object->get_translated_in_language( $language_code );

					// get page-builder of this object.
					$translated_post_obj = new Post_Object( $translated_post_id );
					$page_builder = $translated_post_obj->get_page_builder();

					// do not show anything if the used page builder plugin is not available.
					if( false === $page_builder->is_active() ) {
						echo '<span class="dashicons dashicons-lightbulb" title="'.sprintf(__( 'Used page builder %s not available', 'easy-language' ), $page_builder->get_name()).'"></span>';

						// get link to delete this translation.
						$delete_translation = get_delete_post_link( $translated_post_id );

						// show link to delete the translated post.
						/* translators: %1$s is the name of the language */
						echo '<a href="' . esc_url( $delete_translation ) . '" class="dashicons dashicons-trash easy-language-trash" title="'.esc_attr( sprintf( __( 'Delete translation in %1$s', '' ), $settings['label']) ).'">&nbsp;</a>';
						continue;
					}

					// get page-builder-specific edit-link if user has capability for it.
                    if( current_user_can( 'edit_el_translate' ) ) {
	                    $edit_translation = $page_builder->get_edit_link();

	                    // show link to add translation for this language.
	                    /* translators: %1$s is the name of the language */
	                    echo '<a href="' . esc_url( $edit_translation ) . '" class="dashicons dashicons-edit" title="' . esc_attr( sprintf( __( 'Edit translation in %1$s', '' ), $settings['label'] ) ) . '">&nbsp;</a>';
                    }

					// create link to run translation of this page via API (if available).
					if( false !== $api_obj && current_user_can( 'edit_el_translate' ) ) {
                        // get quota-state of this object.
                        $quota_status = $translated_post_obj->get_quota_state( $api_obj );

                        // only if it is ok show translate-icon.
                        if( 'ok' === $quota_status['status'] ) {
	                        // get link to add translation.
	                        $do_translation = $translated_post_obj->get_translation_via_api_link();

	                        // show link to translate this page via api.
	                        /* translators: %1$s is the name of the language, %2$s is the name of the used API */
	                        echo '<a href="' . esc_url( $do_translation ) . '" class="dashicons dashicons-translation easy-language-translate-object" data-id="'.absint($post_object->get_id()).'" title="' . esc_attr( sprintf( __( 'Translate this %1$s in %2$s with %3$s', 'easy-language' ), $translated_post_obj->get_type(), esc_html( $settings['label'] ), esc_html( $api_obj->get_title() ) ) ) . '">&nbsp;</a>';
                        }
                        else {
                            // otherwise show simple not clickable icon.
	                        echo '<span class="dashicons dashicons-translation" title="' . esc_attr( sprintf( __( 'Not enough quota to translate this %1$s in %2$s with %3$s.', 'easy-language' ), $translated_post_obj->get_type(), esc_html( $settings['label'] ), esc_html( $api_obj->get_title() ) ) ) . '">&nbsp;</span>';
                        }

                        // show quota hint.
						$this->show_quota_hint( $api_obj );
					}

                    // get link to view object in frontend.
                    $show_link = get_permalink( $translated_post_obj->get_id() );

                    // show link to view object in frontend.
					echo '<a href="' . esc_url( $show_link ) . '" class="dashicons dashicons-admin-site-alt3" target="_blank" title="'.esc_attr( __( 'Show in fronted (opens new window)', 'easy-language' ) ).'">&nbsp;</a>';

					// get link to delete this translation if user has capability for it.
                    if( current_user_can( 'delete_el_translate' ) ) {
	                    $delete_translation = get_delete_post_link( $translated_post_id );

	                    // show link to delete the translated post.
	                    /* translators: %1$s is the name of the language */
	                    echo '<a href="' . esc_url( $delete_translation ) . '" class="dashicons dashicons-trash easy-language-trash" title="' . esc_attr( sprintf( __( 'Delete translation in %1$s', '' ), $settings['label'] ) ) . '">&nbsp;</a>';
                    }

					// show mark if content of original page has been changed.
					if( $post_object->has_changed( $language_code ) && current_user_can( 'edit_el_translate' ) ) {
						echo '<span class="dashicons dashicons-image-rotate" title="'.__( 'Original content has been changed!', 'easy-language' ).'"></span>';
					}
				}
				else {
					// create link to translate this post.
					$create_translation = $post_object->get_translate_link( $language_code );

					// show link to add translation for this language.
					/* translators: %1$s is the name of the language */
					echo '<a href="' . esc_url( $create_translation ) . '" class="dashicons dashicons-plus" title="'.esc_attr( sprintf( __( 'Add translation in %s', '' ), $settings['label']) ).'">&nbsp;</a>';
				}
			}
		}
	}

	/**
     * Add one column for each enabled language in supported taxonomies.
     *
	 * @param $columns
	 *
	 * @return array
	 */
    public function add_taxonomy_columns( $columns ): array {
	    // create new array for columns to get clean ordering.
	    $new_columns = array();
	    $new_columns['cb'] = $columns['cb'];
	    $new_columns['name'] = $columns['name'];

	    // get actual supported languages.
	    foreach( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
		    $new_columns['easy-language '.strtolower($language_code)] = $settings['label'];
	    }

	    // return result.
	    return array_merge( $new_columns, $columns );
    }

	/**
     * Add content for the new added columns in taxonomies.
     *
	 * @param string $string The default value for the column.
	 * @param string $column The name of the column.
	 * @param int $term_id The ID of the term.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
    public function add_taxonomy_column_content( string $string, string $column, int $term_id ): void {
	    // get active API for automatic translation.
	    $api_obj = Apis::get_instance()->get_active_api();

        // get term.
        $term = get_term( $term_id );

	    // get object of this term.
	    $term_object = new Term_Object( $term_id, $term->taxonomy );

	    // get actual supported languages.
	    foreach( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
		    if ( 'easy-language '.strtolower($language_code) === $column ) {
			    // check if this object is already translated in this language.
			    if( false !== $term_object->is_translated_in_language( $language_code ) ) {
				    // get the term-ID of the translated term.
				    $translated_term_id = $term_object->get_translated_in_language( $language_code );

				    // get page-builder of this object.
				    $translated_term_obj = new Term_Object( $translated_term_id, $term->taxonomy );

				    // get page-builder-specific edit-link if user has capability for it.
				    if( current_user_can( 'edit_el_translate' ) ) {
					    $edit_translation = $translated_term_obj->get_edit_link();

					    // show link to add translation for this language.
					    /* translators: %1$s is the name of the language */
					    echo '<a href="' . esc_url( $edit_translation ) . '" class="dashicons dashicons-edit" title="' . esc_attr( sprintf( __( 'Edit translation in %1$s', '' ), $settings['label'] ) ) . '">&nbsp;</a>';
				    }

				    // create link to run translation of this page via API (if available).
				    if( false !== $api_obj && current_user_can( 'edit_el_translate' ) ) {
					    // get link to add translation.
					    $do_translation = $translated_term_obj->get_translation_via_api_link();

					    // show link to translate this page via api.
					    /* translators: %1$s is the name of the language, %2$s is the name of the used API */
					    echo '<a href="' . esc_url( $do_translation ) . '" class="dashicons dashicons-translation" title="' . esc_attr( sprintf( __( 'Translate this term in %1$s with %2$s', '' ), $settings['label'], $api_obj->get_title() ) ) . '">&nbsp;</a>';

					    // show quota hint.
					    $this->show_quota_hint( $api_obj );
				    }

				    // get link to delete this translation if user has capability for it.
				    if( current_user_can( 'delete_el_translate' ) ) {
					    $delete_translation = $translated_term_obj->get_delete_link();

					    // show link to delete the translated term.
					    /* translators: %1$s is the name of the language */
					    echo '<a href="' . esc_url( $delete_translation ) . '" class="dashicons dashicons-trash easy-language-trash" title="' . esc_attr( sprintf( __( 'Delete translation in %1$s', '' ), $settings['label'] ) ) . '">&nbsp;</a>';
				    }

				    // show mark if content of original page has been changed.
				    if( $term_object->has_changed( $language_code ) && current_user_can( 'edit_el_translate' ) ) {
					    echo '<span class="dashicons dashicons-image-rotate" title="'.__( 'Original content has been changed!', 'easy-language' ).'"></span>';
				    }
			    }
			    else {
				    // create link to translate this term.
				    $create_translation = $term_object->get_translate_link( $language_code );

				    // show link to add translation for this language.
				    /* translators: %1$s is the name of the language */
				    echo '<a href="' . esc_url( $create_translation ) . '" class="dashicons dashicons-plus" title="'.esc_attr( sprintf( __( 'Add translation in %s', '' ), $settings['label']) ).'">&nbsp;</a>';
			    }
		    }
	    }
    }

	/**
	 * Hide our translated objects in queries for actively supported post types in backend.
	 *
	 * @param $query
	 *
	 * @return void
	 */
	public function hide_translated_posts( $query ): void {
		if ( is_admin() && '' === $query->get('do_not_use_easy_language_filter') && $query->get('post_status') !== 'trash' ) {
			$post_types = $this->get_supported_post_types();
            if( !empty($post_types[$query->get('post_type')]) ) {
	            $query->set( 'meta_query', array(
			            array(
				            'key'     => 'easy_language_translation_original_id',
				            'compare' => 'NOT EXISTS'
			            )
		            )
	            );

	            if ( isset( $_GET['lang'] ) ) {
		            $query->set( 'meta_query', array(
				            array(
					            'key'     => 'easy_language_translated_in',
					            'value'   => wp_unslash( $_GET['lang'] ),
					            'compare' => 'LIKE'
				            )
			            )
		            );
	            }
            }
		}
	}

	/**
	 * Hide our translated terms in queries for post types in backend.
	 *
	 * @param $args
	 *
	 * @return array
	 */
	public function hide_translated_terms( $args ): array {
		if ( is_admin() && !empty($args['taxonomy'][0]) && $args['taxonomy'][0] === 'category' && empty($_GET['action']) ) {
			$args['meta_query'] = array(
                array(
                    'key' => 'easy_language_translation_original_id',
                    'compare' => 'NOT EXISTS'
                )
			);
		}

        // return resulting arguments.
        return $args;
	}

	/**
	 * If locale setting changed in WP, change the plugin-settings.
	 *
	 * @param $old_value
	 * @param $value
	 *
	 * @return void
	 */
	public function option_locale_changed( $old_value, $value ): void {
		// if new value is empty, it is the en_US-default.
		if( empty($value) ) {
			$value = 'en_US';
		}

		// same for old_value.
		if( empty($old_value) ) {
			$old_value = 'en_US';
		}

		// get actual setting for source languages.
		$languages = get_option( 'easy_language_source_languages', array());

		// remove the old-value from list, if it exists there.
		if( isset($languages[$old_value]) ) {
			unset( $languages[ $old_value ] );
		}

		// check if the new value is supported as source language by active APIs.
		$add = false;
		$api_obj = Apis::get_instance()->get_active_api();
		if( false !== $api_obj ) {
			$source_languages = $api_obj->get_supported_source_languages();
			if ( ! empty( $source_languages[ $value ] ) ) {
				$add = true;
			}
		}

		// add the new language as activate language.
		if( false !== $add ) {
			$languages[ $value ] = "1";
		}

		// update resulting setting.
		update_option( 'easy_language_source_languages', $languages);
	}

	/**
	 * Add translation-button in admin bar in frontend.
	 *
	 * @param WP_Admin_Bar $admin_bar
	 *
	 * @return void
	 */
	public function admin_bar_menu( WP_Admin_Bar $admin_bar ): void {
		// do not show anything in wp-admin.
		if( is_admin() ) {
			return;
		}

		// do not show if user has no capabilities for this.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// get the active languages.
		$target_languages = Languages::get_instance()->get_active_languages();

		// if actual language is not supported as source language, do not show anything.
		if( empty($target_languages) ) {
			return;
		}

		// get current object id
		$object_id = get_queried_object_id();

		// get our own object for the requested object.
		$object = $this->get_object_by_wp_object( get_queried_object(), $object_id );
		if( false === $object ) {
			return;
		}

		// bail if used pagebuilder prevent display of translate-option in frontend (e.g. to use its own options).
		if( $object->get_page_builder() && false !== $object->get_page_builder()->hide_translate_menu_in_frontend() ) {
			return;
		}

		// check if this object is a translated object.
		if( $object->is_translated() ) {
			$object_id = $object->get_original_object_as_int();
			// get new object as base for the listing.
			$object = $this->get_object_by_wp_object( get_queried_object(), $object_id );
		}

		// bail if post type is not supported.
		if( false === $this->is_post_type_supported( $object->get_type() ) ) {
			return;
		}

		// secure the menu ID.
		$id = 'easy-language-translate-button';

		// add not clickable main menu where all languages will be added as dropdown-items.
		$admin_bar->add_menu( array(
			'id'    => $id,
			'parent' => null,
			'group'  => null,
			'title' => __( 'Translate page', 'easy-language' ),
			'href'  => '',
		) );

		// add sub-entry for each possible target language.
		foreach( $target_languages as $language_code => $target_language ) {
			// check if this object is already translated in this language.
			if( false !== $object->is_translated_in_language( $language_code ) ) {
				// generate link-target to default editor with language-marker.
				$translated_post_object = new Post_Object( $object->get_translated_in_language( $language_code ) );
				$url = $translated_post_object->get_page_builder()->get_edit_link();
			}
			else {
				// create link to generate a new translation for this object.
				$url = $object->get_translate_link( $language_code );
			}

			// add language as possible translation-target.
			$admin_bar->add_menu( array(
				'id'        => $id.'-'.$language_code,
				'parent'    => $id,
				'title'     => $target_language['label'],
				'href'      => $url,
			) );
		}
	}

	/**
	 * Initialize our main CLI-functions.
	 *
	 * @return void
	 * @noinspection PhpUndefinedClassInspection
	 * @noinspection PhpFullyQualifiedNameUsageInspection
	 */
	public function cli(): void	{
		\WP_CLI::add_command('easy-language', 'easyLanguage\Multilingual_plugins\Easy_Language\Cli');
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
	 * Return supported taxonomies.
	 *
	 * @return array
	 */
	public function get_supported_taxonomies(): array {
		return get_option( 'easy_language_taxonomies', array() );
	}

	/**
	 * Return supported post-types.
	 *
	 * @param string $post_type
	 *
	 * @return bool
	 */
	public function is_post_type_supported( string $post_type ): bool {
		$post_types = $this->get_supported_post_types();
		return !empty($post_types[$post_type]);
	}

	/**
	 * Run on plugin-installation.
	 *
	 * @return void
	 */
	public function install(): void {
		// set supported post-types.
		if( !get_option('easy_language_post_types') ) {
			update_option('easy_language_post_types', array( 'post' => '1', 'page' => '1' ) );
		}

		// set supported taxonomies.
		if( !get_option('easy_language_taxonomies') ) {
			update_option('easy_language_taxonomies', array( 'category' => '1', 'post_tag' => '1' ) );
		}

		// set deletion state.
		if( !get_option('easy_language_state_on_deactivation') ) {
			update_option('easy_language_state_on_deactivation', 'draft' );
		}

		// set to generate permalinks.
		if( !get_option('easy_language_generate_permalink') ) {
			update_option('easy_language_generate_permalink', '1' );
		}

		// set language-switcher-mode.
		if( !get_option('easy_language_switcher_link') ) {
			update_option('easy_language_switcher_link', 'link_translated');
		}

		// set supported language to one matching the project-language.
		if( !get_option('easy_language_languages') ) {
			$source_languages = get_option('easy_language_source_languages');
			$languages = array();
			$mappings = Apis::get_instance()->get_available_apis()[0]->get_mapping_languages();
			foreach( $source_languages as $source_language => $enabled ) {
				if( !empty($mappings[$source_language]) ) {
					foreach( $mappings[$source_language] as $language ) {
						$languages[ $language ] = "1";
					}
				}
			}
			update_option('easy_language_languages', $languages );
		}

        // get post-type-names.
        $post_type_names = \easyLanguage\Init::get_instance()->get_post_type_names();

        // get our own translator-role.
		$translator_role = get_role('el_translator');

		// get all translated, draft and marked objects in any supported language to set them to publish.
		foreach( $this->get_supported_post_types() as $post_type => $enabled ) {
			$query = array(
				'post_type' => $post_type,
				'posts_per_page' => -1,
				'post_status' => array( 'any', 'trash' ),
				'fields' => 'ids',
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key' => 'easy_language_translation_original_id',
						'compare' => 'EXISTS'
					),
					array(
						'key' => 'easy_language_translation_state_changed_from',
						'compare' => 'EXISTS'
					)
				),
				'do_not_use_easy_language_filter' => true
			);
			$results = new WP_Query( $query );
			foreach( $results->posts as $post_id ) {
				// get state this post hav before.
				$post_state = get_post_meta( $post_id, 'easy_language_translation_state_changed_from', true );

				// set the state.
				$query = array(
					'ID'           => $post_id,
					'post_status'  => $post_state,
				);
				wp_update_post( $query );

				// remove marker.
				delete_post_meta( $post_id, 'easy_language_translation_state_changed_from' );
			}

			// add cap for translator-role to edit this post-types.
            if( !empty($post_type_names[$post_type]) ) {
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
	}

	/**
	 * Run on deactivation.
	 *
	 * @return void
	 */
	public function deactivation(): void {
		// get new state setting.
		$new_state_setting = get_option( 'easy_language_state_on_deactivation', 'draft' );
		if( 'disabled' !== $new_state_setting ) {
			// get all translated objects in any supported language to set them to draft.
			foreach ( $this->get_supported_post_types() as $post_type => $enabled ) {
				$query   = array(
					'post_type'      => $post_type,
					'posts_per_page' => -1,
					'post_status'    => array( 'any', 'trash' ),
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'     => 'easy_language_translation_original_id',
							'compare' => 'EXISTS'
						)
					),
					'do_not_use_easy_language_filter' => true
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
					update_post_meta( $post_id, 'easy_language_translation_state_changed_from', $post_state );
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
		foreach( DB::get_instance()->get_entries() as $entry ) {
			$entry->delete();
		}

        // remove custom transients which are not set via Transient-object.
        delete_transient( 'easy_language_refresh_rewrite_rules' );

		// delete switcher.
		$query = array(
			'post_type' => EASY_LANGUAGE_CPT_SWITCHER,
			'post_status' => 'any',
			'post_per_page' => -1,
			'fields' => 'ids'
		);
		$switcher = new WP_Query( $query );
		foreach( $switcher->posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		// delete options.
		$options = array(
			'easy_language_post_types',
			'easy_language_taxonomies',
			'easy_language_languages',
			'easy_language_switcher_link',
			EASY_LANGUAGE_OPTION_TRANSLATE_RUNNING,
			EASY_LANGUAGE_OPTION_TRANSLATE_COUNT,
			EASY_LANGUAGE_OPTION_TRANSLATE_MAX,
            'easy_language_switcher_default',
            'easy_language_state_on_deactivation',
            'easy_language_generate_permalink'
		);
		foreach( $options as $option ) {
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
            __('General Settings', 'easy-language'),
            '__return_true',
            'easyLanguageEasyLanguagePage'
        );

		// get all actual post-types in this project.
		$post_types_array = array( 'post', 'page' );
	    $post_types = array();
		foreach( $post_types_array as $post_type ) {
			$post_type_obj = get_post_type_object($post_type);
			if( false !== $post_type_obj->show_ui && false !== $post_type_obj->public && 'attachment' !== $post_type_obj->name ) {
				$post_types[$post_type] = array(
					'label' => $post_type_obj->label
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
                'fieldId' => 'easy_language_post_types',
                'description' => '', // TODO pro-hint
				'options' => apply_filters( 'easy_language_possible_post_types', $post_types )
            )
        );
        register_setting( 'easyLanguageEasyLanguageFields', 'easy_language_post_types', array( 'sanitize_callback' => array( $this, 'validate_post_types') ) );

	    // get all actual taxonomies in this project.
	    $taxonomies_array = array( 'category', 'post_tag' );
        $taxonomies = array();
        foreach( $taxonomies_array as $taxonomy ) {
	        $taxonomy_obj = get_taxonomy( $taxonomy );
	        if( false !== $taxonomy_obj->show_ui && false !== $taxonomy_obj->public ) {
		        $taxonomies[$taxonomy] = array(
			        'label' => $taxonomy_obj->label
		        );
	        }
        }

	    // Choose supported taxonomies.
	    add_settings_field(
		    'easy_language_taxonomies',
		    __( 'Choose supported taxonomies', 'easy-language' ),
		    'easy_language_admin_multiple_checkboxes_field',
		    'easyLanguageEasyLanguagePage',
		    'settings_section_easy_language',
		    array(
			    'label_for' => 'easy_language_taxonomies',
			    'fieldId' => 'easy_language_taxonomies',
			    'description' => '',
			    'options' => apply_filters( 'easy_language_possible_taxonomies', $taxonomies )
		    )
	    );
	    register_setting( 'easyLanguageEasyLanguageFields', 'easy_language_taxonomies', array( 'sanitize_callback' => array( $this, 'validate_taxonomies') ) );

		// get active api for readonly-marker depending on active api.
		$active_api = Apis::get_instance()->get_active_api();
	    $readonly = true;
        if( $active_api && false === $active_api->has_settings() ) {
            $readonly = false;
        }

		// Choose supported languages for manuel translations.
		add_settings_field(
			'easy_language_languages',
			__( 'Choose languages', 'easy-language' ),
			'easy_language_admin_multiple_checkboxes_field',
			'easyLanguageEasyLanguagePage',
			'settings_section_easy_language',
			array(
				'label_for' => 'easy_language_languages',
				'fieldId' => 'easy_language_languages',
				'description' => $readonly ? __( 'Go to API-settings to choose the languages you want to use.', 'easy-language' ) : __( 'Choose the language you want to use for translation.', 'easy-language' ),
				'options' => Languages::get_instance()->get_possible_target_languages(),
				'readonly' => $readonly
			)
		);
		register_setting( 'easyLanguageEasyLanguageFields', 'easy_language_languages', array( 'sanitize_callback' => array( $this, 'change_langauges' ) ) );

	    // Set object state on plugin deactivation.
	    add_settings_field(
		    'easy_language_state_on_deactivation',
		    __( 'Set object state on plugin deactivation', 'easy-language' ),
		    'easy_language_admin_select_field',
		    'easyLanguageEasyLanguagePage',
		    'settings_section_easy_language',
		    array(
			    'label_for' => 'easy_language_state_on_deactivation',
			    'fieldId' => 'easy_language_state_on_deactivation',
			    'description' => __('If plugin is disabled your translated objects will get the state set here. If plugin is reactivated they will be set to their state before.<br><strong>Hint:</strong> During uninstallation all translated objects will be deleted regardless of the setting here.', 'easy-language'),
			    'values' => array(
				    'disabled' => __( 'Do not change anything', 'easy-language'),
				    'draft' => __( 'Set to draft', 'easy-language'),
				    'trash' => __( 'Set to trash', 'easy-language'),
			    ),
			    'disable_empty' => true
		    )
	    );
	    register_setting( 'easyLanguageEasyLanguageFields', 'easy_language_state_on_deactivation' );

	    // Set if translated pages should have a generated permalink.
	    add_settings_field(
		    'easy_language_generate_permalink',
		    __( 'Generate permalink for translated objects', 'easy-language' ),
		    'easy_language_admin_checkbox_field',
		    'easyLanguageEasyLanguagePage',
		    'settings_section_easy_language',
		    array(
			    'label_for' => 'easy_language_generate_permalink',
			    'fieldId' => 'easy_language_generate_permalink',
			    'description' => __('If enabled an individual permalink will be generated from title after translation of the title.', 'easy-language'),
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
			    'fieldId' => 'easy_language_switcher_link',
			    'options' => array(
				    'do_not_link' => array(
					    'label' => __( 'Do not link translated pages', 'easy-language'),
					    'enabled' => true,
					    'description' => __( 'The links in the switcher will general link to the language-specific homepage.', 'easy-language'),
				    ),
				    'link_translated' => array(
					    'label' => __( 'Link translated pages.', 'easy-language'),
					    'enabled' => true,
					    'description' => __( 'The Links in the switcher will link to the translated page. If a page is not translated, the link will target the language-specific homepage.', 'easy-language'),
				    ),
			    ),
		    )
	    );
	    register_setting( 'easyLanguageEasyLanguageFields', 'easy_language_switcher_link' );
    }

    /**
     * Add settings-tab for this plugin.
     *
     * @param $tab
     *
     * @return void
     */
    public function add_settings_tab( $tab ): void {
        // check active tab
        $activeClass = '';
        if( 'general' === $tab ) $activeClass = ' nav-tab-active';

        // output tab
        echo '<a href="'.esc_url(helper::get_settings_page_url()).'&tab=general" class="nav-tab'.esc_attr($activeClass).'">'.__('General settings', 'easy-language').'</a>';
    }

	/**
	 * Add settings-tab for this plugin.
	 *
	 * @param $tab
	 *
	 * @return void
	 */
	public function add_translations_tab( $tab ): void {
		// check active tab
		$activeClass = '';
		if( 'translations' === $tab ) $activeClass = ' nav-tab-active';

		// output tab
		echo '<a href="'.esc_url(helper::get_settings_page_url()).'&tab=translations" class="nav-tab'.esc_attr($activeClass).'">'.__('Translations', 'easy-language').'</a>';
	}

    /**
     * Add settings page for this plugin.
     *
     * @return void
     */
    public function add_settings_page(): void {
        // check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        ?>
        <form method="POST" action="<?php echo get_admin_url(); ?>options.php">
            <?php
            settings_fields( 'easyLanguageEasyLanguageFields' );
            do_settings_sections( 'easyLanguageEasyLanguagePage' );
            submit_button();
            ?>
        </form>
        <?php
    }

	/**
	 * Process multiple translations.
	 *
	 * @param Object $translation_obj
	 * @param array $language_mappings
	 * @param int $object_id
	 * @param string $taxonomy
	 *
	 * @return int
	 * @noinspection PhpUnused
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function process_translations( Object $translation_obj, array $language_mappings, int $object_id = 0, string $taxonomy = '' ): int {
        // create object-hash.
        $hash = md5($object_id.$taxonomy);

		// do not run translation if it is already running in another process for this object.
		$translation_running = get_option(EASY_LANGUAGE_OPTION_TRANSLATE_RUNNING, array() );
		if( !empty($translation_running[$hash]) && $translation_running[$hash] > 0 ) {
			return 0;
		}

		// mark translation for this object as running.
		update_option( EASY_LANGUAGE_OPTION_TRANSLATE_RUNNING, $translation_running + array( $hash => time() ) );

		// counter for translations.
		$c = 0;

		// define filter
		$filter = array();

		// add object-id to filter.
		if( $object_id > 0 ) {
			$post_obj = new Post_Object( $object_id );
			$filter['object_id'] = $post_obj->get_id();
		}

		// get entries.
		$entries = Db::get_instance()->get_entries( $filter );

		// set max texts to translate.
		$translation_max = get_option(EASY_LANGUAGE_OPTION_TRANSLATE_MAX, array() );
		update_option( EASY_LANGUAGE_OPTION_TRANSLATE_MAX, $translation_max + array( $hash => count($entries) ) );

		// set counter for translated texts to 0.
		$translation_count = get_option(EASY_LANGUAGE_OPTION_TRANSLATE_COUNT, array() );
		update_option( EASY_LANGUAGE_OPTION_TRANSLATE_COUNT, $translation_count + array( $hash => 0 ) );

        // show CLI process.
	    $progress = Helper::is_cli() ? \WP_CLI\Utils\make_progress_bar( 'Run translations', count($entries) ) : false;

		// loop through translations of this object.
		foreach( $entries as $entry ) {
			$c = $c + $this->process_translation( $translation_obj, $language_mappings, $entry, $object_id, $taxonomy );

			// update counter for translation of texts.
			$translation_count_in_loop = get_option(EASY_LANGUAGE_OPTION_TRANSLATE_COUNT, array() );
			$translation_count_in_loop[$hash]++;
			update_option( EASY_LANGUAGE_OPTION_TRANSLATE_COUNT, $translation_count_in_loop );

			// show progress
			!$progress ?: $progress->tick();
		}

		// end progress
		!$progress ?: $progress->finish();

		// remove marker for running translation on this object.
		$translation_running = get_option(EASY_LANGUAGE_OPTION_TRANSLATE_RUNNING, array() );
		$translation_running[$hash] = 0;
		update_option( EASY_LANGUAGE_OPTION_TRANSLATE_RUNNING, $translation_running );

		// return translation-count.
		return $c;
	}

	/**
	 * Process translation of single text initialized with API-support.
	 *
	 * @param Object $translation_obj
	 * @param array $language_mappings
	 * @param Text $entry
	 * @param int $object_id
	 * @param string $taxonomy
	 *
	 * @return int
	 * @noinspection PhpUnused
	 */
	private function process_translation( Object $translation_obj, array $language_mappings, Text $entry, int $object_id, string $taxonomy = '' ): int {
		// counter for translations.
		$c = 0;

		// get object the text belongs to, to get the target language.
		$object = new Post_Object( $object_id );
		$object_language = $object->get_language();

		// send request for each active mapping between source-language and target-languages.
		foreach( $language_mappings as $source_language => $target_languages ) {
			foreach( $target_languages as $target_language ) {
				// only if this text is not already translated in target-language matching the source-language.
				if ( !empty($object_language[$target_language]) && false === $entry->has_translation_in_language( $target_language ) && $source_language === $entry->get_source_language() ) {
					// call API to get translation as result-array.
					$results = $translation_obj->call_api( $entry->get_original(), $source_language, $target_language );

					// save translation if results are available.
					if ( ! empty( $results ) ) {
						$entry->set_translation( $results['translated_text'], $target_language, $translation_obj->init->get_name(), absint( $results['jobid'] ) );
						$c ++;
					}
				}
			}
		}

		// loop through translated texts to replace them in their original objects.
		// if request is only for one object, run it only there.
		$objects = $entry->get_objects();
		if( $object_id > 0 ) {
			$objects = array( array( 'object_id' => $object_id ) );
		}

		// loop through the posts and the languages to replace their texts.
		foreach( $objects as $object ) {
			foreach ( $language_mappings as $source_language => $target_languages) {
				foreach ( $target_languages as $target_language ) {
					if ( false !== $entry->has_translation_in_language( $target_language ) && $source_language === $entry->get_source_language() ) {
						$entry->replace_original_with_translation( $object['object_id'], $target_language, $taxonomy );
					}
				}
			}
		}

		// return translation-count.
		return $c;
	}

	/**
	 * Return languages this plugin would support.
	 *
	 * @return array
	 */
	public function get_supported_languages(): array {
		$settings_language = get_option( 'easy_language_languages', array() );
		if( empty($settings_language) ) {
			$settings_language = array();
		}
		return $settings_language;
	}

	/**
	 * Add row actions to show translate-options and -state per object for nested pages.
	 *
	 * @param $actions
	 * @param $post
	 *
	 * @return array
	 */
	public function add_post_row_action( $actions, $post ): array {
		// get post-object.
		$post_obj = new Post_Object( $post->ID );

		// get pagebuilder.
		$page_builder = $post_obj->get_page_builder();

		// get actual supported languages.
		foreach( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
			// check if this object is already translated in this language.
			if( false !== $post_obj->is_translated_in_language( $language_code ) ) {

				// do not show anything if the used page builder plugin is not available.
				if( false === $page_builder->is_active() ) {
					echo '<span class="dashicons dashicons-lightbulb" title="'.sprintf(__( 'Used page builder %s not available', 'easy-language' ), $page_builder->get_name()).'"></span>';
				}
				else {
					// create link to edit the translated post.
					$edit_translation = $page_builder->get_edit_link();

					// show link to add translation for this language.
					$actions[ 'easy-language-' . $language_code ] = '<a href="' . esc_url( $edit_translation ) . '"><i class="dashicons dashicons-edit"></i> ' . esc_html( $settings['label'] ) . '</a>';
				}
			}
			else {
				// create link to translate this post.
				$create_translation = $post_obj->get_translate_link( $language_code );

				// show link to add translation for this language.
				$actions['easy-language-'.$language_code] = '<a href="' . esc_url( $create_translation ) . '"><i class="dashicons dashicons-plus"></i> '.esc_html($settings['label']).'</a>';
			}
		}
		return $actions;
	}

	/**
	 * Get our own object for given WP-object.
	 *
	 * @param WP_Term|WP_User|WP_Post_Type|WP_Post|null $object The WP-object.
	 * @param int $id The ID of the WP-object.
	 * @param string $taxonomy The taxonomy.
	 *
	 * @return object|false
	 */
	public function get_object_by_wp_object( WP_Term|WP_User|WP_Post_Type|WP_Post|null $object, int $id, string $taxonomy = '' ): object|false {
		if( is_null($object) ) {
			return false;
		}
		return match ( get_class( $object ) ) {
			'WP_Post' => new Post_Object( $id ),
            'WP_Term' => new Term_Object( $id, $taxonomy ),
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
		$called_post_type = (isset($_GET['post_type'])) ? sanitize_text_field($_GET['post_type']) : 'post';

		// get supported post-types.
		$post_types = $this->get_supported_post_types();
		foreach( $post_types as $post_type => $enabled ) {
			if( 1 === absint($enabled) && $post_type === $called_post_type ) {
				?>
				<!--suppress HtmlFormInputWithoutLabel -->
				<select name="admin_filter_easy_language_changed">
					<option value=""><?php echo esc_html__( 'Filter easy language', 'easy-language' ); ?></option>
					<option value="translated"<?php echo ( isset( $_GET[ 'admin_filter_easy_language_changed' ] ) && wp_unslash( $_GET[ 'admin_filter_easy_language_changed' ] ) == 'translated' ) ? ' selected="selected"' : ''; ?>><?php echo esc_html__( 'show translated content', 'easy-language' ); ?></option>
					<option value="not_translated"<?php echo ( isset( $_GET[ 'admin_filter_easy_language_changed' ] ) && wp_unslash( $_GET[ 'admin_filter_easy_language_changed' ] ) == 'not_translated' ) ? ' selected="selected"' : ''; ?>><?php echo esc_html__( 'show not translated content', 'easy-language' ); ?></option>
					<option value="changed"<?php echo ( isset( $_GET[ 'admin_filter_easy_language_changed' ] ) && wp_unslash( $_GET[ 'admin_filter_easy_language_changed' ] ) == 'changed' ) ? ' selected="selected"' : ''; ?>><?php echo esc_html__( 'show changed content', 'easy-language' ); ?></option>
					<option value="not_changed"<?php echo ( isset( $_GET[ 'admin_filter_easy_language_changed' ] ) && wp_unslash( $_GET[ 'admin_filter_easy_language_changed' ] ) == 'not_changed' ) ? ' selected="selected"' : ''; ?>><?php echo esc_html__( 'show not changed content', 'easy-language' ); ?></option>
				</select>
				<?php
			}
		}
	}

	/**
	 * Filter for translated content in table-list.
	 *
	 * @param WP_Query $query
	 *
	 * @return void
	 * @noinspection SpellCheckingInspection
	 */
	public function posts_filter( WP_Query $query ): void {
		global $pagenow;

		// do not change anything if this is not the main query, our filter-var is not set or this is not edit.php.
		if( empty($_GET['admin_filter_easy_language_changed']) || false === $query->is_main_query() || $pagenow !== 'edit.php' ) {
			return;
		}

		// get called post-type.
		$called_post_type = (isset($_GET['post_type'])) ? sanitize_text_field($_GET['post_type']) : 'post';

		// get supported post-types.
		$post_types = $this->get_supported_post_types();
		foreach( $post_types as $post_type => $enabled ) {
			if ( 1 === absint( $enabled ) && $post_type === $called_post_type ) {
				remove_action( 'pre_get_posts', array( $this, 'hide_translated_objects' ) );
				switch( wp_unslash($_GET['admin_filter_easy_language_changed']) ) {
					case 'translated':
                        // get all objects WITH translation-objects.
						$query->set( 'meta_query', array(
							array(
								'key' => 'easy_language_translated_in',
								'compare' => 'EXISTS'
							)
						) );
						break;
					case 'not_translated':
						// get all objects WITHOUT translation-objects.
						$query->set( 'meta_query', array(
                            'relation' => 'AND',
							array(
								'key' => 'easy_language_translated_in',
								'compare' => 'NOT EXISTS'
							),
                            array(
                                'key' => 'easy_language_translation_original_id',
                                'compare' => 'NOT EXISTS'
                            )
						) );
						break;
					case 'changed':
						// get all objects which has been changed its content.
						$meta_query = array(
							'relation' => 'OR'
						);
						foreach( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
							$meta_query[] = array(
								'relation' => 'AND',
                                array(
								    'key' => 'easy_language_'.$language_code.'_changed',
								    'compare' => 'EXISTS'
							    ),
                                array(
								    'key' => 'easy_language_translated_in',
								    'compare' => 'EXISTS'
                                )
							);
						}
						$query->set( 'meta_query', array($meta_query) );
						break;
					case 'not_changed':
						// get all objects which has NOT been changed its content.
						$meta_query = array(
							'relation' => 'OR'
						);
						foreach( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
							$meta_query[] = array(
								'relation' => 'AND',
								array(
									'key' => 'easy_language_'.$language_code.'_changed',
									'compare' => 'NOT EXISTS'
								),
								array(
									'key' => 'easy_language_translated_in',
									'compare' => 'EXISTS'
								)
							);
						}
						$query->set( 'meta_query', array($meta_query) );
						break;
				}
			}
		}
	}

	/**
	 * Add our languages as filter-views in lists of supported post-types.
	 *
	 * @param $views
	 *
	 * @return array
	 */
	public function add_post_type_view( $views ): array {
		// get screen.
		$screen = get_current_screen();

		// get called post_type.
		$post_type = $screen->post_type;

		// loop through active languages and add them to the filter.
		foreach( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
			$url = add_query_arg(
				array(
					'post_type' => $post_type,
					'lang' => $language_code
				),
				'edit.php'
			);
			$views['easy-language-'.$language_code] = '<a href="'.esc_url($url).'">'.esc_html($settings['label']).'</a>';
		}
		return $views;
	}

	/**
	 * Run translation of given object via AJAX.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function ajax_run_translation(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-translate-start-nonce', 'nonce' );

		// get api.
		$api_obj = Apis::get_instance()->get_active_api();
		if( false === $api_obj ) {
			// no api active => forward user.
			wp_redirect($_SERVER['HTTP_REFERER']);
			exit;
		}

		// get the post-id from request.
		$post_id = isset($_POST['post']) ? absint($_POST['post']) : 0;

		if( absint($post_id) > 0 ) {
			// run translation of this object.
			$this->process_translations( $api_obj->get_translations_obj(), $api_obj->get_active_language_mapping(), $post_id );
		}

		// return nothing.
		wp_die();
	}

	/**
	 * Get info about running translation of given object via AJAX.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function ajax_get_translation_info(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-translate-get-nonce', 'nonce' );

		// get the post-id from request.
		$post_id = isset($_POST['post']) ? absint($_POST['post']) : 0;

		if( $post_id > 0 ) {
			// hash of the post_id.
			$hash = md5($post_id);

            // get running translations.
			$running_translations = get_option(EASY_LANGUAGE_OPTION_TRANSLATE_RUNNING, array() );

            // get max value for running translations.
            $max_translations = get_option(EASY_LANGUAGE_OPTION_TRANSLATE_MAX, array() );

            // get count value for running translations.
			$count_translations = get_option(EASY_LANGUAGE_OPTION_TRANSLATE_COUNT, array() );

			// collect return value.
			echo absint($count_translations[$hash]).";".absint($max_translations[$hash]).";".absint($running_translations[$hash]);
		}

		// return nothing.
		wp_die();
	}

	/**
     * Remove translated pages from get_pages-result.
     *
	 * @param array $pages The list of pages.
	 *
	 * @return array
	 */
    public function get_pages( array $pages ): array {
        foreach( $pages as $index => $page ) {
            $post_obj = new Post_Object( $page->ID );
            if( $post_obj->is_translated() ) {
                unset($pages[$index]);
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
	public function get_translations_script(): void {
		// backend-translations-JS.
		wp_enqueue_script(
			'easy-language-translations',
			plugins_url( '/classes/multilingual-plugins/easy-language/admin/translations.js', EASY_LANGUAGE ),
			array( 'jquery' ),
			filemtime( plugin_dir_path(EASY_LANGUAGE) . '/classes/multilingual-plugins/easy-language/admin/translations.js' ),
			true
		);

		// add php-vars to our translations-js-script.
		wp_localize_script(
			'easy-language-translations',
			'easyLanguageTranslationsJsVars',
			array(
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
				'label_translate_is_running' => __( 'Translation in progress', 'easy-language' ),
				'label_ok' => __( 'OK', 'easy-language' ),
				'txt_please_wait' => __( 'Please wait', 'easy-language' ),
				'run_translate_nonce' => wp_create_nonce( 'easy-language-translate-start-nonce' ),
				'get_translate_nonce' => wp_create_nonce( 'easy-language-translate-get-nonce' ),
				'txt_translation_has_been_run' => __( 'Translation has been run.', 'easy-language' ),
			)
		);

		// embed necessary scripts for progressbar.
        // TODO lokal speichern?
		$wp_scripts = wp_scripts();
		wp_enqueue_script('jquery-ui-progressbar');
		wp_enqueue_script('jquery-ui-dialog');
		wp_enqueue_style(
			'easy-language-jquery-ui-styles',
			'https://code.jquery.com/ui/' . $wp_scripts->registered['jquery-ui-core']->ver . '/themes/smoothness/jquery-ui.min.css',
			false,
			'1.0.0',
			false
		);
	}

	/**
     * Embed our own scripts.
     *
	 * @return void
	 */
    public function admin_enqueue_scripts(): void {
	    // backend-JS.
	    wp_enqueue_script(
		    'easy-language-plugin-admin',
		    plugins_url( '/classes/multilingual-plugins/easy-language/admin/js.js', EASY_LANGUAGE ),
		    array( 'jquery' ),
		    filemtime( plugin_dir_path(EASY_LANGUAGE) . '/classes/multilingual-plugins/easy-language/admin/js.js' ),
		    true
	    );

	    // add php-vars to our backend-js-script.
	    wp_localize_script(
		    'easy-language-plugin-admin',
		    'easyLanguagePluginJsVars',
		    array(
			    'delete_confirmation_question' => __( 'Do you really want to delete this translated object?', 'easy-language' )
		    )
	    );
    }

	/**
     * Show list of translations.
     *
	 * @return void
	 */
    public function add_translations(): void {
        $translations = new Texts_Table();
        $translations->prepare_items();
	    ?>
            <div class="wrap">
                <div id="icon-users" class="icon32"></div>
                <h2><?php _e('Translations', 'easy-language'); ?></h2>
                <p><?php echo esc_html__( 'This table contains all by any API translated texts. The original texts will not be translated a second time.', ''); ?></p>
                <p><?php echo esc_html__( 'You can here delete the translations to force a new translation of the original text if requested. In objects saved translations will not be deleted.', 'easy-language' ); ?></p>
                <?php $translations->display(); ?>
            </div>
	    <?php
    }

	/**
	 * Validate the post-type-setting.
	 *
	 * @param $values
	 *
	 * @return array
	 */
	public function validate_post_types( $values ): array {
        if( is_null( $values ) ) {
            $values = array();
        }
		return $values;
	}

	/**
     * Validate the taxonomy-setting.
     *
	 * @param $values
	 *
	 * @return array
	 */
    public function validate_taxonomies( $values ): array {
	    if( is_null( $values ) ) {
		    $values = array();
	    }
        return $values;
    }

	/**
     * Show quota hint in backend tables.
     *
	 * @param mixed $api_obj
	 *
	 * @return void
	 */
	private function show_quota_hint( mixed $api_obj ): void {
		$quota_array = $api_obj->get_quota();
		$quota_percent = 0;
		if( !empty($quota_array['character_limit']) && $quota_array['character_limit'] > 0 ) {
			$quota_percent = absint($quota_array['character_spent']) / absint($quota_array['character_limit']);
		}
		if( $quota_percent > apply_filters( 'easy_language_quota_percent', 0.8 ) ) {
            /* translators: %1$d will be replaced by a percentage value between 0 and 100. */
			echo '<span class="dashicons dashicons-info-outline" title="'.esc_attr( sprintf( __('Quota for the used API is used for %1$d%%!', 'easy-language' ), round((float)$quota_percent * 100 ) ) ).'"></span>';
		}
	}

	/**
	 * Initialize the permalink refresh if languages changing.
	 *
	 * @param $value
	 *
	 * @return array
	 */
	public function change_langauges( $value ): array {
		Rewrite::get_instance()->set_refresh();
		return $value;
	}
}
