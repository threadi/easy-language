window.addEventListener( 'elementor/init', () => {
    let view = elementor.modules.controls.BaseData.extend({
        onReady: function() {
            this.$el.find('.easy-language-translate-object').on('click', function(e) {
                e.preventDefault();

                easy_language_get_translation();
            });
        }
    });

    elementor.addControlView( 'easy_languages', view );
});
