/* @jsxRuntime classic */
/**
 * Register the modules of Easy Language in the visual builder of Divi 5.
 *
 * The metadata of the modules (settings) are generated in PHP from the field definitions of the switcher and passed
 * via the global object "EasyLanguageDivi5SwitcherData". The preview is rendered by PHP via the REST API.
 */

// External dependencies (globals of the Divi visual builder, see webpack.config.js).
/* eslint-disable import/no-extraneous-dependencies, import/no-unresolved */
import React, { useEffect, useRef, useState } from 'react';

// WordPress dependencies.
import { addAction, addFilter } from '@wordpress/hooks';

// Divi dependencies.
import { registerModule } from '@divi/module-library';
import { ModuleContainer, StyleContainer, CssStyle } from '@divi/module';
/* eslint-enable import/no-extraneous-dependencies, import/no-unresolved */

// Local dependencies.
import { switcherIcon } from './icon';

/**
 * The data from PHP.
 */
const data = window.EasyLanguageDivi5SwitcherData || {};

/**
 * Return the values of the switcher from the module attributes.
 *
 * @param {Object} attrs The module attributes.
 * @return {Object} The values (field name => value).
 */
const getValues = ( attrs ) =>
	attrs?.switcher?.advanced?.settings?.desktop?.value ?? {};

/**
 * Return the value of a field including its default.
 *
 * @param {Object} attrs     The module attributes.
 * @param {string} fieldName The name of the field.
 * @return {*} The value.
 */
const getValue = ( attrs, fieldName ) => {
	const value = getValues( attrs )[ fieldName ];
	if ( undefined === value || null === value ) {
		return data.metadata?.attributes?.switcher?.default?.advanced?.settings
			?.desktop?.value?.[ fieldName ];
	}
	return value;
};

/**
 * Styles of the module (design settings of Divi like spacing or background).
 *
 * @param {Object}  props            React component props.
 * @param {Object}  props.attrs      The module attributes.
 * @param {Object}  props.settings   The module settings.
 * @param {string}  props.orderClass The order class of the module.
 * @param {string}  props.mode       The style mode.
 * @param {string}  props.state      The attribute state.
 * @param {boolean} props.noStyleTag True to output the styles without style tag.
 * @param {Object}  props.elements   The module elements.
 * @return {Object} The style components.
 */
const SwitcherStyles = ( {
	attrs,
	settings,
	orderClass,
	mode,
	state,
	noStyleTag,
	elements,
} ) => (
	<StyleContainer mode={ mode } state={ state } noStyleTag={ noStyleTag }>
		{ elements.style( {
			attrName: 'module',
			styleProps: {
				disabledOn: {
					disabledModuleVisibility:
						settings?.disabledModuleVisibility,
				},
			},
		} ) }
		<CssStyle
			selector={ orderClass }
			attr={ attrs?.css }
			cssFields={ elements?.moduleMetadata?.customCssFields }
		/>
	</StyleContainer>
);

/**
 * Edit component of the language switcher module: shows the output of the switcher, rendered by PHP.
 *
 * @param {Object} props React component props.
 * @return {Object} The module.
 */
const SwitcherEdit = ( props ) => {
	const { attrs, elements, id, name, isLooped, loopIndex } = props;
	const [ html, setHtml ] = useState( null );
	const values = JSON.stringify( getValues( attrs ) );
	const abort = useRef( null );

	// load the preview if the values have been changed.
	useEffect( () => {
		if ( abort.current ) {
			abort.current.abort();
		}
		const controller = new AbortController();
		abort.current = controller;
		const timer = setTimeout( () => {
			window
				.fetch( data.previewUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': data.nonce,
					},
					body: JSON.stringify( {
						post_id: data.postId,
						attrs: { switcher: attrs?.switcher ?? {} },
					} ),
					signal: controller.signal,
				} )
				.then( ( response ) => response.json() )
				.then( ( result ) => setHtml( result?.html ?? '' ) )
				.catch( () => {} );
		}, 300 );
		return () => {
			clearTimeout( timer );
			controller.abort();
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ values ] );

	return (
		<ModuleContainer
			attrs={ attrs }
			elements={ elements }
			id={ id }
			name={ name }
			stylesComponent={ SwitcherStyles }
			isLooped={ isLooped }
			loopIndex={ loopIndex }
		>
			{ elements.styleComponents( { attrName: 'module' } ) }
			{ null === html ? (
				<div className="easy-language-switcher-loading">
					{ data.loading }
				</div>
			) : (
				<div
					className="easy-language-switcher-preview"
					dangerouslySetInnerHTML={ { __html: html } }
				/>
			) }
		</ModuleContainer>
	);
};

/**
 * Return the callbacks for the visibility of fields with conditions.
 *
 * @return {Object} The callbacks per panel and group.
 */
const getCallbacks = () => {
	const callbacks = {};
	Object.entries( data.conditions || {} ).forEach(
		( [ fieldName, settings ] ) => {
			const { panel, groupSlug, condition } = settings;
			callbacks[ panel ] = callbacks[ panel ] || {};
			callbacks[ panel ][ groupSlug ] = callbacks[ panel ][
				groupSlug
			] || { fields: {} };
			callbacks[ panel ][ groupSlug ].fields[ fieldName ] = {
				visible: ( { attrs } ) =>
					Object.entries( condition ).every( ( [ field, allowed ] ) =>
						allowed.includes(
							String( getValue( attrs, field ) ?? '' )
						)
					),
			};
		}
	);
	return callbacks;
};

// add the icon of the module.
addFilter( 'divi.iconLibrary.icon.map', 'easyLanguage', ( icons ) => ( {
	...icons,
	[ switcherIcon.name ]: switcherIcon,
} ) );

// register the module.
addAction(
	'divi.moduleLibrary.registerModuleLibraryStore.after',
	'easyLanguage',
	() => {
		if ( ! data.metadata ) {
			return;
		}
		registerModule( data.metadata, {
			renderers: {
				edit: SwitcherEdit,
			},
			callbacks: getCallbacks(),
		} );
	}
);
