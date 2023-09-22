<?php
/**
 * File for hooks regarding the translatePress-plugin.
 *
 * @package easy-language
 */

use easyLanguage\helper;
use easyLanguage\Multilingual_plugins\TranslatePress\Init;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register the TranslatePress service in this plugin.
 *
 * @param array $plugin_list List of available multilingual-plugins.
 * @return array
 */
function easy_language_register_plugin_translate_press( array $plugin_list ): array {
	// bail if plugin is not active.
	if( false === Helper::is_plugin_active( 'translatepress-multilingual/index.php' ) ) {
		return $plugin_list;
	}

	// get plugin-object and add it to list.
	$plugin_list[] = Init::get_instance();

	// return resulting list.
	return $plugin_list;
}
add_filter( 'easy_language_register_plugin', 'easy_language_register_plugin_translate_press');

/**
 * Add Leichte Sprache to list of all languages from WP.
 *
 * @param array $list List of supported languages.
 * @return array
 */
function easy_language_trp_add_to_wp_list( array $list ): array {
	$list['ls_ls'] = array(
		'language'     => 'ls_ls',
		'english_name' => 'Leichte Sprache',
		'native_name'  => 'Leichte Sprache',
		'iso'          => array(
			'ls_ls',
		),
	);
	return $list;
}
add_filter( 'trp_wp_languages', 'easy_language_trp_add_to_wp_list', 10, 1 );

/**
 * Add our automatic machine as functions.
 *
 * @param array $list List of supported languages.
 * @return array
 */
function easy_language_trp_add_automatic_machine( array $list ): array {
	$list['summ-ai'] = 'easyLanguage\Multilingual_plugins\TranslatePress\Translatepress_Summ_Ai_Machine_Translator';
	return $list;
}
add_filter( 'trp_automatic_translation_engines_classes', 'easy_language_trp_add_automatic_machine', 10, 1 );

/**
 * Add the automatic machine to the list in translatePress-backend.
 *
 * @param array $engines List of supported translate engines.
 * @return mixed
 */
function easy_language_trp_add_automatic_engine( array $engines ): array {
	$engines[] = array(
		'value' => 'summ-ai',
		'label' => __( 'SUMM AI', 'easy-language' ),
	);
	return $engines;
}
add_filter( 'trp_machine_translation_engines', 'easy_language_trp_add_automatic_engine', 30 );

/**
 * Add our individual settings.
 *
 * @param array $mt_settings List of settings.
 * @return void
 */
function easy_language_trp_settings( array $mt_settings ): void {
	$trp                = TRP_Translate_Press::get_trp_instance();
	$machine_translator = $trp->get_component( 'machine_translator' );

	$translation_engine = $mt_settings['translation-engine'] ?? '';

	// Check for API errors only if $translation_engine is summ-ai.
	if ( 'summ-ai' === $translation_engine ) {
		$api_check = $machine_translator->check_api_key_validity();
	}

	// Check for errors.
	$error_message = '';
	$show_errors   = false;
	if ( isset( $api_check ) && true === $api_check['error'] ) {
		$error_message = $api_check['message'];
		$show_errors   = true;
	}

	$text_input_classes = array(
		'trp-text-input',
	);
	if ( $show_errors && 'summ-ai' === $translation_engine ) {
		$text_input_classes[] = 'trp-text-input-error';
	}

	?>

	<tr>
		<th scope="row"><label for="trp-summ-ai-key"><?php esc_html_e( 'SUMM AI API Key', 'easy-language' ); ?></label></th>
		<td>
			<?php
			// Display an error message above the input.
			if ( $show_errors && 'summ-ai' === $translation_engine ) {
				?>
				<p class="trp-error-inline">
					<?php echo wp_kses_post( $error_message ); ?>
				</p>
				<?php
			}

			// get the key as value.
			$value = '';
			if ( ! empty( $mt_settings['summ-ai-key'] ) ) {
				$value = $mt_settings['summ-ai-key'];
			}

			?>
			<input type="text" id="trp-summ-ai-key" class="<?php echo esc_html( implode( ' ', $text_input_classes ) ); ?>" name="trp_machine_translation_settings[summ-ai-key]" value="<?php echo esc_attr( $value ); ?>"/>
			<?php
			// Only show errors if summ-ai translate is active.
			if ( 'summ-ai' === $translation_engine && function_exists( 'trp_output_svg' ) ) {
				$machine_translator->automatic_translation_svg_output( $show_errors );
			}
			?>
			<p class="description">
				<?php
					$url = 'https://summ-ai.com/';
					/* translators: %1$s is replaced with the SUMM AI URL */
					echo wp_kses_post( sprintf( __( 'You can get the SUMM AI API Key from the provider <a href="%1$s">here</a>.', 'easy-language' ), $url ) );
				?>
			</p>
		</td>

	</tr>

	<?php
}
add_action( 'trp_machine_translation_extra_settings_middle', 'easy_language_trp_settings', 40, 1 );

/**
 * Check our individual settings.
 *
 * @param array $settings List of settings.
 * @return array
 */
function easy_language_trp_sanitize_settings( array $settings ): array {
	if ( ! empty( $_POST['trp_machine_translation_settings']['summ-ai-key'] ) ) {
		$settings['summ-ai-key'] = sanitize_text_field( wp_unslash( $_POST['trp_machine_translation_settings']['summ-ai-key'] ) );
	}

	return $settings;
}
add_filter( 'trp_machine_translation_sanitize_settings', 'easy_language_trp_sanitize_settings', 10, 1 );

/**
 * Truncate any translations for Leichte Sprache.
 *
 * @return void
 */
function easy_language_trp_reset_translations(): void {
	global $wpdb;
	$trp       = TRP_Translate_Press::get_trp_instance();
	$trp_query = $trp->get_component( 'query' );
	$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %s', $trp_query->get_table_name( 'ls_ls' ) ) );
}

/**
 * Check for supported languages.
 *
 * @param bool  $all_are_available Whether all languages are available.
 * @param array $languages List of languages.
 * @param array $settings List of settings.
 * @return bool
 */
function easy_language_trp_get_supported_languages( bool $all_are_available, array $languages, array $settings ): bool {
	if ( 'summ-ai' === $settings['trp_machine_translation_settings']['translation-engine'] ) {
		if ( in_array( 'ls_ls', $languages, true ) ) {
			return true;
		}
	}
	return $all_are_available;
}
add_filter( 'trp_mt_available_supported_languages', 'easy_language_trp_get_supported_languages', 10, 3 );

/**
 * Add settings for our individual language for language-switcher in frontend.
 *
 * @param array  $current_language The current language.
 * @param array  $published_languages The list of published languages.
 * @param string $trp_language The translatePress-language.
 * @return array
 */
function easy_language_trp_set_current_language_fields( array $current_language, array $published_languages, string $trp_language ): array {
	if ( 'ls_ls' === $trp_language ) {
		$current_language = array(
			'name' => __( 'Leichte Sprache', 'easy-language' ),
			'code' => 'ls_ls',
		);
	}
	return $current_language;
}
add_filter( 'trp_ls_floating_current_language', 'easy_language_trp_set_current_language_fields', 10, 3 );

/**
 * Change path for our own language-flag.
 *
 * TODO individuelle Grafik vom Admin hinterlegbar machen
 *
 * @param string $flags_path Path to the flags.
 * @param string $language_code Checked language-code.
 * @return string
 */
function easy_language_set_flag( string $flags_path, string $language_code ): string {
	if ( 'ls_ls' === $language_code ) {
		return helper::get_plugin_url() . 'gfx/';
	}
	return $flags_path;
}
add_filter( 'trp_flags_path', 'easy_language_set_flag', 10, 2 );
