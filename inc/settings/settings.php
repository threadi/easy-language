<?php
/**
 * File for settings functions of this plugin.
 *
 * @package easy-language
 */

/**
 * Add settings in options menu.
 *
 * @return void
 */
function easy_language_admin_add_settings_menu(): void {
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
	$tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : null;

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Easy Language Plugin', 'easy-language' ); ?></h1>
		<nav class="nav-tab-wrapper">
			<a href="<?php echo admin_url(); ?>options-general.php?page=easy_language_settings" class="nav-tab <?php if($tab===null):?>nav-tab-active<?php endif; ?>"><?php esc_html_e('API', 'easy-language'); ?></a>
			<?php
				do_action('easy_language_settings_add_tab', $tab);
			?>
		</nav>

		<div class="tab-content">
			<?php
			// get the content of the actual tab.
			do_action('easy_language_settings_'.($tab == null ? 'api' : $tab).'_page');
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
	do_action('easy_language_settings_add_settings');

	// get settings-fields.
	global $wp_settings_fields;

	if( !empty($wp_settings_fields) ) {
		// loop through the fields.
		foreach ( $wp_settings_fields as $name => $sections ) {
			// filter for our own settings.
			if ( str_contains( $name, 'easy_language' ) ) {
				// loop through the sections of this setting.
				foreach ( $sections as $section ) {
					// loop through the field of this section.
					foreach ( $section as $field ) {
						$functionName = 'easy_language_admin_sanitize_settings_field';
						if ( ! empty( $field['args']['sanitizeFunction'] ) && function_exists( $field['args']['sanitizeFunction'] ) ) {
							$functionName = $field['args']['sanitizeFunction'];
						}
						add_filter( 'sanitize_option_' . $field['args']['fieldId'], $functionName, 10, 2 );
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
 * @param $attr
 *
 * @return void
 * @noinspection DuplicatedCode
 */
function easy_language_admin_text_field( $attr ): void {
	if( !empty($attr['fieldId']) ) {
		// get value from config
		$value = get_option($attr['fieldId'], '');

		// get value from request.
		if( isset($_POST[$attr['fieldId']]) ) {
			$value = sanitize_text_field($_POST[$attr['fieldId']]);
		}

		// get title.
		$title = '';
		if( isset($attr['title']) ) {
			$title = $attr['title'];
		}

		// set readonly attribute.
		$readonly = '';
		if( isset($attr['readonly']) && false !== $attr['readonly'] ) {
			$readonly = ' disabled="disabled"';
			?><input type="hidden" name="<?php echo esc_attr($attr['fieldId']); ?>_ro" value="<?php echo esc_attr($value); ?>"><?php
		}

		// mark as highlighted if set.
		if( isset($attr['highlight']) && false !== $attr['highlight'] ) {
			?><div class="highlight"><?php
		}

		// output.
		?>
		<input type="text" id="<?php echo esc_attr($attr['fieldId']); ?>" name="<?php echo esc_attr($attr['fieldId']); ?>" value="<?php echo esc_attr($value); ?>"<?php echo !empty($attr['placeholder']) ? ' placeholder="'.esc_attr($attr['placeholder']).'"' : '';echo $readonly; ?> class="widefat" title="<?php echo esc_attr($title); ?>">
		<?php
		if( !empty($attr['description']) ) {
			echo "<p>".wp_kses_post($attr['description'])."</p>";
		}

		// end mark as highlighted if set.
		if( isset($attr['highlight']) && false !== $attr['highlight'] ) {
			?></div><?php
		}
	}
}

/**
 * Define an input-number-field.
 *
 * @param $attr
 *
 * @return void
 * @noinspection DuplicatedCode
 */
function easy_language_admin_number_field( $attr ): void {
	if( !empty($attr['fieldId']) ) {
		// get value from config.
		$value = get_option($attr['fieldId'], '');

		// get value from request.
		if( isset($_POST[$attr['fieldId']]) ) {
			$value = sanitize_text_field($_POST[$attr['fieldId']]);
		}

		// get title.
		$title = '';
		if( isset($attr['title']) ) {
			$title = $attr['title'];
		}

		// set readonly attribute.
		$readonly = '';
		if( isset($attr['readonly']) && false !== $attr['readonly'] ) {
			$readonly = ' disabled="disabled"';
			?><input type="hidden" name="<?php echo esc_attr($attr['fieldId']); ?>_ro" value="<?php echo esc_attr($value); ?>"><?php
		}

		// output
		?>
		<input type="number" id="<?php echo esc_attr($attr['fieldId']); ?>" name="<?php echo esc_attr($attr['fieldId']); ?>" value="<?php echo esc_attr($value); ?>"<?php echo !empty($attr['placeholder']) ? ' placeholder="'.esc_attr($attr['placeholder']).'"' : '';echo $readonly; ?> class="widefat" title="<?php echo esc_attr($title); ?>">
		<?php
		if( !empty($attr['description']) ) {
			echo "<p>".wp_kses_post($attr['description'])."</p>";
		}
	}
}

/**
 * Define an input-email-field.
 *
 * @param $attr
 *
 * @return void
 * @noinspection DuplicatedCode*/
function easy_language_admin_email_field( $attr ): void {
	if( !empty($attr['fieldId']) ) {
		// get value from config.
		$value = get_option($attr['fieldId'], '');

		// get value from request.
		if( isset($_POST[$attr['fieldId']]) ) {
			$value = sanitize_text_field($_POST[$attr['fieldId']]);
		}

		// get title.
		$title = '';
		if( isset($attr['title']) ) {
			$title = $attr['title'];
		}

		// set readonly attribute.
		$readonly = '';
		if( isset($attr['readonly']) && false !== $attr['readonly'] ) {
			$readonly = ' disabled="disabled"';
			?><input type="hidden" name="<?php echo esc_attr($attr['fieldId']); ?>_ro" value="<?php echo esc_attr($value); ?>"><?php
		}

		// mark as highlighted if set.
		if( isset($attr['highlight']) && false !== $attr['highlight'] ) {
			?><div class="highlight"><?php
		}

		// output.
		?>
		<input type="email" id="<?php echo esc_attr($attr['fieldId']); ?>" name="<?php echo esc_attr($attr['fieldId']); ?>" value="<?php echo esc_attr($value); ?>"<?php echo !empty($attr['placeholder']) ? ' placeholder="'.esc_attr($attr['placeholder']).'"' : '';echo $readonly; ?> class="widefat" title="<?php echo esc_attr($title); ?>">
		<?php
		if( !empty($attr['description']) ) {
			echo "<p>".wp_kses_post($attr['description'])."</p>";
		}

		// end mark as highlighted if set.
		if( isset($attr['highlight']) && false !== $attr['highlight'] ) {
			?></div><?php
		}
	}
}

/**
 * Define an input-checkbox-field.
 *
 * @param $attr
 * @return void
 */
function easy_language_admin_checkbox_field( $attr ): void {
	if( !empty($attr['fieldId']) ) {
		// get title.
		$title = '';
		if( isset($attr['title']) ) {
			$title = $attr['title'];
		}

		// set readonly attribute.
		$readonly = '';
		if( isset($attr['readonly']) && false !== $attr['readonly'] ) {
			$readonly = ' disabled="disabled"';
			?><input type="hidden" name="<?php echo esc_attr($attr['fieldId']); ?>_ro" value="<?php echo (get_option($attr['fieldId'], 0) == 1 || ( isset($_POST[$attr['fieldId']]) && absint($_POST[$attr['fieldId']]) == 1 ) ) ? '1' : '0'; ?>"><?php
		}

		?>
		<input type="checkbox" id="<?php echo esc_attr($attr['fieldId']); ?>"
		       name="<?php echo esc_attr($attr['fieldId']); ?>"
		       value="1"
			<?php
			echo (get_option($attr['fieldId'], 0) == 1 || ( isset($_POST[$attr['fieldId']]) && absint($_POST[$attr['fieldId']]) == 1 ) ) ? ' checked="checked"' : '';
			echo esc_attr($readonly); ?>
			   class="easy-language-field-width"
			   title="<?php echo esc_attr($title); ?>"
		>
		<?php

		// show optional description for this checkbox.
		if( !empty($attr['description']) ) {
			echo "<p>".wp_kses_post($attr['description'])."</p>";
		}

		// show optional hint for our Pro-version
		if( !empty($attr['pro_hint']) ) {
			do_action('easy_language_admin_show_pro_hint', $attr['pro_hint']);
		}
	}
}

/**
 * Show select-field with given values.
 *
 * @param $attr
 *
 * @return void
 */
function easy_language_admin_select_field( $attr ): void {
	if( !empty($attr['fieldId']) && !empty($attr['values']) ) {
		// get value from config.
		$value = get_option($attr['fieldId'], '');

		// or get it from request.
		if( isset($_POST[$attr['fieldId']]) ) {
			$value = sanitize_text_field($_POST[$attr['fieldId']]);
		}

		// get title.
		$title = '';
		if( isset($attr['title']) ) {
			$title = $attr['title'];
		}

		// set readonly attribute.
		$readonly = '';
		if( isset($attr['readonly']) && false !== $attr['readonly'] ) {
			$readonly = ' disabled="disabled"';
			?><input type="hidden" name="<?php echo esc_attr($attr['fieldId']); ?>_ro" value="<?php echo esc_attr($value);?>" /><?php
		}

		?>
		<select id="<?php echo esc_attr($attr['fieldId']); ?>" name="<?php echo esc_attr($attr['fieldId']); ?>" class="easy-language-field-width"<?php echo $readonly ; ?> title="<?php echo esc_attr($title); ?>">
			<?php
				if( empty($attr['disable_empty']) ) {
					?>
					<option value=""></option>
					<?php
				}
				foreach( $attr['values'] as $key => $label ) {
					?><option value="<?php echo esc_attr($key); ?>"<?php echo ($value == $key ? ' selected="selected"' : ''); ?>><?php echo esc_html($label); ?></option><?php
				}
			?>
		</select>
		<?php
		if( !empty($attr['description']) ) {
			echo "<p>".wp_kses_post($attr['description'])."</p>";
		}
	}
	elseif( empty($attr['values']) && !empty($attr['noValues']) ) {
		echo "<p>".esc_html($attr['noValues'])."</p>";
	}
}

/**
 * Show multiselect-field with given values.
 *
 * @param $attr
 * @return void
 */
function easy_language_admin_multiselect_field( $attr ): void {
	if( !empty($attr['fieldId']) && !empty($attr['values']) ) {
		// get value from config.
		$actualValues = get_option($attr['fieldId'], array() );
		if( empty($actualValues) ) {
			$actualValues = array();
		}

		// or get them from request.
		if( isset($_POST[$attr['fieldId']]) && is_array($_POST[$attr['fieldId']]) ) {
			$actualValues = array();
			$values = array_map( 'sanitize_text_field', $_POST[$attr['fieldId']] );
			foreach( $values as $key => $item ) {
				$actualValues[absint($key)] = sanitize_text_field($item);
			}
		}

		// if $actualValues is a string, convert it.
		if( !is_array($actualValues) ) {
			$actualValues = explode(',', $actualValues);
		}

		// use values as key if set.
		if(!empty($attr['useValuesAsKeys'])) {
			$newArray = array();
			foreach( $attr['values'] as $value ) {
				$newArray[$value] = $value;
			}
			$attr['values'] = $newArray;
		}

		// get title.
		$title = '';
		if( isset($attr['title']) ) {
			$title = $attr['title'];
		}

		// set readonly attribute.
		$readonly = '';
		if( isset($attr['readonly']) && false !== $attr['readonly'] ) {
			$readonly = ' disabled="disabled"';
			?><input type="hidden" name="<?php echo esc_attr($attr['fieldId']); ?>_ro" value="<?php echo implode(",", $actualValues);?>" /><?php
		}

		?>
		<select id="<?php echo esc_attr($attr['fieldId']); ?>" name="<?php echo esc_attr($attr['fieldId']); ?>[]" multiple class="easy-language-field-width"<?php echo $readonly; ?> title="<?php echo esc_attr($title); ?>">
			<?php
			foreach( $attr['values'] as $key => $value ) {
				?><option value="<?php echo esc_attr($key); ?>"<?php echo in_array($key, $actualValues, true ) ? ' selected="selected"' : ''; ?>><?php echo esc_html($value); ?></option><?php
			}
			?></select>
		<?php
		if( !empty($attr['description']) ) {
			echo "<p>".wp_kses_post($attr['description'])."</p>";
		}
	}
}

/**
 * Show multiple checkboxes for a single setting.
 *
 * @param $attr
 *
 * @return void
 */
function easy_language_admin_multiple_checkboxes_field( $attr ): void {
	if( !empty($attr['options']) ) {
		if( !empty($attr['description']) ) {
			echo '<p class="easy-language-checkbox">'.wp_kses_post($attr['description']).'</p>';
		}

		foreach( $attr['options'] as $key => $settings ) {
			// get checked-marker.
			$actual_values = get_option( $attr['fieldId'], array() );
			$checked = !empty($actual_values[$key]) ? ' checked="checked"' : '';

			// title.
			$title = __( 'Check to enable this language.', 'easy-language');

			// readonly.
			$readonly = '';
			if( isset($attr['readonly']) && false !== $attr['readonly'] ) {
				$readonly = ' disabled="disabled"';
				?><input type="hidden" name="<?php echo esc_attr($attr['fieldId']); ?>_ro[<?php echo esc_attr($key); ?>]" value="<?php echo !empty($checked) ? 1 : 0; ?>"><?php
			}
			if( isset($settings['enabled']) && false === $settings['enabled'] ) {
				$readonly = ' disabled="disabled"';
				$title = '';
			}

			// output.
			?>
			<div class="easy-language-checkbox">
				<input type="checkbox" id="<?php echo esc_attr($attr['fieldId'].$key); ?>" name="<?php echo esc_attr($attr['fieldId']); ?>[<?php echo esc_attr($key); ?>]" value="1"<?php echo esc_attr($checked).esc_attr($readonly); ?> title="<?php echo esc_attr($title); ?>">
				<label for="<?php echo esc_attr($attr['fieldId'].$key); ?>"><?php echo esc_html($settings['label']); ?></label>
				<?php
				if( !empty($settings['description']) ) {
					echo "<p>".wp_kses_post($settings['description'])."</p>";
				}
				?>
			</div>
			<?php
		}

		// show pro hint.
		/* translators: %1$s is replaced with "string" */
		do_action('easy_language_admin_show_pro_hint', __('Use all languages supported by SUMM AI with %s.', 'easy-language'));
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
	if( !empty($attr['options']) ) {
        if( !empty($attr['description_above']) && false !== $attr['description_above'] ) {
	        if( !empty($attr['description']) ) {
		        echo "<p>".wp_kses_post($attr['description'])."</p>";
	        }
        }

		foreach( $attr['options'] as $key => $settings ) {
			// get checked-marker.
			$actual_value = get_option($attr['fieldId'], '');
			$checked = $actual_value === $key ? ' checked="checked"' : '';

			// title.
			$title = __( 'Check to enable this language.', 'easy-language');

			// readonly.
			$readonly = '';
			if( isset($attr['readonly']) && false !== $attr['readonly'] ) {
				$readonly = ' disabled="disabled"';
				if( !empty($checked) ) {
					?><input type="hidden" name="<?php echo esc_attr($attr['fieldId']); ?>_ro" value="<?php echo esc_attr($key); ?>"><?php
				}
			}
			if( false === $settings['enabled'] ) {
				$readonly = ' disabled="disabled"';
				$title = '';
			}

			// output.
			?>
			<div class="easy-language-radio">
				<input type="radio" id="<?php echo esc_attr($attr['fieldId'].$key); ?>" name="<?php echo esc_attr($attr['fieldId']); ?>" value="<?php echo esc_attr($key); ?>"<?php echo esc_attr($checked).esc_attr($readonly); ?> title="<?php echo esc_attr($title); ?>">
				<label for="<?php echo esc_attr($attr['fieldId'].$key); ?>"><?php echo esc_html($settings['label']); ?></label>
				<?php
					if( !empty($settings['description']) ) {
					echo "<p>".wp_kses_post($settings['description'])."</p>";
					}
				?>
			</div>
			<?php
		}

		if( empty($attr['description_above']) && !empty($attr['description']) ) {
			echo "<p>".wp_kses_post($attr['description'])."</p>";
		}

		// show pro hint.
		/* translators: %1$s is replaced with the name of the Pro-plugin */
		do_action('easy_language_admin_show_pro_hint', __('Use all languages supported by SUMM AI with %s.', 'easy-language'));
	}
}
