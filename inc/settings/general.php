<?php
/**
 * File for general settings for this plugin.
 *
 * @package easy-language
 */

use easyLanguage\Apis;
use easyLanguage\Multilingual_Plugins;

/**
 * Page for general settings.
 *
 * @return void
 * @noinspection PhpUnused
 */
function easy_language_admin_add_menu_content_settings(): void {
	// check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	?>
	<form method="POST" action="<?php echo get_admin_url(); ?>options.php">
		<?php
		settings_fields( 'easyLanguageFields' );
		do_settings_sections( 'easyLanguagePage' );
		submit_button();
		?>
	</form>
	<?php
}
add_action('easy_language_settings_general_page', 'easy_language_admin_add_menu_content_settings' );

/**
 * Get general options.
 *
 * @return void
 * @noinspection PhpUnused
 */
function easy_language_admin_add_settings_general(): void {
	/**
	 * General Section
	 */
	add_settings_section(
		'settings_section_main',
		__( 'Choose API for translations', 'easy-language' ),
		'__return_true',
		'easyLanguagePage',
        array(
            'before_section' => '<div class="%s">',
            'after_section' => '</div>',
            'section_class' => 'easy-language-choose-api'
        )
	);

	// get list of supported APIs.
	$apis = array();
	foreach( Apis::get_instance()->get_available_apis() as $api_obj ) {
		$apis[$api_obj->get_name()] = $api_obj;
	}

	// get list of available plugins and check if they support APIs.
	$supports_api = false;
	foreach( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
		if( $plugin_obj->is_supporting_apis() ) {
			$supports_api = true;
		}
	}

	// API-Chooser.
	if (!empty($apis)) {
		add_settings_field(
			'easy_language_api',
			__('Choose API', 'easy-language'),
			'easy_language_admin_choose_api',
			'easyLanguagePage',
			'settings_section_main',
			array(
				'label_for' => 'easy_language_api',
				'fieldId' => 'easy_language_api',
				'description' => __('Please choose the API you want to use to translate your website.', 'easy-language'),
				'options' => $apis,
				'readonly' => !$supports_api,
				'disable_empty' => true
			)
		);
		register_setting( 'easyLanguageFields', 'easy_language_api' );
	}
}
add_action( 'easy_language_settings_add_settings', 'easy_language_admin_add_settings_general');

/**
 * Show selection of supported APIs.
 *
 * @param array $attr List of attributes for this field.
 *
 * @return void
 */
function easy_language_admin_choose_api( array $attr ): void {
	if( !empty($attr['options']) ) {

        // show description in front of list.
		if( !empty($attr['description']) ) {
			echo "<p>".wp_kses_post($attr['description'])."</p>";
		}

        // add wrapper for list.
		?><div class="easy-api-radios"><?php

        // loop through the options (available APIs).
		foreach( $attr['options'] as $key => $settings ) {
			// get checked-marker.
			$actual_value = get_option($attr['fieldId'], '');
			$checked = $actual_value === $key ? ' checked="checked"' : '';

			// readonly.
			$readonly = '';
			if( isset($attr['readonly']) && false !== $attr['readonly'] ) {
				$readonly = ' disabled="disabled"';
				if( !empty($checked) ) {
					?><input type="hidden" name="<?php echo esc_attr($attr['fieldId']); ?>_ro" value="<?php echo esc_attr($key); ?>"><?php
				}
			}

			// output.
			?>
            <div class="easy-api-radio">
                <input type="radio" id="<?php echo esc_attr($attr['fieldId'].$key); ?>" name="<?php echo esc_attr($attr['fieldId']); ?>" value="<?php echo esc_attr($key); ?>"<?php echo esc_attr($checked).esc_attr($readonly); ?>>
                <label for="<?php echo esc_attr($attr['fieldId'].$key); ?>" data-active-title="<?php echo esc_html__( 'Activated', 'easy-language' ); ?>">
                    <?php
                        // get api-logo, if it exists.
                        $logo_url = $settings->get_logo_url();
                        if( !empty($logo_url) ) {
                            ?><img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($settings->get_title()); ?>"><?php
                        }

                        // show api-title.
                        echo '<h2>'.esc_html($settings->get_title()).'</h2>';

                        // show api-description, if available.
                        if( !empty($settings->get_description()) ) {
                            echo wp_kses_post($settings->get_description());
                        }
                    ?>
                </label>
            </div>
			<?php
		}

        // end wrapper for list.
        ?></div><?php
	}
}
