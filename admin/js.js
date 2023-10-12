jQuery(document).ready(function($) {
    // save to hide transient-messages via ajax-request.
    $('div[data-dismissible] button.notice-dismiss').on('click',
        function (event) {
            event.preventDefault();
            let $this = $(this);
            let attr_value, option_name, dismissible_length, data;
            attr_value = $this.closest('div[data-dismissible]').attr('data-dismissible').split('-');

            // Remove the dismissible length from the attribute value and rejoin the array.
            dismissible_length = attr_value.pop();
            option_name = attr_value.join('-');
            data = {
                'action': 'dismiss_admin_notice',
                'option_name': option_name,
                'dismissible_length': dismissible_length,
                'nonce': easyLanguageJsVars.dismiss_nonce
            };

            // run ajax request to save this setting
            $.post(easyLanguageJsVars.ajax_url, data);
            $this.closest('div[data-dismissible]').hide('slow');
        }
    );

	// prevent leaving of posts-form if it has changes.
	$("body.settings_page_easy_language_settings form").dirty({preventLeaving: true});
});
