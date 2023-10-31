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

		// start to simplify a single text via AJAX.
		$('.easy-language-simplify-text').on(
			'click',
			function (e) {
				e.preventDefault();
				// create dialog.
				let dialog_config = {
					detail: {
						title: 'Simplify this text?',
						texts: [
							'<p>Simplifying texts via API could cause costs.<br><strong>Are you sure your want to simplify this single text?</strong></p>'
						],
						buttons: [
							{
								'action': 'location.href="' + $(this).attr('href') + '";',
								'variant': 'primary',
								'text': easyLanguageSimplificationJsVars.label_yes
							},
							{
								'action': 'closeDialog();',
								'variant': 'secondary',
								'text': easyLanguageSimplificationJsVars.label_no
							}
						]
					}
				}
				easy_language_create_dialog( dialog_config );
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
				'prevent_automatic_simplification': "manually" === simplification_mode,
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
									'text': 'Edit %1$s'.replace('%1$s', data.object_type_name)
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
			title: easyLanguageSimplificationJsVars.title_simplify_texts,
			texts: [
				'<p>' + easyLanguageSimplificationJsVars.translate_confirmation_question + '</p>'
			],
			buttons: [
				{
					'action': 'easy_language_get_simplification( ' + id + ', "' + link + '", ' + frontend_edit + ');',
					'variant': 'primary',
					'text': easyLanguageSimplificationJsVars.label_yes
				},
				{
					'action': 'closeDialog();',
					'variant': 'secondary',
					'text': easyLanguageSimplificationJsVars.label_no
				}
			]
		}
	}
	easy_language_create_dialog( dialog_config );
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
	easy_language_create_dialog( dialog_config );

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
					easy_language_create_dialog( dialog_config );
				}
			},
			success: function (data) {
				let count    = parseInt( data[0] );
				let max      = parseInt( data[1] );
				let running  = parseInt( data[2] );
				let result   = data[3];
				let link = data[4];
				let edit_link = data[5];

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
									'text': 'Show in frontend'
								},
								{
									'action': 'location.href="' + edit_link + '";',
									'variant': 'primary',
									'text': 'Edit'
								},
								{
									'action': 'closeDialog();',
									'variant': 'secondary',
									'text': 'Cancel'
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
