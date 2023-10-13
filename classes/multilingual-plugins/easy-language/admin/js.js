jQuery( document ).ready(
	function ($) {
		// start to translate an object via AJAX.
		$( '.easy-language-translate-object' ).on(
			'click',
			function (e) {
				e.preventDefault();

				if( confirm( easyLanguagePluginJsVars.translate_confirmation_question ) ) {
					easy_language_get_simplification($(this).data('id'), $(this).data('link'));
				}
			}
		);

		// confirm deletion of translated object.
		$( '.easy-language-trash' ).on(
			'click',
			function () {
				return confirm( easyLanguagePluginJsVars.delete_confirmation_question );
			}
		);

		$('body.easy-language-intro-step-2 .menu-icon-page .wp-menu-name').pointer({
			content: easyLanguagePluginJsVars.intro_step_2,
			position: {
				edge:  'left',
				align: 'left'
			},
			pointerClass: 'easy-language-pointer',
			close: function() {
				// save via ajax the dismission of this hint.
				let data = {
					'action': 'easy_language_dismiss_intro_step_2',
					'nonce': easyLanguagePluginJsVars.dismiss_intro_nonce
				};

				// run ajax request to save this setting
				$.post(easyLanguagePluginJsVars.ajax_url, data);
			}
		}).pointer('open');
	}
);
