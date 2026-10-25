/**
 * Shared editor component for both language switcher blocks.
 *
 * The attributes are defined in PHP: easyLanguage\EasyLanguage\Switcher::get_block_attributes().
 */

/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-i18n/
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Add individual dependencies.
 */
import {
	Button,
	ComboboxControl,
	PanelBody,
	SelectControl,
	ToggleControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- there is no stable unit control yet.
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import {
	InspectorControls,
	PanelColorSettings,
	useBlockProps,
} from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { useEffect, useRef, useState } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';
import './switcher-editor.scss';

/**
 * Path of our REST API endpoint for the object search.
 */
const objectsPath = '/easy-language/v1/switcher-objects';

/**
 * Return the data from PHP.
 *
 * @return {Object} The data.
 */
const getData = () => window.easyLanguageSwitcher || { languages: {} };

/**
 * Units for sizes.
 */
const sizeUnits = [
	{ value: 'px', label: 'px', default: 24 },
	{ value: 'em', label: 'em', default: 1.5 },
	{ value: 'rem', label: 'rem', default: 1.5 },
];

/**
 * Return the actual display mode (considers the setting before 4.0.0).
 *
 * @param {Object} attributes The block attributes.
 * @return {string} The display mode.
 */
export const getDisplay = ( attributes ) => {
	if ( [ 'text', 'icons', 'toggle' ].includes( attributes.display ) ) {
		return attributes.display;
	}
	return attributes.show_icons ? 'icons' : 'text';
};

/**
 * Picker for pages and terms.
 *
 * @param {Object}   props          The properties.
 * @param {Array}    props.value    List of chosen objects ("post:ID" or "term:ID").
 * @param {Function} props.onChange Callback on change.
 * @return {Element} The picker.
 */
const ObjectPicker = ( { value, onChange } ) => {
	const [ labels, setLabels ] = useState( {} );
	const [ options, setOptions ] = useState( [] );
	const requestCounter = useRef( 0 );

	/**
	 * Remember labels of the given items.
	 *
	 * @param {Array} items List of items with value and label.
	 */
	const addLabels = ( items ) => {
		setLabels( ( previous ) => {
			const result = { ...previous };
			items.forEach( ( item ) => {
				result[ item.value ] = item.label;
			} );
			return result;
		} );
	};

	/**
	 * Search for objects.
	 *
	 * @param {string} searchString The search string.
	 */
	const search = ( searchString ) => {
		const request = ++requestCounter.current;
		apiFetch( {
			path: addQueryArgs( objectsPath, { search: searchString } ),
		} )
			.then( ( items ) => {
				addLabels( items );
				// ignore outdated results.
				if ( request === requestCounter.current ) {
					setOptions( items );
				}
			} )
			.catch( () => setOptions( [] ) );
	};
	const debouncedSearch = useDebounce( search, 300 );

	// load the first results.
	useEffect( () => {
		search( '' );
		// eslint-disable-next-line react-hooks/exhaustive-deps -- only on first render.
	}, [] );

	// load the labels of the chosen objects.
	useEffect( () => {
		const missing = value.filter( ( item ) => ! labels[ item ] );
		if ( ! missing.length ) {
			return;
		}
		apiFetch( {
			path: addQueryArgs( objectsPath, { include: missing.join( ',' ) } ),
		} )
			.then( addLabels )
			.catch( () => {} );
		// eslint-disable-next-line react-hooks/exhaustive-deps -- labels are only an internal cache.
	}, [ value ] );

	return (
		<div className="easy-language-object-picker">
			<ComboboxControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Pages and terms', 'easy-language' ) }
				help={ __(
					'Search for pages, posts or terms.',
					'easy-language'
				) }
				value={ null }
				options={ options.filter(
					( option ) => ! value.includes( option.value )
				) }
				onFilterValueChange={ debouncedSearch }
				onChange={ ( newValue ) => {
					if ( newValue && ! value.includes( newValue ) ) {
						onChange( [ ...value, newValue ] );
					}
				} }
			/>
			{ value.length > 0 && (
				<ul className="easy-language-object-picker-selected">
					{ value.map( ( item ) => (
						<li key={ item }>
							<span>{ labels[ item ] || item }</span>
							<Button
								icon="no-alt"
								size="small"
								label={ sprintf(
									/* translators: %1$s will be replaced by the title of the object. */
									__( 'Remove %1$s', 'easy-language' ),
									labels[ item ] || item
								) }
								onClick={ () =>
									onChange(
										value.filter(
											( entry ) => entry !== item
										)
									)
								}
							/>
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
};

/**
 * Create the edit function for the given block.
 *
 * @param {string} blockName The name of the block.
 * @return {Function} The edit function.
 */
export const createEdit = ( blockName ) =>
	function Edit( { attributes, setAttributes } ) {
		const display = getDisplay( attributes );
		const languages = getData().languages || {};
		const visibilityObjects = Array.isArray( attributes.visibility_objects )
			? attributes.visibility_objects
			: [];

		return (
			<div { ...useBlockProps() }>
				<InspectorControls>
					<PanelBody title={ __( 'Settings', 'easy-language' ) }>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Display', 'easy-language' ) }
							value={ display }
							options={ [
								{
									label: __( 'Text', 'easy-language' ),
									value: 'text',
								},
								{
									label: __( 'Icons', 'easy-language' ),
									value: 'icons',
								},
								{
									label: __( 'Toggle', 'easy-language' ),
									value: 'toggle',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( {
									display: value,
									show_icons: 'icons' === value,
								} )
							}
						/>
						{ 'toggle' !== display && (
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'Hide actual language',
									'easy-language'
								) }
								checked={ attributes.hide_actual_language }
								onChange={ ( value ) =>
									setAttributes( {
										hide_actual_language: value,
									} )
								}
							/>
						) }
						{ 'icons' === display && (
							<UnitControl
								__next40pxDefaultSize
								label={ __( 'Icon size', 'easy-language' ) }
								help={ __(
									'Leave empty to use the default size from the plugin settings.',
									'easy-language'
								) }
								value={ attributes.icon_size }
								units={ [
									{ value: 'px', label: 'px', default: 24 },
									{ value: 'em', label: 'em', default: 1.5 },
									{
										value: 'rem',
										label: 'rem',
										default: 1.5,
									},
								] }
								min={ 0 }
								onChange={ ( value ) =>
									setAttributes( {
										icon_size: value || '',
									} )
								}
							/>
						) }
						{ 'toggle' === display && (
							<>
								<SelectControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __(
										'Language of the toggle',
										'easy-language'
									) }
									value={ attributes.toggle_language }
									options={ Object.keys( languages ).map(
										( code ) => ( {
											label: languages[ code ],
											value: code,
										} )
									) }
									onChange={ ( value ) =>
										setAttributes( {
											toggle_language: value,
										} )
									}
								/>
								<ToggleControl
									__nextHasNoMarginBottom
									label={ __(
										'Grey out if no simplification is available',
										'easy-language'
									) }
									help={ __(
										'If disabled, the toggle will be hidden on pages without simplification.',
										'easy-language'
									) }
									checked={ attributes.toggle_disabled }
									onChange={ ( value ) =>
										setAttributes( {
											toggle_disabled: value,
										} )
									}
								/>
								<UnitControl
									__next40pxDefaultSize
									label={ __(
										'Size of the toggle',
										'easy-language'
									) }
									help={ __(
										'Height of the toggle, the width is calculated. Leave empty for the default size.',
										'easy-language'
									) }
									value={ attributes.toggle_size }
									units={ sizeUnits }
									min={ 0 }
									onChange={ ( value ) =>
										setAttributes( {
											toggle_size: value || '',
										} )
									}
								/>
								<UnitControl
									__next40pxDefaultSize
									label={ __(
										'Rounding of the toggle',
										'easy-language'
									) }
									help={ __(
										'Leave empty for a round toggle, use 0 for a squared toggle.',
										'easy-language'
									) }
									value={ attributes.toggle_radius }
									units={ [
										...sizeUnits,
										{ value: '%', label: '%', default: 50 },
									] }
									min={ 0 }
									onChange={ ( value ) =>
										setAttributes( {
											toggle_radius: value || '',
										} )
									}
								/>
							</>
						) }
					</PanelBody>
					{ 'toggle' === display && (
						<PanelColorSettings
							title={ __(
								'Colors of the toggle',
								'easy-language'
							) }
							colorSettings={ [
								{
									label: __( 'Toggle on', 'easy-language' ),
									value: attributes.toggle_color_on,
									onChange: ( value ) =>
										setAttributes( {
											toggle_color_on: value || '',
										} ),
								},
								{
									label: __( 'Toggle off', 'easy-language' ),
									value: attributes.toggle_color_off,
									onChange: ( value ) =>
										setAttributes( {
											toggle_color_off: value || '',
										} ),
								},
								{
									label: __( 'Thumb', 'easy-language' ),
									value: attributes.toggle_color_thumb,
									onChange: ( value ) =>
										setAttributes( {
											toggle_color_thumb: value || '',
										} ),
								},
							] }
						/>
					) }
					<PanelBody
						title={ __( 'Visibility', 'easy-language' ) }
						initialOpen={ 'all' !== attributes.visibility }
					>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Visibility', 'easy-language' ) }
							value={ attributes.visibility }
							options={ [
								{
									label: __(
										'Show everywhere',
										'easy-language'
									),
									value: 'all',
								},
								{
									label: __(
										'Show only on chosen pages and terms',
										'easy-language'
									),
									value: 'include',
								},
								{
									label: __(
										'Show everywhere except on chosen pages and terms',
										'easy-language'
									),
									value: 'exclude',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { visibility: value } )
							}
						/>
						{ 'all' !== attributes.visibility && (
							<>
								<ObjectPicker
									value={ visibilityObjects }
									onChange={ ( value ) =>
										setAttributes( {
											visibility_objects: value,
										} )
									}
								/>
								<ToggleControl
									__nextHasNoMarginBottom
									label={ __(
										'Include child pages and child terms',
										'easy-language'
									) }
									checked={ attributes.visibility_children }
									onChange={ ( value ) =>
										setAttributes( {
											visibility_children: value,
										} )
									}
								/>
								<ToggleControl
									__nextHasNoMarginBottom
									label={ __(
										'Include contents assigned to the chosen terms',
										'easy-language'
									) }
									checked={ attributes.visibility_assigned }
									onChange={ ( value ) =>
										setAttributes( {
											visibility_assigned: value,
										} )
									}
								/>
							</>
						) }
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'On pages without own language versions',
								'easy-language'
							) }
							help={ __(
								'For example archives, search results or the blog homepage.',
								'easy-language'
							) }
							value={ attributes.fallback }
							options={ [
								{
									label: __(
										'Automatic (like until now)',
										'easy-language'
									),
									value: 'auto',
								},
								{
									label: __(
										'Link to the homepages',
										'easy-language'
									),
									value: 'home',
								},
								{
									label: __( 'Hide', 'easy-language' ),
									value: 'hide',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { fallback: value } )
							}
						/>
						<p className="description">
							{ __(
								'In the editor the language switcher is always visible.',
								'easy-language'
							) }
						</p>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender
					block={ blockName }
					attributes={ attributes }
					httpMethod="POST"
					skipBlockSupportAttributes
				/>
			</div>
		);
	};

/**
 * Return the variations for our blocks.
 *
 * The default variation marks new blocks with the actual markup version. Existing blocks without this attribute
 * keep their previous output.
 *
 * @param {string} title The title of the block.
 * @return {Array} List of variations.
 */
export const getVariations = ( title ) => [
	{
		name: 'easy-language-switcher-default',
		title,
		isDefault: true,
		attributes: { markup_version: 2 },
		scope: [ 'inserter' ],
	},
];
