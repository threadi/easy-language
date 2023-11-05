window.addEventListener(
	'elementor/init',
	() => {
    let view = elementor.modules.controls.BaseData.extend(
			{
				onReady: function () {
					this.$el.find( '.easy-language-translate-object' ).on(
                        'click',
					function (e) {
						e.preventDefault();

						// TODO object-type im HTML ergänzen
						easy_language_simplification_init( jQuery( this ).data( 'id' ), jQuery( this ).data( 'object-type' ) );
					}
					);
				}
			}
	);
	elementor.addControlView( 'easy_languages', view );
	}
);
