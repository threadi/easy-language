<?php
/**
 * File for handler for things the capito supports.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\Capito;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Api_Base;
use easyLanguage\Base;
use easyLanguage\Apis;
use easyLanguage\Helper;
use easyLanguage\Language_Icon;
use easyLanguage\Languages;
use easyLanguage\Log;
use easyLanguage\Multilingual_Plugins;
use easyLanguage\Multilingual_plugins\Easy_Language\Db;
use easyLanguage\Transients;
use wpdb;

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
	protected string $title = 'capito';

	/**
	 * Instance of this object.
	 *
	 * @var ?Capito
	 */
	private static ?Capito $instance = null;

	/**
	 * Set max text length for single entry for this API.
	 *
	 * @var int
	 */
	protected int $max_single_text_length = 9000;

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

		// add admin actions.
		add_action( 'admin_action_easy_language_capito_remove_token', array( $this, 'remove_token' ) );
		add_action( 'admin_action_easy_language_capito_test_token', array( $this, 'run_token_test' ) );

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
	 * Return the public description of the capito API.
	 *
	 * @return string
	 */
	public function get_description(): string {
		// get quota.
		$quota = $this->get_quota();

		/* translators: %1$d will be replaced by the link to capito */
		$text = sprintf( __( '<p><a href="%1$s" target="_blank"><strong>capito digital</strong> (opens new window)</a> is an AI-based tool for <i>Easy language</i>.<br>It helps you write better texts.</p><p>This API simplifies texts based on the Common European Framework of Reference for Languages.<br>This describes the <i>complexity of languages according to proficiency levels</i> (A1, A2, B1 ..).</p><p>The number of simplifications with capito digital is limited to <strong>quotas</strong>.</p>', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) );
		if ( $quota['character_limit'] > 0 ) {
			/* translators: %1$d will be replaced by the characters spent for capito, %2$d will be the quota for capito, %3$d will be the rest quota */
			$text .= sprintf( __( '<p><strong>Actual character spent:</strong> %1$d<br><strong>Quota limit:</strong> %2$d<br><strong>Rest quota:</strong> %3$d</strong></p>', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ), $quota['character_spent'], $quota['character_limit'], absint( $quota['character_limit'] ) - absint( $quota['character_spent'] ) );
		} elseif ( ! empty( $quota['unlimited'] ) ) {
			$text .= '<p><strong>' . __( 'Unlimited quota', 'easy-language' ) . '</strong></p>';
		} elseif ( -1 === $quota['character_limit'] ) {
			/* translators: %1$s will be replaced by a URL. */
			$text .= '<p><strong>' . __( 'The available quota is retrieved after entering the API key in the API settings.', 'easy-language' ) . ' ' . sprintf( __( 'You can see the prices for the contingents <a href="%1$s" target="_blank">here</a>.', 'easy-language' ), $this->get_prices_url() ) . '</strong></p>';
		}

		// wrapper for buttons.
		$text .= $this->get_description_buttons();

		// return resulting text.
		return $text;
	}

	/**
	 * Return the URL of the public logo for this API.
	 *
	 * @return string
	 */
	public function get_logo_url(): string {
		return Helper::get_plugin_url() . 'classes/apis/capito/gfx/2025_logo.svg';
	}

	/**
	 * Return list of supported source-languages.
	 *
	 * @return array
	 * @noinspection DuplicatedCode
	 */
	public function get_supported_source_languages(): array {
		$source_languages = array(
			'de_DE'          => array(
				'label'       => __( 'German', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Informal german spoken in germany.', 'easy-language' ),
				'icon'        => 'icon-de-de',
				'img'         => 'de_de.png',
				'img_icon'    => $this->is_active() ? Helper::get_icon_img_for_language_code( 'de_de' ) : '',
				'api_value'   => 'de',
			),
			'de_DE_formal'   => array(
				'label'       => __( 'German (Formal)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Formal german spoken in germany.', 'easy-language' ),
				'icon'        => 'icon-de-de',
				'img'         => 'de_de.png',
				'img_icon'    => $this->is_active() ? Helper::get_icon_img_for_language_code( 'de_de' ) : '',
				'api_value'   => 'de',
			),
			'de_CH'          => array(
				'label'       => __( 'German (CH)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Formal german spoken in suisse.', 'easy-language' ),
				'icon'        => 'icon-de-ch',
				'img'         => 'de_ch.png',
				'img_icon'    => $this->is_active() ? Helper::get_icon_img_for_language_code( 'de_ch' ) : '',
				'api_value'   => 'de',
			),
			'de_CH_informal' => array(
				'label'       => __( 'German (CH, informal)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Informal german spoken in suisse.', 'easy-language' ),
				'icon'        => 'icon-de-ch',
				'img'         => 'de_ch.png',
				'img_icon'    => $this->is_active() ? Helper::get_icon_img_for_language_code( 'de_ch' ) : '',
				'api_value'   => 'de',
			),
			'de_AT'          => array(
				'label'       => __( 'German (AT)', 'easy-language' ),
				'enable'      => true,
				'description' => __( 'Informal german spoken in austria.', 'easy-language' ),
				'icon'        => 'icon-de-at',
				'img'         => 'de_at.png',
				'img_icon'    => $this->is_active() ? Helper::get_icon_img_for_language_code( 'de_at' ) : '',
				'api_value'   => 'de',
			),
		);

		/**
		 * Filter capito source languages.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $source_languages List of source languages.
		 */
		return apply_filters( 'easy_language_capito_source_languages', $source_languages );
	}

	/**
	 * Return the languages this API supports.
	 *
	 * @return array
	 * @noinspection DuplicatedCode
	 */
	public function get_supported_target_languages(): array {
		$target_languages = array(
			'de_a1' => array(
				'label'       => __( 'German A1', 'easy-language' ),
				'enabled'     => true,
				'description' => __( 'The easiest level of german language.', 'easy-language' ),
				'url'         => 'de_a1',
				'api_value'   => 'a1',
				'icon'        => 'icon-de-ls',
				'img'         => 'de_LS.svg',
				'img_icon'    => $this->is_active() ? Helper::get_icon_img_for_language_code( 'de_a1' ) : '',
			),
			'de_a2' => array(
				'label'       => __( 'German A2', 'easy-language' ),
				'enabled'     => true,
				'description' => __( 'capito compares this with Leichte Sprache', 'easy-language' ),
				'url'         => 'de_a2',
				'api_value'   => 'a2',
				'icon'        => 'icon-de-ls',
				'img'         => 'de_LS.svg',
				'img_icon'    => $this->is_active() ? Helper::get_icon_img_for_language_code( 'de_a2' ) : '',
			),
			'de_b1' => array(
				'label'       => __( 'German B1', 'easy-language' ),
				'enabled'     => true,
				'description' => __( 'capito compares this with Einfache Sprache', 'easy-language' ),
				'url'         => 'de_a2',
				'api_value'   => 'b1',
				'icon'        => 'icon-de-el',
				'img'         => 'de_EL.svg',
				'img_icon'    => $this->is_active() ? Helper::get_icon_img_for_language_code( 'de_b1' ) : '',
			),
		);

		/**
		 * Filter capito target languages.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $target_languages List of target languages.
		 */
		return apply_filters( 'easy_language_capito_target_languages', $target_languages );
	}

	/**
	 * Return the list of supported languages which could be translated with this API into each other.
	 *
	 * Left source, right possible target languages.
	 *
	 * @return array
	 */
	public function get_mapping_languages(): array {
		$language_mappings = array(
			'de_DE'          => array( 'de_a1', 'de_a2', 'de_b1' ),
			'de_DE_formal'   => array( 'de_a1', 'de_a2', 'de_b1' ),
			'de_AT'          => array( 'de_a1', 'de_a2', 'de_b1' ),
			'de_CH'          => array( 'de_a1', 'de_a2', 'de_b1' ),
			'de_CH_informal' => array( 'de_a1', 'de_a2', 'de_b1' ),
		);

		/**
		 * Filter mapping of capito languages.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $language_mappings List of mappings.
		 */
		return apply_filters( 'easy_language_capito_mapping_languages', $language_mappings );
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

		// set interval for automatic simplification to daily.
		if ( ! get_option( 'easy_language_capito_quota_interval' ) ) {
			update_option( 'easy_language_capito_quota_interval', 'daily' );
		}

		// set capito api key to nothing but with active autoload.
		if ( ! get_option( 'easy_language_capito_api_key' ) ) {
			add_option( 'easy_language_capito_api_key', '', '', true );
		}

		// set capito quota array.
		if ( ! get_option( 'easy_language_capito_quota' ) ) {
			update_option( 'easy_language_capito_quota', array(), '', true );
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
		return array(
			'easy_language_capito_test_token',
		);
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
	 * @return object
	 */
	public function get_simplifications_obj(): object {
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
		// get actual enabled target-languages, if token is given.
		$target_languages = array();
		if ( $this->is_capito_token_set() ) {
			$target_languages = get_option( 'easy_language_capito_target_languages', array() );
			if ( ! is_array( $target_languages ) ) {
				$target_languages = array();
			}
		}

		// define resulting list.
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
	 * @param string $tab The tab internal name.
	 *
	 * @return void
	 */
	public function add_settings_tab( string $tab ): void {
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
		$active_class = '';
		if ( $this->get_name() === $tab ) {
			$active_class = ' nav-tab-active';
		}

		// output tab.
		echo '<a href="' . esc_url( helper::get_settings_page_url() ) . '&tab=' . esc_attr( $this->get_name() ) . '" class="nav-tab' . esc_attr( $active_class ) . '">' . esc_html__( 'capito', 'easy-language' ) . '</a>';
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
		<form method="POST" action="<?php echo esc_url( get_admin_url() ); ?>options.php">
			<?php
			settings_fields( 'easyLanguageCapitoFields' );
			do_settings_sections( 'easyLanguageCapitoPage' );
			submit_button();
			?>
		</form>
		<h2 id="statistics"><?php esc_html_e( 'capito Quota', 'easy-language' ); ?></h2>
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
				<strong><?php echo esc_html__( 'Quota', 'easy-language' ); ?>:</strong> <?php echo esc_html( $quota_text ); ?>
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
		 * The capito settings Section
		 */
		add_settings_section(
			'settings_section_capito',
			__( 'capito Settings', 'easy-language' ),
			'__return_true',
			'easyLanguageCapitoPage'
		);

		// Set description for token field if it has not been set.
		/* translators: %1$s will be replaced by the capito URL */
		$description = sprintf( __( 'Get your capito API Token <a href="%1$s" target="_blank">here (opens new window)</a> (copy "Access Token").<br>If you have any questions about the token provided by capito, please contact their support: <a href="%2$s" target="_blank">%2$s (opens new window)</a>', 'easy-language' ), esc_url( $this->get_token_url() ), esc_url( $this->get_language_specific_support_page() ) );
		if ( false !== $this->is_capito_token_set() ) {
			// Set link to test the entered token.
			$url = add_query_arg(
				array(
					'action' => 'easy_language_capito_test_token',
					'nonce'  => wp_create_nonce( 'easy-language-capito-test-token' ),
				),
				get_admin_url() . 'admin.php'
			);

			// set link to remove the token.
			$remove_token_url = add_query_arg(
				array(
					'action' => 'easy_language_capito_remove_token',
					'nonce'  => wp_create_nonce( 'easy-language-capito-remove-token' ),
				),
				get_admin_url() . 'admin.php'
			);

			// Show other description if token is set.
			/* translators: %1$s will be replaced by the capito URL */
			$description  = sprintf( __( 'If you have any questions about the token provided by capito, please contact their support: <a href="%1$s" target="_blank">%1$s (opens new window)</a>', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) );
			$description .= '<br><a href="' . esc_url( $url ) . '" class="button button-secondary easy-language-settings-button">' . __( 'Test token', 'easy-language' ) . '</a><a href="' . esc_url( $remove_token_url ) . '" class="button button-secondary easy-language-settings-button">' . __( 'Remove token', 'easy-language' ) . '</a>';
		}

		// if foreign simplification-plugin with API-support is used, hide the language-settings.
		$foreign_translation_plugin_with_api_support = false;
		foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			if ( $plugin_obj->is_foreign_plugin() && $plugin_obj->is_supporting_apis() ) {
				$foreign_translation_plugin_with_api_support = true;
			}
		}

		// capito token.
		add_settings_field(
			'easy_language_capito_api_key',
			__( 'capito API Key', 'easy-language' ),
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
		register_setting(
			'easyLanguageCapitoFields',
			'easy_language_capito_api_key',
			array(
				'sanitize_callback' => array( $this, 'validate_api_key' ),
				'show_in_rest'      => true,
			)
		);

		// Enable source-languages.
		// -> defaults to WP-locale.
		// -> if WPML, Polylang or TranslatePress is available, show additional languages.
		// -> but restrict list to languages supported by capito.
		add_settings_field(
			'easy_language_capito_source_languages',
			__( 'Choose source languages', 'easy-language' ),
			'easy_language_admin_multiple_checkboxes_field',
			'easyLanguageCapitoPage',
			'settings_section_capito',
			array(
				'label_for'   => 'easy_language_capito_source_languages',
				'fieldId'     => 'easy_language_capito_source_languages',
				'description' => __( 'These are the possible source languages for capito-simplifications. This language has to be the language which you use for any texts in your website.', 'easy-language' ),
				'options'     => $this->get_supported_source_languages(),
				'readonly'    => false === $this->is_capito_token_set() || $foreign_translation_plugin_with_api_support,
				'pro_hint'    => $this->get_pro_hint(),
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
				'description' => __( 'These are the possible target languages for capito-simplifications.', 'easy-language' ),
				'options'     => $this->get_supported_target_languages(),
				'readonly'    => false === $this->is_capito_token_set() || $foreign_translation_plugin_with_api_support,
				'pro_hint'    => $this->get_pro_hint(),
			)
		);
		register_setting( 'easyLanguageCapitoFields', 'easy_language_capito_target_languages', array( 'sanitize_callback' => array( $this, 'validate_language_settings' ) ) );

		// get possible intervals.
		$intervals = array();
		foreach ( wp_get_schedules() as $name => $schedule ) {
			$intervals[ $name ] = $schedule['display'];
		}

		/**
		 * Hook for capito automatic interval settings.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $intervals The possible intervals.
		 * @param bool $foreign_translation_plugin_with_api_support Whether we support third-party-plugins.
		 */
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
		if ( ! empty( $quota['assistance']['subscription']['remaining'] ) ) {
			return array(
				'character_spent' => $quota['assistance']['subscription']['remaining'],
				'character_limit' => 0,
				'unlimited'       => true, // we use unlimited marker as we cannot get any info about the booked quota.
			);
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
	 * @param ?string $value The API-key.
	 * @return ?string
	 * @noinspection PhpUnused
	 */
	public function validate_api_key( ?string $value ): ?string {
		$errors = array();
		if ( function_exists( 'get_settings_errors' ) ) {
			$errors = get_settings_errors();
		}

		if ( ! function_exists( 'add_settings_error' ) ) {
			return $value;
		}

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
			add_settings_error( 'easy_language_capito_api_key', 'easy_language_capito_api_key', __( 'You did not enter an API token. All simplification options via the capito API have been disabled.', 'easy-language' ) );
		} elseif ( 0 !== strcmp( $value, get_option( 'easy_language_capito_api_key', '' ) ) ) {
			// if token has been changed, get the quota and delete settings hint.
			$request = $this->get_test_request_response( $value );
			if ( in_array( $request->get_http_status(), array( 401, 404 ), true ) ) {
				// show hint if token is not valid for API.
				/* translators: %1$s is replaced by the URL for the API-log */
				add_settings_error( 'easy_language_capito_api_key', 'easy_language_capito_api_key', sprintf( __( '<strong>Token could not be verified.</strong> Please take a look <a href="%1$s">in the log</a> to check the reason.', 'easy-language' ), esc_url( Helper::get_api_logs_page_url() ) ) );

				// Log event.
				Log::get_instance()->add_log( sprintf( 'Token for capito has been changed, but we get an error from API by validation of the key. Please <a href="%1$s">check API log</a>.', esc_url( Helper::get_api_logs_page_url() ) ), 'error' );

				// remove token.
				$value = '';
			} else {
				// get initial quota.
				$this->get_quota_from_api( $value );

				// Log event.
				Log::get_instance()->add_log( __( 'Token for capito has been changed.', 'easy-language' ), 'success' );
			}

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
	 * @param ?array $values The values.
	 *
	 * @return array|null
	 */
	public function validate_language_settings( ?array $values ): ?array {
		$values = Helper::settings_validate_multiple_checkboxes( $values );
		if ( empty( $values ) ) {
			add_settings_error( 'easy_language_capito_target_languages', 'easy_language_capito_target_languages', __( 'You have to set a target-language for simplifications.', 'easy-language' ) );
		} elseif ( false === $this->is_language_set( $values ) ) {
			add_settings_error( 'easy_language_capito_target_languages', 'easy_language_capito_target_languages', __( 'At least one language cannot (currently) be simplified into the selected target languages by the API.', 'easy-language' ) );
		}

		// return value.
		return $values;
	}

	/**
	 * Set the interval for the quota-schedule, if it is enabled.
	 *
	 * @param ?string $value The value.
	 *
	 * @return ?string
	 */
	public function set_quota_interval( ?string $value ): ?string {
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

		// define resulting list.
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
	 * @param string $token The token (optional).
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
		$results = json_decode( $response, true );
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
			if ( ! empty( $quota['assistance']['subscription']['remaining'] ) ) {
				if ( absint( $quota['assistance']['subscription']['remaining'] ) > 0 && absint( $quota['assistance']['subscription']['remaining'] ) < 1000 ) {
					// get the transients-object to add the new one.
					$transient_obj = $transients_obj->add();
					$transient_obj->set_dismissible_days( 2 );
					$transient_obj->set_name( 'easy_language_capito_quota' );
					/* translators: %1$s will be replaced by the URL for capito support. */
					$transient_obj->set_message( sprintf( __( '<strong>Your quota for the capito API is nearly depleted.</strong> You will soon not be able to use any simplifications from capito. Please contact the <a href="%1$s" target="_blank">Capito support (opens new window)</a> about extending the quota.', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) ) );
					$transient_obj->set_type( 'error' );
					$transient_obj->save();
				} elseif ( 0 === absint( $quota['assistance']['subscription']['remaining'] ) ) {
					// get the transients-object to add the new one.
					$transient_obj = $transients_obj->add();
					$transient_obj->set_dismissible_days( 2 );
					$transient_obj->set_name( 'easy_language_capito_quota' );
					/* translators: %1$s will be replaced by the URL for capito support. */
					$transient_obj->set_message( sprintf( __( '<strong>Your quota for the capito API is completely depleted.</strong> You will not be able to use any simplifications from capito. Please contact the <a href="%1$s" target="_blank">Capito support (opens new window)</a> about extending the quota.', 'easy-language' ), esc_url( $this->get_language_specific_support_page() ) ) );
					$transient_obj->set_type( 'error' );
					$transient_obj->save();
				}
			}
		} else {
			// delete quota-array in db.
			delete_option( 'easy_language_capito_ai_quota' );

			// delete hint.
			$transients_obj->get_transient_by_name( 'easy_language_capito_ai_quota' )->delete();
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

		// remove our schedule.
		wp_clear_scheduled_hook( 'easy_language_capito_request_quota' );

		// redirect user.
		wp_safe_redirect( wp_get_referer() );
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
		wp_safe_redirect( wp_get_referer() );
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
		// bail if this is run via REST API.
		if ( ! Helper::is_admin_api_request() ) {
			return;
		}

		// save language-icons in db.
		foreach ( $this->get_supported_target_languages() as $language_code => $settings ) {
			$icon_obj = new Language_Icon();
			$icon_obj->set_file( $settings['img'] );
			$icon_obj->save( $language_code );
		}
	}

	/**
	 * Run a token test.
	 *
	 * @return void
	 */
	public function run_token_test(): void {
		// check nonce.
		check_ajax_referer( 'easy-language-capito-test-token', 'nonce' );

		// get global transients-object.
		$transients_obj = Transients::get_instance();

		// run test only is necessary values are set.
		if ( $this->is_capito_token_set() ) {
			// send request.
			$request = $this->get_test_request_response();

			// add new transient for response to user.
			$transient_obj = $transients_obj->add();
			$transient_obj->set_name( 'easy_language_capito_test_token' );
			if ( 200 === $request->get_http_status() ) {
				// show ok-message.
				$transient_obj->set_message( __( 'Token could be successfully verified.', 'easy-language' ) );
				$transient_obj->set_type( 'success' );
			} else {
				// show error.
				/* translators: %1$s is replaced by the URL for the API-log */
				$transient_obj->set_message( sprintf( __( '<strong>Token could not be verified.</strong> Please take a look <a href="%1$s">in the log</a> to check the reason.', 'easy-language' ), esc_url( Helper::get_api_logs_page_url() ) ) );
				$transient_obj->set_type( 'error' );
			}
		} else {
			// show error via new transients object.
			$transient_obj = $transients_obj->add();
			$transient_obj->set_message( __( 'Token missing.', 'easy-language' ) );
			$transient_obj->set_type( 'error' );
		}
		$transient_obj->save();

		// redirect user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Send test request to API.
	 *
	 * @param string $token The token for this API.
	 *
	 * @return Request
	 */
	private function get_test_request_response( string $token = '' ): Request {
		$request = new Request();
		$request->set_token( empty( $token ) ? $this->get_token() : $token );
		$request->set_url( EASY_LANGUAGE_CAPITO_SUBSCRIPTION_URL );
		$request->set_method( 'GET' );
		$request->send();

		// return object.
		return $request;
	}

	/**
	 * Return all log entries of this API.
	 *
	 * @return array
	 */
	public function get_log_entries(): array {
		$results = $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT `time`, `request`, `response` FROM ' . $this->table_requests . ' WHERE 1 = %d', array( 1 ) ) );
		if ( is_null( $results ) ) {
			return array();
		}
		return $results;
	}

	/**
	 * Get the URL where the user can get its token.
	 *
	 * @return string
	 */
	private function get_token_url(): string {
		return 'https://digital.capito.eu/token';
	}

	/**
	 * Return whether this API has extended support in Easy Language Pro.
	 *
	 * @return bool
	 */
	public function is_extended_in_pro(): bool {
		return true;
	}

	/**
	 * Return custom pro-hint for API-chooser.
	 *
	 * @return string
	 * @noinspection PhpUnused
	 */
	public function get_pro_hint(): string {
		/* translators: %1$s will be replaced by the link to laolaweb.com */
		return sprintf( __( 'More languages and Options with <a href="%1$s" target="_blank" title="link opens new window">Easy Language Pro</a>', 'easy-language' ), esc_url( Helper::get_pro_url() ) );
	}

	/**
	 * Return true whether this API would support translatepress-plugin.
	 *
	 * @return bool
	 */
	public function is_supporting_translatepress(): bool {
		return true;
	}

	/**
	 * Return class-name for translatepress-machine.
	 *
	 * @return string
	 */
	public function get_translatepress_machine_class(): string {
		return 'easyLanguage\Multilingual_plugins\TranslatePress\Translatepress_Capito_Machine_Translator';
	}

	/**
	 * Return the URL for the price list.
	 *
	 * @return string
	 */
	private function get_prices_url(): string {
		if ( Languages::get_instance()->is_german_language() ) {
			return 'https://www.capito.eu/preise-pakete/';
		}
		return 'https://www.capito.eu/en/prices-packages/';
	}
}
