/**
 * Create custom field with language-options for the object opened in Divi PageBuilder
 *
 * @source: https://divibooster.com/divi-custom-field-type-multi-checkboxes-with-ids/
 */

// embed style.
import './style.scss';

// embed react and jQuery.
import React, { Component, useEffect, useState } from 'react';
import $ from 'jquery';

/**
 * Init scripts for easy-language-handling.
 */
function init_scripts() {
	if( top.document.querySelector('.easy-language-language-options-wrap .easy-dialog-for-wordpress') ) {
		top.document.querySelector('.easy-language-language-options-wrap .easy-dialog-for-wordpress').addEventListener('click', function(e) {
			e.preventDefault();
			document.body.dispatchEvent(new CustomEvent("react-dialog", { detail: JSON.parse(this.dataset.dialog) }));
		});
	}
	else {
		setTimeout(function() {
			init_scripts();
		}, 200)
	}
}

/**
 * Request the language options for this object and show them.
 *
 * @returns {JSX.Element}
 * @constructor
 */
function Get_Language_Options( args ) {
	const [options, setOptions] = useState(null);

	// Function to get the possible language options via AJAX.
	const get_options = (e) => {
		fetch(args.env.rest.endpoints.language_options + '/' + args.object_id, {
			method: "POST",
			headers: {
				"Content-Type": "application/json",
				"X-WP-Nonce": args.env.rest.nonce
			},
			body: JSON.stringify({post: args.object_id}),
		})
		.then((response) => response.json())
		.then((data) => {
			setOptions(data);
		})
	};

	// run request.
	useEffect(() => {
		get_options();

		/**
		 * Set onclick event for button to show dialog before simplifying this object.
		 */
		init_scripts();
	}, []);

	return (
		<div>
			{options &&
				<div dangerouslySetInnerHTML={{__html: options.html}} />
			}
		</div>
	);
}

/**
 * Define object which represents the field.
 */
class Easy_Language_Language_Options extends Component {
	static slug = 'easy-language-language-options';
	constructor(props) {
		super(props);
	}

	render() {
		return (
			<div className={`${this.constructor.slug}-wrap`}>
				<Get_Language_Options object_id={ETBuilderBackendDynamic.postId} env={easyLanguageDiviData} />
			</div>
		);
	}
}

/**
 * Add field via Divi-hook.
 */
$(window).on('et_builder_api_ready', (event, API) => {
	API.registerModalFields([Easy_Language_Language_Options]);
});

