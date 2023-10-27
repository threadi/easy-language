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
						hide_title: true,
						texts: [
							'<p><strong>' + easyLanguagePluginJsVars.delete_confirmation_question + '</strong></p>'
						],
						buttons: [
							{
								'action': 'location.href="' + $(this).attr('href') + '";',
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
		$( 'a.easy-language-missing-pagebuilder-warning' ).on( 'click', function() {
			// create dialog.
			let dialog_config = {
				detail: {
					hide_title: true,
					texts: [
						'<p><strong>' + easyLanguagePluginJsVars.txt_pagebuilder_unknown_warnung + '</strong></p>'
					],
					buttons: [
						{
							'action': 'location.href="' + $(this).attr('href') + '";',
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
		});
	}
);
