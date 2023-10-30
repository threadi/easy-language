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
 * Create simplified object via AJAX with or without automatic simplification.
 *
 * @param object_id
 * @param language
 * @param simplification_mode
 */
function easy_language_add_simplification_object( object_id, language, simplification_mode ) {
	jQuery.ajax(
		{
			type: "POST",
			url: easyLanguageSimplificationJsVars.ajax_url,
			data: {
				'action': 'easy_language_add_simplification_object',
				'post': object_id,
				'language': language,
				'nonce': easyLanguageSimplificationJsVars.add_simplification_nonce
			},
			success: function(data) {
				if( 'ok' === data.status && "auto" === simplification_mode ) {
					easy_language_get_simplification( data.object_id, data.language, false );
				}
				else if( 'ok' === data.status && "manually" === simplification_mode ) {
					// create dialog.
					let dialog_config = {
						detail: {
							title: '%1$s ready for simplification'.replace('%1$s', data.object_type_name ),
							texts: [
								'<p>The %1$s <i>%2$s</i> has been created in %3$s.<br>Its texts are not yet simplified.</p>'.replace('%1$s', data.object_type_name).replace('%2$s', data.title).replace('%3$s', data.language)
							],
							buttons: [
								{
									'action': 'easy_language_get_simplification(' + data.object_id + ', "' + data.language + '", false );',
									'variant': 'primary',
									'text': 'Simplify now via API %1$s'.replace('%1$s', data.api_title)
								},
								{
									'action': 'location.href="' + data.edit_link + '";',
									'variant': 'secondary',
									'text': 'Edit'
								},
								{
									'action': 'location.reload();',
									'text': 'Cancel'
								}
							]
						}
					}
					easy_language_create_dialog( dialog_config );
				}
				else if( 'ok' === data.status && "background" === simplification_mode ) {
					// create dialog.
					let dialog_config = {
						detail: {
							title: '%1$s will be simplified'.replace('%1$s', data.title ),
							texts: [
								'<p>The %1$s <i>%2$s</i> has been created in %3$s.<br>Its texts will be simplified in background.</p>'.replace('%1$s', data.object_type_name).replace('%2$s', data.title).replace('%3$s', data.language)
							],
							buttons: [
								{
									'action': 'location.reload();',
									'variant': 'primary',
									'text': 'OK'
								}
							]
						}
					}
					easy_language_create_dialog( dialog_config );
				}
			}
		}
	);
}

/**
 * Initialize the simplification incl. confirmation.
 *
 * @param id ID of the object to simplify.
 * @param link URL for the object which will be simplified.
 * @param frontend_edit Bool if the edit uses the frontend.
 */
function easy_language_simplification_init( id, link, frontend_edit ) {
	let dialog_config = {
		detail: {
			hide_title: true,
			texts: [
				'<p><strong>' + easyLanguageSimplificationJsVars.translate_confirmation_question + '</strong></p>'
			],
			buttons: [
				{
					'action': 'easy_language_get_simplification( ' + id + ', "' + link + '", ' + frontend_edit + ');',
					'variant': 'primary',
					'text': 'Yes'
				},
				{
					'action': 'closeDialog();',
					'variant': 'secondary',
					'text': 'No'
				}
			]
		}
	}
	document.body.dispatchEvent(new CustomEvent("easy-language-dialog", dialog_config));
}

/**
 * Start loading of simplifications of actual object.
 *
 * @param object_id
 * @param link
 * @param frontend_edit
 */
function easy_language_get_simplification( object_id, link, frontend_edit ) {
	// create dialog.
	let dialog_config = {
		detail: {
			title: 'Simplification in progress',
			progressbar: {
				active: true,
				progress: 0,
				id: 'progress' + object_id
			},
		}
	}
	document.body.dispatchEvent(new CustomEvent("easy-language-dialog", dialog_config));

	// start simplification.
	easy_language_get_simplification_info( object_id, true );
}

/**
 * Get import info until import is done.
 *
 * @param obj_id
 * @param initialization
 */
function easy_language_get_simplification_info( obj_id, initialization ) {
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
			error: function(e) {
				if( e.textStatus !== undefined ) {
					// create dialog with error-message.
					let dialog_config = {
						detail: {
							title: easyLanguageSimplificationJsVars.label_simplification_error,
							texts: [
								easyLanguageSimplificationJsVars.txt_simplification_error.replace('[error]', e.statusText)
							],
							buttons: [
								{
									'action': 'closeDialog();',
									'variant': 'secondary',
									'text': 'OK'
								}
							]
						}
					}
					document.body.dispatchEvent(new CustomEvent("easy-language-dialog", dialog_config));
				}
			},
			success: function (data) {
				let count    = parseInt( data[0] );
				let max      = parseInt( data[1] );
				let running  = parseInt( data[2] );
				let result   = data[3];
				let link = data[4];

				// update progressbar.
				jQuery("#progress" + obj_id).attr('value', (count / max) * 100);

				// get next info until running is not 0.
				if ( running >= 1 ) {
					setTimeout(
						function () {
							easy_language_get_simplification_info( obj_id, false ) },
						200
					);
				} else {
					// create dialog.
					let dialog_config = {
						detail: {
							title: 'Simplification processed',
							texts: [
								'<p>' + result + '</p>'
							],
							buttons: [
								{
									'action': 'location.href="' + link + '";',
									'variant': 'primary',
									'text': 'Show site'
								},
								{
									'action': 'closeDialog();',
									'variant': 'secondary',
									'text': 'Cancel'
								}
							]
						}
					}
					document.body.dispatchEvent(new CustomEvent("easy-language-dialog", dialog_config));

					// set events on buttons in texts.
					/*stepDescription.find('a.button').each(function() {
						if( jQuery(this).data('run-again') ) {
							jQuery(this).on('click', function (e) {
								e.preventDefault();

								// first reset the processing texts to state "to_simplify".
								jQuery.ajax(
									{
										type: "POST",
										url: easyLanguageSimplificationJsVars.ajax_url,
										data: {
											'action': 'easy_language_reset_processing_simplification',
											'post': obj_id,
											'nonce': easyLanguageSimplificationJsVars.reset_processing_simplification_nonce
										},
										error: function (e) {
											// hide progressbar.
											progressbar.addClass("hidden");

											// update dialog-title.
											jQuery('#easylanguage-simplification-dialog').dialog({title: easyLanguageSimplificationJsVars.label_simplification_error});

											// show error.
											stepDescription.html(easyLanguageSimplificationJsVars.txt_simplification_error.replace('[error]', e.statusText));

											// get buttons.
											jQuery('.easylanguage-simplification-dialog-no-close .ui-button').prop('disabled', false);
										},
										success: function (data) {
											// then run normal simplification again.
											easy_language_get_simplification_info( obj_id, progressbar, stepDescription, true, frontend_edit );
										}
									}
								);
							});
						}
						if( jQuery(this).data('ignore-texts') ) {
							jQuery(this).on('click', function (e) {
								e.preventDefault();

								// first set the processing texts to state "ignore".
								jQuery.ajax(
									{
										type: "POST",
										url: easyLanguageSimplificationJsVars.ajax_url,
										data: {
											'action': 'easy_language_ignore_processing_simplification',
											'post': obj_id,
											'nonce': easyLanguageSimplificationJsVars.ignore_processing_simplification_nonce
										},
										error: function (e) {
											// hide progressbar.
											progressbar.addClass("hidden");

											// update dialog-title.
											jQuery('#easylanguage-simplification-dialog').dialog({title: easyLanguageSimplificationJsVars.label_simplification_error});

											// show error.
											stepDescription.html(easyLanguageSimplificationJsVars.txt_simplification_error.replace('[error]', e.statusText));

											// get buttons.
											jQuery('.easylanguage-simplification-dialog-no-close .ui-button').prop('disabled', false);
										},
										success: function (data) {
											// then run normal simplification again.
											easy_language_get_simplification_info( obj_id, progressbar, stepDescription, true, frontend_edit );
										}
									}
								);
							});
						}
					});*/
				}
			}
		}
	);
}
