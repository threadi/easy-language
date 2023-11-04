jQuery( document ).ready(
	function ($) {
		// get internationalization tools of WordPress.
		let { __ } = wp.i18n;

		// start to translate an object via AJAX.
		$('.easy-language-translate-object').on(
			'click',
			function (e) {
				e.preventDefault();
				easy_language_simplification_init( $(this).data('id'), $(this).data('link'), false );
			}
		);

		// save automatic prevention setting.
		$('input.easy-language-automatic-simplification-prevention').on( 'change', function() {
			easy_language_prevent_automatic_simplification( $(this).data('id'), $(this).is(':checked'), null );
		});

		// start to simplify a single text via AJAX.
		$('.easy-language-simplify-text').on(
			'click',
			function (e) {
				e.preventDefault();
				// create dialog.
				let dialog_config = {
					detail: {
						title: __('Simplify this text?', 'easy-language'),
						texts: [
							__( '<p>Simplifying texts via API could cause costs.<br><strong>Are you sure your want to simplify this single text?</strong></p>', 'easy-language' )
						],
						buttons: [
							{
								'action': 'location.href="' + $(this).attr('href') + '";',
								'variant': 'primary',
								'text': __( 'Yes', 'easy-language' )
							},
							{
								'action': 'closeDialog();',
								'variant': 'secondary',
								'text': __( 'No', 'easy-language' )
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
 * Send request to reset the simplification process of given object.
 *
 * @param object_id
 * @param link
 */
function easy_language_reset_processing_simplification( object_id, link ) {
	jQuery.ajax(
		{
			type: "POST",
			url: easyLanguageSimplificationJsVars.ajax_url,
			data: {
				'action': 'easy_language_reset_processing_simplification',
				'post': object_id,
				'nonce': easyLanguageSimplificationJsVars.reset_processing_simplification_nonce
			},
			success: function() {
				easy_language_simplification_init( object_id, link, false );
			}
		}
	);
}

/**
 * Send request to ignore the failed simplification of a given object.
 *
 * @param object_id
 * @param link
 */
function easy_language_ignore_processing_simplification( object_id, link ) {
	jQuery.ajax(
		{
			type: "POST",
			url: easyLanguageSimplificationJsVars.ajax_url,
			data: {
				'action': 'easy_language_ignore_processing_simplification',
				'post': object_id,
				'nonce': easyLanguageSimplificationJsVars.ignore_processing_simplification_nonce
			},
			success: function() {
				easy_language_simplification_init( object_id, link, false );
			}
		}
	);
}

/**
 * Create simplified object via AJAX with or without automatic simplification.
 *
 * @param object_id
 * @param language
 * @param simplification_mode
 * @param api_configured
 */
function easy_language_add_simplification_object( object_id, language, simplification_mode, api_configured ) {
	// get internationalization tools of WordPress.
	let { __ } = wp.i18n;

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
					let dialog_config = {};
					if( api_configured ) {
						dialog_config = {
							detail: {
								/* translators: %1$s will be replaced by the object type name (e.g. page or post) */
								title: __('%1$s ready for simplification', 'easy-language').replace('%1$s', data.object_type_name),
								texts: [
									/* translators: %1$s and %4$s will be replaced by the object type name (e.g. page or post), %2$s will be replaced by the object-title, %3$s will be replaced by the language-name */
									__('<p>The %1$s <i>%2$s</i> has been created in %3$s.<br>Its texts are not yet simplified.<br>If you decide to edit this %4$s by yourself its texts will not automatically be simplified in background.</p>', 'easy-language').replace('%1$s', data.object_type_name).replace('%2$s', data.title).replace('%3$s', data.language).replace('%4$s', data.object_type_name)
								],
								buttons: [
									{
										'action': 'easy_language_get_simplification(' + data.object_id + ' );',
										'variant': 'primary',
										/* translators: %1$s will be replaced by the API-title */
										'text': __('Simplify now via API %1$s', 'easy-language').replace('%1$s', data.api_title)
									},
									{
										'action': 'easy_language_prevent_automatic_simplification(' + data.object_id + ', true, "' + data.edit_link + '" );',
										'variant': 'secondary',
										/* translators: %1$s will be replaced by the object type name (e.g. page or post) */
										'text': __('Edit %1$s', 'easy-language').replace('%1$s', data.object_type_name)
									},
									{
										'action': 'location.reload();',
										'text': __('Cancel', 'easy-language')
									}
								]
							}
						}
					}
					else {
						dialog_config = {
							detail: {
								/* translators: %1$s will be replaced by the object type name (e.g. page or post) */
								title: __('%1$s ready for simplification', 'easy-language').replace('%1$s', data.object_type_name),
								texts: [
									/* translators: %1$s and %4$s will be replaced by the object type name (e.g. page or post), %2$s will be replaced by the object-title, %3$s will be replaced by the language-name */
									__('<p>The %1$s <i>%2$s</i> has been created in %3$s.<br>Its texts are not yet simplified.</p>', 'easy-language').replace('%1$s', data.object_type_name).replace('%2$s', data.title).replace('%3$s', data.language).replace('%4$s', data.object_type_name)
								],
								buttons: [
									{
										'action': 'location.href="' + data.edit_link + '";',
										'variant': 'primary',
										/* translators: %1$s will be replaced by the object type name (e.g. page or post) */
										'text': __('Edit %1$s', 'easy-language').replace('%1$s', data.object_type_name)
									},
									{
										'action': 'location.reload();',
										'text': __('Cancel', 'easy-language')
									}
								]
							}
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
	// get internationalization tools of WordPress.
	let { __ } = wp.i18n;

	let dialog_config = {
		detail: {
			title: __( 'Simplify texts', 'easy-language' ),
			texts: [
				'<p>' + __( '<strong>Are you sure you want to simplify these texts via API?</strong><br>Hint: this could cause costs with the API.', 'easy-language' ) + '</p>'
			],
			buttons: [
				{
					'action': 'easy_language_get_simplification( ' + id + ' );',
					'variant': 'primary',
					'text': __( 'Yes', 'easy-language' )
				},
				{
					'action': 'closeDialog();',
					'variant': 'secondary',
					'text': __( 'No', 'easy-language' )
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
 */
function easy_language_get_simplification( object_id ) {
	// get internationalization tools of WordPress.
	let { __ } = wp.i18n;

	// create dialog.
	let dialog_config = {
		detail: {
			title: __('Simplification in progress', 'easy-language' ),
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
	// get internationalization tools of WordPress.
	let { __ } = wp.i18n;

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
							title: __( 'Error', 'easy-language' ),
							texts: [
								// TODO Pro-URL ermitteln
								/* translators: [error] will be replaced by the http-error-message (e.g. "Gateway timed our"), %1$s will be replaced with the Pro-URL */
								__( '<p><strong>The following error occurred during: [error]</strong> - at least one simplification could not be processed.<br><br><strong>Possible causes:</strong></p><ul><li>The server settings for WordPress hosting prevent longer lasting requests. Contact your hosters support for a solution.</li><li>The API used took too long to simplify the text. Shorten particularly long texts or contact API support for further assistance.</li><li>Use <a href="%1$s" target="_blank">Easy Language Pro</a> for automatic translations in the background without the risk of hitting timeouts on your own hosting.</li></ul>', 'easy-language' ).replace('[error]', e.statusText)
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
				let dialog_config   = data[3];

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
					// create dialog based on return.
					easy_language_create_dialog( { detail: dialog_config } );
				}
			}
		}
	);
}

/**
 * Prevent simplification of object and optionally forward user to given link or run command after it.
 *
 * @param object_id
 * @param prevent_automatic_simplification
 * @param link
 */
function easy_language_prevent_automatic_simplification( object_id, prevent_automatic_simplification, link, command ) {
	jQuery.ajax(
		{
			type: "POST",
			url: easyLanguageSimplificationJsVars.ajax_url,
			data: {
				'action': 'easy_language_set_simplification_prevention_on_object',
				'post': object_id,
				'prevent_automatic_simplification': prevent_automatic_simplification,
				'nonce': easyLanguageSimplificationJsVars.set_simplification_prevention_nonce
			},
			success: function() {
				if( link ) {
					location.href = link;
				}
				else if( command ) {
					eval(command);
				}
			}
		}
	);
}
