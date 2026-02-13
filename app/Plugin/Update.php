<?php
/**
 * File for handling updates of this plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Apis\Summ_Ai\Summ_Ai;
use easyLanguage\Dependencies\easyTransientsForWordPress\Transients;
use easyLanguage\EasyLanguage\Rewrite;
use easyLanguage\EasyLanguage\Texts;

/**
 * Helper-function for updates of this plugin.
 */
class Update {
	/**
	 * Instance of this object.
	 *
	 * @var ?Update
	 */
	private static ?Update $instance = null;

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
	public static function get_instance(): Update {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the Updater.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'run' ) );
	}

	/**
	 * Run check for updates.
	 *
	 * @return void
	 */
	public function run(): void {
		// get installed plugin-version (version of the actual files in this plugin).
		$installed_plugin_version = EASY_LANGUAGE_VERSION;

		// get db-version (version which was last installed).
		$db_plugin_version = get_option( 'easyLanguageVersion', '1.0.0' );

		// compare version if we are not in development-mode.
		if (
			(
				(
					function_exists( 'wp_is_development_mode' ) && false === wp_is_development_mode( 'plugin' )
				)
				|| ! function_exists( 'wp_is_development_mode' )
			)
			&& version_compare( $installed_plugin_version, $db_plugin_version, '>' )
		) {
			if ( version_compare( $installed_plugin_version, '2.1.0', '>=' ) ) { // @phpstan-ignore if.alwaysFalse
				$this->version210();
			}

			if ( version_compare( $db_plugin_version, '2.1.0', '<' ) ) {
				$this->version210();
			}
			if ( version_compare( $db_plugin_version, '2.3.0', '<' ) ) {
				$this->version230();
			}
			if ( version_compare( $db_plugin_version, '2.4.0', '<' ) ) {
				$this->version240();
			}
			if ( version_compare( $db_plugin_version, '2.5.0', '<' ) ) {
				$this->version250();
			}
			if ( version_compare( $db_plugin_version, '2.9.0', '<' ) ) {
				$this->version290();
			}
			if ( version_compare( $db_plugin_version, '3.0.0', '<' ) ) {
				$this->version300();
			}
			if ( version_compare( $db_plugin_version, '3.0.2', '<' ) ) {
				$this->version302();
			}

			// log this event.
			/* translators: %1$s and %2$s are replaced by the old and new version. */
			Log::get_instance()->add_log( sprintf( __( 'Easy Language has been updated from %1$s to %2$s.', 'easy-language' ), $db_plugin_version, $installed_plugin_version ), 'success' );

			// save the new plugin-version in DB.
			delete_option( 'easyLanguageVersion' );
			add_option( 'easyLanguageVersion', $installed_plugin_version, '', true );
		}
	}

	/**
	 * Run on every update for 2.1.0 or newer.
	 *
	 * @return void
	 */
	private function version210(): void {
		// update schedule interval from 5minutely or minutely to 10minutely.
		if ( ! wp_next_scheduled( 'easy_language_automatic_simplification' ) ) {
			// add it.
			wp_schedule_event( time(), '10minutely', 'easy_language_automatic_simplification' );
		} else {
			$scheduled_event = wp_get_scheduled_event( 'easy_language_automatic_simplification' );
			if ( $scheduled_event && in_array( $scheduled_event->schedule, array( '5minutely', 'minutely' ), true ) ) {
				wp_clear_scheduled_hook( 'easy_language_automatic_simplification' );
				wp_schedule_event( time(), '10minutely', 'easy_language_automatic_simplification' );
			}
		}

		// set setup to complete if an API key is set or texts are simplified.
		$setup_obj = Setup::get_instance();
		if ( ! $setup_obj->is_completed() ) {
			$setup_completed = false;

			// check active API.
			$api_obj = Apis::get_instance()->get_active_api();
			if ( $api_obj ) {
				$token_field_name = $api_obj->get_token_field_name();
				if ( ! empty( $token_field_name ) && ! empty( get_option( $token_field_name ) ) ) {
					$setup_completed = true;
				}
			}

			// check if any texts are set.
			$texts = Texts::get_instance()->get_texts();
			if ( ! empty( $texts ) ) {
				$setup_completed = true;
			}

			// set setup to complete.
			if ( $setup_completed ) {
				$setup_obj = Setup::get_instance();
				$setup_obj->set_completed( $setup_obj->get_setup_name() );
			}
		}

		// remote now unused transients.
		Transients::get_instance()->delete_transient( Transients::get_instance()->get_transient_by_name( 'easy_language_intro_step_1' ) );
	}

	/**
	 * On update to version 2.3.0 or newer.
	 *
	 * @return void
	 */
	public function version230(): void {
		// get the actual value for setup and save it in the new field, if not already set.
		if ( ! get_option( 'esfw_completed' ) ) {
			update_option( 'esfw_completed', get_option( 'wp_easy_setup_completed' ) );
		}

		// remove our schedules.
		wp_unschedule_hook( 'easy_language_capito_quota_interval' );
		wp_unschedule_hook( 'easy_language_summ_ai_request_quota' );

		// re-initialize the active API for clean usage.
		$api_obj = Apis::get_instance()->get_active_api();
		if ( $api_obj instanceof Base ) {
			$api_obj->enable();
		}
	}

	/**
	 * On update to version 2.4.0 or newer.
	 *
	 * @return void
	 */
	public function version240(): void {
		// remove old separator setting.
		delete_option( 'easy_language_summ_ai_separator' );

		// set separator setting for each activated target language.
		if ( ! get_option( 'easy_language_summ_ai_target_languages_separator' ) ) {
			$target_languages = Summ_Ai::get_instance()->get_supported_target_languages();
			$separators       = array();
			foreach ( $target_languages as $target_language => $settings ) {
				$separators[ $target_language ] = $settings['separator'];
			}
			update_option( 'easy_language_summ_ai_target_languages_separator', $separators );
		}

		// set the new_lines setting for each activated target language.
		if ( ! get_option( 'easy_language_summ_ai_target_languages_new_lines' ) ) {
			$target_languages = Summ_Ai::get_instance()->get_supported_target_languages();
			$new_lines        = array();
			foreach ( $target_languages as $target_language => $settings ) {
				$new_lines[ $target_language ] = $settings['new_lines'] ? 1 : 0;
			}
			update_option( 'easy_language_summ_ai_target_languages_new_lines', $new_lines );
		}
	}

	/**
	 * On update to version 2.5.0 or newer.
	 *
	 * @return void
	 */
	public function version250(): void {
		// set embolden negative setting for each activated target language.
		if ( ! get_option( 'easy_language_summ_ai_target_languages_embolden_negative' ) ) {
			$target_languages   = Summ_Ai::get_instance()->get_supported_target_languages();
			$embolden_negatives = array();
			foreach ( $target_languages as $target_language => $settings ) {
				$embolden_negatives[ $target_language ] = $settings['embolden_negative'] ? 1 : 0;
			}
			update_option( 'easy_language_summ_ai_target_languages_embolden_negative', $embolden_negatives );
		}
	}

	/**
	 * On update to version 2.9.0 or newer.
	 *
	 * @return void
	 */
	public function version290(): void {
		// set the new option to "user".
		update_option( 'easy_language_capito_account_type', 'user' );
	}

	/**
	 * On update to version 3.0.0 or newer.
	 *
	 * @return void
	 */
	public function version300(): void {
		/**
		 * Migrate SUMM AI target language settings.
		 */
		$target_languages = get_option( 'easy_language_summ_ai_target_languages' );
		if ( is_array( $target_languages ) ) {
			foreach ( $target_languages as $target_language => $settings ) {
				update_option( 'easy_language_summ_ai_target_languages_' . $target_language, $settings );
			}
		}
		$target_languages_separator = get_option( 'easy_language_summ_ai_target_languages_separator' );
		if ( is_array( $target_languages_separator ) ) {
			foreach ( $target_languages_separator as $target_language => $settings ) {
				update_option( 'easy_language_summ_ai_target_languages_' . $target_language . '_separator', $settings );
			}
		}
		$target_languages_new_lines = get_option( 'easy_language_summ_ai_target_languages_new_lines' );
		if ( is_array( $target_languages_new_lines ) ) {
			foreach ( $target_languages_new_lines as $target_language => $settings ) {
				update_option( 'easy_language_summ_ai_target_languages_' . $target_language . '_new_line', $settings );
			}
		}
		$target_languages_embolden_negative = get_option( 'easy_language_summ_ai_target_languages_embolden_negative' );
		if ( is_array( $target_languages_embolden_negative ) ) {
			foreach ( $target_languages_embolden_negative as $target_language => $settings ) {
				update_option( 'easy_language_summ_ai_target_languages_' . $target_language . '_embolden_negative', $settings );
			}
		}

		// delete the old settings.
		delete_option( 'easy_language_summ_ai_target_languages' );
		delete_option( 'easy_language_summ_ai_target_languages_separator' );
		delete_option( 'easy_language_summ_ai_target_languages_new_lines' );
		delete_option( 'easy_language_summ_ai_target_languages_embolden_negative' );

		// migrate the interval settings for our schedules.
		update_option( 'easy_language_capito_quota_interval', 'easy_language_' . get_option( 'easy_language_capito_quota_interval' ) );
		update_option( 'easy_language_summ_ai_quota_interval', 'easy_language_' . get_option( 'easy_language_summ_ai_quota_interval' ) );
		update_option( 'easy_language_automatic_simplification', 'easy_language_' . get_option( 'easy_language_automatic_simplification' ) );
	}

	/**
	 * On update to version 3.0.2 or newer.
	 *
	 * @return void
	 */
	public function version302(): void {
		Rewrite::get_instance()->set_refresh();
	}
}
