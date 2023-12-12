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
	document.body.dispatchEvent(new CustomEvent("react-dialog", dialog_config ));

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
			url: easyLanguageDiviSimplificationJsVars.ajax_url,
			data: {
				'action': 'easy_language_run_simplification',
				'id': obj_id,
				'type': type,
				'initialization': initialization,
				'nonce': easyLanguageDiviSimplificationJsVars.run_simplification_nonce
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
				document.body.dispatchEvent(new CustomEvent("react-dialog", dialog_config ));
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
					document.body.dispatchEvent(new CustomEvent("react-dialog", { detail: dialog_config } ));
				}
			}
		}
	);
}
