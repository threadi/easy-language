jQuery( document ).ready(
	function ($) {
		// start to translate an object via AJAX.
		$('.easy-language-translate-object > a').on(
			'click',
			function (e) {
				e.preventDefault();
				easy_language_simplification_init(ETBuilderBackendDynamic.postId, ETBuilderBackendDynamic.currentPage.permalink, true);
			}
		);
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
