<?php
/**
 * File for handler for things the ChatGpt supports.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\ChatGpt;

use easyLanguage\Apis;
use easyLanguage\Base;
use easyLanguage\Api_Base;
use easyLanguage\Helper;
use easyLanguage\Multilingual_Plugins;
use easyLanguage\Multilingual_plugins\Easy_Language\Db;
use easyLanguage\Transients;
use wpdb;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define what ChatGpt supports and what not.
 */
class ChatGpt extends Base implements Api_Base {

	/**
	 * Set the internal name for the API.
	 *
	 * @var string
	 */
	protected string $name = 'chatgpt';

	/**
	 * Set the public title for the API.
	 *
	 * @var string
	 */
	protected string $title = 'ChatGpt';

	/**
	 * Instance of this object.
	 *
	 * @var ?ChatGpt
	 */
	private static ?ChatGpt $instance = null;

	/**
	 * Name for database-table with request-response.
	 *
	 * @var string
	 */
	private string $table_requests;

	/**
	 * Database-object.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Language-specific support-URL.
	 *
	 * @var array
	 */
	protected array $support_url = array(
		'de_DE' => 'https://help.openai.com/',
		'en_US' => 'https://help.openai.com/',
		'en_UK' => 'https://help.openai.com/',
	);

	/**
	 * Constructor for Init-Handler.
	 */
	private function __construct() {
		global $wpdb;

		// get the db-connection.
		$this->wpdb = $wpdb;

		// table for requests and responses.
		$this->table_requests = DB::get_instance()->get_wpdb_prefix() . 'easy_language_chatgpt';

		// add settings.
		add_action( 'easy_language_settings_add_settings', array( $this, 'add_settings' ), 20 );

		// add settings tab.
		add_action( 'easy_language_settings_add_tab', array( $this, 'add_settings_tab' ), 20 );

		// add settings page.
		add_action( 'easy_language_settings_chatgpt_page', array( $this, 'add_settings_page' ) );

		// add hook für schedules.
		$translations_obj = Translations::get_instance();
		$translations_obj->init( $this );
		add_action( 'easy_language_chatgpt_automatic', array( $translations_obj, 'run' ) );
		//add_action( 'easy_language_chatgpt_request_quota', array( $this, 'get_quota_from_api' ) );

		// add hook to remove token.
		add_action( 'admin_action_easy_language_chatgpt_remove_token', array( $this, 'remove_token' ) );

		// add hook to get actual quota via link.
		//add_action( 'admin_action_easy_language_chatgpt_get_quota', array( $this, 'get_quota_from_api_via_link' ) );
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): ChatGpt {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Return the public description of the SUMM AI API.
	 *
	 * @return string
	 */
	public function get_description(): string {
		// get quota.
		$quota = $this->get_quota();

		/* translators: %1$d will be replaced by the link to Chatgpt */
		$text = sprintf( __( '<p><a href="%1$s" target="_blank"><strong>ChatGpt</strong> (opens new window)</a> is an AI-tool for any conversations.<br>It helps you to answer questions you have.</p><p>This API tries to simplify texts based on its on artificial intelligence.<br>The results are based on no standards for Easy or Plain language.</p><p>The number of simplifications with CHatGpt is limited to <strong>quotas</strong>.</p>', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) );
		$text .= '<p><strong>'.__( 'You will use tokens on ChatGpt for every simplification. Please consult your ChatGpt-account about the usage.', 'easy-language' ).'</strong></p>';

		// wrapper for buttons.
		$text .= '<p>';

		// help-button.
		$text .= '<a href="'.esc_url($this->get_language_specific_support_page()).'" target="_blank" class="button button-primary" title="'.esc_attr( __( 'Get help for this API', 'easy-language' ) ).'"><span class="dashicons dashicons-editor-help"></span></a>';

		// Show setting-button if this API is enabled.
		if( $this->is_active() ) {
			$text .= '<a href="'.esc_html($this->get_settings_url()).'" class="button button-primary" title="'.esc_html__( 'Go to settings', 'easy-languag'  ).'"><span class="dashicons dashicons-admin-generic"></span></a>';
		}

		$text .= '</p>';

		// return resulting text.
		return $text;
	}

	/**
	 * Return list of supported source-languages.
	 *
	 * @return array
	 */
	public function get_supported_source_languages(): array {
		return array(
			'de_DE' => array(
				'label'       => __( 'German', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Informal german spoken in germany.', 'easy-language' ),
				'api_value'   => 'de',
			),
			'de_CH' => array(
				'label'       => __( 'German (CH)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Informal german spoken in suisse.', 'easy-language' ),
				'api_value'   => 'de',
			),
			'de_AT' => array(
				'label'       => __( 'German (AT)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Informal german spoken in austria.', 'easy-language' ),
				'api_value'   => 'de',
			),
		);
	}

	/**
	 * Return the languages this API supports.
	 *
	 * @return array
	 * @noinspection DuplicatedCode
	 */
	public function get_supported_target_languages(): array {
		return array(
			'de_EL' => array(
				'label'       => __( 'Einfache Sprache', 'easy-language' ),
				'enabled'     => true,
				'description' => __( 'The Einfache Sprache used in Germany, Suisse and Austria.', 'easy-language' ),
				'url'         => 'de_el',
				'api_value'   => 'plain',
			),
			'de_LS' => array(
				'label'       => __( 'Leichte Sprache', 'easy-language' ),
				'enabled'     => true,
				'description' => __( 'The Leichte Sprache used in Germany, Suisse and Austria.', 'easy-language' ),
				'url'         => 'de_ls',
				'api_value'   => 'easy',
			),
		);
	}

	/**
	 * Return supported target languages.
	 *
	 * @return array
	 * @noinspection DuplicatedCode
	 */
	public function get_active_target_languages(): array {
		// get actual enabled target-languages.
		$target_languages = get_option( 'easy_language_chatgpt_target_languages', array() );
		if ( ! is_array( $target_languages ) ) {
			$target_languages = array();
		}

		// define resulting list
		$list = array();

		foreach ( $this->get_supported_target_languages() as $language_code => $language ) {
			if ( ! empty( $target_languages[ $language_code ] ) ) {
				$list[ $language_code ] = $language;
			}
		}

		// return resulting list.
		return $list;
	}

	/**
	 * Return the list of supported languages which could be translated with this API.
	 *
	 * Left source, right possible target languages.
	 *
	 * @return array
	 */
	public function get_mapping_languages(): array {
		return array(
			'de_DE' => array( 'de_LS', 'de_EL' ),
			'de_CH' => array( 'de_LS', 'de_EL' ),
			'de_AT' => array( 'de_LS', 'de_EL' ),
		);
	}

	/**
	 * Add settings tab.
	 *
	 * @param $tab
	 *
	 * @return void
	 * @noinspection DuplicatedCode
	 */
	public function add_settings_tab( $tab ): void {
		// get list of available plugins and check if they support APIs.
		$supports_api = false;
		foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			if ( $plugin_obj->is_supporting_apis() && false === $plugin_obj->has_own_api_config() ) {
				$supports_api = true;
			}
		}

		// bail of plugin does not support api OR this API is not enabled.
		if ( false === $supports_api || $this->get_name() !== get_option( 'easy_language_api', '' ) ) {
			return;
		}

		// check active tab.
		$activeClass = '';
		if ( $this->get_name() === $tab ) {
			$activeClass = ' nav-tab-active';
		}

		// output tab.
		echo '<a href="' . esc_url( helper::get_settings_page_url() ) . '&tab=' . esc_attr( $this->get_name() ) . '" class="nav-tab' . esc_attr( $activeClass ) . '">' . __( 'ChatGpt', 'easy-language' ) . '</a>';
	}

	/**
	 * Add settings page.
	 *
	 * @return void
	 * @noinspection DuplicatedCode
	 */
	public function add_settings_page(): void {
		// bail if this API is not enabled.
		if ( Apis::get_instance()->get_active_api()->get_name() !== $this->get_name() ) {
			return;
		}

		// get list of available plugins and check if they support APIs.
		$supports_api = false;
		foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			if ( $plugin_obj->is_supporting_apis() ) {
				$supports_api = true;
			}
		}

		// bail if the plugin does not support APIs and also check user capabilities.
		if ( false === $supports_api || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<form method="POST" action="<?php echo get_admin_url(); ?>options.php">
			<?php
			settings_fields( 'easyLanguageChatGptFields' );
			do_settings_sections( 'easyLanguageChatGptPage' );
			submit_button();
			?>
		</form>
		<?php
		/*
		?>
		<h2 id="statistics"><?php esc_html_e( 'ChatGpt Quota', 'easy-language' ); ?></h2>
		<?php
		*/
		if ( $this->is_chatgpt_token_set() ) {
			/**
			 * Get and show the quota we received from API.
			 */
			$api_quota = $this->get_quota();
			if ( empty( $api_quota ) ) {
				$quota_text = esc_html__( 'No quota consumed so far', 'easy-language' );
			} elseif ( -1 === $api_quota['character_limit'] ) {
				$quota_text = __( 'Update quota now.', 'easy-language' );
			} elseif ( 0 === $api_quota['character_limit'] ) {
				$quota_text = __( 'Unlimited.', 'easy-language' );
			} else {
				$quota_text = $api_quota['character_spent'] . ' / ' . $api_quota['character_limit'];
			}

			// get the update quota link.
			/*$update_quota_url = add_query_arg(
				array(
					'action' => 'easy_language_chatgpt_get_quota',
					'nonce'  => wp_create_nonce( 'easy-language-chatgpt-get-quota' ),
				),
				get_admin_url() . 'admin.php'
			);

			// output.
			?>
			<p>
				<strong><?php echo esc_html__( 'Quota', 'easy-language' ); ?>:</strong> <?php echo $quota_text; ?>
				<a href="<?php echo esc_url( $update_quota_url ); ?>#statistics" class="button button-secondary"><?php echo esc_html__( 'Update now', 'easy-language' ); ?></a>
			</p>
			<?php*/
		} else {
			/*
			?>
			<p><?php echo esc_html__( 'Info about quota will be available until the API token is set', 'easy-language' ); ?></p>
			<?php
			*/
		}
	}

	/**
	 * Add chatgpt settings.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		/**
		 * Chatgpt settings Section
		 */
		add_settings_section(
			'settings_section_chatgpt',
			__( 'Chatgpt Settings', 'easy-language' ),
			'__return_true',
			'easyLanguageChatGptPage'
		);

		// Set description for token field if it has not been set.
		/* translators: %1$s will be replaced by the Chatgpt URL */
		$description = sprintf( __( 'Get your ChatGpt API Token <a href="%1$s" target="_blank">here (opens new window)</a>.<br>If you have any questions about the token provided by ChatGpt, please contact their support: <a href="%1$s" target="_blank">%1$s (opens new window)</a>', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) );
		if ( false !== $this->is_chatgpt_token_set() ) {
			// set link to remove the token.
			$remove_token_url = add_query_arg(
				array(
					'action' => 'easy_language_chatgpt_remove_token',
					'nonce'  => wp_create_nonce( 'easy-language-chatgpt-remove-token' ),
				),
				get_admin_url() . 'admin.php'
			);

			// Show other description if token is set.
			/* translators: %1$s will be replaced by the Chatgpt URL */
			$description  = sprintf( __( 'If you have any questions about the token provided by ChatGpt, please contact their support: <a href="%1$s" target="_blank">%1$s (opens new window)</a>', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) );
			$description .= '<br><a href="' . esc_url( $remove_token_url ) . '" class="button button-secondary easy-language-settings-button">' . __( 'Remove token', 'easy-language' ) . '</a>';
		}

		// if foreign translation-plugin with API-support is used, hide the language-settings.
		$foreign_translation_plugin_with_api_support = false;
		foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			if ( $plugin_obj->is_foreign_plugin() && $plugin_obj->is_supporting_apis() ) {
				$foreign_translation_plugin_with_api_support = true;
			}
		}

		// Chatgpt token.
		add_settings_field(
			'easy_language_chatgpt_api_key',
			__( 'Chatgpt API Key', 'easy-language' ),
			'easy_language_admin_text_field',
			'easyLanguageChatGptPage',
			'settings_section_chatgpt',
			array(
				'label_for'   => 'easy_language_chatgpt_api_key',
				'fieldId'     => 'easy_language_chatgpt_api_key',
				'description' => $description,
				'placeholder' => __( 'Enter token here', 'easy-language' ),
				'highlight'   => false === $this->is_chatgpt_token_set(),
			)
		);
		register_setting( 'easyLanguageChatGptFields', 'easy_language_chatgpt_api_key', array( 'sanitize_callback' => array( $this, 'validate_api_key' ) ) );

		// Enable source-languages
		// -> defaults to WP-locale
		// -> if WPML, Polylang or TranslatePress is available, show additional languages
		// -> but restrict list to languages supported by ChatGpt
		add_settings_field(
			'easy_language_chatgpt_source_languages',
			__( 'Choose source languages', 'easy-language' ),
			'easy_language_admin_multiple_checkboxes_field',
			'easyLanguageChatGptPage',
			'settings_section_chatgpt',
			array(
				'label_for'   => 'easy_language_chatgpt_source_languages',
				'fieldId'     => 'easy_language_chatgpt_source_languages',
				'description' => __( 'These are the possible source languages for ChatGpt-translations. This language has to be the language which you use for any texts in your website.', 'easy-language' ),
				'options'     => $this->get_supported_source_languages(),
				'readonly'    => false === $this->is_chatgpt_token_set() || $foreign_translation_plugin_with_api_support,
			)
		);
		register_setting( 'easyLanguageChatGptFields', 'easy_language_chatgpt_source_languages', array( 'sanitize_callback' => 'easyLanguage\Helper::settings_validate_multiple_checkboxes' ) );

		// Enable target languages.
		add_settings_field(
			'easy_language_chatgpt_target_languages',
			__( 'Choose target languages', 'easy-language' ),
			'easy_language_admin_multiple_checkboxes_field',
			'easyLanguageChatGptPage',
			'settings_section_chatgpt',
			array(
				'label_for'   => 'easy_language_chatgpt_target_languages',
				'fieldId'     => 'easy_language_chatgpt_target_languages',
				'description' => __( 'These are the possible target languages for ChatGpt-translations.', 'easy-language' ),
				'options'     => $this->get_supported_target_languages(),
				'readonly'    => false === $this->is_chatgpt_token_set() || $foreign_translation_plugin_with_api_support,
			)
		);
		register_setting( 'easyLanguageChatGptFields', 'easy_language_chatgpt_target_languages', array( 'sanitize_callback' => array( $this, 'validate_language_settings' ) ) );

		// Set translation mode.
		add_settings_field(
			'easy_language_chatgpt_automatic_mode',
			__( 'Choose translation-mode', 'easy-language' ),
			'easy_language_admin_multiple_radio_field',
			'easyLanguageChatGptPage',
			'settings_section_chatgpt',
			array(
				'label_for' => 'easy_language_chatgpt_automatic_mode',
				'fieldId'   => 'easy_language_chatgpt_automatic_mode',
				'options'   => array(
					'disabled'  => array(
						'label'       => __( 'Disabled', 'easy-language' ),
						'enabled'     => true,
						'description' => __( 'You have to write all simplifications manually. The API will not be used.', 'easy-language' ),
					),
					'automatic' => array(
						'label'       => __( 'Automatic translation of each text.', 'easy-language' ),
						'enabled'     => true,
						'description' => __( 'Each for translation requested text will be translated automatic in the intervall set below. Be aware that this is not an automatic translation in frontend initiated through the visitor.', 'easy-language' ),
					),
					'manuell'   => array(
						'label'       => __( 'Simplify texts manually, use API as helper.', 'easy-language' ),
						'enabled'     => true,
						'description' => __( 'The system will not check automatically for translations. Its your decision.', 'easy-language' ),
					),
				),
				'readonly'  => false === $this->is_chatgpt_token_set() || $foreign_translation_plugin_with_api_support,
			)
		);
		register_setting( 'easyLanguageChatGptFields', 'easy_language_chatgpt_automatic_mode', array( 'sanitize_callback' => array( $this, 'set_automatic_mode' ) ) );

		// get possible intervals.
		$intervals = array();
		foreach ( wp_get_schedules() as $name => $schedule ) {
			$intervals[ $name ] = $schedule['display'];
		}

		// Interval for automatic translations.
		add_settings_field(
			'easy_language_chatgpt_interval',
			__( 'Interval for automatic translation', 'easy-language' ),
			'easy_language_admin_select_field',
			'easyLanguageChatGptPage',
			'settings_section_chatgpt',
			array(
				'label_for'   => 'easy_language_chatgpt_interval',
				'fieldId'     => 'easy_language_chatgpt_interval',
				'values'      => $intervals,
				'readonly'    => ! $this->is_chatgpt_token_set() || 'automatic' !== get_option( 'easy_language_chatgpt_automatic_mode', '' ) || $foreign_translation_plugin_with_api_support,
				'description' => __( 'The interval is only used for automatic translations.', 'easy-language' ),
			)
		);
		register_setting( 'easyLanguageChatGptFields', 'easy_language_chatgpt_interval', array( 'sanitize_callback' => array( $this, 'set_interval' ) ) );

		// Interval for quota-request.
		add_settings_field(
			'easy_language_chatgpt_quota_interval',
			__( 'Interval for quota request', 'easy-language' ),
			'easy_language_admin_select_field',
			'easyLanguageChatGptPage',
			'settings_section_chatgpt',
			array(
				'label_for'   => 'easy_language_chatgpt_quota_interval',
				'fieldId'     => 'easy_language_chatgpt_quota_interval',
				'values'      => $intervals,
				'readonly'    => ! $this->is_chatgpt_token_set(),
				'description' => __( 'The actual API quota will be requested in this interval.', 'easy-language' ),
			)
		);
		register_setting( 'easyLanguageChatGptFields', 'easy_language_chatgpt_quota_interval', array( 'sanitize_callback' => array( $this, 'set_quota_interval' ) ) );
	}

	/**
	 * Return whether this API is active regarding all its settings.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->get_name() === get_option( 'easy_language_api' );
	}

	/**
	 * Install-routines for the API, called during plugin-activation and API-change.
	 *
	 * @return void
	 */
	public function install(): void {
		global $wpdb;

		// set source language depending on WP-locale and its support.
		if ( ! get_option( 'easy_language_chatgpt_source_languages' ) ) {
			$language  = helper::get_wp_lang();
			$languages = array( $language => '1' );
			update_option( 'easy_language_chatgpt_source_languages', $languages );
		}

		// set target language depending on source-language and if only one target could be possible.
		if ( ! get_option( 'easy_language_chatgpt_target_languages' ) ) {
			$language  = helper::get_wp_lang();
			$languages = array( 'de_b1' => '1' );
			if ( false !== str_contains( $language, 'en_' ) ) {
				$languages = array( 'en_b1' => '1' );
			}
			update_option( 'easy_language_chatgpt_target_languages', $languages );
		}

		// set translation mode to manuell.
		if ( ! get_option( 'easy_language_chatgpt_automatic_mode' ) ) {
			update_option( 'easy_language_chatgpt_automatic_mode', 'manuell' );
		}

		// set schedule for automatic translation.
		$this->set_automatic_mode( get_option( 'easy_language_chatgpt_automatic_mode', 'disabled' ) );

		// set interval for automatic translation to daily.
		if ( ! get_option( 'easy_language_chatgpt_quota_interval' ) ) {
			update_option( 'easy_language_chatgpt_quota_interval', 'daily' );
		}

		// set interval for automatic translation to daily.
		if ( ! get_option( 'easy_language_chatgpt_interval' ) ) {
			update_option( 'easy_language_chatgpt_interval', 'daily' );
		}

		$charset_collate = $wpdb->get_charset_collate();

		// table for original-texts to translate.
		$sql = "CREATE TABLE $this->table_requests (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `time` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            `request` text DEFAULT '' NOT NULL,
            `response` text DEFAULT '' NOT NULL,
            `duration` int(11) DEFAULT 0 NOT NULL,
            `httpstatus` int(11) DEFAULT 0 NOT NULL,
            `quota` int(11) DEFAULT 0 NOT NULL,
            `blog_id` int(11) DEFAULT 0 NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Install-routines for the API, called during plugin-activation and API-change.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		/**
		 * Remove settings.
		 */
		foreach ( $this->get_options() as $option_name ) {
			delete_option( $option_name );
		}

		/**
		 * Delete our table.
		 */
		$sql = 'DROP TABLE IF EXISTS ' . $this->table_requests;
		$this->wpdb->query( $sql );
	}

	/**
	 * Return list of options this plugin is using, e.g. for clean uninstall.
	 *
	 * @return array
	 */
	private function get_options(): array {
		return array(
			'easy_language_chatgpt_api_key',
			'easy_language_chatgpt_source_languages',
			'easy_language_chatgpt_target_languages',
			'easy_language_chatgpt_automatic_mode',
			'easy_language_chatgpt_interval',
			'easy_language_chatgpt_quota',
			'easy_language_chatgpt_quota_interval',
		);
	}

	/**
	 * Return the translations-object.
	 *
	 * @return Translations
	 */
	public function get_translations_obj(): object {
		// get the object.
		$obj = Translations::get_instance();

		// initialize it.
		$obj->init( $this );

		// return resulting object.
		return $obj;
	}

	/**
	 * Initialize api-specific CLI-functions for this API: none.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Return API URL.
	 *
	 * @return string
	 */
	public function get_api_url(): string {
		return EASY_LANGUAGE_CHATGPT_API_URL;
	}

	/**
	 * Return list of transients this plugin is using, e.g. for clean uninstallation.
	 *
	 * @return array
	 */
	private function get_transients(): array {
		return array();
	}

	/**
	 * Delete our own transients during disabling this API.
	 *
	 * @return void
	 */
	public function disable(): void {
		$transients_obj = Transients::get_instance();
		foreach ( $this->get_transients() as $transient_name ) {
			$transient_obj = $transients_obj->get_transient_by_name( $transient_name );
			$transient_obj->delete();
		}
	}

	/**
	 * Return true if token is set.
	 *
	 * @return bool
	 */
	private function is_chatgpt_token_set(): bool {
		return ! empty( $this->get_token() );
	}

	/**
	 * Get the token.
	 *
	 * @return string
	 */
	public function get_token(): string {
		return (string) get_option( 'easy_language_chatgpt_api_key', '' );
	}

	/**
	 * Set the automatic mode for the translations.
	 *
	 * @param $value
	 * @return string|null
	 */
	public function set_automatic_mode( $value ): ?string {
		$value = Helper::settings_validate_multiple_radios( $value );
		switch ( $value ) {
			case 'disabled':
			case 'manuell':
				wp_clear_scheduled_hook( 'easy_language_chatgpt_automatic' );
				break;
			case 'automatic':
				wp_clear_scheduled_hook( 'easy_language_chatgpt_automatic' );
				wp_schedule_event( time(), get_option( 'easy_language_chatgpt_interval', 'daily' ), 'easy_language_chatgpt_automatic' );
				break;
		}

		return $value;
	}

	/**
	 * Return request object.
	 *
	 * @return Request
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public function get_request_object() {
		return new Request();
	}

	/**
	 * Validate the API token.
	 *
	 * @param $value
	 * @return ?string
	 * @noinspection PhpUnused
	 */
	public function validate_api_key( $value ): ?string {
		$errors = get_settings_errors();

		/**
		 * If a result-entry already exists, do nothing here.
		 *
		 * @see https://core.trac.wordpress.org/ticket/21989
		 */
		if ( helper::check_if_setting_error_entry_exists_in_array( 'easy_language_chatgpt_api_key', $errors ) ) {
			return $value;
		}

		// if no token has been entered, show hint.
		if ( empty( $value ) ) {
			add_settings_error( 'easy_language_chatgpt_api_key', 'easy_language_chatgpt_api_key', __( 'You did not enter an API token. All translation options via the ChatGpt API have been disabled.', 'easy-language' ) );
		}
		// TODO validate key against the API.
		// if token has been changed, delete settings hint.
		elseif ( 0 !== strcmp( $value, get_option( 'easy_language_chatgpt_api_key', '' ) ) ) {
			// delete api-settings hint.
			Transients::get_instance()->get_transient_by_name( 'easy_language_api_changed' )->delete();
		}

		// return the entered token.
		return $value;
	}

	/**
	 * Validate the language-settings.
	 *
	 * The source-language must be possible to simplify in the target-language.
	 *
	 * @param $values
	 *
	 * @return array|null
	 */
	public function validate_language_settings( $values ): ?array {
		$values = Helper::settings_validate_multiple_checkboxes( $values );
		if ( empty( $values ) ) {
			add_settings_error( 'easy_language_chatgpt_target_languages', 'easy_language_chatgpt_target_languages', __( 'You have to set a target-language for translations.', 'easy-language' ) );
		} elseif ( false === $this->is_language_set( $values ) ) {
			add_settings_error( 'easy_language_chatgpt_target_languages', 'easy_language_chatgpt_target_languages', __( 'At least one language cannot (currently) be translated into the selected target languages by the API.', 'easy-language' ) );
		}

		// return value.
		return $values;
	}

	/**
	 * Return whether a valid language-combination is set.
	 *
	 * Any active source language must be translatable to any active target-language.
	 *
	 * @param array $target_languages List of target languages to check.
	 * @return bool true if valid language-combination exist
	 */
	private function is_language_set( array $target_languages = array() ): bool {
		if ( empty( $target_languages ) ) {
			// get actual enabled source-languages.
			$target_languages = get_option( 'easy_language_chatgpt_target_languages', array() );
		}

		if ( ! is_array( $target_languages ) ) {
			$target_languages = array();
		}

		// get mappings.
		$mappings = $this->get_mapping_languages();

		// get actual enabled source-languages.
		$source_languages = get_option( 'easy_language_chatgpt_source_languages', array() );
		if ( ! is_array( $source_languages ) ) {
			$source_languages = array();
		}

		// check if all source-languages mapping all target-languages.
		$match = array();
		foreach ( $source_languages as $source_language => $enabled ) {
			foreach ( $target_languages as $value => $enabled2 ) {
				if ( 1 === absint( $enabled ) && 1 === absint( $enabled2 ) && ! empty( $mappings[ $source_language ] ) && false !== in_array( $value, $mappings[ $source_language ], true ) ) {
					$match[] = $source_language;
				}
			}
		}

		// return false if no valid combination has been found.
		return ! empty( $match );
	}

	/**
	 * Set the interval for the automatic-schedule, if it is enabled.
	 *
	 * @param $value
	 *
	 * @return ?string
	 */
	public function set_interval( $value ): ?string {
		$value = Helper::settings_validate_select_field( $value );
		// reset schedule if it is set to automatic.
		if ( ! empty( $value ) && 'automatic' === get_option( 'easy_language_chatgpt_automatic_mode' ) ) {
			wp_schedule_event( time(), $value, 'easy_language_chatgpt_automatic_mode' );
		}

		// return setting.
		return $value;
	}

	/**
	 * Set the interval for the automatic-schedule, if it is enabled.
	 *
	 * @param $value
	 *
	 * @return ?string
	 */
	public function set_quota_interval( $value ): ?string {
		$value = Helper::settings_validate_select_field( $value );
		if ( ! empty( $value ) ) {
			//wp_schedule_event( time(), $value, 'easy_language_quota_request_quota' ); // TODO eindeutiger Name nötig
		}

		// return setting.
		return $value;
	}

	/**
	 * Remove token via click.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function remove_token(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-chatgpt-remove-token', 'nonce' );

		// delete settings.
		delete_option( 'easy_language_chatgpt_api_key' );
		delete_option( 'easy_language_chatgpt_quota' );

		// delete quota-hint.
		$transients_obj = Transients::get_instance();
		$transient_obj  = $transients_obj->get_transient_by_name( 'easy_language_chatgpt_quota' );
		$transient_obj->delete();

		// redirect user.
		wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
		exit;
	}

	/**
	 * Get quota via link request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function get_quota_from_api_via_link(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-chatgpt-get-quota', 'nonce' );

		// get quota.
		$this->get_quota_from_api();

		// redirect user.
		wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
		exit;
	}

	/**
	 * Get quota as array containing 'character_spent' and 'character_limit'.
	 *
	 * @return array
	 */
	public function get_quota(): array {
		$quota = get_option( 'easy_language_chatgpt_quota', array() );

		// TODO

		// return initial values.
		return array(
			'character_spent' => 0,
			'character_limit' => -1,
			'unlimited' => true
		);
	}

	/**
	 * Get quota.
	 *
	 * TODO is it possible to get info about used and limited tokens?
	 *
	 * @param string $token
	 *
	 * @return array
	 */
	public function request_quota( string $token = '' ): array {
		// send request.
		$request = new Request();
		$request->set_token( empty( $token ) ? $this->get_token() : $token );
		$request->set_url( EASY_LANGUAGE_CHATGPT_API_URL_QUOTA );
		$request->send();

		// get the response.
		$response = $request->get_response();

		// transform it to an array and return it.
		$results = json_decode( $response, ARRAY_A );
		if ( is_array( $results ) ) {
			return $results;
		}

		// return empty array if request for quota does not give any result.
		return array();
	}

	/**
	 * Get quota from API.
	 *
	 * @param string $token The optional token.
	 *
	 * @return array
	 */
	public function get_quota_from_api( string $token = '' ): array {
		// get quota from api.
		$quota = $this->request_quota( $token );

		// get the transients object.
		$transients_obj = Transients::get_instance();

		// save value in db.
		if ( ! empty( $quota ) ) {
			update_option( 'easy_language_chatgpt_quota', $quota );

			// check if key is limited.
			if ( !empty($quota['simplification']) && absint( $quota['simplification']['subscription']['available'] ) > 0 ) {
				// show hint of 80% of limit is used.
				$percent = absint( $quota['simplification']['subscription']['remaining'] ) / absint( $quota['simplification']['subscription']['available'] );
				if ( 1 === $percent ) {
					// get the transients-object to add the new one.
					$transient_obj = $transients_obj->add();
					$transient_obj->set_dismissible_days( 2 );
					$transient_obj->set_name( 'easy_language_chatgpt_quota' );
					/* translators: %1$s will be replaced by the URL for ChatGpt support. */
					$transient_obj->set_message( sprintf( __( '<strong>Your quota for the ChatGpt API is completely depleted.</strong> You will not be able to use any translation from ChatGpt. Please contact the <a href="%1$s" target="_blank">ChatGpt support (opens new window)</a> about extending the quota.', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) ) );
					$transient_obj->set_type( 'error' );
					$transient_obj->save();
				} elseif ( $percent > apply_filters( 'easy_language_quota_percent', 0.8 ) ) {
					// get the transients-object to add the new one.
					$transient_obj = $transients_obj->add();
					$transient_obj->set_dismissible_days( 2 );
					$transient_obj->set_name( 'easy_language_chatgpt_quota' );
					/* translators: %1$s will be replaced by the URL for ChatGpt support. */
					$transient_obj->set_message( sprintf( __( '<strong>More than 80%% of your quota for the ChatGpt API has already been used.</strong> Please contact the <a href="%1$s" target="_blank">ChatGpt support (opens new window)</a> about extending the quota.', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) ) );
					$transient_obj->set_type( 'error' );
					$transient_obj->save();
				}
			}
		} else {
			// delete quota-array in db.
			delete_option( 'easy_language_chatgpt_ai_quota' );

			// delete hint.
			$transient_obj = $transients_obj->get_transient_by_name( 'easy_language_chatgpt_ai_quota' );
			$transient_obj->delete();
		}

		// return quota.
		return $quota;
	}

	/**
	 * API has settings.
	 *
	 * @return bool
	 */
	public function has_settings(): bool {
		return true;
	}

	/**
	 * Return active source languages of this API.
	 *
	 * @return array
	 */
	public function get_active_source_languages(): array {
		// get actual enabled source-languages.
		$source_languages = get_option( 'easy_language_chatgpt_source_languages', array() );
		if ( ! is_array( $source_languages ) ) {
			$source_languages = array();
		}

		// define resulting list
		$list = array();

		foreach ( $this->get_supported_source_languages() as $language_code => $language ) {
			if ( ! empty( $source_languages[ $language_code ] ) ) {
				$list[ $language_code ] = $language;
			}
		}

		// return resulting list.
		return $list;
	}

	/**
	 * Return language-specific request text for the API.
	 *
	 * @param string $source_language
	 *
	 * @return string
	 */
	public function get_request_text_by_language( string $source_language ): string {
		$request_texts = array(
			'de_DE' => 'Vereinfache bitte den folgenden Text in Leichter Sprache. Verwende dabei pro Absatz eine Zeile.',
			'de_AT' => 'Vereinfache bitte den folgenden Text in Leichter Sprache. Verwende dabei pro Absatz eine Zeile.',
			'de_CH' => 'Vereinfache bitte den folgenden Text in Leichter Sprache. Verwende dabei pro Absatz eine Zeile.',
		);
		return $request_texts[$source_language];
	}
}
