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
						jQuery( '<div id="easylanguage-simple-dialog" title="' + easyLanguageJsVars.label_reset_intro + '">' + easyLanguageJsVars.txt_intro_reset + '</div>' ).dialog(
							{
								width: 500,
								closeOnEscape: true,
								resizable: false,
								modal: true,
								draggable: false,
								buttons: [
									{
										text: easyLanguageJsVars.label_ok,
										click: function () {
											location.href = easyLanguageJsVars.admin_start;
										}
									}
								]
							}
						);
					}
				}
			}
		);
	});

	// delete all simplified data.
	$('body.settings_page_easy_language_settings a.easy-language-delete-data').on('click', function(e) {
		e.preventDefault();

		if( confirm(easyLanguageJsVars.txt_delete_question) ) {

			// create dialog if it does not exist atm.
			if (jQuery('#easylanguage-delete-data-dialog').length === 0) {
				jQuery('<div id="easylanguage-delete-data-dialog" title="' + easyLanguageJsVars.label_delete_data + '"><div id="easylanguage-delete-data-dialog-step-description"></div><div id="easylanguage-delete-data-dialog-progressbar"></div></div>').dialog(
					{
						width: 500,
						closeOnEscape: false,
						dialogClass: "easylanguage-delete-data-dialog-no-close",
						resizable: false,
						modal: true,
						draggable: false,
						buttons: [
							{
								text: easyLanguageJsVars.label_ok,
								click: function () {
									location.reload();
								}
							}
						]
					}
				);
			} else {
				jQuery('#easylanguage-delete-data-dialog').dialog('open');
			}

			// disable buttons in dialog.
			jQuery('.easylanguage-delete-data-dialog-no-close .ui-button').prop('disabled', true);

			// init description.
			let stepDescription = jQuery('#easylanguage-delete-data-dialog-step-description');
			stepDescription.html('<p>' + easyLanguageJsVars.txt_please_wait + '</p>');

			// init progressbar.
			let progressbar = jQuery("#easylanguage-delete-data-dialog-progressbar");
			progressbar.progressbar(
				{
					value: 0
				}
			).removeClass("hidden");

			// start data deletion.
			jQuery.ajax(
				{
					type: "POST",
					url: easyLanguageJsVars.ajax_url,
					data: {
						'action': 'easy_language_run_data_deletion',
						'nonce': easyLanguageJsVars.run_delete_data_nonce
					},
					beforeSend: function () {
						// get import-infos
						setTimeout(
							function () {
								easy_language_get_data_deletion_info(progressbar, stepDescription);
							},
							1000
						);
					}
				}
			);
		}
	});

	// prevent leaving of posts-form if it has changes.
	$("body.settings_page_easy_language_settings form").dirty({preventLeaving: true});

	/**
	 * Image handling: choose new icon vor language.
	 */
	$('body.settings_page_easy_language_settings a.replace-icon').on( 'click',function(e){
		e.preventDefault();
		let obj = $(this);
		console.log(obj.parent().find('span'));
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

/**
 * Get import info until deletion is done.
 *
 * @param progressbar
 * @param stepDescription
 */
function easy_language_get_data_deletion_info(progressbar, stepDescription) {
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

				// update progressbar
				progressbar.progressbar(
					{
						value: (count / max) * 100
					}
				);

				// get next info until running is not 1
				if ( running >= 1 ) {
					setTimeout(
						function () {
							easy_language_get_data_deletion_info( progressbar, stepDescription ) },
						500
					);
				} else {
					progressbar.addClass( "hidden" );
					stepDescription.html( easyLanguageJsVars.txt_deletion_done );
					jQuery( '.easylanguage-delete-data-dialog-no-close .ui-button' ).prop( 'disabled', false );
				}
			}
		}
	)
}
