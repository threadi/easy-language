/**
 * Language switcher element for the page builder Brizy.
 *
 * The element is registered as Brizy "third party component" via the documented "thirdPartyComponents" config
 * of the Brizy builder (the same way as the package @brizy/core does it).
 *
 * This script runs in two environments:
 * - in the Brizy editor (browser window): here the settings of the element are available via
 *   window.easyLanguageBrizy (added by PHP, see easyLanguage\PageBuilder\Brizy::add_editor_data()).
 * - in the Brizy compiler (a web worker, which creates the HTML for the frontend): here only the
 *   view-component is used and no data from PHP is available.
 *
 * The element does not create the switcher itself: it outputs the Brizy placeholder "{{easy_language_switcher ...}}"
 * with all its values. Brizy replaces it in frontend and in the editor preview via PHP with the output of the
 * central switcher (easyLanguage\EasyLanguage\Switcher::render_widget()).
 *
 * The settings are defined in PHP: easyLanguage\EasyLanguage\Switcher::get_widget_fields().
 */

/**
 * The global object (window in editor, self in the web worker of the compiler).
 */
const root = globalThis;

/**
 * The ID of our element in Brizy.
 */
const ELEMENT_ID = 'EasyLanguageSwitcher';

/**
 * Prefix for our values in the element model of Brizy.
 */
const PREFIX = 'el_';

/**
 * The name of the Brizy placeholder, which is replaced by PHP.
 */
const PLACEHOLDER = 'easy_language_switcher';

/**
 * The CSS class of our wrapper.
 */
const WRAPPER_CLASS = 'easy-language-brizy-switcher';

/**
 * The icon of the element.
 */
const ICON = 'nc-multi-languages';

/**
 * Icons for the toolbar popovers of the sections.
 */
const SECTION_ICONS = {
	settings: 'nc-cog',
	visibility: 'nc-eye-17',
	design: 'nc-toggle',
};

/**
 * Return the data from PHP (only available in the editor).
 *
 * @return {Object} The data.
 */
const getData = () => root.easyLanguageBrizy || {};

/**
 * Return the field definitions as object with the field name as key.
 *
 * @return {Object} The fields.
 */
const getFields = () => {
	const fields = {};
	( getData().fields || [] ).forEach( ( field ) => {
		fields[ field.name ] = field;
	} );
	return fields;
};

/**
 * Return the default color of a color field (Brizy has no empty colors, so the placeholder is used).
 *
 * @param {Object} field The field definition.
 *
 * @return {string} The color as hex code.
 */
const getDefaultColor = ( field ) => {
	const color = String( field.default || field.placeholder || '' );
	return /^#[0-9a-f]{6}$/i.test( color ) ? color.toLowerCase() : '#000000';
};

/**
 * Return the default values of the fields in the Brizy model.
 *
 * Brizy adds the default values of all options to the props of the element in the editor (but not in the compiler).
 *
 * @return {Object} The default values.
 */
const getDefaultValues = () => {
	const values = {};
	const fields = getFields();
	Object.keys( fields ).forEach( ( name ) => {
		const field = fields[ name ];
		switch ( field.type ) {
			case 'select':
			case 'size':
				values[ name ] = String( field.default ?? '' );
				break;
			case 'switch':
				values[ name ] = isTrue( field.default ) ? 'on' : 'off';
				break;
			case 'color':
				values[ name + 'Hex' ] = getDefaultColor( field );
				values[ name + 'Opacity' ] = 1;
				values[ name + 'Palette' ] = '';
				break;
			case 'objects':
				values[ name ] = '[]';
				break;
		}
	} );
	return values;
};

/**
 * Create a React element with the React instance of Brizy.
 *
 * @param {...*} args The arguments for React.createElement().
 *
 * @return {Object} The React element.
 */
const createElement = ( ...args ) => root.React.createElement( ...args );

/**
 * Return whether the given value is true (for switches).
 *
 * @param {*} value The value.
 *
 * @return {boolean} True if the value is true.
 */
const isTrue = ( value ) =>
	true === value ||
	1 === value ||
	[ 'on', 'yes', '1', 'true' ].includes( value );

/**
 * Return our values from the props of the element (all primitive values with our prefix, without the prefix).
 *
 * @param {Object} props The props of the element.
 *
 * @return {Object} The values.
 */
const getValues = ( props ) => {
	const values = {};
	Object.keys( props ).forEach( ( key ) => {
		const value = props[ key ];
		if (
			key.startsWith( PREFIX ) &&
			[ 'string', 'number', 'boolean' ].includes( typeof value )
		) {
			values[ key.substring( PREFIX.length ) ] = value;
		}
	} );
	return values;
};

/**
 * Encode a value for an attribute of the placeholder (it gets decoded by Brizy via urldecode()).
 *
 * @param {*} value The value.
 *
 * @return {string} The encoded value.
 */
const encodeValue = ( value ) =>
	encodeURIComponent( String( value ) ).replace( /'/g, '%27' );

/**
 * Return the placeholder for the given props.
 *
 * @param {Object}  props   The props of the element.
 * @param {boolean} preview True if the placeholder is used for the preview in the editor.
 *
 * @return {string} The placeholder.
 */
const getPlaceholder = ( props, preview ) => {
	const values = getValues( props );

	// remove the default values in the editor, so the preview gets the same values as the output in frontend.
	if ( preview ) {
		const defaults = getDefaultValues();
		Object.keys( defaults ).forEach( ( key ) => {
			if (
				undefined !== values[ key ] &&
				String( values[ key ] ).toLowerCase() ===
					String( defaults[ key ] ).toLowerCase()
			) {
				delete values[ key ];
			}
		} );
	}

	const attributes = Object.keys( values )
		.filter( ( key ) => /^\w+$/.test( key ) )
		.sort()
		.map( ( key ) => key + "='" + encodeValue( values[ key ] ) + "'" );
	if ( preview ) {
		attributes.push( "preview='1'" );
	}
	return (
		'{{' +
		PLACEHOLDER +
		( attributes.length > 0 ? ' ' + attributes.join( ' ' ) : '' ) +
		'}}'
	);
};

/**
 * Render the element with the placeholder.
 *
 * Brizy outputs the placeholder in the compiled HTML (view) and shows an icon in the editor until the placeholder
 * is loaded (Brizy Pro loads it itself via its dynamic content API).
 *
 * @param {Object}  props   The props of the element.
 * @param {boolean} preview True if the element is rendered in the editor.
 *
 * @return {Object} The React element.
 */
const renderElement = ( props, preview ) => {
	const { DynamicContent } = props;
	return createElement(
		'div',
		{ className: WRAPPER_CLASS },
		createElement( DynamicContent, {
			placeholder: getPlaceholder( props, preview ),
			placeholderIcon: ICON,
			tagName: 'div',
			className: WRAPPER_CLASS + '__content',
		} )
	);
};

/**
 * Return whether Brizy loads placeholders in its editor itself (only if its dynamic content API is available).
 *
 * @return {boolean} True if Brizy loads the placeholders.
 */
const hasDynamicContentApi = () => {
	const config = root.__VISUAL_CONFIG__;
	return !! (
		config &&
		config.dynamicContent &&
		'function' === typeof config.dynamicContent.getPlaceholderData
	);
};

/**
 * Load the output of a placeholder via the AJAX action of Brizy, which replaces placeholders for its editor.
 *
 * @param {string}      placeholder The placeholder.
 * @param {AbortSignal} signal      The abort signal.
 *
 * @return {Promise<string>} The HTML.
 */
const fetchPreview = ( placeholder, signal ) => {
	const env = root.__BRZ_PLUGIN_ENV__;
	if (
		! env ||
		! env.url ||
		! env.actions ||
		! env.actions.placeholdersContent
	) {
		return Promise.reject( new Error( 'Missing Brizy configuration' ) );
	}
	const pageId = String( env.pageId || 0 );
	const body = new URLSearchParams( {
		hash: env.hash || '',
		version: env.editorVersion || '',
		action: env.actions.placeholdersContent,
	} );
	body.append( 'p[' + pageId + '][]', placeholder );
	return fetch( env.url, {
		method: 'POST',
		credentials: 'same-origin',
		body,
		signal,
	} )
		.then( ( response ) => response.json() )
		.then( ( result ) => {
			const list =
				result &&
				result.data &&
				result.data.placeholders &&
				result.data.placeholders[ pageId ];
			return Array.isArray( list ) && 'string' === typeof list[ 0 ]
				? list[ 0 ]
				: '';
		} );
};

/**
 * The component for the editor of Brizy.
 *
 * @param {Object} props The props of the element.
 *
 * @return {Object} The React element.
 */
const EditorComponent = ( props ) => {
	// load the preview ourselves if Brizy does not do it (Brizy without Pro has no dynamic content API).
	const useApi = hasDynamicContentApi();
	const placeholder = getPlaceholder( props, true );
	const [ html, setHtml ] = root.React.useState( '' );
	root.React.useEffect( () => {
		if ( useApi ) {
			return undefined;
		}
		const controller = new AbortController();
		const timeout = setTimeout( () => {
			fetchPreview( placeholder, controller.signal )
				.then( ( result ) => setHtml( result ) )
				.catch( () => {} );
		}, 300 );
		return () => {
			clearTimeout( timeout );
			controller.abort();
		};
	}, [ placeholder, useApi ] );

	// show the icon of Brizy as long as there is no output.
	if ( useApi || '' === html ) {
		return renderElement( props, true );
	}

	// show the output (without reacting on clicks, as they would leave the editor).
	return createElement(
		'div',
		{ className: WRAPPER_CLASS },
		createElement( 'div', {
			className: WRAPPER_CLASS + '__content',
			style: { pointerEvents: 'none' },
			dangerouslySetInnerHTML: { __html: html },
		} )
	);
};

/**
 * The component for the output (used by the compiler of Brizy).
 *
 * @param {Object} props The props of the element.
 *
 * @return {Object} The React element.
 */
const ViewComponent = ( props ) => renderElement( props, false );

/**
 * Return the objects from our REST API for the visibility settings.
 *
 * @param {Object}      params The parameters ("search" or "include").
 * @param {AbortSignal} signal The abort signal.
 *
 * @return {Promise<Array>} The choices.
 */
const fetchObjects = ( params, signal ) => {
	const data = getData();
	if ( ! data.restUrl ) {
		return Promise.resolve( [] );
	}
	const query = new URLSearchParams( { ...params, _wpnonce: data.nonce } );
	const url =
		data.restUrl + ( data.restUrl.includes( '?' ) ? '&' : '?' ) + query;
	return fetch( url, { credentials: 'same-origin', signal } )
		.then( ( response ) => response.json() )
		.then( ( objects ) =>
			Array.isArray( objects )
				? objects.map( ( object ) => ( {
						title: object.label,
						value: object.value,
					} ) )
				: []
		);
};

/**
 * Return whether the condition of a field matches the actual values.
 *
 * @param {Object}   field    The field definition.
 * @param {Object}   fields   All field definitions.
 * @param {Function} getValue Function to get the actual value of a field.
 *
 * @return {boolean} True if the field should be visible.
 */
const matchesCondition = ( field, fields, getValue ) =>
	Object.entries( field.condition || {} ).every( ( [ name, allowed ] ) => {
		const value = getValue( name );
		const list = Array.isArray( allowed ) ? allowed : [ allowed ];
		return list.some( ( entry ) =>
			fields[ name ] && 'switch' === fields[ name ].type
				? isTrue( entry ) === isTrue( value )
				: String( entry ) === String( value )
		);
	} );

/**
 * Return the Brizy option for a field.
 *
 * @param {Object}  field    The field definition.
 * @param {boolean} disabled True if the option should be hidden.
 *
 * @return {Object|null} The option or null if the type is not supported.
 */
const getOption = ( field, disabled ) => {
	const option = {
		id: PREFIX + field.name,
		label: field.label,
		devices: 'desktop',
		disabled,
	};
	if ( field.description ) {
		option.helper = { content: field.description };
	}

	switch ( field.type ) {
		case 'select':
			return {
				...option,
				type: 'select',
				default: String( field.default ?? '' ),
				choices: field.choices || [],
			};
		case 'switch':
			return {
				...option,
				type: 'switch',
				default: isTrue( field.default ) ? 'on' : 'off',
			};
		case 'size':
			return {
				...option,
				type: 'inputText',
				default: String( field.default ?? '' ),
				placeholder:
					field.placeholder ||
					( field.units || [ 'px' ] ).join( ', ' ),
			};
		case 'color':
			return {
				...option,
				type: 'colorPicker',
				default: { hex: getDefaultColor( field ), opacity: 1 },
			};
		case 'objects':
			delete option.helper;
			return {
				...option,
				type: 'multiSelect',
				placeholder: field.description || '',
				config: { search: true, fetchOnMount: true },
				choices: {
					load: ( values, signal ) =>
						Array.isArray( values ) && values.length > 0
							? fetchObjects(
									{ include: values.join( ',' ) },
									signal
								)
							: fetchObjects( { search: '' }, signal ),
					search: ( search, signal ) =>
						fetchObjects( { search }, signal ),
				},
			};
		default:
			return null;
	}
};

/**
 * Return the toolbar configuration of the element.
 *
 * @param {Object}   props          The toolbar props from Brizy.
 * @param {Function} props.getValue Function to get a value of the element.
 *
 * @return {Array} The configuration.
 */
const getOptions = ( { getValue } ) => {
	const data = getData();
	const fields = getFields();
	const sections = data.sections || {};

	// return the value of a field (Brizy returns the model of the option, e.g. { value: "toggle" }) or its default.
	const getFieldValue = ( name ) => {
		let value = getValue( PREFIX + name );
		if ( value && 'object' === typeof value && 'value' in value ) {
			value = value.value;
		}
		return undefined === value && fields[ name ]
			? fields[ name ].default
			: value;
	};

	// collect the options per section.
	const items = {};
	Object.keys( fields ).forEach( ( name ) => {
		const field = fields[ name ];
		const option = getOption(
			field,
			! matchesCondition( field, fields, getFieldValue )
		);
		if ( ! option ) {
			return;
		}
		const section = field.section || 'settings';
		items[ section ] = items[ section ] || [];

		// show colors in tabs (the color picker of Brizy has no visible label).
		if ( 'colorPicker' === option.type ) {
			let tabs = items[ section ].find(
				( entry ) => 'easyLanguageColors_' + section === entry.id
			);
			if ( ! tabs ) {
				tabs = {
					id: 'easyLanguageColors_' + section,
					type: 'tabs',
					disabled: true,
					tabs: [],
				};
				items[ section ].push( tabs );
			}
			if ( ! option.disabled ) {
				tabs.disabled = false;
				tabs.tabs.push( {
					id: 'easyLanguageTab_' + field.name,
					label: option.label,
					options: [ option ],
				} );
			}
			return;
		}

		items[ section ].push( option );
	} );

	// create a popover for each section.
	const toolbar = Object.keys( items ).map( ( section, index ) => ( {
		id: 'easyLanguagePopover_' + section,
		type: 'popover',
		position: 10 * ( index + 1 ),
		devices: 'desktop',
		disabled: items[ section ].every( ( option ) => option.disabled ),
		config: {
			icon: SECTION_ICONS[ section ] || 'nc-cog',
			title: sections[ section ] || section,
			size: 'large',
		},
		options: items[ section ],
	} ) );

	return [ { selector: '.' + WRAPPER_CLASS, toolbar } ];
};

/**
 * Register the element in Brizy.
 *
 * @return {void}
 */
const register = () => {
	const config = root.__VISUAL_CONFIG__;
	if ( ! config || 'object' !== typeof config ) {
		return;
	}

	// add the component.
	const data = getData();
	const category = data.category || 'Easy Language';
	config.thirdPartyComponents = config.thirdPartyComponents || {};
	config.thirdPartyComponents[ ELEMENT_ID ] = {
		id: ELEMENT_ID,
		title: data.title || 'Language Switcher',
		category,
		icon: ICON,
		keywords: data.keywords || '',
		component: { editor: EditorComponent, view: ViewComponent },
		options: getOptions,
	};

	// add it to the list of elements in the left sidebar (only exists in the editor).
	const tabs =
		config.ui &&
		config.ui.leftSidebar &&
		Array.isArray( config.ui.leftSidebar.topTabsOrder )
			? config.ui.leftSidebar.topTabsOrder
			: [];
	const tab = tabs.find( ( entry ) => entry && 'addElements' === entry.id );
	if ( ! tab ) {
		return;
	}
	tab.elements = Array.isArray( tab.elements ) ? tab.elements : [];
	const group = tab.elements.find( ( entry ) => entry.label === category );
	if ( group ) {
		if ( ! group.moduleNames.includes( ELEMENT_ID ) ) {
			group.moduleNames.push( ELEMENT_ID );
		}
	} else {
		tab.elements.push( { label: category, moduleNames: [ ELEMENT_ID ] } );
	}
};

register();
