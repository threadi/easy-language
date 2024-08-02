<?php
/**
 * File to handle setup for this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Apis\Capito\Capito;
use easyLanguage\Apis\Summ_Ai\Summ_AI;

/**
 * Initialize the setup.
 */
class Setup {
	/**
	 * Instance of this object.
	 *
	 * @var ?Setup
	 */
	private static ?Setup $instance = null;

	/**
	 * Define setup as array with steps.
	 *
	 * @var array
	 */
	private array $setup = array();

	/**
	 * The object of the setup.
	 *
	 * @var \wpEasySetup\Setup
	 */
	private \wpEasySetup\Setup $setup_obj;

	/**
	 * Constructor for this handler.
	 */
	private function __construct() {
		// get the setup-object.
		$this->setup_obj = \wpEasySetup\Setup::get_instance();
		$this->setup_obj->set_url( Helper::get_plugin_url() );
		$this->setup_obj->set_path( Helper::get_plugin_path() );
		$this->setup_obj->set_texts(
			array(
				'title_error' => __( 'Error', 'easy-language' ),
				'txt_error_1' => __( 'The following error occurred:', 'easy-language' ),
				/* translators: %1$s will be replaced with the URL of the plugin-forum on wp.org */
				'txt_error_2' => sprintf( __( '<strong>If reason is unclear</strong> please contact our <a href="%1$s" target="_blank">support-forum (opens new window)</a> with as much detail as possible.', 'easy-language' ), esc_url( Helper::get_plugin_support_url() ) ),
			)
		);
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Setup {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Initialize the setup-object.
	 *
	 * @return void
	 */
	public function init(): void {
		// check to show hint if setup should be run.
		$this->show_hint();

		// only load setup if it is not completed.
		if ( ! $this->is_completed() ) {
			add_action( 'init', array( $this, 'add_settings' ) );

			add_action( 'wp_easy_setup_set_completed', array( $this, 'set_completed' ) );
			add_action( 'wp_easy_setup_process', array( $this, 'run_process' ) );
			add_action( 'wp_easy_setup_process', array( $this, 'show_process_end' ), PHP_INT_MAX );
			add_filter( 'wp_easy_setup_steps', array( $this, 'update_steps' ) );

			// set configuration for the setup.
			$this->setup_obj->set_config( $this->get_config() );

			// add hooks to enable the setup of this plugin.
			add_action( 'admin_menu', array( $this, 'add_setup_menu' ) );

			// use own hooks.
			add_action( 'easy_language_import_max_count', array( $this, 'update_max_step' ) );
			add_action( 'easy_language_import_count', array( $this, 'update_step' ) );
		}
	}

	/**
	 * Return whether setup is completed.
	 *
	 * @return bool
	 */
	public function is_completed(): bool {
		return $this->setup_obj->is_completed( $this->get_setup_name() );
	}

	/**
	 * Return the setup-URL.
	 *
	 * @return string
	 */
	public function get_setup_link(): string {
		return add_query_arg( array( 'page' => 'easyLanguageSetup' ), admin_url() . 'admin.php' );
	}

	/**
	 * Check if setup should be run and show hint for it.
	 *
	 * @return void
	 */
	public function show_hint(): void {
		// get transients object.
		$transients_obj = Transients::get_instance();

		// check if setup should be run.
		if ( ! $this->is_completed() ) {
			// bail if hint is already set.
			if ( $transients_obj->get_transient_by_name( 'easy_language_start_setup_hint' )->is_set() ) {
				return;
			}

			// delete all other transients.
			foreach ( $transients_obj->get_transients() as $transient_obj ) {
				$transient_obj->delete();
			}

			// add hint to run setup.
			$transient_obj = Transients::get_instance()->add();
			$transient_obj->set_name( 'easy_language_start_setup_hint' );
			$transient_obj->set_message( __( '<strong>You have installed Easy Language - nice and thank you!</strong> Now run the setup to expand your website with the possibilities of this plugin to simplify the texts of your website.', 'easy-language' ) . '<br><br>' . sprintf( '<a href="%1$s" class="button button-primary">' . __( 'Start setup', 'easy-language' ) . '</a>', esc_url( $this->get_setup_link() ) ) );
			$transient_obj->set_type( 'error' );
			$transient_obj->set_dismissible_days( 2 );
			$transient_obj->set_hide_on(
				array(
					Helper::get_settings_page_url(),
					$this->get_setup_link(),
				)
			);
			$transient_obj->save();
		} else {
			$transients_obj->get_transient_by_name( 'easy_language_start_setup_hint' )->delete();
		}
	}

	/**
	 * Return the configured setup.
	 *
	 * @return array
	 */
	private function get_setup(): array {
		$setup = $this->setup;
		if ( empty( $setup ) ) {
			$this->set_config();
			$setup = $this->setup;
		}

		/**
		 * Filter the configured setup for this plugin.
		 *
		 * @since 2.2.0 Available since 2.2.0.
		 *
		 * @param array $setup The setup-configuration.
		 */
		return apply_filters( 'easy_language_setup', $setup );
	}

	/**
	 * Show setup dialog.
	 *
	 * @return void
	 */
	public function display(): void {
		echo wp_kses_post( $this->setup_obj->display( $this->get_setup_name() ) );
	}

	/**
	 * Convert options array to react-compatible array-list with label and value.
	 *
	 * @param array $options The list of options to convert.
	 *
	 * @return array
	 */
	public function convert_options_for_react( array $options ): array {
		// define resulting list.
		$resulting_array = array();

		// loop through the options.
		foreach ( $options as $key => $label ) {
			$resulting_array[] = array(
				'label' => $label,
				'value' => $key,
			);
		}

		// return resulting list.
		return $resulting_array;
	}

	/**
	 * Return configuration for setup.
	 *
	 * Here we define which steps and texts are used by wp-easy-setup.
	 *
	 * @return array
	 */
	private function get_config(): array {
		// get setup.
		$setup = $this->get_setup();

		// collect configuration for the setup.
		$config = array(
			'name'                  => $this->get_setup_name(),
			'title'                 => __( 'Easy Language', 'easy-language' ) . ' ' . __( 'Setup', 'easy-language' ),
			'steps'                 => $setup,
			'back_button_label'     => __( 'Back', 'easy-language' ) . '<span class="dashicons dashicons-undo"></span>',
			'continue_button_label' => __( 'Continue', 'easy-language' ) . '<span class="dashicons dashicons-controls-play"></span>',
			'finish_button_label'   => __( 'Completed', 'easy-language' ) . '<span class="dashicons dashicons-saved"></span>',
			'update_fields'         => true,
		);

		/**
		 * Filter the setup configuration.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param array $config List of configuration for the setup.
		 */
		return apply_filters( 'easy_language_setup_config', $config );
	}

	/**
	 * Set process label.
	 *
	 * @param string $label The label to process.
	 *
	 * @return void
	 */
	public function set_process_label( string $label ): void {
		update_option( 'wp_easy_setup_step_label', $label );
	}

	/**
	 * Updates the process step.
	 *
	 * @param int $step Steps to add.
	 *
	 * @return void
	 */
	public function update_process_step( int $step = 1 ): void {
		update_option( 'wp_easy_setup_step', absint( get_option( 'wp_easy_setup_step', 0 ) + $step ) );
	}

	/**
	 * Sets the setup configuration.
	 *
	 * @return void
	 */
	public function set_config(): void {
		// get list of supported APIs.
		$apis = array();
		foreach ( Apis::get_instance()->get_available_apis() as $api_obj ) {
			$apis[ $api_obj->get_name() ] = $api_obj->get_title();
		}

		// define setup.
		$this->setup = array(
			1 => array(
				'easy_language_api' => array(
					'type'     => 'RadioControl',
					'label'    => __( 'Select API to simply your texts', 'easy-language' ),
					'help'     => __( 'Please select the API you want to use to simplify texts your website.<br>Please note that some APIs require additional settings.<br>Some APIs are also associated with costs.<br>You can change the API to use any time after this setup.', 'easy-language' ),
					'required' => true,
					'options'  => $this->convert_options_for_react( $apis ),
				),
				'help'              => array(
					'type' => 'Text',
					/* translators: %1$s will be replaced by our support-forum-URL. */
					'text' => '<p><span class="dashicons dashicons-editor-help"></span> ' . sprintf( __( '<strong>Need help?</strong> Ask in <a href="%1$s" target="_blank">our forum (opens new window)</a>.', 'easy-language' ), esc_url( Helper::get_plugin_support_url() ) ) . '</p>',
				),
			),
			2 => array(
				'runSetup' => array(
					'type'  => 'ProgressBar',
					'label' => __( 'Setup preparing the simplification of your texts.', 'easy-language' ),
				),
			),
		);
	}

	/**
	 * Update max count.
	 *
	 * @param int $max_count The value to add.
	 *
	 * @return void
	 */
	public function update_max_step( int $max_count ): void {
		update_option( 'wp_easy_setup_max_steps', absint( get_option( 'wp_easy_setup_max_steps' ) ) + $max_count );
	}

	/**
	 * Update count.
	 *
	 * @param int $count The value to add.
	 *
	 * @return void
	 */
	public function update_step( int $count ): void {
		update_option( 'wp_easy_setup_step', absint( get_option( 'wp_easy_setup_step' ) ) + $count );
	}

	/**
	 * Run the process.
	 *
	 * @param string $config_name The name of the setup-configuration.
	 *
	 * @return void
	 */
	public function run_process( string $config_name ): void {
		// bail if this is not our setup.
		if ( $config_name !== $this->get_setup_name() ) {
			return;
		}

		// get the max steps for this process.
		$max_steps = 1;

		// set max step count.
		update_option( 'wp_easy_setup_max_steps', $max_steps );

		// 1. Run step 1
		$this->set_process_label( 'run step 1' );
		// ...

		// 2. Run import of positions.
		$this->set_process_label( 'run step 2' );

		// set steps to max steps to end the process.
		update_option( 'wp_easy_setup_step', $max_steps );
	}

	/**
	 * Show process end text.
	 *
	 * @param string $config_name The name of the setup-configuration.
	 *
	 * @return void
	 */
	public function show_process_end( string $config_name ): void {
		// bail if this is not our setup.
		if ( $config_name !== $this->get_setup_name() ) {
			return;
		}

		$completed_text = __( 'Setup has been run. You can now simplify your texts. Click on "Completed" to view the possibilities in an intro.', 'easy-language' );
		/**
		 * Filter the text for display if setup has been run.
		 *
		 * @since 2.2.0 Available since 2.2.0
		 * @param string $completed_text The text to show.
		 * @param string $config_name The name of the setup-configuration used.
		 */
		$this->set_process_label( apply_filters( 'easy_language_setup_process_completed_text', $completed_text, $config_name ) );
	}

	/**
	 * Run additional tasks if setup has been marked as completed.
	 *
	 * @param string $config_name The name of the setup-configuration.
	 *
	 * @return void
	 */
	public function set_completed( string $config_name ): void {
		// bail if this is not our setup.
		if ( $this->get_setup_name() !== $config_name ) {
			return;
		}

		// get actual list of completed setups.
		$actual_completed = get_option( 'wp_easy_setup_completed', array() );

		// add this setup to the list.
		$actual_completed[] = $this->get_setup_name();

		// add the actual setup to the list of completed setups.
		update_option( 'wp_easy_setup_completed', $actual_completed );

		if ( Helper::is_admin_api_request() ) {
			// Return JSON with forward-URL.
			wp_send_json(
				array(
					'forward' => add_query_arg( array( 'post_type' => 'page' ), get_admin_url() . 'edit.php' ),
				)
			);
		}
	}

	/**
	 * Return name for the setup configuration.
	 *
	 * @return string
	 */
	public function get_setup_name(): string {
		return 'easy-language';
	}

	/**
	 * Add setup menu of setup is not completed.
	 *
	 * @return void
	 */
	public function add_setup_menu(): void {
		// add setup entry as sub-menu, so it will not be visible in menu.
		add_submenu_page(
			'easyLanguageSetup',
			__( 'Easy Language', 'easy-language' ) . ' ' . __( 'Setup', 'easy-language' ),
			__( 'Setup', 'easy-language' ),
			'manage_options',
			'easyLanguageSetup',
			array( $this, 'display' ),
			1
		);
	}

	/**
	 * Add settings.
	 *
	 * TODO cleanup.
	 * TODO show callback-result direct in setup.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		register_setting(
			'easyLanguageApiFields',
			'easy_language_api',
			array(
				'default'      => '',
				'show_in_rest' => true,
				'type'         => 'string',
			)
		);
		register_setting(
			'easyLanguageCapitoFields',
			'easy_language_capito_api_key',
			array(
				'sanitize_callback' => array( Capito::get_instance(), 'validate_api_key' ),
				'default'           => '',
				'show_in_rest'      => true,
				'type'              => 'string',
			)
		);
		register_setting(
			'easyLanguageSummAiFields',
			'easy_language_summ_ai_api_key',
			array(
				'sanitize_callback' => array( Summ_AI::get_instance(), 'validate_api_key' ),
				'default'           => '',
				'show_in_rest'      => true,
				'type'              => 'string',
			)
		);
	}

	/**
	 * Update steps depending on configuration.
	 *
	 * @param array $steps The steps with its fields as array.
	 *
	 * @return array
	 */
	public function update_steps( array $steps ): array {
		// if API is configured, add the API key configuration field for this API as second step.
		$api_obj = APIs::get_instance()->get_active_api();
		if ( $api_obj ) {
			$token_field_name = $api_obj->get_token_field_name();
			if ( ! empty( $token_field_name ) ) {
				$steps[3] = $steps[2];
				$steps[2] = array(
					$token_field_name => array(
						'type'  => 'TextControl',
						'label' => __( 'Enter API Token (optional)', 'easy-language' ),
					),
					'help'            => array(
						'type' => 'Text',
						/* translators: %1$s will be replaced by our support-forum-URL. */
						'text' => '<p><span class="dashicons dashicons-editor-help"></span> ' . __( 'If you do not have an API Token yet, just go to next step.', 'easy-language' ) . '</p>',
					),
				);
			}
		}

		// return resulting steps.
		return $steps;
	}
}
