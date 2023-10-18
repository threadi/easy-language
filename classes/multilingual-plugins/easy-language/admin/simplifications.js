jQuery( document ).ready(
	function ($) {
		// start to translate an object via AJAX.
		$('.easy-language-translate-object').on(
			'click',
			function (e) {
				e.preventDefault();
				easy_language_simplification_init($(this).data('id'), $(this).data('link'));
			}
		);
	}
);

/**
 * Initialize the simplification incl. confirmation.
 *
 * @param id
 * @param link
 */
function easy_language_simplification_init( id, link ) {
	if (confirm(easyLanguageSimplificationJsVars.translate_confirmation_question)) {
		easy_language_get_simplification( id, link );
	}
}

/**
 * Start loading of simplifications of actual object.
 */
function easy_language_get_simplification( obj_id, link ) {
	// create dialog if it does not exist atm.
	if ( jQuery( '#easylanguage-simplification-dialog' ).length === 0 ) {
		jQuery( '<div id="easylanguage-simplification-dialog" title="' + easyLanguageSimplificationJsVars.label_simplification_is_running + '"><div id="easylanguage-simplification-dialog-step-description"></div><div id="easylanguage-simplification-dialog-progressbar"></div></div>' ).dialog(
			{
				width: 500,
				closeOnEscape: false,
				dialogClass: "easylanguage-simplification-dialog-no-close",
				resizable: false,
				modal: true,
				draggable: false,
				buttons: [
					{
						text: easyLanguageSimplificationJsVars.label_open_link,
						click: function () {
							location.href = link;
						}
					},
					{
						text: easyLanguageSimplificationJsVars.label_ok,
						click: function () {
							location.reload();
						}
					}
				]
			}
		);
	} else {
		jQuery( '#easylanguage-simplification-dialog' ).dialog( 'open' );
	}

	// disable buttons in dialog.
	jQuery( '.easylanguage-simplification-dialog-no-close .ui-button' ).prop( 'disabled', true );

	// init description.
	let stepDescription = jQuery( '#easylanguage-simplification-dialog-step-description' );
	stepDescription.html( '<p>' + easyLanguageSimplificationJsVars.txt_please_wait + '</p>' );

	// init and show progressbar.
	let progressbar = jQuery( "#easylanguage-simplification-dialog-progressbar" );
	progressbar.progressbar(
		{
			value: 0
		}
	).removeClass( "hidden" );

	// start simplification.
	easy_language_get_simplification_info( obj_id, progressbar, stepDescription, true );
}

/**
 * Get import info until import is done.
 *
 * @param obj_id
 * @param progressbar
 * @param stepDescription
 * @param initialization
 */
function easy_language_get_simplification_info(obj_id, progressbar, stepDescription, initialization) {
	jQuery.ajax(
		{
			type: "POST",
			url: easyLanguageSimplificationJsVars.ajax_url,
			data: {
				'action': 'easy_language_run_simplification',
				'post': obj_id,
				'initialization': initialization,
				'nonce': easyLanguageSimplificationJsVars.run_simplification_nonce
			},
			success: function (data) {
				let stepData = data.split( ";" );
				let count    = parseInt( stepData[0] );
				let max      = parseInt( stepData[1] );
				let running  = parseInt( stepData[2] );
				let result   = stepData[3];
				let link = stepData[4];

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
							easy_language_get_simplification_info( obj_id, progressbar, stepDescription, false ) },
						200
					);
				} else {
					// show result.
					stepDescription.html( '<p>' + result + '</p>' );

					// hide progressbar.
					progressbar.addClass( "hidden" );

					// update dialog-title.
					jQuery( '#easylanguage-simplification-dialog' ).dialog( { title:easyLanguageSimplificationJsVars.label_simplification_done } );

					// get buttons.
					let buttons = jQuery( '.easylanguage-simplification-dialog-no-close .ui-button' );

					// update link-target of the first button.
					if( link ) {
						buttons.first().off('click').on('click', function() {
							location.href = link;
						});
					}

					// enable buttons.
					buttons.prop( 'disabled', false );
				}
			}
		}
	)
}
