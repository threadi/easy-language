jQuery(document).ready(function($) {
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
								title: easyLanguageJsVars.label_reset_intro,
								texts: [
									'<p>' + easyLanguageJsVars.txt_intro_reset + '</p>'
								],
								buttons: [
									{
										'action': 'location.href = "' + easyLanguageJsVars.admin_start + '";',
										'variant': 'primary',
										'text': easyLanguageJsVars.label_ok
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

	// delete all simplified texts.
	$('body.settings_page_easy_language_settings a.easy-language-delete-data').on('click', function(e) {
		e.preventDefault();

		// create dialog.
		let dialog_config = {
			detail: {
				hide_title: true,
				texts: [
					'<p><strong>' + easyLanguageJsVars.txt_delete_question + '</strong></p>',
				],
				buttons: [
					{
						'action': 'easy_language_start_data_deletion();',
						'variant': 'primary',
						'text': easyLanguageJsVars.label_yes,
					},
					{
						'action': 'closeDialog();',
						'variant': 'primary',
						'text': easyLanguageJsVars.label_no,
					}
				]
			}
		}
		easy_language_create_dialog( dialog_config );
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
			title: easyLanguageJsVars.label_icon_chooser,
			library : {
				type : 'image'
			},
			button: {
				text: easyLanguageJsVars.button_icon_chooser
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
					success: function() {
						// replace img in list.
						obj.parent().find('img').attr( 'src', attachment.url );

						// show success message.
						alert(easyLanguageJsVars.txt_icon_changed);
					}
				}
			);

		}).open();
	});
});

function easy_language_start_data_deletion() {
	// create dialog.
	let dialog_config = {
		detail: {
			title: easyLanguageJsVars.label_delete_data,
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
			success: function() {
				easy_language_get_data_deletion_info();
			}
		}
	);
}

/**
 * Get import info until deletion is done.
 */
function easy_language_get_data_deletion_info() {
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
							title: easyLanguageJsVars.label_delete_data,
							texts: [
								easyLanguageJsVars.txt_deletion_done
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
