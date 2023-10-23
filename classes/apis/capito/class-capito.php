<?php
/**
 * File for handler for things the Capito supports.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\Capito;

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
 * Define what Capito supports and what not.
 */
class Capito extends Base implements Api_Base {

	/**
	 * Set the internal name for the API.
	 *
	 * @var string
	 */
	protected string $name = 'capito';

	/**
	 * Set the public title for the API.
	 *
	 * @var string
	 */
	protected string $title = 'Capito';

	/**
	 * Instance of this object.
	 *
	 * @var ?Capito
	 */
	private static ?Capito $instance = null;

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
		'de_DE' => 'https://www.capito.eu/',
		'en_US' => 'https://www.capito.eu/en/',
		'en_UK' => 'https://www.capito.eu/en/',
	);

	/**
	 * Constructor for Init-Handler.
	 */
	private function __construct() {
		global $wpdb;

		// get the db-connection.
		$this->wpdb = $wpdb;

		// table for requests and responses.
		$this->table_requests = DB::get_instance()->get_wpdb_prefix() . 'easy_language_capito';

		// add settings.
		add_action( 'easy_language_settings_add_settings', array( $this, 'add_settings' ), 20 );

		// add settings tab.
		add_action( 'easy_language_settings_add_tab', array( $this, 'add_settings_tab' ), 20 );

		// add settings page.
		add_action( 'easy_language_settings_capito_page', array( $this, 'add_settings_page' ) );

		// add hook für schedules.
		add_action( 'easy_language_capito_request_quota', array( $this, 'get_quota_from_api' ) );

		// add hook to remove token.
		add_action( 'admin_action_easy_language_capito_remove_token', array( $this, 'remove_token' ) );

		// add hook to get actual quota via link.
		add_action( 'admin_action_easy_language_capito_get_quota', array( $this, 'get_quota_from_api_via_link' ) );
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
	public static function get_instance(): Capito {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Return the public description of the Capito API.
	 *
	 * @return string
	 */
	public function get_description(): string {
		// get quota.
		$quota = $this->get_quota();

		/* translators: %1$d will be replaced by the link to Capito */
		$text = sprintf( __( '<p><a href="%1$s" target="_blank"><strong>capito digital</strong> (opens new window)</a> is an AI-based tool for <i>Easy language</i>.<br>It helps you write better texts.</p><p>This API simplifies texts based on the Common European Framework of Reference for Languages.<br>This describes the <i>complexity of languages according to proficiency levels</i> (A1, A2, B1 ..).</p><p>The number of simplifications with capito digital is limited to <strong>quotas</strong>.</p>', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) );
		if ( $quota['character_limit'] > 0 ) {
			/* translators: %1$d will be replaced by the characters spent for Capito, %2$d will be the quota for Capito, %3$d will be the rest quota */
			$text .= sprintf( __( '<p><strong>Actual character spent:</strong> %1$d<br><strong>Quota limit:</strong> %2$d<br><strong>Rest quota:</strong> %3$d</strong></p>', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ), $quota['character_spent'], $quota['character_limit'], absint( $quota['character_limit'] ) - absint( $quota['character_spent'] ) );
		} elseif ( ! empty( $quota['unlimited'] ) ) {
			$text .= __( '<p><strong>Unlimited quota</strong></p>', 'easy-language' );
		} elseif ( -1 === $quota['character_limit'] ) {
			$text .= '<p><strong>' . __( 'The available quota is retrieved after entering the API key in the API settings.', 'easy-language' ) . '</strong></p>';
		}

		// wrapper for buttons.
		$text .= '<p>';

		// help-button.
		$text .= '<a href="' . esc_url( $this->get_language_specific_support_page() ) . '" target="_blank" class="button button-primary" title="' . esc_attr( __( 'Get help for this API', 'easy-language' ) ) . '"><span class="dashicons dashicons-editor-help"></span></a>';

		// Show setting-button if this API is enabled.
		if ( $this->is_active() ) {
			$text .= '<a href="' . esc_html( $this->get_settings_url() ) . '" class="button button-primary" title="' . esc_html__( 'Go to settings', 'easy-languag' ) . '"><span class="dashicons dashicons-admin-generic"></span></a>';
		}

		$text .= '</p>';

		// return resulting text.
		return $text;
	}

	/**
	 * Return the URL of the public logo for this API.
	 *
	 * @return string
	 */
	public function get_logo_url(): string {
		return Helper::get_plugin_url() . 'classes/apis/capito/gfx/logo.svg';
	}

	/**
	 * Return list of supported source-languages.
	 *
	 * @return array
	 * @noinspection DuplicatedCode
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
			'en_UK' => array(
				'label'       => __( 'English', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'English spoken in the United Kingdom.', 'easy-language' ),
				'api_value'   => 'en',
			),
			'en_US' => array(
				'label'       => __( 'English', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'English spoken in the USA.', 'easy-language' ),
				'api_value'   => 'en',
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
			'de_a1' => array(
				'label'       => __( 'German A1', 'easy-language' ),
				'enabled'     => true,
				'description' => __( 'The easiest level of german language.', 'easy-language' ),
				'url'         => 'de_a1',
				'api_value'   => 'a1',
				'icon' => 'icon-de-ls',
				'img' => 'de_LS.png',
				'img_icon' => Helper::get_icon_img_for_language_code( 'de_a1' )
			),
			'de_a2' => array(
				'label'       => __( 'German A2', 'easy-language' ),
				'enabled'     => true,
				'description' => __( 'Capito compares this with Leichte Sprache', 'easy-language' ),
				'url'         => 'de_a2',
				'api_value'   => 'a2',
				'icon' => 'icon-de-ls',
				'img' => 'de_LS.png',
				'img_icon' => Helper::get_icon_img_for_language_code( 'de_a2' )
			),
			'de_b1' => array(
				'label'       => __( 'German B1', 'easy-language' ),
				'enabled'     => true,
				'description' => __( 'Capito compares this with Einfache Sprache', 'easy-language' ),
				'url'         => 'de_a2',
				'api_value'   => 'b1',
				'icon' => 'icon-de-el',
				'img' => 'de_EL.png',
				'img_icon' => Helper::get_icon_img_for_language_code( 'de_b1' )
			),
			'en_a1' => array(
				'label'       => __( 'English A1', 'easy-language' ),
				'enabled'     => true,
				'description' => __( 'The easiest level of english language.', 'easy-language' ),
				'url'         => 'de_a1',
				'api_value'   => 'a1',
				'icon' => 'icon-de-ls',
				'img' => 'en_LS.png',
				'img_icon' => Helper::get_icon_img_for_language_code( 'en_a1' )
			),
			'en_a2' => array(
				'label'       => __( 'English A2', 'easy-language' ),
				'enabled'     => true,
				'description' => __( 'Capito compares this with Plain Sprache', 'easy-language' ),
				'url'         => 'de_a2',
				'api_value'   => 'a2',
				'icon' => 'icon-de-ls',
				'img' => 'en_LS.png',
				'img_icon' => Helper::get_icon_img_for_language_code( 'en_a2' )
			),
			'en_b1' => array(
				'label'       => __( 'English B1', 'easy-language' ),
				'enabled'     => true,
				'description' => __( 'Capito compares this with Easy Language', 'easy-language' ),
				'url'         => 'de_a2',
				'api_value'   => 'b1',
				'icon' => 'icon-de-ls',
				'img' => 'en_EL.png',
				'img_icon' => Helper::get_icon_img_for_language_code( 'en_b1' )
			),
		);
	}

	/**
	 * Return the list of supported languages which could be translated with this API into each other.
	 *
	 * Left source, right possible target languages.
	 *
	 * @return array
	 */
	public function get_mapping_languages(): array {
		return array(
			'de_DE' => array( 'de_a1', 'de_a2', 'de_b1' ),
			'en'    => array( 'en_a1', 'en_a2', 'en_b1' ),
		);
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
	 * Install-routines for the API, called during plugin-activation.
	 *
	 * @return void
	 */
	public function install(): void {
		global $wpdb;

		// set source language depending on WP-locale and its support.
		if ( ! get_option( 'easy_language_capito_source_languages' ) ) {
			$language  = helper::get_wp_lang();
			$languages = array( $language => '1' );
			update_option( 'easy_language_capito_source_languages', $languages );
		}

		// set target language depending on source-language and if only one target could be possible.
		if ( ! get_option( 'easy_language_capito_target_languages' ) ) {
			$language  = helper::get_wp_lang();
			$languages = array( 'de_b1' => '1' );
			if ( false !== str_contains( $language, 'en_' ) ) {
				$languages = array( 'en_b1' => '1' );
			}
			update_option( 'easy_language_capito_target_languages', $languages );
		}

		// set simplification mode to manuell.
		if ( ! get_option( 'easy_language_capito_automatic_mode' ) ) {
			update_option( 'easy_language_capito_automatic_mode', 'manuell' );
		}

		// set schedule for automatic simplification.
		$this->set_automatic_mode( get_option( 'easy_language_capito_automatic_mode', 'disabled' ) );

		// set interval for automatic simplification to daily.
		if ( ! get_option( 'easy_language_capito_quota_interval' ) ) {
			update_option( 'easy_language_capito_quota_interval', 'daily' );
		}

		// set capito api key to nothing but with active autoload.
		if ( ! get_option( 'easy_language_capito_api_key' ) ) {
			update_option( 'easy_language_capito_api_key', '', true );
		}

		// set capito quota array.
		if ( ! get_option( 'easy_language_capito_quota' ) ) {
			update_option( 'easy_language_capito_quota', array(), true );
		}

		// check if quota-interval does already exist.
		if (!wp_next_scheduled('easy_language_capito_request_quota')) {
			// add it.
			wp_schedule_event(time(), get_option('easy_language_capito_quota_interval', 'daily' ), 'easy_language_capito_request_quota');
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
	 * Deactivate-routines for the API, called during plugin-deactivation.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// remove our own schedule.
		wp_clear_scheduled_hook( 'easy_language_capito_request_quota' );
	}

	/**
	 * Install-routines for the API, called during plugin-activation and API-change.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		// remove our own schedule.
		wp_clear_scheduled_hook( 'easy_language_capito_request_quota' );

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
	 * Return list of options this plugin is using, e.g. for clean uninstallation.
	 *
	 * @return array
	 */
	private function get_options(): array {
		return array(
			'easy_language_capito_api_key',
			'easy_language_capito_source_languages',
			'easy_language_capito_target_languages',
			'easy_language_capito_automatic_mode',
			'easy_language_capito_interval',
			'easy_language_capito_quota',
			'easy_language_capito_quota_interval',
		);
	}

	/**
	 * Return list of transients this plugin is using, e.g. for clean uninstall.
	 *
	 * @return array
	 */
	private function get_transients(): array {
		return array();
	}

	/**
	 * Initialize api-specific CLI-functions for this API: none.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Return the simplifications-object.
	 *
	 * @return Simplifications
	 */
	public function get_simplifications_obj(): Simplifications {
		// get the object.
		$obj = Simplifications::get_instance();

		// initialize it.
		$obj->init( $this );

		// return resulting object.
		return $obj;
	}

	/**
	 * Return supported target languages.
	 *
	 * @return array
	 */
	public function get_active_target_languages(): array {
		// get actual enabled target-languages.
		$target_languages = get_option( 'easy_language_capito_target_languages', array() );
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
	 * Delete our own transients during disabling this API.
	 *
	 * @return void
	 */
	public function disable(): void {
		// remove our own schedule.
		wp_clear_scheduled_hook( 'easy_language_capito_request_quota' );

		$transients_obj = Transients::get_instance();
		foreach ( $this->get_transients() as $transient_name ) {
			$transient_obj = $transients_obj->get_transient_by_name( $transient_name );
			$transient_obj->delete();
		}
	}

	/**
	 * Add settings tab.
	 *
	 * @param $tab
	 *
	 * @return void
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
		echo '<a href="' . esc_url( helper::get_settings_page_url() ) . '&tab=' . esc_attr( $this->get_name() ) . '" class="nav-tab' . esc_attr( $activeClass ) . '">' . __( 'Capito', 'easy-language' ) . '</a>';
	}

	/**
	 * Add settings page.
	 *
	 * @return void
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
			settings_fields( 'easyLanguageCapitoFields' );
			do_settings_sections( 'easyLanguageCapitoPage' );
			submit_button();
			?>
		</form>
		<h2 id="statistics"><?php esc_html_e( 'Capito Quota', 'easy-language' ); ?></h2>
		<?php
		if ( $this->is_capito_token_set() ) {
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
			$update_quota_url = add_query_arg(
				array(
					'action' => 'easy_language_capito_get_quota',
					'nonce'  => wp_create_nonce( 'easy-language-capito-get-quota' ),
				),
				get_admin_url() . 'admin.php'
			);

			// output.
			?>
			<p>
				<strong><?php echo esc_html__( 'Quota', 'easy-language' ); ?>:</strong> <?php echo $quota_text; ?>
				<a href="<?php echo esc_url( $update_quota_url ); ?>#statistics" class="button button-secondary"><?php echo esc_html__( 'Update now', 'easy-language' ); ?></a>
			</p>
			<?php
		} else {
			?>
			<p><?php echo esc_html__( 'Info about quota will be available until the API token is set', 'easy-language' ); ?></p>
			<?php
		}
	}

	/**
	 * Add capito settings.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		/**
		 * Capito settings Section
		 */
		add_settings_section(
			'settings_section_capito',
			__( 'Capito Settings', 'easy-language' ),
			'__return_true',
			'easyLanguageCapitoPage'
		);

		// Set description for token field if it has not been set.
		/* translators: %1$s will be replaced by the Capito URL */
		$description = sprintf( __( 'Get your Capito API Token <a href="%1$s" target="_blank">here (opens new window)</a>.<br>If you have any questions about the token provided by Capito, please contact their support: <a href="%1$s" target="_blank">%1$s (opens new window)</a>', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) );
		if ( false !== $this->is_capito_token_set() ) {
			// set link to remove the token.
			$remove_token_url = add_query_arg(
				array(
					'action' => 'easy_language_capito_remove_token',
					'nonce'  => wp_create_nonce( 'easy-language-capito-remove-token' ),
				),
				get_admin_url() . 'admin.php'
			);

			// Show other description if token is set.
			/* translators: %1$s will be replaced by the Capito URL */
			$description  = sprintf( __( 'If you have any questions about the token provided by Capito, please contact their support: <a href="%1$s" target="_blank">%1$s (opens new window)</a>', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) );
			$description .= '<br><a href="' . esc_url( $remove_token_url ) . '" class="button button-secondary easy-language-settings-button">' . __( 'Remove token', 'easy-language' ) . '</a>';
		}

		// if foreign simplification-plugin with API-support is used, hide the language-settings.
		$foreign_translation_plugin_with_api_support = false;
		foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			if ( $plugin_obj->is_foreign_plugin() && $plugin_obj->is_supporting_apis() ) {
				$foreign_translation_plugin_with_api_support = true;
			}
		}

		// Capito token.
		add_settings_field(
			'easy_language_capito_api_key',
			__( 'Capito API Key', 'easy-language' ),
			'easy_language_admin_text_field',
			'easyLanguageCapitoPage',
			'settings_section_capito',
			array(
				'label_for'   => 'easy_language_capito_api_key',
				'fieldId'     => 'easy_language_capito_api_key',
				'description' => $description,
				'placeholder' => __( 'Enter token here', 'easy-language' ),
				'highlight'   => false === $this->is_capito_token_set(),
			)
		);
		register_setting( 'easyLanguageCapitoFields', 'easy_language_capito_api_key', array( 'sanitize_callback' => array( $this, 'validate_api_key' ) ) );

		// Enable source-languages
		// -> defaults to WP-locale
		// -> if WPML, Polylang or TranslatePress is available, show additional languages
		// -> but restrict list to languages supported by Capito
		add_settings_field(
			'easy_language_capito_source_languages',
			__( 'Choose source languages', 'easy-language' ),
			'easy_language_admin_multiple_checkboxes_field',
			'easyLanguageCapitoPage',
			'settings_section_capito',
			array(
				'label_for'   => 'easy_language_capito_source_languages',
				'fieldId'     => 'easy_language_capito_source_languages',
				'description' => __( 'These are the possible source languages for Capito-simplifications. This language has to be the language which you use for any texts in your website.', 'easy-language' ),
				'options'     => $this->get_supported_source_languages(),
				'readonly'    => false === $this->is_capito_token_set() || $foreign_translation_plugin_with_api_support,
			)
		);
		register_setting( 'easyLanguageCapitoFields', 'easy_language_capito_source_languages', array( 'sanitize_callback' => 'easyLanguage\Helper::settings_validate_multiple_checkboxes' ) );

		// Enable target languages.
		add_settings_field(
			'easy_language_capito_target_languages',
			__( 'Choose target languages', 'easy-language' ),
			'easy_language_admin_multiple_checkboxes_field',
			'easyLanguageCapitoPage',
			'settings_section_capito',
			array(
				'label_for'   => 'easy_language_capito_target_languages',
				'fieldId'     => 'easy_language_capito_target_languages',
				'description' => __( 'These are the possible target languages for Capito-simplifications.', 'easy-language' ),
				'options'     => $this->get_supported_target_languages(),
				'readonly'    => false === $this->is_capito_token_set() || $foreign_translation_plugin_with_api_support,
			)
		);
		register_setting( 'easyLanguageCapitoFields', 'easy_language_capito_target_languages', array( 'sanitize_callback' => array( $this, 'validate_language_settings' ) ) );

		// Set simplification mode.
		add_settings_field(
			'easy_language_capito_automatic_mode',
			__( 'Choose simplification-mode', 'easy-language' ),
			'easy_language_admin_multiple_radio_field',
			'easyLanguageCapitoPage',
			'settings_section_capito',
			array(
				'label_for' => 'easy_language_capito_automatic_mode',
				'fieldId'   => 'easy_language_capito_automatic_mode',
				'options'   => apply_filters( 'easy_language_capito_automatic_mode', array(
					'disabled'  => array(
						'label'       => __( 'Disabled', 'easy-language' ),
						'enabled'     => true,
						'description' => __( 'You have to write all simplifications manually. The API will not be used.', 'easy-language' ),
					),
					'manuell'   => array(
						'label'       => __( 'Simplify texts manually, use API as helper.', 'easy-language' ),
						'enabled'     => true,
						'description' => __( 'The system will not check automatically for simplifications. Its your decision.', 'easy-language' ),
					),
					'automatic' => array(
						'label'       => __( 'Automatic simplification of each text.', 'easy-language' ),
						'enabled'     => false,
						'pro_hint' => __( 'Use this mode and many other options with %1$s.', 'easy-language' )
					)),
				),
				'readonly'  => false === $this->is_capito_token_set() || $foreign_translation_plugin_with_api_support,
			)
		);
		register_setting( 'easyLanguageCapitoFields', 'easy_language_capito_automatic_mode', array( 'sanitize_callback' => array( $this, 'set_automatic_mode' ) ) );

		// get possible intervals.
		$intervals = array();
		foreach ( wp_get_schedules() as $name => $schedule ) {
			$intervals[ $name ] = $schedule['display'];
		}

		do_action( 'easy_language_capito_automatic_interval', $intervals, $foreign_translation_plugin_with_api_support );

		// Interval for quota-request.
		add_settings_field(
			'easy_language_capito_quota_interval',
			__( 'Interval for quota request', 'easy-language' ),
			'easy_language_admin_select_field',
			'easyLanguageCapitoPage',
			'settings_section_capito',
			array(
				'label_for'   => 'easy_language_capito_quota_interval',
				'fieldId'     => 'easy_language_capito_quota_interval',
				'values'      => $intervals,
				'readonly'    => ! $this->is_capito_token_set(),
				'description' => __( 'The actual API quota will be requested in this interval.', 'easy-language' ),
			)
		);
		register_setting( 'easyLanguageCapitoFields', 'easy_language_capito_quota_interval', array( 'sanitize_callback' => array( $this, 'set_quota_interval' ) ) );
	}

	/**
	 * Get quota as array containing 'character_spent' and 'character_limit'.
	 *
	 * @return array
	 */
	public function get_quota(): array {
		$quota = get_option( 'easy_language_capito_quota', array() );
		if ( ! empty( $quota['simplification'] ) ) {
			$return = array(
				'character_spent' => absint( $quota['simplification']['subscription']['available'] ) - absint( $quota['simplification']['subscription']['remaining'] ),
				'character_limit' => absint( $quota['simplification']['subscription']['available'] ),
			);
			if ( 0 === absint( $quota['simplification']['subscription']['available'] ) ) {
				$return['unlimited'] = true;
			}
			return $return;
		}

		// return initial values.
		return array(
			'character_spent' => 0,
			'character_limit' => -1,
		);
	}

	/**
	 * Return API URL.
	 *
	 * @return string
	 */
	public function get_api_url(): string {
		return EASY_LANGUAGE_CAPITO_API_URL;
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
	 * API has settings.
	 *
	 * @return bool
	 */
	public function has_settings(): bool {
		return true;
	}

	/**
	 * Return true if token is set.
	 *
	 * @return bool
	 */
	public function is_capito_token_set(): bool {
		return ! empty( $this->get_token() );
	}

	/**
	 * Get the token.
	 *
	 * @return string
	 */
	public function get_token(): string {
		return (string) get_option( 'easy_language_capito_api_key', '' );
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
		if ( helper::check_if_setting_error_entry_exists_in_array( 'easy_language_capito_api_key', $errors ) ) {
			return $value;
		}

		// if no token has been entered, show hint.
		if ( empty( $value ) ) {
			add_settings_error( 'easy_language_capito_api_key', 'easy_language_capito_api_key', __( 'You did not enter an API token. All simplification options via the Capito API have been disabled.', 'easy-language' ) );
		}
		// TODO validate key against the API.
		// if token has been changed, get the quota and delete settings hint.
		elseif ( 0 !== strcmp( $value, get_option( 'easy_language_capito_api_key', '' ) ) ) {
			$this->get_quota_from_api( $value );

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
			add_settings_error( 'easy_language_capito_target_languages', 'easy_language_capito_target_languages', __( 'You have to set a target-language for simplifications.', 'easy-language' ) );
		} elseif ( false === $this->is_language_set( $values ) ) {
			add_settings_error( 'easy_language_capito_target_languages', 'easy_language_capito_target_languages', __( 'At least one language cannot (currently) be translated into the selected target languages by the API.', 'easy-language' ) );
		}

		// return value.
		return $values;
	}

	/**
	 * Set the automatic mode for the simplifications.
	 *
	 * @param $value
	 * @return string|null
	 */
	public function set_automatic_mode( $value ): ?string {
		$value = Helper::settings_validate_multiple_radios( $value );

		do_action( 'easy_language_capito_automatic_sanitize', $value );

		return $value;
	}

	/**
	 * Set the interval for the automatic-schedule, if it is enabled.
	 *
	 * @param $value
	 *
	 * @return ?string
	 */
	public function set_interval( $value ): ?string {
		// validate value.
		$value = Helper::settings_validate_select_field( $value );

		// run additional tasks.
		do_action( 'easy_language_capito_automatic_sanitize', $value );

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
			wp_clear_scheduled_hook( 'easy_language_capito_request_quota' );
			wp_schedule_event( time(), $value, 'easy_language_capito_request_quota' );
		}

		// return setting.
		return $value;
	}

	/**
	 * Return active source languages of this API.
	 *
	 * @return array
	 */
	public function get_active_source_languages(): array {
		// get actual enabled source-languages.
		$source_languages = get_option( 'easy_language_capito_source_languages', array() );
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
	 * Get quota.
	 *
	 * @param string $token
	 *
	 * @return array
	 */
	public function request_quota( string $token = '' ): array {
		// send request.
		$request = new Request();
		$request->set_token( empty( $token ) ? $this->get_token() : $token );
		$request->set_url( EASY_LANGUAGE_CAPITO_API_URL_QUOTA );
		$request->set_method( 'GET' );
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
			update_option( 'easy_language_capito_quota', $quota );

			// check if key is limited.
			if ( ! empty( $quota['simplification'] ) && absint( $quota['simplification']['subscription']['available'] ) > 0 ) {
				// show hint of 80% of limit is used.
				$percent = absint( $quota['simplification']['subscription']['remaining'] ) / absint( $quota['simplification']['subscription']['available'] );
				if ( 1 === $percent ) {
					// get the transients-object to add the new one.
					$transient_obj = $transients_obj->add();
					$transient_obj->set_dismissible_days( 2 );
					$transient_obj->set_name( 'easy_language_capito_quota' );
					/* translators: %1$s will be replaced by the URL for Capito support. */
					$transient_obj->set_message( sprintf( __( '<strong>Your quota for the Capito API is completely depleted.</strong> You will not be able to use any simplifications from Capito. Please contact the <a href="%1$s" target="_blank">Capito support (opens new window)</a> about extending the quota.', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) ) );
					$transient_obj->set_type( 'error' );
					$transient_obj->save();
				} elseif ( $percent > apply_filters( 'easy_language_quota_percent', 0.8 ) ) {
					// get the transients-object to add the new one.
					$transient_obj = $transients_obj->add();
					$transient_obj->set_dismissible_days( 2 );
					$transient_obj->set_name( 'easy_language_capito_quota' );
					/* translators: %1$s will be replaced by the URL for Capito support. */
					$transient_obj->set_message( sprintf( __( '<strong>More than 80%% of your quota for the Capito API has already been used.</strong> Please contact the <a href="%1$s" target="_blank">Capito support (opens new window)</a> about extending the quota.', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) ) );
					$transient_obj->set_type( 'error' );
					$transient_obj->save();
				}
			}
		} else {
			// delete quota-array in db.
			delete_option( 'easy_language_capito_ai_quota' );

			// delete hint.
			$transient_obj = $transients_obj->get_transient_by_name( 'easy_language_capito_ai_quota' );
			$transient_obj->delete();
		}

		// return quota.
		return $quota;
	}

	/**
	 * Remove token via click.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function remove_token(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-capito-remove-token', 'nonce' );

		// delete settings.
		delete_option( 'easy_language_capito_api_key' );
		delete_option( 'easy_language_capito_quota' );

		// delete quota-hint.
		$transients_obj = Transients::get_instance();
		$transient_obj  = $transients_obj->get_transient_by_name( 'easy_language_capito_quota' );
		$transient_obj->delete();

		// Remove intro-hint if it is enabled.
		if ( 1 === absint( get_option( 'easy_language_intro_step_2', 0 ) ) ) {
			delete_option( 'easy_language_intro_step_2' );
		}

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
		check_ajax_referer( 'easy-language-capito-get-quota', 'nonce' );

		// get quota.
		$this->get_quota_from_api();

		// redirect user.
		wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
		exit;
	}

	/**
	 * Return whether this API is configured (true) or not (false).
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return ! empty( $this->get_token() );
	}

	/**
	 * Enable-routines for the API, called on the new API if another API is chosen.
	 *
	 * @return void
	 */
	public function enable(): void {
		// check if quota-interval does already exist.
		if (!wp_next_scheduled('easy_language_capito_request_quota')) {
			// add it.
			wp_schedule_event(time(), get_option('easy_language_capito_quota_interval', 'daily' ), 'easy_language_capito_request_quota');
		}
	}
}
