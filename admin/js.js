jQuery(document).ready(function($) {
	// get internationalization tools of WordPress.
	let { __ } = wp.i18n;

	let settings_body = $('body.settings_page_easy_language_settings .wrap > h1');

	// add option near to list-headline.
	settings_body.after('<a class="page-title-action easy-language-pro-hint" href="' + easyLanguageJsVars.pro_url + '" target="_blank">' + easyLanguageJsVars.title_get_pro + '</a>');
	settings_body.each(function() {
		let button = document.createElement('a');
		button.className = 'review-hint-button page-title-action';
		button.href = easyLanguageJsVars.review_url;
		button.innerHTML = easyLanguageJsVars.title_rate_us;
		button.target = '_blank';
		this.after(button);
	})

    // save to hide transient-messages via ajax-request.
    $('div[data-dismissible] button.notice-dismiss').on('click',
        function (event) {
            event.preventDefault();
            let $this = $(this);
            let attr_value, option_name, dismissible_length, data;
            attr_value = $this.closest('div[data-dismissible]').attr('data-dismissible').split('-');

            // Remove the dismissible length from the attribute value and rejoin the array.
            dismissible_length = attr_value.pop();
            option_name = attr_value.join('-');
            data = {
                'action': 'dismiss_admin_notice',
                'option_name': option_name,
                'dismissible_length': dismissible_length,
                'nonce': easyLanguageJsVars.dismiss_nonce
            };

            // run ajax request to save this setting
            $.post(easyLanguageJsVars.ajax_url, data);
            $this.closest('div[data-dismissible]').hide('slow');
        }
    );

	// reset intro via ajax.
	$('body.settings_page_easy_language_settings a.easy-language-reset-intro').on('click', function(e) {
		e.preventDefault();

		// send request for reset via ajax.
		jQuery.ajax(
			{
				type: "POST",
				url: easyLanguageJsVars.ajax_url,
				data: {
					'action': 'easy_language_reset_intro',
					'nonce': easyLanguageJsVars.reset_intro_nonce
				},
				success: function ( data ) {
					if( data.result === 'ok' ) {
						// create dialog.
						let dialog_config = {
							detail: {
								title: __( 'Intro reset', 'easy-language' ),
								texts: [
									'<p>' + __( '<p><strong>Intro has been reset.</strong> You can now start again to configure the plugin.<br><strong>Hint:</strong> No configuration and data has been changed.</p>', 'easy-language' ) + '</p>'
								],
								buttons: [
									{
										'action': 'location.href = "' + easyLanguageJsVars.admin_start + '";',
										'variant': 'primary',
										'text': __( 'OK', 'easy-language' )
									}
								]
							}
						}
						easy_language_create_dialog( dialog_config );
					}
				}
			}
		);
	});

	// prevent leaving of posts-form if it has changes.
	$("body.settings_page_easy_language_settings form").dirty({preventLeaving: true});

	/**
	 * Image handling: choose new icon vor language.
	 */
	$('body.settings_page_easy_language_settings a.replace-icon').on( 'click',function(e){
		e.preventDefault();
		let obj = $(this);
		let custom_uploader = wp.media({
			title: __( 'Choose new icon', 'easy-language' ),
			library : {
				type : 'image'
			},
			button: {
				text: __( 'Use this icon', 'easy-language' )
			},
			multiple: false
		}).on('select', function() { // it also has "open" and "close" events
			// get attachment-data.
			let attachment = custom_uploader.state().get('selection').first().toJSON();

			// save new attachment for language via ajax.
			// start data deletion.
			jQuery.ajax(
				{
					type: "POST",
					url: easyLanguageJsVars.ajax_url,
					data: {
						'action': 'easy_language_set_icon_for_language',
						'icon': attachment.id,
						'language': obj.parent().find('span').data( 'language-code' ),
						'nonce': easyLanguageJsVars.set_icon_for_language_nonce
					},
					success: function( dialog_config ) {
						// replace img in list.
						obj.parent().find('img').attr( 'src', attachment.url );

						// show success message.
						easy_language_create_dialog( dialog_config );
					}
				}
			);

		}).open();
	});
});

/**
 * Delete all simplified texts via AJAX incl. progressbar.
 */
function easy_language_start_data_deletion() {
	// get internationalization tools of WordPress.
	let { __ } = wp.i18n;

	// create dialog.
	let dialog_config = {
		detail: {
			title: __( 'Deletion of simplified texts', 'easy-language' ),
			progressbar: {
				active: true,
				progress: 0,
				id: 'progress_delete_simplifications'
			}
		}
	}
	easy_language_create_dialog( dialog_config );

	// start deletion.
	jQuery.ajax(
		{
			type: "POST",
			url: easyLanguageJsVars.ajax_url,
			data: {
				'action': 'easy_language_run_data_deletion',
				'nonce': easyLanguageJsVars.run_delete_data_nonce
			},
			success: function( data ) {
				easy_language_get_data_deletion_info();
			}
		}
	);
}

/**
 * Get import info until deletion is done.
 */
function easy_language_get_data_deletion_info() {
	// get internationalization tools of WordPress.
	let { __ } = wp.i18n;

	// run info check.
	jQuery.ajax(
		{
			type: "POST",
			url: easyLanguageJsVars.ajax_url,
			data: {
				'action': 'easy_language_get_info_delete_data',
				'nonce': easyLanguageJsVars.get_delete_data_nonce
			},
			success: function (data) {
				let stepData = data.split( ";" );
				let count    = parseInt( stepData[0] );
				let max      = parseInt( stepData[1] );
				let running  = parseInt( stepData[2] );

				// update progressbar.
				jQuery("#progress_delete_simplifications").attr('value', (count / max) * 100);

				// get next info until running is not 1.
				if ( running >= 1 ) {
					setTimeout(
						function () {
							easy_language_get_data_deletion_info() },
						500
					);
				} else {
					// create dialog.
					let dialog_config = {
						detail: {
							title: __( 'Deletion of simplified texts', 'easy-language' ),
							texts: [
								__( '<p><strong>Deletion of simplified texts done.</strong><br>You can now start with simplifications.</p>', 'easy-language' )
							],
							buttons: [
								{
									'action': 'closeDialog();',
									'variant': 'primary',
									'text': 'OK',
								}
							]
						}
					}
					easy_language_create_dialog( dialog_config );
				}
			}
		}
	)
}
