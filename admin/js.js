jQuery( document ).ready(
	function ($) {

		// get internationalization tools of WordPress.
		let { __ } = wp.i18n;

		$('body.settings_page_easy_language_settings .wrap > h1').each(function() {
			let button = document.createElement('a');
			button.className = 'review-hint-button page-title-action';
			button.href = easyLanguageJsVars.review_url;
			button.innerHTML = easyLanguageJsVars.title_rate_us;
			button.target = '_blank';
			this.after(button);
		})

		// reset intro via ajax.
		$('body.settings_page_easy_language_settings a.easy-language-reset-intro').on('click', function(e) {
			e.preventDefault();

			// send request for reset via ajax.
			jQuery.ajax(
				{
					type: "POST",
					url: easyLanguageJsVars.ajax_url,
					data: {
						'action': 'easy_language_reset_intro',
						'nonce': easyLanguageJsVars.reset_intro_nonce
					},
					success: function ( data ) {
						if( data.result === 'ok' ) {
							// create dialog.
							let dialog_config = {
								detail: {
									title: __( 'Intro reset', 'easy-language' ),
									texts: [
										'<p>' + __( '<p><strong>Intro has been reset.</strong> You can now start again to configure the plugin.<br><strong>Hint:</strong> No configuration and data has been changed.</p>', 'easy-language' ) + '</p>'
									],
									buttons: [
										{
											'action': 'location.href = "' + easyLanguageJsVars.admin_start + '";',
											'variant': 'primary',
											'text': __( 'OK', 'easy-language' )
										}
									]
								}
							}
							easy_language_create_dialog( dialog_config );
						}
					}
				}
			);
		});

		/**
		 * Image handling: choose new icon vor language.
		 */
		$('body.settings_page_easy_language_settings a.replace-icon').on( 'click',function(e){
			e.preventDefault();
			let obj = $(this);
			let custom_uploader = wp.media({
				title: __( 'Choose new icon', 'easy-language' ),
				library : {
					type : 'image'
				},
				button: {
					text: __( 'Use this icon', 'easy-language' )
				},
				multiple: false
			}).on('select', function() { // it also has "open" and "close" events
				// get attachment-data.
				let attachment = custom_uploader.state().get('selection').first().toJSON();

				// save new attachment for language via ajax.
				// start data deletion.
				jQuery.ajax(
					{
						type: "POST",
						url: easyLanguageJsVars.ajax_url,
						data: {
							'action': 'easy_language_set_icon_for_language',
							'icon': attachment.id,
							'language': obj.parent().find('span').data( 'language-code' ),
							'nonce': easyLanguageJsVars.set_icon_for_language_nonce
						},
						success: function( dialog_config ) {
							// replace img in list.
							obj.parent().find('img').attr( 'src', attachment.url );

							// show success message.
							easy_language_create_dialog( dialog_config );
						}
					}
				);

			}).open();
		});

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
		if (jQuery.fn.pointer) {
			$('body.easy-language-intro-step-2 .menu-icon-page .wp-menu-name').pointer(
				{
					content: easyLanguageJsVars.intro_step_2,
					position: {
						edge: 'left',
						align: 'left'
					},
					pointerClass: 'easy-language-pointer',
					close: function () {
						// save via ajax the hiding of this hint.
						let data = {
							'action': 'easy_language_dismiss_intro_step_2',
							'nonce': easyLanguageJsVars.dismiss_intro_nonce
						};

						// run ajax request to save this setting
						$.post(easyLanguageJsVars.ajax_url, data);
					}
				}
			).pointer('open');
		}

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
 * Delete all simplified texts via AJAX incl. progressbar.
 */
function easy_language_start_data_deletion() {
	// get internationalization tools of WordPress.
	let { __ } = wp.i18n;

	// create dialog.
	let dialog_config = {
		detail: {
			title: __( 'Deletion of simplified texts', 'easy-language' ),
			progressbar: {
				active: true,
				progress: 0,
				id: 'progress_delete_simplifications'
			}
		}
	}
	easy_language_create_dialog( dialog_config );

	// start deletion.
	jQuery.ajax(
		{
			type: "POST",
			url: easyLanguageJsVars.ajax_url,
			data: {
				'action': 'easy_language_run_data_deletion',
				'nonce': easyLanguageJsVars.run_delete_data_nonce
			},
			success: function( data ) {
				easy_language_get_data_deletion_info();
			}
		}
	);
}

/**
 * Get import info until deletion is done.
 */
function easy_language_get_data_deletion_info() {
	// get internationalization tools of WordPress.
	let { __ } = wp.i18n;

	// run info check.
	jQuery.ajax(
		{
			type: "POST",
			url: easyLanguageJsVars.ajax_url,
			data: {
				'action': 'easy_language_get_info_delete_data',
				'nonce': easyLanguageJsVars.get_delete_data_nonce
			},
			success: function (data) {
				let stepData = data.split( ";" );
				let count    = parseInt( stepData[0] );
				let max      = parseInt( stepData[1] );
				let running  = parseInt( stepData[2] );

				// update progressbar.
				jQuery("#progress_delete_simplifications").attr('value', (count / max) * 100);

				// get next info until running is not 1.
				if ( running >= 1 ) {
					setTimeout(
						function () {
							easy_language_get_data_deletion_info() },
						500
					);
				} else {
					// create dialog.
					let dialog_config = {
						detail: {
							title: __( 'Deletion of simplified texts', 'easy-language' ),
							texts: [
								__( '<p><strong>Deletion of simplified texts done.</strong><br>You can now start with simplifications.</p>', 'easy-language' )
							],
							buttons: [
								{
									'action': 'closeDialog();',
									'variant': 'primary',
									'text': 'OK',
								}
							]
						}
					}
					easy_language_create_dialog( dialog_config );
				}
			}
		}
	)
}


/**
 * Helper to create a new dialog with given config.
 *
 * @param config
 */
function easy_language_create_dialog( config ) {
	document.body.dispatchEvent(new CustomEvent("easy-dialog-for-wordpress", config));
}
