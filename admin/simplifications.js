jQuery( document ).ready(
	function ($) {
		/**
		 * Start to translate an object via AJAX.
		 */
		$('.easy-language-translate-object').on(
			'click',
			function (e) {
				e.preventDefault();
				easy_language_simplification_init( $(this).data('id'), $(this).data('object-type') );
			}
		);

		/**
		 * Load the debug information for the given object.
		 */
		$('.easy-language-debug-object').on( 'click', function(e) {
			e.preventDefault();
			$.ajax(
				{
					type: "POST",
					url: easyLanguageSimplificationJsVars.ajax_url,
					data: {
						'action': 'easy_language_get_debug_info',
						'config': $(this).data('debug-config'),
						'nonce': easyLanguageSimplificationJsVars.debug_info_nonce
					},
					success: function( result ) {
						easy_language_create_dialog( result )
					}
				}
			);
		});

		// save automatic prevention setting.
		$('input.easy-language-automatic-simplification-prevention').on( 'change', function() {
			easy_language_prevent_automatic_simplification( $(this).data('id'), $(this).data('object-type'), $(this).is(':checked'), null );
		});
	}
);

/**
 * Send request to reset the simplification process of given object.
 *
 * @param object_id
 * @param type
 */
function easy_language_reset_processing_simplification( object_id, type ) {
	jQuery.ajax(
		{
			type: "POST",
			url: easyLanguageSimplificationJsVars.ajax_url,
			data: {
				'action': 'easy_language_reset_processing_simplification',
				'post': object_id,
				'type': type,
				'nonce': easyLanguageSimplificationJsVars.reset_processing_simplification_nonce
			},
			success: function() {
				easy_language_simplification_init( object_id, type );
			}
		}
	);
}

/**
 * Send request to ignore the failed simplification of a given object.
 *
 * @param object_id
 * @param type
 */
function easy_language_ignore_processing_simplification( object_id, type ) {
	jQuery.ajax(
		{
			type: "POST",
			url: easyLanguageSimplificationJsVars.ajax_url,
			data: {
				'action': 'easy_language_ignore_processing_simplification',
				'id': object_id,
				'type': type,
				'nonce': easyLanguageSimplificationJsVars.ignore_processing_simplification_nonce
			},
			success: function() {
				easy_language_simplification_init( object_id, type );
			}
		}
	);
}

/**
 * Create simplified object via AJAX with or without automatic simplification.
 *
 * @param object_id
 * @param type
 * @param language
 * @param simplification_mode
 * @param api_configured
 */
function easy_language_add_simplification_object( object_id, type, language, simplification_mode, api_configured ) {
	// get internationalization tools of WordPress.
	let { __ } = wp.i18n;

	jQuery.ajax(
		{
			type: "POST",
			url: easyLanguageSimplificationJsVars.ajax_url,
			data: {
				'action': 'easy_language_add_simplification_object',
				'id': object_id,
				'type': type,
				'language': language,
				'nonce': easyLanguageSimplificationJsVars.add_simplification_nonce
			},
			success: function(data) {
				if( 'ok' === data.status && "auto" === simplification_mode && "ok" === data.quota_state.status ) {
					easy_language_get_simplification( data.object_id, data.object_type, false );
				}
				else if( 'ok' === data.status && "auto" === simplification_mode && "above_entry_limit" === data.quota_state.status ) {
					let dialog_config = {
						detail: {
							/* translators: %1$s will be replaced by the object type name (e.g. page or post) */
							title: __('%1$s ready for simplification', 'easy-language').replace('%1$s', data.object_type_name),
							texts: [
								/* translators: %1$s will be replaced by the object type name (e.g. page or post), %2$s will be replaced by the API-title, %3$s will be replaced by the object name */
								'<p>' + __( 'To many text-widgets in this %1$s for adhoc simplification with %2$s.<br>The texts of this %3$s will be simplified in background automatically.<br>Check the list of texts to simplify for the progress.', 'easy-language' ).replace("%1$s", data.object_type_name).replace("%2$s", data.api_title).replace("%3$s", data.object_type_name) + '</p>'
							],
							buttons: [
								{
									'action': 'location.href="' + data.simplification_list_link + '";',
									'variant': 'primary',
									'text': __('Go to simplify list', 'easy-language')
								},
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
					easy_language_create_dialog( dialog_config );
				}
				else if( 'ok' === data.status && "auto" === simplification_mode && "above_text_limit" === data.quota_state.status ) {
					let dialog_config = {
						detail: {
							/* translators: %1$s will be replaced by the object type name (e.g. page or post) */
							title: __('%1$s ready for simplification', 'easy-language').replace('%1$s', data.object_type_name),
							texts: [
								/* translators: %1$s will be replaced by the object type name (e.g. page or post), %2$s will be replaced by the API-title */
								'<p>' + __( 'To large texts in this %1$s for simplification with %2$s.', 'easy-language' ).replace("%1$s", data.object_type_name).replace("%2$s", data.api_title) + '</p>'
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
					easy_language_create_dialog( dialog_config );
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
										'action': 'easy_language_get_simplification(' + data.object_id + ', "' + data.object_type + '" );',
										'variant': 'primary',
										/* translators: %1$s will be replaced by the API-title */
										'text': __('Simplify now via API %1$s', 'easy-language').replace('%1$s', data.api_title)
									},
									{
										'action': 'easy_language_prevent_automatic_simplification(' + data.object_id + ', "' + data.object_type + '", true, "' + data.edit_link + '" );',
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
								title: __('%1$s is ready for simplification', 'easy-language').replace('%1$s', data.object_type_name),
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
 * @param type The type of the object to simplify.
 */
function easy_language_simplification_init( id, type ) {
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
					'action': 'easy_language_get_simplification( ' + id + ', "' + type + '" );',
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
 * @param type
 */
function easy_language_get_simplification( object_id, type ) {
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
	easy_language_get_simplification_info( object_id, type, true );
}

/**
 * Get import info until import is done.
 *
 * @param obj_id
 * @param type
 * @param initialization
 */
function easy_language_get_simplification_info( obj_id, type, initialization ) {
	// get internationalization tools of WordPress.
	let { __ } = wp.i18n;

	jQuery.ajax(
		{
			type: "POST",
			url: easyLanguageSimplificationJsVars.ajax_url,
			data: {
				'action': 'easy_language_run_simplification',
				'id': obj_id,
				'type': type,
				'initialization': initialization,
				'nonce': easyLanguageSimplificationJsVars.run_simplification_nonce
			},
			error: function(e) {
				let dialog_config = {};
				if( 200 !== e.status ) {
					// create dialog with error-message.
					dialog_config = {
						detail: {
							title: __('Error', 'easy-language'),
							texts: [
								/* translators: [error] will be replaced by the http-error-message (e.g. "Gateway timed our"), %1$s will be replaced with the Pro-URL */
								__('<p><strong>The following error occurred during: [error]</strong> - at least one simplification could not be processed.<br><br><strong>Possible causes:</strong></p><ul><li>The server settings for WordPress hosting prevent longer lasting requests. Contact your hosters support for a solution.</li><li>The API used took too long to simplify the text. Shorten particularly long texts or contact API support for further assistance.</li><li>Use automatic simplifications in the background without the risk of hitting timeouts on your own hosting.</li></ul>', 'easy-language').replace('[error]', e.statusText)
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
				}
				else {
					// create dialog with error-message.
					dialog_config = {
						detail: {
							title: __('Error', 'easy-language'),
							texts: [
								__('<p><strong>Any error occurred during simplification.</strong><br>Please check your error-log for the reason.', 'easy-language')
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
				}
				easy_language_create_dialog( dialog_config );
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
							easy_language_get_simplification_info( obj_id, type, false ) },
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
 * @param type
 * @param prevent_automatic_simplification
 * @param link
 * @param command
 */
function easy_language_prevent_automatic_simplification( object_id, type, prevent_automatic_simplification, link, command ) {
	jQuery.ajax(
		{
			type: "POST",
			url: easyLanguageSimplificationJsVars.ajax_url,
			data: {
				'action': 'easy_language_set_simplification_prevention_on_object',
				'id': object_id,
				'type': type,
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
