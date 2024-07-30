<?php
/**
 * File for settings functions of this plugin.
 *
 * @package easy-language
 */

use easyLanguage\Helper;
use easyLanguage\Setup;

/**
 * Add settings in options menu.
 *
 * @return void
 */
function easy_language_admin_add_settings_menu(): void {
	// bail without completed setup.
	if ( ! Setup::get_instance()->is_completed() ) {
		return;
	}

	// add our settings-page in menu.
	add_options_page(
		__( 'Settings', 'easy-language' ),
		__( 'Easy Language Settings', 'easy-language' ),
		'manage_options',
		'easy_language_settings',
		'easy_language_admin_add_settings_content',
		10
	);
}
add_action( 'admin_menu', 'easy_language_admin_add_settings_menu' );

/**
 * Wrapper for each settings page of this plugin.
 *
 * @return void
 */
function easy_language_admin_add_settings_content(): void {
	// check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// get the active tab from the $_GET param.
	$tab = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	if ( is_null( $tab ) ) {
		$tab = 'api';
	}

	// output.
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Easy Language Settings', 'easy-language' ); ?></h1>
		<nav class="nav-tab-wrapper">
			<a href="<?php echo esc_url( Helper::get_settings_page_url() ); ?>" class="nav-tab
								<?php
								if ( 'api' === $tab ) :
									?>
				nav-tab-active<?php endif; ?>"><?php esc_html_e( 'API', 'easy-language' ); ?></a>
			<?php
				do_action( 'easy_language_settings_add_tab', $tab );
			?>
		</nav>

		<div class="tab-content">
			<?php
			// get the content of the actual tab.
			do_action( 'easy_language_settings_' . $tab . '_page' );
			?>
		</div>
	</div>
	<?php
}

/**
 * Add settings for admin-page via custom hook.
 * And add filter for each settings-field of our own plugin, if configured.
 *
 * @return void
 * @noinspection PhpUnused
 */
function easy_language_admin_add_settings(): void {
	// add settings.
	do_action( 'easy_language_settings_add_settings' );

	// get settings-fields.
	global $wp_settings_fields;

	if ( ! empty( $wp_settings_fields ) ) {
		// loop through the fields.
		foreach ( $wp_settings_fields as $name => $sections ) {
			// filter for our own settings.
			if ( str_contains( $name, 'easy_language' ) ) {
				// loop through the sections of this setting.
				foreach ( $sections as $section ) {
					// loop through the field of this section.
					foreach ( $section as $field ) {
						$function_name = 'easy_language_admin_sanitize_settings_field';
						if ( ! empty( $field['args']['sanitizeFunction'] ) && function_exists( $field['args']['sanitizeFunction'] ) ) {
							$function_name = $field['args']['sanitizeFunction'];
						}
						add_filter( 'sanitize_option_' . $field['args']['fieldId'], $function_name, 10, 2 );
					}
				}
			}
		}
	}
}
add_action( 'admin_init', 'easy_language_admin_add_settings' );

/**
 * Define an input-text-field.
 *
 * @param array $attr List of attributes.
 *
 * @return void
 * @noinspection DuplicatedCode
 */
function easy_language_admin_text_field( array $attr ): void {
	if ( ! empty( $attr['fieldId'] ) ) {
		// get value from config.
		$value = get_option( $attr['fieldId'], '' );

		// get value from request.
		$post_value = filter_input( INPUT_POST, $attr['fieldId'], FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_null( $post_value ) ) {
			$value = $post_value;
		}

		// get title.
		$title = '';
		if ( isset( $attr['title'] ) ) {
			$title = $attr['title'];
		}

		// set readonly attribute.
		$readonly = '';
		if ( isset( $attr['readonly'] ) && false !== $attr['readonly'] ) {
			$readonly = ' disabled="disabled"';
			?>
			<input type="hidden" name="<?php echo esc_attr( $attr['fieldId'] ); ?>_ro" value="<?php echo esc_attr( $value ); ?>">
			<?php
		}

		// mark as highlighted if set.
		if ( isset( $attr['highlight'] ) && false !== $attr['highlight'] ) {
			?>
			<div class="highlight">
			<?php
		}

		// output.
		?>
		<input type="text" id="<?php echo esc_attr( $attr['fieldId'] ); ?>" name="<?php echo esc_attr( $attr['fieldId'] ); ?>" value="<?php echo esc_attr( $value ); ?>"
											<?php
											echo ! empty( $attr['placeholder'] ) ? ' placeholder="' . esc_attr( $attr['placeholder'] ) . '"' : '';
											echo esc_attr( $readonly );
											?>
		class="widefat" title="<?php echo esc_attr( $title ); ?>">
		<?php
		if ( ! empty( $attr['description'] ) ) {
			echo '<p>' . wp_kses_post( $attr['description'] ) . '</p>';
		}

		// end mark as highlighted if set.
		if ( isset( $attr['highlight'] ) && false !== $attr['highlight'] ) {
			?>
			</div>
			<?php
		}
	}
}

/**
 * Define an input-number-field.
 *
 * @param array $attr List of attributes.
 *
 * @return void
 * @noinspection DuplicatedCode
 */
function easy_language_admin_number_field( array $attr ): void {
	if ( ! empty( $attr['fieldId'] ) ) {
		// get value from config.
		$value = get_option( $attr['fieldId'], '' );

		// get value from request.
		$post_value = filter_input( INPUT_POST, $attr['fieldId'], FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_null( $post_value ) ) {
			$value = $post_value;
		}

		// get title.
		$title = '';
		if ( isset( $attr['title'] ) ) {
			$title = $attr['title'];
		}

		// set readonly attribute.
		$readonly = '';
		if ( isset( $attr['readonly'] ) && false !== $attr['readonly'] ) {
			$readonly = ' disabled="disabled"';
			?>
			<input type="hidden" name="<?php echo esc_attr( $attr['fieldId'] ); ?>_ro" value="<?php echo esc_attr( $value ); ?>">
			<?php
		}

		// output.
		?>
		<input type="number" id="<?php echo esc_attr( $attr['fieldId'] ); ?>" name="<?php echo esc_attr( $attr['fieldId'] ); ?>" value="<?php echo esc_attr( $value ); ?>"
											<?php
											echo ! empty( $attr['placeholder'] ) ? ' placeholder="' . esc_attr( $attr['placeholder'] ) . '"' : '';
											echo esc_attr( $readonly );
											?>
		class="widefat" title="<?php echo esc_attr( $title ); ?>">
		<?php
		if ( ! empty( $attr['description'] ) ) {
			echo '<p>' . wp_kses_post( $attr['description'] ) . '</p>';
		}
	}
}

/**
 * Define an input-email-field.
 *
 * @param array $attr List of attributes.
 *
 * @return void
 */
function easy_language_admin_email_field( array $attr ): void {
	if ( ! empty( $attr['fieldId'] ) ) {
		// get value from config.
		$value = get_option( $attr['fieldId'], '' );

		// get value from request.
		$post_value = filter_input( INPUT_POST, $attr['fieldId'], FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_null( $post_value ) ) {
			$value = $post_value;
		}

		// get title.
		$title = '';
		if ( isset( $attr['title'] ) ) {
			$title = $attr['title'];
		}

		// set readonly attribute.
		$readonly = '';
		if ( isset( $attr['readonly'] ) && false !== $attr['readonly'] ) {
			$readonly = ' disabled="disabled"';
			?>
			<input type="hidden" name="<?php echo esc_attr( $attr['fieldId'] ); ?>_ro" value="<?php echo esc_attr( $value ); ?>">
			<?php
		}

		// mark as highlighted if set.
		if ( isset( $attr['highlight'] ) && false !== $attr['highlight'] ) {
			?>
			<div class="highlight">
			<?php
		}

		// output.
		?>
		<input type="email" id="<?php echo esc_attr( $attr['fieldId'] ); ?>" name="<?php echo esc_attr( $attr['fieldId'] ); ?>" value="<?php echo esc_attr( $value ); ?>"
											<?php
											echo ! empty( $attr['placeholder'] ) ? ' placeholder="' . esc_attr( $attr['placeholder'] ) . '"' : '';
											echo esc_attr( $readonly );
											?>
		class="widefat" title="<?php echo esc_attr( $title ); ?>">
		<?php
		if ( ! empty( $attr['description'] ) ) {
			echo '<p>' . wp_kses_post( $attr['description'] ) . '</p>';
		}

		// end mark as highlighted if set.
		if ( isset( $attr['highlight'] ) && false !== $attr['highlight'] ) {
			?>
			</div>
			<?php
		}
	}
}

/**
 * Define an input-checkbox-field.
 *
 * @param array $attr List of attributes.
 * @return void
 */
function easy_language_admin_checkbox_field( array $attr ): void {
	if ( ! empty( $attr['fieldId'] ) ) {
		// get title.
		$title = '';
		if ( isset( $attr['title'] ) ) {
			$title = $attr['title'];
		}

		// get value from request.
		$post_value = filter_input( INPUT_POST, $attr['fieldId'], FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// set readonly attribute.
		$readonly = '';
		if ( isset( $attr['readonly'] ) && false !== $attr['readonly'] ) {
			$readonly = ' disabled="disabled"';
			?>
			<input type="hidden" name="<?php echo esc_attr( $attr['fieldId'] ); ?>_ro" value="<?php echo ( 1 === absint( get_option( $attr['fieldId'], 0 ) ) || ( ! is_null( $post_value ) && 1 === absint( $post_value ) ) ) ? '1' : '0'; ?>">
			<?php
		}

		?>
		<input type="checkbox" id="<?php echo esc_attr( $attr['fieldId'] ); ?>"
				name="<?php echo esc_attr( $attr['fieldId'] ); ?>"
				value="1"
			<?php
			echo ( 1 === absint( get_option( $attr['fieldId'], 0 ) ) || ( ! is_null( $post_value ) && 1 === absint( $post_value ) ) ) ? ' checked="checked"' : '';
			echo esc_attr( $readonly );
			?>
				class="easy-language-field-width"
				title="<?php echo esc_attr( $title ); ?>"
		>
		<?php

		// show optional description for this checkbox.
		if ( ! empty( $attr['description'] ) ) {
			echo '<p>' . wp_kses_post( $attr['description'] ) . '</p>';
		}

		// show optional hint for our Pro-version.
		if ( ! empty( $attr['pro_hint'] ) ) {
			do_action( 'easy_language_admin_show_pro_hint', $attr['pro_hint'] );
		}
	}
}

/**
 * Show select-field with given values.
 *
 * @param array $attr List of attributes.
 *
 * @return void
 */
function easy_language_admin_select_field( array $attr ): void {
	if ( ! empty( $attr['fieldId'] ) && ! empty( $attr['values'] ) ) {
		// get value from config.
		$value = get_option( $attr['fieldId'], '' );

		// get value from request.
		$post_value = filter_input( INPUT_POST, $attr['fieldId'], FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_null( $post_value ) ) {
			$value = $post_value;
		}

		// get title.
		$title = '';
		if ( isset( $attr['title'] ) ) {
			$title = $attr['title'];
		}

		// set readonly attribute.
		$readonly = '';
		if ( isset( $attr['readonly'] ) && false !== $attr['readonly'] ) {
			$readonly = ' disabled="disabled"';
			?>
			<input type="hidden" name="<?php echo esc_attr( $attr['fieldId'] ); ?>_ro" value="<?php echo esc_attr( $value ); ?>" />
			<?php
		}

		?>
		<select id="<?php echo esc_attr( $attr['fieldId'] ); ?>" name="<?php echo esc_attr( $attr['fieldId'] ); ?>" class="easy-language-field-width"<?php echo esc_attr( $readonly ); ?> title="<?php echo esc_attr( $title ); ?>">
			<?php
			if ( empty( $attr['disable_empty'] ) ) {
				?>
					<option value=""></option>
				<?php
			}
			foreach ( $attr['values'] as $key => $label ) {
				?>
					<option value="<?php echo esc_attr( $key ); ?>"<?php echo ( $value === $key ? ' selected="selected"' : '' ); ?>><?php echo esc_html( $label ); ?></option>
					<?php
			}
			?>
		</select>
		<?php
		if ( ! empty( $attr['description'] ) ) {
			echo '<p>' . wp_kses_post( $attr['description'] ) . '</p>';
		}
	} elseif ( empty( $attr['values'] ) && ! empty( $attr['noValues'] ) ) {
		echo '<p>' . esc_html( $attr['noValues'] ) . '</p>';
	}
}

/**
 * Show multiple checkboxes for a single setting.
 *
 * @param array $attr List of attributes.
 *
 * @return void
 */
function easy_language_admin_multiple_checkboxes_field( array $attr ): void {
	if ( ! empty( $attr['options'] ) ) {
		if ( ! empty( $attr['description'] ) ) {
			echo '<p class="easy-language-checkbox">' . wp_kses_post( $attr['description'] ) . '</p>';
		}

		foreach ( $attr['options'] as $key => $settings ) {
			// get checked-marker.
			$actual_values = get_option( $attr['fieldId'], array() );
			$checked       = ! empty( $actual_values[ $key ] ) ? ' checked' : '';

			// title.
			$title = __( 'Check to enable this language.', 'easy-language' );

			// readonly.
			$readonly = '';
			if ( isset( $attr['readonly'] ) && false !== $attr['readonly'] ) {
				$readonly = ' disabled="disabled"';
				?>
				<input type="hidden" name="<?php echo esc_attr( $attr['fieldId'] ); ?>_ro[<?php echo esc_attr( $key ); ?>]" value="<?php echo ! empty( $checked ) ? 1 : 0; ?>">
				<?php
			}
			if ( isset( $settings['enabled'] ) && false === $settings['enabled'] ) {
				$readonly = ' disabled="disabled"';
				$title    = '';
			}

			// get icon, if set.
			$icon = '';
			if ( ! empty( $settings['img_icon'] ) ) {
				$icon = $settings['img_icon'];
			}

			// output.
			?>
			<div class="easy-language-checkbox">
				<input type="checkbox" id="<?php echo esc_attr( $attr['fieldId'] . $key ); ?>" name="<?php echo esc_attr( $attr['fieldId'] ); ?>[<?php echo esc_attr( $key ); ?>]" value="1"<?php echo esc_attr( $checked ) . esc_attr( $readonly ); ?> title="<?php echo esc_attr( $title ); ?>">
				<label for="<?php echo esc_attr( $attr['fieldId'] . $key ); ?>"><?php echo esc_html( $settings['label'] ) . wp_kses_post( $icon ); ?></label>
				<?php
				if ( ! empty( $settings['description'] ) ) {
					echo '<p>' . wp_kses_post( $settings['description'] ) . '</p>';
				}
				?>
			</div>
			<?php
		}
		if ( ! empty( $attr['pro_hint'] ) ) {
			do_action( 'easy_language_admin_show_pro_hint', $attr['pro_hint'] );
		}
	}
}

/**
 * Show multiple radio-fields for a single setting.
 *
 * @param array $attr List of attributes for this field-list.
 *
 * @return void
 * @noinspection PhpUnused
 * @noinspection PhpConditionAlreadyCheckedInspection
 */
function easy_language_admin_multiple_radio_field( array $attr ): void {
	if ( ! empty( $attr['options'] ) ) {
		if ( ! empty( $attr['description_above'] ) && false !== $attr['description_above'] ) {
			if ( ! empty( $attr['description'] ) ) {
				echo '<p>' . wp_kses_post( $attr['description'] ) . '</p>';
			}
		}

		foreach ( $attr['options'] as $key => $settings ) {
			// get checked-marker.
			$actual_value = get_option( $attr['fieldId'], '' );
			$checked      = $actual_value === $key ? ' checked' : '';

			// title.
			$title = __( 'Check to enable this language.', 'easy-language' );

			// readonly.
			$readonly = '';
			if ( isset( $attr['readonly'] ) && false !== $attr['readonly'] ) {
				$readonly = ' disabled="disabled"';
				if ( ! empty( $checked ) ) {
					?>
					<input type="hidden" name="<?php echo esc_attr( $attr['fieldId'] ); ?>_ro" value="<?php echo esc_attr( $key ); ?>">
					<?php
				}
			}
			if ( false === $settings['enabled'] ) {
				$readonly = ' disabled="disabled"';
				$title    = '';
			}

			// output.
			?>
			<div class="easy-language-radio">
				<input type="radio" id="<?php echo esc_attr( $attr['fieldId'] . $key ); ?>" name="<?php echo esc_attr( $attr['fieldId'] ); ?>" value="<?php echo esc_attr( $key ); ?>"<?php echo esc_attr( $checked ) . esc_attr( $readonly ); ?> title="<?php echo esc_attr( $title ); ?>" autocomplete="off">
				<label for="<?php echo esc_attr( $attr['fieldId'] . $key ); ?>"><?php echo esc_html( $settings['label'] ); ?></label>
				<?php
				if ( ! empty( $settings['description'] ) ) {
					echo '<p>' . wp_kses_post( $settings['description'] ) . '</p>';
				}
				if ( ! empty( $settings['pro_hint'] ) ) {
					do_action( 'easy_language_admin_show_pro_hint', $settings['pro_hint'] );
				}
				?>
			</div>
			<?php
		}

		if ( empty( $attr['description_above'] ) && ! empty( $attr['description'] ) ) {
			echo '<p>' . wp_kses_post( $attr['description'] ) . '</p>';
		}
	}
}

/**
 * Add pro hint via settings-field for better position in list.
 *
 * @return void
 */
function easy_language_admin_advanced_pro_hint(): void {
	// pro hint.
	/* translators: %1$s is replaced with the plugin name */
	do_action( 'easy_language_admin_show_pro_hint', __( 'With %1$s you get more settings options, e.g. support for any post-type and simplify of taxonomies.', 'easy-language' ) );
}

/**
 * Show hint for our Pro-version.
 *
 * @param string $hint The hint.
 * @return void
 */
function easy_language_admin_show_pro_hint( string $hint ): void {
	echo '<p class="easy-language-pro-hint">' . sprintf( wp_kses_post( $hint ), '<a href="' . esc_url( Helper::get_pro_url() ) . '" target="_blank">Easy Language Pro (opens new window)</a>' ) . '</p>';
}
add_action( 'easy_language_admin_show_pro_hint', 'easy_language_admin_show_pro_hint' );

/**
 * Show multiple input-text fields for a single setting.
 *
 * @param array $attr List of attributes.
 *
 * @return void
 */
function easy_language_admin_multiple_text_field( array $attr ): void {
	if ( ! empty( $attr['options'] ) ) {
		if ( ! empty( $attr['description'] ) ) {
			echo '<p class="easy-language-input-text">' . wp_kses_post( $attr['description'] ) . '</p>';
		}

		foreach ( $attr['options'] as $key => $settings ) {
			// get checked-marker.
			$actual_values = get_option( $attr['fieldId'], array() );
			$value         = ! empty( $actual_values[ $key ] ) ? $actual_values[ $key ] : '';

			// readonly.
			$readonly = '';
			if ( isset( $attr['readonly'] ) && false !== $attr['readonly'] ) {
				$readonly = ' disabled="disabled"';
				?>
				<input type="hidden" name="<?php echo esc_attr( $attr['fieldId'] ); ?>_ro[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>">
				<?php
			}
			if ( isset( $settings['enabled'] ) && false === $settings['enabled'] ) {
				$readonly = ' disabled="disabled"';
			}

			// output.
			?>
			<div class="easy-language-input-text">
				<label for="<?php echo esc_attr( $attr['fieldId'] . $key ); ?>"><?php echo esc_html( $settings['label'] ); ?></label>
				<input type="text" id="<?php echo esc_attr( $attr['fieldId'] . $key ); ?>" name="<?php echo esc_attr( $attr['fieldId'] ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>"<?php echo esc_attr( $readonly ); ?>>
			</div>
			<?php
		}
	}
}

/**
 * Add tab in settings for logs.
 *
 * @return void
 * @noinspection PhpUnused
 */
function easy_language_settings_add_helper_tab(): void {
	// output tab.
	echo '<a href="' . esc_url( Helper::get_plugin_support_url() ) . '" class="nav-tab easy-language-help-tab" target="_blank"><span class="dashicons dashicons-editor-help"></span> ' . esc_html__( 'Need help?', 'easy-language' ) . '</a>';
}

add_action( 'easy_language_settings_add_tab', 'easy_language_settings_add_helper_tab', 200, 0 );
