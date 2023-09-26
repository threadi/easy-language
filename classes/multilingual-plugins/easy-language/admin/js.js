jQuery(document).ready(function($) {
    // start to translate an object via AJAX.
    $('.easy-language-translate-object').on('click', function (e) {
        e.preventDefault();

        easy_language_get_translation( $(this).data('id') );
    });

    // confirm deletion of translated object.
    $('.easy-language-trash').on('click', function () {
        return confirm(easyLanguagePluginJsVars.delete_confirmation_question);
    });
});
