jQuery( document ).ready(
	function ($) {

		// get internationalization tools of WordPress.
		let { __ } = wp.i18n;

		// confirm deletion of simplified object.
		$( '.easy-language-trash' ).on(
			'click',
			function (e) {
				e.preventDefault();

				// create dialog.
				let dialog_config = {
					detail: {
						/* translators: %1$s will be replaced by the object type name (e.g. page or post) */
						title: __( 'Delete simplified %1$s', 'easy-language' ).replace('%1$s', $(this).data('object-type-name')),
						texts: [
							/* translators: %1$s will be replaced by the object type name (e.g. page or post), %2$s will be replaced by the object-title */
							'<p><strong>' + __( 'Do you really want to delete the simplified %1$s <i>%2$s</i>?', 'easy-language' ).replace('%1$s', $(this).data('object-type-name')).replace('%2$s', $(this).data('title')) + '</strong></p>'
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

		// show pointer where user could edit its contents.
		$( 'body.easy-language-intro-step-2 .menu-icon-page .wp-menu-name' ).pointer(
			{
				content: easyLanguagePluginJsVars.intro_step_2,
				position: {
					edge:  'left',
					align: 'left'
				},
				pointerClass: 'easy-language-pointer',
				close: function () {
					// save via ajax the hiding of this hint.
					let data = {
						'action': 'easy_language_dismiss_intro_step_2',
						'nonce': easyLanguagePluginJsVars.dismiss_intro_nonce
					};

					// run ajax request to save this setting
					$.post( easyLanguagePluginJsVars.ajax_url, data );
				}
			}
		).pointer( 'open' );

		// show warning if the page builder, used for an object, is not available.
		$( 'a.easy-language-missing-pagebuilder-warning' ).off('click').on( 'click', function(e) {
			e.preventDefault();

			// create dialog.
			let dialog_config = {
				detail: {
					title: __( 'Unknown pagebuilder', 'easy-language' ),
					texts: [
						/* translators: %1$s and %3$s will be replaced by the object type name (e.g. page or post), %2$s will be replaced by the object-title */
						'<p>' + __( 'The %1$s <i>%2$s</i> has been created with an unknown pagebuilder or the classic editor.<br><strong>Are you sure you want to create simplified text for this %3$s?</strong>', 'easy-language' ).replace('%1$s', $(this).data('object-type-name')).replace('%2$s', $(this).data('title')).replace('%3$s', $(this).data('object-type-name')) + '</p>'
					],
					buttons: [
						{
							'action': 'easy_language_create_dialog({ "detail": ' + JSON.stringify($(this).data('dialog')) + ' });',
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
		});
	}
);

/**
 * Helper to create a new dialog with given config.
 *
 * @param config
 */
function easy_language_create_dialog( config ) {
	document.body.dispatchEvent(new CustomEvent("wp-easy-dialog", config));
}
