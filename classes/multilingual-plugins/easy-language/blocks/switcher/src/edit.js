/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * Add individual dependencies.
 */
import {
	PanelBody,
	ToggleControl,
} from '@wordpress/components';
import {
	InspectorControls,
	useBlockProps
} from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import {
	onChangeShowIcons,
	onChangeHideActualLanguage
} from '../../components'

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @param object
 * @return {WPElement} Element to render.
 */
export default function edit( object ) {
	/**
	 * Collect return for the edit-function
	 */
	return (
		< div { ...useBlockProps() } >
			< InspectorControls >
				< PanelBody title = { __( 'Settings', 'easy-language' ) } >
					< ToggleControl
						label     = { __( 'Show icons', 'easy-language' ) }
						checked   = { object.attributes.show_icons }
						onChange  = { value => onChangeShowIcons( value, object ) }
					/ >
					< ToggleControl
						label     = { __( 'Hide actual language', 'easy-language' ) }
						checked   = { object.attributes.hide_actual_language }
						onChange  = { value => onChangeHideActualLanguage( value, object ) }
					/ >
				< / PanelBody >
			< / InspectorControls >
			< ServerSideRender
				block             = "easy-language/switcher"
				attributes        = { object.attributes }
				httpMethod        = "POST"
			/ >
		< / div >
	);
}
