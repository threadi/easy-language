/**
 * Create custom field with language-options for the object opened in Divi PageBuilder
 *
 * @source: https://divibooster.com/divi-custom-field-type-multi-checkboxes-with-ids/
 */

// embed react and jQuery
import React, { Component, useEffect } from 'react';
import $ from 'jquery';
import { hydrateRoot } from 'react-dom/client';

function Get_Language_Options() {
	useEffect(() => {
		post('/analytics/event');
	}, []);

	return (
		<div>Hallo</div>
	);
}

/**
 * Define object which represents the field.
 */
class Easy_Language_Language_Options extends Component {
	static slug = 'easy-language-language-options';
	constructor(props) {
		super(props);

		let container = document.getElementById('easy-language-language-options');
		let root = hydrateRoot(container, <Get_Language_Options />);
	}

	render() {
		return (
			<div id="easy-language-language-options" className={`${this.constructor.slug}-wrap et-fb-multiple-checkboxes-wrap`}>
				{ root }
			</div>
		);
	}
}

/**
 * Export field directly.
 */
export default Easy_Language_Language_Options;
$(window).on('et_builder_api_ready', (event, API) => {
	API.registerModalFields([Easy_Language_Language_Options]);
});
