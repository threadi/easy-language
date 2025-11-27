<?php
/**
 * File for initializing the support for TranslatePress.
 *
 * @package easy-language
 */

namespace easyLanguage\ThirdPartySupport\TranslatePress;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Apis;
use easyLanguage\Plugin\Base;
use easyLanguage\Plugin\Helper;
use easyLanguage\Plugin\Languages;
use easyLanguage\Plugin\ThirdPartySupport_Base;
use easyLanguage\Dependencies\easyTransientsForWordPress\Transients;
use TRP_Translate_Press;

/**
 * Object to handle the support for TranslatePress.
 */
class Init extends Base implements ThirdPartySupport_Base {
	/**
	 * Marker for API-support.
	 *
	 * @var bool
	 */
	protected bool $supports_apis = true;

	/**
	 * Marker if plugin has own API-configuration.
	 *
	 * @var bool
	 */
	protected bool $has_own_api_config = true;

	/**
	 * Name of this plugin.
	 *
	 * @var string
	 */
	protected string $name = 'translatepress';

	/**
	 * Title of this plugin.
	 *
	 * @var string
	 */
	protected string $title = 'TranslatePress';

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
		// bail if plugin is not enabled.
		if( ! $this->is_active() ) {
			return;
		}

		add_action( 'deactivate_translatepress-multilingual/index.php', array( $this, 'foreign_deactivate' ) );
		add_filter( 'trp_wp_languages', array( $this, 'add_to_wp_list' ) );
		add_filter( 'trp_automatic_translation_engines_classes', array( $this, 'add_automatic_machine' ) );
		add_filter( 'trp_machine_translation_engines', array( $this, 'add_automatic_engine' ), 30 );
		add_filter( 'trp_mt_available_supported_languages', array( $this, 'get_supported_languages_for_trp' ), 10, 3 );
		add_filter( 'trp_ls_floating_current_language', array( $this, 'set_current_language_fields' ), 10, 3 );
		add_filter( 'trp_flags_path', array( $this, 'set_flag' ), 10, 2 );
		add_action( 'trp_machine_translation_extra_settings_middle', array( $this, 'add_settings' ), 40, 0 );
		add_filter( 'trp_machine_translation_sanitize_settings', array( $this, 'sanitize_settings' ), 10, 1 );
	}

	/**
	 * Initialize our main CLI-functions.
	 *
	 * @return void
	 * @noinspection PhpFullyQualifiedNameUsageInspection
	 */
	public function cli(): void {
		\WP_CLI::add_command( 'easy-language', 'easyLanguage\ThirdPartySupport\TranslatePress\Cli' );
	}

	/**
	 * Run on plugin-installation.
	 *
	 * @return void
	 */
	public function install(): void {}

	/**
	 * Run on uninstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void {}

	/**
	 * Run on deactivation.
	 *
	 * @return void
	 */
	public function deactivation(): void {}

	/**
	 * Run on deactivation of translatepress.
	 *
	 * @return void
	 */
	public function foreign_deactivate(): void {
		// get transient objects-object.
		$transients_obj = Transients::get_instance();

		// get transient-object for this plugin.
		$transient_obj = $transients_obj->get_transient_by_name( 'easy_language_plugin_' . $this->get_name() );

		// delete it.
		$transient_obj->delete();
	}

	/**
	 * We do not add any styles or scripts for translatepress.
	 *
	 * @return void
	 */
	public function get_simplifications_scripts(): void {}

	/**
	 * Return list of active languages this plugin is using atm.
	 *
	 * @return array<string,string>
	 */
	public function get_active_languages(): array {
		// get settings from translatepress.
		$trp       = TRP_Translate_Press::get_trp_instance();

		// bail if trp instance could not be loaded.
		if( is_null( $trp ) ) {
			return array();
		}

		$trp_query = $trp->get_component( 'settings' );

		// initialize the list to return.
		$languages = array();

		// loop through the languages activated in WPML.
		foreach ( $trp_query->get_setting( 'translation-languages' ) as $language ) {
			$languages[ $language ] = '1';
		}

		// return resulting list of locales (e.g. "de_EL").
		return $languages;
	}

	/**
	 * Add our languages to the list of all languages from translatepress.
	 *
	 * @param array<string,array<string,mixed>> $supported_languages_list List of supported languages.
	 * @return array<string,array<string,mixed>>
	 */
	public function add_to_wp_list( array $supported_languages_list ): array {
		// remove our own filter to prevent loop.
		remove_filter( 'trp_wp_languages', array( $this, 'add_to_wp_list' ) );

		// get possible target-languages.
		$languages = Languages::get_instance()->get_possible_target_languages();

		// add them to the list.
		foreach ( $languages as $language_code => $language ) {
			if ( empty( $supported_languages_list[ $language['url'] ] ) ) {
				$supported_languages_list[ $language['url'] ] = array(
					'language'     => $language_code,
					'english_name' => $language['label'],
					'native_name'  => $language['label'],
					'iso'          => array(
						1 => $language_code,
					),
				);
			}
		}

		// re-add our own filter.
		add_filter( 'trp_wp_languages', array( $this, 'add_to_wp_list' ) );

		// return the resulting list.
		return $supported_languages_list;
	}

	/**
	 * Add our automatic machine as functions.
	 *
	 * @param array<string,string> $api_list List of supported languages.
	 * @return array<string,string>
	 */
	public function add_automatic_machine( array $api_list ): array {
		// get active API and add it if they support this plugin.
		$api_obj = Apis::get_instance()->get_active_api();

		// bail if no active API is set.
		if( ! $api_obj ) {
			return $api_list;
		}

		// bail if API does not support translatepress.
		if ( ! $api_obj->is_supporting_translatepress() ) {
			return $api_list;
		}

		// add the translatepress class to the list.
		$api_list[ $api_obj->get_name() ] = $api_obj->get_translatepress_machine_class();

		// return resulting list.
		return $api_list;
	}

	/**
	 * Add the automatic machine to the list in translatePress-backend.
	 *
	 * @param array<int,array<string,string>> $engines List of supported simplify engines.
	 * @return array<int,array<string,string>>
	 */
	public function add_automatic_engine( array $engines ): array {
		// get active API and add it if they support this plugin.
		$api_obj = Apis::get_instance()->get_active_api();

		// bail if no active API is set.
		if( ! $api_obj ) {
			return $engines;
		}

		// bail if API does not support TranslatePress.
		if ( ! $api_obj->is_supporting_translatepress() ) {
			return $engines;
		}

		// add the engine settings.
		$engines[] = array(
			'value' => $api_obj->get_name(),
			'label' => $api_obj->get_title(),
		);

		// return the resulting list.
		return $engines;
	}

	/**
	 * Truncate any simplifications for Leichte Sprache.
	 *
	 * @return void
	 */
	public function reset_simplifications(): void {
		global $wpdb;
		$trp       = TRP_Translate_Press::get_trp_instance();

		// bail if class could not be loaded.
		if( is_null( $trp ) ) {
			return;
		}

		// get the query.
		$trp_query = $trp->get_component( 'query' );

		// get possible target-languages.
		$languages = Languages::get_instance()->get_possible_target_languages();

		// add them to the list.
		foreach ( $languages as $language_code => $language ) {
			// check if the table exist.
			if ( ! empty( $wpdb->get_results( $wpdb->prepare( 'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = %s', array( $trp_query->get_table_name( strtolower( $language_code ) ) ) ) ) ) ) {
				// truncate tables.
				$wpdb->query( sprintf( 'TRUNCATE TABLE %s', esc_sql( $trp_query->get_table_name( strtolower( $language_code ) ) ) ) ); // @phpstan-ignore argument.type
				$wpdb->query( sprintf( 'TRUNCATE TABLE %s', esc_sql( $trp_query->get_gettext_table_name( strtolower( $language_code ) ) ) ) ); // @phpstan-ignore argument.type
			}
		}
	}

	/**
	 * Check for supported languages.
	 *
	 * @param bool  $all_are_available Whether all languages are available.
	 * @param array<string,string> $trp_languages List of languages in translatepress.
	 * @param array<string,array<string,mixed>> $settings List of settings.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 **/
	public function get_supported_languages_for_trp( bool $all_are_available, array $trp_languages, array $settings ): bool {
		if ( in_array( $settings['trp_machine_translation_settings']['translation-engine'], array( 'summ-ai', 'capito' ), true ) ) {
			// remove our own filter to prevent loop.
			remove_filter( 'trp_mt_available_supported_languages', array( $this, 'get_supported_languages_for_trp' ) );

			// get possible target-languages.
			$languages = Languages::get_instance()->get_possible_target_languages();

			// add them to the list.
			foreach ( $languages as $language_code => $language ) {
				if ( in_array( $language_code, $languages, true ) ) {
					// re-add our own filter.
					add_filter( 'trp_mt_available_supported_languages', array( $this, 'get_supported_languages_for_trp' ), 10, 3 );

					// return true as we detected that this language is supported.
					return true;
				}
			}

			// re-add our own filter.
			add_filter( 'trp_mt_available_supported_languages', array( $this, 'get_supported_languages_for_trp' ), 10, 3 );
		}
		return $all_are_available;
	}

	/**
	 * Add settings for our individual language for language-switcher in frontend.
	 *
	 * @param array<string>  $current_language The current language.
	 * @param array<string>  $trp_published_languages The list of published languages.
	 * @param string $trp_language The translatePress-language.
	 * @return array<string>
	 */
	public function set_current_language_fields( array $current_language, array $trp_published_languages, string $trp_language ): array {
		// get possible target-languages.
		$languages = Languages::get_instance()->get_possible_target_languages();

		// add them to the list.
		foreach ( $languages as $language_code => $language ) {
			if ( $language_code === $trp_language ) {
				$current_language = array(
					'name' => $language['label'],
					'code' => $language_code,
				);
			}
		}

		// return the resulting array.
		return $current_language;
	}

	/**
	 * Change path for our own language-flag.
	 *
	 * @param string $flags_path Path to the flags.
	 * @param string $searched_language_code Checked language-code.
	 * @return string
	 */
	public function set_flag( string $flags_path, string $searched_language_code ): string {
		// get possible target-languages.
		$languages = Languages::get_instance()->get_possible_target_languages();

		// add them to the list.
		foreach ( $languages as $language_code => $language ) {
			if ( $language_code === $searched_language_code ) {
				$flags_path = trailingslashit( dirname( Helper::get_icon_path_for_language_code( $language_code ) ) );
			}
		}

		// return the given path.
		return $flags_path;
	}

	/**
	 * Add our individual settings.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// define url to SUMM AI settings.
		$summ_ai_settings = add_query_arg(
			array(
				'page' => 'easy_language_settings',
				'tab'  => 'summ_ai',
			),
			get_admin_url() . 'options-general.php'
		);

		// output.
		?>
		<tr>
			<th scope="row"><label for="trp-summ-ai-key"><?php esc_html_e( 'SUMM AI API Key', 'easy-language' ); ?></label></th>
			<td>
				<?php
				/* translators: %1%s will be replaced by the URL for plugin-settings */
				echo wp_kses_post( sprintf( __( 'Add your API-key in the <a href="%1$s">Easy Language plugin settings</a>.', 'easy-language' ), esc_url( $summ_ai_settings ) ) );
				?>
			</td>

		</tr>
		<?php
	}

	/**
	 * Check our individual settings.
	 *
	 * @param array<string,string> $settings List of settings.
	 * @return array<string,string>
	 */
	public function sanitize_settings( array $settings ): array {
		// check for nonce.
		if ( isset( $_POST['easy-language-verify'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['easy-language-verify'] ) ), 'submit-application' ) ) {
			return $settings;
		}

		if ( ! empty( $_POST['trp_machine_translation_settings']['summ-ai-key'] ) ) {
			$settings['summ-ai-key'] = sanitize_text_field( wp_unslash( $_POST['trp_machine_translation_settings']['summ-ai-key'] ) );
		}

		return $settings;
	}

	/**
	 * Return whether this object is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'translatepress-multilingual/index.php' ) || Helper::is_plugin_active( 'translatepress-business/index.php' );
	}
}
