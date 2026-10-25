<?php
/**
 * File to register the WordPress Abilities of this plugin.
 *
 * The Abilities API is available since WordPress 6.9. Via an MCP plugin, these abilities
 * can be used by AI clients to read and edit the data of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\EasyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Api_Base;
use easyLanguage\Plugin\Apis;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Languages;
use easyLanguage\Plugin\Log;
use easyLanguage\Plugin\Settings;
use easyLanguage\Plugin\Setup;
use easySettingsForWordPress\Fields\Checkbox;
use easySettingsForWordPress\Fields\Radio;
use easySettingsForWordPress\Page;
use WP_Error;
use WP_Post;
use WP_Post_Type;
use WP_Query;
use WP_Taxonomy;

/**
 * Object to handle the WordPress Abilities of this plugin.
 */
class Abilities {
	/**
	 * Name of the ability category.
	 */
	public const CATEGORY = 'easy-language';

	/**
	 * Name of the option to enable the abilities.
	 */
	public const OPTION_ENABLED = 'easy_language_abilities_enabled';

	/**
	 * Name of the option for the ability mode ("read" or "all").
	 */
	public const OPTION_MODE = 'easy_language_abilities_mode';

	/**
	 * Name of the cron hook which runs a simplification requested via ability.
	 */
	public const CRON_HOOK = 'easy_language_ability_run_simplification';

	/**
	 * Name of the object marker which holds the user who requested a simplification via ability.
	 */
	private const MARKER_USER = 'easy_language_ability_user';

	/**
	 * Maximum entries per page in lists.
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * Instance of this object.
	 *
	 * @var ?Abilities
	 */
	private static ?Abilities $instance = null;

	/**
	 * Constructor for this object.
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
	public static function get_instance(): Abilities {
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
		// add the settings.
		add_action( 'init', array( $this, 'add_settings' ), 40 );

		// register category and abilities (these hooks only exist since WordPress 6.9).
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );

		// run simplifications requested via ability in the background.
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled_simplification' ), 10, 2 );
	}

	/**
	 * Return whether the Abilities API is available in this WordPress.
	 *
	 * @return bool
	 */
	public function is_api_available(): bool {
		return function_exists( 'wp_register_ability' ) && function_exists( 'wp_register_ability_category' );
	}

	/**
	 * Return whether the abilities are enabled in the settings.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return 1 === absint( get_option( self::OPTION_ENABLED, 1 ) );
	}

	/**
	 * Return the configured mode: "read" for read-only abilities, "all" for all abilities.
	 *
	 * @return string
	 */
	public function get_mode(): string {
		return 'all' === get_option( self::OPTION_MODE, 'read' ) ? 'all' : 'read';
	}

	/**
	 * Save the default values of our settings if they do not exist in DB (e.g. after an update without new activation).
	 *
	 * Without this, WordPress would not save a setting if its value equals the registered default.
	 *
	 * @return void
	 */
	public function add_default_options(): void {
		foreach ( array(
			self::OPTION_ENABLED => 1,
			self::OPTION_MODE    => 'read',
		) as $option_name => $default_value ) {
			if ( false === get_option( $option_name, false ) ) {
				add_option( $option_name, $default_value, '', true );
			}
		}
	}

	/**
	 * Add the settings for the abilities.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// save the default values if the settings do not exist in DB.
		$this->add_default_options();

		// get the settings object.
		$settings_obj = Settings::get_instance()->get_settings_object();

		// get the settings page.
		$settings_page = $settings_obj->get_page( 'easy_language_settings' );

		// bail if the page is not available.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// add the tab.
		$abilities_tab = $settings_page->add_tab( 'abilities', 65 );
		$abilities_tab->set_title( __( 'Abilities', 'easy-language' ) );

		// add the section for the status.
		$status_section = $abilities_tab->add_section( 'easy_language_abilities_status', 10 );
		$status_section->set_title( __( 'Status', 'easy-language' ) );
		$status_section->set_callback( array( $this, 'render_status' ) );
		$status_section->set_setting( $settings_obj );

		// add the section for the settings.
		$settings_section = $abilities_tab->add_section( 'easy_language_abilities_settings', 20 );
		$settings_section->set_title( __( 'Settings', 'easy-language' ) );
		$settings_section->set_setting( $settings_obj );

		// add setting to enable the abilities.
		$enabled_setting = $settings_obj->add_setting( self::OPTION_ENABLED );
		$enabled_setting->set_section( $settings_section );
		$enabled_setting->set_type( 'integer' );
		$enabled_setting->set_default( 1 );
		$enabled_setting->set_show_in_rest( true );
		$field = new Checkbox( $settings_obj );
		$field->set_title( __( 'Enable abilities', 'easy-language' ) );
		$field->set_description( __( 'If enabled, Easy Language registers its abilities in WordPress. AI clients can then use them via an MCP plugin. Each ability can only be used by users with the necessary permissions.', 'easy-language' ) );
		$enabled_setting->set_field( $field );

		// add setting for the mode.
		$mode_setting = $settings_obj->add_setting( self::OPTION_MODE );
		$mode_setting->set_section( $settings_section );
		$mode_setting->set_type( 'string' );
		$mode_setting->set_default( 'read' );
		$mode_setting->set_show_in_rest( true );
		$field = new Radio( $settings_obj );
		$field->set_title( __( 'Allowed abilities', 'easy-language' ) );
		$field->set_options(
			array(
				'read' => array(
					'label'       => __( 'Read only', 'easy-language' ),
					'description' => __( 'AI clients can read settings, simplifications and texts. Nothing will be changed.', 'easy-language' ),
				),
				'all'  => array(
					'label'       => __( 'All', 'easy-language' ),
					'description' => __( 'AI clients can additionally create simplifications, save simplified texts, change the state of texts and start simplifications via the active API (this uses your quota).', 'easy-language' ),
				),
			)
		);
		$field->add_depend( $enabled_setting, 1 );
		$mode_setting->set_field( $field );
	}

	/**
	 * Return the results of the checks whether this project could use the abilities.
	 *
	 * Each entry has a "label", a "state" ("ok", "warning" or "error") and a "message".
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_checks(): array {
		$checks = array();

		// check the WordPress version.
		if ( $this->is_api_available() ) {
			$checks[] = array(
				'label'   => __( 'Abilities API', 'easy-language' ),
				'state'   => 'ok',
				/* translators: %1$s will be replaced by the WordPress version. */
				'message' => sprintf( __( 'Available in WordPress %1$s.', 'easy-language' ), get_bloginfo( 'version' ) ),
			);
		} else {
			$checks[] = array(
				'label'   => __( 'Abilities API', 'easy-language' ),
				'state'   => 'error',
				/* translators: %1$s will be replaced by the WordPress version. */
				'message' => sprintf( __( 'Not available. The Abilities API requires WordPress 6.9 or newer, this project uses WordPress %1$s.', 'easy-language' ), get_bloginfo( 'version' ) ),
			);
		}

		// check the setting.
		if ( ! $this->is_enabled() ) {
			$checks[] = array(
				'label'   => __( 'Abilities of Easy Language', 'easy-language' ),
				'state'   => 'warning',
				'message' => __( 'Disabled in the settings below.', 'easy-language' ),
			);
		} elseif ( $this->is_api_available() ) {
			// count the registered abilities.
			$registered = 0;
			if ( function_exists( 'wp_get_abilities' ) ) {
				foreach ( wp_get_abilities() as $ability ) {
					if ( str_starts_with( $ability->get_name(), self::CATEGORY . '/' ) ) {
						++$registered;
					}
				}
			}

			$checks[] = array(
				'label'   => __( 'Abilities of Easy Language', 'easy-language' ),
				'state'   => $registered > 0 ? 'ok' : 'error',
				'message' => $registered > 0
					/* translators: %1$d will be replaced by a number, %2$s by the mode. */
					? sprintf( _n( '%1$d ability registered (%2$s).', '%1$d abilities registered (%2$s).', $registered, 'easy-language' ), $registered, 'all' === $this->get_mode() ? __( 'all', 'easy-language' ) : __( 'read only', 'easy-language' ) )
					: __( 'No abilities could be registered. Please check the logs of Easy Language.', 'easy-language' ),
			);
		}

		// check the setup.
		$checks[] = array(
			'label'   => __( 'Plugin setup', 'easy-language' ),
			'state'   => Setup::get_instance()->is_completed() ? 'ok' : 'warning',
			'message' => Setup::get_instance()->is_completed() ? __( 'Completed.', 'easy-language' ) : __( 'The setup of Easy Language has not been completed yet.', 'easy-language' ),
		);

		// check the supported post types.
		$post_types = $this->get_supported_post_types();
		$checks[]   = array(
			'label'   => __( 'Supported post types', 'easy-language' ),
			'state'   => empty( $post_types ) ? 'error' : 'ok',
			'message' => empty( $post_types ) ? __( 'No post type is enabled for simplifications.', 'easy-language' ) : implode( ', ', $post_types ),
		);

		// show a hint for post types which are enabled in the settings but not registered in WordPress.
		$unknown_post_types = array_diff( $this->get_enabled_post_types(), $post_types );
		if ( ! empty( $unknown_post_types ) ) {
			$checks[] = array(
				'label'   => __( 'Unknown post types', 'easy-language' ),
				'state'   => 'warning',
				/* translators: %1$s will be replaced by a list of post type names. */
				'message' => sprintf( __( 'The settings contain post types which are not registered in WordPress and will be ignored: %1$s. Save the general settings of Easy Language to remove them.', 'easy-language' ), implode( ', ', $unknown_post_types ) ),
			);
		}

		// check the target languages.
		$languages = Languages::get_instance()->get_active_languages();
		$checks[]  = array(
			'label'   => __( 'Target languages', 'easy-language' ),
			'state'   => empty( $languages ) ? 'error' : 'ok',
			'message' => empty( $languages ) ? __( 'No target language is active.', 'easy-language' ) : implode( ', ', wp_list_pluck( $languages, 'label' ) ),
		);

		// check the API (only necessary to start simplifications via the API).
		$api_obj = Apis::get_instance()->get_active_api();
		if ( ! $api_obj instanceof Api_Base || 'no_api' === $api_obj->get_name() ) {
			$checks[] = array(
				'label'   => __( 'API for simplifications', 'easy-language' ),
				'state'   => 'warning',
				'message' => __( 'No API for automatic simplifications is active. AI clients can still save their own simplifications.', 'easy-language' ),
			);
		} else {
			$checks[] = array(
				'label'   => __( 'API for simplifications', 'easy-language' ),
				'state'   => $api_obj->is_configured() ? 'ok' : 'warning',
				/* translators: %1$s will be replaced by the API title. */
				'message' => $api_obj->is_configured() ? sprintf( __( '%1$s is active and configured.', 'easy-language' ), $api_obj->get_title() ) : sprintf( __( '%1$s is active but not configured.', 'easy-language' ), $api_obj->get_title() ),
			);
		}

		// check application passwords, which are used by most remote MCP connections.
		$app_passwords = function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available();
		$checks[]      = array(
			'label'   => __( 'Application passwords', 'easy-language' ),
			'state'   => $app_passwords ? 'ok' : 'warning',
			'message' => $app_passwords ? __( 'Available. Remote AI clients can authenticate with an application password.', 'easy-language' ) : __( 'Not available (they require HTTPS or a local environment). Remote AI clients may not be able to authenticate.', 'easy-language' ),
		);

		$instance = $this;
		/**
		 * Filter the checks for the abilities.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param array<int,array<string,string>> $checks List of checks.
		 * @param Abilities $instance The abilities object.
		 */
		return apply_filters( 'easy_language_abilities_checks', $checks, $instance );
	}

	/**
	 * Show the status of the abilities in the settings.
	 *
	 * @return void
	 */
	public function render_status(): void {
		// get the checks.
		$checks = $this->get_checks();

		// get the overall state.
		$has_error = in_array( 'error', wp_list_pluck( $checks, 'state' ), true );

		// show the overall hint.
		if ( $has_error ) {
			echo '<p><strong>' . esc_html__( 'This project can not use the abilities of Easy Language at the moment.', 'easy-language' ) . '</strong></p>';
		} else {
			echo '<p><strong>' . esc_html__( 'This project can use the abilities of Easy Language.', 'easy-language' ) . '</strong></p>';
		}

		// show the list.
		$icons  = array(
			'ok'      => 'dashicons-yes-alt',
			'warning' => 'dashicons-warning',
			'error'   => 'dashicons-dismiss',
		);
		$colors = array(
			'ok'      => '#00a32a',
			'warning' => '#dba617',
			'error'   => '#d63638',
		);
		echo '<table class="widefat striped" style="max-width: 900px"><tbody>';
		foreach ( $checks as $check ) {
			$state = isset( $icons[ $check['state'] ] ) ? $check['state'] : 'warning';
			echo '<tr>';
			echo '<td style="width: 30px"><span class="dashicons ' . esc_attr( $icons[ $state ] ) . '" style="color: ' . esc_attr( $colors[ $state ] ) . '"></span></td>';
			echo '<th scope="row" style="width: 220px">' . esc_html( $check['label'] ) . '</th>';
			echo '<td>' . esc_html( $check['message'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		// show the hint about MCP.
		echo '<p>' . esc_html__( 'Abilities describe what an AI client may do in this project. To use them with an AI (for example Claude or ChatGPT), you also need an MCP plugin which provides the abilities of WordPress as tools via the Model Context Protocol (MCP). Easy Language marks its abilities as public for MCP.', 'easy-language' ) . '</p>';
	}

	/**
	 * Register the category for our abilities.
	 *
	 * @return void
	 */
	public function register_category(): void {
		// bail if the API is not available.
		if ( ! $this->is_api_available() ) {
			return;
		}

		// bail if abilities are disabled.
		if ( ! $this->is_enabled() ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Easy Language', 'easy-language' ),
				'description' => __( 'Abilities to read and edit simplifications of contents into easy or plain language (Leichte Sprache, Einfache Sprache).', 'easy-language' ),
			)
		);
	}

	/**
	 * Register our abilities depending on the settings.
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		// bail if the API is not available.
		if ( ! $this->is_api_available() ) {
			return;
		}

		// bail if abilities are disabled.
		if ( ! $this->is_enabled() ) {
			return;
		}

		// get the mode.
		$mode = $this->get_mode();

		// register each ability.
		foreach ( $this->get_ability_definitions() as $name => $args ) {
			// bail if this ability changes data and only read-only abilities are allowed.
			if ( 'read' === $mode && empty( $args['meta']['annotations']['readonly'] ) ) {
				continue;
			}

			wp_register_ability( strtolower( self::CATEGORY . '/' . $name ), $args );
		}
	}

	/**
	 * Return the definitions of all abilities of this plugin.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_ability_definitions(): array {
		// define the schema for a single language entry.
		$language_schema = array(
			'type'       => 'object',
			'properties' => array(
				'code'  => array( 'type' => 'string' ),
				'label' => array( 'type' => 'string' ),
			),
		);

		// define the schema for the object reference input.
		$object_input = array(
			'object_id'   => array(
				'type'        => 'integer',
				'description' => __( 'ID of the post, page or term.', 'easy-language' ),
				'minimum'     => 1,
			),
			'object_type' => array(
				'type'        => 'string',
				'description' => __( 'Post type or taxonomy of the object. Optional for posts, required for terms.', 'easy-language' ),
			),
		);

		// define the schema for an object reference in results.
		$object_schema = array(
			'type'       => 'object',
			'properties' => array(
				'object_id'   => array( 'type' => 'integer' ),
				'object_type' => array( 'type' => 'string' ),
				'title'       => array( 'type' => 'string' ),
				'status'      => array( 'type' => 'string' ),
				'link'        => array( 'type' => 'string' ),
				'edit_link'   => array( 'type' => 'string' ),
			),
		);

		$definitions = array(
			'get-configuration'          => array(
				'label'               => __( 'Get Easy Language configuration', 'easy-language' ),
				'description'         => __( 'Returns the configuration of the plugin Easy Language: the active API for automatic simplifications incl. its quota, the source and target languages (e.g. de_LS for Leichte Sprache, de_EL for Einfache Sprache), the supported post types and taxonomies and the settings for automatic simplifications. Use this first to learn which language codes and post types can be used with the other abilities.', 'easy-language' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'api'                      => array( 'type' => array( 'object', 'null' ) ),
						'source_languages'         => array(
							'type'  => 'array',
							'items' => $language_schema,
						),
						'target_languages'         => array(
							'type'  => 'array',
							'items' => $language_schema,
						),
						'post_types'               => array( 'type' => 'array' ),
						'taxonomies'               => array( 'type' => 'array' ),
						'automatic_simplification' => array( 'type' => 'object' ),
						'abilities_mode'           => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_get_configuration' ),
				'permission_callback' => array( $this, 'permission_simplifier' ),
				'meta'                => $this->get_meta( true, false, true ),
			),
			'get-object-simplifications' => array(
				'label'               => __( 'Get simplifications of an object', 'easy-language' ),
				'description'         => __( 'Returns for a post, page or term (original or simplified version) its source language and for each active target language whether a simplified version exists, its ID, links, state, whether the original has been changed since (outdated), whether a simplification is running or scheduled and how many of its texts are still to simplify.', 'easy-language' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => $object_input,
					'required'             => array( 'object_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'original'        => $object_schema,
						'source_language' => array( 'type' => 'string' ),
						'simplifications' => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_get_object_simplifications' ),
				'permission_callback' => array( $this, 'permission_object' ),
				'meta'                => $this->get_meta( true, false, true ),
			),
			'find-objects'               => array(
				'label'               => __( 'Find objects by simplification state', 'easy-language' ),
				'description'         => __( 'Lists original posts and pages of the supported post types filtered by their simplification state: "missing" (no simplification in the given language), "simplified", "outdated" (the original has been changed after the simplification) or "all". Without a language, all active target languages are considered. The result is paginated.', 'easy-language' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'filter'    => array(
							'type'    => 'string',
							'enum'    => array( 'all', 'missing', 'simplified', 'outdated' ),
							'default' => 'missing',
						),
						'language'  => array(
							'type'        => 'string',
							'description' => __( 'Code of the target language, e.g. de_LS.', 'easy-language' ),
						),
						'post_type' => array(
							'type'        => 'string',
							'description' => __( 'Limit the result to one supported post type.', 'easy-language' ),
						),
						'search'    => array( 'type' => 'string' ),
						'page'      => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page'  => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => self::MAX_PER_PAGE,
							'default' => 20,
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'total'       => array( 'type' => 'integer' ),
						'total_pages' => array( 'type' => 'integer' ),
						'page'        => array( 'type' => 'integer' ),
						'items'       => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_find_objects' ),
				'permission_callback' => array( $this, 'permission_simplifier' ),
				'meta'                => $this->get_meta( true, false, true ),
			),
			'list-texts'                 => array(
				'label'               => __( 'List texts for simplification', 'easy-language' ),
				'description'         => __( 'Lists the single texts (title and content parts) Easy Language manages for simplifications, with original, source language, state ("to_simplify", "processing", "in_use", "ignore"), whether it contains HTML, its existing simplifications and the objects using it. Filter by the ID of a simplified object and state "to_simplify" to get the texts you still have to simplify for this object. The result is paginated.', 'easy-language' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array_merge(
						$object_input,
						array(
							'state'    => array(
								'type' => 'string',
								'enum' => array( 'to_simplify', 'processing', 'in_use', 'ignore' ),
							),
							'page'     => array(
								'type'    => 'integer',
								'minimum' => 1,
								'default' => 1,
							),
							'per_page' => array(
								'type'    => 'integer',
								'minimum' => 1,
								'maximum' => self::MAX_PER_PAGE,
								'default' => 20,
							),
						)
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'total'       => array( 'type' => 'integer' ),
						'total_pages' => array( 'type' => 'integer' ),
						'page'        => array( 'type' => 'integer' ),
						'items'       => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_list_texts' ),
				'permission_callback' => array( $this, 'permission_list_texts' ),
				'meta'                => $this->get_meta( true, false, true ),
			),
			'create-simplification'      => array(
				'label'               => __( 'Create a simplification of an object', 'easy-language' ),
				'description'         => __( 'Creates a simplified version of an original post, page or term in the given target language. The new object is a copy of the original, its texts are registered for simplification. No API is called and no quota is used. Afterwards either simplify the texts yourself (list-texts and save-text-simplification) or start the simplification via the active API (run-simplification).', 'easy-language' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array_merge(
						$object_input,
						array(
							'language' => array(
								'type'        => 'string',
								'description' => __( 'Code of the target language, e.g. de_LS.', 'easy-language' ),
							),
							'prevent_automatic_simplification' => array(
								'type'        => 'boolean',
								'description' => __( 'If true, the texts of the new object will not be simplified by the automatic background simplification of the plugin.', 'easy-language' ),
								'default'     => true,
							),
						)
					),
					'required'             => array( 'object_id', 'language' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'simplification'    => $object_schema,
						'language'          => array( 'type' => 'string' ),
						'texts_to_simplify' => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_create_simplification' ),
				'permission_callback' => array( $this, 'permission_object' ),
				'meta'                => $this->get_meta( false, false, false ),
			),
			'save-text-simplification'   => array(
				'label'               => __( 'Save the simplification of a text', 'easy-language' ),
				'description'         => __( 'Saves your own simplification of a single text for a simplified object and inserts it into this object. The target language is the language of the simplified object. Keep the HTML structure if the text contains HTML. If the text already has a simplification in this language, it will be used unless "overwrite" is true.', 'easy-language' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array_merge(
						$object_input,
						array(
							'text_id'        => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'simplification' => array(
								'type'      => 'string',
								'minLength' => 1,
							),
							'overwrite'      => array(
								'type'    => 'boolean',
								'default' => false,
							),
						)
					),
					'required'             => array( 'object_id', 'text_id', 'simplification' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'text_id'           => array( 'type' => 'integer' ),
						'object_id'         => array( 'type' => 'integer' ),
						'language'          => array( 'type' => 'string' ),
						'used_existing'     => array( 'type' => 'boolean' ),
						'texts_to_simplify' => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_save_text_simplification' ),
				'permission_callback' => array( $this, 'permission_object' ),
				'meta'                => $this->get_meta( false, false, true ),
			),
			'set-text-state'             => array(
				'label'               => __( 'Set the state of a text', 'easy-language' ),
				'description'         => __( 'Sets the state of a text of a simplified object to "ignore" (will not be simplified) or "to_simplify" (e.g. to reset a failed simplification).', 'easy-language' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array_merge(
						$object_input,
						array(
							'text_id' => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'state'   => array(
								'type' => 'string',
								'enum' => array( 'to_simplify', 'ignore' ),
							),
						)
					),
					'required'             => array( 'object_id', 'text_id', 'state' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'text_id' => array( 'type' => 'integer' ),
						'state'   => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_set_text_state' ),
				'permission_callback' => array( $this, 'permission_object' ),
				'meta'                => $this->get_meta( false, false, true ),
			),
			'set-automatic-prevention'   => array(
				'label'               => __( 'Prevent automatic simplification', 'easy-language' ),
				'description'         => __( 'Enables or disables the automatic background simplification for a simplified post or page.', 'easy-language' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array_merge(
						$object_input,
						array(
							'prevent' => array( 'type' => 'boolean' ),
						)
					),
					'required'             => array( 'object_id', 'prevent' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'object_id' => array( 'type' => 'integer' ),
						'prevent'   => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_set_automatic_prevention' ),
				'permission_callback' => array( $this, 'permission_object' ),
				'meta'                => $this->get_meta( false, false, true ),
			),
			'run-simplification'         => array(
				'label'               => __( 'Run simplification via API', 'easy-language' ),
				'description'         => __( 'Schedules the simplification of all open texts of a simplified object via the active API (e.g. SUMM AI, capito or ChatGPT). This uses the quota of the API and runs in the background. Check the progress with get-object-simplifications.', 'easy-language' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => $object_input,
					'required'             => array( 'object_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'status'            => array( 'type' => 'string' ),
						'object_id'         => array( 'type' => 'integer' ),
						'api'               => array( 'type' => 'string' ),
						'texts_to_simplify' => array( 'type' => 'integer' ),
						'quota_state'       => array( 'type' => 'object' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_run_simplification' ),
				'permission_callback' => array( $this, 'permission_object' ),
				'meta'                => $this->get_meta( false, false, false ),
			),
		);

		// add the category to each definition.
		foreach ( $definitions as $name => $args ) {
			$definitions[ $name ]['category'] = self::CATEGORY;
		}

		$instance = $this;
		/**
		 * Filter the definitions of the abilities of Easy Language.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param array<string,array<string,mixed>> $definitions List of ability definitions (name without namespace => arguments).
		 * @param Abilities $instance The abilities object.
		 */
		return apply_filters( 'easy_language_abilities', $definitions, $instance );
	}

	/**
	 * Return the meta for an ability.
	 *
	 * @param bool $is_readonly True if the ability does not change anything.
	 * @param bool $destructive True if the ability could delete or overwrite data.
	 * @param bool $idempotent True if repeated calls with the same input have no additional effect.
	 *
	 * @return array<string,mixed>
	 */
	private function get_meta( bool $is_readonly, bool $destructive, bool $idempotent ): array {
		return array(
			'annotations'  => array(
				'readonly'    => $is_readonly,
				'destructive' => $destructive,
				'idempotent'  => $idempotent,
			),
			'show_in_rest' => true,
			'mcp'          => array(
				'public' => true,
			),
		);
	}

	/**
	 * Permission: the user must be allowed to simplify.
	 *
	 * @return bool
	 */
	public function permission_simplifier(): bool {
		return current_user_can( 'edit_el_simplifier' );
	}

	/**
	 * Permission: the user must be allowed to simplify and to edit the requested object.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return bool
	 */
	public function permission_object( mixed $input = null ): bool {
		// bail if user is not allowed to simplify.
		if ( ! $this->permission_simplifier() ) {
			return false;
		}

		// get the object reference.
		$reference = $this->get_object_reference( $input );

		// check the object.
		return Init::get_instance()->current_user_can_edit_object( $reference['object_id'], $reference['object_type'] );
	}

	/**
	 * Permission for the text list: if an object is given, the user must be allowed to edit it.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return bool
	 */
	public function permission_list_texts( mixed $input = null ): bool {
		// bail if user is not allowed to simplify.
		if ( ! $this->permission_simplifier() ) {
			return false;
		}

		// bail if no object is requested.
		if ( ! is_array( $input ) || empty( $input['object_id'] ) ) {
			return true;
		}

		// check the object.
		return $this->permission_object( $input );
	}

	/**
	 * Return the object ID and type from the input.
	 *
	 * If no type is given, the post type of the given ID is used.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return array{object_id: int, object_type: string}
	 */
	private function get_object_reference( mixed $input ): array {
		// get the ID.
		$object_id = is_array( $input ) && isset( $input['object_id'] ) ? absint( $input['object_id'] ) : 0;

		// get the type.
		$object_type = is_array( $input ) && ! empty( $input['object_type'] ) ? sanitize_key( $input['object_type'] ) : '';

		// use the post type if no type is given.
		if ( '' === $object_type && $object_id > 0 ) {
			$object_type = (string) get_post_type( $object_id );
		}

		// return the reference.
		return array(
			'object_id'   => $object_id,
			'object_type' => $object_type,
		);
	}

	/**
	 * Return the object for the given input.
	 *
	 * @param mixed $input The input of the ability.
	 *
	 * @return Objects|WP_Error
	 */
	private function get_object_by_input( mixed $input ): Objects|WP_Error {
		// get the reference.
		$reference = $this->get_object_reference( $input );

		// get the object.
		$object = Helper::get_object( $reference['object_id'], $reference['object_type'] );

		// bail if object could not be loaded.
		if ( ! $object instanceof Objects ) {
			return new WP_Error( 'easy_language_object_not_found', __( 'The requested object could not be found or is not supported by Easy Language.', 'easy-language' ) );
		}

		// return the object.
		return $object;
	}

	/**
	 * Return the base data of an object for results.
	 *
	 * @param Objects $item The object.
	 *
	 * @return array<string,int|string>
	 */
	private function get_object_data( Objects $item ): array {
		return array(
			'object_id'   => $item->get_id(),
			'object_type' => $item->get_type(),
			'title'       => html_entity_decode( $item->get_title(), ENT_QUOTES ),
			'status'      => $item->get_status(),
			'link'        => $item->get_link(),
			'edit_link'   => html_entity_decode( $item->get_edit_link(), ENT_QUOTES ),
		);
	}

	/**
	 * Return the given language list as list of code and label.
	 *
	 * @param array<string,array<string,mixed>> $languages The languages.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_language_list( array $languages ): array {
		$list = array();
		foreach ( $languages as $code => $settings ) {
			$list[] = array(
				'code'  => (string) $code,
				'label' => isset( $settings['label'] ) ? (string) $settings['label'] : (string) $code,
			);
		}
		return $list;
	}

	/**
	 * Return the names of the post types which are enabled in the settings.
	 *
	 * @return array<int,string>
	 */
	private function get_enabled_post_types(): array {
		return array_map( 'strval', array_keys( array_filter( Init::get_instance()->get_supported_post_types() ) ) );
	}

	/**
	 * Return the names of the post types which are enabled in the settings and registered in WordPress.
	 *
	 * @return array<int,string>
	 */
	private function get_supported_post_types(): array {
		return array_values( array_filter( $this->get_enabled_post_types(), 'post_type_exists' ) );
	}

	/**
	 * Return the active target language codes.
	 *
	 * @return array<int,string>
	 */
	private function get_active_language_codes(): array {
		return array_map( 'strval', array_keys( Languages::get_instance()->get_active_languages() ) );
	}

	/**
	 * Return the number of texts of an object in the given state.
	 *
	 * @param Objects $item The object.
	 * @param string  $state The state.
	 *
	 * @return int
	 */
	private function count_texts( Objects $item, string $state ): int {
		return Db::get_instance()->count_entries(
			array(
				'object_id'   => $item->get_id(),
				'object_type' => $item->get_type(),
				'state'       => $state,
			)
		);
	}

	/**
	 * Execute: return the configuration.
	 *
	 * @return array<string,mixed>
	 */
	public function execute_get_configuration(): array {
		// get the active API.
		$api_obj  = Apis::get_instance()->get_active_api();
		$api_data = null;
		if ( $api_obj instanceof Api_Base ) {
			$quota    = $api_obj->get_quota();
			$api_data = array(
				'name'       => $api_obj->get_name(),
				'title'      => $api_obj->get_title(),
				'configured' => $api_obj->is_configured(),
				'test_mode'  => $api_obj->is_test_mode_active(),
				'quota'      => array(
					'character_spent' => isset( $quota['character_spent'] ) ? absint( $quota['character_spent'] ) : 0,
					'character_limit' => isset( $quota['character_limit'] ) ? absint( $quota['character_limit'] ) : 0,
					'unlimited'       => ! empty( $quota['unlimited'] ),
				),
			);
		}

		// get the source languages.
		$source_languages = $api_obj instanceof Api_Base ? $api_obj->get_active_source_languages() : Languages::get_instance()->get_possible_source_languages();

		// get the post types.
		$post_types = array();
		foreach ( $this->get_supported_post_types() as $post_type ) {
			$post_type_obj = get_post_type_object( $post_type );
			$post_types[]  = array(
				'name'  => $post_type,
				'label' => $post_type_obj instanceof WP_Post_Type ? $post_type_obj->label : $post_type,
			);
		}

		// get the taxonomies (only enabled ones which are registered in WordPress).
		$taxonomies = array();
		foreach ( Init::get_instance()->get_supported_taxonomies() as $taxonomy => $enabled ) {
			$taxonomy_obj = get_taxonomy( (string) $taxonomy );
			if ( empty( $enabled ) || ! $taxonomy_obj instanceof WP_Taxonomy ) {
				continue;
			}
			$taxonomies[] = array(
				'name'  => (string) $taxonomy,
				'label' => $taxonomy_obj->label,
			);
		}

		// return the configuration.
		return array(
			'api'                      => $api_data,
			'source_languages'         => $this->get_language_list( $source_languages ),
			'target_languages'         => $this->get_language_list( Languages::get_instance()->get_active_languages() ),
			'post_types'               => $post_types,
			'taxonomies'               => $taxonomies,
			'automatic_simplification' => array(
				'enabled'  => 1 === absint( get_option( 'easy_language_automatic_simplification_enabled', 1 ) ),
				'interval' => (string) get_option( 'easy_language_automatic_simplification', '' ),
				'per_run'  => absint( get_option( 'easy_language_automatic_item_count', 6 ) ),
			),
			'abilities_mode'           => $this->get_mode(),
		);
	}

	/**
	 * Execute: return the simplifications of an object.
	 *
	 * @param mixed $input The input.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute_get_object_simplifications( mixed $input = null ): array|WP_Error {
		// get the object.
		$object = $this->get_object_by_input( $input );
		if ( $object instanceof WP_Error ) {
			return $object;
		}

		// if this is a simplified object, use its original.
		if ( $object->is_simplified() ) {
			$original = Helper::get_object( $object->get_original_object_as_int(), $object->get_type() );
			if ( ! $original instanceof Objects ) {
				return new WP_Error( 'easy_language_original_not_found', __( 'The original of this simplified object could not be found.', 'easy-language' ) );
			}
			$object = $original;
		}

		// collect the simplifications.
		$simplifications = array();
		foreach ( Languages::get_instance()->get_active_languages() as $language_code => $settings ) {
			// get the simplified object in this language.
			$simplified_id = $object->get_simplification_in_language( (string) $language_code );

			// collect the base data.
			$entry = array(
				'language'       => (string) $language_code,
				'language_label' => isset( $settings['label'] ) ? (string) $settings['label'] : (string) $language_code,
				'exists'         => $simplified_id > 0,
				'outdated'       => method_exists( $object, 'has_changed' ) && $simplified_id > 0 && $object->has_changed( (string) $language_code ),
			);

			// add the details of the simplified object.
			$simplified_object = $simplified_id > 0 ? Helper::get_object( $simplified_id, $object->get_type() ) : false;
			if ( $simplified_object instanceof Objects ) {
				$entry = array_merge( $entry, $this->get_object_data( $simplified_object ) );

				$entry['automatic_simplification_prevented'] = $simplified_object->is_automatic_mode_prevented();
				$entry['running']                            = $simplified_object->is_simplification_running();
				$entry['scheduled']                          = false !== wp_next_scheduled( self::CRON_HOOK, array( $simplified_object->get_id(), $simplified_object->get_type() ) );
				$entry['texts']                              = array(
					'to_simplify' => $this->count_texts( $simplified_object, 'to_simplify' ),
					'processing'  => $this->count_texts( $simplified_object, 'processing' ),
					'in_use'      => $this->count_texts( $simplified_object, 'in_use' ),
					'ignore'      => $this->count_texts( $simplified_object, 'ignore' ),
				);

				// add the last result of a simplification via API, if available.
				$result = $simplified_object->get_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RESULTS );
				if ( is_array( $result ) && ! empty( $result['title'] ) ) {
					$entry['last_result'] = array(
						'title'   => (string) $result['title'],
						'message' => trim( wp_strip_all_tags( implode( ' ', array_map( 'strval', (array) ( $result['texts'] ?? array() ) ) ) ) ),
					);
				}
			}

			// add the entry.
			$simplifications[] = $entry;
		}

		// get the source language.
		$source_language = (string) array_key_first( $object->get_language() );
		if ( '' === $source_language ) {
			$source_language = Helper::get_lang_of_object( $object->get_id(), $object->get_type() );
		}

		// return the result.
		return array(
			'original'        => $this->get_object_data( $object ),
			'source_language' => $source_language,
			'simplifications' => $simplifications,
		);
	}

	/**
	 * Execute: find objects by their simplification state.
	 *
	 * @param mixed $input The input.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute_find_objects( mixed $input = null ): array|WP_Error {
		$input = is_array( $input ) ? $input : array();

		// get the filter.
		$filter = isset( $input['filter'] ) ? (string) $input['filter'] : 'missing';

		// get the post types.
		$post_types = $this->get_supported_post_types();
		if ( ! empty( $input['post_type'] ) ) {
			// bail if the post type is not supported.
			if ( ! in_array( $input['post_type'], $post_types, true ) ) {
				return new WP_Error( 'easy_language_post_type_not_supported', __( 'The requested post type is not supported by Easy Language.', 'easy-language' ) );
			}
			$post_types = array( (string) $input['post_type'] );
		}

		// bail if no post type is supported.
		if ( empty( $post_types ) ) {
			return new WP_Error( 'easy_language_no_post_types', __( 'No post type is enabled for simplifications.', 'easy-language' ) );
		}

		// get the languages.
		$languages = $this->get_active_language_codes();
		if ( ! empty( $input['language'] ) ) {
			// bail if language is not active.
			if ( ! in_array( $input['language'], $languages, true ) ) {
				return new WP_Error( 'easy_language_language_not_active', __( 'The requested language is not active.', 'easy-language' ) );
			}
			$languages = array( (string) $input['language'] );
		}

		// define the meta query: only originals.
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => 'easy_language_simplification_original_id',
				'compare' => 'NOT EXISTS',
			),
		);

		// add the filter for the simplification state.
		if ( 'missing' === $filter ) {
			$state_query = array(
				'relation' => 'OR',
				array(
					'key'     => 'easy_language_simplified_in',
					'compare' => 'NOT EXISTS',
				),
			);
			foreach ( $languages as $language_code ) {
				$state_query[] = array(
					'key'     => 'easy_language_simplified_in',
					'value'   => ',' . $language_code . ',',
					'compare' => 'NOT LIKE',
				);
			}
			$meta_query[] = $state_query;
		} elseif ( 'simplified' === $filter ) {
			$state_query = array( 'relation' => 'OR' );
			foreach ( $languages as $language_code ) {
				$state_query[] = array(
					'key'     => 'easy_language_simplified_in',
					'value'   => ',' . $language_code . ',',
					'compare' => 'LIKE',
				);
			}
			$meta_query[] = $state_query;
		} elseif ( 'outdated' === $filter ) {
			$state_query = array( 'relation' => 'OR' );
			foreach ( $languages as $language_code ) {
				$state_query[] = array(
					'key'   => 'easy_language_' . $language_code . '_changed',
					'value' => '1',
				);
			}
			$meta_query[] = $state_query;
		}

		// get the pagination.
		$page     = max( 1, absint( $input['page'] ?? 1 ) );
		$per_page = min( self::MAX_PER_PAGE, max( 1, absint( $input['per_page'] ?? 20 ) ) );

		// define the query.
		$query = array(
			'post_type'                       => $post_types,
			'post_status'                     => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the simplification state is only available via post meta
			'meta_query'                      => $meta_query,
			'posts_per_page'                  => $per_page,
			'paged'                           => $page,
			'orderby'                         => 'modified',
			'order'                           => 'DESC',
			'fields'                          => 'ids',
			'do_not_use_easy_language_filter' => '1',
		);
		if ( ! empty( $input['search'] ) ) {
			$query['s'] = sanitize_text_field( (string) $input['search'] );
		}
		$results = new WP_Query( $query );

		// collect the items.
		$items = array();
		foreach ( $results->get_posts() as $post_id ) {
			$post_id = absint( $post_id );

			// bail if the user is not allowed to edit this post.
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			// get the object.
			$object = new Post_Object( $post_id );

			// collect the state per language.
			$states = array();
			foreach ( $languages as $language_code ) {
				$simplified_id            = $object->get_simplification_in_language( $language_code );
				$states[ $language_code ] = array(
					'simplification_id' => $simplified_id,
					'outdated'          => $simplified_id > 0 && $object->has_changed( $language_code ),
				);
			}

			// add the item.
			$item              = $this->get_object_data( $object );
			$item['modified']  = (string) get_post_modified_time( 'c', true, $post_id );
			$item['languages'] = $states;
			$items[]           = $item;
		}

		// return the result.
		return array(
			'total'       => absint( $results->found_posts ),
			'total_pages' => absint( $results->max_num_pages ),
			'page'        => $page,
			'items'       => $items,
		);
	}

	/**
	 * Execute: list texts.
	 *
	 * @param mixed $input The input.
	 *
	 * @return array<string,mixed>
	 */
	public function execute_list_texts( mixed $input = null ): array {
		$input = is_array( $input ) ? $input : array();

		// build the filter.
		$filter = array();
		if ( ! empty( $input['object_id'] ) ) {
			$reference             = $this->get_object_reference( $input );
			$filter['object_id']   = $reference['object_id'];
			$filter['object_type'] = $reference['object_type'];
		}
		if ( ! empty( $input['state'] ) ) {
			$filter['state'] = (string) $input['state'];
		}

		// get the pagination.
		$page     = max( 1, absint( $input['page'] ?? 1 ) );
		$per_page = min( self::MAX_PER_PAGE, max( 1, absint( $input['per_page'] ?? 20 ) ) );

		// get the active languages.
		$languages = $this->get_active_language_codes();

		// get the entries.
		$db = Db::get_instance();
		if ( ! empty( $filter['object_id'] ) ) {
			// the user may edit the requested object (see permission check), so all its texts are visible: paginate via DB.
			$total   = $db->count_entries( $filter );
			$visible = array();
			foreach ( $db->get_entries( $filter, array(), $per_page, ( $page - 1 ) * $per_page ) as $entry ) {
				$visible[] = array(
					'entry'   => $entry,
					'objects' => $this->get_editable_objects_of_text( $entry ),
				);
			}
		} else {
			// only texts used by at least one object the user may edit are visible, so the total must be counted on these.
			$all_visible = array();
			foreach ( $db->get_entries( $filter ) as $entry ) {
				$objects = $this->get_editable_objects_of_text( $entry );
				if ( empty( $objects ) ) {
					continue;
				}
				$all_visible[] = array(
					'entry'   => $entry,
					'objects' => $objects,
				);
			}
			$total   = count( $all_visible );
			$visible = array_slice( $all_visible, ( $page - 1 ) * $per_page, $per_page );
		}

		// collect the items.
		$items = array();
		foreach ( $visible as $visible_entry ) {
			$entry = $visible_entry['entry'];

			// collect the simplifications.
			$simplifications = array();
			foreach ( $languages as $language_code ) {
				$simplification = $entry->get_raw_simplification( $language_code );
				if ( '' !== $simplification ) {
					$simplifications[ $language_code ] = $simplification;
				}
			}

			// add the item.
			$items[] = array(
				'text_id'         => $entry->get_id(),
				'original'        => $entry->get_original(),
				'source_language' => $entry->get_source_language(),
				'state'           => $entry->get_state(),
				'html'            => $entry->is_html(),
				'simplifications' => (object) $simplifications,
				'objects'         => $visible_entry['objects'],
			);
		}

		// return the result.
		return array(
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
			'page'        => $page,
			'items'       => $items,
		);
	}

	/**
	 * Return the objects using the given text which the actual user may edit.
	 *
	 * @param Text $entry The text.
	 *
	 * @return array<int,array<string,int|string>>
	 */
	private function get_editable_objects_of_text( Text $entry ): array {
		$objects = array();
		foreach ( $entry->get_objects() as $object_array ) {
			$object_id   = absint( $object_array['object_id'] );
			$object_type = (string) $object_array['object_type'];

			// bail if user could not edit this object.
			if ( ! Init::get_instance()->current_user_can_edit_object( $object_id, $object_type ) ) {
				continue;
			}

			$objects[] = array(
				'object_id'   => $object_id,
				'object_type' => $object_type,
				'field'       => (string) $object_array['field'],
			);
		}
		return $objects;
	}

	/**
	 * Execute: create a simplification of an object.
	 *
	 * @param mixed $input The input.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute_create_simplification( mixed $input = null ): array|WP_Error {
		$input = is_array( $input ) ? $input : array();

		// get the object.
		$object = $this->get_object_by_input( $input );
		if ( $object instanceof WP_Error ) {
			return $object;
		}

		// bail if this is not an original.
		if ( ! $object->is_simplifiable() ) {
			return new WP_Error( 'easy_language_not_simplifiable', __( 'This object is itself a simplification. Use the ID of the original object.', 'easy-language' ) );
		}

		// get the language.
		$language = isset( $input['language'] ) ? (string) $input['language'] : '';

		// bail if the language is not active.
		if ( ! in_array( $language, $this->get_active_language_codes(), true ) ) {
			return new WP_Error( 'easy_language_language_not_active', __( 'The requested language is not active.', 'easy-language' ) );
		}

		// bail if a simplification in this language already exists.
		$existing_id = $object->get_simplification_in_language( $language );
		if ( $existing_id > 0 ) {
			/* translators: %1$d will be replaced by an ID. */
			return new WP_Error( 'easy_language_simplification_exists', sprintf( __( 'A simplification in this language already exists with the ID %1$d.', 'easy-language' ), $existing_id ), array( 'object_id' => $existing_id ) );
		}

		// get the active API.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( ! $api_obj instanceof Api_Base ) {
			return new WP_Error( 'easy_language_no_api', __( 'No API is active in Easy Language.', 'easy-language' ) );
		}

		// create the simplified object.
		$copy = $object->add_simplification_object( $language, $api_obj, ! isset( $input['prevent_automatic_simplification'] ) || false !== $input['prevent_automatic_simplification'] );

		// bail if the copy could not be created.
		if ( ! $copy instanceof Objects ) {
			return new WP_Error( 'easy_language_create_failed', __( 'The simplification could not be created. Possibly the page builder of this object is not supported.', 'easy-language' ) );
		}

		// log this event.
		/* translators: %1$s will be replaced by a title, %2$s by a language code. */
		Log::get_instance()->add_log( sprintf( __( 'Simplification of %1$s in %2$s created via ability.', 'easy-language' ), '<i>' . esc_html( $object->get_title() ) . '</i>', esc_html( $language ) ), 'success' );

		// return the result.
		return array(
			'simplification'    => $this->get_object_data( $copy ),
			'language'          => $language,
			'texts_to_simplify' => $this->count_texts( $copy, 'to_simplify' ),
		);
	}

	/**
	 * Return the simplified object for the given input and its target language.
	 *
	 * @param mixed $input The input.
	 *
	 * @return array{object: Objects, language: string}|WP_Error
	 */
	private function get_simplified_object_by_input( mixed $input ): array|WP_Error {
		// get the object.
		$object = $this->get_object_by_input( $input );
		if ( $object instanceof WP_Error ) {
			return $object;
		}

		// bail if this is not a simplified object.
		if ( ! $object->is_simplified() ) {
			return new WP_Error( 'easy_language_not_simplified', __( 'This object is not a simplification. Use the ID of the simplified object (see get-object-simplifications).', 'easy-language' ) );
		}

		// get its language.
		$language = (string) array_key_first( $object->get_language() );

		// bail if the language is not active.
		if ( '' === $language ) {
			return new WP_Error( 'easy_language_language_not_active', __( 'The language of this simplified object is not active.', 'easy-language' ) );
		}

		// return the object and its language.
		return array(
			'object'   => $object,
			'language' => $language,
		);
	}

	/**
	 * Return the text with the given ID, if it is used by the given object.
	 *
	 * @param int     $text_id The ID of the text.
	 * @param Objects $item The object.
	 *
	 * @return Text|WP_Error
	 */
	private function get_text_of_object( int $text_id, Objects $item ): Text|WP_Error {
		// get the entries of this object with this ID.
		$entries = Db::get_instance()->get_entries(
			array(
				'id'          => $text_id,
				'object_id'   => $item->get_id(),
				'object_type' => $item->get_type(),
			),
			array(),
			1
		);

		// bail if the text is not used by this object.
		if ( empty( $entries ) ) {
			return new WP_Error( 'easy_language_text_not_found', __( 'The text is not used by this object.', 'easy-language' ) );
		}

		// return the text.
		return $entries[0];
	}

	/**
	 * Execute: save the simplification of a text.
	 *
	 * @param mixed $input The input.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute_save_text_simplification( mixed $input = null ): array|WP_Error {
		$input = is_array( $input ) ? $input : array();

		// get the simplified object.
		$result = $this->get_simplified_object_by_input( $input );
		if ( $result instanceof WP_Error ) {
			return $result;
		}
		$object   = $result['object'];
		$language = $result['language'];

		// get the text.
		$text = $this->get_text_of_object( absint( $input['text_id'] ?? 0 ), $object );
		if ( $text instanceof WP_Error ) {
			return $text;
		}

		// bail if a simplification of this object is running.
		if ( $object->is_simplification_running() ) {
			return new WP_Error( 'easy_language_simplification_running', __( 'A simplification of this object is actually running. Please try again later.', 'easy-language' ) );
		}

		// sanitize the simplification.
		$simplification = (string) ( $input['simplification'] ?? '' );
		$simplification = $text->is_html() ? wp_kses_post( $simplification ) : sanitize_textarea_field( $simplification );

		// bail if the simplification is empty.
		if ( '' === trim( $simplification ) ) {
			return new WP_Error( 'easy_language_empty_simplification', __( 'The simplification is empty.', 'easy-language' ) );
		}

		// check if an existing simplification should be used.
		$used_existing = $text->has_simplification_in_language( $language ) && empty( $input['overwrite'] );

		// mark the simplification as running to prevent the update-handling of the plugin during the replacement.
		$object->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, time() );

		// save the simplification.
		if ( ! $used_existing ) {
			$text->delete_simplification( $language );
			$text->set_simplification( $simplification, $language, 'no_api', 0 );
		}

		// replace the original text in the object with its simplification.
		$replaced = $text->replace_original_with_simplification( $object->get_id(), $language, $object->get_type() );

		// remove the running marker.
		$object->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, 0 );

		// bail if the text could not be replaced.
		if ( ! $replaced ) {
			return new WP_Error( 'easy_language_replace_failed', __( 'The simplification has been saved, but could not be inserted into the object. Possibly the page builder of this object is not supported.', 'easy-language' ) );
		}

		// mark the text as in use.
		$text->set_state( 'in_use' );

		// get the count of texts which are still to simplify.
		$to_simplify = $this->count_texts( $object, 'to_simplify' );

		// run the tasks after the end of a simplification if this was the last text.
		if ( 0 === $to_simplify ) {
			$object->run_simplification_end_trigger();
		}

		// return the result.
		return array(
			'text_id'           => $text->get_id(),
			'object_id'         => $object->get_id(),
			'language'          => $language,
			'used_existing'     => $used_existing,
			'texts_to_simplify' => $to_simplify,
		);
	}

	/**
	 * Execute: set the state of a text.
	 *
	 * @param mixed $input The input.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute_set_text_state( mixed $input = null ): array|WP_Error {
		$input = is_array( $input ) ? $input : array();

		// get the simplified object.
		$result = $this->get_simplified_object_by_input( $input );
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		// get the text.
		$text = $this->get_text_of_object( absint( $input['text_id'] ?? 0 ), $result['object'] );
		if ( $text instanceof WP_Error ) {
			return $text;
		}

		// get the state.
		$state = (string) ( $input['state'] ?? '' );
		if ( ! in_array( $state, array( 'to_simplify', 'ignore' ), true ) ) {
			return new WP_Error( 'easy_language_invalid_state', __( 'The requested state is not allowed.', 'easy-language' ) );
		}

		// set the state.
		$text->set_state( $state );

		// return the result.
		return array(
			'text_id' => $text->get_id(),
			'state'   => $text->get_state(),
		);
	}

	/**
	 * Execute: set the prevention of the automatic simplification.
	 *
	 * @param mixed $input The input.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute_set_automatic_prevention( mixed $input = null ): array|WP_Error {
		$input = is_array( $input ) ? $input : array();

		// get the simplified object.
		$result = $this->get_simplified_object_by_input( $input );
		if ( $result instanceof WP_Error ) {
			return $result;
		}
		$object = $result['object'];

		// bail if this is not a post object, as only these support this setting.
		if ( ! $object instanceof Post_Object ) {
			return new WP_Error( 'easy_language_not_supported', __( 'This setting is only available for posts and pages.', 'easy-language' ) );
		}

		// set the value.
		$object->set_automatic_mode_prevented( ! empty( $input['prevent'] ) );

		// return the result.
		return array(
			'object_id' => $object->get_id(),
			'prevent'   => $object->is_automatic_mode_prevented(),
		);
	}

	/**
	 * Execute: schedule the simplification of an object via the active API.
	 *
	 * @param mixed $input The input.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function execute_run_simplification( mixed $input = null ): array|WP_Error {
		// get the simplified object.
		$result = $this->get_simplified_object_by_input( $input );
		if ( $result instanceof WP_Error ) {
			return $result;
		}
		$object = $result['object'];

		// get the active API.
		$api_obj = Apis::get_instance()->get_active_api();

		// bail if no API for automatic simplifications is active.
		if ( ! $api_obj instanceof Api_Base || 'no_api' === $api_obj->get_name() ) {
			return new WP_Error( 'easy_language_no_api', __( 'No API for automatic simplifications is active in Easy Language.', 'easy-language' ) );
		}

		// bail if the API is not configured.
		if ( ! $api_obj->is_configured() ) {
			/* translators: %1$s will be replaced by the API title. */
			return new WP_Error( 'easy_language_api_not_configured', sprintf( __( 'The API %1$s is not configured.', 'easy-language' ), $api_obj->get_title() ) );
		}

		// define the arguments for the event.
		$args = array( $object->get_id(), $object->get_type() );

		// bail if a simplification is already running or scheduled.
		if ( $object->is_simplification_running() || false !== wp_next_scheduled( self::CRON_HOOK, $args ) ) {
			return new WP_Error( 'easy_language_simplification_running', __( 'A simplification of this object is already running or scheduled.', 'easy-language' ) );
		}

		// get the count of texts to simplify.
		$to_simplify = $this->count_texts( $object, 'to_simplify' );

		// get the quota state.
		$quota_state = $object->get_quota_state( $api_obj );

		// bail if nothing is to simplify.
		if ( 0 === $to_simplify ) {
			return array(
				'status'            => 'nothing_to_simplify',
				'object_id'         => $object->get_id(),
				'api'               => $api_obj->get_title(),
				'texts_to_simplify' => 0,
				'quota_state'       => $quota_state,
			);
		}

		// bail if the quota is used up: do not schedule anything.
		if ( Helper::is_quota_used_up( $api_obj ) ) {
			/* translators: %1$s will be replaced by the API title. */
			return new WP_Error( 'easy_language_quota_exceeded', sprintf( __( 'The quota of %1$s is used up. Nothing has been simplified.', 'easy-language' ), $api_obj->get_title() ), array( 'quota_state' => $quota_state ) );
		}

		// save the requesting user for the background process.
		update_option( $object->get_marker_option_name( self::MARKER_USER ), get_current_user_id(), false );

		// schedule the simplification.
		$scheduled = wp_schedule_single_event( time(), self::CRON_HOOK, $args, true );
		if ( $scheduled instanceof WP_Error ) {
			return $scheduled;
		}

		// trigger the cron to start it as soon as possible.
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}

		// return the result.
		return array(
			'status'            => 'scheduled',
			'object_id'         => $object->get_id(),
			'api'               => $api_obj->get_title(),
			'texts_to_simplify' => $to_simplify,
			'quota_state'       => $quota_state,
		);
	}

	/**
	 * Run a simplification which has been scheduled via ability.
	 *
	 * @param int    $object_id The ID of the simplified object.
	 * @param string $object_type The type of the simplified object.
	 *
	 * @return void
	 */
	public function run_scheduled_simplification( int $object_id, string $object_type ): void {
		// get the object.
		$object = Helper::get_object( $object_id, $object_type );

		// bail if the object could not be loaded or is not a simplified object.
		if ( ! $object instanceof Objects || ! $object->is_simplified() ) {
			return;
		}

		// get the user who requested this simplification.
		$option_name = $object->get_marker_option_name( self::MARKER_USER );
		$user_id     = absint( get_option( $option_name, 0 ) );
		delete_option( $option_name );

		// run the simplification as this user.
		$previous_user_id = get_current_user_id();
		wp_set_current_user( $user_id );

		// bail if the user is not allowed to do this (anymore).
		if ( ! current_user_can( 'edit_el_simplifier' ) || ! Init::get_instance()->current_user_can_edit_object( $object_id, $object_type ) ) {
			/* translators: %1$d will be replaced by an ID. */
			Log::get_instance()->add_log( sprintf( __( 'Simplification of object %1$d requested via ability was canceled as the user is not allowed to simplify it.', 'easy-language' ), $object_id ), 'error' );
			wp_set_current_user( $previous_user_id );
			return;
		}

		// get the active API.
		$api_obj = Apis::get_instance()->get_active_api();

		// bail if no API is active.
		if ( ! $api_obj instanceof Api_Base ) {
			wp_set_current_user( $previous_user_id );
			return;
		}

		// bail if the quota has been used up since the simplification was scheduled.
		if ( Helper::is_quota_used_up( $api_obj ) ) {
			/* translators: %1$s will be replaced by a title, %2$s by the API title. */
			Log::get_instance()->add_log( sprintf( __( 'Simplification of %1$s requested via ability was canceled as the quota of %2$s is used up.', 'easy-language' ), '<i>' . esc_html( $object->get_title() ) . '</i>', esc_html( $api_obj->get_title() ) ), 'error' );
			wp_set_current_user( $previous_user_id );
			return;
		}

		// get the count of texts to simplify before the run.
		$to_simplify_before = $this->count_texts( $object, 'to_simplify' );

		// run the simplification of all texts of this object.
		$object->process_simplifications( $api_obj->get_simplifications_obj(), $api_obj->get_active_language_mapping(), 0, true );

		// get the count of texts which are still to simplify (e.g. because of API errors).
		$to_simplify_after = $this->count_texts( $object, 'to_simplify' );

		// finish the process if it is still marked as running.
		if ( absint( $object->get_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING ) ) > 0 ) {
			if ( 0 === $to_simplify_after ) {
				// let the plugin finish the process (no API request is sent as nothing is left to simplify).
				$object->process_simplifications( $api_obj->get_simplifications_obj(), $api_obj->get_active_language_mapping(), 0, false );
			} else {
				// do not retry failed texts to protect the quota, just end the process.
				$object->set_array_marker_during_simplification( EASY_LANGUAGE_OPTION_SIMPLIFICATION_RUNNING, 0 );
			}
		}

		// log this event.
		/* translators: %1$s will be replaced by a title, %2$d and %3$d by numbers, %4$s by the API title. */
		Log::get_instance()->add_log( sprintf( __( 'Simplification of %1$s requested via ability has been processed via %4$s: %2$d of %3$d texts simplified.', 'easy-language' ), '<i>' . esc_html( $object->get_title() ) . '</i>', $to_simplify_before - $to_simplify_after, $to_simplify_before, esc_html( $api_obj->get_title() ) ), 0 === $to_simplify_after ? 'success' : 'error' );

		// reset the user.
		wp_set_current_user( $previous_user_id );
	}
}
