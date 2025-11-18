<?php
/**
 * File to handle setup for this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Dependencies\easyTransientsForWordPress\Transients;

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
	 * @var array<int,array<string,mixed>>
	 */
	private array $setup = array();

	/**
	 * Constructor for this handler.
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
	public static function get_instance(): Setup {
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
		add_action( 'init', array( $this, 'init_setup' ), 20 );
	}

	/**
	 * Initialize the setup-object.
	 *
	 * @return void
	 */
	public function init_setup(): void {
		// check to show a hint if setup should be run.
		$this->show_hint();

		// only load setup if it is not completed.
		if ( ! $this->is_completed() ) {
			// get the setup-object.
			$setup_obj = \easySetupForWordPress\Setup::get_instance();
			$setup_obj->init();

			// get the setup-object.
			$setup_obj->set_url( Helper::get_plugin_url() );
			$setup_obj->set_path( Helper::get_plugin_path() );
			$setup_obj->set_texts(
				array(
					'title_error' => __( 'Error', 'easy-language' ),
					'txt_error_1' => __( 'The following error occurred:', 'easy-language' ),
					/* translators: %1$s will be replaced with the URL of the plugin-forum on wp.org */
					'txt_error_2' => sprintf( __( '<strong>If reason is unclear</strong> please contact our <a href="%1$s" target="_blank">support-forum (opens new window)</a> with as much detail as possible.', 'easy-language' ), esc_url( Helper::get_plugin_support_url() ) ),
				)
			);

			// set configuration for setup.
			$setup_obj->set_config( $this->get_config() );

			add_action( 'esfw_set_completed', array( $this, 'set_completed' ) );
			add_action( 'esfw_process', array( $this, 'run_process' ) );
			add_action( 'esfw_process', array( $this, 'show_process_end' ), PHP_INT_MAX );

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
		return \easySetupForWordPress\Setup::get_instance()->is_completed( $this->get_setup_name() );
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
	 * Check if setup should be run and show a hint for it.
	 *
	 * @return void
	 */
	public function show_hint(): void {
		// get transients object.
		$transients_obj = Transients::get_instance();

		// check if setup should be run.
		if ( ! $this->is_completed() ) {
			// bail if a hint is already set.
			if ( $transients_obj->get_transient_by_name( 'easy_language_start_setup_hint' )->is_set() ) {
				return;
			}

			// delete all other transients.
			foreach ( $transients_obj->get_transients() as $transient_obj ) {
				$transient_obj->delete();
			}

			// add a hint to run setup.
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
	 * @return array<int,array<string,mixed>>
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
		 * @param array<int,array<string,mixed>> $setup The setup-configuration.
		 */
		return apply_filters( 'easy_language_setup', $setup );
	}

	/**
	 * Show setup dialog.
	 *
	 * @return void
	 */
	public function display(): void {
		// create help in case of error during loading of the setup.
		$error_help = '<div class="easy-language-transient notice notice-success"><h3>' . esc_html( apply_filters( 'easy_language_transient_title', Helper::get_plugin_name() ) ) . '</h3><p><strong>' . __( 'Setup is loading', 'easy-language' ) . '</strong><br>' . __( 'Please wait while we load the setup.', 'easy-language' ) . '<br>' . __( 'However, you can also skip the setup and configure the plugin manually.', 'easy-language' ) . '</p><p><a href="' . esc_url( \easySetupForWordPress\Setup::get_instance()->get_skip_url( $this->get_setup_name(), Helper::get_settings_page_url() ) ) . '" class="button button-primary">' . __( 'Skip setup', 'easy-language' ) . '</a></p></div>';

		// add error text.
		\easySetupForWordPress\Setup::get_instance()->set_error_help( $error_help );

		// output.
		echo wp_kses_post( \easySetupForWordPress\Setup::get_instance()->display( $this->get_setup_name() ) );
	}

	/**
	 * Convert options array to react-compatible array-list with label and value.
	 *
	 * @param array<string,string> $options The list of options to convert.
	 *
	 * @return array<int,array<string,string>>
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
	 * Here we define which steps and texts are used by easy-setup-for-wordpress.
	 *
	 * @return array<string,mixed>
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
		 * @param array<string,mixed> $config List of configuration for the setup.
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
		update_option( 'esfw_step_label', $label );
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
		update_option( 'esfw_max_steps', absint( get_option( 'esfw_max_steps' ) ) + $max_count );
	}

	/**
	 * Update count.
	 *
	 * @param int $count The value to add.
	 *
	 * @return void
	 */
	public function update_step( int $count ): void {
		update_option( 'esfw_steps', absint( get_option( 'esfw_steps ' ) ) + $count );
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
		update_option( 'esfw_max_steps', $max_steps );

		// 1. Run step 1
		$this->set_process_label( 'run step 1' );
		// ...

		// 2. Run import of positions.
		$this->set_process_label( 'run step 2' );

		// update step counter.
		$this->update_process_step( 1 );

		// set steps to max steps to end the process.
		update_option( 'esfw_steps', $max_steps );
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
		$actual_completed = get_option( 'esfw_completed', array() );

		// add this setup to the list.
		$actual_completed[] = $this->get_setup_name();

		// add the actual setup to the list of completed setups.
		update_option( 'esfw_completed', $actual_completed );

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
	 * Update steps depending on configuration.
	 *
	 * @param array<int,array<string,mixed>> $steps The steps with its fields as array.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function update_steps( array $steps ): array {
		// if API is configured, add the API key configuration field for this API as second step.
		$api_obj = Apis::get_instance()->get_active_api();
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

	/**
	 * Ron on uninstallation of the plugin.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		\easySetupForWordPress\Setup::get_instance()->uninstall( $this->get_setup_name() );
	}

	/**
	 * Return the actual max steps.
	 *
	 * @return int
	 */
	public function get_max_step(): int {
		return absint( get_option( 'esfw_max_steps' ) );
	}

	/**
	 * Updates the process step.
	 *
	 * @param int $step Steps to add.
	 *
	 * @return void
	 */
	public function update_process_step( int $step = 1 ): void {
		update_option( 'esfw_step', absint( get_option( 'esfw_step' ) + $step ) );
	}
}
