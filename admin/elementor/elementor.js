window.addEventListener(
	'elementor/init',
	() => {
    let view = elementor.modules.controls.BaseData.extend(
			{
				onReady: function () {
					this.$el.find( '.easy-dialog-for-wordpress' ).on(
                        'click',
					function (e) {
						e.preventDefault();
						easy_language_create_dialog({ detail: JSON.parse(this.dataset.dialog) });
					}
					);
				}
			}
	);
	elementor.addControlView( 'easy_languages', view );
	}
);

/**
 * Helper to create a new dialog with given config.
 *
 * @param config
 */
function easy_language_create_dialog( config ) {
	document.body.dispatchEvent(new CustomEvent("easy-dialog-for-wordpress", config));
}
