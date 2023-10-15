<?php
/**
 * File for api settings for this plugin.
 *
 * @package easy-language
 */

use easyLanguage\Apis;
use easyLanguage\Multilingual_plugins\Easy_Language\Init;
use easyLanguage\Transients;

/**
 * Page for API settings.
 *
 * @return void
 * @noinspection PhpUnused
 */
function easy_language_admin_add_menu_content_settings(): void {
	// check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// output.
	?>
	<form method="POST" action="<?php echo esc_url( get_admin_url() ); ?>options.php">
		<?php
		settings_fields( 'easyLanguageApiFields' );
		do_settings_sections( 'easyLanguageApiPage' );
		submit_button();
		?>
	</form>
	<?php
}
add_action( 'easy_language_settings_api_page', 'easy_language_admin_add_menu_content_settings' );

/**
 * Get general options.
 *
 * @return void
 * @noinspection PhpUnused
 */
function easy_language_admin_add_settings_api(): void {
	/**
	 * General Section
	 */
	add_settings_section(
		'settings_section_main',
		__( 'Choose API for simplifications of your website-texts', 'easy-language' ),
		'__return_true',
		'easyLanguageApiPage',
		array(
			'before_section' => '<div class="%s">',
			'after_section'  => '</div>',
			'section_class'  => 'easy-language-choose-api',
		)
	);

	// get list of supported APIs.
	$apis = array();
	foreach ( Apis::get_instance()->get_available_apis() as $api_obj ) {
		$apis[ $api_obj->get_name() ] = $api_obj;
	}

	// API-Chooser.
	if ( ! empty( $apis ) ) {
		add_settings_field(
			'easy_language_api',
			__( 'Choose API', 'easy-language' ),
			'easy_language_admin_choose_api',
			'easyLanguageApiPage',
			'settings_section_main',
			array(
				'label_for'     => 'easy_language_api',
				'fieldId'       => 'easy_language_api',
				'description'   => __( 'Please choose the API you want to use to simplify texts your website.', 'easy-language' ),
				'options'       => $apis,
				'disable_empty' => true,
			)
		);
		register_setting( 'easyLanguageApiFields', 'easy_language_api', array( 'sanitize_callback' => 'easy_language_admin_validate_chosen_api' ) );
	}
}
add_action( 'easy_language_settings_add_settings', 'easy_language_admin_add_settings_api' );

/**
 * Show selection of supported APIs.
 *
 * @param array $attr List of attributes for this field.
 *
 * @return void
 */
function easy_language_admin_choose_api( array $attr ): void {
	if ( ! empty( $attr['options'] ) ) {

		// show description in front of list.
		if ( ! empty( $attr['description'] ) ) {
			echo '<p>' . wp_kses_post( $attr['description'] ) . '</p>';
		}

		// add wrapper for list.
		?>
		<div class="easy-api-radios">
		<?php

		// loop through the options (available APIs).
		foreach ( $attr['options'] as $key => $settings ) {
			// get checked-marker.
			$actual_value = get_option( $attr['fieldId'], '' );
			$checked      = $actual_value === $key ? ' checked="checked"' : '';

			// set marker for Pro-extension of this API.
			$css_class = '';
			$pro_hint = '';
			if( $settings->is_extended_in_pro() ) {
				$css_class .= ' easy-language-api-pro-hint';
				$pro_hint = $settings->get_pro_hint();
			}
			if( !empty($checked) ) {
				$css_class .= ' easy-language-api-active';
			}

			// output.
			?>
			<div class="easy-api-radio<?php echo esc_attr($css_class); ?>">
				<input type="radio" id="<?php echo esc_attr( $attr['fieldId'] . $key ); ?>" name="<?php echo esc_attr( $attr['fieldId'] ); ?>" value="<?php echo esc_attr( $key ); ?>"<?php echo esc_attr( $checked ); ?>>
				<label for="<?php echo esc_attr( $attr['fieldId'] . $key ); ?>" data-active-title="<?php echo esc_html__( 'Activated', 'easy-language' ); ?>" data-choose-title="<?php echo esc_html__( 'Chosen', 'easy-language' ); ?>">
			<?php
				// get api-logo, if it exists.
				$logo_url = $settings->get_logo_url();
				if ( ! empty( $logo_url ) ) {
					?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $settings->get_title() ); ?>">
					<?php
				}

				// show api-title.
				echo '<h2>' . esc_html( $settings->get_title() ) . '</h2>';

				// show api-description, if available.
				if ( ! empty( $settings->get_description() ) ) {
					echo wp_kses_post( $settings->get_description() );
				}

				if( !empty($pro_hint) ) {
					?><span class="pro-hint"><?php echo wp_kses_post($pro_hint); ?></span><?php
				}
				?>
				</label>
			</div>
			<?php
		}

		// end wrapper for list.
		?>
		</div>
		<?php
	}
}

/**
 * Validate the chosen API.
 *
 * @param string $value
 *
 * @return string
 * @noinspection PhpUnused
 */
function easy_language_admin_validate_chosen_api( string $value ): string {
	// get the new post-state for objects of the former API.
	$post_state = get_option('easy_language_state_on_api_change', 'draft' );

	// get the actual API.
	$api = Apis::get_instance()->get_api_by_name( get_option('easy_language_api', '' ) );

	// if the actual API is not the new API and changing post-state is not disabled, go further.
	if( $api && $value !== $api->get_name() && 'disabled' !== $post_state ) {
		// get the translated objects of the former API (all of them).
		$post_type_objects = $api->get_translated_post_type_objects();

		// loop through the object and change their state.
		foreach( $post_type_objects as $post_type_object_id ) {
			// save the previous state.
			update_post_meta( $post_type_object_id, 'easy_language_simplification_state_changed_from', get_post_status($post_type_object_id) );

			// update object.
			$array = array(
				'ID' => $post_type_object_id,
				'post_status' => $post_state
			);
			wp_update_post($array);
		}
	}

	// get the new API.
	$new_api = Apis::get_instance()->get_api_by_name( $value );

	// Remove intro-hint if it is enabled.
	if( 1 === absint(get_option( 'easy_language_intro_step_2', 0 ) ) ) {
		delete_option( 'easy_language_intro_step_2' );
	}

	// Check if API has been saved first time and the new API is already configured (or no configuration at all),
	// to show intro part 2.
	if( !get_option( 'easy_language_intro_step_2') && ( false !== $new_api->is_configured() || false === $new_api->has_settings() ) ) {
		update_option( 'easy_language_intro_step_2', 1 );
	}

	// if the new API is valid and setting has been changed.
	if( $new_api && $api && $api->get_name() !== $new_api->get_name() ) {
		// get the simplified objects of the new API (all of them).
		$post_type_objects = $new_api->get_translated_post_type_objects();

		// loop through the object and change their to its previous state.
		foreach( $post_type_objects as $post_type_object_id ) {
			// get the previous state.
			$new_post_state = get_post_meta( $post_type_object_id, 'easy_language_simplification_state_changed_from', true );

			// update object.
			$array = array(
				'ID' => $post_type_object_id,
				'post_status' => $new_post_state
			);
			wp_update_post($array);

			// delete the setting for previous state.
			delete_post_meta( $post_type_object_id, 'easy_language_simplification_state_changed_from' );
		}

		// Enable hint if user has not configured the new API yet and if this API has no translation-objects.
		if( empty($post_type_objects) && false === $new_api->is_configured() ) {
			$links              = '';
			$post_type_settings = \easyLanguage\Init::get_instance()->get_post_type_settings();
			$post_types         = Init::get_instance()->get_supported_post_types();
			$post_types_count   = count( $post_types );
			$post_types_counter = 0;
			foreach ( $post_types as $post_type => $enabled ) {
				if ( $post_types_counter === ( $post_types_count - 1 ) ) {
					$links .= ' ' . esc_html__( 'and', 'easy-language' ) . ' ';
				}
				$links .= '<a href="' . esc_url( $post_type_settings[ $post_type ]['admin_edit_url'] ) . '">' . $post_type_settings[ $post_type ]['label_plural'] . '</a>';
				++ $post_types_counter;
			}
			$transient_obj = Transients::get_instance()->add();
			$transient_obj->set_dismissible_days( 90 );
			$transient_obj->set_name( 'easy_language_api_changed' );
			if ( $transient_obj->is_set() ) {
				$transient_obj->delete();
			}
			if ( $new_api->has_settings() ) {
				/* translators: %1$s will be replaced by the name of the active API, %2%s will be replaced by the settings-URL for this API, %3$s will be replaced by list of post-types and their links in wp-admin. */
				$transient_obj->set_message( sprintf( __( '<strong>You have activated %1$s as API to simplify your texts.</strong> Please check now the <a href="%2$s">API-settings</a> before you could use %1$s.', 'easy-language' ), esc_html( $new_api->get_title() ), esc_url( $new_api->get_settings_url() ) ) );
			} else {
				/* translators: %1$s will be replaced by the name of the active API, %2$s will be replaced by list of post-types and their links in wp-admin. */
				$transient_obj->set_message( sprintf( __( '<strong>You have activated %1$s as API to simplify your texts.</strong> Go now to your %2$s and simplify them with %1$s.', 'easy-language' ), esc_html( $new_api->get_title() ), wp_kses_post( $links ) ) );
			}
			$transient_obj->set_type( 'hint' );
			$transient_obj->save();
		}
		else {
			// delete api-settings hint.
			Transients::get_instance()->get_transient_by_name( 'easy_language_api_changed' )->delete();
		}
	}

	// return chosen api-name.
	return $value;
}
