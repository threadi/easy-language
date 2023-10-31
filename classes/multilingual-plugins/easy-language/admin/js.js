jQuery( document ).ready(
	function ($) {
		// confirm deletion of translated object.
		$( '.easy-language-trash' ).on(
			'click',
			function (e) {
				e.preventDefault();

				// create dialog.
				let dialog_config = {
					detail: {
						title: easyLanguagePluginJsVars.title_delete_confirmation.replace('%1$s', $(this).data('object-type-name')),
						texts: [
							'<p><strong>' + easyLanguagePluginJsVars.delete_confirmation_question.replace('%1$s', $(this).data('object-type-name')).replace('%2$s', $(this).data('title')) + '</strong></p>'
						],
						buttons: [
							{
								'action': 'location.href="' + $(this).attr('href') + '";',
								'variant': 'primary',
								'text': easyLanguagePluginJsVars.label_yes
							},
							{
								'action': 'closeDialog();',
								'variant': 'secondary',
								'text': easyLanguagePluginJsVars.label_no
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
					// save via ajax the dismission of this hint.
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
					title: easyLanguagePluginJsVars.title_unknown_pagebuilder,
					texts: [
						'<p>' + easyLanguagePluginJsVars.txt_pagebuilder_unknown_warnung.replace('%1$s', $(this).data('object-type-name')).replace('%2$s', $(this).data('title')).replace('%3$s', $(this).data('object-type-name')) + '</p>'
					],
					buttons: [
						{
							'action': 'easy_language_create_dialog({ "detail": ' + JSON.stringify($(this).data('dialog')) + ' });',
							'variant': 'primary',
							'text': easyLanguagePluginJsVars.label_yes
						},
						{
							'action': 'closeDialog();',
							'variant': 'secondary',
							'text': easyLanguagePluginJsVars.label_no
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
	document.body.dispatchEvent(new CustomEvent("easy-language-dialog", config));
}
