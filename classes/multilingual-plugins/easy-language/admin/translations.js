/**
 * Start loading of translations of actual object.
 */
function easy_language_get_translation( obj_id ) {
	// create dialog if it does not exist atm
	if ( jQuery( '#easylanguage-translate-dialog' ).length === 0 ) {
		jQuery( '<div id="easylanguage-translate-dialog" title="' + easyLanguageTranslationsJsVars.label_translate_is_running + '"><div id="easylanguage-translate-dialog-step-description"></div><div id="easylanguage-translate-dialog-progressbar"></div></div>' ).dialog(
			{
				width: 500,
				closeOnEscape: false,
				dialogClass: "easylanguage-translate-dialog-no-close",
				resizable: false,
				modal: true,
				draggable: false,
				buttons: [
				{
					text: easyLanguageTranslationsJsVars.label_ok,
					click: function () {
						location.reload();
					}
				}
				]
			}
		);
	} else {
		jQuery( '#easylanguage-translate-dialog' ).dialog( 'open' );
	}

	// disable button in dialog
	jQuery( '.easylanguage-translate-dialog-no-close .ui-button' ).prop( 'disabled', true );

	// init description
	let stepDescription = jQuery( '#easylanguage-translate-dialog-step-description' );
	stepDescription.html( '<p>' + easyLanguageTranslationsJsVars.txt_please_wait + '</p>' );

	// init progressbar
	let progressbar = jQuery( "#easylanguage-translate-dialog-progressbar" );
	progressbar.progressbar(
		{
			value: 0
		}
	).removeClass( "hidden" );

	// start translation.
	jQuery.ajax(
		{
			type: "POST",
			url: easyLanguageTranslationsJsVars.ajax_url,
			data: {
				'action': 'easy_language_run_translation',
				'post': obj_id,
				'nonce': easyLanguageTranslationsJsVars.run_translate_nonce
			},
			beforeSend: function () {
				// get import-infos
				setTimeout(
					function () {
						easy_language_get_translation_info( obj_id, progressbar, stepDescription ); },
					1000
				);
			}
		}
	);
}

/**
 * Get import info until import is done.
 *
 * @param obj_id
 * @param progressbar
 * @param stepDescription
 */
function easy_language_get_translation_info(obj_id, progressbar, stepDescription) {
	jQuery.ajax(
		{
			type: "POST",
			url: easyLanguageTranslationsJsVars.ajax_url,
			data: {
				'action': 'easy_language_get_info_translation',
				'post': obj_id,
				'nonce': easyLanguageTranslationsJsVars.get_translate_nonce
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
							easy_language_get_translation_info( obj_id, progressbar, stepDescription ) },
						500
					);
				} else {
					progressbar.addClass( "hidden" );
					stepDescription.html( easyLanguageTranslationsJsVars.txt_translation_has_been_run );
					jQuery( '.easylanguage-translate-dialog-no-close .ui-button' ).prop( 'disabled', false );
				}
			}
		}
	)
}
